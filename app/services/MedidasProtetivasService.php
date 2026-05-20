<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/MedidasProtetivasRepository.php';
require_once __DIR__ . '/../repositories/MedidasProtetivasSubtarefasRepository.php';
require_once __DIR__ . '/../repositories/MedidasProtetivasAlocadosRepository.php';
require_once __DIR__ . '/../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../repositories/PartesRepository.php';
require_once __DIR__ . '/../repositories/ProcessosPartesRepository.php';
require_once __DIR__ . '/ProcessoPrioridadesService.php';
require_once __DIR__ . '/../repositories/ProcessoPrioridadesRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../services/CejuscService.php';
require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';
require_once __DIR__ . '/../helpers/OrphanSubtarefasCleaner.php';
require_once __DIR__ . '/../helpers/CartorioProdutividade.php';
require_once __DIR__ . '/../helpers/AlocacaoTarefasPerfisHelper.php';
require_once __DIR__ . '/../helpers/CartorioTarefaStatusPrincipalHelper.php';

final class MedidasProtetivasService
{
    /** Quantidade na subtarefa «Cadastro de partes no PJe» (0 = nenhuma). Limite superior para todas as subtarefas com campo numérico. */
    public const QTD_PARTES_PJE_MIN = 0;
    public const QTD_QUANTIDADE_MAX = 10;

    /** Pontos de produtividade (campo quantidade) ao concluir «Certidão de cumprimento». */
    public const PRODUTIVIDADE_PONTOS_CERTIDAO_CUMPRIMENTO = 3;

    private PDO $connection;
    private MedidasProtetivasRepository $repository;
    private MedidasProtetivasSubtarefasRepository $subtarefasRepository;
    private MedidasProtetivasAlocadosRepository $alocadosRepository;
    private AlocacoesRegrasRepository $alocacoesRegrasRepository;
    private PartesRepository $partesRepository;
    private ProcessosPartesRepository $processosPartesRepository;
    private ProcessoPrioridadesService $processoPrioridadesService;
    private ProcessosRepository $processosRepository;
    private ClassesRepository $classesRepository;
    private CejuscService $cejuscService;

