<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/ProcessoAssuntoOrigem.php';
require_once __DIR__ . '/../repositories/AssuntosRepository.php';
require_once __DIR__ . '/../repositories/ProcessoAssuntosRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';

/**
 * Leitura de assuntos por CNJ e sincronização de assuntos a partir do DataJud.
 */
final class ProcessoAssuntoSyncService
{
    private ProcessosRepository $processosRepository;
    private AssuntosRepository $assuntosRepository;
    private ProcessoAssuntosRepository $processoAssuntosRepository;

    public function __construct(PDO $connection)
    {
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
        $digits20 = preg_replace('/\D+/', '', $digits20);
        if (strlen($digits20) !== 20) {
            throw new InvalidArgumentException('Número do processo deve ter 20 dígitos.');
        }

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
    public function syncFromDataJud(string $digits20, array $assuntosPayload): array
    {
        $digits20 = preg_replace('/\D+/', '', $digits20);
        if (strlen($digits20) !== 20) {
            throw new InvalidArgumentException('Número do processo deve ter 20 dígitos.');
        }

        $assuntosNormalizados = $this->normalizeAssuntosPayload($assuntosPayload);
        $assuntoIds = [];
        foreach ($assuntosNormalizados as $item) {
            $assuntoIds[] = $this->assuntosRepository->ensureAssunto($item['codigo'], $item['descricao']);
        }
        $assuntoIds = array_values(array_unique($assuntoIds));

        $processo = $this->processosRepository->findByNumero($digits20);
        $foiCriado = false;
        if ($processo === null) {
            $processoId = $this->processosRepository->ensureProcess($this->formatarCnj20($digits20), null, null, false);
            $foiCriado = true;
        } else {
            $processoId = trim((string) ($processo['id'] ?? ''));
        }

        if ($processoId === '') {
            throw new RuntimeException('Não foi possível resolver o processo para sincronizar assuntos.');
        }

        $stats = $this->processoAssuntosRepository->syncDataJudAssuntos($processoId, $assuntoIds);
        $rows = $this->processoAssuntosRepository->findByProcessoId($processoId);
        $assuntos = $this->mapRowsToAssuntos($rows);

        $action = 'noop';
        if ($foiCriado) {
            $action = 'created';
        } elseif ($stats['inserted'] > 0 || $stats['removed'] > 0) {
            $action = 'updated';
        }

        return [
            'action' => $action,
            'processo_id' => $processoId,
            'assuntos' => $assuntos,
            'stats' => $stats,
        ];
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
        if ($this->processosRepository->findById($processoId) === null) {
            throw new InvalidArgumentException('Processo não encontrado para vincular assunto.');
        }

        $assuntoId = $this->assuntosRepository->ensureAssunto($codigo, $descricao);
        return $this->processoAssuntosRepository->upsert($processoId, $assuntoId, ProcessoAssuntoOrigem::MANUAL);
    }

    public function unlinkManual(string $processoId, string $assuntoId): void
    {
        $processoId = trim((string) $processoId);
        $assuntoId = trim((string) $assuntoId);
        if ($processoId === '' || $assuntoId === '') {
            throw new InvalidArgumentException('processo_id e assunto_id são obrigatórios.');
        }

        $this->processoAssuntosRepository->deleteByProcessoAndAssunto($processoId, $assuntoId);
    }

    /**
     * @param list<mixed> $payload
     * @return list<array{codigo:string,descricao:string}>
     */
    private function normalizeAssuntosPayload(array $payload): array
    {
        $out = [];
        foreach ($payload as $item) {
            $codigoRaw = '';
            $descricao = '';

            if (is_string($item)) {
                $descricao = trim($item);
            } elseif (is_array($item)) {
                $codigoRaw = (string) ($item['codigo'] ?? $item['codigoAssunto'] ?? $item['numAssunto'] ?? '');
                $descricao = (string) ($item['descricao'] ?? $item['descricaoAssunto'] ?? $item['nome'] ?? '');
            } else {
                continue;
            }

            $codigo = AssuntosRepository::normalizeCodigo($codigoRaw);
            $descricao = trim((string) $descricao);
            if ($codigo === '') {
                continue;
            }
            if ($descricao === '') {
                $descricao = $codigo;
            }

            $out[$codigo] = ['codigo' => $codigo, 'descricao' => $descricao];
        }

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
