<?php
declare(strict_types=1);

$root = dirname(__DIR__);
if (PHP_SAPI !== 'cli' || is_file($root . '/.env')) {
    throw new RuntimeException('Use checkout isolado, sem .env.');
}
if (isset($argv[1])) {
    foreach (['APP_ENV'=>'fixture','DB_HOST'=>'127.0.0.1','DB_PORT'=>'1','DB_NAME'=>'fixture_sqlite','DB_USER'=>'fixture','DB_PASS'=>'fixture'] as $key=>$value) {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
    require_once $root . '/app/helpers/BootstrapHelper.php';
    require_once $root . '/config/security.php';
    $pdo = new class ('sqlite::memory:') extends PDO {
        public function prepare(string $query, array $options=[]): PDOStatement|false
        {
            $query = preg_replace('/\s+FOR UPDATE\s*$/i', '', $query);
            if (preg_match('/^\s*(UPDATE|DELETE)\b/i', $query)) {
                $query = preg_replace('/\s+LIMIT\s+1\s*$/i', '', $query);
            }
            return parent::prepare($query, $options);
        }
    };
    $pdo->sqliteCreateFunction('NOW', static fn (): string => '2026-11-26 12:00:00', 0);
    $pdo->exec(<<<'SQL'
CREATE TABLE usuarios (id_usuario TEXT, token_acesso TEXT, token_expira TEXT, cadastro_ativo INTEGER);
CREATE TABLE usuario_perfil (usuario TEXT, perfil TEXT);
CREATE TABLE partes (id TEXT PRIMARY KEY, cpf TEXT, nome_completo TEXT, sexo TEXT, data_nascimento TEXT, profissao TEXT, created_at TEXT, updated_at TEXT);
CREATE TABLE partes_telefones (id TEXT PRIMARY KEY, parte_id TEXT, tipo TEXT, numero TEXT, whatsapp INT, principal INT, ativo INT, created_at TEXT, updated_at TEXT);
CREATE TABLE partes_instituicoes (id TEXT, parte_id TEXT, instituicao_id TEXT, setor_id TEXT, profissao TEXT, cargo_funcao TEXT, data_inclusao TEXT, data_inicio TEXT, data_fim TEXT, ativo INT, observacoes TEXT, created_at TEXT, updated_at TEXT);
INSERT INTO partes (id,nome_completo) VALUES ('P','Pessoa Sintética');
INSERT INTO partes_telefones VALUES ('T','P',NULL,'1111',0,0,1,NULL,'2026-11-26 12:00:00');
SQL);
    $token = bin2hex(random_bytes(16));
    $pdo->prepare('INSERT INTO usuarios VALUES (?, ?, ?, 1)')->execute(['U',Security::hashAccessToken($token),'2099-01-01 00:00:00']);
    $pdo->prepare('INSERT INTO usuario_perfil VALUES (?,?)')->execute(['U',$argv[1]==='perfil'?'CONSULTOR':'ADMIN']);
    $reflect=new ReflectionClass(Conection::class);
    $connection=$reflect->newInstanceWithoutConstructor();
    $reflect->getProperty('pdo')->setValue($connection,$pdo);
    $reflect->getProperty('instance')->setValue(null,$connection);
    $_SESSION=$argv[1]==='anonimo'?[]:['id_usuario'=>'U','token'=>$token,'last_activity_at'=>time()];
    $_SERVER['REQUEST_METHOD']=$argv[1]==='metodo'?'GET':'POST';
    $_SERVER['HTTP_HOST']='fixture.invalid';
    $_SERVER['HTTP_ORIGIN']=$argv[1]==='origem'?'http://outra.invalid':'http://fixture.invalid';
    $_POST=match($argv[1]){
        'criacao'=>['parte_id'=>'P','numero'=>'(00) 5555-5555'],
        'sem_telefone','sem_pessoa'=>['id'=>'AUSENTE','numero'=>'2222'],
        'sem_excluir'=>['id'=>'AUSENTE'],
        'pessoa'=>['id'=>'P','nome_completo'=>'Pessoa Sintética'],
        default=>['id'=>'T','numero'=>$argv[1]==='sem_numero'?'--':($argv[1]==='repeticao'?'1111':'(00) 2211-0000')],
    };
    $endpoint=match($argv[1]){
        'criacao'=>'telefones/create.php',
        'excluir','sem_excluir'=>'telefones/delete.php',
        'pessoa','sem_pessoa'=>'update.php',
        default=>'telefones/update.php',
    };
    register_shutdown_function(static function () use ($pdo): void {
        $phones=$pdo->query('SELECT numero FROM partes_telefones ORDER BY numero')->fetchAll(PDO::FETCH_COLUMN);
        fwrite(STDERR,json_encode([http_response_code()?:200,$phones]));
    });
    require $root.'/public/api/pessoas/'.$endpoint;
    exit;
}
$cases=[
    'criacao'=>[201,['0055555555','1111']],
    'edicao'=>[200,['0022110000']],
    'repeticao'=>[200,['1111']],
    'excluir'=>[200,[]],
    'sem_telefone'=>[404,['1111']],
    'sem_excluir'=>[404,['1111']],
    'sem_numero'=>[400,['1111']],
    'sem_pessoa'=>[404,['1111']],
    'pessoa'=>[200,['1111']],
    'anonimo'=>[401,['1111']],
    'perfil'=>[403,['1111']],
    'origem'=>[403,['1111']],
    'metodo'=>[403,['1111']],
];
foreach($cases as $case=>$expected){
    $p=proc_open([PHP_BINARY,__FILE__,$case],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $body=stream_get_contents($pipes[1]);$state=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($p);
    if($code!==0||json_decode($state,true)!==$expected||!is_array(json_decode($body,true))){
        throw new RuntimeException('Fixture de endpoint falhou: '.$case.' '.$state.' '.$body);
    }
}
echo "PASS REI-320/331: endpoints reais, edições, ausência, repetição e controles de acesso (SQLite)\n";
