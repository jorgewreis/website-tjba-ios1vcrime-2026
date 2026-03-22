<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/CartasRecebidasRepository.php';
require_once __DIR__ . '/../repositories/CartasRecebidasSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/CartasRecebidasAlocadosRepository.php';
require_once __DIR__ . '/../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../repositories/TribunaisRepository.php';
require_once __DIR__ . '/../repositories/ComarcasRepository.php';
require_once __DIR__ . '/../repositories/VarasRepository.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';
require_once __DIR__ . '/../helpers/OrphanSubtarefasCleaner.php';

final class CartasRecebidasService
{
    private PDO $connection;
    private CartasRecebidasRepository $repository;
    private CartasRecebidasSubtarefasRepository $subtarefasRepository;
    private CartasRecebidasAlocadosRepository $alocadosRepository;
    private AlocacoesRegrasRepository $alocacoesRegrasRepository;
    private ProcessosRepository $processosRepository;
    private ClassesRepository $classesRepository;
    private TribunaisRepository $tribunaisRepository;
    private ComarcasRepository $comarcasRepository;
    private VarasRepository $varasRepository;

    private const REGRA_ALOCAR_CARTAS = 'ALLOCATE-CARTASRECEBIDAS';

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->repository = new CartasRecebidasRepository($connection);
        $this->subtarefasRepository = new CartasRecebidasSubtarefasRepository($connection);
        $this->alocadosRepository = new CartasRecebidasAlocadosRepository($connection);
        $this->alocacoesRegrasRepository = new AlocacoesRegrasRepository($connection);
        $this->processosRepository = new ProcessosRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
        $this->tribunaisRepository = new TribunaisRepository($connection);
        $this->comarcasRepository = new ComarcasRepository($connection);
        $this->varasRepository = new VarasRepository($connection);
    }

    public function listCartas(array $filters, int $limit, int $offset): array
    {
        $rows = $this->repository->fetchList($filters, $limit, $offset);
        foreach ($rows as $i => $row) {
            $idCarta = (string) ($row['IdCarta'] ?? '');
            if ($idCarta !== '') {
                $rows[$i]['Subtarefas'] = $this->subtarefasRepository->findPendenteOuEmAndamentoByCartaId($idCarta);
                $rows[$i]['SubtarefasAll'] = $this->subtarefasRepository->findByCartaId($idCarta);
                $alocadosRaw = $this->alocadosRepository->findByCartaId($idCarta);
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

    public function getClasseCounts(array $filters): array
    {
        return $this->repository->countByClasse($filters);
    }

    public function getFinalidadeCounts(array $filters): array
    {
        return $this->repository->countByFinalidade($filters);
    }

    public function create(array $payload): string
    {
        $numeroProcesso = trim((string) ($payload['numero_processo_origem'] ?? $payload['processo'] ?? ''));
        if ($numeroProcesso === '') {
            throw new InvalidArgumentException('O número do processo de origem é obrigatório.');
        }

        $digits = preg_replace('/\D+/', '', $numeroProcesso);
        $numeroFormatado = strlen($digits) === 20
            ? $this->formatarCNJ($digits)
            : $numeroProcesso;

        $ufOrigem = strtoupper(trim((string) ($payload['uf_origem'] ?? $payload['estado'] ?? $payload['uf'] ?? '')));
        if (strlen($ufOrigem) !== 2) {
            throw new InvalidArgumentException('A UF de origem é obrigatória (2 caracteres).');
        }

        $cidadeOrigem = trim((string) ($payload['cidade_origem'] ?? $payload['cidade'] ?? ''));
        if ($cidadeOrigem === '') {
            throw new InvalidArgumentException('A cidade de origem é obrigatória.');
        }
        $cidadeOrigem = TextFormatter::formatProperName($cidadeOrigem, 120);

        $tribunalSigla = 'TJ' . $ufOrigem;
        $tribunalId = $this->tribunaisRepository->ensureTribunal($tribunalSigla, 'Tribunal de Justiça do Estado');
        $comarcaOrigem = $this->comarcasRepository->findByTribunalUfNome($tribunalId, $ufOrigem, $cidadeOrigem);
        $comarcaOrigemId = $comarcaOrigem !== null
            ? (string) $comarcaOrigem['id']
            : $this->comarcasRepository->ensureComarca($tribunalId, $ufOrigem, $cidadeOrigem);

        $dataRecebimento = $this->parseDate($payload['data_recebimento'] ?? null);
        $finalidade = trim((string) ($payload['finalidade'] ?? ''));
        if ($finalidade === '') {
            throw new InvalidArgumentException('A finalidade é obrigatória.');
        }
        $prazoDias = (int) $this->sanitizePrazo((string) ($payload['prazo_dias'] ?? $payload['prazo'] ?? '30'));

        $classeId = null;
        $classeCodigo = trim((string) ($payload['classe_codigo'] ?? ''));
        $classeDescricao = trim((string) ($payload['classe_descricao'] ?? $payload['classe'] ?? ''));
        if ($classeCodigo !== '' && $classeDescricao !== '') {
            $classeId = $this->classesRepository->ensureClass($classeCodigo, TextFormatter::formatProperName($classeDescricao, 200));
        } elseif ($classeCodigo !== '') {
            $classeId = $this->classesRepository->ensureClass($classeCodigo, $classeCodigo);
        }

        $processoOrigemId = null;
        if (strlen($digits) === 20) {
            $processoOrigemId = $this->processosRepository->ensureProcess($numeroFormatado, $classeId, null, false);
        }

        $idCarta = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->repository->getTableName(),
            'id',
            'CPR',
            30
        );

        $row = [
            'id' => $idCarta,
            'processo_origem_id' => $processoOrigemId,
            'numero_processo_origem' => $numeroFormatado,
            'comarca_origem_id' => $comarcaOrigemId,
            'cidade_origem' => $cidadeOrigem,
            'uf_origem' => $ufOrigem,
            'data_recebimento' => $dataRecebimento,
            'finalidade' => $finalidade,
            'prazo_dias' => $prazoDias,
            'status' => 'pendente',
            'observacoes' => trim((string) ($payload['observacoes'] ?? '')),
        ];

        $this->repository->insert($row);

        $usuarioCadastroId = trim((string) ($payload['usuario_cadastro_id'] ?? ''));

        if ($usuarioCadastroId !== '') {
            $this->alocadosRepository->addAlocado($idCarta, $usuarioCadastroId, CartasRecebidasAlocadosRepository::TIPO_ALL);
        }

        $regra = $this->alocacoesRegrasRepository->findByRegra(self::REGRA_ALOCAR_CARTAS);
        if ($regra !== null) {
            $usuarioIdRegra = trim((string) ($regra['usuario_id'] ?? ''));
            if ($usuarioIdRegra !== '' && $usuarioIdRegra !== $usuarioCadastroId) {
                $this->alocadosRepository->addAlocado($idCarta, $usuarioIdRegra, CartasRecebidasAlocadosRepository::TIPO_RULE);
            }
        }

        $repo = $this->subtarefasRepository;
        $repo->create($idCarta, $repo::TIPO_ORDEM_1, 1, 'em_andamento', null);
        $repo->create($idCarta, $repo::TIPO_ORDEM_2, 2, 'pendente', null);
        $repo->create($idCarta, $repo::TIPO_ORDEM_3, 3, 'pendente', null);
        $repo->create($idCarta, $repo::TIPO_ORDEM_4, 4, 'pendente', null);
        $repo->create($idCarta, $repo::TIPO_ORDEM_5, 5, 'pendente', null);
        $repo->create($idCarta, $repo::TIPO_ORDEM_6, 6, 'pendente', null);
        $repo->create($idCarta, $repo::TIPO_ORDEM_7, 7, 'pendente', null);

        $this->repository->update($idCarta, ['status' => 'em_andamento']);

        return $idCarta;
    }

    public function concluirSubtarefa(string $subtarefaId, ?string $dadosJson = null): array
    {
        $subtarefa = $this->subtarefasRepository->findById($subtarefaId);
        if ($subtarefa === null) {
            throw new InvalidArgumentException('Subtarefa não encontrada.');
        }

        $ordem = (int) ($subtarefa['ordem'] ?? 0);
        $cartaId = trim((string) ($subtarefa['carta_recebida_id'] ?? ''));

        if ($cartaId === '') {
            throw new InvalidArgumentException('Carta da subtarefa não identificada.');
        }

        $usuarioIdMovimentacao = null;
        if ($dadosJson !== null && $dadosJson !== '') {
            $dadosArray = json_decode($dadosJson, true);
            if (is_array($dadosArray)) {
                $usuarioIdMovimentacao = trim((string) ($dadosArray['usuario_id'] ?? ''));
            }
        }
        if ($usuarioIdMovimentacao !== '') {
            try {
                $this->alocadosRepository->addAlocado($cartaId, $usuarioIdMovimentacao);
            } catch (RuntimeException $e) {
                // limite ou duplicata
            }
        }

        if ($ordem === 3) {
            $dadosArray = [];
            if ($dadosJson !== null && $dadosJson !== '') {
                $dadosArray = json_decode($dadosJson, true);
            }
            $resultadoCumprimento = trim((string) (($dadosArray['resultado_cumprimento'] ?? $dadosArray['resultado'] ?? '')));
            if ($resultadoCumprimento === '') {
                throw new InvalidArgumentException('Selecione o resultado do cumprimento para concluir a juntada da certidão do oficial de justiça.');
            }
        }

        $this->subtarefasRepository->marcarConcluida($subtarefaId);

        if ($dadosJson !== null && $dadosJson !== '') {
            $stmt = $this->connection->prepare(
                'UPDATE `cartas_recebidas_subtarefas` SET `dados_especificos` = :dados, `updated_at` = NOW() WHERE `id` = :id LIMIT 1'
            );
            $stmt->bindValue(':dados', $dadosJson);
            $stmt->bindValue(':id', $subtarefaId);
            $stmt->execute();
        }

        if ($ordem < 7) {
            $proxima = $this->subtarefasRepository->findByCartaIdEOrdem($cartaId, $ordem + 1);
            if ($proxima !== null) {
                $this->subtarefasRepository->atualizarStatus((string) $proxima['id'], 'em_andamento');
            }
            $this->repository->update($cartaId, ['status' => 'em_andamento']);
            return ['tarefa_principal_concluida' => false];
        } else {
            $this->repository->update($cartaId, ['status' => 'concluída']);
            $carta = $this->repository->find($cartaId);
            $processo = trim((string) ($carta['numero_processo_origem'] ?? ''));
            $finalidade = trim((string) ($carta['finalidade'] ?? ''));
            $cidade = trim((string) ($carta['cidade_origem'] ?? ''));
            $uf = trim((string) ($carta['uf_origem'] ?? ''));
            $msg = 'Tarefa principal concluída automaticamente: carta recebida do processo '
                . ($processo !== '' ? $processo : 'não informado')
                . ' (' . ($finalidade !== '' ? $finalidade : 'finalidade não informada')
                . ' de ' . (($cidade !== '' ? $cidade : 'comarca não informada') . ($uf !== '' ? '/' . $uf : '')) . ').';
            return [
                'tarefa_principal_concluida' => true,
                'tarefa_principal_mensagem' => $msg,
            ];
        }
    }

    public function cancelarSubtarefa(string $subtarefaId): void
    {
        $subtarefa = $this->subtarefasRepository->findById($subtarefaId);
        if ($subtarefa === null) {
            throw new InvalidArgumentException('Subtarefa não encontrada.');
        }

        $ordem = (int) ($subtarefa['ordem'] ?? 0);
        if ($ordem !== 1) {
            throw new InvalidArgumentException('Apenas a subtarefa "Ato ordinatório - Cumprimento" pode ser cancelada.');
        }

        $cartaId = trim((string) ($subtarefa['carta_recebida_id'] ?? ''));
        if ($cartaId === '') {
            throw new InvalidArgumentException('Carta da subtarefa não identificada.');
        }

        $this->subtarefasRepository->atualizarStatus($subtarefaId, CartasRecebidasSubtarefasRepository::STATUS_CANCELADA);

        $proxima = $this->subtarefasRepository->findByCartaIdEOrdem($cartaId, 2);
        if ($proxima !== null) {
            $this->subtarefasRepository->atualizarStatus((string) $proxima['id'], 'em_andamento');
        }
        $this->repository->update($cartaId, ['status' => 'em_andamento']);
    }

    private function normalizeTipoAlocado(string $tipo): string
    {
        $t = trim($tipo);
        if ($t === CartasRecebidasAlocadosRepository::TIPO_RULE || $t === 'rule') {
            return 'rule';
        }
        if ($t === CartasRecebidasAlocadosRepository::TIPO_ALL || $t === 'all') {
            return 'all';
        }
        return 'specific';
    }

    private function parseDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            $ts = (int) $value;
            if ($ts > 1e12) {
                $ts = (int) floor($ts / 1000);
            }
            $date = date('Y-m-d', $ts);
            return $date !== false && $this->isValidDateYear($date) ? $date : null;
        }
        $ts = strtotime((string) $value);
        if ($ts === false) {
            return null;
        }
        $date = date('Y-m-d', $ts);
        return $date !== false && $this->isValidDateYear($date) ? $date : null;
    }

    private function isValidDateYear(string $ymd): bool
    {
        $y = (int) substr($ymd, 0, 4);
        return $y >= 1900 && $y <= 2100;
    }

    private function sanitizePrazo(string $valor): string
    {
        $digits = preg_replace('/\D+/', '', $valor);
        return $digits === '' ? '30' : substr($digits, 0, 3);
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
        return $this->repository->update($id, ['status' => 'cancelada']);
    }

    public function delete(string $id): array
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }

        $subtarefas = $this->subtarefasRepository->findByCartaId($id);
        $mensagensSubtarefas = [];
        foreach ($subtarefas as $subtarefa) {
            $tipo = trim((string) ($subtarefa['tipo'] ?? 'subtarefa'));
            if ($tipo !== '') {
                $mensagensSubtarefas[] = 'Subtarefa excluída: ' . str_replace('_', ' ', $tipo) . '.';
            }
        }
        $this->subtarefasRepository->deleteByCartaId($id);
        $this->alocadosRepository->deleteByCartaId($id);
        $deleted = $this->repository->delete($id);
        $orfandade = OrphanSubtarefasCleaner::cleanAll($this->connection);
        return [
            'deleted' => $deleted,
            'subtarefas_toasts' => $mensagensSubtarefas,
            'tarefa_toast' => 'Tarefa principal excluída com sucesso.',
            'subtarefas_orfas_removidas' => array_sum($orfandade),
        ];
    }

    public function find(string $id): ?array
    {
        return $this->repository->find($id);
    }

    public function criarPrimeiraSubtarefa(string $cartaId, ?string $usuarioId = null): void
    {
        $cartaId = trim($cartaId);
        if ($cartaId === '') {
            throw new InvalidArgumentException('Identificador da carta é obrigatório.');
        }
        if ($this->repository->find($cartaId) === null) {
            throw new InvalidArgumentException('Carta recebida não encontrada.');
        }
        $existentes = $this->subtarefasRepository->findByCartaId($cartaId);
        if (count($existentes) > 0) {
            throw new InvalidArgumentException('Esta carta já possui subtarefas.');
        }
        $repo = $this->subtarefasRepository;
        $repo->create($cartaId, $repo::TIPO_ORDEM_1, 1, 'em_andamento', null);
        $repo->create($cartaId, $repo::TIPO_ORDEM_2, 2, 'pendente', null);
        $repo->create($cartaId, $repo::TIPO_ORDEM_3, 3, 'pendente', null);
        $repo->create($cartaId, $repo::TIPO_ORDEM_4, 4, 'pendente', null);
        $repo->create($cartaId, $repo::TIPO_ORDEM_5, 5, 'pendente', null);
        $repo->create($cartaId, $repo::TIPO_ORDEM_6, 6, 'pendente', null);
        $repo->create($cartaId, $repo::TIPO_ORDEM_7, 7, 'pendente', null);
        $this->repository->update($cartaId, ['status' => 'em_andamento']);
        if ($usuarioId !== null && trim($usuarioId) !== '') {
            $this->alocadosRepository->addAlocado($cartaId, trim($usuarioId));
        }
    }
}
