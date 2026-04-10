<?php
declare(strict_types=1);

/**
 * Alinha status da tarefa principal ao conjunto de subtarefas (padrão cartório).
 *
 * @see docs/20-modules/patterns/cartorio-listagem-tarefas-subtarefas.md
 */
final class CartorioTarefaStatusPrincipalHelper
{
    /**
     * @param list<array<string,mixed>> $subtarefas
     * @param list<string> $statusContamComoConcluidos Valores de `status` de subtarefa que indicam etapa já concluída
     *                                                    (ex.: ['concluida']; em alguns módulos incluir 'pulada' se aplicável).
     */
    public static function principalStatusPadrao(
        array $subtarefas,
        array $statusContamComoConcluidos,
        string $statusFinalPrincipal,
        string $emAndamento = 'em_andamento',
        string $pendente = 'pendente'
    ): string {
        $total = count($subtarefas);
        if ($total === 0) {
            return $pendente;
        }
        $n = 0;
        foreach ($subtarefas as $st) {
            $s = trim((string) ($st['status'] ?? ''));
            if (in_array($s, $statusContamComoConcluidos, true)) {
                $n++;
            }
        }
        if ($n >= $total) {
            return $statusFinalPrincipal;
        }
        if ($n >= 1) {
            return $emAndamento;
        }

        return $pendente;
    }
}
