<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/Helper.php';

class ClasseManager
{
    private mysqli $connection;

    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Lista todas as classes cadastradas
     */
    public function listarClasses(): array
    {
        $sql = "SELECT cod, descricao FROM classes ORDER BY descricao ASC";
        require_once __DIR__ . '/../helpers/BootstrapHelper.php';

        if (!$stmt) {
            return [];
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $classes = [];

        while ($row = $result->fetch_assoc()) {
            $classes[] = $row;
        }

        $stmt->close();
        return $classes;
    }

    /**
     * Busca uma classe específica pelo código
     */
    public function buscarClasse(string $codigo): ?array
    {
        $sql = "SELECT id_classe, cod, descricao FROM classes WHERE cod = ? LIMIT 1";
        $stmt = $this->connection->prepare($sql);

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("s", $codigo);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            return null;
        }

        $classe = $result->fetch_assoc();
        $stmt->close();
        return $classe;
    }

    /**
     * Cadastra uma nova classe se não existir
     */
    public function cadastrarClasse(string $codigo, string $descricao): bool
    {
        $existe = $this->buscarClasse($codigo);
        if ($existe) {
            return true;
        }

        $novoId = Helper::gerarId();
        $sql = "INSERT INTO classes (id_classe, cod, descricao) VALUES (?, ?, ?)";
        $stmt = $this->connection->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("sss", $novoId, $codigo, $descricao);
        $sucesso = $stmt->execute();
        $stmt->close();

        return $sucesso;
    }
}
