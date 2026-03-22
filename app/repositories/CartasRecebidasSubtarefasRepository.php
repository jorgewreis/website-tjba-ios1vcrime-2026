<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class CartasRecebidasSubtarefasRepository
{
    private PDO $connection;
    private string $table;

    public const TIPO_ORDEM_1 = 'ato_ordinatorio_cumprimento';
    public const TIPO_ORDEM_2 = 'expedicao_documento';
    public const TIPO_ORDEM_3 = 'juntada_certidao_oficial';
    public const TIPO_ORDEM_4 = 'ato_ordinatorio_devolucao';
    public const TIPO_ORDEM_5 = 'envio_processo';
    public const TIPO_ORDEM_6 = 'juntada_termo_envio';
    public const TIPO_ORDEM_7 = 'arquivamento_definitivo';

    private const ORDEM_POR_TIPO = [
        self::TIPO_ORDEM_1 => 1,
        self::TIPO_ORDEM_2 => 2,
        self::TIPO_ORDEM_3 => 3,
        self::TIPO_ORDEM_4 => 4,
        self::TIPO_ORDEM_5 => 5,
        self::TIPO_ORDEM_6 => 6,
        self::TIPO_ORDEM_7 => 7,
    ];

    private const STATUS_EM_ANDAMENTO = 'em_andamento';
    private const STATUS_PENDENTE = 'pendente';
    private const STATUS_CONCLUIDA = 'concluida';
    public const STATUS_CANCELADA = 'cancelada';

    public function __construct(PDO $connection, string $table = 'cartas_recebidas_subtarefas')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function create(string $cartaId, string $tipo, int $ordem, string $status = 'pendente', ?string $dadosJson = null): string
    {
        $cartaId = trim($cartaId);
        $tipo = trim($tipo);
        if ($cartaId === '' || $tipo === '' || $ordem < 1 || $ordem > 7) {
            throw new InvalidArgumentException('carta_recebida_id, tipo e ordem (1-7) são obrigatórios.');
        }
        if (!isset(self::ORDEM_POR_TIPO[$tipo]) || self::ORDEM_POR_TIPO[$tipo] !== $ordem) {
            throw new InvalidArgumentException('Tipo e ordem inválidos.');
        }

        $id = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->table,
            'id',
            'CRS',
            30
        );

        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `carta_recebida_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at`) VALUES (:id, :carta_recebida_id, :tipo, :ordem, :status, :dados_especificos, NOW(), NOW())',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->bindValue(':carta_recebida_id', $cartaId);
        $statement->bindValue(':tipo', $tipo);
        $statement->bindValue(':ordem', $ordem, PDO::PARAM_INT);
        $statement->bindValue(':status', $status);
        $statement->bindValue(':dados_especificos', $dadosJson);
        $statement->execute();

        return $id;
    }

    public function findByCartaId(string $cartaId): array
    {
        $cartaId = trim($cartaId);
        if ($cartaId === '') {
            return [];
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `carta_recebida_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at` FROM `%s` WHERE `carta_recebida_id` = :carta_id ORDER BY `ordem` ASC',
                $this->table
            )
        );
        $statement->bindValue(':carta_id', $cartaId);
        $statement->execute();

        $rows = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function deleteByCartaId(string $cartaId): void
    {
        $cartaId = trim($cartaId);
        if ($cartaId === '') {
            return;
        }
        $statement = $this->connection->prepare(
            sprintf('DELETE FROM `%s` WHERE `carta_recebida_id` = :carta_id', $this->table)
        );
        $statement->bindValue(':carta_id', $cartaId);
        $statement->execute();
    }

    public function findByCartaIdEOrdem(string $cartaId, int $ordem): ?array
    {
        $cartaId = trim($cartaId);
        if ($cartaId === '' || $ordem < 1 || $ordem > 7) {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `carta_recebida_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at`
                 FROM `%s`
                 WHERE `carta_recebida_id` = :carta_id AND `ordem` = :ordem
                 ORDER BY `updated_at` DESC
                 LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':carta_id', $cartaId);
        $statement->bindValue(':ordem', $ordem, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Retorna as subtarefas a exibir na lista: apenas 1 por vez, a que estiver em andamento ou pendente (primeira na ordem).
     * Ignora subtarefas com status cancelada.
     */
    public function findPendenteOuEmAndamentoByCartaId(string $cartaId): array
    {
        $all = $this->findByCartaId($cartaId);
        foreach ($all as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === self::STATUS_EM_ANDAMENTO || $status === self::STATUS_PENDENTE) {
                return [$row];
            }
        }
        return [];
    }

    public function marcarConcluida(string $id): void
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException('ID da subtarefa é obrigatório.');
        }

        $statement = $this->connection->prepare(
            sprintf(
                'UPDATE `%s` SET `status` = :status, `updated_at` = NOW() WHERE `id` = :id LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':status', self::STATUS_CONCLUIDA);
        $statement->bindValue(':id', $id);
        $statement->execute();
    }

    public function atualizarStatus(string $id, string $status): void
    {
        $id = trim($id);
        $status = trim($status);
        if ($id === '') {
            throw new InvalidArgumentException('ID da subtarefa é obrigatório.');
        }
        if (!in_array($status, [self::STATUS_PENDENTE, self::STATUS_EM_ANDAMENTO, self::STATUS_CONCLUIDA, self::STATUS_CANCELADA], true)) {
            throw new InvalidArgumentException('Status de subtarefa inválido.');
        }

        $statement = $this->connection->prepare(
            sprintf(
                'UPDATE `%s` SET `status` = :status, `updated_at` = NOW() WHERE `id` = :id LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':status', $status);
        $statement->bindValue(':id', $id);
        $statement->execute();
    }

    public function findById(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `carta_recebida_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at` FROM `%s` WHERE `id` = :id LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function obterProximoTipo(int $ordemAtual): ?string
    {
        $next = $ordemAtual + 1;
        $map = [
            1 => self::TIPO_ORDEM_1,
            2 => self::TIPO_ORDEM_2,
            3 => self::TIPO_ORDEM_3,
            4 => self::TIPO_ORDEM_4,
            5 => self::TIPO_ORDEM_5,
            6 => self::TIPO_ORDEM_6,
            7 => self::TIPO_ORDEM_7,
        ];
        return $map[$next] ?? null;
    }

    public function getOrdenMaximaPorCarta(string $cartaId): int
    {
        $all = $this->findByCartaId($cartaId);
        $max = 0;
        foreach ($all as $row) {
            $o = (int) ($row['ordem'] ?? 0);
            if ($o > $max) {
                $max = $o;
            }
        }
        return $max;
    }
}
