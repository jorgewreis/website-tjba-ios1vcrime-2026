<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/SalaAudienciasAtaSigiloHelper.php';

final class SalaAudienciasAtaRepository
{
    private PDO $connection;
    private string $table;
    private bool $hasIdentificadorArquivo;

    public function __construct(PDO $connection, string $table = 'sala_audiencias_ata')
    {
        $this->connection = $connection;
        $this->table = $table;
        $this->hasIdentificadorArquivo = $this->detectIdentificadorArquivoColumn();
    }

    private function detectIdentificadorArquivoColumn(): bool
    {
        try {
            $sql = 'SELECT COUNT(*) FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = :table AND `COLUMN_NAME` = :column';
            $st = $this->connection->prepare($sql);
            $st->bindValue(':table', $this->table);
            $st->bindValue(':column', 'identificador_arquivo');
            $st->execute();
            return ((int) $st->fetchColumn()) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function findByPauta(string $idPauta): ?array
    {
        $st = $this->connection->prepare(sprintf('SELECT * FROM `%s` WHERE `pauta` = :p LIMIT 1', $this->table));
        $st->bindValue(':p', $idPauta);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchExpiredBeforeNow(): array
    {
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `expires_at` <= NOW() AND TRIM(COALESCE(`arquivo_relativo`, \'\')) <> \'\'',
            $this->table
        );
        $st = $this->connection->query($sql);
        if ($st === false) {
            return [];
        }

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Após o prazo de download: remove referência ao arquivo no disco (já tratada no serviço) e zera campos do PDF,
     * mantendo o registro para não reabrir a tarefa de upload na listagem.
     */
    public function clearArquivoAposExpiracaoRetencao(string $idAta): bool
    {
        $idAta = trim($idAta);
        if ($idAta === '') {
            return false;
        }
        $sql = sprintf(
            'UPDATE `%s` SET `arquivo_relativo` = \'\', `tamanho_bytes` = 0 WHERE `id_ata` = :id LIMIT 1',
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $idAta);
        $st->execute();

        return $st->rowCount() > 0;
    }

    public function deleteByIdAta(string $idAta): bool
    {
        $st = $this->connection->prepare(sprintf('DELETE FROM `%s` WHERE `id_ata` = :id LIMIT 1', $this->table));
        $st->bindValue(':id', $idAta);
        $st->execute();

        return $st->rowCount() > 0;
    }

    /**
     * Substitui ata existente da pauta (uma linha por pauta).
     *
     * @param array{id_ata?: string, pauta: string, arquivo_relativo: string, nome_original: string, mime: string, tamanho_bytes: int, uploaded_by: ?string, uploaded_at: string, expires_at: string, identificador_arquivo?: string} $row
     */
    public function upsertForPauta(array $row): string
    {
        $existing = $this->findByPauta(trim((string) $row['pauta']));
        if ($existing !== null) {
            $updateSql = $this->hasIdentificadorArquivo
                ? 'UPDATE `%s` SET `arquivo_relativo`=:rel,`nome_original`=:nome,`mime`=:mime,`tamanho_bytes`=:tam,`uploaded_at`=:up,`uploaded_by`=:upby,`expires_at`=:ex,`identificador_arquivo`=:ident WHERE `id_ata`=:id LIMIT 1'
                : 'UPDATE `%s` SET `arquivo_relativo`=:rel,`nome_original`=:nome,`mime`=:mime,`tamanho_bytes`=:tam,`uploaded_at`=:up,`uploaded_by`=:upby,`expires_at`=:ex WHERE `id_ata`=:id LIMIT 1';
            $st = $this->connection->prepare(sprintf($updateSql, $this->table));
            $st->bindValue(':rel', $row['arquivo_relativo']);
            $st->bindValue(':nome', $row['nome_original']);
            $st->bindValue(':mime', $row['mime']);
            $st->bindValue(':tam', (int) $row['tamanho_bytes'], PDO::PARAM_INT);
            $st->bindValue(':up', $row['uploaded_at']);
            $st->bindValue(':upby', $row['uploaded_by'] ?? null);
            $st->bindValue(':ex', $row['expires_at']);
            if ($this->hasIdentificadorArquivo) {
                $st->bindValue(':ident', (string) ($row['identificador_arquivo'] ?? ''));
            }
            $st->bindValue(':id', $existing['id_ata']);
            $st->execute();

            return (string) $existing['id_ata'];
        }

        $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id_ata', 'SAT', 30);
        $insertSql = $this->hasIdentificadorArquivo
            ? 'INSERT INTO `%s` (`id_ata`,`pauta`,`arquivo_relativo`,`nome_original`,`mime`,`tamanho_bytes`,`uploaded_at`,`uploaded_by`,`expires_at`,`identificador_arquivo`) VALUES (:id,:pauta,:rel,:nome,:mime,:tam,:up,:upby,:ex,:ident)'
            : 'INSERT INTO `%s` (`id_ata`,`pauta`,`arquivo_relativo`,`nome_original`,`mime`,`tamanho_bytes`,`uploaded_at`,`uploaded_by`,`expires_at`) VALUES (:id,:pauta,:rel,:nome,:mime,:tam,:up,:upby,:ex)';
        $st = $this->connection->prepare(sprintf($insertSql, $this->table));
        $st->bindValue(':id', $id);
        $st->bindValue(':pauta', $row['pauta']);
        $st->bindValue(':rel', $row['arquivo_relativo']);
        $st->bindValue(':nome', $row['nome_original']);
        $st->bindValue(':mime', $row['mime']);
        $st->bindValue(':tam', (int) $row['tamanho_bytes'], PDO::PARAM_INT);
        $st->bindValue(':up', $row['uploaded_at']);
        $st->bindValue(':upby', $row['uploaded_by'] ?? null);
        $st->bindValue(':ex', $row['expires_at']);
        if ($this->hasIdentificadorArquivo) {
            $st->bindValue(':ident', (string) ($row['identificador_arquivo'] ?? ''));
        }
        $st->execute();

        return $id;
    }

    /**
     * Audiências realizadas com metadados de processo e ata (lista interna upload).
     *
     * @return list<array<string, mixed>>
     */
    public function listRealizadasComAta(int $limit, int $offset, int $diasOcultarListagemSemAtaDisponivel = 40): array
    {
        $diasOcultarListagemSemAtaDisponivel = max(0, $diasOcultarListagemSemAtaDisponivel);
        $sql = sprintf(
            'SELECT a.`id_pauta`, a.`id_pauta` AS `IdPauta`, a.`processo` AS `processo_id`, a.`tipo_audiencia`, a.`data_audiencia`, a.`hora_audiencia`, a.`data_hora_inicio_efetivo`, a.`localizacao`, a.`status`,
                    p.`numero_processo`, COALESCE(NULLIF(TRIM(cl.`nome`), \'\'), \'Classe não informada\') AS `classe_nome`,
                    ata.`id_ata`, ata.`expires_at`, ata.`nome_original`, ata.`uploaded_at` AS `Data`, ata.`arquivo_relativo`,
                    TRIM(CONCAT(COALESCE(ucr_up.`nome`, \'\'), \' \', COALESCE(ucr_up.`sobrenome`, \'\'))) AS `CriadoPorNome`
             FROM `sala_audiencias_pauta` a
             LEFT JOIN `processos` p ON p.`id` = a.`processo`
             LEFT JOIN `classes` cl ON cl.`id` = p.`classe_id`
             LEFT JOIN `%s` ata ON ata.`pauta` = a.`id_pauta`
             LEFT JOIN `usuarios` ucr_up ON ucr_up.`id_usuario` = ata.`uploaded_by`
             WHERE a.`ativo` = 1 AND a.`status` IN (\'realizada\', \'finalizada\')
             AND NOT (
                a.`data_audiencia` < DATE_SUB(CURDATE(), INTERVAL %d DAY)
                AND NOT (
                    TRIM(COALESCE(ata.`arquivo_relativo`, \'\')) <> \'\'
                    AND ata.`expires_at` > NOW()
                )
             )%s
             ORDER BY
                COALESCE(DATE(a.`data_hora_inicio_efetivo`), a.`data_audiencia`) DESC,
                COALESCE(TIME(a.`data_hora_inicio_efetivo`), TIME(a.`hora_audiencia`)) DESC,
                CASE
                    WHEN ata.`id_ata` IS NULL THEN 0
                    WHEN TRIM(COALESCE(ata.`arquivo_relativo`, \'\')) = \'\' THEN 2
                    ELSE 1
                END ASC
             LIMIT :limit OFFSET :offset',
            $this->table,
            $diasOcultarListagemSemAtaDisponivel,
            SalaAudienciasAtaSigiloHelper::sqlExcluirProcessoSigiloso()
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':limit', $limit, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countRealizadas(int $diasOcultarListagemSemAtaDisponivel = 40): int
    {
        $diasOcultarListagemSemAtaDisponivel = max(0, $diasOcultarListagemSemAtaDisponivel);
        $sql = sprintf(
            'SELECT COUNT(*) FROM `sala_audiencias_pauta` a
             LEFT JOIN `processos` p ON p.`id` = a.`processo`
             LEFT JOIN `%s` ata ON ata.`pauta` = a.`id_pauta`
             WHERE a.`ativo` = 1 AND a.`status` IN (\'realizada\', \'finalizada\')
             AND NOT (
                a.`data_audiencia` < DATE_SUB(CURDATE(), INTERVAL %d DAY)
                AND NOT (
                    TRIM(COALESCE(ata.`arquivo_relativo`, \'\')) <> \'\'
                    AND ata.`expires_at` > NOW()
                )
             )%s',
            $this->table,
            $diasOcultarListagemSemAtaDisponivel,
            SalaAudienciasAtaSigiloHelper::sqlExcluirProcessoSigiloso()
        );
        $st = $this->connection->query($sql);
        if ($st === false) {
            return 0;
        }

        return (int) $st->fetchColumn();
    }
}
