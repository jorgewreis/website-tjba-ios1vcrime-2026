<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/TextFormatter.php';
require_once __DIR__ . '/../helpers/ProfissaoValidator.php';
require_once __DIR__ . '/../repositories/PartesRepository.php';
require_once __DIR__ . '/../repositories/PartesTelefonesRepository.php';
require_once __DIR__ . '/../repositories/PartesEnderecosRepository.php';
require_once __DIR__ . '/../repositories/PartesInstituicoesRepository.php';
require_once __DIR__ . '/../repositories/ProcessosPartesRepository.php';

final class PessoasService
{
    private PDO $connection;
    private PartesRepository $partesRepository;
    private PartesTelefonesRepository $telefonesRepository;
    private PartesEnderecosRepository $enderecosRepository;
    private PartesInstituicoesRepository $partesInstituicoesRepository;
    private ProcessosPartesRepository $processosPartesRepository;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->partesRepository = new PartesRepository($connection);
        $this->telefonesRepository = new PartesTelefonesRepository($connection);
        $this->enderecosRepository = new PartesEnderecosRepository($connection);
        $this->partesInstituicoesRepository = new PartesInstituicoesRepository($connection);
        $this->processosPartesRepository = new ProcessosPartesRepository($connection);
    }

    public function list(array $filters, int $limit, int $offset): array
    {
        $rows = $this->partesRepository->fetchList($filters, $limit, $offset);
        foreach ($rows as $i => $row) {
            $parteId = (string) ($row['id'] ?? '');
            $rows[$i]['total_processos'] = $parteId !== '' ? count($this->processosPartesRepository->findByParteId($parteId)) : 0;
            $vinculosInst = $parteId !== '' ? $this->partesInstituicoesRepository->findByParteId($parteId, true) : [];
            $rows[$i]['total_instituicoes'] = count($vinculosInst);
            $rows[$i]['telefones'] = $parteId !== '' ? $this->telefonesRepository->findByParteId($parteId) : [];
            $rows[$i]['instituicoes'] = array_map(function (array $v): array {
                return [
                    'nome' => $v['instituicao_nome'] ?? '',
                    'sigla' => $v['instituicao_sigla'] ?? '',
                    'cargo_funcao' => $v['cargo_funcao'] ?? '',
                ];
            }, $vinculosInst);
        }
        return [
            'rows' => $rows,
            'total' => $this->partesRepository->count($filters),
        ];
    }

    public function find(string $id): ?array
    {
        $parte = $this->partesRepository->findById($id);
        if ($parte === null) {
            return null;
        }
        $parte['telefones'] = $this->telefonesRepository->findByParteId($id);
        $parte['enderecos'] = $this->enderecosRepository->findByParteId($id);
        $parte['vinculos_instituicoes'] = $this->partesInstituicoesRepository->findByParteId($id, null);
        $parte['vinculos_processos'] = $this->processosPartesRepository->findByParteId($id);
        return $parte;
    }

    public function create(array $payload): string
    {
        $this->connection->beginTransaction();
        try {
            $id = $this->partesRepository->insert([
                'cpf' => $payload['cpf'] ?? '',
                'nome_completo' => $payload['nome_completo'] ?? '',
                'sexo' => $payload['sexo'] ?? '',
                'data_nascimento' => $payload['data_nascimento'] ?? '',
                'profissao' => $this->resolverProfissaoParaPayload($payload, null),
            ]);

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

            $enderecos = $payload['enderecos'] ?? [];
            if (is_array($enderecos)) {
                foreach ($enderecos as $end) {
                    $cep = trim((string) ($end['cep'] ?? ''));
                    $logradouro = trim((string) ($end['logradouro'] ?? ''));
                    $numero = trim((string) ($end['numero'] ?? ''));
                    $complemento = trim((string) ($end['complemento'] ?? ''));
                    $bairro = trim((string) ($end['bairro'] ?? ''));
                    $cidade = trim((string) ($end['cidade'] ?? ''));
                    $uf = trim((string) ($end['uf'] ?? ''));
                    if ($cep !== '' || $logradouro !== '' || $numero !== '' || $complemento !== '' || $bairro !== '' || $cidade !== '' || $uf !== '') {
                        $this->enderecosRepository->insert([
                            'parte_id' => $id,
                            'cep' => $cep ?: null,
                            'logradouro' => $logradouro ?: null,
                            'numero' => $numero ?: null,
                            'complemento' => $complemento ?: null,
                            'bairro' => $bairro ?: null,
                            'cidade' => $cidade ?: null,
                            'uf' => $uf ?: null,
                            'principal' => isset($end['principal']) ? (int) (bool) $end['principal'] : 0,
                        ]);
                    }
                }
            }

            $this->connection->commit();
            return $id;
        } catch (Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    public function update(string $id, array $payload): bool
    {
        $existente = $this->partesRepository->findById($id);
        if ($existente === null) {
            return false;
        }
        $allowed = ['cpf', 'nome_completo', 'sexo', 'data_nascimento'];
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

    public function addEndereco(string $parteId, array $row): string
    {
        $data = [
            'parte_id' => $parteId,
            'cep' => isset($row['cep']) ? (trim((string) $row['cep']) ?: null) : null,
            'logradouro' => isset($row['logradouro']) ? (trim((string) $row['logradouro']) ?: null) : null,
            'numero' => isset($row['numero']) ? (trim((string) $row['numero']) ?: null) : null,
            'complemento' => isset($row['complemento']) ? (trim((string) $row['complemento']) ?: null) : null,
            'bairro' => isset($row['bairro']) ? (trim((string) $row['bairro']) ?: null) : null,
            'cidade' => isset($row['cidade']) ? (trim((string) $row['cidade']) ?: null) : null,
            'uf' => isset($row['uf']) ? (trim((string) $row['uf']) ?: null) : null,
            'principal' => isset($row['principal']) ? (int) (bool) $row['principal'] : 0,
        ];
        if (array_key_exists('ativo', $row)) {
            $data['ativo'] = (int) (bool) $row['ativo'];
        }
        return $this->enderecosRepository->insert($data);
    }

    public function updateEndereco(string $id, array $payload): bool
    {
        return $this->enderecosRepository->update($id, $payload);
    }

    public function deleteEndereco(string $id): bool
    {
        return $this->enderecosRepository->delete($id);
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
        $novoId = $this->partesInstituicoesRepository->insert($data);
        $this->reconciliarProfissaoParteComVinculosInstituicao($parteId);

        return $novoId;
    }

    public function updateVinculoInstituicao(string $id, array $payload): bool
    {
        $ok = $this->partesInstituicoesRepository->update($id, $payload);
        if ($ok) {
            $row = $this->partesInstituicoesRepository->findById($id);
            $parteId = trim((string) ($row['parte_id'] ?? ''));
            if ($parteId !== '') {
                $this->reconciliarProfissaoParteComVinculosInstituicao($parteId);
            }
        }

        return $ok;
    }

    public function deleteVinculoInstituicao(string $id): bool
    {
        $row = $this->partesInstituicoesRepository->findById($id);
        $parteId = $row !== null ? trim((string) ($row['parte_id'] ?? '')) : '';
        $ok = $this->partesInstituicoesRepository->delete($id);
        if ($ok && $parteId !== '') {
            $this->reconciliarProfissaoParteComVinculosInstituicao($parteId);
        }

        return $ok;
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
        $parte = $this->partesRepository->findById($parteId);
        if ($parte === null) {
            return;
        }
        $vinculos = $this->partesInstituicoesRepository->findByParteId($parteId, true);
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

        $this->connection->beginTransaction();
        try {
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
            $this->connection->commit();
        } catch (Throwable $e) {
            $this->connection->rollBack();
            throw $e;
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
