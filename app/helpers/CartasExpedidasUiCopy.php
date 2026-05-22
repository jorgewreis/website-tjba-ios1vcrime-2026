<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/CartasExpedidasSubtarefasRepository.php';

/**
 * Textos de status da tarefa e subtarefas — cartas expedidas (lista, edição, timeline, exportação).
 *
 * Contrato: docs/20-modules/specs/cartorio-cartas-expedidas.md
 */
final class CartasExpedidasUiCopy
{
    public const CADASTRO_ETAPA = 'Carta expedida e cadastrada';

    public const TODAS_ETAPAS_CONCLUIDAS = 'Todas as etapas concluídas';

    /** @var array<string, array{ordem: int, label: string, task_title: string, button_title: string, pending_text: string}> */
    private const ETAPAS = [
        CartasExpedidasSubtarefasRepository::TIPO_ENVIO => [
            'ordem' => CartasExpedidasSubtarefasRepository::ORDEM_ENVIO,
            'label' => 'Envio',
            'task_title' => 'Aguardando envio',
            'button_title' => 'Enviar',
            'pending_text' => 'Aguardando envio de documentos',
        ],
        CartasExpedidasSubtarefasRepository::TIPO_DEVOLUCAO => [
            'ordem' => CartasExpedidasSubtarefasRepository::ORDEM_DEVOLUCAO,
            'label' => 'Devolução',
            'task_title' => 'Aguardando cumprimento',
            'button_title' => 'Juntar',
            'pending_text' => 'Aguardando cumprimento',
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

    public static function textoTimelinePendentePorTipo(string $tipo): string
    {
        $tipo = trim($tipo);

        return self::ETAPAS[$tipo]['pending_text'] ?? $tipo;
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

    public static function rotuloBadgeStatusTarefa(string $statusDb, ?array $subtarefaAtiva = null): string
    {
        $st = trim($statusDb);
        if (in_array($st, ['concluída', 'concluida', 'finalizada'], true)) {
            return 'Finalizada';
        }
        if ($st === 'cancelada') {
            return 'Cancelada';
        }

        $tipoAtivo = self::tipoSubtarefaAtiva($subtarefaAtiva);
        if ($tipoAtivo !== null) {
            return self::tituloTarefaPorTipo($tipoAtivo);
        }

        if (in_array($st, ['em_andamento', 'aguardando envio'], true)) {
            return 'Aguardando envio';
        }
        if ($st === 'aguardando cumprimento') {
            return 'Aguardando cumprimento';
        }

        return $st !== '' ? ucfirst($st) : 'Aguardando envio';
    }

    public static function classeCssBadgeStatusTarefa(string $statusDb, ?array $subtarefaAtiva = null): string
    {
        $st = trim($statusDb);
        if (in_array($st, ['concluída', 'concluida', 'finalizada'], true)) {
            return 'status-finished';
        }
        if ($st === 'cancelada') {
            return 'status-cancelled';
        }

        $tipoAtivo = self::tipoSubtarefaAtiva($subtarefaAtiva);
        if ($tipoAtivo === CartasExpedidasSubtarefasRepository::TIPO_DEVOLUCAO) {
            return 'status-pending';
        }
        if ($tipoAtivo === CartasExpedidasSubtarefasRepository::TIPO_ENVIO) {
            return 'status-current';
        }

        if ($st === 'aguardando cumprimento') {
            return 'status-pending';
        }

        return 'status-current';
    }

    public static function tarefaEstaEmAndamento(string $statusDb): bool
    {
        $st = trim($statusDb);

        return in_array($st, ['em_andamento', 'aguardando envio', 'aguardando cumprimento'], true);
    }

    /**
     * @param list<array<string, mixed>> $subtarefasAll
     * @param array<string, mixed>|null $subtarefaAtiva Linha retornada por {@see CartasExpedidasSubtarefasRepository::resolverSubtarefaAtivaPorLista()}
     */
    public static function resolverTituloTarefaListagem(string $statusTarefa, array $subtarefasAll, ?array $subtarefaAtiva = null): string
    {
        $st = trim($statusTarefa);
        $ativa = $subtarefaAtiva ?? CartasExpedidasSubtarefasRepository::resolverSubtarefaAtivaPorLista($subtarefasAll);

        if ($ativa !== null) {
            return self::tituloTarefaPorTipo(trim((string) ($ativa['tipo'] ?? '')));
        }

        if (in_array($st, ['concluída', 'concluida', 'finalizada'], true)) {
            return self::TODAS_ETAPAS_CONCLUIDAS;
        }
        if ($st === 'aguardando cumprimento') {
            return self::tituloTarefaPorTipo(CartasExpedidasSubtarefasRepository::TIPO_DEVOLUCAO);
        }
        if (in_array($st, ['em_andamento', 'aguardando envio'], true)) {
            return self::tituloTarefaPorTipo(CartasExpedidasSubtarefasRepository::TIPO_ENVIO);
        }
        if ($st !== '') {
            return self::rotuloBadgeStatusTarefa($st);
        }

        return self::tituloTarefaPorTipo(CartasExpedidasSubtarefasRepository::TIPO_ENVIO);
    }

    /**
     * Badge da coluna de status alinhado à subtarefa ativa (não só ao campo status da tarefa).
     *
     * @param array<string, mixed>|null $subtarefaAtiva
     */
    public static function rotuloBadgeStatusTarefaListagem(string $statusTarefa, ?array $subtarefaAtiva = null): string
    {
        if ($subtarefaAtiva !== null) {
            $tipo = trim((string) ($subtarefaAtiva['tipo'] ?? ''));
            if ($tipo === CartasExpedidasSubtarefasRepository::TIPO_ENVIO) {
                return 'Aguardando envio';
            }
            if ($tipo === CartasExpedidasSubtarefasRepository::TIPO_DEVOLUCAO) {
                return 'Aguardando cumprimento';
            }
        }

        return self::rotuloBadgeStatusTarefa($statusTarefa, $subtarefaAtiva);
    }

    /**
     * @param array<string, mixed>|null $subtarefaAtiva
     */
    public static function classeCssBadgeStatusTarefaListagem(string $statusTarefa, ?array $subtarefaAtiva = null): string
    {
        if ($subtarefaAtiva !== null) {
            $tipo = trim((string) ($subtarefaAtiva['tipo'] ?? ''));
            if ($tipo === CartasExpedidasSubtarefasRepository::TIPO_DEVOLUCAO) {
                return 'status-pending';
            }
            if ($tipo === CartasExpedidasSubtarefasRepository::TIPO_ENVIO) {
                return 'status-current';
            }
        }

        return self::classeCssBadgeStatusTarefa($statusTarefa, $subtarefaAtiva);
    }

    public static function textoSpanStatusSubtarefa(string $tipo, string $statusSubtarefa): string
    {
        $tipo = trim($tipo);
        $status = trim($statusSubtarefa);
        if ($status === 'concluida') {
            return 'Etapa concluída';
        }

        return self::textoTimelinePendentePorTipo($tipo);
    }

    public static function classeCssSpanStatusSubtarefa(string $statusSubtarefa): string
    {
        $status = trim($statusSubtarefa);

        return $status === 'em_andamento' ? 'status-current' : 'status-pending';
    }

    /**
     * @param array<string, mixed>|null $subtarefaAtiva
     */
    private static function tipoSubtarefaAtiva(?array $subtarefaAtiva): ?string
    {
        if ($subtarefaAtiva === null) {
            return null;
        }

        $tipo = trim((string) ($subtarefaAtiva['tipo'] ?? ''));

        return CartasExpedidasSubtarefasRepository::isTipoAtivo($tipo) ? $tipo : null;
    }
}
