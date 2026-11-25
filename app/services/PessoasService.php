<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/TextFormatter.php';
require_once __DIR__ . '/../helpers/ProfissaoValidator.php';
require_once __DIR__ . '/../helpers/ParteDocumentoHelper.php';
require_once __DIR__ . '/../helpers/ParteDocumentoLock.php';
require_once __DIR__ . '/../repositories/PartesRepository.php';
require_once __DIR__ . '/../repositories/PartesDocumentosRepository.php';
require_once __DIR__ . '/../repositories/PartesTelefonesRepository.php';
require_once __DIR__ . '/../repositories/PartesInstituicoesRepository.php';
require_once __DIR__ . '/../repositories/ProcessosPartesRepository.php';

final class PessoaDocumentoEstadoAlterado extends RuntimeException {}

final class PessoasService
{
    private PDO $connection;
    private PartesRepository $partesRepository;
    private PartesDocumentosRepository $documentosRepository;
    private PartesTelefonesRepository $telefonesRepository;
    private PartesInstituicoesRepository $partesInstituicoesRepository;
    private ProcessosPartesRepository $processosPartesRepository;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->partesRepository = new PartesRepository($connection);
        $this->documentosRepository = new PartesDocumentosRepository($connection);
        $this->telefonesRepository = new PartesTelefonesRepository($connection);
        $this->partesInstituicoesRepository = new PartesInstituicoesRepository($connection);
        $this->processosPartesRepository = new ProcessosPartesRepository($connection);
    }

    public function list(array $filters, int $limit, int $offset): array
    {
        $rows = $this->partesRepository->fetchList($filters, $limit, $offset);
        foreach ($rows as $i => $row) {
            $parteId = (string) ($row['id'] ?? '');
            $rows[$i]['total_processos'] = $parteId !== '' ? count($this->processosPartesRepository->findByParteId($parteId)) : 0;
            $vinculosInst = $parteId !== '' ? $this->partesInstituicoesRepository->findByParteId($parteId, true, !empty($filters['somente_instituicoes_ativas'])) : [];
            $rows[$i]['total_instituicoes'] = count($vinculosInst);
            $rows[$i]['documentos'] = $parteId !== '' ? $this->documentosRepository->findByParteId($parteId) : [];
            $rows[$i]['telefones'] = $parteId !== '' ? $this->telefonesRepository->findByParteId($parteId) : [];
            $rows[$i]['instituicoes'] = array_map(function (array $v): array {
                return [
                    'nome' => $v['instituicao_nome'] ?? '',
                    'sigla' => $v['instituicao_sigla'] ?? '',
                    'profissao' => $v['profissao'] ?? '',
                    'cargo_funcao' => $v['cargo_funcao'] ?? '',
                ];
            }, $vinculosInst);
        }
        return [
            'rows' => $rows,
            'total' => $this->partesRepository->count($filters),
        ];
    }

    public function find(string $id, bool $onlyActiveInstitutions = false): ?array
    {
        $parte = $this->partesRepository->findById($id);
        if ($parte === null) {
            return null;
        }
        $parte['documentos'] = $this->documentosRepository->findByParteId($id);
        $parte['telefones'] = $this->telefonesRepository->findByParteId($id);
        $parte['vinculos_instituicoes'] = $this->partesInstituicoesRepository->findByParteId($id, null, $onlyActiveInstitutions);
        $parte['vinculos_processos'] = $this->processosPartesRepository->findByParteId($id);
        return $parte;
    }

    public function create(array $payload): string
    {
        $documentosPayload = $this->normalizarDocumentosPayload($payload);
        if ($documentosPayload === [] && !empty($payload['cpf'])) {
            $cpfUnico = ParteDocumentoHelper::normalizarNumero('CPF', (string) $payload['cpf']);
            if ($cpfUnico !== '') {
                ParteDocumentoHelper::validarNumero('CPF', $cpfUnico);
                $documentosPayload = [['tipo' => 'CPF', 'numero' => $cpfUnico]];
            }
        }

        return ParteDocumentoLock::runMany($this->connection, $documentosPayload, function () use ($payload, $documentosPayload): string {
            if (!$this->connection->beginTransaction()) {
                throw new RuntimeException('Não foi possível iniciar o cadastro da pessoa.');
            }
            try {
                ParteDocumentoLock::requireTransactionalTables($this->connection, self::tabelasAdicionaisCadastro($payload));
                $id = $this->partesRepository->insert([
                    'cpf' => '',
                    'nome_completo' => $payload['nome_completo'] ?? '',
                    'sexo' => $payload['sexo'] ?? '',
                    'data_nascimento' => $payload['data_nascimento'] ?? '',
                    'profissao' => $this->resolverProfissaoParaPayload($payload, null),
                ]);

                $this->persistirDocumentos($id, $documentosPayload);

                $telefones = $payload['telefones'] ?? [];
                if (is_array($telefones)) {
                    foreach ($telefones as $tel) {
                        $numero = trim((string) ($tel['numero'] ?? ''));
                        if ($numero !== '') {
                            $this->telefonesRepository->insert([
                                'parte_id' => $id,
                                'tipo' => trim((string) ($tel['tipo'] ?? '')) ?: null,
                                'numero' => $numero,
                            ]);
                        }
                    }
                }

                ParteDocumentoLock::commit($this->connection, 'cadastro da pessoa');
                return $id;
            } catch (Throwable $e) {
                if ($this->connection->inTransaction()) {
                    try {
                        if (!$this->connection->rollBack()) {
                            throw new RuntimeException('Rollback do cadastro retornou false.');
                        }
                    } catch (Throwable $rollbackError) {
                        ParteDocumentoLock::markConnectionUnsafe($this->connection);
                        throw new DocumentoResultadoIncerto('nao_confirmada', $e);
                    }
                }
                throw $e;
            }
        });
    }

    /** @return list<string> */
    private static function tabelasAdicionaisCadastro(array $payload): array
    {
        $telefones = $payload['telefones'] ?? [];
        if (is_array($telefones)) {
            foreach ($telefones as $telefone) {
                if (trim((string) ($telefone['numero'] ?? '')) !== '') {
                    return ['partes_telefones'];
                }
            }
        }
        return [];
    }

    public function update(string $id, array $payload): bool
    {
        return $this->operacaoProfissaoAtomica(function () use ($id, $payload): bool {
            $existente = $this->partesRepository->findByIdForUpdate($id);
            if ($existente === null) {
                return false;
            }
            $allowed = ['nome_completo', 'sexo', 'data_nascimento'];
            $data = array_intersect_key($payload, array_flip($allowed));
            $anterior = trim((string) ($existente['profissao'] ?? ''));
            if (array_key_exists('profissao_selecao', $payload) || array_key_exists('profissao_outra', $payload) || array_key_exists('profissao', $payload)) {
                $data['profissao'] = $this->resolverProfissaoParaPayload($payload, $anterior);
            }
            if ($data === []) {
                return true;
            }
            $ok = $this->partesRepository->update($id, $data);
            if ($ok) {
                $this->reconciliarProfissaoParteComVinculosInstituicao($id);
            }
            return $ok;
        });
    }

    public function addDocumento(string $parteId, array $row): string
    {
        $tipo = ParteDocumentoHelper::normalizarTipo((string) ($row['tipo'] ?? ''));
        $numero = ParteDocumentoHelper::normalizarNumero($tipo, (string) ($row['numero'] ?? ''));
        if ($tipo === '' || !ParteDocumentoHelper::isTipoValido($tipo)) {
            throw new InvalidArgumentException('Tipo de documento é obrigatório.');
        }
        ParteDocumentoHelper::validarNumero($tipo, $numero);

        return $this->comLocksDocumento($parteId, $tipo, $numero,
            function () use ($parteId, $tipo, $numero): string {
                $existentes = $this->documentosRepository->findByParteId($parteId);
                foreach ($existentes as $doc) {
                    if (ParteDocumentoHelper::normalizarTipo((string) ($doc['tipo'] ?? '')) === $tipo) {
                        throw new InvalidArgumentException('Esta pessoa já possui documento do tipo ' . $tipo . '.');
                    }
                }

                $newId = $this->documentosRepository->insert([
                    'parte_id' => $parteId,
                    'tipo' => $tipo,
                    'numero' => $numero,
                ]);
                $this->sincronizarCpfLegado($parteId);

                return $newId;
            }
        );
    }

    public function deleteDocumento(string $id): bool
    {
        $row = $this->documentosRepository->findById($id);
        if ($row === null) {
            return false;
        }
        $parteId = trim((string) ($row['parte_id'] ?? ''));
        $tipo = ParteDocumentoHelper::normalizarTipo((string) ($row['tipo'] ?? ''));
        $numero = ParteDocumentoHelper::normalizarNumero($tipo, (string) ($row['numero'] ?? ''));
        if ($parteId === '' || $tipo === '' || $numero === '') {
            return false;
        }
        return $this->comLocksDocumento($parteId, $tipo, $numero,
            function () use ($id, $parteId, $tipo, $numero): bool {
                $atual = $this->documentosRepository->findById($id);
                if ($atual === null || (string) ($atual['parte_id'] ?? '') !== $parteId) {
                    return false;
                }
                if (ParteDocumentoHelper::normalizarTipo((string) ($atual['tipo'] ?? '')) !== $tipo
                    || ParteDocumentoHelper::normalizarNumero($tipo, (string) ($atual['numero'] ?? '')) !== $numero) {
                    throw new RuntimeException('Documento alterado durante a exclusão; tente novamente.');
                }
                $ok = $this->documentosRepository->delete($id);
                if ($ok) {
                    $this->sincronizarCpfLegado($parteId);
                }

                return $ok;
            }
        );
    }

    /** Os CPFs antigo e documental são bloqueados antes da transação e revalidados sob lock da pessoa. */
    private function comLocksDocumento(string $parteId, string $tipo, string $numero, callable $operacao): mixed
    {
        if ($this->connection->inTransaction()) {
            throw new LogicException('A alteração de documento exige transação própria.');
        }
        for ($tentativa = 0; $tentativa < 3; $tentativa++) {
            $snapshot = $this->estadoCpfDocumento($parteId);
            $locks = [
                ['tipo' => $tipo, 'numero' => $numero],
                ['tipo' => 'CPF', 'numero' => $snapshot['legado']],
                ['tipo' => 'CPF', 'numero' => $snapshot['documento']],
            ];
            try {
                return ParteDocumentoLock::runMany($this->connection, $locks,
                    fn(): mixed => $this->transacaoDocumento($parteId, function () use ($parteId, $snapshot, $operacao): mixed {
                        if ($this->estadoCpfDocumento($parteId) !== $snapshot) {
                            throw new PessoaDocumentoEstadoAlterado('O CPF da pessoa foi alterado durante a operação.');
                        }
                        return $operacao();
                    })
                );
            } catch (PessoaDocumentoEstadoAlterado $error) {
                if (ParteDocumentoLock::isConnectionUnsafe($this->connection) || $this->connection->inTransaction() || $tentativa === 2) {
                    throw $error;
                }
            }
        }
        throw new RuntimeException('Não foi possível alterar o documento.');
    }

    /** @return array{legado:string, documento:string} */
    private function estadoCpfDocumento(string $parteId): array
    {
        $parte = $this->partesRepository->findById($parteId);
        if ($parte === null) {
            throw new InvalidArgumentException('Pessoa não encontrada.');
        }
        $documentos = $this->documentosRepository->findByParteId($parteId);
        return [
            'legado' => ParteDocumentoHelper::normalizarNumero('CPF', (string) ($parte['cpf'] ?? '')),
            'documento' => ParteDocumentoHelper::resolverCpfParaColunaLegada($documentos) ?? '',
        ];
    }

    /** Serializa alterações de documentos da mesma pessoa antes de ler ou gravar. */
    private function transacaoDocumento(string $parteId, callable $operacao): mixed
    {
        if ($this->connection->inTransaction()) {
            throw new LogicException('A alteração de documento exige transação própria.');
        }
        if (!$this->connection->beginTransaction()) {
            throw new RuntimeException('Não foi possível iniciar a alteração do documento.');
        }
        try {
            ParteDocumentoLock::requireTransactionalTables($this->connection);
            $sql = 'SELECT `id` FROM `partes` WHERE `id` = :id';
            if ($this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $sql .= ' FOR UPDATE';
            }
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([':id' => $parteId]);
            if ($stmt->fetchColumn() === false) {
                throw new InvalidArgumentException('Pessoa não encontrada.');
            }
            $resultado = $operacao();
            ParteDocumentoLock::commit($this->connection, 'alteração de documento');
            return $resultado;
        } catch (Throwable $e) {
            if ($this->connection->inTransaction()) {
                try {
                    if (!$this->connection->rollBack()) {
                        throw new RuntimeException('Rollback da alteração de documento retornou false.');
                    }
                } catch (Throwable $rollbackError) {
                    ParteDocumentoLock::markConnectionUnsafe($this->connection);
                    error_log('Falha ao reverter transação de documento; estado da conexão incerto.');
                    throw new DocumentoResultadoIncerto('nao_confirmada', $e);
                }
            }
            throw $e;
        }
    }

    public function addTelefone(string $parteId, array $row): string
    {
        $numero = trim((string) ($row['numero'] ?? ''));
        if ($numero === '') {
            throw new InvalidArgumentException('Número do telefone é obrigatório.');
        }
        return $this->telefonesRepository->insert([
            'parte_id' => $parteId,
            'tipo' => isset($row['tipo']) ? (trim((string) $row['tipo']) ?: null) : null,
            'numero' => $numero,
        ]);
    }

    public function updateTelefone(string $id, array $payload): bool
    {
        return $this->telefonesRepository->update($id, $payload);
    }

    public function deleteTelefone(string $id): bool
    {
        return $this->telefonesRepository->delete($id);
    }

    public function addVinculoInstituicao(string $parteId, array $row): string
    {
        $instituicaoId = trim((string) ($row['instituicao_id'] ?? ''));
        if ($instituicaoId === '') {
            throw new InvalidArgumentException('instituicao_id é obrigatório.');
        }
        $dataInclusao = trim((string) ($row['data_inclusao'] ?? ''));
        if ($dataInclusao === '') {
            $dataInclusao = date('Y-m-d');
        }
        $data = [
            'parte_id' => $parteId,
            'instituicao_id' => $instituicaoId,
            'data_inclusao' => $dataInclusao,
            'data_inicio' => isset($row['data_inicio']) && trim((string) $row['data_inicio']) !== '' ? trim((string) $row['data_inicio']) : $dataInclusao,
            'setor_id' => isset($row['setor_id']) ? (trim((string) $row['setor_id']) ?: null) : null,
            'profissao' => isset($row['profissao']) ? (trim((string) $row['profissao']) ?: null) : null,
            'cargo_funcao' => isset($row['cargo_funcao']) ? (trim((string) $row['cargo_funcao']) ?: null) : null,
            'data_fim' => isset($row['data_fim']) ? (trim((string) $row['data_fim']) ?: null) : null,
            'ativo' => array_key_exists('ativo', $row) ? (int) (bool) $row['ativo'] : 1,
            'observacoes' => isset($row['observacoes']) ? (trim((string) $row['observacoes']) ?: null) : null,
        ];
        return $this->operacaoProfissaoAtomica(function () use ($data, $parteId): string {
            if ($this->partesRepository->findByIdForUpdate($parteId) === null) {
                throw new InvalidArgumentException('Pessoa não encontrada.');
            }
            $novoId = $this->partesInstituicoesRepository->insert($data);
            $this->reconciliarProfissaoParteComVinculosInstituicao($parteId);
            return $novoId;
        });
    }

    public function updateVinculoInstituicao(string $id, array $payload): bool
    {
        return $this->operacaoProfissaoAtomica(function () use ($id, $payload): bool {
            $row = $this->partesInstituicoesRepository->findById($id);
            $parteId = trim((string) ($row['parte_id'] ?? ''));
            if ($parteId === '' || $this->partesRepository->findByIdForUpdate($parteId) === null) {
                return false;
            }
            $current = $this->partesInstituicoesRepository->findByIdForUpdate($id);
            if ($current === null || trim((string) ($current['parte_id'] ?? '')) !== $parteId) {
                return false;
            }
            $ok = $this->partesInstituicoesRepository->update($id, $payload);
            if ($ok) {
                $this->reconciliarProfissaoParteComVinculosInstituicao($parteId);
            }
            return $ok;
        });
    }

    public function deleteVinculoInstituicao(string $id): bool
    {
        return $this->operacaoProfissaoAtomica(function () use ($id): bool {
            $row = $this->partesInstituicoesRepository->findById($id);
            $parteId = $row !== null ? trim((string) ($row['parte_id'] ?? '')) : '';
            if ($parteId === '' || $this->partesRepository->findByIdForUpdate($parteId) === null) {
                return false;
            }
            $current = $this->partesInstituicoesRepository->findByIdForUpdate($id);
            if ($current === null || trim((string) ($current['parte_id'] ?? '')) !== $parteId) {
                return false;
            }
            $ok = $this->partesInstituicoesRepository->delete($id);
            if ($ok && $parteId !== '') {
                $this->reconciliarProfissaoParteComVinculosInstituicao($parteId);
            }
            return $ok;
        });
    }

    /**
     * Mantém `partes.profissao` alinhada com os vínculos ativos em `partes_instituicoes`:
     * - se `partes.profissao` estiver vazia e algum vínculo tiver profissão, copia a do vínculo mais recente para `partes`;
     * - define um valor canônico (prioridade: `partes.profissao` quando preenchida; senão a primeira profissão não vazia nos vínculos, por data mais recente);
     * - atualiza cada vínculo ativo cuja profissão canônica seja diferente ou vazia.
     */
    private function reconciliarProfissaoParteComVinculosInstituicao(string $parteId): void
    {
        $parteId = trim($parteId);
        if ($parteId === '') {
            return;
        }
        $parte = $this->partesRepository->findByIdForUpdate($parteId);
        if ($parte === null) {
            return;
        }
        $vinculos = $this->partesInstituicoesRepository->findByParteIdForUpdate($parteId, true);
        if ($vinculos === []) {
            return;
        }

        $canonPartes = $this->canonProfissaoParaComparacao($parte['profissao'] ?? null);
        $primeiraDosVinculos = '';
        foreach ($vinculos as $v) {
            $c = $this->canonProfissaoParaComparacao($v['profissao'] ?? null);
            if ($c !== '') {
                $primeiraDosVinculos = $c;
                break;
            }
        }

        if ($canonPartes === '' && $primeiraDosVinculos === '') {
            return;
        }

        $valorAlvo = $canonPartes !== '' ? $canonPartes : $primeiraDosVinculos;

        $alterarPartes = $canonPartes === '' && $primeiraDosVinculos !== '';
        $alterarAlgumVinculo = false;
        foreach ($vinculos as $v) {
            $cv = $this->canonProfissaoParaComparacao($v['profissao'] ?? null);
            if ($cv !== $valorAlvo) {
                $alterarAlgumVinculo = true;
                break;
            }
        }

        if (!$alterarPartes && !$alterarAlgumVinculo) {
            return;
        }

        if ($alterarPartes) {
            $this->partesRepository->update($parteId, ['profissao' => $valorAlvo]);
        }
        if ($alterarAlgumVinculo) {
            foreach ($vinculos as $v) {
                $vid = trim((string) ($v['id'] ?? ''));
                if ($vid === '') {
                    continue;
                }
                $cv = $this->canonProfissaoParaComparacao($v['profissao'] ?? null);
                if ($cv !== $valorAlvo) {
                    $this->partesInstituicoesRepository->update($vid, ['profissao' => $valorAlvo]);
                }
            }
        }
    }

    /** Envolve a alteração e a reconciliação; nunca confirma uma transação externa. */
    private function operacaoProfissaoAtomica(callable $operation): mixed
    {
        $external = $this->connection->inTransaction();
        $savepoint = 'pessoa_profissao_' . bin2hex(random_bytes(8));
        if ($external) {
            $this->connection->exec('SAVEPOINT ' . $savepoint);
        } elseif (!$this->connection->beginTransaction()) {
            throw new RuntimeException('Não foi possível iniciar a operação de vínculo.');
        }
        try {
            $result = $operation();
            if ($external) {
                $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
            } elseif (!$this->connection->commit()) {
                throw new RuntimeException('Não foi possível confirmar a operação de vínculo.');
            }
            return $result;
        } catch (Throwable $error) {
            try {
                if ($external) {
                    $this->connection->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                    $this->connection->exec('RELEASE SAVEPOINT ' . $savepoint);
                } elseif ($this->connection->inTransaction()) {
                    $this->connection->rollBack();
                }
            } catch (Throwable $rollbackError) {
                if ($external && $this->connection->inTransaction()) {
                    try {
                        $this->connection->rollBack();
                    } catch (Throwable $abortError) {
                        throw new RuntimeException('Falha ao reverter operação; estado da transação incerto.', 0, $error);
                    }
                    throw new RuntimeException('Falha no savepoint; transação externa abortada.', 0, $error);
                }
                throw new RuntimeException('Falha ao reverter operação; estado da transação incerto.', 0, $error);
            }
            throw $error;
        }
    }

    /**
     * @return list<array{tipo: string, numero: string}>
     */
    private function normalizarDocumentosPayload(array $payload): array
    {
        $out = [];
        $vistosTipo = [];
        $lista = $payload['documentos'] ?? [];
        if (!is_array($lista)) {
            return $out;
        }
        foreach ($lista as $item) {
            if (!is_array($item)) {
                continue;
            }
            $tipo = ParteDocumentoHelper::normalizarTipo((string) ($item['tipo'] ?? ''));
            $numero = ParteDocumentoHelper::normalizarNumero($tipo, (string) ($item['numero'] ?? ''));
            if ($tipo === '' || $numero === '') {
                continue;
            }
            if (!ParteDocumentoHelper::isTipoValido($tipo)) {
                throw new InvalidArgumentException('Tipo de documento inválido: ' . $tipo);
            }
            if (isset($vistosTipo[$tipo])) {
                throw new InvalidArgumentException('Informe apenas um documento por tipo (' . $tipo . ').');
            }
            ParteDocumentoHelper::validarNumero($tipo, $numero);
            $vistosTipo[$tipo] = true;
            $out[] = ['tipo' => $tipo, 'numero' => $numero];
        }

        return $out;
    }

    /**
     * @param list<array{tipo: string, numero: string}> $documentos
     */
    private function persistirDocumentos(string $parteId, array $documentos): void
    {
        foreach ($documentos as $doc) {
            $this->documentosRepository->insert([
                'parte_id' => $parteId,
                'tipo' => $doc['tipo'],
                'numero' => $doc['numero'],
            ]);
        }
        $this->sincronizarCpfLegado($parteId);
    }

    private function sincronizarCpfLegado(string $parteId): void
    {
        $documentos = $this->documentosRepository->findByParteId($parteId);
        $cpf = ParteDocumentoHelper::resolverCpfParaColunaLegada($documentos);
        $this->partesRepository->setCpfLegado($parteId, $cpf);
    }

    /**
     * Serializa números de documento globalmente no MySQL, inclusive quando duas
     * requisições tentam vinculá-los a partes diferentes. O lock envolve o commit
     * da transação que grava o documento, evitando depender de índice ausente no
     * checkout. SQLite segue sem lock advisory para os testes unitários locais.
     *
     * @param list<array{tipo: string, numero: string}> $documentos
     */
    private function withDocumentNumberLocks(array $documentos, callable $operation): mixed
    {
        if ($this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql' || $documentos === []) {
            return $operation();
        }

        $lockNames = [];
        foreach ($documentos as $documento) {
            $tipo = ParteDocumentoHelper::normalizarTipo((string) ($documento['tipo'] ?? ''));
            $numero = ParteDocumentoHelper::normalizarNumero($tipo, (string) ($documento['numero'] ?? ''));
            if ($tipo === '' || $numero === '') {
                continue;
            }
            $lockNames[] = 'pd_' . substr(hash('sha256', $tipo . "\0" . $numero), 0, 60);
        }
        $lockNames = array_values(array_unique($lockNames));
        sort($lockNames, SORT_STRING);
        if ($lockNames === []) {
            return $operation();
        }

        $acquired = [];
        try {
            $acquire = $this->connection->prepare('SELECT GET_LOCK(:lock_name, 10)');
            foreach ($lockNames as $lockName) {
                $acquire->execute([':lock_name' => $lockName]);
                if ((int) $acquire->fetchColumn() !== 1) {
                    throw new RuntimeException('Não foi possível reservar o documento para gravação. Tente novamente.');
                }
                $acquired[] = $lockName;
            }

            return $operation();
        } finally {
            if ($acquired !== []) {
                $release = $this->connection->prepare('SELECT RELEASE_LOCK(:lock_name)');
                foreach (array_reverse($acquired) as $lockName) {
                    try {
                        $release->execute([':lock_name' => $lockName]);
                        if ((int) $release->fetchColumn() !== 1) {
                            error_log('liberar bloqueio de documento: lock não pertencia à conexão atual');
                        }
                    } catch (Throwable $releaseError) {
                        error_log('liberar bloqueio de documento: ' . $releaseError->getMessage());
                    }
                }
            }
        }
    }

    private function canonProfissaoParaComparacao(?string $valor): string
    {
        $t = trim((string) $valor);
        if ($t === '') {
            return '';
        }

        return TextFormatter::formatProperName($t, 120);
    }

    /**
     * Resolve `profissao` a partir de `profissao_selecao` / `profissao_outra` ou texto livre legado (`profissao`).
     */
    private function resolverProfissaoParaPayload(array $payload, ?string $profissaoAnteriorParte): ?string
    {
        if (array_key_exists('profissao_selecao', $payload) || array_key_exists('profissao_outra', $payload)) {
            return $this->resolverProfissaoPayload($payload, $profissaoAnteriorParte);
        }
        if (array_key_exists('profissao', $payload)) {
            $lista = $this->partesRepository->listProfissoesDistintas();
            $raw = trim((string) $payload['profissao']);
            if ($raw === '') {
                return null;
            }
            if (in_array($raw, $lista, true)) {
                return TextFormatter::formatProperName($raw, 120);
            }
            $ign = $profissaoAnteriorParte !== null && trim((string) $profissaoAnteriorParte) !== ''
                ? trim((string) $profissaoAnteriorParte)
                : null;
            ProfissaoValidator::validarNovaProfissao($raw, $lista, $ign);

            return TextFormatter::formatProperName($raw, 120);
        }

        return null;
    }

    private function resolverProfissaoPayload(array $payload, ?string $profissaoAnteriorParte): ?string
    {
        $lista = $this->partesRepository->listProfissoesDistintas();
        $prev = trim((string) ($profissaoAnteriorParte ?? ''));

        $sel = trim((string) ($payload['profissao_selecao'] ?? ''));
        $outra = trim((string) ($payload['profissao_outra'] ?? ''));

        if ($sel === '') {
            return null;
        }
        if ($sel === ProfissaoValidator::SELECT_OUTRA) {
            ProfissaoValidator::validarNovaProfissao($outra, $lista, $prev !== '' ? $prev : null);

            return TextFormatter::formatProperName($outra, 120);
        }
        if (!in_array($sel, $lista, true)) {
            if ($prev !== '' && TextFormatter::formatProperName($sel, 120) === TextFormatter::formatProperName($prev, 120)) {
                return TextFormatter::formatProperName($prev, 120);
            }
            throw new InvalidArgumentException('Profissão inválida. Atualize a página e tente novamente.');
        }

        return TextFormatter::formatProperName($sel, 120);
    }
}
