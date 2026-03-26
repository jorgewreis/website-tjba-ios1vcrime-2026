<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class SalaAudienciasEstudanteInscricaoRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'sala_audiencias_estudante_inscricao')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function exists(string $idPauta, string $idUsuario): bool
    {
        $st = $this->connection->prepare(sprintf('SELECT 1 FROM `%s` WHERE `pauta` = :p AND `usuario` = :u LIMIT 1', $this->table));
        $st->bindValue(':p', $idPauta);
        $st->bindValue(':u', $idUsuario);
        $st->execute();

        return $st->fetchColumn() !== false;
    }

    public function countInscritosPorPauta(string $idPauta): int
    {
        $st = $this->connection->prepare(sprintf('SELECT COUNT(*) FROM `%s` WHERE `pauta` = :p', $this->table));
        $st->bindValue(':p', $idPauta);
        $st->execute();

        return (int) $st->fetchColumn();
    }

    public function insert(string $idPauta, string $idUsuario): string
    {
        $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'SAI', 30);
        $st = $this->connection->prepare(sprintf(
            'INSERT INTO `%s` (`id`, `pauta`, `usuario`, `created_at`) VALUES (:id, :pauta, :usuario, NOW())',
            $this->table
        ));
        $st->bindValue(':id', $id);
        $st->bindValue(':pauta', $idPauta);
        $st->bindValue(':usuario', $idUsuario);
        $st->execute();

        return $id;
    }

    /**
     * Inscrições do usuário onde a ata ainda não expirou ou ainda não foi publicada (para lista “minhas audiências”).
     *
     * @return list<array<string, mixed>>
     */
    public function listInscricoesVisiveisParaUsuario(string $idUsuario): array
    {
        $sql = sprintf(
            'SELECT i.`pauta`, i.`created_at` AS inscrito_em,
                    a.`tipo_audiencia`, a.`data_audiencia`, a.`hora_audiencia`, a.`status`,
                    p.`numero_processo`, COALESCE(NULLIF(TRIM(cl.`nome`), \'\'), \'Classe não informada\') AS `classe_nome`,
                    ata.`id_ata`, ata.`expires_at`, ata.`nome_original`, ata.`uploaded_at`
             FROM `%s` i
             INNER JOIN `sala_audiencias_pauta` a ON a.`id_pauta` = i.`pauta` AND a.`ativo` = 1
             LEFT JOIN `processos` p ON p.`id` = a.`processo`
             LEFT JOIN `classes` cl ON cl.`id` = p.`classe_id`
             LEFT JOIN `sala_audiencias_ata` ata ON ata.`pauta` = i.`pauta`
             WHERE i.`usuario` = :u
               AND (ata.`id_ata` IS NULL OR ata.`expires_at` > NOW())
             ORDER BY a.`data_audiencia` DESC, a.`hora_audiencia` DESC',
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':u', $idUsuario);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Audiências de hoje, designadas, elegíveis para inscrição, com flag se o usuário já está inscrito.
     *
     * @return list<array<string, mixed>>
     */
    public function listHojeDesignadasElegiveis(string $idUsuario): array
    {
        $sql = '
            SELECT a.`id_pauta`, a.`tipo_audiencia`, a.`data_audiencia`, a.`hora_audiencia`, a.`localizacao`, a.`status`,
                   a.`data_hora_inicio_efetivo`, a.`data_hora_fim_efetivo`, a.`duracao_estimada_minutos`,
                   p.`numero_processo`, COALESCE(NULLIF(TRIM(cl.`nome`), \'\'), \'Classe não informada\') AS `classe_nome`,
                   COALESCE(p.`sigiloso`, 0) AS `processo_sigiloso`,
                   CASE WHEN i.`id` IS NOT NULL THEN 1 ELSE 0 END AS `ja_inscrito`,
                   (SELECT COUNT(*) FROM `sala_audiencias_pauta_parte` pp
                    WHERE pp.`pauta` = a.`id_pauta` AND pp.`ativo` = 1 AND pp.`maioridade` = \'menor\') AS `qtd_menor`,
                   (SELECT COUNT(*) FROM `' . $this->table . '` inv WHERE inv.`pauta` = a.`id_pauta`) AS `total_inscritos_pauta`
            FROM `sala_audiencias_pauta` a
            LEFT JOIN `processos` p ON p.`id` = a.`processo`
            LEFT JOIN `classes` cl ON cl.`id` = p.`classe_id`
            LEFT JOIN `' . $this->table . '` i ON i.`pauta` = a.`id_pauta` AND i.`usuario` = :u
            WHERE a.`ativo` = 1
              AND a.`data_audiencia` = CURDATE()
              AND a.`status` IN (\'designada\', \'agendada\')
        ';
        $st = $this->connection->prepare($sql);
        $st->bindValue(':u', $idUsuario);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