    private const REGRA_ALOCAR_MP = 'ALLOCATE-MEDIDASPROTETIVAS';

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->repository = new MedidasProtetivasRepository($connection);
        $this->subtarefasRepository = new MedidasProtetivasSubtarefasRepository($connection);
        $this->alocadosRepository = new MedidasProtetivasAlocadosRepository($connection);
        $this->alocacoesRegrasRepository = new AlocacoesRegrasRepository($connection);
        $this->partesRepository = new PartesRepository($connection);
        $this->processosPartesRepository = new ProcessosPartesRepository($connection);
        $this->processoPrioridadesService = new ProcessoPrioridadesService($connection);
        $this->processosRepository = new ProcessosRepository($connection);
        $this->classesRepository = new ClassesRepository($connection);
        $this->cejuscService = new CejuscService($connection);
    }

    public function listMedidasProtetivas(array $filters, int $limit, int $offset): array
    {
        $rows = $this->repository->fetchList($filters, $limit, $offset);
        foreach ($rows as $index => $row) {
            $idMedida = (string) ($row['IdMedida'] ?? '');
            if ($idMedida === '') {
                $rows[$index]['Subtarefas'] = [];
                $rows[$index]['SubtarefasAll'] = [];
                $rows[$index]['Alocados'] = [];
                continue;
            }

            $this->subtarefasRepository->ensureDuasSubtarefas($idMedida);
            $rows[$index]['Subtarefas'] = $this->subtarefasRepository->findPendenteOuEmAndamentoByMedidaId($idMedida);
            $rows[$index]['SubtarefasAll'] = $this->subtarefasRepository->findByMedidaId($idMedida);
            $alocadosRaw = $this->alocadosRepository->findByMedidaId($idMedida);
            $alocados = [];
            foreach (array_slice($alocadosRaw, 0, 5) as $alocado) {
                $tipo = $this->normalizeTipoAlocado((string) ($alocado['tipo'] ?? ''));
                $alocados[] = [
                    'alocado_id'    => $alocado['id'] ?? '',
                    'iniciais'      => $alocado['iniciais'] ?? '—',
                    'nome_completo' => $alocado['nome_completo'] ?? '',
                    'usuario_id'    => $alocado['usuario_id'] ?? '',
                    'tipo'          => $tipo,
                ];
            }
            usort($alocados, static function (array $a, array $b): int {
                static $order = ['rule' => 0, 'all' => 1, 'specific' => 2];
                return ($order[$a['tipo']] ?? 9) <=> ($order[$b['tipo']] ?? 9);
            });
            $rows[$index]['Alocados'] = $alocados;
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
        $medida = $this->repository->findById($id);
        if ($medida === null) {
            return null;
        }

        $processoId = trim((string) ($medida['processo_id'] ?? ''));
        $medida['reu'] = [
            'parte_id' => $medida['parte_reu_id'] ?? '',
            'cpf' => $medida['reu_cpf'] ?? '',
            'nome_completo' => $medida['reu_nome_completo'] ?? '',
            'sexo' => $medida['reu_sexo'] ?? '',
            'data_nascimento' => $medida['reu_data_nascimento'] ?? '',
        ];
        $medida['vitimas'] = $processoId !== ''
            ? $this->processosPartesRepository->findByProcessoId($processoId, ProcessosPartesRepository::TIPO_VITIMA)
            : [];
        $medida['prioridades_processo'] = $processoId !== ''
            ? (new ProcessoPrioridadesRepository($this->connection))->findByProcessoId($processoId)
            : [];

        return $medida;
    }

    /**
     * @return array{id: string, cejusc_tarefa_id: string, cejusc_criada: bool}
     */
    public function create(array $payload): array
    {
        $numeroProcesso = trim((string) ($payload['processo'] ?? $payload['numero_processo'] ?? ''));
        if ($numeroProcesso === '') {
            throw new InvalidArgumentException('O número do processo é obrigatório.');
        }

        $digits = preg_replace('/\D+/', '', $numeroProcesso);
        if ($digits === '') {
            throw new InvalidArgumentException('O número do processo é inválido.');
        }

        $numeroFormatado = strlen($digits) === 20 ? $this->formatarCNJ($digits) : $numeroProcesso;
        $classeId = null;
        $classeCodigo = trim((string) ($payload['classe_codigo'] ?? ''));
        $classeDescricao = trim((string) ($payload['classe_descricao'] ?? $payload['classe'] ?? ''));
        if ($classeCodigo !== '') {
            $classeNome = $classeDescricao !== '' ? TextFormatter::formatProperName($classeDescricao, 200) : $classeCodigo;
            $classeId = $this->classesRepository->ensureClass($classeCodigo, $classeNome);
        }
        $processoId = $this->processosRepository->ensureProcess($numeroFormatado, $classeId, null, false);

        $reuPayload = $payload['reu'] ?? null;
        if (!is_array($reuPayload)) {
            throw new InvalidArgumentException('Os dados do réu são obrigatórios.');
        }

        $parteReuId = $this->resolveParteIdFromPayload($reuPayload, 'réu');

        $this->connection->beginTransaction();
        try {
            if ($parteReuId === null) {
                $nomeCompleto = TextFormatter::formatProperName((string) ($reuPayload['nome_completo'] ?? ''), 255);
                if ($nomeCompleto === '') {
                    throw new InvalidArgumentException('Informe o nome completo do réu ou selecione um réu existente.');
                }
                $cpfDigits = preg_replace('/\D+/', '', (string) ($reuPayload['cpf'] ?? ''));
                $cpfParaEnsure = null;
                if ($cpfDigits !== '') {
                    if (strlen($cpfDigits) !== 11) {
                        throw new InvalidArgumentException('CPF do réu incompleto. Informe 11 dígitos ou deixe em branco.');
                    }
                    $cpfParaEnsure = $cpfDigits;
                }
                $parteReuId = $this->partesRepository->ensureParteByNomeOuCpf($nomeCompleto, $cpfParaEnsure);

                $sexoReu = trim((string) ($reuPayload['sexo'] ?? ''));
                $dataNascReu = trim((string) ($reuPayload['data_nascimento'] ?? ''));
                $reuUpdate = [];
                if ($sexoReu !== '') {
                    $reuUpdate['sexo'] = $sexoReu;
                }
                if ($dataNascReu !== '') {
                    $reuUpdate['data_nascimento'] = $dataNascReu;
                }
                if ($reuUpdate !== []) {
                    $this->partesRepository->update($parteReuId, $reuUpdate);
                }
            }
            $vinculoReuExistente = $this->processosPartesRepository->findByProcessoAndParte($processoId, $parteReuId);
            if ($vinculoReuExistente === null) {
                $this->processosPartesRepository->addParte(
                    $processoId,
                    $parteReuId,
                    ProcessosPartesRepository::TIPO_REU,
                    ProcessosPartesRepository::POLO_PASSIVO
                );
            }

            $medidaId = IdGenerator::generateUniquePrefixedId(
                $this->connection,
                $this->repository->getTableName(),
                'id',
                'MPR',
                30
            );

            $usuarioCadastroId = trim((string) ($payload['usuario_cadastro_id'] ?? ''));
            $insertRow = [
                'id' => $medidaId,
                'processo_id' => $processoId,
                'parte_reu_id' => $parteReuId,
                'status' => 'pendente',
                'observacoes' => trim((string) ($payload['observacoes'] ?? '')),
            ];
            if ($usuarioCadastroId !== '') {
                $insertRow['criado_por'] = $usuarioCadastroId;
            }
            $this->repository->insert($insertRow);
            if ($usuarioCadastroId !== ''
                && !AlocacaoTarefasPerfisHelper::usuarioComPerfilChefiaSemAutoalocacao($usuarioCadastroId)) {
                $this->alocadosRepository->addAlocado($medidaId, $usuarioCadastroId, MedidasProtetivasAlocadosRepository::TIPO_ALL);
            }

            $regra = $this->alocacoesRegrasRepository->findByRegra(self::REGRA_ALOCAR_MP);
            if ($regra !== null) {
                $usuarioIdRegra = trim((string) ($regra['usuario_id'] ?? ''));
                if ($usuarioIdRegra !== '' && $usuarioIdRegra !== $usuarioCadastroId
                    && !AlocacaoTarefasPerfisHelper::usuarioComPerfilChefiaSemAutoalocacao($usuarioIdRegra)) {
                    $this->alocadosRepository->addAlocado($medidaId, $usuarioIdRegra, MedidasProtetivasAlocadosRepository::TIPO_RULE);
                }
            }

            AlocacaoTarefasPerfisHelper::registrarAlocacoesDirecionadasNoCadastro(
                $this->connection,
                $usuarioCadastroId,
                $payload,
                function (string $uid) use ($medidaId): void {
                    try {
                        $this->alocadosRepository->addAlocado($medidaId, $uid, MedidasProtetivasAlocadosRepository::TIPO_SPECIFIC);
                    } catch (Throwable $e) {
                        // limite ou duplicata
                    }
                }
            );

            $cejuscResult = $this->cejuscService->createFromMedidaIfNotExists([
                'numero_processo_origem' => $numeroFormatado,
                'parte_id' => $parteReuId,
                'classe_codigo' => $classeCodigo,
                'classe_descricao' => $classeDescricao,
                'usuario_cadastro_id' => $usuarioCadastroId,
            ]);
            $cejuscId = trim((string) ($cejuscResult['id'] ?? ''));
            $this->repository->update($medidaId, ['cejusc_tarefa_id' => $cejuscId]);

            $this->processoPrioridadesService->registrarPrioridadesCadastroMedidaProtetiva($processoId);

            if ($usuarioCadastroId !== '' && $usuarioCadastroId !== 'sistema') {
                $classeObs = CartorioProdutividade::nonEmpty(
                    $classeDescricao !== '' ? $classeDescricao : $classeCodigo,
                    'classe não informada'
                );
                CartorioProdutividade::record($this->connection, $usuarioCadastroId, 'cartorio-medidas-protetivas.create', [
                    'referencia_item_id' => $medidaId,
                    'referencia_sistema' => 'cartorio-medidas-protetivas',
                    'numero_processo' => $numeroFormatado,
                    'classe_processo' => $classeObs,
                ]);
            }

            $this->connection->commit();
            return [
                'id' => $medidaId,
                'cejusc_tarefa_id' => $cejuscId,
                'cejusc_criada' => (bool) ($cejuscResult['created'] ?? false),
            ];
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function criarPrimeiraSubtarefa(string $medidaId, ?string $usuarioId = null): void
    {
        $medidaId = trim($medidaId);
        if ($medidaId === '') {
            throw new InvalidArgumentException('Identificador da medida protetiva é obrigatório.');
        }
        if ($this->repository->findById($medidaId) === null) {
            throw new InvalidArgumentException('Medida protetiva não encontrada.');
        }

        $existentes = $this->subtarefasRepository->findByMedidaId($medidaId);
        if (count($existentes) > 0) {
            $status = CartorioTarefaStatusPrincipalHelper::principalStatusPadrao(
                $existentes,
                ['concluida'],
                'concluída'
            );
            $this->repository->update($medidaId, ['status' => $status]);
            return;
        }

        $this->subtarefasRepository->create($medidaId, MedidasProtetivasSubtarefasRepository::TIPO_ORDEM_1, 1, 'em_andamento', null);
        $this->subtarefasRepository->create($medidaId, MedidasProtetivasSubtarefasRepository::TIPO_ORDEM_2, 2, 'pendente', null);
        $this->subtarefasRepository->create($medidaId, MedidasProtetivasSubtarefasRepository::TIPO_ORDEM_3, 3, 'pendente', null);
        $this->subtarefasRepository->create($medidaId, MedidasProtetivasSubtarefasRepository::TIPO_ORDEM_4, 4, 'pendente', null);
        $this->repository->update($medidaId, ['status' => 'pendente']);

        if ($usuarioId !== null && trim($usuarioId) !== ''
            && !AlocacaoTarefasPerfisHelper::usuarioComPerfilChefiaSemAutoalocacao(trim($usuarioId))) {
            $this->alocadosRepository->addAlocado($medidaId, trim($usuarioId), MedidasProtetivasAlocadosRepository::TIPO_SPECIFIC);
        }
    }

    public function concluirSubtarefa(string $subtarefaId, ?string $dadosJson = null): array
    {
        require_once __DIR__ . '/../helpers/CartorioSubtarefaUsuarioSessao.php';
        $dadosJson = CartorioSubtarefaUsuarioSessao::mergeDadosEspecificosComSessao($this->connection, $dadosJson);

        $subtarefa = $this->subtarefasRepository->findById($subtarefaId);
        if ($subtarefa === null) {
            throw new InvalidArgumentException('Subtarefa não encontrada.');
        }

        $medidaId = trim((string) ($subtarefa['medida_id'] ?? ''));
        $ordem = (int) ($subtarefa['ordem'] ?? 0);
        if ($medidaId === '' || $ordem < 1) {
            throw new InvalidArgumentException('Subtarefa inválida.');
        }

        if ($dadosJson !== null && $dadosJson !== '') {
            $dadosArray = json_decode($dadosJson, true);
            if (!is_array($dadosArray)) {
                throw new InvalidArgumentException('Dados específicos inválidos.');
            }
            $tipoSub = trim((string) ($subtarefa['tipo'] ?? ''));
            $qtd = array_key_exists('qtd', $dadosArray) ? (int) $dadosArray['qtd'] : 1;

            if ($tipoSub === MedidasProtetivasSubtarefasRepository::TIPO_ORDEM_1) {
                if ($qtd < self::QTD_PARTES_PJE_MIN || $qtd > self::QTD_QUANTIDADE_MAX) {
                    throw new InvalidArgumentException(
                        'A quantidade de partes cadastradas no PJe deve ser entre '
                        . self::QTD_PARTES_PJE_MIN . ' e ' . self::QTD_QUANTIDADE_MAX . '.'
                    );
                }
            } elseif ($tipoSub === MedidasProtetivasSubtarefasRepository::TIPO_ORDEM_2) {
                if ($qtd < 1) {
                    throw new InvalidArgumentException('Informe uma quantidade maior que zero para concluir a subtarefa.');
                }
                if ($qtd > self::QTD_QUANTIDADE_MAX) {
                    throw new InvalidArgumentException('A quantidade não pode ser maior que ' . self::QTD_QUANTIDADE_MAX . '.');
                }
            } elseif ($tipoSub === MedidasProtetivasSubtarefasRepository::TIPO_ORDEM_4) {
                $qtd = self::PRODUTIVIDADE_PONTOS_CERTIDAO_CUMPRIMENTO;
            } else {
                // Subtarefas sem complemento numérico (ex.: cadastro no BNMP) usam quantidade fixa 1 na produtividade.
                $qtd = 1;
            }

            $usuarioIdMovimentacao = trim((string) ($dadosArray['usuario_id'] ?? ''));
            if ($usuarioIdMovimentacao !== '') {
                try {
                    $this->alocadosRepository->addAlocado($medidaId, $usuarioIdMovimentacao, MedidasProtetivasAlocadosRepository::TIPO_SPECIFIC);
                } catch (\RuntimeException $e) {
                    // limite ou duplicata — não impede a conclusão da subtarefa
                }
            }
            $this->subtarefasRepository->atualizarDadosEspecificos($subtarefaId, $dadosJson);
        } else {
            throw new InvalidArgumentException('Os dados da subtarefa são obrigatórios.');
        }

        $this->subtarefasRepository->atualizarStatus($subtarefaId, 'concluida');
        $tipoSubtarefa = trim((string) ($subtarefa['tipo'] ?? ''));
        if ($usuarioIdMovimentacao !== '' && $usuarioIdMovimentacao !== 'sistema' && $tipoSubtarefa !== '') {
            $medidaAtual = $this->repository->findById($medidaId) ?? [];
            $classeMedida = CartorioProdutividade::nonEmpty(
                trim((string) ($medidaAtual['classe_nome'] ?? $medidaAtual['classe_codigo'] ?? '')),
                'classe não informada'
            );
            $numeroProcessoMedida = trim((string) ($medidaAtual['numero_processo'] ?? $medidaAtual['numero_processo_origem'] ?? ''));
            CartorioProdutividade::record($this->connection, $usuarioIdMovimentacao, 'cartorio-medidas-protetivas.subtarefa.' . $tipoSubtarefa, [
                'referencia_item_id' => $subtarefaId,
                'referencia_sistema' => 'cartorio-medidas-protetivas',
                'numero_processo' => $numeroProcessoMedida,
                'classe_processo' => $classeMedida,
                'quantidade' => $qtd,
            ]);
        }
        $subtarefas = $this->subtarefasRepository->findByMedidaId($medidaId);
        $proximaOrdem = $ordem + 1;
        $proxima = null;
        foreach ($subtarefas as $item) {
            if ((int) ($item['ordem'] ?? 0) === $proximaOrdem) {
                $proxima = $item;
                break;
            }
        }

        if ($proxima !== null) {
            $this->subtarefasRepository->atualizarStatus((string) $proxima['id'], 'em_andamento');
            $this->repository->update($medidaId, ['status' => 'em_andamento']);
            return ['tarefa_principal_concluida' => false];
        }

        $this->repository->update($medidaId, ['status' => 'concluída']);
        $medida = $this->repository->findById($medidaId);
        $processo = trim((string) ($medida['numero_processo'] ?? $medida['numero_processo_origem'] ?? ''));
        $reu = trim((string) ($medida['reu_nome_completo'] ?? ''));
        return [
            'tarefa_principal_concluida' => true,
            'tarefa_principal_mensagem' => 'Tarefa principal concluída automaticamente: medida protetiva do processo '
                . ($processo !== '' ? $processo : 'não informado')
                . ' (réu: ' . ($reu !== '' ? $reu : 'não informado') . ').',
        ];
    }

    public function update(string $id, array $payload): bool
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador informado é inválido.');
        }

        $allowed = ['observacoes', 'status', 'cejusc_tarefa_id'];
        $data = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $payload)) {
                $data[$key] = $payload[$key];
            }
        }
        if (empty($data)) {
            return false;
        }

        return $this->repository->update($id, $data);
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

        $subtarefas = $this->subtarefasRepository->findByMedidaId($id);
        $mensagensSubtarefas = [];
        foreach ($subtarefas as $subtarefa) {
            $tipo = trim((string) ($subtarefa['tipo'] ?? 'subtarefa'));
            if ($tipo !== '') {
                $mensagensSubtarefas[] = 'Subtarefa excluída: ' . str_replace('_', ' ', $tipo) . '.';
            }
        }
        $this->subtarefasRepository->deleteByMedidaId($id);
        $this->alocadosRepository->deleteByMedidaId($id);
        $deleted = $this->repository->delete($id);
        $orfandade = OrphanSubtarefasCleaner::cleanAll($this->connection);
        return [
            'deleted' => $deleted,
            'subtarefas_toasts' => $mensagensSubtarefas,
            'tarefa_toast' => 'Tarefa principal excluída com sucesso.',
            'subtarefas_orfas_removidas' => array_sum($orfandade),
        ];
    }

    /**
     * Se o payload tiver parte_id válido, retorna esse id. Caso contrário retorna null (caller deve validar e ensureParte).
     */
    private function resolveParteIdFromPayload(array $payload, string $label): ?string
    {
        $parteId = trim((string) ($payload['parte_id'] ?? ''));
        if ($parteId === '') {
            return null;
        }
        $parte = $this->partesRepository->findById($parteId);
        if ($parte !== null) {
            return $parte['id'];
        }
        throw new InvalidArgumentException('Parte informada para ' . $label . ' não foi encontrada.');
    }

    private function normalizeTipoAlocado(string $tipo): string
    {
        if ($tipo === MedidasProtetivasAlocadosRepository::TIPO_RULE || $tipo === 'rule') {
            return 'rule';
        }
        if ($tipo === MedidasProtetivasAlocadosRepository::TIPO_ALL || $tipo === 'all') {
            return 'all';
        }

        return 'specific';
    }

    public function removeAlocado(string $alocadoId, string $tipo, string $requestUserId): bool
    {
        $alocadoId     = trim($alocadoId);
        $requestUserId = trim($requestUserId);
        if ($alocadoId === '' || $requestUserId === '') {
            return false;
        }
        return $this->alocadosRepository->deleteById($alocadoId);
    }

    public function alocarUsuarioDirecionado(string $medidaId, string $targetUsuarioId, string $actorId): void
    {
        $medidaId = trim($medidaId);
        if ($medidaId === '') {
            throw new InvalidArgumentException('Identificador da medida inválido.');
        }
        $row = $this->repository->findById($medidaId);
        if ($row === null) {
            throw new InvalidArgumentException('Medida protetiva não encontrada.');
        }
        $st = trim((string) ($row['status'] ?? ''));
        if (in_array($st, ['cancelada', 'concluída'], true)) {
            throw new InvalidArgumentException('Não é possível alterar alocações neste status.');
        }
        AlocacaoTarefasPerfisHelper::registrarAlocacaoDirecionadaNaEdicao(
            $this->connection,
            $actorId,
            $targetUsuarioId,
            function (string $uid) use ($medidaId): void {
                $this->alocadosRepository->addAlocado($medidaId, $uid, MedidasProtetivasAlocadosRepository::TIPO_SPECIFIC);
            }
        );
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
}
