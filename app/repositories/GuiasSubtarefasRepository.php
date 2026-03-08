<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class GuiasSubtarefasRepository
{
    private PDO $connection;
    private string $table;

    public const TIPO_ORDEM_1 = 'detalhamento_informacoes';
    public const TIPO_ORDEM_2 = 'calculo_pena_multa';
    public const TIPO_ORDEM_3 = 'expedicao_guia';
    public const TIPO_ORDEM_4 = 'aguardando_assinatura';
    public const TIPO_ORDEM_5 = 'juntada_automatica';
    public const TIPO_ORDEM_6 = 'gerar_pdf';
    public const TIPO_ORDEM_7 = 'envio_guia';
    public const TIPO_ORDEM_8 = 'juntada_termo_envio';

    private const ORDEM_POR_TIPO = [
        self::TIPO_ORDEM_1 => 1,
        self::TIPO_ORDEM_2 => 2,
        self::TIPO_ORDEM_3 => 3,
        self::TIPO_ORDEM_4 => 4,
        self::TIPO_ORDEM_5 => 5,
        self::TIPO_ORDEM_6 => 6,
        self::TIPO_ORDEM_7 => 7,
        self::TIPO_ORDEM_8 => 8,
    ];

    private const STATUS_EM_ANDAMENTO = 'em_andamento';
    private const STATUS_PENDENTE = 'pendente';
    private const STATUS_CONCLUIDA = 'concluida';
    public const STATUS_CANCELADA = 'cancelada';
    public const STATUS_PULADA = 'pulada';

    public function __construct(PDO $connection, string $table = 'guias_subtarefas')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function create(string $guiaId, string $tipo, int $ordem, string $status = 'pendente', ?string $dadosJson = null): string
    {
        $guiaId = trim($guiaId);
        $tipo = trim($tipo);
        if ($guiaId === '' || $tipo === '' || $ordem < 1 || $ordem > 8) {
            throw new InvalidArgumentException('guia_id, tipo e ordem (1-8) são obrigatórios.');
        }
        if (!isset(self::ORDEM_POR_TIPO[$tipo]) || self::ORDEM_POR_TIPO[$tipo] !== $ordem) {
            throw new InvalidArgumentException('Tipo e ordem inválidos.');
        }

        $id = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->table,
            'id',
            'GUS',
            30
        );

        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `guia_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at`) VALUES (:id, :guia_id, :tipo, :ordem, :status, :dados_especificos, NOW(), NOW())',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->bindValue(':guia_id', $guiaId);
        $statement->bindValue(':tipo', $tipo);
        $statement->bindValue(':ordem', $ordem, PDO::PARAM_INT);
        $statement->bindValue(':status', $status);
        $statement->bindValue(':dados_especificos', $dadosJson);
        $statement->execute();

        return $id;
    }

    public function findByGuiaId(string $guiaId): array
    {
        $guiaId = trim($guiaId);
        if ($guiaId === '') {
            return [];
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `guia_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at` FROM `%s` WHERE `guia_id` = :guia_id ORDER BY `ordem` ASC',
                $this->table
            )
        );
        $statement->bindValue(':guia_id', $guiaId);
        $statement->execute();

        $rows = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function deleteByGuiaId(string $guiaId): void
    {
        $guiaId = trim($guiaId);
        if ($guiaId === '') {
            return;
        }
        $statement = $this->connection->prepare(
            sprintf('DELETE FROM `%s` WHERE `guia_id` = :guia_id', $this->table)
        );
        $statement->bindValue(':guia_id', $guiaId);
        $statement->execute();
    }

    public function findByGuiaIdEOrdem(string $guiaId, int $ordem): ?array
    {
        $guiaId = trim($guiaId);
        if ($guiaId === '' || $ordem < 1 || $ordem > 8) {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `guia_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at`
                 FROM `%s`
                 WHERE `guia_id` = :guia_id AND `ordem` = :ordem
                 ORDER BY `updated_at` DESC
                 LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':guia_id', $guiaId);
        $statement->bindValue(':ordem', $ordem, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Retorna a primeira subtarefa em_andamento ou pendente (ignora cancelada e pulada).
     */
    public function findPendenteOuEmAndamentoByGuiaId(string $guiaId): array
    {
        $all = $this->findByGuiaId($guiaId);
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
        if (!in_array($status, [self::STATUS_PENDENTE, self::STATUS_EM_ANDAMENTO, self::STATUS_CONCLUIDA, self::STATUS_CANCELADA, self::STATUS_PULADA], true)) {
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

    public function atualizarDadosEspecificos(string $id, ?string $dadosJson): void
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException('ID da subtarefa é obrigatório.');
        }

        $statement = $this->connection->prepare(
            sprintf(
                'UPDATE `%s` SET `dados_especificos` = :dados_especificos, `updated_at` = NOW() WHERE `id` = :id LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':dados_especificos', $dadosJson);
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
                'SELECT `id`, `guia_id`, `tipo`, `ordem`, `status`, `dados_especificos`, `created_at`, `updated_at` FROM `%s` WHERE `id` = :id LIMIT 1',
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
            8 => self::TIPO_ORDEM_8,
        ];
        return $map[$next] ?? null;
    }

    public function getOrdenMaximaPorGuia(string $guiaId): int
    {
        $all = $this->findByGuiaId($guiaId);
        $max = 0;
        foreach ($all as $row) {
            $o = (int) ($row['ordem'] ?? 0);
            if ($o > $max) {
                $max = $o;
            }
        }
        return $max;
    }

    /**
     * Garante as 8 subtarefas para uma guia que ainda não tem nenhuma (ex.: pós-migração ou edge case).
     */
    public function ensureOitoSubtarefas(string $guiaId): void
    {
        $guiaId = trim($guiaId);
        if ($guiaId === '') {
            return;
        }
        $existentes = $this->findByGuiaId($guiaId);
        if (count($existentes) > 0) {
            return;
        }
        $this->create($guiaId, self::TIPO_ORDEM_1, 1, self::STATUS_EM_ANDAMENTO, null);
        $this->create($guiaId, self::TIPO_ORDEM_2, 2, self::STATUS_PENDENTE, null);
        $this->create($guiaId, self::TIPO_ORDEM_3, 3, self::STATUS_PENDENTE, null);
        $this->create($guiaId, self::TIPO_ORDEM_4, 4, self::STATUS_PENDENTE, null);
        $this->create($guiaId, self::TIPO_ORDEM_5, 5, self::STATUS_PENDENTE, null);
        $this->create($guiaId, self::TIPO_ORDEM_6, 6, self::STATUS_PENDENTE, null);
        $this->create($guiaId, self::TIPO_ORDEM_7, 7, self::STATUS_PENDENTE, null);
        $this->create($guiaId, self::TIPO_ORDEM_8, 8, self::STATUS_PENDENTE, null);
    }
}
