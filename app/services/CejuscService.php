<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/CejuscRepository.php';
require_once __DIR__ . '/../repositories/CejuscSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/CejuscAlocadosRepository.php';
require_once __DIR__ . '/../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../services/ReuManager.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';

final class CejuscService
{
    private PDO $connection;
    private CejuscRepository $repository;
    private CejuscSubtarefasRepository $subtarefasRepository;
    private CejuscAlocadosRepository $alocadosRepository;
    private AlocacoesRegrasRepository $alocacoesRegrasRepository;
    private ProcessosRepository $processosRepository;
    private ClassesRepository $classesRepository;
    private ReuManager $reuManager;

    private const REGRA_ALOCAR_CEJUSC = 'ALLOCATE-CEJUSC';

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->repository = new CejuscRepository($connection);
        $this->subtarefasRepository = new CejuscSubtarefasRepository($connection);
        $this->alocadosRepository = new CejuscAlocadosRepository($connection);
        $this->alocacoesRegrasRepository = new AlocacoesRegrasRepository($connection);
        $this->processosRepository = new ProcessosRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
        $this->reuManager = new ReuManager($connection);
    }

    public function listTarefas(array $filters, int $limit, int $offset): array
    {
        $rows = $this->repository->fetchList($filters, $limit, $offset);
        foreach ($rows as $i => $row) {
            $idTarefa = (string) ($row['IdCarta'] ?? '');
            if ($idTarefa !== '') {
                $this->subtarefasRepository->ensureFluxoCompleto($idTarefa);
                $rows[$i]['Subtarefas'] = $this->subtarefasRepository->findPendenteOuEmAndamentoByCejuscId($idTarefa);
                $rows[$i]['SubtarefasAll'] = $this->subtarefasRepository->findByCejuscId($idTarefa);
                $alocadosRaw = $this->alocadosRepository->findByCejuscId($idTarefa);
                $rows[$i]['Alocados'] = [];
                $max = 5;
                foreach (array_slice($alocadosRaw, 0, $max) as $a) {
                    $rows[$i]['Alocados'][] = [
                        'iniciais' => $a['iniciais'] ?? '—',
                        'nome_completo' => $a['nome_completo'] ?? '',
                        'usuario_id' => $a['usuario_id'] ?? '',
                        'tipo' => $this->normalizeTipoAlocado($a['tipo'] ?? ''),
                    ];
                }
            } else {
                $rows[$i]['Subtarefas'] = [];
                $rows[$i]['SubtarefasAll'] = [];
                $rows[$i]['Alocados'] = [];
            }
        }
        return [
            'rows' => $rows,
            'total' => $this->repository->count($filters),
        ];
    }

    public function getStatusCounts(array $filters): array
    {
        return $this->repository->countByStatus($filters);
    }

    public function find(string $id): ?array
    {
        return $this->repository->findById($id);
    }

    public function create(array $payload): string
    {
        $numeroProcesso = trim((string) ($payload['numero_processo_origem'] ?? $payload['processo'] ?? ''));
        if ($numeroProcesso === '') {
            throw new InvalidArgumentException('O número do processo é obrigatório.');
        }

        $reuId = trim((string) ($payload['reu_id'] ?? ''));
        $nomeReu = trim((string) ($payload['nome_reu'] ?? ''));
        if ($nomeReu !== '') {
            $nomeReu = TextFormatter::formatProperName($nomeReu, 120);
        }

        $digits = preg_replace('/\D+/', '', $numeroProcesso);
        $numeroFormatado = strlen($digits) === 20 ? $this->formatarCNJ($digits) : $numeroProcesso;

        $processoOrigemId = null;
        if (strlen($digits) === 20) {
            $classeId = null;
            $classeCodigo = trim((string) ($payload['classe_codigo'] ?? ''));
            $classeDescricao = trim((string) ($payload['classe_descricao'] ?? $payload['classe'] ?? ''));
            if ($classeCodigo !== '') {
                $classeNome = $classeDescricao !== '' ? TextFormatter::formatProperName($classeDescricao, 200) : $classeCodigo;
                $classeId = $this->classesRepository->ensureClass($classeCodigo, $classeNome);
            }
            $processoOrigemId = $this->processosRepository->ensureProcess($numeroFormatado, $classeId, null, false);
        }

        if ($reuId !== '') {
            $this->reuManager->vincularReuProcesso($reuId, $numeroFormatado);
        } else {
            if ($nomeReu === '') {
                throw new InvalidArgumentException('O nome do réu é obrigatório.');
            }
            $idReu = $this->reuManager->cadastrarReu($nomeReu, null);
            if ($idReu === null || $idReu === '') {
                throw new InvalidArgumentException('Não foi possível cadastrar ou localizar o réu.');
            }
            $this->reuManager->vincularReuProcesso($idReu, $numeroFormatado);
            $reuId = $idReu;
        }

        $idTarefa = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->repository->getTableName(),
            'id',
            'CJT',
            30
        );

        $row = [
            'id' => $idTarefa,
            'processo_origem_id' => $processoOrigemId,
            'numero_processo_origem' => $numeroFormatado,
            'reu_id' => $reuId,
            'status' => 'pendente',
        ];

        $this->repository->insert($row);

        $usuarioCadastroId = trim((string) ($payload['usuario_cadastro_id'] ?? ''));

        if ($usuarioCadastroId !== '') {
            $this->alocadosRepository->addAlocado($idTarefa, $usuarioCadastroId, CejuscAlocadosRepository::TIPO_ALL);
        }

        $regra = $this->alocacoesRegrasRepository->findByRegra(self::REGRA_ALOCAR_CEJUSC);
        if ($regra !== null) {
            $usuarioIdRegra = trim((string) ($regra['usuario_id'] ?? ''));
            if ($usuarioIdRegra !== '' && $usuarioIdRegra !== $usuarioCadastroId) {
                $this->alocadosRepository->addAlocado($idTarefa, $usuarioIdRegra, CejuscAlocadosRepository::TIPO_RULE);
            }
        }

        $this->subtarefasRepository->create($idTarefa, CejuscSubtarefasRepository::TIPO_ORDEM_1, 1, 'em_andamento', null);
        $this->subtarefasRepository->create($idTarefa, CejuscSubtarefasRepository::TIPO_ORDEM_2, 2, 'pendente', null);
        $this->subtarefasRepository->create($idTarefa, CejuscSubtarefasRepository::TIPO_ORDEM_3, 3, 'pendente', null);

        $this->repository->updateStatus($idTarefa, 'em_andamento');

        return $idTarefa;
    }

    public function concluirSubtarefa(string $subtarefaId, ?string $dadosJson = null): void
    {
        $subtarefa = $this->subtarefasRepository->findById($subtarefaId);
        if ($subtarefa === null) {
            throw new InvalidArgumentException('Subtarefa não encontrada.');
        }

        $ordem = (int) ($subtarefa['ordem'] ?? 0);
        $cejuscId = trim((string) ($subtarefa['cejusc_tarefa_id'] ?? ''));

        if ($cejuscId === '') {
            throw new InvalidArgumentException('Tarefa Cejusc da subtarefa não identificada.');
        }

        $usuarioIdMovimentacao = null;
        if ($dadosJson !== null && $dadosJson !== '') {
            $dadosArray = json_decode($dadosJson, true);
            if (is_array($dadosArray)) {
                $usuarioIdMovimentacao = trim((string) ($dadosArray['usuario_id'] ?? ''));
            }
        }
        if ($usuarioIdMovimentacao !== null && $usuarioIdMovimentacao !== '') {
            $this->alocadosRepository->addAlocado($cejuscId, $usuarioIdMovimentacao);
        }

        if ($dadosJson !== null && $dadosJson !== '') {
            $this->subtarefasRepository->atualizarDadosEspecificos($subtarefaId, $dadosJson);
        }

        $this->subtarefasRepository->marcarConcluida($subtarefaId);

        if ($ordem === 1) {
            $ordem2 = $this->subtarefasRepository->findByCejuscIdEOrdem($cejuscId, 2);
            if ($ordem2 !== null) {
                $this->subtarefasRepository->atualizarStatus((string) $ordem2['id'], 'em_andamento');
            } else {
                $this->subtarefasRepository->create($cejuscId, CejuscSubtarefasRepository::TIPO_ORDEM_2, 2, 'em_andamento', null);
            }
            $this->repository->updateStatus($cejuscId, 'em_andamento');
        } elseif ($ordem === 2) {
            $ordem3 = $this->subtarefasRepository->findByCejuscIdEOrdem($cejuscId, 3);
            if ($ordem3 !== null) {
                $this->subtarefasRepository->atualizarStatus((string) $ordem3['id'], 'em_andamento');
            } else {
                $this->subtarefasRepository->create($cejuscId, CejuscSubtarefasRepository::TIPO_ORDEM_3, 3, 'em_andamento', null);
            }
            $this->repository->updateStatus($cejuscId, 'em_andamento');
        } else {
            $this->repository->updateStatus($cejuscId, 'concluída');
        }
    }

    private function normalizeTipoAlocado(string $tipo): string
    {
        $t = trim($tipo);
        if ($t === CejuscAlocadosRepository::TIPO_RULE || $t === 'rule') {
            return 'rule';
        }
        if ($t === CejuscAlocadosRepository::TIPO_ALL || $t === 'all') {
            return 'all';
        }
        return 'specific';
    }

    private function formatarCNJ(string $digits): string
    {
        if (strlen($digits) !== 20) {
            return $digits;
        }
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

    public function update(string $id, array $payload): bool
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }

        $usuarioId = trim((string) ($payload['usuario_id'] ?? ''));
        unset($payload['usuario_id']);
        if ($usuarioId !== '') {
            $this->alocadosRepository->addAlocado($id, $usuarioId);
        }

        return $this->repository->update($id, $payload);
    }

    public function cancel(string $id): bool
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }
        return $this->repository->updateStatus($id, 'cancelada');
    }

    public function delete(string $id): bool
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }

        $this->subtarefasRepository->deleteByCejuscId($id);
        $this->alocadosRepository->deleteByCejuscId($id);
        return $this->repository->delete($id);
    }

    public function criarPrimeiraSubtarefa(string $cejuscId, ?string $usuarioId = null): void
    {
        $cejuscId = trim($cejuscId);
        if ($cejuscId === '') {
            throw new InvalidArgumentException('Identificador da tarefa Cejusc é obrigatório.');
        }
        if ($this->repository->findById($cejuscId) === null) {
            throw new InvalidArgumentException('Tarefa Cejusc não encontrada.');
        }
        $existentes = $this->subtarefasRepository->findByCejuscId($cejuscId);
        if (count($existentes) > 0) {
            $this->subtarefasRepository->ensureFluxoCompleto($cejuscId);
            $ordem1 = $this->subtarefasRepository->findByCejuscIdEOrdem($cejuscId, 1);
            if ($ordem1 !== null && trim((string) ($ordem1['status'] ?? '')) !== 'concluida') {
                $this->subtarefasRepository->atualizarStatus((string) $ordem1['id'], 'em_andamento');
            }
            $this->repository->updateStatus($cejuscId, 'em_andamento');
            return;
        }
        $this->subtarefasRepository->create($cejuscId, CejuscSubtarefasRepository::TIPO_ORDEM_1, 1, 'em_andamento', null);
        $this->subtarefasRepository->create($cejuscId, CejuscSubtarefasRepository::TIPO_ORDEM_2, 2, 'pendente', null);
        $this->subtarefasRepository->create($cejuscId, CejuscSubtarefasRepository::TIPO_ORDEM_3, 3, 'pendente', null);
        $this->repository->updateStatus($cejuscId, 'em_andamento');
        if ($usuarioId !== null && trim($usuarioId) !== '') {
            $this->alocadosRepository->addAlocado($cejuscId, trim($usuarioId));
        }
    }
}
