<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/SalaAudienciasPautaRepository.php';
require_once __DIR__ . '/../repositories/SalaAudienciasPautaParteRepository.php';
require_once __DIR__ . '/../repositories/PartesRepository.php';
require_once __DIR__ . '/../repositories/PartesTelefonesRepository.php';
require_once __DIR__ . '/../repositories/PartesInstituicoesRepository.php';
require_once __DIR__ . '/../repositories/PartesDocumentosRepository.php';
require_once __DIR__ . '/ParteDocumentoHelper.php';
require_once __DIR__ . '/PrioridadesProcessuaisCatalog.php';
require_once __DIR__ . '/../services/SalaAudienciasPautaService.php';

/**
 * Constrói o payload e os renderizadores (TXT/PDF) do resumo da audiência.
 *
 * - TXT: mantém literal os marcadores estilo WhatsApp (`*bold*`, `_italic_`,
 *   `~strike~`, `` `mono` ``, ```` ```block``` ````, listas com `-` ou `*`,
 *   quote com `>` etc.), para que o usuário possa colar diretamente no
 *   WhatsApp Web/mobile sem perder a formatação.
 * - PDF: converte os mesmos marcadores em HTML semântico antes de chamar o
 *   Dompdf, garantindo apresentação rica sem deixar markup cru visível.
 */
final class AudienciaResumoBuilder
{
    /** Títulos exibidos por tipo de parte, na ordem desejada. */
    private const TITULOS_POR_TIPO = [
        'reu' => 'DENUNCIADO(S)',
        'vitima' => 'VÍTIMA(S)',
        'testemunha_denuncia' => 'TESTEMUNHA(S) DA DENÚNCIA',
        'testemunha_defesa' => 'TESTEMUNHA(S) DA DEFESA',
        'outro' => 'OUTROS',
    ];

    /**
     * Coleta os dados necessários para o resumo da audiência.
     *
     * @return array{
     *   id_pauta:string,
     *   numero_processo:string,
     *   classe:string,
     *   tipo_audiencia:string,
     *   data_hora:string,
     *   prioridades:list<string>,
     *   status:string,
     *   grupos:array<string, list<array{
     *     status:string,
     *     nome:string,
     *     cargo_orgao:string,
     *     defensor:string,
     *     contatos:string
     *   }>>
     * }
     */
    public static function buildPayload(PDO $pdo, string $idPauta): array
    {
        $idPauta = trim($idPauta);
        if ($idPauta === '') {
            throw new InvalidArgumentException('ID da audiência é obrigatório.');
        }

        $service = new SalaAudienciasPautaService($pdo);
        $pauta = $service->find($idPauta);
        if ($pauta === null) {
            throw new InvalidArgumentException('Audiência não encontrada.');
        }

        $partesRepo = new PartesRepository($pdo);
        $documentosRepo = new PartesDocumentosRepository($pdo);
        $telefonesRepo = new PartesTelefonesRepository($pdo);
        $instituicoesRepo = new PartesInstituicoesRepository($pdo);

        // --- Cabeçalho ----------------------------------------------------
        $numeroProcesso = trim((string) ($pauta['numero_processo'] ?? ''));
        $classe = trim((string) ($pauta['classe_nome'] ?? ''));
        $tipoAud = trim((string) ($pauta['tipo_audiencia'] ?? ''));
        $statusAud = strtolower(trim((string) ($pauta['status'] ?? '')));

        $dataAud = trim((string) ($pauta['data_audiencia'] ?? ''));
        $horaAud = substr((string) ($pauta['hora_audiencia'] ?? ''), 0, 5);
        $dataHora = '';
        if ($dataAud !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataAud)) {
            $dataHora = date('d/m/Y', strtotime($dataAud));
            if ($horaAud !== '') {
                $dataHora .= ' às ' . $horaAud;
            }
        }

        $prioridades = [];
        try {
            $estado = $service->listPrioridadesEdicaoAudiencia($idPauta);
            if (is_array($estado) && isset($estado['prioridades_labels']) && is_array($estado['prioridades_labels'])) {
                foreach ($estado['prioridades_labels'] as $label) {
                    $label = trim((string) $label);
                    if ($label !== '') {
                        $prioridades[] = $label;
                    }
                }
            }
        } catch (Throwable $e) {
            $prioridades = [];
        }

