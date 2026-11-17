<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class EditaisSubtarefasRepository
{
    private PDO $connection;
    private string $table;

    public const TIPO_PUBLICACAO = 'certificacao_publicacao';
    public const TIPO_DECURSO = 'certificacao_decurso_prazo';

    /** @deprecated Legado — não criar novas subtarefas */
    public const TIPO_ORDEM_1 = 'expedicao_edital';
    /** @deprecated Legado */
    public const TIPO_ORDEM_2 = 'envio_djen';
    /** Alias legado (ordem antiga 3) */
    public const TIPO_ORDEM_3 = self::TIPO_PUBLICACAO;
    /** Alias legado (ordem antiga 4) */
    public const TIPO_ORDEM_4 = self::TIPO_DECURSO;

    public const ORDEM_PUBLICACAO = 1;
    public const ORDEM_DECURSO = 2;

    public const ORDENS_ATIVAS = [self::ORDEM_PUBLICACAO, self::ORDEM_DECURSO];

    private const ORDEM_POR_TIPO = [
        self::TIPO_PUBLICACAO => self::ORDEM_PUBLICACAO,
        self::TIPO_DECURSO => self::ORDEM_DECURSO,
    ];

    private const STATUS_EM_ANDAMENTO = 'em_andamento';
    private const STATUS_PENDENTE = 'pendente';
    private const STATUS_CONCLUIDA = 'concluida';

    public function __construct(PDO $connection, string $table = 'editais_subtarefas')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public static function isOrdemAtiva(int $ordem): bool
    {
        return in_array($ordem, self::ORDENS_ATIVAS, true);
    }

    public static function isTipoAtivo(string $tipo): bool
    {
        $tipo = trim($tipo);

        return isset(self::ORDEM_POR_TIPO[$tipo]);
    }

    public function create(string $editalId, string $tipo, int $ordem, string $status = 'pendente', ?string $dadosJson = null): string
    {
        $editalId = trim($editalId);
        $tipo = trim($tipo);
        if ($editalId === '' || $tipo === '' || !self::isOrdemAtiva($ordem)) {
            throw new InvalidArgumentException('edital_id, tipo e ordem ativa (1 ou 2) são obrigatórios.');
        }
        if (!isset(self::ORDEM_POR_TIPO[$tipo]) || self::ORDEM_POR_TIPO[$tipo] !== $ordem) {
            throw new InvalidArgumentException('Tipo e ordem inválidos.');
        }

        $id = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->table,
            'id',
            'EDS',
            30
        );

        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `edital_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at`) VALUES (:id, :edital_id, :tipo, :ordem, :status, :dados_especificos, NOW(), NOW())',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->bindValue(':edital_id', $editalId);
        $statement->bindValue(':tipo', $tipo);
        $statement->bindValue(':ordem', $ordem, PDO::PARAM_INT);
        $statement->bindValue(':status', $status);
        $statement->bindValue(':dados_especificos', $dadosJson);
        $statement->execute();

        return $id;
    }

    public function findByEditalId(string $editalId): array
    {
        $editalId = trim($editalId);
        if ($editalId === '') {
            return [];
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `edital_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at` FROM `%s` WHERE `edital_id` = :edital_id ORDER BY `ordem` ASC',
                $this->table
            )
        );
        $statement->bindValue(':edital_id', $editalId);
        $statement->execute();

        $rows = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function deleteByEditalId(string $editalId): void
    {
        $editalId = trim($editalId);
        if ($editalId === '') {
            return;
        }
        $statement = $this->connection->prepare(
            sprintf('DELETE FROM `%s` WHERE `edital_id` = :edital_id', $this->table)
        );
        $statement->bindValue(':edital_id', $editalId);
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
                'SELECT `id`, `edital_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at` FROM `%s` WHERE `id` = :id LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findPendenteOuEmAndamentoByEditalId(string $editalId): array
    {
        $all = $this->findByEditalId($editalId);
        $ativas = [];

        foreach ($all as $row) {
            $ordem = (int) ($row['ordem'] ?? 0);
            $tipo = (string) ($row['tipo'] ?? '');
            if (!self::isOrdemAtiva($ordem) && !self::isTipoAtivo($tipo)) {
                continue;
            }
            $status = strtolower(trim((string) ($row['status'] ?? '')));
            if ($status === 'concluída') {
                $status = 'concluida';
            }
            if (in_array($status, ['pendente', 'em_andamento'], true)) {
                $ativas[] = $row;
            }
        }

        usort($ativas, static function (array $a, array $b): int {
            return ((int) ($a['ordem'] ?? 0)) <=> ((int) ($b['ordem'] ?? 0));
        });

        return $ativas;
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

        $limit = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' LIMIT 1' : '';
        $statement = $this->connection->prepare(
            sprintf(
                'UPDATE `%s` SET `status` = :status, `updated_at` = NOW() WHERE `id` = :id%s',
                $this->table,
                $limit
            )
        );
        $statement->bindValue(':status', $status);
        $statement->bindValue(':id', $id);
        $statement->execute();
    }

    public function atualizarDadosEspecificos(string $id, ?string $dadosJson): void
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException('ID da subtarefa é obrigatório.');
        }

        $limit = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' LIMIT 1' : '';
        $statement = $this->connection->prepare(
            sprintf(
                'UPDATE `%s` SET `dados_especificos` = :dados_especificos, `updated_at` = NOW() WHERE `id` = :id%s',
                $this->table,
                $limit
            )
        );
        $statement->bindValue(':dados_especificos', $dadosJson);
        $statement->bindValue(':id', $id);
        $statement->execute();
    }

    /**
     * Garante as 2 subtarefas ativas (publicação + decurso) quando o edital não tem nenhuma.
     */
    public function ensureSubtarefasAtivas(string $editalId): void
    {
        $editalId = trim($editalId);
        if ($editalId === '') {
            return;
        }
        $existentes = $this->findByEditalId($editalId);
        $temAtiva = false;
        foreach ($existentes as $row) {
            if (self::isOrdemAtiva((int) ($row['ordem'] ?? 0)) || self::isTipoAtivo((string) ($row['tipo'] ?? ''))) {
                $temAtiva = true;
                break;
            }
        }
        if ($temAtiva) {
            return;
        }

        $this->create($editalId, self::TIPO_PUBLICACAO, self::ORDEM_PUBLICACAO, self::STATUS_EM_ANDAMENTO, null);
        $this->create($editalId, self::TIPO_DECURSO, self::ORDEM_DECURSO, self::STATUS_PENDENTE, null);
    }

    /** @deprecated Use ensureSubtarefasAtivas */
    public function ensureQuatroSubtarefas(string $editalId): void
    {
        $this->ensureSubtarefasAtivas($editalId);
    }
}
