<?php
declare(strict_types=1);

require_once __DIR__ . '/DataJudService.php';
require_once __DIR__ . '/MniService.php';

/**
 * Façade que orquestra DataJud (1ª fonte: rápida/pública) + MNI (enriquecimento autenticado).
 *
 * Estratégia desta primeira versão (Fase 5 — sem expor ainda às telas):
 *   - lookup($cnj): executa DataJud em primeiro lugar (síncrono). Se ele tiver sucesso, devolve
 *     imediatamente o shape DataJud e marca `enrichment_pending=true`. O MNI é executado em sequência
 *     no mesmo request (não há job assíncrono no projeto) e o resultado é mesclado em `data.enrichment`.
 *   - Falha do MNI nunca afeta a resposta principal: vai apenas em `enrichment_error`.
 *   - Persistência de dados extras (partes, órgão julgador, movimentos) NÃO é feita aqui ainda —
 *     fica para uma fase 5.1 depois que a Fase 3 (relatório de campos) for assinada.
 *
 * Interface estável para a Fase 6 (front opt-in):
 *   $service = new ProcessoLookupService($datajudConfig);
 *   $result = $service->lookup($cnj, ['mni' => true]);
 *   // $result['data'] = shape DataJud "tradicional"
 *   // $result['enrichment'] = shape MNI (mesclado) OU null se desligado/erro
 *   // $result['enrichment_error'] = {kind, message} se MNI falhou
 */
final class ProcessoLookupService
{
    private DataJudService $datajud;
    private ?MniService $mni;

    public function __construct(array $datajudConfig, ?MniService $mni = null)
    {
        $this->datajud = new DataJudService($datajudConfig);
        $this->mni = $mni;
    }

    /**
     * @param array{mni?: bool, mni_options?: array<string, mixed>} $opts
     * @return array{
     *   data: array<string, mixed>,
     *   enrichment: array<string, mixed>|null,
     *   enrichment_error: array{kind: string, message: string}|null,
     *   sources: array{datajud: bool, mni: bool},
     *   meta: array<string, mixed>
     * }
     */
    public function lookup(string $cnj, array $opts = []): array
    {
        $digits = CnjValidator::digits20OrThrow($cnj);
        $useMni = !empty($opts['mni']);

        $dataDatajud = $this->datajud->searchProcesso($digits);

        $enrichment = null;
        $enrichmentError = null;
        $mniMeta = [];

        if ($useMni) {
            try {
                $mni = $this->mni ?? new MniService();
                $resMni = $mni->consultar($digits, (array) ($opts['mni_options'] ?? []));
                $mniMeta = $resMni['meta'] ?? [];

                if ($resMni['ok']) {
                    $enrichment = $this->mergeForEnrichment($dataDatajud, $resMni['shape']);
                } else {
                    $enrichmentError = [
                        'kind'    => (string) ($resMni['error']['kind'] ?? 'unknown'),
                        'message' => (string) ($resMni['error']['message'] ?? 'Erro desconhecido no MNI.'),
                    ];
                }
            } catch (Throwable $e) {
                $enrichmentError = [
                    'kind'    => 'unknown',
                    'message' => 'Exceção ao consultar MNI: ' . $e->getMessage(),
                ];
            }
        }

        return [
            'data'              => $dataDatajud,
            'enrichment'        => $enrichment,
            'enrichment_error'  => $enrichmentError,
            'sources'           => [
                'datajud' => true,
                'mni'     => $useMni && $enrichment !== null,
            ],
            'meta' => [
                'mni' => $mniMeta,
            ],
        ];
    }

