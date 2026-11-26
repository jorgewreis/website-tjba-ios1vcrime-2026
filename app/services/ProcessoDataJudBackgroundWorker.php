<?php
declare(strict_types=1);

require_once __DIR__ . '/DataJudService.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../repositories/AssuntosRepository.php';
require_once __DIR__ . '/../helpers/CnjValidator.php';
require_once __DIR__ . '/ProcessoDataJudSyncService.php';

final class ProcessoDataJudBackgroundWorker
{
    public function __construct(private PDO $db, private DataJudService $dataJud) {}

    public function run(int $limit = 20): array
    {
        $done = 0;
        $failed = 0;
        for ($i = 0; $i < max(1, min(100, $limit)); $i++) {
            $job = $this->claim();
            if ($job === null) break;
            try {
                $this->sync($job);
                $this->finish((int) $job['id']);
                $done++;
            } catch (Throwable $e) {
                $this->retry((int) $job['id'], (int) $job['tentativas']);
                $failed++;
                error_log('datajud background sync falhou: ' . get_class($e));
            }
        }
        return ['processados' => $done, 'falhas' => $failed];
    }

    private function claim(): ?array
    {
        // MariaDB 10.4 does not support SKIP LOCKED. A short advisory lock
        // serializes only claim/recovery; claimed work runs concurrently.
        $lock = (int) $this->db->query("SELECT GET_LOCK('ios1vcrime-datajud-claim', 5)")->fetchColumn();
        if ($lock !== 1) return null;
        try {
            // Recover jobs whose worker died after claiming them.
            $this->db->exec("UPDATE processo_datajud_sync_queue SET status = IF(tentativas + 1 >= 8, 'esgotado', 'falhou'), tentativas = tentativas + 1, proxima_tentativa_em = DATE_ADD(NOW(), INTERVAL 2 MINUTE), processando_em = NULL, updated_at = NOW() WHERE status = 'processando' AND processando_em < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
            $this->db->beginTransaction();
            $q = $this->db->query("SELECT id, processo_id, tentativas FROM processo_datajud_sync_queue WHERE status IN ('pendente','falhou') AND proxima_tentativa_em <= NOW() ORDER BY proxima_tentativa_em, id LIMIT 1 FOR UPDATE");
            $job = $q->fetch(PDO::FETCH_ASSOC);
            if (!is_array($job)) {
                $this->db->commit();
                return null;
            }
            $u = $this->db->prepare("UPDATE processo_datajud_sync_queue SET status='processando', processando_em=NOW(6), updated_at=NOW() WHERE id=:id");
            $u->execute([':id' => (int) $job['id']]);
            $this->db->commit();
            return $job;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('ios1vcrime-datajud-claim')");
        }
    }

    private function sync(array $job): void
    {
        $processo = (new ProcessosRepository($this->db))->findById((string) $job['processo_id']);
        if ($processo === null || !empty($processo['sigiloso'])) return;
        $numero = (string) ($processo['numero_processo'] ?? '');
        try {
            $result = $this->dataJud->searchProcesso($numero);
        } catch (DataJudAmbiguousResponseException) {
            // A public index may contain multiple degrees. This is a valid but
            // non-syncable result, so repeated background retries cannot help.
            return;
        }
        if (($result['fonte_confirmada_para_sincronizacao'] ?? false) !== true
            || ($result['resultado_unico_verificado'] ?? false) !== true
            || ($result['sigilo_restrito'] ?? true) !== false
            || ($result['sigilo_ausencia_verificada'] ?? false) !== true) return;

        $classe = $result['classe'] ?? null;
        if (!is_array($classe)) return;
        $codigoClasse = $classe['codigo'] ?? null;
        if ($codigoClasse === null && ($classe['descricao'] ?? null) === null) {
            $classe = null;
        } elseif (!is_scalar($codigoClasse) || ClassesRepository::normalizeCodigo((string) $codigoClasse) === '') {
            return;
        }

        $assuntos = $result['assuntos'] ?? null;
        $estadoAssuntos = $result['assuntos_estado'] ?? '';
        if ($estadoAssuntos === 'ausente') {
            $assuntos = null;
        } elseif ($estadoAssuntos === 'vazio') {
            if (!is_array($assuntos) || !array_is_list($assuntos) || $assuntos !== []) return;
        } elseif ($estadoAssuntos === 'presente') {
            if (!is_array($assuntos) || !array_is_list($assuntos) || $assuntos === []
                || ($result['assuntos_completos'] ?? false) !== true) return;
            foreach ($assuntos as $item) {
                if (!is_array($item) || !is_scalar($item['codigo'] ?? null)
                    || AssuntosRepository::normalizeCodigo((string) $item['codigo']) === ''
                    || !is_string($item['descricao'] ?? null)) return;
            }
        } else {
            return;
        }
        if ($classe === null && $assuntos === null) return;

        // Class and subject changes use the existing atomic sync service. It
        // revalidates both payloads before opening the transaction.
        (new ProcessoDataJudSyncService($this->db))->sync(
            $numero,
            $classe === null ? null : ['codigo' => (string) $classe['codigo'], 'descricao' => (string) ($classe['descricao'] ?? $classe['nome'] ?? $classe['codigo'])],
            $assuntos,
            $estadoAssuntos === 'vazio',
            function (PDO $writeDb) use ($job, $numero): bool {
                $sql = 'SELECT numero_processo, sigiloso FROM processos WHERE id = :id LIMIT 1';
                if ($writeDb->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
                $query = $writeDb->prepare($sql);
                $query->execute([':id' => (string) $job['processo_id']]);
                $current = $query->fetch(PDO::FETCH_ASSOC);
                return is_array($current)
                    && CnjValidator::onlyDigits((string) ($current['numero_processo'] ?? '')) === CnjValidator::onlyDigits($numero)
                    && empty($current['sigiloso']);
            }
        );
    }

    private function finish(int $id): void
    {
        $q = $this->db->prepare("UPDATE processo_datajud_sync_queue SET status=IF(solicitado_em > processando_em, 'pendente', 'concluido'), processando_em=NULL, atualizado_em=NOW(), updated_at=NOW() WHERE id=:id");
        $q->execute([':id' => $id]);
    }

    private function retry(int $id, int $attempts): void
    {
        $next = $attempts + 1;
        $status = $next >= 8 ? 'esgotado' : 'falhou';
        $delay = min(1440, 2 ** min($next, 10));
        $q = $this->db->prepare("UPDATE processo_datajud_sync_queue SET status=:status, tentativas=:attempts, proxima_tentativa_em=DATE_ADD(NOW(), INTERVAL :delay MINUTE), processando_em=NULL, updated_at=NOW() WHERE id=:id");
        $q->bindValue(':status', $status);
        $q->bindValue(':attempts', $next, PDO::PARAM_INT);
        $q->bindValue(':delay', $delay, PDO::PARAM_INT);
        $q->bindValue(':id', $id, PDO::PARAM_INT);
        $q->execute();
    }
}
