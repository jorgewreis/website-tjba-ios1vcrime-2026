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
     * Remove todas as subtarefas da carta (usado ao excluir a tarefa principal).
     */
    public function deleteByCartaId(string $cartaId): void
    {
        $cartaId = trim($cartaId);
        if ($cartaId === '') {
            return;
        }
        $statement = $this->connection->prepare(
            sprintf('DELETE FROM `%s` WHERE `carta_expedida_id` = :carta_id', $this->table)
        );
        $statement->bindValue(':carta_id', $cartaId);
        $statement->execute();
    }

    public function findByCartaIdEOrdem(string $cartaId, int $ordem): ?array
    {
        $cartaId = trim($cartaId);
        if ($cartaId === '' || $ordem < 1 || $ordem > 6) {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `carta_expedida_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at`
                 FROM `%s`
                 WHERE `carta_expedida_id` = :carta_id AND `ordem` = :ordem
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
     * Retorna as subtarefas a exibir na lista: 1 em andamento OU 2 quando for Informações e/ou Devolução (sempre mostrar ambas juntas quando uma delas estiver pendente/em andamento).
     */
    public function findPendenteOuEmAndamentoByCartaId(string $cartaId): array
    {
        $all = $this->findByCartaId($cartaId);
        $emAndamento = [];
        $ordem5Row = null;
        $ordem6Row = null;
        $faseInformacoesDevolucao = false;

        foreach ($all as $row) {
            $status = (string) ($row['status'] ?? '');
            $ordem = (int) ($row['ordem'] ?? 0);
            if ($ordem === 5) {
                $ordem5Row = $row;
                if ($status === self::STATUS_EM_ANDAMENTO || $status === self::STATUS_PENDENTE) {
                    $faseInformacoesDevolucao = true;
                }
            }
            if ($ordem === 6) {
                $ordem6Row = $row;
                if ($status === self::STATUS_EM_ANDAMENTO || $status === self::STATUS_PENDENTE) {
                    $faseInformacoesDevolucao = true;
                }
            }
            if ($ordem !== 5 && $ordem !== 6 && ($status === self::STATUS_EM_ANDAMENTO || $status === self::STATUS_PENDENTE)) {
                $emAndamento[] = $row;
            }
        }

        if ($faseInformacoesDevolucao && ($ordem5Row !== null || $ordem6Row !== null)) {
            $out = [];
            $status5 = trim((string) (($ordem5Row['status'] ?? '')));
            $status6 = trim((string) (($ordem6Row['status'] ?? '')));
            if ($ordem5Row !== null && ($status5 === self::STATUS_EM_ANDAMENTO || $status5 === self::STATUS_PENDENTE)) {
                $out[] = $ordem5Row;
            }
            if ($ordem6Row !== null && ($status6 === self::STATUS_EM_ANDAMENTO || $status6 === self::STATUS_PENDENTE)) {
                $out[] = $ordem6Row;
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

    public function atualizarStatus(string $id, string $status): void
    {
        $id = trim($id);
        $status = trim($status);
        if ($id === '') {
            throw new InvalidArgumentException('ID da subtarefa é obrigatório.');
        }
        if (!in_array($status, [self::STATUS_PENDENTE, self::STATUS_EM_ANDAMENTO, self::STATUS_CONCLUIDA], true)) {
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

    /**
     * Garante que as subtarefas das ordens 5 (informações) e 6 (devolução) existam em conjunto.
     * Corrige cartas antigas em que uma das duas não foi criada.
     */
    public function ensureInformacoesDevolucaoPair(string $cartaId): void
    {
        $cartaId = trim($cartaId);
        if ($cartaId === '') {
            return;
        }

        $ordem5 = $this->findByCartaIdEOrdem($cartaId, 5);
        $ordem6 = $this->findByCartaIdEOrdem($cartaId, 6);

        if ($ordem5 === null && $ordem6 === null) {
            return;
        }

        if ($ordem5 !== null && $ordem6 === null) {
            $status5 = trim((string) ($ordem5['status'] ?? ''));
            $status6 = $status5 === self::STATUS_CONCLUIDA ? self::STATUS_EM_ANDAMENTO : self::STATUS_PENDENTE;
            $this->create($cartaId, self::TIPO_ORDEM_6, 6, $status6, null);
            return;
        }

        if ($ordem5 === null && $ordem6 !== null) {
            $status6 = trim((string) ($ordem6['status'] ?? ''));
            $status5 = $status6 === self::STATUS_CONCLUIDA ? self::STATUS_CONCLUIDA : self::STATUS_PENDENTE;
            $this->create($cartaId, self::TIPO_ORDEM_5, 5, $status5, null);
        }
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
