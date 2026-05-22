<?php
declare(strict_types=1);

final class OrphanSubtarefasCleaner
{
    /**
     * Remove subtarefas órfãs (sem tarefa principal) em todos os módulos do cartório.
     *
     * @return array<string,int> Quantidade removida por tabela.
     */
    public static function cleanAll(PDO $connection): array
    {
        $targets = [
            ['sub' => 'cartas_expedidas_subtarefas', 'fk' => 'carta_expedida_id', 'main' => 'cartas_expedidas'],
            ['sub' => 'cartas_recebidas_subtarefas', 'fk' => 'carta_recebida_id', 'main' => 'cartas_recebidas'],
            ['sub' => 'editais_subtarefas', 'fk' => 'edital_id', 'main' => 'editais'],
            ['sub' => 'guias_subtarefas', 'fk' => 'guia_id', 'main' => 'guias'],
        ];

        $removedByTable = [];

        foreach ($targets as $target) {
            $subTable = $target['sub'];
            $fkColumn = $target['fk'];
            $mainTable = $target['main'];

            $sql = sprintf(
                'DELETE s FROM `%s` s LEFT JOIN `%s` m ON m.`id` = s.`%s` WHERE m.`id` IS NULL',
                $subTable,
                $mainTable,
                $fkColumn
            );

            $stmt = $connection->prepare($sql);
            $stmt->execute();
            $removedByTable[$subTable] = (int) $stmt->rowCount();
        }

        return $removedByTable;
    }
}

