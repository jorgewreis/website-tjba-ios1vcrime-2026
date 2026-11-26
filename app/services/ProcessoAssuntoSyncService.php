<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/CnjValidator.php';
require_once __DIR__ . '/../helpers/ProcessoAssuntoOrigem.php';
require_once __DIR__ . '/../repositories/AssuntosRepository.php';
require_once __DIR__ . '/../repositories/ProcessoAssuntosRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';

/**
 * Leitura de assuntos por CNJ e sincronização de assuntos a partir do DataJud.
 */
final class ProcessoAssuntoSyncService
{
    private PDO $connection;
    private ProcessosRepository $processosRepository;
    private AssuntosRepository $assuntosRepository;
    private ProcessoAssuntosRepository $processoAssuntosRepository;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->processosRepository = new ProcessosRepository($connection);
        $this->assuntosRepository = new AssuntosRepository($connection);
        $this->processoAssuntosRepository = new ProcessoAssuntosRepository($connection);
    }

    /**
     * @return array{
     *   processo:?array{id:string,numero_processo:string},
     *   assuntos:list<array{id:string,codigo:string,descricao:string,origem:string}>
     * }
     */
    public function readByNumero20(string $digits20): array
    {
        $digits20 = CnjValidator::digits20OrThrow($digits20);

        $processo = $this->processosRepository->findByNumero($digits20);
        if ($processo === null) {
            return ['processo' => null, 'assuntos' => []];
        }

        $processoId = trim((string) ($processo['id'] ?? ''));
        if ($processoId === '') {
            return ['processo' => null, 'assuntos' => []];
        }

        $rows = $this->processoAssuntosRepository->findByProcessoId($processoId);
        return [
            'processo' => [
                'id' => $processoId,
                'numero_processo' => trim((string) ($processo['numero_processo'] ?? '')),
            ],
            'assuntos' => $this->mapRowsToAssuntos($rows),
        ];
    }

    /**
     * @param list<mixed> $assuntosPayload
     * @return array{
     *   action:'noop'|'updated'|'created',
     *   processo_id:string,
     *   assuntos:list<array{id:string,codigo:string,descricao:string,origem:string}>,
     *   stats:array{inserted:int,removed:int,unchanged:int}
     * }
     */
    public function syncFromDataJud(
        string $digits20,
        array $assuntosPayload,
        bool $requireExistingProcess = false,
        bool $allowVerifiedEmptyPayload = false,
        bool $preserveCatalog = false
    ): array
    {
        $digits20 = CnjValidator::digits20OrThrow($digits20);

        $assuntosNormalizados = $this->normalizeAssuntosPayload($assuntosPayload, $allowVerifiedEmptyPayload);
        $processo = $this->processosRepository->findByNumero($digits20);
        if ($requireExistingProcess && $processo === null) {
            throw new InvalidArgumentException('Processo não encontrado no cadastro local.');
        }
        $propria = !$this->connection->inTransaction();
        $savepoint = $propria ? null : 'assunto_sync_' . bin2hex(random_bytes(8));
        if ($propria) $this->connection->beginTransaction();
        else $this->connection->exec('SAVEPOINT ' . $savepoint);
        try {
        $foiCriado = false;
        if ($processo === null) {
            $processoId = $this->processosRepository->ensureProcess($this->formatarCnj20($digits20), null, null, null);
            $foiCriado = true;
        } else {
            $processoId = trim((string) ($processo['id'] ?? ''));
        }

        if ($processoId === '') {
            throw new RuntimeException('Não foi possível resolver o processo para sincronizar assuntos.');
        }

        // Serializa sincronizações do mesmo processo que usam este serviço.
        $lock = $this->connection->prepare('SELECT `id` FROM `processos` WHERE `id` = :id FOR UPDATE');
        $lock->execute([':id' => $processoId]);
        if ($lock->fetchColumn() === false) {
            throw new RuntimeException('Processo não encontrado durante a sincronização.');
        }
        $assuntoIds = [];
        foreach ($assuntosNormalizados as $item) {
            $existing = $preserveCatalog ? $this->assuntosRepository->findByCodigo($item['codigo'], true) : null;
            $assuntoIds[] = $existing !== null
                ? (string) $existing['id']
                : $this->assuntosRepository->ensureAssunto($item['codigo'], $item['descricao'], true);
        }
        $assuntoIds = array_values(array_unique($assuntoIds));

        $stats = $this->processoAssuntosRepository->syncDataJudAssuntos($processoId, $assuntoIds, true);
        $rows = $this->processoAssuntosRepository->findByProcessoId($processoId, true);
        $assuntos = $this->mapRowsToAssuntos($rows);

        $action = 'noop';
        if ($foiCriado) {
            $action = 'created';
        } elseif ($stats['inserted'] > 0 || $stats['removed'] > 0) {
            $action = 'updated';
        }

        $result = [
            'action' => $action,
            'processo_id' => $processoId,
            'assuntos' => $assuntos,
            'stats' => $stats,
        ];
        if ($propria) $this->connection->commit();
        else $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
        return $result;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                if ($propria) $this->connection->rollBack();
                else {
                    $this->connection->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                    $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
                }
            }
            throw $exception;
        }
    }

    /**
     * @return bool true quando cria vínculo novo
     */
    public function linkManual(string $processoId, string $codigo, string $descricao = ''): bool
    {
        $processoId = trim((string) $processoId);
        if ($processoId === '') {
            throw new InvalidArgumentException('processo_id é obrigatório.');
        }
        return $this->withProcessLock($processoId, true, function () use ($processoId, $codigo, $descricao): bool {
            $assuntoId = $this->assuntosRepository->ensureAssunto($codigo, $descricao, true);
            return $this->processoAssuntosRepository->upsert($processoId, $assuntoId, ProcessoAssuntoOrigem::MANUAL, true);
        });
    }

    public function unlinkManual(string $processoId, string $assuntoId): bool
    {
        $processoId = trim((string) $processoId);
        $assuntoId = trim((string) $assuntoId);
        if ($processoId === '' || $assuntoId === '') {
            throw new InvalidArgumentException('processo_id e assunto_id são obrigatórios.');
        }

        return (bool) $this->withProcessLock($processoId, false, function () use ($processoId, $assuntoId): bool {
            return $this->processoAssuntosRepository->deleteByProcessoAndAssunto($processoId, $assuntoId);
        });
    }

    private function withProcessLock(string $processoId, bool $required, callable $operation): mixed
    {
        $propria = !$this->connection->inTransaction();
        $savepoint = $propria ? null : 'assunto_manual_' . bin2hex(random_bytes(8));
        if ($propria) $this->connection->beginTransaction();
        else $this->connection->exec('SAVEPOINT ' . $savepoint);
        try {
            $lock = $this->connection->prepare('SELECT `id` FROM `processos` WHERE `id` = :id FOR UPDATE');
            $lock->execute([':id' => $processoId]);
            if ($lock->fetchColumn() === false) {
                if ($required) throw new InvalidArgumentException('Processo não encontrado para vincular assunto.');
                $result = null;
            } else {
                $result = $operation();
            }
            if ($propria) $this->connection->commit();
            else $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
            return $result;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                if ($propria) $this->connection->rollBack();
                else {
                    $this->connection->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                    $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
                }
            }
            throw $exception;
        }
    }

    /**
     * @param list<mixed> $payload
     * @return list<array{codigo:string,descricao:string}>
     */
    private function normalizeAssuntosPayload(array $payload, bool $allowEmpty): array
    {
        // A ausência de assuntos não prova que a origem devolveu um lote completo.
        // Somente o agregador, após validar a resposta DataJud, pode autorizar []
        // como resultado explícito e completo sem assuntos.
        if (($payload === [] && !$allowEmpty) || !array_is_list($payload)) {
            throw new InvalidArgumentException('Informe uma lista não vazia de assuntos válidos.');
        }
        $out = [];
        foreach ($payload as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Lista de assuntos inválida.');
            }
            $codigoRaw = null;
            foreach (['codigo', 'codigoAssunto', 'numAssunto'] as $key) {
                if (!array_key_exists($key, $item) || $item[$key] === null || (is_string($item[$key]) && trim($item[$key]) === '')) continue;
                $codigoRaw = $item[$key];
                break;
            }
            $descricaoRaw = '';
            foreach (['descricao', 'descricaoAssunto', 'nome'] as $key) {
                if (!array_key_exists($key, $item) || $item[$key] === null || (is_string($item[$key]) && trim($item[$key]) === '')) continue;
                $descricaoRaw = $item[$key];
                break;
            }
            if ((!is_string($codigoRaw) && !is_int($codigoRaw)) || !is_string($descricaoRaw)) {
                throw new InvalidArgumentException('Lista de assuntos inválida.');
            }
            $codigo = AssuntosRepository::normalizeCodigo((string) $codigoRaw);
            $descricao = trim($descricaoRaw);
            if ($codigo === '') {
                throw new InvalidArgumentException('Lista de assuntos inválida.');
            }
            if ($descricao === '') {
                $descricao = $codigo;
            }

            $out[$codigo] = ['codigo' => $codigo, 'descricao' => $descricao];
        }

        ksort($out, SORT_STRING);
        return array_values($out);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{id:string,codigo:string,descricao:string,origem:string}>
     */
    private function mapRowsToAssuntos(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $id = trim((string) ($row['assunto_id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $items[] = [
                'id' => $id,
                'codigo' => trim((string) ($row['assunto_codigo'] ?? '')),
                'descricao' => trim((string) ($row['assunto_descricao'] ?? '')),
                'origem' => trim((string) ($row['origem'] ?? '')),
            ];
        }

        return $items;
    }

    private function formatarCnj20(string $digits): string
    {
        return sprintf(
            '%s-%s.%s.%s.%s.%s',
            substr($digits, 0, 7),
            substr($digits, 7, 2),
            substr($digits, 9, 4),
            substr($digits, 13, 1),
            substr($digits, 14, 2),
            substr($digits, 16, 4)
        );
    }
}