    /**
     * Constrói um payload de "enrichment" combinando o que o MNI sabe a mais que o DataJud.
     * Mantém intacto o shape DataJud original (em $datajud) — quem usa decide o que adotar.
     *
     * @param array<string, mixed> $datajud shape DataJud
     * @param array<string, mixed>|null $mniShape shape MNI (já comum)
     * @return array<string, mixed>
     */
    private function mergeForEnrichment(array $datajud, ?array $mniShape): array
    {
        if ($mniShape === null) {
            return [];
        }

        $extras = [];

        if ($this->preferMniClasse($datajud, $mniShape)) {
            $extras['classe'] = $mniShape['classe'];
        }
        if ($this->preferMniAssuntos($datajud, $mniShape)) {
            $extras['assuntos'] = $mniShape['assuntos'];
        }

        $extras['polo_passivo_detalhado']  = $mniShape['mni']['partes']['passivo']    ?? [];
        $extras['polo_ativo_detalhado']    = $mniShape['mni']['partes']['ativo']      ?? [];
        $extras['terceiros']               = $mniShape['mni']['partes']['terceiro']   ?? [];
        $extras['fiscais']                 = $mniShape['mni']['partes']['fiscal']     ?? [];
        $extras['testemunhas']             = $mniShape['mni']['partes']['testemunha'] ?? [];
        $extras['amicus_curiae']           = $mniShape['mni']['partes']['amicus']     ?? [];
        $extras['vitimas']                 = $mniShape['mni']['partes']['vitima']     ?? [];
        $extras['outras_partes']           = $mniShape['mni']['partes']['outros']     ?? [];
        $extras['orgao_julgador_mni']      = $mniShape['mni']['orgaoJulgador']        ?? [];
        $extras['data_ajuizamento_mni']    = $mniShape['mni']['dataAjuizamento']      ?? null;
        $extras['movimentos_mni']          = $mniShape['movimentos']                  ?? [];
        $extras['movimentos_total_mni']    = $mniShape['mni']['totalMovimentos']      ?? 0;
        $extras['documentos_metadados']    = $mniShape['mni']['documentos']           ?? [];

        // Enriquecimentos exclusivos do MNI v3 (DataJud nao traz):
        $cab = $mniShape['mni']['cabecalho'] ?? [];
        if (!empty($cab['prioridade'])) {
            $extras['prioridade_mni'] = $cab['prioridade'];
        }
        if (isset($cab['nivel_sigilo']) && $cab['nivel_sigilo'] !== null) {
            $extras['nivel_sigilo'] = $cab['nivel_sigilo'];
            $extras['nivel_sigilo_label'] = $cab['nivel_sigilo_label'] ?? null;
        }
        if (isset($cab['valor_causa']) && $cab['valor_causa'] !== null) {
            $extras['valor_causa'] = $cab['valor_causa'];
        }
        if (!empty($cab['magistrados_atuantes'])) {
            $extras['magistrados_atuantes'] = $cab['magistrados_atuantes'];
        }
        if (isset($cab['pedido_liminar_pendente']) && $cab['pedido_liminar_pendente'] !== null) {
            $extras['pedido_liminar_pendente'] = $cab['pedido_liminar_pendente'];
        }
        if (isset($cab['processo_fisico']) && $cab['processo_fisico'] !== null) {
            $extras['processo_fisico'] = $cab['processo_fisico'];
        }
        if (isset($cab['intervencao_mp']) && $cab['intervencao_mp'] !== null) {
            $extras['intervencao_mp'] = $cab['intervencao_mp'];
        }
        if (!empty($cab['historico_deslocamento'])) {
            $extras['historico_deslocamento'] = $cab['historico_deslocamento'];
        }
        if (!empty($cab['processo_vinculado'])) {
            $extras['processo_vinculado'] = $cab['processo_vinculado'];
        }
        if (!empty($cab['outros_numeros'])) {
            $extras['outros_numeros'] = $cab['outros_numeros'];
        }

        return $extras;
    }

    private function preferMniClasse(array $datajud, array $mniShape): bool
    {
        $djCodigo = trim((string) ($datajud['classe']['codigo'] ?? ''));
        $mniCodigo = trim((string) ($mniShape['classe']['codigo'] ?? ''));
        if ($djCodigo === '' && $mniCodigo !== '') {
            return true;
        }
        return false;
    }

    private function preferMniAssuntos(array $datajud, array $mniShape): bool
    {
        $djQtd = is_array($datajud['assuntos'] ?? null) ? count($datajud['assuntos']) : 0;
        $mniQtd = is_array($mniShape['assuntos'] ?? null) ? count($mniShape['assuntos']) : 0;
        return $mniQtd > $djQtd;
    }
}
