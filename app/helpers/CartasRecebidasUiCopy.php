<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/CartasRecebidasSubtarefasRepository.php';

/**
 * Textos de status da tarefa e subtarefas — cartas recebidas (lista, edição, timeline, exportação).
 *
 * Contrato: docs/20-modules/specs/cartorio-cartas-recebidas.md
 */
final class CartasRecebidasUiCopy
{
    public const CADASTRO_ETAPA = 'Carta recebida e cadastrada';

    public const TODAS_ETAPAS_CONCLUIDAS = 'Todas as etapas concluídas';

    public const STATUS_AGUARDANDO_CUMPRIMENTO = 'aguardando cumprimento';

    public const STATUS_AGUARDANDO_DEVOLUCAO = 'aguardando devolução';

    /** @var array<string, array{ordem: int, label: string, task_title: string, button_title: string, pending_text: string}> */
    private const ETAPAS = [
        CartasRecebidasSubtarefasRepository::TIPO_CUMPRIMENTO => [
            'ordem' => CartasRecebidasSubtarefasRepository::ORDEM_CUMPRIMENTO,
            'label' => 'Cumprimento',
            'task_title' => 'Aguardando cumprimento',
            'button_title' => 'Cumprir',
            'pending_text' => 'Aguardando cumprimento',
        ],
        CartasRecebidasSubtarefasRepository::TIPO_DEVOLUCAO => [
            'ordem' => CartasRecebidasSubtarefasRepository::ORDEM_DEVOLUCAO,
            'label' => 'Devolução',
            'task_title' => 'Aguardando devolução',
            'button_title' => 'Devolver',
            'pending_text' => 'Aguardando devolução',
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

    /** @return array<string, string> */
    public static function mapaTituloTarefaPorTipo(): array
    {
        $out = [];
        foreach (self::ETAPAS as $tipo => $def) {
            $out[$tipo] = $def['task_title'];
        }

        return $out;
    }

    /** @return array<string, string> */
    public static function mapaTextoTimelinePendentePorTipo(): array
    {
        $out = [];
        foreach (self::ETAPAS as $tipo => $def) {
            $out[$tipo] = $def['pending_text'];
        }

        return $out;
    }

    /** @return array<string, string> */
    public static function mapaTituloBotaoPorTipo(): array
    {
        $out = [];
        foreach (self::ETAPAS as $tipo => $def) {
            $out[$tipo] = $def['button_title'];
        }

        return $out;
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

    public static function tituloTarefaPorTipo(string $tipo): string
    {
        $tipo = trim($tipo);

        return self::ETAPAS[$tipo]['task_title'] ?? $tipo;
    }

    public static function tituloBotaoPorTipo(string $tipo): string
    {
        $tipo = trim($tipo);

        return self::ETAPAS[$tipo]['button_title'] ?? $tipo;
    }

    public static function legendaEtapaPorOrdem(int $ordem): string
    {
        foreach (self::ETAPAS as $def) {
            if ($def['ordem'] === $ordem) {
                return $def['label'];
            }
        }

        return '';
    }

    public static function rotuloBadgeStatusTarefa(string $statusDb): string
    {
        $st = trim($statusDb);
        if (in_array($st, ['concluída', 'concluida', 'finalizada'], true)) {
            return 'Finalizada';
        }
        if ($st === 'cancelada') {
            return 'Cancelada';
        }
        if ($st === self::STATUS_AGUARDANDO_DEVOLUCAO) {
            return 'Aguardando devolução';
        }
        if ($st === self::STATUS_AGUARDANDO_CUMPRIMENTO) {
            return 'Aguardando cumprimento';
        }

        return $st !== '' ? ucfirst($st) : 'Aguardando cumprimento';
    }

    public static function classeCssBadgeStatusTarefa(string $statusDb): string
    {
        $st = trim($statusDb);
        if (in_array($st, ['concluída', 'concluida', 'finalizada'], true)) {
            return 'status-finished';
        }
        if ($st === 'cancelada') {
            return 'status-cancelled';
        }
        if ($st === self::STATUS_AGUARDANDO_CUMPRIMENTO) {
            return 'status-pending';
        }

        return 'status-current';
    }

    public static function tarefaEstaEmAndamento(string $statusDb): bool
    {
        $st = trim($statusDb);

        return in_array($st, [self::STATUS_AGUARDANDO_CUMPRIMENTO, self::STATUS_AGUARDANDO_DEVOLUCAO], true);
    }

    /**
     * @param list<array<string, mixed>> $subtarefasAll
     */
    public static function resolverTituloTarefaListagem(string $statusTarefa, array $subtarefasAll): string
    {
        $st = trim($statusTarefa);
        $mapa = self::mapaTituloTarefaPorTipo();

        foreach ($subtarefasAll as $s) {
            if (!is_array($s)) {
                continue;
            }
            if (trim((string) ($s['status'] ?? '')) !== 'em_andamento') {
                continue;
            }
            $tipoAtual = trim((string) ($s['tipo'] ?? ''));
            if (!CartasRecebidasSubtarefasRepository::isTipoAtivo($tipoAtual)) {
                continue;
            }

            return $mapa[$tipoAtual] ?? $tipoAtual;
        }

        if (in_array($st, ['concluída', 'concluida', 'finalizada'], true)) {
            return self::TODAS_ETAPAS_CONCLUIDAS;
        }
        if ($st === 'cancelada') {
            return 'Cancelada';
        }
        if ($st === self::STATUS_AGUARDANDO_DEVOLUCAO) {
            return $mapa[CartasRecebidasSubtarefasRepository::TIPO_DEVOLUCAO] ?? 'Aguardando devolução';
        }
        if ($st === self::STATUS_AGUARDANDO_CUMPRIMENTO) {
            return $mapa[CartasRecebidasSubtarefasRepository::TIPO_CUMPRIMENTO] ?? 'Aguardando cumprimento';
        }
        if ($st !== '') {
            return self::rotuloBadgeStatusTarefa($st);
        }

        return $mapa[CartasRecebidasSubtarefasRepository::TIPO_CUMPRIMENTO] ?? 'Aguardando cumprimento';
    }

    public static function textoSpanStatusSubtarefa(string $tipo, string $statusSubtarefa): string
    {
        $tipo = trim($tipo);
        $status = trim($statusSubtarefa);
        if ($status === 'concluida') {
            return 'Etapa concluída';
        }

        return self::mapaTextoTimelinePendentePorTipo()[$tipo] ?? $tipo;
    }

    public static function classeCssSpanStatusSubtarefa(string $statusSubtarefa): string
    {
        $status = trim($statusSubtarefa);

        return $status === 'em_andamento' ? 'status-current' : 'status-pending';
    }
}
