<?php
declare(strict_types=1);

final class CartasExpedidasPrioridade
{
    public const PADRAO = 'medio';
    public const ROTULOS = ['baixo'=>'Baixo','medio'=>'Médio','alto'=>'Alto','urgente'=>'Urgente','critico'=>'Crítico'];
    public const ICONES = ['baixo'=>'↓','medio'=>'–','alto'=>'↑','urgente'=>'!','critico'=>'‼'];

    /** Uma consulta para os registros já autorizados na página; tolera implantação antes da migração. */
    public static function paraCartas(PDO $db, array $ids): array
    {
        if ($ids === []) return [];
        try {
            $query = $db->prepare('SELECT id, prioridade_tarefa FROM cartas_expedidas WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
            $query->execute(array_values($ids));
            return $query->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException $e) {
            if (in_array((string)$e->getCode(), ['42S22', '42S02'], true)) return [];
            throw $e;
        }
    }

    public static function disponivel(PDO $db): bool
    {
        try {
            $db->query('SELECT prioridade_tarefa FROM cartas_expedidas WHERE 1=0');
            return true;
        } catch (PDOException $e) {
            if (in_array((string)$e->getCode(), ['42S22', '42S02'], true)) return false;
            throw $e;
        }
    }

    public static function validar(mixed $valor): string
    {
        if (!is_string($valor) || !isset(self::ROTULOS[$valor])) {
            throw new InvalidArgumentException('Selecione uma prioridade válida para a carta.');
        }
        return $valor;
    }
}
