<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/ProdutividadeService.php';

final class CartorioProdutividade
{
    /** Rótulo para observações de guias (ex.: "Guia de Execução Definitiva"). */
    public static function labelTipoGuia(string $tipo): string
    {
        $t = trim($tipo);
        if ($t === '') {
            return 'Guia';
        }
        if (preg_match('/^guia\b/iu', $t) === 1) {
            return $t;
        }
        return 'Guia de ' . $t;
    }

    /** Classe processual da carta para texto; padrão "Carta Precatória" se ausente. */
    public static function labelClasseCarta(?string $nomeClasse): string
    {
        $t = trim((string) $nomeClasse);
        if ($t === '' || strcasecmp($t, 'Classe não informada') === 0) {
            return 'Carta Precatória';
        }
        return $t;
    }

    /** Rótulo para ofícios (ex.: "Ofício de Intimação"). */
    public static function labelTipoOficio(string $tipo): string
    {
        $t = trim($tipo);
        if ($t === '') {
            return 'Ofício';
        }
        if (preg_match('/^ofício\b/iu', $t) === 1) {
            return $t;
        }
        return 'Ofício de ' . $t;
    }

    /** Evita placeholder vazio em textos de produtividade. */
    public static function nonEmpty(?string $value, string $fallback): string
    {
        $t = trim((string) $value);
        return $t !== '' ? $t : $fallback;
    }

    /** Texto para observação (valor persistido em ofícios: email, malote_digital, pessoalmente). */
    public static function labelMeioEnvio(?string $meio): string
    {
        $m = trim((string) $meio);
        return match ($m) {
            'email' => 'e-mail',
            'malote_digital' => 'malote digital',
            'pessoalmente' => 'pessoalmente',
            '' => 'meio não informado',
            default => $m,
        };
    }

    /**
     * Pontos para conclusão de geração de PDF: 1 fixo + 0,1 por documento informado.
     * Ex.: 10 documentos → 1 + 1,0 = 2,0.
     */
    public static function pontosFormulaGeracaoPdf(int $numDocumentos): float
    {
        $n = max(0, $numDocumentos);
        return round(1.0 + 0.1 * $n, 2);
    }

