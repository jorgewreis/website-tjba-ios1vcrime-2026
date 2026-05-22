<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/EditaisSubtarefasRepository.php';

/**
 * Textos de status da tarefa e subtarefas — editais (lista, edição, timeline, exportação).
 */
final class EditaisUiCopy
{
    public const CADASTRO_ETAPA = 'Edital cadastrado';

    public const TODAS_ETAPAS_CONCLUIDAS = 'Todas as etapas concluídas';

    public const STATUS_AGUARDANDO_PUBLICACAO = 'aguardando publicação';

    public const STATUS_AGUARDANDO_DECURSO = 'aguardando decurso';

    /** @var array<string, array{ordem: int, label: string, task_title: string, button_title: string, pending_text: string}> */
    private const ETAPAS = [
        EditaisSubtarefasRepository::TIPO_PUBLICACAO => [
            'ordem' => EditaisSubtarefasRepository::ORDEM_PUBLICACAO,
            'label' => 'Data da publicação',
            'task_title' => 'Aguardando publicação',
            'button_title' => 'Informar publicação',
            'pending_text' => 'Aguardando publicação',
        ],
        EditaisSubtarefasRepository::TIPO_DECURSO => [
            'ordem' => EditaisSubtarefasRepository::ORDEM_DECURSO,
            'label' => 'Decurso do prazo',
            'task_title' => 'Aguardando decurso do prazo',
            'button_title' => 'Certificar decurso',
            'pending_text' => 'Aguardando decurso do prazo do edital',
        ],
    ];

    /**
     * @return array{cadastro_label: string, subtasks: array<int, array{type: string, label: string, task_title: string, button_title: string, pending_text: string}>}
     */
    public static function timelineModuleConfig(): array
    {
        $subtasks = [];
        foreach (self::ETAPAS as $tipo => $def) {
            $subtasks[$def['ordem']] = [
                'type' => $tipo,
                'label' => $def['label'],
                'task_title' => $def['task_title'],
                'button_title' => $def['button_title'],
                'pending_text' => $def['pending_text'],
            ];
        }

        return [
            'cadastro_label' => self::CADASTRO_ETAPA,
            'subtasks' => $subtasks,
        ];
    }

    /** @return array<int, string> */
    public static function mapaLegendaEtapaPorOrdem(): array
    {
        $out = [];
        foreach (self::ETAPAS as $def) {
            $out[$def['ordem']] = $def['label'];
        }

        return $out;
    }

