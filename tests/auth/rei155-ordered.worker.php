<?php
declare(strict_types=1);
require __DIR__ . '/sandbox-guard.php';
require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/app/models/Usuario.php';
require dirname(__DIR__, 2) . '/app/services/PasswordResetService.php';

[$script, $mode, $user, $token, $barrier, $hold] = $argv;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
class Rei155OrderedStatement extends PDOStatement {
    protected function __construct(private string $barrier, private bool $hold) {}
    public function execute(?array $params = null): bool {
        $result = parent::execute($params);
        if ($result && $this->hold && preg_match('/SELECT\s+(?:senha_hash|id_usuario)\s+FROM\s+usuarios\s+WHERE\s+id_usuario\s*=\s*\?(?:\s+LIMIT\s+1)?\s+FOR\s+UPDATE/i', $this->queryString)) {
            file_put_contents($this->barrier . '.locked', 'locked');
            $deadline = microtime(true) + 25;
            while (!is_file($this->barrier . '.release')) {
                if (microtime(true) > $deadline) throw new RuntimeException('Timeout na barreira sintética.');
                usleep(10000);
            }
        }
        return $result;
    }
}
$pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET), DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [Rei155OrderedStatement::class, [$barrier, $hold === 'yes']]);
$connection = Conection::getInstance();
(new ReflectionProperty(Conection::class, 'pdo'))->setValue($connection, $pdo);
file_put_contents($barrier . '.' . $mode . '.connection', (string)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn());
$result = match ($mode) {
    'change' => (new Usuario())->alterarSenha($user, 'Ativo!12345', 'Troca!Senha1552026'),
    'reset' => (new PasswordResetService())->resetPassword($token, 'Reset!Senha1552026'),
    default => throw new RuntimeException('Modo inválido.'),
};
echo json_encode($result, JSON_THROW_ON_ERROR);
