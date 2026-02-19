<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/Helper.php';

class ProcessoManager
{
    private mysqli $connection;

    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Busca um processo pelo número
     */
    public function buscarProcesso(string $numero): ?array
    {
        $sql = "SELECT id_proc, numero, classe, data_cadastro FROM processos WHERE numero = ? LIMIT 1";
        require_once __DIR__ . '/../helpers/BootstrapHelper.php';

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("s", $numero);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }

        $processo = $result->fetch_assoc();
        $stmt->close();
        return $processo;
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
        $stmt = $this->connection->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("ssss", $novoId, $numero, $classe, $agora);
        $sucesso = $stmt->execute();
        $stmt->close();

        return $sucesso;
    }

    /**
     * Cadastra prioridades de um processo
     */
    public function cadastrarPrioridades(string $numeroProcesso, array $prioridades): bool
    {
        try {
            $sqlDelete = "DELETE FROM processoPrioridades WHERE numero = ?";
            $stmtDelete = $this->connection->prepare($sqlDelete);
            if ($stmtDelete) {
                $stmtDelete->bind_param("s", $numeroProcesso);
                $stmtDelete->execute();
                $stmtDelete->close();
            } else {
                return false;
            }

            if (empty($prioridades)) {
                return true;
            }

            $sql = "INSERT INTO processoPrioridades (id_proc_prioridade, numero, prioridade) VALUES (?, ?, ?)";
            $stmt = $this->connection->prepare($sql);

            if (!$stmt) {
                return false;
            }

            foreach ($prioridades as $prioridade) {
                $idPrioridade = Helper::gerarId();
                $stmt->bind_param("sss", $idPrioridade, $numeroProcesso, $prioridade);
                $stmt->execute();
            }

            $stmt->close();
            return true;

        } catch (Throwable $e) {
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
        $stmt = $this->connection->prepare($sql);

        if (!$stmt) {
            return $prioridades;
        }

        $stmt->bind_param('s', $numeroProcesso);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if (!empty($row['prioridade'])) {
                    $prioridades[] = $row['prioridade'];
                }
            }
        }

        $stmt->close();

        return $prioridades;
    }

    /**
     * Atualiza a classe de um processo
     */
    private function atualizarClasseProcesso(string $numero, string $classe): bool
    {
        $sql = "UPDATE processos SET classe = ? WHERE numero = ?";
        $stmt = $this->connection->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("ss", $classe, $numero);
        $sucesso = $stmt->execute();
        $stmt->close();

        return $sucesso;
    }
}