    /** @return array<string, string> */
    public static function mapaTituloTarefaPorTipo(): array
    {
        $out = [];
        foreach (self::ETAPAS as $tipo => $def) {
            $out[$tipo] = $def['task_title'];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $subtarefasAll
     */
    public static function resolverTituloTarefaListagem(string $statusPrincipal, array $subtarefasAll): string
    {
        $statusNorm = self::normalizarStatusPrincipal($statusPrincipal);
        if (in_array($statusNorm, ['concluida', 'finalizada'], true)) {
            return self::TODAS_ETAPAS_CONCLUIDAS;
        }
        if ($statusNorm === 'cancelada') {
            return 'Cancelada';
        }

        foreach ($subtarefasAll as $st) {
            if (!is_array($st)) {
                continue;
            }
            if (!EditaisSubtarefasRepository::isTipoAtivo((string) ($st['tipo'] ?? ''))) {
                continue;
            }
            $stStatus = strtolower(trim((string) ($st['status'] ?? '')));
            if ($stStatus === 'concluída') {
                $stStatus = 'concluida';
            }
            if ($stStatus === 'em_andamento') {
                return self::mapaTituloTarefaPorTipo()[(string) ($st['tipo'] ?? '')] ?? $st['tipo'] ?? '';
            }
        }

        if ($statusNorm === self::STATUS_AGUARDANDO_PUBLICACAO) {
            return self::ETAPAS[EditaisSubtarefasRepository::TIPO_PUBLICACAO]['task_title'];
        }

        if ($statusNorm === self::STATUS_AGUARDANDO_DECURSO || $statusNorm === 'em_andamento') {
            return self::ETAPAS[EditaisSubtarefasRepository::TIPO_DECURSO]['task_title'];
        }

        return self::ETAPAS[EditaisSubtarefasRepository::TIPO_PUBLICACAO]['task_title'];
    }

    public static function rotuloBadgeStatusTarefa(string $statusPrincipal): string
    {
        $statusNorm = self::normalizarStatusPrincipal($statusPrincipal);
        if (in_array($statusNorm, ['concluida', 'finalizada'], true)) {
            return 'Finalizada';
        }
        if ($statusNorm === 'cancelada') {
            return 'Cancelada';
        }
        if ($statusNorm === self::STATUS_AGUARDANDO_DECURSO) {
            return 'Aguardando decurso';
        }
        if ($statusNorm === self::STATUS_AGUARDANDO_PUBLICACAO) {
            return 'Aguardando publicação';
        }
        if ($statusNorm === 'em_andamento') {
            return 'Em andamento';
        }

        return 'Pendente';
    }

    public static function classeCssBadgeStatusTarefa(string $statusPrincipal): string
    {
        $statusNorm = self::normalizarStatusPrincipal($statusPrincipal);
        if (in_array($statusNorm, ['concluida', 'finalizada'], true)) {
            return 'status-finished';
        }
        if ($statusNorm === 'cancelada') {
            return 'status-cancelled';
        }
        if (in_array($statusNorm, [self::STATUS_AGUARDANDO_DECURSO, self::STATUS_AGUARDANDO_PUBLICACAO, 'em_andamento'], true)) {
            return 'status-current';
        }

        return 'status-pending';
    }

    public static function tituloBotaoPorTipo(string $tipo): string
    {
        $tipo = trim($tipo);

        return self::ETAPAS[$tipo]['button_title'] ?? 'Concluir';
    }

    public static function textoSpanStatusSubtarefa(string $tipo, string $statusSubtarefa): string
    {
        $statusNorm = strtolower(trim($statusSubtarefa));
        if ($statusNorm === 'concluída') {
            $statusNorm = 'concluida';
        }
        if ($statusNorm === 'concluida') {
            return self::ETAPAS[$tipo]['label'] ?? $tipo;
        }
        if ($statusNorm === 'em_andamento') {
            return self::ETAPAS[$tipo]['task_title'] ?? self::ETAPAS[$tipo]['pending_text'] ?? $tipo;
        }

        return self::ETAPAS[$tipo]['pending_text'] ?? $tipo;
    }

    public static function classeCssSpanStatusSubtarefa(string $statusSubtarefa): string
    {
        $statusNorm = strtolower(trim($statusSubtarefa));
        if ($statusNorm === 'concluída') {
            $statusNorm = 'concluida';
        }
        if ($statusNorm === 'concluida') {
            return 'status-finished';
        }
        if ($statusNorm === 'em_andamento') {
            return 'status-current';
        }

        return 'status-pending';
    }

    /**
     * Status principal a partir das subtarefas ativas (ordens 1 e 2).
     *
     * @param list<array<string, mixed>> $subtarefas
     */
    public static function statusPrincipalFromSubtarefas(array $subtarefas): string
    {
        $porOrdem = [];
        foreach ($subtarefas as $st) {
            if (!is_array($st)) {
                continue;
            }
            $ordem = (int) ($st['ordem'] ?? 0);
            if (EditaisSubtarefasRepository::isOrdemAtiva($ordem)) {
                $porOrdem[$ordem] = strtolower(trim((string) ($st['status'] ?? '')));
            }
        }

        $dec = $porOrdem[EditaisSubtarefasRepository::ORDEM_DECURSO] ?? '';
        $pub = $porOrdem[EditaisSubtarefasRepository::ORDEM_PUBLICACAO] ?? '';

        if ($dec === 'concluida' || $dec === 'concluída') {
            return 'concluída';
        }
        if ($pub === 'concluida' || $pub === 'concluída') {
            return self::STATUS_AGUARDANDO_DECURSO;
        }
        if ($pub === 'em_andamento' || $dec === 'em_andamento') {
            return self::STATUS_AGUARDANDO_PUBLICACAO;
        }

        return 'pendente';
    }

    public static function normalizarStatusPrincipal(string $status): string
    {
        $status = strtolower(trim($status));
        if ($status === 'concluída') {
            return 'concluida';
        }
        if ($status === 'em_andamento') {
            return self::STATUS_AGUARDANDO_DECURSO;
        }

        return $status;
    }
}