    public static function record(PDO $connection, string $actorUserId, string $eventCode, array $context = []): void
    {
        $actorUserId = trim($actorUserId);
        if ($actorUserId === '' || strcasecmp($actorUserId, 'sistema') === 0) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $actorUserId = trim((string) ($_SESSION['id_usuario'] ?? ''));
            }
        }
        if ($actorUserId === '' || strcasecmp($actorUserId, 'sistema') === 0) {
            return;
        }
        $registry = self::loadRegistry();
        if (!isset($registry[$eventCode])) {
            error_log('CartorioProdutividade: event_code desconhecido: ' . $eventCode);
            return;
        }
        $def = $registry[$eventCode];
        $texts = self::loadTexts();
        $tpl = (string) ($texts[$eventCode]['observacao'] ?? '');
        $observacoes = self::interpolate($tpl, $context);
        $quantidade = (int) ($context['quantidade'] ?? $def['quantidade_default'] ?? 1);
        if ($quantidade <= 0) {
            $quantidade = 1;
        }
        $svc = new ProdutividadeService($connection);
        $payload = [
            'tipo_ato' => (string) ($def['tipo_ato'] ?? 'Cartório'),
            'tipo_documento' => (string) ($def['tipo_documento'] ?? $eventCode),
            'sistema_modulo' => (string) ($def['sistema_modulo'] ?? ''),
            'quantidade' => $quantidade,
            'referencia_item_id' => trim((string) ($context['referencia_item_id'] ?? '')),
            'referencia_sistema' => trim((string) ($context['referencia_sistema'] ?? $def['referencia_sistema'] ?? $def['sistema_modulo'] ?? '')),
            'numero_processo' => trim((string) ($context['numero_processo'] ?? '')),
            'classe_processo' => trim((string) ($context['classe_processo'] ?? '')),
            'observacoes' => $observacoes,
            'data_hora_ato' => trim((string) ($context['data_hora_ato'] ?? '')) ?: date('Y-m-d H:i:s'),
        ];
        $regraIds = self::loadRegraIds();
        if (isset($regraIds[$eventCode])) {
            $rid = trim((string) $regraIds[$eventCode]);
            if ($rid !== '') {
                $payload['regra_id'] = $rid;
            }
        }
        if (self::eventoUsaFormulaGeracaoPdf($eventCode)) {
            $payload['quantidade'] = 1;
            $nDocs = self::extrairContagemDocumentosPdf($eventCode, $context);
            $payload['pontos_calculados_override'] = self::pontosFormulaGeracaoPdf($nDocs);
        }
        try {
            $result = $svc->registrarAuto($actorUserId, $payload);
            if (empty($result['created'])) {
                $reason = (string) ($result['reason'] ?? '');
                if ($reason !== 'duplicado') {
                    error_log(sprintf(
                        'CartorioProdutividade::record sem lançamento (event=%s, reason=%s, usuario=%s)',
                        $eventCode,
                        $reason,
                        $actorUserId
                    ));
                }
            }
        } catch (Throwable $e) {
            error_log('CartorioProdutividade::record falhou: ' . $e->getMessage());
        }
    }

    public static function mirrorEstornoSaldo(PDO $connection, string $actorUserId, string $referenciaItemId, string $referenciaSistema, string $motivoObs = ''): void
    {
        $actorUserId = trim($actorUserId);
        $referenciaItemId = trim($referenciaItemId);
        $referenciaSistema = trim($referenciaSistema);
        if ($actorUserId === '' || $actorUserId === 'sistema' || $referenciaItemId === '' || $referenciaSistema === '') {
            return;
        }
        $svc = new ProdutividadeService($connection);
        try {
            $svc->registrarEstornoSaldoReferencia($actorUserId, $referenciaItemId, $referenciaSistema, $motivoObs);
        } catch (Throwable $e) {
            error_log('CartorioProdutividade::mirrorEstornoSaldo falhou: ' . $e->getMessage());
        }
    }


    public static function mirrorEstornoSaldoSession(PDO $connection, string $referenciaItemId, string $referenciaSistema, string $motivoObs = ''): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $uid = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
        self::mirrorEstornoSaldo($connection, $uid, $referenciaItemId, $referenciaSistema, $motivoObs);
    }

    public static function loadRegistry(): array
    {
        $path = __DIR__ . '/../config/cartorio-produtividade-registry.php';
        if (!is_file($path)) {
            return [];
        }
        $data = require $path;
        return is_array($data) ? $data : [];
    }

    /**
     * @return array<string, string>
     */
    public static function loadRegraIds(): array
    {
        $path = __DIR__ . '/../config/cartorio-produtividade-regra-ids.php';
        if (!is_file($path)) {
            return [];
        }
        $data = require $path;
        return is_array($data) ? $data : [];
    }

    public static function loadTexts(): array
    {
        $path = __DIR__ . '/../config/cartorio-produtividade-list-texts.php';
        if (!is_file($path)) {
            return [];
        }
        $data = require $path;
        return is_array($data) ? $data : [];
    }

    private static function interpolate(string $template, array $context): string
    {
        $out = $template;
        foreach ($context as $key => $value) {
            if (!is_scalar($value) && $value !== null) {
                continue;
            }
            $rep = $value === null ? '' : (string) $value;
            $out = str_replace('{' . $key . '}', $rep, $out);
        }
        return trim($out);
    }

    private static function eventoUsaFormulaGeracaoPdf(string $eventCode): bool
    {
        return str_ends_with($eventCode, '.gerar_pdf')
            || str_contains($eventCode, '.aguardando_gerar_pdf')
            || str_ends_with($eventCode, '.gerar_pdf_decisao');
    }

    /**
     * Contagem de documentos para a fórmula de PDF. Chaves comuns no payload da subtarefa: qtd_docs (guias), qtd_docs_gerados (cartas expedidas).
     */
    private static function extrairContagemDocumentosPdf(string $eventCode, array $context): int
    {
        if (array_key_exists('pdf_documentos_count', $context)) {
            return max(0, (int) $context['pdf_documentos_count']);
        }
        foreach (['qtd_docs', 'qtd_docs_gerados', 'quantidade_documentos', 'num_documentos'] as $k) {
            if (!array_key_exists($k, $context)) {
                continue;
            }
            $n = (int) $context[$k];
            if ($n > 0) {
                return $n;
            }
        }
        if (str_ends_with($eventCode, '.gerar_pdf_decisao')) {
            return 1;
        }

        return 0;
    }
}