        // --- Partes -------------------------------------------------------
        $partes = $service->listPartes($idPauta);

        $grupos = [];
        foreach (self::TITULOS_POR_TIPO as $tipo => $titulo) {
            $grupos[$titulo] = [];
        }

        foreach ($partes as $parte) {
            $tipo = strtolower(trim((string) ($parte['tipo_parte'] ?? '')));
            if (!isset(self::TITULOS_POR_TIPO[$tipo])) {
                continue;
            }
            $titulo = self::TITULOS_POR_TIPO[$tipo];

            $parteGlobalId = trim((string) ($parte['parte_id_global'] ?? ''));
            $parteGlobalCadastro = $parteGlobalId !== '' ? $partesRepo->findById($parteGlobalId) : null;

            $contatos = self::montarContatosParte($parte, $parteGlobalId, $telefonesRepo);
            $cargoOrgao = '';
            if ($tipo === 'testemunha_denuncia' && $parteGlobalId !== '') {
                $cargoOrgao = self::montarCargoOrgaoTestemunhaPolicial($parteGlobalId, $instituicoesRepo);
            }

            $defensor = '';
            if ($tipo === 'reu' || $tipo === 'vitima') {
                $defensor = self::montarDefensor($parte, $partesRepo, $documentosRepo);
            }

            $statusIntimacao = self::montarStatusIntimacao($parte);

            $grupos[$titulo][] = [
                'status' => $statusIntimacao,
                'nome' => trim((string) ($parte['nome_parte'] ?? '')),
                'cargo_orgao' => $cargoOrgao,
                'defensor' => $defensor,
                'contatos' => $contatos,
            ];
        }

