<?php
declare(strict_types=1);

// Este teste substitui dependências e cria tabelas. Nunca executá-lo no checkout.
if (realpath(dirname(__DIR__, 2)) !== '/workspace' || !is_file('/.dockerenv')) {
    throw new RuntimeException('Este teste só pode executar na cópia descartável do container.');
}

// Only the disposable runner may supply this exact DSN and random marker.
$dsn = (string) getenv('MYSQL_TEST_DSN');
$marker = (string) getenv('MYSQL_TEST_MARKER');
if ($dsn !== 'mysql:host=127.0.0.1;port=3306;dbname=auth_test;charset=utf8mb4' || !preg_match('/^[a-f0-9]{64}$/D', $marker)) {
    throw new RuntimeException('Ambiente descartável não identificado.');
}
$db = new PDO($dsn, (string) getenv('MYSQL_TEST_USER'), (string) getenv('MYSQL_TEST_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$proof = $db->query('SELECT token FROM `_sandbox_proof`')->fetchAll(PDO::FETCH_COLUMN);
$tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
sort($tables);
if (count($proof) !== 1 || !hash_equals($marker, (string) $proof[0]) || $tables !== ['_sandbox_proof']) {
    throw new RuntimeException('Banco descartável não comprovado ou não está vazio.');
}

$db->exec("CREATE TABLE usuarios (id_usuario VARCHAR(64) PRIMARY KEY, nome VARCHAR(100), sobrenome VARCHAR(100), sexo VARCHAR(20), data_nascimento VARCHAR(20), cargo VARCHAR(100), instituicao VARCHAR(150), cpf VARCHAR(20), telefone VARCHAR(30), email VARCHAR(255), senha_hash VARCHAR(255), meta VARCHAR(50), ultimo_acesso DATETIME NULL, token_acesso VARCHAR(255) NULL, token_expira DATETIME NULL, cadastro_ativo INT, created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB");
$db->exec('CREATE TABLE perfis (id_perfil VARCHAR(64) PRIMARY KEY, descricao VARCHAR(100)) ENGINE=InnoDB');
$db->exec('CREATE TABLE usuario_perfil (id_usu_perfil VARCHAR(64) PRIMARY KEY, usuario VARCHAR(64), perfil VARCHAR(64)) ENGINE=InnoDB');

// Replace dependencies only inside the runner's disposable PHP container.
$root = dirname(__DIR__, 2);
file_put_contents($root . '/config/config.php', '<?php');
file_put_contents($root . '/config/security.php', <<<'PHP'
<?php class Security {
 static function normalizeCPF($v){return preg_replace('/\D/','',$v);} static function validarCPF($v){return true;}
 static function formatCPF($v){return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/','$1.$2.$3-$4',$v);}
 static function validatePasswordPolicy($v){return ['valid'=>true];}
 static function hashPassword($v){if(getenv('CPF_FIRST')==='1') usleep(2000000); return 'hash-'.$v;}
 static function requireAuthentication(){} static function getUserId(){return 'synthetic-admin';}
 static function verifyCSRFToken($v){return true;} static function generateCSRFToken(){return 'synthetic';}
}
PHP);
file_put_contents($root . '/app/helpers/Conection.php', <<<'PHP'
<?php class InstrumentedStatement extends PDOStatement { protected function __construct() {} public function execute(?array $params=null): bool {
 if (str_contains($this->queryString,'GET_LOCK')) file_put_contents('/tmp/cpf-lock-attempt-'.getenv('CPF_ROLE'),'1');
 return parent::execute($params);
}}
class Conection { static function getInstance(){return new self();} function getConnection(){
 return new PDO(getenv('MYSQL_TEST_DSN'),getenv('MYSQL_TEST_USER'),getenv('MYSQL_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_STATEMENT_CLASS=>[InstrumentedStatement::class,[]]]); }}
PHP);
file_put_contents($root . '/app/helpers/TextFormatter.php', '<?php class TextFormatter { static function formatProperName($v,$n){return $v;} }');
file_put_contents($root . '/app/helpers/SexoHelper.php', '<?php class SexoHelper { static function normalize($v){return $v;} static function isValid($v){return true;} }');
file_put_contents($root . '/app/helpers/DateHelper.php', '<?php class DateHelper { static function normalizeIsoDate($v){return null;} }');
file_put_contents($root . '/app/helpers/SetoresAccessHelper.php', '<?php class SetoresAccessHelper {}');
file_put_contents($root . '/app/helpers/UsuarioPerfisAutorizacao.php', "<?php class UsuarioPerfisAutorizacao { static function perfisDoUsuario(\$v){return ['ADMIN'];} }");
file_put_contents($root . '/app/views/internal/_internal_head_styles.php', '<?php');
file_put_contents($root . '/app/views/internal/_toast_script.php', '<?php');
$worker = $root . '/tests/api-security/cpf-worker.runtime.php';
file_put_contents($worker, <<<'PHP'
<?php
$_SERVER['REQUEST_METHOD']='POST'; $_SESSION=[];
if ($argv[1] === 'admin') {
 $_POST=['csrf_token'=>'x','nome'=>'Admin','sobrenome'=>'Sintetico','cpf'=>'529.982.247-25','email'=>'admin@example.invalid','sexo'=>'M','senha'=>'admin','senha_confirm'=>'admin','tipo'=>'interno','cargo'=>'Admin'];
 require __DIR__.'/../../app/views/internal/usuarios-create.php';
} else {
 $_POST=['nome_completo'=>'Estudante Sintetico','cpf'=>'52998224725','telefone'=>'71999999999','sexo'=>'M','faculdade'=>'Teste','email'=>'student@example.invalid','aceite_termos_lgpd'=>true,'senha'=>'student'];
 require __DIR__.'/../../public/api/cadastro_estudantes.php';
}
PHP);

$lock = 'cadest:' . substr(hash('sha256', '52998224725'), 0, 48);
foreach (['admin', 'student'] as $first) {
    $db->exec('DELETE FROM usuario_perfil');
    $db->exec('DELETE FROM usuarios');
    foreach (['admin','student'] as $role) { $path = '/tmp/cpf-lock-attempt-' . $role; if (is_file($path)) unlink($path); }
    $spawn = static function (string $role, bool $hold) use ($worker): array {
        $env = array_merge($_ENV, ['MYSQL_TEST_DSN'=>getenv('MYSQL_TEST_DSN'), 'MYSQL_TEST_USER'=>getenv('MYSQL_TEST_USER'), 'MYSQL_TEST_PASSWORD'=>getenv('MYSQL_TEST_PASSWORD'), 'CPF_FIRST'=>$hold ? '1' : '0', 'CPF_ROLE'=>$role]);
        $proc = proc_open([PHP_BINARY, $worker, $role], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, null, $env);
        if (!is_resource($proc)) throw new RuntimeException('Falha ao iniciar processo sintético.');
        fclose($pipes[0]); return [$proc,$pipes];
    };
    [$a,$pa] = $spawn($first, true);
    $query = $db->prepare('SELECT IS_USED_LOCK(?)');
    $held = false;
    for ($i=0; $i<100; $i++) { $query->execute([$lock]); if ($query->fetchColumn() !== null) { $held=true; break; } usleep(10000); }
    if (!$held) throw new RuntimeException('Primeiro fluxo não obteve o lock.');
    $second = $first === 'admin' ? 'student' : 'admin';
    [$b,$pb] = $spawn($second, false);
    $attempted = false;
    for ($i=0; $i<100; $i++) { if (is_file('/tmp/cpf-lock-attempt-' . $second)) { $attempted=true; break; } usleep(10000); }
    $query->execute([$lock]);
    if (!$attempted || $query->fetchColumn() === null) throw new RuntimeException('Concorrência não ocorreu enquanto o primeiro mantinha o lock.');
    foreach ([[$a,$pa],[$b,$pb]] as [$proc,$pipes]) {
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($proc) !== 0 || str_contains($err, 'Fatal error')) throw new RuntimeException('Fluxo sintético falhou: ' . $err);
    }
    $rows = $db->query('SELECT nome, senha_hash, cadastro_ativo FROM usuarios')->fetchAll(PDO::FETCH_ASSOC);
    $profiles = $db->query('SELECT perfil FROM usuario_perfil')->fetchAll(PDO::FETCH_COLUMN);
    $expected = $first === 'admin' ? ['Admin','hash-admin','0','ADMIN'] : ['Estudante','hash-student','1','ESTUDANTE'];
    if (count($rows) !== 1 || count($profiles) !== 1 || [$rows[0]['nome'],$rows[0]['senha_hash'],(string)$rows[0]['cadastro_ativo'],$profiles[0]] !== $expected) {
        throw new RuntimeException('Concorrência alterou dados ou duplicou CPF para primeiro fluxo ' . $first);
    }
}
echo "PASS concorrência MySQL entre cadastros canônicos em ambas as ordens (identidade simulada)\n";
