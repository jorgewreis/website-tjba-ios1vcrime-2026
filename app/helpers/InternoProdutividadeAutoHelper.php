<?php

declare(strict_types=1);

require_once __DIR__ . '/../services/ProdutividadeService.php';

/**
 * Lançamentos automáticos de produtividade para fluxos internos (fora do registry cartório).
 */
final class InternoProdutividadeAutoHelper
{
    /**
     * Chave em config/toast_messages.php (interno.produtividade.*) para toast informativo após lançamento.
     */
    private static function toastCatalogKeyForRule(string $ruleKey): ?string
    {
        $map = [
            'consulta_partes_list' => 'interno.produtividade.consulta_partes.ponto',
            'consulta_processos_list' => 'interno.produtividade.consulta_processos_lista.ponto',
            'consulta_processos_get' => 'interno.produtividade.consulta_processos_detalhe.ponto',
            'audiencia_parte_cadastro' => 'interno.produtividade.audiencia_parte_cadastro.ponto',
            'audiencia_parte_edicao' => 'interno.produtividade.audiencia_parte_edicao.ponto',
            'processo_prioridade_add' => 'interno.produtividade.prioridade_add.ponto',
        ];

        return $map[$ruleKey] ?? null;
    }

    /**
     * @param PDO    $connection
     * @param string $usuarioId      id_usuario da sessão
     * @param string $ruleKey        chave em app/config/interno-produtividade-regras.php
     * @param string $referenciaItemId identificador curto para dedupe (hash inclui minuto no serviço)
     * @param string $observacoes      texto para listagem (opcional)
     * @param string $numeroProcesso   número CNJ ou equivalente gravado em `produtividade_lancamentos.numero_processo` (opcional)
     *
     * @return array{created: bool, toast_key: string|null}
     */
    public static function tryRegistrar(PDO $connection, string $usuarioId, string $ruleKey, string $referenciaItemId, string $observacoes = '', string $numeroProcesso = ''): array
    {
        $usuarioId = trim($usuarioId);
        $referenciaItemId = trim($referenciaItemId);
        if ($usuarioId === '' || $referenciaItemId === '') {
            return ['created' => false, 'toast_key' => null];
        }

        static $configs = null;
        if ($configs === null) {
            $path = __DIR__ . '/../config/interno-produtividade-regras.php';
            $configs = is_file($path) ? require $path : [];
        }

        if (!is_array($configs)) {
            return ['created' => false, 'toast_key' => null];
        }

        $def = $configs[$ruleKey] ?? null;
        if (!is_array($def)) {
            return ['created' => false, 'toast_key' => null];
        }

        $regraId = trim((string) ($def['regra_id'] ?? ''));
        if ($regraId === '') {
            return ['created' => false, 'toast_key' => null];
        }

        $obs = trim($observacoes);
        $toastKey = self::toastCatalogKeyForRule($ruleKey);

        try {
            $service = new ProdutividadeService($connection);
            $payload = [
                'regra_id' => $regraId,
                'tipo_ato' => trim((string) ($def['tipo_ato'] ?? '')),
                'tipo_documento' => trim((string) ($def['tipo_documento'] ?? '')),
                'sistema_modulo' => trim((string) ($def['sistema_modulo'] ?? '')),
                'quantidade' => 1,
                'referencia_item_id' => $referenciaItemId,
                'referencia_sistema' => trim((string) ($def['referencia_sistema'] ?? $def['sistema_modulo'] ?? '')),
            ];
            if ($obs !== '') {
                $payload['observacoes'] = $obs;
            }
            $np = trim($numeroProcesso);
            if ($np !== '') {
                $payload['numero_processo'] = $np;
            }
            $result = $service->registrarAuto($usuarioId, $payload);
            if (!empty($result['created'])) {
                return ['created' => true, 'toast_key' => $toastKey];
            }
        } catch (Throwable $ignored) {
            // Não interromper o fluxo principal.
        }

        return ['created' => false, 'toast_key' => null];
    }
}