        return [
            'id_pauta' => $idPauta,
            'numero_processo' => $numeroProcesso,
            'classe' => $classe,
            'tipo_audiencia' => $tipoAud,
            'data_hora' => $dataHora,
            'prioridades' => $prioridades,
            'status' => $statusAud,
            'grupos' => $grupos,
        ];
    }

    /**
     * Texto plano com marcadores WhatsApp literais. Inicia com BOM UTF-8 e
     * usa CRLF para compatibilidade ampla (notepad, WhatsApp Web).
     *
     * @param array<string, mixed> $payload
     */
    public static function renderTxt(array $payload): string
    {
        $linhas = self::montarLinhasBase($payload);
        $body = implode("\r\n", $linhas);

        return "\xEF\xBB\xBF" . $body . "\r\n";
    }

    /**
     * HTML formatado para Dompdf. Converte marcadores WhatsApp em HTML.
     *
     * @param array<string, mixed> $payload
     */
    public static function renderHtmlForPdf(array $payload): string
    {
        $linhas = self::montarLinhasBase($payload);
        $corpoWhats = implode("\n", $linhas);
        $corpoHtml = self::whatsappToHtml($corpoWhats);

        $titulo = 'Resumo da audiência';
        $tituloEsc = htmlspecialchars($titulo, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{$tituloEsc}</title>
    <style>
        @page { margin: 18mm 16mm; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 11pt; color: #1f2933; line-height: 1.45; }
        h1.resumo-title { font-size: 14pt; margin: 0 0 12px 0; color: #102a43; }
        .resumo-body { white-space: normal; }
        .resumo-body strong { color: #102a43; }
        .resumo-body em { font-style: italic; color: #334e68; }
        .resumo-body s { color: #829ab1; }
        .resumo-body code { font-family: DejaVu Sans Mono, Courier New, monospace; background: #f0f4f8; padding: 0 3px; border-radius: 3px; }
        .resumo-body pre { font-family: DejaVu Sans Mono, Courier New, monospace; background: #f0f4f8; padding: 8px 10px; border-radius: 4px; overflow-x: auto; }
        .resumo-body blockquote { border-left: 3px solid #bcccdc; margin: 4px 0 4px 4px; padding: 2px 10px; color: #486581; background: #f7fafc; }
        .resumo-body ul, .resumo-body ol { margin: 4px 0 4px 18px; padding: 0; }
        .resumo-body li { margin: 2px 0; }
        .resumo-body p { margin: 2px 0; }
        .resumo-body .line { margin: 0; }
    </style>
</head>
<body>
    <h1 class="resumo-title">{$tituloEsc}</h1>
    <div class="resumo-body">{$corpoHtml}</div>
</body>
</html>
HTML;
    }

    /**
     * Converte texto com marcação estilo WhatsApp em HTML seguro.
     *
     * Suporta:
     * - bold: `*texto*`
     * - italic: `_texto_`
     * - strikethrough: `~texto~`
     * - monoespaçado inline: `` `texto` ``
     * - bloco monoespaçado: ```` ```texto``` ````
     * - listas: linhas iniciadas com `- ` ou `* ` (não-ordenada) e `N. ` (ordenada)
     * - citações: linhas iniciadas com `> `
     * - quebras de linha viram `<br>`
     *
     * A entrada é escapada antes de quaisquer substituições para evitar HTML
     * arbitrário; as tags geradas pelo conversor sobrevivem ao escape porque
     * são inseridas depois.
     */
    public static function whatsappToHtml(string $txt): string
    {
        $txt = str_replace(["\r\n", "\r"], "\n", $txt);
        $txt = htmlspecialchars($txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Bloco ```...``` (multilinha). Aplicado antes para escapar o conteúdo
        // do tratamento de listas/quotes/bold/italic.
        $blocos = [];
        $txt = preg_replace_callback('/```(.+?)```/s', static function (array $m) use (&$blocos): string {
            $key = '___WAB_' . count($blocos) . '___';
            $blocos[$key] = '<pre><code>' . $m[1] . '</code></pre>';
            return $key;
        }, $txt) ?? $txt;

        // Mono inline `texto`
        $monoInline = [];
        $txt = preg_replace_callback('/`([^`\n]+?)`/', static function (array $m) use (&$monoInline): string {
            $key = '___WAM_' . count($monoInline) . '___';
            $monoInline[$key] = '<code>' . $m[1] . '</code>';
            return $key;
        }, $txt) ?? $txt;

        // Quebra em linhas para tratar listas e citações.
        $linhas = explode("\n", $txt);
        $out = [];
        $inUl = false;
        $inOl = false;
        $closeListsIfOpen = static function () use (&$inUl, &$inOl, &$out): void {
            if ($inUl) { $out[] = '</ul>'; $inUl = false; }
            if ($inOl) { $out[] = '</ol>'; $inOl = false; }
        };

        foreach ($linhas as $linha) {
            if (preg_match('/^(\s*)([-*])\s+(.+)$/u', $linha, $m)) {
                if (!$inUl) {
                    if ($inOl) { $out[] = '</ol>'; $inOl = false; }
                    $out[] = '<ul>';
                    $inUl = true;
                }
                $out[] = '<li>' . self::applyInlineWhatsappTags($m[3]) . '</li>';
                continue;
            }
            if (preg_match('/^(\s*)\d+\.\s+(.+)$/u', $linha, $m)) {
                if (!$inOl) {
                    if ($inUl) { $out[] = '</ul>'; $inUl = false; }
                    $out[] = '<ol>';
                    $inOl = true;
                }
                $out[] = '<li>' . self::applyInlineWhatsappTags($m[2]) . '</li>';
                continue;
            }
            $closeListsIfOpen();
            if (preg_match('/^\s*&gt;\s?(.*)$/u', $linha, $m)) {
                $out[] = '<blockquote>' . self::applyInlineWhatsappTags($m[1]) . '</blockquote>';
                continue;
            }
            if (trim($linha) === '') {
                $out[] = '<br>';
                continue;
            }
            $out[] = '<p class="line">' . self::applyInlineWhatsappTags($linha) . '</p>';
        }
        $closeListsIfOpen();

        $resultado = implode("\n", $out);
        $resultado = strtr($resultado, $monoInline);
        $resultado = strtr($resultado, $blocos);

        return $resultado;
    }

    // ------------------------------------------------------------------
    // Helpers internos
    // ------------------------------------------------------------------

    /**
     * Monta o corpo principal (mesma estrutura para TXT e PDF), com
     * marcadores WhatsApp literais. O renderizador de PDF aplica
     * `whatsappToHtml` em cima; o de TXT mantém literal.
     *
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private static function montarLinhasBase(array $payload): array
    {
        $linhas = [];
        $linhas[] = '*' . self::naoVazio((string) ($payload['numero_processo'] ?? ''), 'Processo não informado') . '*';
        $linhas[] = '*' . self::naoVazio((string) ($payload['classe'] ?? ''), 'Classe não informada') . '*';

        if (!empty($payload['prioridades']) && is_array($payload['prioridades'])) {
            $labels = array_filter(array_map('strval', $payload['prioridades']));
            if ($labels !== []) {
                $linhas[] = '*Prioridades: ' . implode(', ', $labels) . '*';
            }
        }

        $linhas[] = '*' . self::naoVazio((string) ($payload['tipo_audiencia'] ?? ''), 'Tipo de audiência não informado') . '*';
        if (trim((string) ($payload['data_hora'] ?? '')) !== '') {
            $linhas[] = '*' . (string) $payload['data_hora'] . '*';
        }

        $grupos = $payload['grupos'] ?? [];
        foreach (self::TITULOS_POR_TIPO as $tipoSlug => $titulo) {
            $partes = $grupos[$titulo] ?? [];
            if (!is_array($partes) || $partes === []) {
                continue;
            }
            $linhas[] = '';
            $linhas[] = '*' . $titulo . '*';

            foreach ($partes as $p) {
                $status = (string) ($p['status'] ?? '');
                $nome = (string) ($p['nome'] ?? '');
                $cargo = (string) ($p['cargo_orgao'] ?? '');
                $contatos = (string) ($p['contatos'] ?? '');
                $defensor = (string) ($p['defensor'] ?? '');

                $prefixoStatus = $status !== '' ? $status . ' ' : '';
                $linhas[] = $prefixoStatus . '- *' . $nome . '*';
                if ($cargo !== '') {
                    $linhas[] = '   _' . $cargo . '_';
                }
                if ($contatos !== '') {
                    $linhas[] = '   ' . $contatos;
                }
                if ($defensor !== '') {
                    $linhas[] = '--- ' . $defensor;
                }
            }
        }

        return $linhas;
    }

    private static function naoVazio(string $valor, string $fallback): string
    {
        $valor = trim($valor);
        return $valor !== '' ? $valor : $fallback;
    }

    /**
     * Combina o campo livre `contatos` da parte da pauta com os telefones
     * cadastrados em `partes_telefones` (quando há vínculo global), com
     * dedupe básico por normalização (mantém o primeiro).
     *
     * @param array<string, mixed> $parte
     */
    private static function montarContatosParte(array $parte, string $parteGlobalId, PartesTelefonesRepository $telefonesRepo): string
    {
        $itens = [];
        $vistos = [];
        $addItem = static function (string $valor) use (&$itens, &$vistos): void {
            $valor = trim($valor);
            if ($valor === '') {
                return;
            }
            $chave = function_exists('mb_strtolower') ? mb_strtolower($valor, 'UTF-8') : strtolower($valor);
            $chave = preg_replace('/\s+/u', ' ', $chave) ?? $chave;
            $chaveDig = preg_replace('/\D+/', '', $chave) ?? '';
            $chaveFinal = $chaveDig !== '' ? 'd:' . $chaveDig : 't:' . $chave;
            if (isset($vistos[$chaveFinal])) {
                return;
            }
            $vistos[$chaveFinal] = true;
            $itens[] = $valor;
        };

        $contatosLocal = trim((string) ($parte['contatos'] ?? ''));
        if ($contatosLocal !== '') {
            foreach (preg_split('/[;,]+/u', $contatosLocal) ?: [] as $pedaco) {
                $addItem((string) $pedaco);
            }
        }

        if ($parteGlobalId !== '') {
            foreach ($telefonesRepo->findByParteId($parteGlobalId) as $tel) {
                if ((int) ($tel['ativo'] ?? 1) !== 1) {
                    continue;
                }
                $addItem(trim((string) ($tel['numero'] ?? '')));
            }
        }

        return implode(', ', $itens);
    }

    /**
     * Para testemunha de denúncia: prefixa o cargo/função com sufixo do
     * órgão público vinculado (em estilo "Soldado PM — Polícia Militar").
     */
    private static function montarCargoOrgaoTestemunhaPolicial(string $parteGlobalId, PartesInstituicoesRepository $instituicoesRepo): string
    {
        $vinculos = $instituicoesRepo->findByParteId($parteGlobalId, true);
        if ($vinculos === []) {
            return '';
        }
        $vin = $vinculos[0];
        $cargo = trim((string) ($vin['cargo_funcao'] ?? ''));
        $orgao = trim((string) ($vin['instituicao_nome'] ?? ''));
        if ($orgao === '') {
            $orgao = trim((string) ($vin['instituicao_sigla'] ?? ''));
        }
        if ($cargo === '' && $orgao === '') {
            return '';
        }
        if ($cargo !== '' && $orgao !== '') {
            return $cargo . ' — ' . $orgao;
        }
        return $cargo !== '' ? $cargo : $orgao;
    }

    /**
     * @param array<string, mixed> $parte
     */
    private static function montarDefensor(array $parte, PartesRepository $partesRepo, PartesDocumentosRepository $documentosRepo): string
    {
        $tipoDefensor = trim((string) ($parte['tipo_defensor'] ?? ''));
        $defPubId = trim((string) ($parte['defensor_publico_id'] ?? ''));
        $defPubOutro = trim((string) ($parte['defensor_publico_outro'] ?? ''));
        $defPrivId = trim((string) ($parte['defensor_privado_id'] ?? ''));
        $defPrivOutro = trim((string) ($parte['defensor_privado_outro'] ?? ''));

        if ($tipoDefensor === 'privado') {
            if ($defPrivId === 'outro' && $defPrivOutro !== '') {
                return ParteDocumentoHelper::montarRotuloDefensorPrivado($defPrivOutro);
            }
            if ($defPrivId !== '') {
                $pAdv = null;
                $nomeAdv = $defPrivId;
                if (preg_match('/^[A-Z]{3}-[a-f0-9]{26}$/i', $defPrivId)) {
                    $pAdv = $partesRepo->findById($defPrivId);
                    if ($pAdv !== null) {
                        $nomeAdv = trim((string) ($pAdv['nome_completo'] ?? $defPrivId));
                    }
                }
                $docsAdv = $pAdv !== null
                    ? $documentosRepo->findByParteId((string) ($pAdv['id'] ?? ''))
                    : [];
                return ParteDocumentoHelper::montarRotuloDefensorPrivado($nomeAdv, $pAdv, $docsAdv);
            }
            return '';
        }

        if ($tipoDefensor === 'publico') {
            if ($defPubId === 'outro' && $defPubOutro !== '') {
                return 'Defensor público: ' . $defPubOutro;
            }
            if ($defPubId !== '') {
                $rotuloDp = $defPubId;
                if (preg_match('/^[A-Z]{3}-[a-f0-9]{26}$/i', $defPubId)) {
                    $pDp = $partesRepo->findById($defPubId);
                    if ($pDp !== null) {
                        $rotuloDp = trim((string) ($pDp['nome_completo'] ?? $defPubId));
                    }
                }
                return 'Defensor público: ' . $rotuloDp;
            }
        }

        return '';
    }

    /**
     * Regra:
     * - cumprimento = 'cumprido' + id_documento_cumprido preenchido → "OK (ID X)"
     * - cumprimento = 'cumprido' sem id_documento_cumprido           → "OK"
     * - demais casos                                                  → "PENDENTE"
     *
     * @param array<string, mixed> $parte
     */
    private static function montarStatusIntimacao(array $parte): string
    {
        $cumprimento = strtolower(trim((string) ($parte['cumprimento'] ?? '')));
        $idDoc = trim((string) ($parte['id_documento_cumprido'] ?? ''));
        if ($cumprimento === 'cumprido') {
            return $idDoc !== '' ? 'OK (ID ' . $idDoc . ')' : 'OK';
        }
        return 'PENDENTE';
    }

    private static function applyInlineWhatsappTags(string $line): string
    {
        // Bold *texto*
        $line = preg_replace(
            '/(^|[\s\(\[\{])\*(\S(?:[^*\n]*?\S)?)\*(?=$|[\s.,!?:;\)\]\}])/u',
            '$1<strong>$2</strong>',
            $line
        ) ?? $line;

        // Italic _texto_
        $line = preg_replace(
            '/(^|[\s\(\[\{])_(\S(?:[^_\n]*?\S)?)_(?=$|[\s.,!?:;\)\]\}])/u',
            '$1<em>$2</em>',
            $line
        ) ?? $line;

        // Strikethrough ~texto~
        $line = preg_replace(
            '/(^|[\s\(\[\{])~(\S(?:[^~\n]*?\S)?)~(?=$|[\s.,!?:;\)\]\}])/u',
            '$1<s>$2</s>',
            $line
        ) ?? $line;

        return $line;
    }
}
