<?php
declare(strict_types=1);

/**
 * Repositório para consulta do salário mínimo por data de vigência.
 * Usado no cálculo da pena de multa: valor_pena = (salario_minimo * dias_multa) / 30.
 */
final class SalarioMinimoRepository
{
    private PDO $connection;
    private string $table = 'salario_minimo';

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Retorna o valor do salário mínimo vigente na data informada (Y-m-d).
     * Considera o registro com vigencia_inicio <= data, o mais recente.
     */
    public function getValorPorData(string $data): ?float
    {
        $data = trim($data);
        if ($data === '') {
            return null;
        }

        $sql = sprintf(
            'SELECT `valor` FROM `%s` WHERE `vigencia_inicio` <= :data ORDER BY `vigencia_inicio` DESC LIMIT 1',
            $this->table
        );
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':data', $data);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || $row['valor'] === null) {
            return null;
        }
        return (float) $row['valor'];
    }
}
