<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/Helper.php';

class ReuManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Busca um réu pelo nome
     */
    public function buscarReu(string $nome): ?array
    {
        $sql = "SELECT id_reu, nome, cpf, statusPrisao FROM reus WHERE nome = ? LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$nome]);
        
        $reu = $stmt->fetch(PDO::FETCH_ASSOC);
        return $reu ?: null;
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
                $stmtUpdate = $this->pdo->prepare($sqlUpdate);
                $stmtUpdate->execute([$cpf, $existe['id_reu']]);
            }
            return $existe['id_reu'];
        }

        $novoId = Helper::gerarId();
        $sql = "INSERT INTO reus (id_reu, nome, cpf) VALUES (?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        
        $cpfValue = $cpf ?? '';
        $sucesso = $stmt->execute([$novoId, $nome, $cpfValue]);

        return $sucesso ? $novoId : null;
    }

    /**
     * Lista réus vinculados a um processo (numero no formato CNJ, ex.: 1234567-89.1234.1.12.3456).
     *
     * @return array<int, array{id_reu: string, nome: string}>
     */
    public function listarReusPorProcesso(string $numeroProcesso): array
    {
        $numero = trim($numeroProcesso);
        if ($numero === '') {
            return [];
        }
        $sql = "SELECT r.id_reu, r.nome FROM processoReus pr INNER JOIN reus r ON r.id_reu = pr.id_reu WHERE pr.numero = ? ORDER BY r.nome ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$numero]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /**
     * Vincula um réu a um processo
     */
    public function vincularReuProcesso(string $idReu, string $numeroProcesso): bool
    {
        $sql = "SELECT 1 FROM processoReus WHERE id_reu = ? AND numero = ? LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$idReu, $numeroProcesso]);
        
        if ($stmt->fetch()) {
            return true;
        }

        $novoId = Helper::gerarId();
        $agora = (new DateTime('now', new DateTimeZone('America/Bahia')))->format('Y-m-d H:i:s');
        $sql = "INSERT INTO processoReus (id_proc_reus, numero, id_reu, data_cadastro) VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([$novoId, $numeroProcesso, $idReu, $agora]);
    }
}
