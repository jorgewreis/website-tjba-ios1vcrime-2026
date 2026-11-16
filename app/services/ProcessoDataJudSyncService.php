<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/CnjValidator.php';
require_once __DIR__ . '/../repositories/ClassesRepository.php';
require_once __DIR__ . '/../repositories/AssuntosRepository.php';
require_once __DIR__ . '/../repositories/ProcessosRepository.php';
require_once __DIR__ . '/ProcessoClasseSyncService.php';
require_once __DIR__ . '/ProcessoAssuntoSyncService.php';

/** Aplica dados já obtidos e validados do DataJud; não faz chamadas externas. */
final class ProcessoDataJudSyncService
{
    private Closure $classeSync;
    private Closure $assuntoSync;

    public function __construct(private PDO $db, ?callable $classeSync = null, ?callable $assuntoSync = null)
    {
        $this->classeSync = Closure::fromCallable($classeSync ??
            fn(string $numero, string $codigo, string $descricao): array =>
                (new ProcessoClasseSyncService($this->db))->syncFromDataJud($numero, $codigo, $descricao, true));
        $this->assuntoSync = Closure::fromCallable($assuntoSync ??
            fn(string $numero, array $assuntos): array =>
                (new ProcessoAssuntoSyncService($this->db))->syncFromDataJud($numero, $assuntos, true));
    }

    /**
     * null significa fonte ausente. Lista vazia de assuntos também preserva os
     * vínculos existentes, como os endpoints atuais. Nenhum dado inválido pode
     * ser normalizado silenciosamente em lista vazia antes da reconciliação.
     * O chamador deve confirmar identidade e completude da resposta de origem.
     *
     * @return array{classe:?array,assuntos:?array}
     */
    public function sync(string $numero, ?array $classe, ?array $assuntos): array
    {
        $numero = CnjValidator::digits20OrThrow($numero);
        $processos = new ProcessosRepository($this->db);
        if ($processos->findByNumero($numero) === null) {
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
            foreach ($assuntos as $item) {
                if (!is_array($item) || !isset($item['codigo']) || !is_scalar($item['codigo']) ||
                    AssuntosRepository::normalizeCodigo((string) $item['codigo']) === '' ||
                    (isset($item['descricao']) && !is_string($item['descricao']))) {
                    throw new InvalidArgumentException('Assunto DataJud inválido.');
                }
            }
        }
        if ($classe === null && ($assuntos === null || $assuntos === [])) {
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
            $resultadoClasse = $classe === null ? null : ($this->classeSync)($numero, $codigo, $descricao);
            $resultadoAssuntos = ($assuntos === null || $assuntos === []) ? null : ($this->assuntoSync)($numero, $assuntos);
            if (!$this->db->inTransaction()) throw new RuntimeException('Transação de sincronização interrompida.');
            if (!$this->db->commit()) throw new RuntimeException('Resultado da confirmação da sincronização é incerto; descarte a conexão.');
            return ['classe' => $resultadoClasse, 'assuntos' => $resultadoAssuntos];
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
