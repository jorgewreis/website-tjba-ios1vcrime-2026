<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class CartasExpedidasSubtarefasRepository
{
    private PDO $connection;
    private string $table;

    public const TIPO_ORDEM_1 = 'aguardando_assinatura';
    public const TIPO_ORDEM_2 = 'aguardando_gerar_pdf';
    public const TIPO_ORDEM_3 = 'aguardando_envio';
    public const TIPO_ORDEM_4 = 'aguardando_juntada_termo';
    public const TIPO_ORDEM_5 = 'aguardando_informacoes';
    public const TIPO_ORDEM_6 = 'aguardando_devolucao';

    private const ORDEM_POR_TIPO = [
        self::TIPO_ORDEM_1 => 1,
        self::TIPO_ORDEM_2 => 2,
        self::TIPO_ORDEM_3 => 3,
        self::TIPO_ORDEM_4 => 4,
        self::TIPO_ORDEM_5 => 5,
        self::TIPO_ORDEM_6 => 6,
    ];

    private const STATUS_EM_ANDAMENTO = 'em_andamento';
    private const STATUS_PENDENTE = 'pendente';
    private const STATUS_CONCLUIDA = 'concluida';

    public function __construct(PDO $connection, string $table = 'cartas_expedidas_subtarefas')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function create(string $cartaId, string $tipo, int $ordem, string $status = 'pendente', ?string $dadosJson = null): string
    {
        $cartaId = trim($cartaId);
        $tipo = trim($tipo);
        if ($cartaId === '' || $tipo === '' || $ordem < 1 || $ordem > 6) {
            throw new InvalidArgumentException('carta_expedida_id, tipo e ordem (1-6) são obrigatórios.');
        }
        if (!isset(self::ORDEM_POR_TIPO[$tipo]) || self::ORDEM_POR_TIPO[$tipo] !== $ordem) {
            throw new InvalidArgumentException('Tipo e ordem inválidos.');
        }

        $id = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->table,
            'id',
            'CES',
            30
        );

        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `carta_expedida_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at`) VALUES (:id, :carta_expedida_id, :tipo, :ordem, :status, :dados_especificos, NOW(), NOW())',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->bindValue(':carta_expedida_id', $cartaId);
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
                'SELECT `id`, `carta_expedida_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at` FROM `%s` WHERE `carta_expedida_id` = :carta_id ORDER BY `ordem` ASC',
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

    /**
     * Retorna as subtarefas a exibir na lista: 1 em andamento OU 2 quando for Informações e/ou Devolução (sempre mostrar ambas juntas quando uma delas estiver pendente/em andamento).
     */
    public function findPendenteOuEmAndamentoByCartaId(string $cartaId): array
    {
        $all = $this->findByCartaId($cartaId);
        $emAndamento = [];
        $ordem5 = null;
        $ordem6 = null;

        foreach ($all as $row) {
            $status = (string) ($row['status'] ?? '');
            $ordem = (int) ($row['ordem'] ?? 0);
            if ($status !== self::STATUS_EM_ANDAMENTO && $status !== self::STATUS_PENDENTE) {
                continue;
            }
            if ($ordem === 5) {
                $ordem5 = $row;
            }
            if ($ordem === 6) {
                $ordem6 = $row;
            }
            if ($ordem !== 5 && $ordem !== 6) {
                $emAndamento[] = $row;
            }
        }

        if ($ordem5 !== null || $ordem6 !== null) {
            $out = [];
            if ($ordem5 !== null) {
                $out[] = $ordem5;
            }
            if ($ordem6 !== null) {
                $out[] = $ordem6;
            }
            return $out;
        }
        if (!empty($emAndamento)) {
            return [reset($emAndamento)];
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

    public function findById(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `carta_expedida_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at` FROM `%s` WHERE `id` = :id LIMIT 1',
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
