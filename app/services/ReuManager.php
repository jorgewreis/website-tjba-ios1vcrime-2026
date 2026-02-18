<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/Helper.php';

class ReuManager
{
    private mysqli $connection;

    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Busca um réu pelo nome
     */
    public function buscarReu(string $nome): ?array
    {
        $sql = "SELECT id_reu, nome, cpf, statusPrisao FROM reus WHERE nome = ? LIMIT 1";
        $stmt = $this->connection->prepare($sql);

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("s", $nome);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }

        $reu = $result->fetch_assoc();
        $stmt->close();
        return $reu;
    }

    /**
     * Cadastra um novo réu
     */
    public function cadastrarReu(string $nome, ?string $cpf = null): ?string
    {
        $existe = $this->buscarReu($nome);
        if ($existe) {
            if ($cpf !== null && $cpf !== '' && (empty($existe['cpf']) || $existe['cpf'] !== $cpf)) {
                $sqlUpdate = "UPDATE reus SET cpf = ? WHERE id_reu = ?";
                $stmtUpdate = $this->connection->prepare($sqlUpdate);
                if ($stmtUpdate) {
                    $stmtUpdate->bind_param("ss", $cpf, $existe['id_reu']);
                    $stmtUpdate->execute();
                    $stmtUpdate->close();
                }
            }
            return $existe['id_reu'];
        }

        $novoId = Helper::gerarId();
        $sql = "INSERT INTO reus (id_reu, nome, cpf) VALUES (?, ?, ?)";
        $stmt = $this->connection->prepare($sql);

        if (!$stmt) {
            return null;
        }

        $cpfValue = $cpf ?? '';
        $stmt->bind_param("sss", $novoId, $nome, $cpfValue);
        $sucesso = $stmt->execute();
        $stmt->close();

        return $sucesso ? $novoId : null;
    }

    /**
     * Vincula um réu a um processo
     */
    public function vincularReuProcesso(string $idReu, string $numeroProcesso): bool
    {
        $sql = "SELECT 1 FROM processoReus WHERE id_reu = ? AND numero = ? LIMIT 1";
        $stmt = $this->connection->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("ss", $idReu, $numeroProcesso);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $stmt->close();
            return true;
        }
        $stmt->close();

        $novoId = Helper::gerarId();
        $agora = (new DateTime('now', new DateTimeZone('America/Bahia')))->format('Y-m-d H:i:s');
        $sql = "INSERT INTO processoReus (id_proc_reus, numero, id_reu, data_cadastro) VALUES (?, ?, ?, ?)";
        $stmt = $this->connection->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("ssss", $novoId, $numeroProcesso, $idReu, $agora);
        $sucesso = $stmt->execute();
        $stmt->close();

        return $sucesso;
    }
}
