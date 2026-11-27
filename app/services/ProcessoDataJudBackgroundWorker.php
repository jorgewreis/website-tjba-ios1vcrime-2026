<?php
declare(strict_types=1);

require_once __DIR__ . '/DataJudService.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../repositories/AssuntosRepository.php';
require_once __DIR__ . '/../helpers/CnjValidator.php';
require_once __DIR__ . '/../helpers/ProcessoSetorAuthorization.php';
require_once __DIR__ . '/ProcessoDataJudSyncService.php';

final class ProcessoDataJudBackgroundWorker
{
    private const BATCH_LOCK = "CONCAT('ios1vcrime-dj-worker:', LEFT(SHA2(DATABASE(), 256), 40))";

    public function __construct(private PDO $db, private DataJudService $dataJud) {}

    public function run(int $limit = 20): array
    {
        // A tarefa agendada e uma execução manual não devem disputar a API.
        if ((int) $this->db->query('SELECT GET_LOCK(' . self::BATCH_LOCK . ', 0)')->fetchColumn() !== 1) {
            return ['processados' => 0, 'falhas' => 0];
        }
        try {
            return $this->runBatch($limit);
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(' . self::BATCH_LOCK . ')')->fetchColumn();
        }
    }

    private function runBatch(int $limit): array
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
                $this->fail((int) $job['id']);
                $failed++;
                error_log('datajud background sync falhou: ' . get_class($e));
                if ($e instanceof DataJudUpstreamException && in_array($e->reason, ['throttled', 'authorization', 'unavailable', 'transport', 'incomplete'], true)) {
                    // Deixe os outros itens para a próxima execução, sem consumir
                    // suas tentativas durante uma indisponibilidade da origem.
                    break;
                }
            }
        }
        return ['processados' => $done, 'falhas' => $failed];
    }

    private function claim(): ?array
    {
        // A trava curta preserva a compatibilidade com o coletor legado durante o deploy.
        $lock = (int) $this->db->query("SELECT GET_LOCK('ios1vcrime-datajud-claim', 5)")->fetchColumn();
        if ($lock !== 1) return null;
        try {
            // Não é possível saber se uma execução interrompida chegou à API.
            // Encerra a tentativa sem reabrir o trabalho nem mexer na fila legada.
            $this->db->exec("UPDATE processo_datajud_sync_queue SET status='falhou', processando_em=NULL, updated_at=NOW()
                WHERE status='cad_inicial_exec' AND processando_em < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
            $this->db->beginTransaction();
            $q = $this->db->query("SELECT id, processo_id, requested_by FROM processo_datajud_sync_queue WHERE status='cadastro_inicial' AND tentativas=0 AND requested_by IS NOT NULL AND proxima_tentativa_em <= NOW() ORDER BY proxima_tentativa_em, id LIMIT 1 FOR UPDATE");
            $job = $q->fetch(PDO::FETCH_ASSOC);
            if (!is_array($job)) {
                $this->db->commit();
                return null;
            }
            $u = $this->db->prepare("UPDATE processo_datajud_sync_queue SET status='cad_inicial_exec', tentativas=1, processando_em=NOW(6), updated_at=NOW() WHERE id=:id AND status='cadastro_inicial'");
            $u->execute([':id' => (int) $job['id']]);
            if ($u->rowCount() !== 1) throw new RuntimeException('Cadastro inicial não reservado para o DataJud.');
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
        if (!ProcessoSetorAuthorization::canAccessProcess(
            $this->db,
            (string) ($job['requested_by'] ?? ''),
            (string) $job['processo_id'],
            false
        )) return;
        $numero = (string) ($processo['numero_processo'] ?? '');
        try {
            $result = $this->dataJud->searchProcesso($numero, true);
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
        if (!is_array($classe) || !self::isValidClassPayload($classe)) return;
        $codigoClasse = $classe['codigo'] ?? null;
        if ($codigoClasse === null && ($classe['descricao'] ?? null) === null) {
            $classe = null;
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
                if (!ProcessoSetorAuthorization::canModifyProcess($writeDb, (string) $job['requested_by'], (string) $job['processo_id'], true)) return false;
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

    /** Evita que bool/array recebidos do índice virem códigos ou nomes por cast PHP. */
    public static function isValidClassPayload(array $classe): bool
    {
        $codigo = $classe['codigo'] ?? null;
        $descricao = $classe['descricao'] ?? null;
        $nome = $classe['nome'] ?? null;
        if ($codigo === null && $descricao === null) return true;
        return (is_string($codigo) || is_int($codigo))
            && preg_match('/^[0-9]+$/D', trim((string) $codigo)) === 1
            && ClassesRepository::normalizeCodigo((string) $codigo) !== ''
            && ($descricao === null || is_string($descricao))
            && ($nome === null || is_string($nome));
    }

    private function finish(int $id): void
    {
        $q = $this->db->prepare("UPDATE processo_datajud_sync_queue SET status='concluido', processando_em=NULL, atualizado_em=NOW(), updated_at=NOW() WHERE id=:id AND status='cad_inicial_exec'");
        $q->execute([':id' => $id]);
    }

    private function fail(int $id): void
    {
        $q = $this->db->prepare("UPDATE processo_datajud_sync_queue SET status='falhou', processando_em=NULL, updated_at=NOW() WHERE id=:id AND status='cad_inicial_exec'");
        $q->execute([':id' => $id]);
    }
}
