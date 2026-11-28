<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/rei351-frame-' . bin2hex(random_bytes(8));
$copy = [
    'public/frame.php' => file_get_contents($root . '/public/frame.php'),
    'config/config.php' => '<?php function envValue(string $key, mixed $default = null): mixed { return getenv($key) === false ? $default : getenv($key); }',
    'config/security.php' => '<?php class Security { static function requireAuthentication(): void {} }',
    'app/helpers/bootstrap.php' => '<?php',
    'app/helpers/UserAccessHelper.php' => file_get_contents($root . '/app/helpers/UserAccessHelper.php'),
    'app/helpers/UsuarioPerfisAutorizacao.php' => '<?php class UsuarioPerfisAutorizacao { static function perfisDoUsuario(string $id): array { return $_SESSION["profiles"] ?? []; } }',
    'app/models/Usuario.php' => '<?php class Usuario { function validarToken(string $token): array { return ["id_usuario" => "synthetic"]; } }',
    'app/helpers/Conection.php' => '<?php class Conection { static function getInstance(): self { return new self; } function getConnection(): mixed { return null; } }',
    'app/access/AccessEvaluator.php' => file_get_contents($root . '/app/access/AccessEvaluator.php'),
    'app/access/AccessPolicyVersion.php' => file_get_contents($root . '/app/access/AccessPolicyVersion.php'),
    'app/access/PageAccessRegistry.php' => file_get_contents($root . '/app/access/PageAccessRegistry.php'),
    'app/access/AccessAreas.php' => file_get_contents($root . '/app/access/AccessAreas.php'),
    'app/access/ProfileNormalizer.php' => file_get_contents($root . '/app/access/ProfileNormalizer.php'),
    'app/views/internal/dashboard-2.php' => '<?php echo "SYNTHETIC-DASHBOARD2";',
    'app/views/internal/errors/403.php' => '<?php echo "DENIED";',
    'app/views/internal/errors/404.php' => '<?php echo "NOT-FOUND";',
];

function runRoute(string $tmp, array $profiles, ?string $flag): string
{
    $runner = 'putenv(' . var_export('DASHBOARD2_ENABLED=' . ($flag ?? ''), true) . '); '
        . '$_GET=["page"=>"dashboard2"]; $_SESSION=["token"=>"synthetic","profiles"=>' . var_export($profiles, true) . ']; '
        . 'http_response_code(200); register_shutdown_function(static function(){echo "|HTTP:" . http_response_code();}); '
        . 'require ' . var_export($tmp . '/public/frame.php', true) . ';';
    $process = proc_open([PHP_BINARY, '-r', $runner], [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Subprocesso PHP indisponível.');
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0) throw new RuntimeException('Falha no fixture: ' . $err);
    return $out;
}

try {
    foreach ($copy as $relative => $contents) {
        $target = $tmp . '/' . $relative;
        if (!is_dir(dirname($target))) mkdir(dirname($target), 0700, true);
        file_put_contents($target, $contents);
    }
    if (runRoute($tmp, ['SERVIDOR-CARTORIO'], null) !== 'NOT-FOUND|HTTP:404') throw new RuntimeException('A flag fechada deveria ocultar dashboard2.');
    if (runRoute($tmp, ['SERVIDOR-CARTORIO'], '1') !== 'SYNTHETIC-DASHBOARD2|HTTP:200') throw new RuntimeException('Perfil interno autorizado não acessou a página.');
    if (runRoute($tmp, ['ESTUDANTE'], '1') !== 'DENIED|HTTP:403') throw new RuntimeException('Perfil externo não deveria acessar dashboard2.');
} finally {
    if (is_dir($tmp)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($tmp);
    }
}
echo "PASS rota Dashboard 2: flag, interno permitido e externo negado\n";
