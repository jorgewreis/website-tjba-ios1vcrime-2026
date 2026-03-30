<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/Helper.php';

class ProcessoManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Busca um processo pelo número
     */
    public function buscarProcesso(string $numero): ?array
    {
        $sql = "SELECT id_proc, numero, classe, data_cadastro FROM processos WHERE numero = ? LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$numero]);
        
        $processo = $stmt->fetch(PDO::FETCH_ASSOC);
        return $processo ?: null;
    }

    /**
     * Cadastra um novo processo
     */
    public function cadastrarProcesso(string $numero, string $classe): bool
    {
        $existe = $this->buscarProcesso($numero);
        if ($existe) {
            if ($existe['classe'] !== $classe) {
                return $this->atualizarClasseProcesso($numero, $classe);
            }
            return true;
        }

        $novoId = Helper::gerarId();
        $agora = (new DateTime('now', new DateTimeZone('America/Bahia')))->format('Y-m-d H:i:s');

        $sql = "INSERT INTO processos (id_proc, numero, classe, data_cadastro) VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([$novoId, $numero, $classe, $agora]);
    }

    /**
     * Cadastra prioridades de um processo
     */
    public function cadastrarPrioridades(string $numeroProcesso, array $prioridades): bool
    {
        try {
            $this->pdo->beginTransaction();

            $sqlDelete = "DELETE FROM processoPrioridades WHERE numero = ?";
            $stmtDelete = $this->pdo->prepare($sqlDelete);
            $stmtDelete->execute([$numeroProcesso]);

            if (!empty($prioridades)) {
                $sql = "INSERT INTO processoPrioridades (id_proc_prioridade, numero, prioridade) VALUES (?, ?, ?)";
                $stmt = $this->pdo->prepare($sql);

                foreach ($prioridades as $prioridade) {
                    $idPrioridade = Helper::gerarId();
                    $stmt->execute([$idPrioridade, $numeroProcesso, $prioridade]);
                }
            }

            $this->pdo->commit();
            return true;

        } catch (Throwable $e) {
            $this->pdo->rollBack();
            error_log('Erro ao cadastrar prioridades: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Lista prioridades cadastradas de um processo
     */
    public function listarPrioridades(string $numeroProcesso): array
    {
        $prioridades = [];

        $sql = "SELECT prioridade FROM processoPrioridades WHERE numero = ? ORDER BY prioridade";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$numeroProcesso]);
        
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            if (!empty($row['prioridade'])) {
                $prioridades[] = $row['prioridade'];
            }
        }

        return $prioridades;
    }

    /**
     * Insere uma prioridade para o número do processo se ainda não existir (não substitui as demais).
     */
    public function adicionarPrioridadeSeNaoExistir(string $numeroProcesso, string $prioridade): bool
    {
        $numeroProcesso = trim($numeroProcesso);
        $prioridade = trim($prioridade);
        if ($numeroProcesso === '' || $prioridade === '') {
            return false;
        }

        $sqlCheck = 'SELECT 1 FROM processoPrioridades WHERE numero = ? AND prioridade = ? LIMIT 1';
        $st = $this->pdo->prepare($sqlCheck);
        $st->execute([$numeroProcesso, $prioridade]);
        if ($st->fetchColumn() !== false) {
            return true;
        }

        try {
            $idPrioridade = Helper::gerarId();
            $sql = 'INSERT INTO processoPrioridades (id_proc_prioridade, numero, prioridade) VALUES (?, ?, ?)';
            $ins = $this->pdo->prepare($sql);

            return $ins->execute([$idPrioridade, $numeroProcesso, $prioridade]);
        } catch (Throwable $e) {
            error_log('ProcessoManager::adicionarPrioridadeSeNaoExistir: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Remove apenas o registro dessa prioridade para o número do processo.
     */
    public function removerPrioridade(string $numeroProcesso, string $prioridade): bool
    {
        $numeroProcesso = trim($numeroProcesso);
        $prioridade = trim($prioridade);
        if ($numeroProcesso === '' || $prioridade === '') {
            return false;
        }

        try {
            $sql = 'DELETE FROM processoPrioridades WHERE numero = ? AND prioridade = ?';
            $st = $this->pdo->prepare($sql);

            return $st->execute([$numeroProcesso, $prioridade]);
        } catch (Throwable $e) {
            error_log('ProcessoManager::removerPrioridade: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Atualiza a classe de um processo
     */
    private function atualizarClasseProcesso(string $numero, string $classe): bool
    {
        $sql = "UPDATE processos SET classe = ? WHERE numero = ?";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([$classe, $numero]);
    }
}
