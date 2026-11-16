<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/SalaAudienciasAtaSigiloHelper.php';
require_once __DIR__ . '/../helpers/PrioridadesProcessuaisCatalog.php';

final class SalaAudienciasEstudanteInscricaoRepository
{
    private ?bool $hasLinkAudienciaColumnCache = null;

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
     * Retorna inscritos agrupados por pauta, em ordem cronológica de inscrição.
     *
     * @param list<string> $pautaIds
     * @return array<string, list<array<string, mixed>>>
     */
    public function listDetalhesByPautaIds(array $pautaIds): array
    {
        $pautaIds = array_values(array_unique(array_filter(array_map(static function ($id): string {
            return trim((string) $id);
        }, $pautaIds), static function (string $id): bool {
            return $id !== '';
        })));
        if ($pautaIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($pautaIds), '?'));
        $sql = sprintf(
            'SELECT i.`id`, i.`pauta`, i.`usuario`, i.`created_at` AS `inscrito_em`,
                    COALESCE(NULLIF(TRIM(u.`cpf`), \'\'), \'\') AS `cpf`,
                    COALESCE(
                        NULLIF(TRIM(CONCAT(COALESCE(u.`nome`, \'\'), \' \', COALESCE(u.`sobrenome`, \'\'))), \'\'),
                        \'Estudante não identificado\'
                    ) AS `nome_completo`,
                    COALESCE(
                        NULLIF(TRIM(u.`telefone`), \'\'),
                        \'\'
                    ) AS `telefone`,
                    COALESCE(
                        CASE
                            WHEN TRIM(COALESCE(u.`instituicao`, \'\')) IN (\'\', \'-\') THEN \'\'
                            ELSE TRIM(u.`instituicao`)
                        END,
                        \'\'
                    ) AS `faculdade`
             FROM `%s` i
             LEFT JOIN `usuarios` u ON u.`id_usuario` = i.`usuario`
             WHERE i.`pauta` IN (' . $placeholders . ')
             ORDER BY i.`pauta` ASC, i.`created_at` ASC, i.`id` ASC',
            $this->table
        );

        $st = $this->connection->prepare($sql);
        foreach ($pautaIds as $idx => $pautaId) {
            $st->bindValue($idx + 1, $pautaId);
        }
        $st->execute();

        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $grouped = [];
        $seenIds = [];
        foreach ($rows as $row) {
            $pautaId = trim((string) ($row['pauta'] ?? ''));
            $inscricaoId = trim((string) ($row['id'] ?? ''));
            if ($pautaId === '' || $inscricaoId === '') {
                continue;
            }
            if (isset($seenIds[$inscricaoId])) {
                // Defesa extra contra multiplicacao residual.
                continue;
            }
            $seenIds[$inscricaoId] = true;
            if (!isset($grouped[$pautaId])) {
                $grouped[$pautaId] = [];
            }
            $row['ordem_inscricao'] = count($grouped[$pautaId]) + 1;
            $grouped[$pautaId][] = $row;
        }

        return $grouped;
    }

    /**
     * Contagem de inscritos por pauta, direto no banco, sem passar por JOINs.
     * Serve como fonte de verdade confiavel para o total mostrado na UI.
     *
     * @param list<string> $pautaIds
     * @return array<string, int>
     */
    public function countInscritosPorPautaIds(array $pautaIds): array
    {
        $pautaIds = array_values(array_unique(array_filter(array_map(static function ($id): string {
            return trim((string) $id);
        }, $pautaIds), static function (string $id): bool {
            return $id !== '';
        })));
        if ($pautaIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($pautaIds), '?'));
        $sql = sprintf(
            'SELECT `pauta`, COUNT(*) AS `total`
             FROM `%s`
             WHERE `pauta` IN (' . $placeholders . ')
             GROUP BY `pauta`',
            $this->table
        );

        $st = $this->connection->prepare($sql);
        foreach ($pautaIds as $idx => $pautaId) {
            $st->bindValue($idx + 1, $pautaId);
        }
        $st->execute();

        $out = [];
        while (($row = $st->fetch(PDO::FETCH_ASSOC)) !== false) {
            $pautaId = trim((string) ($row['pauta'] ?? ''));
            if ($pautaId === '') {
                continue;
            }
            $out[$pautaId] = (int) ($row['total'] ?? 0);
        }

        foreach ($pautaIds as $pautaId) {
            if (!isset($out[$pautaId])) {
                $out[$pautaId] = 0;
            }
        }

        return $out;
    }

    /**
     * Inscrições do usuário onde a ata ainda não expirou ou ainda não foi publicada (para lista “minhas audiências”).
     *
     * @return list<array<string, mixed>>
     */
    public function listInscricoesVisiveisParaUsuario(string $idUsuario): array
    {
        $linkAudienciaSelect = $this->hasLinkAudienciaColumn() ? 'a.`link_audiencia`' : 'NULL';
        $codigosPrioridadeBloqueio = PrioridadesProcessuaisCatalog::codigosBloqueantesInscricaoEstudante();
        $marcadoresPrioridadeBloqueioSelect = implode(', ', array_map(
            static fn (int $i): string => ':prioridade_bloqueio_select_' . $i,
            array_keys($codigosPrioridadeBloqueio)
        ));
        $marcadoresPrioridadeBloqueioFiltro = implode(', ', array_map(
            static fn (int $i): string => ':prioridade_bloqueio_filtro_' . $i,
            array_keys($codigosPrioridadeBloqueio)
        ));
        $sql = sprintf(
            'SELECT i.`pauta`, i.`created_at` AS inscrito_em,
                    a.`processo` AS `processo_id`,
                    a.`tipo_audiencia`, a.`data_audiencia`, a.`hora_audiencia`, a.`status`, %s AS `link_audiencia`,
                    a.`data_hora_inicio_efetivo`, a.`data_hora_fim_efetivo`, a.`duracao_estimada_minutos`,
                    p.`numero_processo`, COALESCE(NULLIF(TRIM(cl.`nome`), \'\'), \'Classe não informada\') AS `classe_nome`,
                    COALESCE(p.`sigiloso`, 0) AS `processo_sigiloso`,
                    (SELECT COUNT(*) FROM `sala_audiencias_pauta_parte` pp WHERE pp.`pauta` = a.`id_pauta` AND pp.`ativo` = 1 AND pp.`maioridade` = \'menor\') AS `qtd_menor`,
                    CASE WHEN EXISTS (SELECT 1 FROM `processo_prioridades` pri WHERE pri.`processo_id` = a.`processo` AND pri.`codigo` IN (%s)) THEN 1 ELSE 0 END AS `tem_prioridade_bloqueio`,
                    ata.`id_ata`, ata.`expires_at`, ata.`nome_original`, ata.`uploaded_at`, ata.`arquivo_relativo`
             FROM `%s` i
             INNER JOIN `sala_audiencias_pauta` a ON a.`id_pauta` = i.`pauta` AND a.`ativo` = 1
             LEFT JOIN `processos` p ON p.`id` = a.`processo`
             LEFT JOIN `classes` cl ON cl.`id` = p.`classe_id`
             LEFT JOIN `sala_audiencias_ata` ata ON ata.`pauta` = i.`pauta`
             WHERE i.`usuario` = :u
               AND (
                    ata.`id_ata` IS NULL
                    OR ata.`expires_at` > NOW()
                    OR TRIM(COALESCE(ata.`arquivo_relativo`, \'\')) = \'\'
                    OR COALESCE(p.`sigiloso`, 0) <> 0
                    OR EXISTS (SELECT 1 FROM `sala_audiencias_pauta_parte` pp WHERE pp.`pauta` = a.`id_pauta` AND pp.`ativo` = 1 AND pp.`maioridade` = \'menor\')
                    OR EXISTS (SELECT 1 FROM `processo_prioridades` pri WHERE pri.`processo_id` = a.`processo` AND pri.`codigo` IN (%s))
               )
             ORDER BY a.`data_audiencia` DESC, a.`hora_audiencia` DESC',
            $linkAudienciaSelect,
            $marcadoresPrioridadeBloqueioSelect,
            $this->table,
            $marcadoresPrioridadeBloqueioFiltro
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':u', $idUsuario);
        foreach ($codigosPrioridadeBloqueio as $i => $codigo) {
            $st->bindValue(':prioridade_bloqueio_select_' . $i, $codigo);
            $st->bindValue(':prioridade_bloqueio_filtro_' . $i, $codigo);
        }
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Audiências de hoje para o painel do estudante, com flags de elegibilidade/inscrição.
     *
     * @return list<array<string, mixed>>
     */
    public function listHojeDesignadasElegiveis(string $idUsuario): array
    {
        $linkAudienciaSelect = $this->hasLinkAudienciaColumn() ? 'a.`link_audiencia`' : 'NULL';
        $codigosPrioridadeBloqueio = PrioridadesProcessuaisCatalog::codigosBloqueantesInscricaoEstudante();
        $marcadoresPrioridadeBloqueio = implode(', ', array_map(
            static fn (int $i): string => ':prioridade_bloqueio_' . $i,
            array_keys($codigosPrioridadeBloqueio)
        ));
        $sql = sprintf('
            SELECT a.`id_pauta`, a.`processo` AS `processo_id`, a.`tipo_audiencia`, a.`data_audiencia`, a.`hora_audiencia`, a.`localizacao`, a.`status`, %s AS `link_audiencia`,
                   a.`data_hora_inicio_efetivo`, a.`data_hora_fim_efetivo`, a.`duracao_estimada_minutos`,
                   p.`numero_processo`, COALESCE(NULLIF(TRIM(cl.`nome`), \'\'), \'Classe não informada\') AS `classe_nome`,
                   ' . SalaAudienciasAtaSigiloHelper::sqlProcessoSigilosoEfetivo('p', 'a') . ' AS `processo_sigiloso`,
                   CASE WHEN i.`id` IS NOT NULL THEN 1 ELSE 0 END AS `ja_inscrito`,
                   (SELECT COUNT(*) FROM `sala_audiencias_pauta_parte` pp
                    WHERE pp.`pauta` = a.`id_pauta` AND pp.`ativo` = 1 AND pp.`maioridade` = \'menor\') AS `qtd_menor`,
                   (SELECT COUNT(*) FROM `' . $this->table . '` inv WHERE inv.`pauta` = a.`id_pauta`) AS `total_inscritos_pauta`,
                   CASE WHEN EXISTS (
                        SELECT 1 FROM `processo_prioridades` pri
                        WHERE pri.`processo_id` = a.`processo`
                          AND pri.`codigo` IN (%s)
                   ) THEN 1 ELSE 0 END AS `tem_prioridade_bloqueio`
            FROM `sala_audiencias_pauta` a
            LEFT JOIN `processos` p ON p.`id` = a.`processo`
            LEFT JOIN `classes` cl ON cl.`id` = p.`classe_id`
            LEFT JOIN `' . $this->table . '` i ON i.`pauta` = a.`id_pauta` AND i.`usuario` = :u
            WHERE a.`ativo` = 1
              AND a.`data_audiencia` = CURDATE()
        ', $linkAudienciaSelect, $marcadoresPrioridadeBloqueio);
        $st = $this->connection->prepare($sql);
        $st->bindValue(':u', $idUsuario);
        foreach ($codigosPrioridadeBloqueio as $i => $codigo) {
            $st->bindValue(':prioridade_bloqueio_' . $i, $codigo);
        }
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function hasLinkAudienciaColumn(): bool
    {
        if ($this->hasLinkAudienciaColumnCache !== null) {
            return $this->hasLinkAudienciaColumnCache;
        }

        try {
            $st = $this->connection->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
            );
            $st->bindValue(':table', 'sala_audiencias_pauta');
            $st->bindValue(':column', 'link_audiencia');
            $st->execute();
            $this->hasLinkAudienciaColumnCache = ((int) $st->fetchColumn()) > 0;
        } catch (Throwable $e) {
            $this->hasLinkAudienciaColumnCache = false;
        }

        return $this->hasLinkAudienciaColumnCache;
    }
}
