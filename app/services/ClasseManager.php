<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/HelperUtils.php';

class ClasseManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Lista todas as classes cadastradas
     */
    public function listarClasses(): array
    {
        $sql = "SELECT cod, descricao FROM classes ORDER BY descricao ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca uma classe específica pelo código
     */
    public function buscarClasse(string $codigo): ?array
    {
        $sql = "SELECT id_classe, cod, descricao FROM classes WHERE cod = ? LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$codigo]);
        
        $classe = $stmt->fetch(PDO::FETCH_ASSOC);
        return $classe ?: null;
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
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([$novoId, $codigo, $descricao]);
    }
}
