<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
$frame = file_get_contents($root . '/public/frame.php');
preg_match_all("~'([^']+)'\\s*=>\\s*\\\$(PAGES|ERRORS)\\s*\\.\\s*'([^']+)'~", $frame, $routes, PREG_SET_ORDER);
if (count($routes) < 40) throw new RuntimeException('Mapa do frame não conferido.');
foreach ($routes as [, $key, $group, $file]) {
    $path = $root . '/app/views/internal' . ($group === 'ERRORS' ? '/errors' : '') . $file;
    if (!is_file($path)) throw new RuntimeException('View não encontrada para ' . $key);
}
$tmp = sys_get_temp_dir() . '/rei338-' . bin2hex(random_bytes(8));
$files = [
    'public/frame.php' => $frame,
    'config/config.php' => '<?php',
    'config/security.php' => '<?php class Security { static function requireAuthentication():void {} }',
    'app/helpers/bootstrap.php' => '<?php',
    'app/helpers/UserAccessHelper.php' => '<?php class UserAccessHelper { static function isUsuarioExterno(array $profiles): bool { return false; } }',
    'app/helpers/UsuarioPerfisAutorizacao.php' => '<?php class UsuarioPerfisAutorizacao { static function perfisDoUsuario(string $id): array { return ["ADMIN"]; } }',
    'app/models/Usuario.php' => '<?php class Usuario { function validarToken(string $token): array { return ["id_usuario"=>"synthetic"]; } }',
    'app/helpers/Conection.php' => '<?php class Conection { static function getInstance(): self { return new self; } function getConnection(): mixed { return null; } }',
    'app/access/AccessEvaluator.php' => '<?php class AccessEvaluator { static function canAccessPage(string $page, array $profiles):bool { return $profiles === ["ADMIN"]; } }',
    'app/access/AccessPolicyVersion.php' => '<?php',
    'app/views/internal/skills.php' => '<?php echo "SYNTHETIC-VALID";',
];
try {
    foreach ($files as $path => $content) {
        $dir = dirname($tmp . '/' . $path);
        if (!is_dir($dir)) mkdir($dir, 0700, true);
        file_put_contents($tmp . '/' . $path, $content);
    }
    foreach (['notificacoes', 'configuracoes', 'documentos', 'skills'] as $page) {
        $runner = '$_GET=["page"=>' . var_export($page, true) . ']; $_SESSION=["token"=>"synthetic"]; http_response_code(200); '
            . 'register_shutdown_function(function(){ echo "|HTTP:" . http_response_code(); }); require ' . var_export($tmp . '/public/frame.php', true) . ';';
        $process = proc_open([PHP_BINARY, '-r', $runner], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Fixture indisponível.');
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0 || $err !== '' || !str_contains($out, $page === 'skills' ? 'SYNTHETIC-VALID|HTTP:200' : '|HTTP:404')) {
            throw new RuntimeException('Status inesperado para ' . $page);
        }
    }
} finally {
    if (is_dir($tmp)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($tmp);
    }
}
echo "PASS rotas existentes e 404 de rotas obsoletas, sessão sintética\n";
