<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/CnjValidator.php';
require_once __DIR__ . '/../DataJud/DataJudLocalSigiloException.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../repositories/AssuntosRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../repositories/ProcessoVaraRepository.php';
require_once __DIR__ . '/ProcessoClasseSyncService.php';
require_once __DIR__ . '/ProcessoAssuntoSyncService.php';

final class ProcessoOrgaoDataJudIndisponivelException extends RuntimeException {}

/** Aplica dados já obtidos e validados do DataJud; não faz chamadas externas. */
final class ProcessoDataJudSyncService
{
    private Closure $classeSync;
    private Closure $assuntoSync;

    public function __construct(private PDO $db, ?callable $classeSync = null, ?callable $assuntoSync = null, bool $onlyChangedFields = false)
    {
        $this->classeSync = Closure::fromCallable($classeSync ??
            fn(string $numero, string $codigo, string $descricao): array =>
                (new ProcessoClasseSyncService($this->db))->syncFromDataJud($numero, $codigo, $descricao, true, !$onlyChangedFields));
        $this->assuntoSync = Closure::fromCallable($assuntoSync ??
            fn(string $numero, array $assuntos, bool $allowVerifiedEmpty): array =>
                (new ProcessoAssuntoSyncService($this->db))->syncFromDataJud($numero, $assuntos, true, $allowVerifiedEmpty, $onlyChangedFields));
    }

    /**
     * null significa fonte ausente. Lista vazia é uma resposta completa e
     * explícita sem assuntos; reconcilia removendo somente vínculos DataJud e
     * preservando vínculos manuais ausentes da fonte. O chamador deve confirmar identidade e
     * completude da resposta de origem antes de chamar este serviço.
     *
     * @param bool $verifiedEmptySubjects True only after the caller verified an explicit empty DataJud source response.
     * @return array{classe:?array,assuntos:?array,orgao?:array}
     */
    public function sync(
        string $numero,
        ?array $classe,
        ?array $assuntos,
        bool $verifiedEmptySubjects = false,
        ?callable $beforeWrite = null,
        array $orgao = []
    ): array
    {
        $numero = CnjValidator::digits20OrThrow($numero);
        $processos = new ProcessosRepository($this->db);
        $processo = $processos->findByNumero($numero);
        if ($processo === null) {
            throw new InvalidArgumentException('Processo não encontrado no cadastro local.');
        }
        $codigo = '';
        $descricao = '';
        if ($classe !== null) {
            if (!isset($classe['codigo']) || !is_scalar($classe['codigo']) ||
                ClassesRepository::normalizeCodigo((string) $classe['codigo']) === '' ||
                (isset($classe['descricao']) && !is_string($classe['descricao']))) {
                throw new InvalidArgumentException('Classe DataJud inválida.');
            }
            $codigo = (string) $classe['codigo'];
            $descricao = (string) ($classe['descricao'] ?? '');
        }
        if ($assuntos !== null) {
            if (!array_is_list($assuntos)) throw new InvalidArgumentException('Assuntos DataJud inválidos.');
            if ($assuntos === [] && !$verifiedEmptySubjects) {
                throw new InvalidArgumentException('Lista vazia de assuntos exige confirmação explícita da origem.');
            }
            foreach ($assuntos as $item) {
                if (!is_array($item) || !isset($item['codigo']) || !is_scalar($item['codigo']) ||
                    AssuntosRepository::normalizeCodigo((string) $item['codigo']) === '' ||
                    (isset($item['descricao']) && !is_string($item['descricao']))) {
                    throw new InvalidArgumentException('Assunto DataJud inválido.');
                }
            }
        }
        if ($classe === null && $assuntos === null && $orgao === []) {
            throw new InvalidArgumentException('Nenhum dado DataJud sincronizável.');
        }
        if ($this->db->inTransaction()) throw new RuntimeException('Sincronização requer transação própria.');
        if (!$this->db->beginTransaction()) throw new RuntimeException('Não foi possível iniciar a sincronização.');
        try {
            // Revalida dentro da transação antes de qualquer escrita. Os serviços
            // de classe/assunto recebem também requireExisting=true.
            if ($processos->findByNumero($numero) === null) {
                throw new InvalidArgumentException('Processo não encontrado no cadastro local.');
            }
            if ($beforeWrite !== null && $beforeWrite($this->db) !== true) {
                throw new RuntimeException('A autorização para sincronizar o processo foi recusada.');
            }
            // Confira o sigilo do cadastro sob lock; pode ter mudado
            // enquanto a API pública respondia.
            $sql = 'SELECT numero_processo, sigiloso FROM processos WHERE id = :id';
            if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
            $row = $this->db->prepare($sql);
            $row->execute([':id' => (string) $processo['id']]);
            $local = $row->fetch(PDO::FETCH_ASSOC);
            $row->closeCursor();
            if ($local === false || CnjValidator::onlyDigits((string) $local['numero_processo']) !== $numero) {
                throw new InvalidArgumentException('O processo mudou antes da sincronização.');
            }
            if (!empty($local['sigiloso'])) throw new DataJudLocalSigiloException();

            $resultadoOrgao = null;
            if ($orgao !== []) {
                $repositorioVara = new ProcessoVaraRepository($this->db);
                if (!$repositorioVara->dataJudDisponivel()) {
                    throw new ProcessoOrgaoDataJudIndisponivelException();
                }
                $alterouOrgao = $repositorioVara->registrarDataJud((string) $processo['id'], $orgao);
                $resultadoOrgao = ['action'=>$alterouOrgao ? 'updated' : 'noop'];
            }
            $resultadoClasse = $classe === null ? null : ($this->classeSync)($numero, $codigo, $descricao);
            $resultadoAssuntos = $assuntos === null ? null : ($this->assuntoSync)($numero, $assuntos, $verifiedEmptySubjects);
            if (!$this->db->inTransaction()) throw new RuntimeException('Transação de sincronização interrompida.');
            if (!$this->db->commit()) throw new RuntimeException('Resultado da confirmação da sincronização é incerto; descarte a conexão.');
            return ['classe' => $resultadoClasse, 'assuntos' => $resultadoAssuntos]
                + ($resultadoOrgao === null ? [] : ['orgao' => $resultadoOrgao]);
        } catch (Throwable $erro) {
            if ($this->db->inTransaction()) {
                try { if (!$this->db->rollBack()) throw new RuntimeException('Rollback recusado.'); }
                catch (Throwable $rollback) {
                    throw new RuntimeException('Falha na sincronização e no rollback; descarte a conexão.', 0, $erro);
                }
            }
            throw $erro;
        }
    }
}
