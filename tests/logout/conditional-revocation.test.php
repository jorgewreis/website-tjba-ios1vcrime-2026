<?php
declare(strict_types=1);

// Copia o modelo para um diretório temporário com conexão SQLite sintética.
$root = sys_get_temp_dir() . '/logout-' . bin2hex(random_bytes(12));
mkdir($root . '/app/models', 0700, true);
mkdir($root . '/app/helpers', 0700, true);
mkdir($root . '/config', 0700, true);
try {
    copy(__DIR__ . '/../../app/models/Usuario.php', $root . '/app/models/Usuario.php');
    file_put_contents($root . '/config/security.php', '<?php final class Security { public static function hashAccessToken(string $token): string { return hash("sha256", $token); } }');
    file_put_contents($root . '/app/helpers/Conection.php', <<<'PHP'
<?php
final class Conection {
    private static ?self $instance = null;
    private PDO $pdo;
    private function __construct() {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE usuarios (id_usuario TEXT PRIMARY KEY, token_acesso TEXT, token_expira TEXT)');
    }
    public static function getInstance(): self { return self::$instance ??= new self(); }
    public function getConnection(): PDO { return $this->pdo; }
}
PHP);
    require $root . '/app/models/Usuario.php';
    $db = Conection::getInstance()->getConnection();
    $insert = $db->prepare('INSERT INTO usuarios VALUES (?, ?, ?)');
    $insert->execute(['U1', hash('sha256', 'old'), 'tomorrow']);
    $user = new Usuario();
    // O login novo substitui o token depois da autenticação do logout antigo.
    $db->prepare('UPDATE usuarios SET token_acesso = ? WHERE id_usuario = ?')->execute([hash('sha256', 'new'), 'U1']);
    $user->logoutLocal('U1', 'old');
    if ($db->query("SELECT token_acesso FROM usuarios WHERE id_usuario = 'U1'")->fetchColumn() !== hash('sha256', 'new')) {
        throw new RuntimeException('Logout antigo revogou a sessão nova.');
    }
    $user->logoutLocal('U1', 'new');
    $row = $db->query("SELECT token_acesso, token_expira FROM usuarios WHERE id_usuario = 'U1'")->fetch(PDO::FETCH_ASSOC);
    if ($row['token_acesso'] !== null || $row['token_expira'] !== null) throw new RuntimeException('Logout normal não revogou token.');
    $user->logoutLocal('U1', 'old');
    if ($user->logoutLocal('U1', '')['success'] !== false) throw new RuntimeException('Token vazio foi aceito.');
    echo "PASS revogação condicional de logout (SQLite em memória)\n";
} finally {
    foreach (['app/models/Usuario.php', 'app/helpers/Conection.php', 'config/security.php'] as $file) {
        if (is_file($root . '/' . $file)) unlink($root . '/' . $file);
    }
    foreach (['app/models', 'app/helpers', 'app', 'config'] as $dir) rmdir($root . '/' . $dir);
    rmdir($root);
}
