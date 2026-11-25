<?php
declare(strict_types=1);

if (getenv('REI88_TEST_DISPOSABLE') !== '1') {
    throw new RuntimeException('Recusado: defina REI88_TEST_DISPOSABLE=1 somente em MySQL descartável.');
}
$repoRoot = getenv('REI88_TEST_REPO_ROOT') ?: dirname(__DIR__, 2);
if (!is_dir($repoRoot . '/app') || is_file($repoRoot . '/.env')) {
    throw new RuntimeException('Recusado: raiz de código inválida ou arquivo .env presente.');
}

$dsn = getenv('REI88_MYSQL_DSN') ?: 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei88_test;charset=utf8mb4';
if (!str_contains($dsn, 'unix_socket=/var/run/mysqld/mysqld.sock')
    || preg_match('/(?:^|;)host\s*=/i', $dsn) === 1) {
    throw new RuntimeException('Recusado: fixture requer socket local, sem rede TCP.');
}
$expectedMarker = getenv('REI88_TEST_DISPOSABLE_MARKER') ?: '';
if (preg_match('/^[a-f0-9]{64}$/', $expectedMarker) !== 1) {
    throw new RuntimeException('Recusado: marcador efêmero de laboratório ausente ou inválido.');
}
$user = getenv('REI88_MYSQL_USER') ?: 'root';
$password = getenv('REI88_MYSQL_PASSWORD') ?: '';
$pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
$versionComment = (string) $pdo->query('SELECT @@version_comment')->fetchColumn();
if ($dbName !== 'rei88_test' || !str_contains($versionComment, 'MySQL') || !str_starts_with($version, '8.4.')) {
    throw new RuntimeException('Recusado: use somente MySQL 8.4 no banco sintético rei88_test.');
}
$tables = $pdo->query("SELECT table_name, engine FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name")->fetchAll(PDO::FETCH_ASSOC);
$tables = array_map(static fn (array $row): array => array_change_key_case($row, CASE_LOWER), $tables);
if (count($tables) !== 1
    || strtolower((string) ($tables[0]['table_name'] ?? '')) !== 'rei88_test_guard'
    || strtolower((string) ($tables[0]['engine'] ?? '')) !== 'innodb') {
    throw new RuntimeException('Recusado: banco deve conter somente a tabela sentinela InnoDB criada pelo runner efêmero.');
}
$marker = $pdo->query('SELECT marker FROM rei88_test_guard LIMIT 1')->fetchColumn();
if (!is_string($marker) || !hash_equals($expectedMarker, $marker)) {
    throw new RuntimeException('Recusado: sentinela do runner não corresponde à execução corrente.');
}

$caseMode = (int)$pdo->query('SELECT @@lower_case_table_names')->fetchColumn();
$expectedMode = getenv('REI160_EXPECT_LOWER_CASE');
if (!in_array($expectedMode, ['0', '1'], true) || $caseMode !== (int)$expectedMode) { throw new RuntimeException('Modo lower_case_table_names inesperado.'); }
echo "PASS lower_case_table_names=$caseMode confirmado na instância descartável.\n";
// Schema intencionalmente sintético com colunas legadas e atuais. Não afirma que coexistem no banco real.
$fixtureTables = ['guiasusuarios','processos_partes','partes','processo_prioridades','guiasUsuarios','guia_subtarefas','guia','processos','classes','audit_log'];
register_shutdown_function(static function () use ($pdo, $fixtureTables, $expectedMarker): void {
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($fixtureTables as $table) { $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`'); }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $remaining = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $marker = $pdo->query('SELECT marker FROM rei88_test_guard LIMIT 1')->fetchColumn();
        if ($remaining !== ['rei88_test_guard'] || !is_string($marker) || !hash_equals($expectedMarker, $marker)) { throw new RuntimeException('Cleanup ou sentinela inválidos.'); }
        echo "CLEANUP schema removido; sentinela preservada.\n";
    } catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
});
$pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");
$ddl = [
    "CREATE TABLE processos (id VARCHAR(40) PRIMARY KEY DEFAULT '', id_proc VARCHAR(40), numero VARCHAR(32), classe VARCHAR(100), data_cadastro DATETIME, numero_processo VARCHAR(32) NOT NULL DEFAULT '' UNIQUE, classe_id VARCHAR(40), data_fato DATE, sigiloso TINYINT NOT NULL DEFAULT 0, created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE classes (id VARCHAR(40) PRIMARY KEY DEFAULT '', id_classe VARCHAR(40), cod VARCHAR(80), descricao VARCHAR(200), codigo VARCHAR(80), nome VARCHAR(200), created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE partes (id VARCHAR(40) PRIMARY KEY, cpf VARCHAR(30), nome_completo VARCHAR(255) NOT NULL, sexo VARCHAR(30), data_nascimento DATE, profissao VARCHAR(255), created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE processos_partes (id VARCHAR(40) PRIMARY KEY, processo_id VARCHAR(40) NOT NULL, parte_id VARCHAR(40) NOT NULL, tipo_parte VARCHAR(50) NOT NULL, polo VARCHAR(20) NOT NULL, prioridade_mulher TINYINT NOT NULL DEFAULT 0, prioridade_crianca_adolescente TINYINT NOT NULL DEFAULT 0, prioridade_idoso TINYINT NOT NULL DEFAULT 0, prioridade_pessoa_deficiencia TINYINT NOT NULL DEFAULT 0, created_at DATETIME, updated_at DATETIME, FOREIGN KEY (processo_id) REFERENCES processos(id), FOREIGN KEY (parte_id) REFERENCES partes(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE guia (id_guia VARCHAR(40) PRIMARY KEY, tipo VARCHAR(100), processo VARCHAR(32), parte_id VARCHAR(40), etapa VARCHAR(50), status VARCHAR(50), regime VARCHAR(100), data_fato DATE, dias_multa INT, observacoes TEXT, data_cadastro DATETIME, data_atualizacao DATETIME, user_cadastro VARCHAR(40)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE processo_prioridades (id VARCHAR(40) PRIMARY KEY, processo_id VARCHAR(40) NOT NULL, codigo VARCHAR(100) NOT NULL, origem VARCHAR(50) NOT NULL, regra_detalhe VARCHAR(255), created_at DATETIME) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE guiasUsuarios (id_alocacao VARCHAR(40) PRIMARY KEY, id_usuario VARCHAR(40), id_guia VARCHAR(40), data_cadastro DATETIME) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE guia_subtarefas (id_guia VARCHAR(40), codigo VARCHAR(100), titulo VARCHAR(200), status VARCHAR(40), created_at DATETIME, started_at DATETIME, completed_at DATETIME, checkbox_reu_provisoria INT, destino_guia VARCHAR(100), dados_extra TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE audit_log (id VARCHAR(40) PRIMARY KEY, user_id VARCHAR(40), action VARCHAR(100), context TEXT, created_at DATETIME) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
foreach ($ddl as $sql) { $pdo->exec($sql); }
// Compatibility bridge is a synthetic test fixture only: production coexistence
// of legacy and current columns or equivalent triggers has not been verified.
$pdo->exec("CREATE TRIGGER rei160_fixture_classe BEFORE INSERT ON classes FOR EACH ROW SET NEW.id=IF(NEW.id='',NEW.id_classe,NEW.id), NEW.codigo=COALESCE(NEW.codigo,NEW.cod), NEW.nome=COALESCE(NEW.nome,NEW.descricao)");
$pdo->exec("CREATE TRIGGER rei160_fixture_processo BEFORE INSERT ON processos FOR EACH ROW SET NEW.id=IF(NEW.id='',NEW.id_proc,NEW.id), NEW.numero_processo=IF(NEW.numero_processo='',NEW.numero,NEW.numero_processo)");
require_once $repoRoot . '/app/services/Guias.php';
$assert = static function (bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } };
$snapshot = static function () use ($pdo, $fixtureTables): array {
    $result = [];
    foreach ($fixtureTables as $table) {
        if ($table === 'guiasusuarios') { continue; }
        $rows = $pdo->query('SELECT * FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC);
        usort($rows, static fn(array $a, array $b): int => strcmp(json_encode($a), json_encode($b)));
        $result[$table] = $rows;
    }
    return $result;
};
$reset = static function () use ($pdo, $fixtureTables): void {
    $pdo->exec('DROP TRIGGER IF EXISTS rei160_fail_part');
    $pdo->exec('DROP TRIGGER IF EXISTS rei160_fail_link');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($fixtureTables as $table) { if ($table !== 'guiasusuarios') { $pdo->exec('TRUNCATE TABLE `' . $table . '`'); } }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    $pdo->exec("INSERT INTO processos (id,id_proc,numero,numero_processo,sigiloso) VALUES ('PRC-TEST','PRC-TEST','0000001-69.2020.8.05.0001','0000001-69.2020.8.05.0001',1)");
};
$number = '0000001-69.2020.8.05.0001';
foreach (['create', 'edit'] as $operation) {
    foreach (['part', 'link'] as $failure) {
        $reset();
        if ($operation === 'edit') { $pdo->exec("INSERT INTO guia (id_guia,tipo,processo,parte_id,etapa,status) VALUES ('GUIA-EXISTENTE','Guia anterior','anterior','PARTE-ANTIGA','Cadastro','Pendente')"); }
        $payload = ['tipo'=>'Guia de Execução', 'processo'=>$number, 'reu'=>'Pessoa Sintetica', 'prioridades'=>[], 'etapa'=>'Cadastro', 'status'=>'Pendente', 'user_cadastro'=>'TESTE', 'alocacao'=>'nao-alocar', 'subtarefas'=>[], 'regime'=>'', 'data_fato'=>null, 'dias_multa'=>'', 'observacoes'=>null, 'servidores'=>[]];
        if ($operation === 'edit') { $payload['id_guia'] = 'GUIA-EXISTENTE'; }
        $before = $snapshot();
        $table = $failure === 'part' ? 'partes' : 'processos_partes';
        $trigger = $failure === 'part' ? 'rei160_fail_part' : 'rei160_fail_link';
        $pdo->exec("CREATE TRIGGER $trigger BEFORE INSERT ON $table FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'REI160_FAIL'");
        $guias = new Guias($pdo);
        $result = $operation === 'create' ? $guias->cadastrarGuia($payload) : $guias->atualizarGuia($payload);
        $assert(($result['ok'] ?? null) === false && !str_contains((string)($result['message'] ?? ''), 'REI160_FAIL'), "$operation/$failure não recusou com erro genérico.");
        $assert($snapshot() === $before && !$pdo->inTransaction(), "$operation/$failure não reverteu snapshot integral.");
        $pdo->exec("DROP TRIGGER $trigger");
        $result = $operation === 'create' ? $guias->cadastrarGuia($payload) : $guias->atualizarGuia($payload);
        $assert(($result['ok'] ?? null) === true, "$operation/$failure retry falhou: " . json_encode($result));
        $assert((int)$pdo->query('SELECT COUNT(*) FROM partes')->fetchColumn() === 1 && (int)$pdo->query('SELECT COUNT(*) FROM processos_partes')->fetchColumn() === 1, "$operation/$failure retry não persistiu parte/vínculo.");
        $assert((int)$pdo->query("SELECT sigiloso FROM processos WHERE id='PRC-TEST'")->fetchColumn() === 1, "$operation/$failure alterou sigilo.");
        $assert(!$pdo->inTransaction(), "$operation/$failure deixou transação aberta.");
        echo "PASS $operation falha $failure, rollback, retry e sigilo preservado\n";
    }
}
$newNumber = '0000004-24.2020.8.05.0001';
foreach (['create','edit'] as $operation) {
    foreach (['part','link'] as $failure) {
        $reset();
        if ($operation === 'edit') $pdo->exec("INSERT INTO guia (id_guia,tipo,processo,parte_id,etapa,status) VALUES ('GUIA-EXISTENTE','Guia anterior','anterior','PARTE-ANTIGA','Cadastro','Pendente')");
        $payload=['id_guia'=>'GUIA-EXISTENTE','tipo'=>'Guia de Execução','processo'=>$newNumber,'classe_cod'=>'REI160-CLASSE','classe_desc'=>'Classe Sintética','reu'=>'Pessoa Nova Sintetica','prioridades'=>[],'etapa'=>'Cadastro','status'=>'Pendente','user_cadastro'=>'TESTE','alocacao'=>'nao-alocar','subtarefas'=>[],'regime'=>'','data_fato'=>null,'dias_multa'=>'','observacoes'=>null,'servidores'=>[]];
        $before=$snapshot();
        $table=$failure==='part' ? 'partes' : 'processos_partes';
        $trigger=$failure==='part' ? 'rei160_fail_part' : 'rei160_fail_link';
        $pdo->exec("CREATE TRIGGER $trigger BEFORE INSERT ON $table FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='REI160_FAIL'");
        $service=new Guias($pdo);
        $result=$operation==='create' ? $service->cadastrarGuia($payload) : $service->atualizarGuia($payload);
        $assert(($result['ok']??null)===false && $snapshot()===$before && !$pdo->inTransaction(), "$operation/$failure não reverteu classe e processo novos.");
        $pdo->exec("DROP TRIGGER $trigger");
        $result=$operation==='create' ? $service->cadastrarGuia($payload) : $service->atualizarGuia($payload);
        $assert(($result['ok']??null)===true, "$operation/$failure com classe e processo novos falhou: ".json_encode($result));
        $class=$pdo->query("SELECT id,id_classe,cod,descricao,codigo,nome FROM classes WHERE cod='REI160-CLASSE'")->fetch(PDO::FETCH_ASSOC);
        $process=$pdo->query("SELECT id,id_proc,numero,numero_processo,classe FROM processos WHERE numero='0000004-24.2020.8.05.0001'")->fetch(PDO::FETCH_ASSOC);
        $assert(is_array($class) && $class['id']===$class['id_classe'] && $class['codigo']==='REI160-CLASSE' && $class['nome']==='Classe Sintética', 'Classe legada não persistiu no schema sintético.');
        $assert(is_array($process) && $process['id']===$process['id_proc'] && $process['numero_processo']===$newNumber && $process['classe']==='REI160-CLASSE', 'Processo legado novo não persistiu no schema sintético.');
        $link=$pdo->prepare('SELECT COUNT(*) FROM processos_partes pp JOIN partes p ON p.id=pp.parte_id WHERE pp.processo_id=? AND p.nome_completo=?');
        $link->execute([$process['id'],'Pessoa Nova Sintetica']);
        $assert((int)$link->fetchColumn()===1 && !$pdo->inTransaction(), "$operation/$failure não persistiu vínculo esperado.");
        echo "PASS $operation classe e processo novos, falha $failure e rollback integral\n";
    }
}
$reset();
$guias = new Guias($pdo);
$payload = ['tipo'=>'Guia de Execução','processo'=>'','reu'=>'','prioridades'=>[],'etapa'=>'Cadastro','status'=>'Pendente','user_cadastro'=>'TESTE','alocacao'=>'nao-alocar','subtarefas'=>[], 'regime'=>'', 'data_fato'=>null,'dias_multa'=>'','observacoes'=>null,'servidores'=>[]];
$result = $guias->cadastrarGuia($payload);
$assert(($result['ok'] ?? null) === true, 'Create sem parte não aceito.');
$pdo->exec("INSERT INTO guia (id_guia,tipo,processo,etapa,status) VALUES ('GUIA-EXISTENTE','Guia anterior','','Cadastro','Pendente')");
$payload['id_guia'] = 'GUIA-EXISTENTE';
$result = $guias->atualizarGuia($payload);
$assert(($result['ok'] ?? null) === true && (int)$pdo->query('SELECT COUNT(*) FROM partes')->fetchColumn() === 0, 'Edit sem parte não aceito.');
$assert((new ReuManager($pdo))->cadastrarReu('') === null && (new ReuManager($pdo))->vincularReuProcesso('', '') === false, 'Ausência legítima não preservada.');
echo "PASS create/edit sem parte opcional\n";
// O MySQL não converte engine em tabelas participantes de FK; a matriz usa cópia sintética sem essas FKs.
$pdo->exec('ALTER TABLE processos_partes DROP FOREIGN KEY processos_partes_ibfk_1, DROP FOREIGN KEY processos_partes_ibfk_2');
// Preflight deve negar cada engine não transacional antes de qualquer escrita.
$engineTables = ['classes','processos','partes','processos_partes','processo_prioridades','guia','guiasUsuarios','guia_subtarefas'];
foreach ($engineTables as $table) {
    foreach (['create','edit'] as $operation) {
        $reset();
        if ($operation === 'edit') { $pdo->exec("INSERT INTO guia (id_guia,tipo,processo,etapa,status) VALUES ('GUIA-EXISTENTE','Anterior','0000001-69.2020.8.05.0001','Cadastro','Pendente')"); }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->exec("ALTER TABLE `$table` ENGINE=MyISAM");
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $before = $snapshot();
        $payload = ['id_guia'=>'GUIA-EXISTENTE','tipo'=>'Guia de Execução','processo'=>$number,'reu'=>'Pessoa Sintetica','prioridades'=>[],'etapa'=>'Cadastro','status'=>'Pendente','user_cadastro'=>'TESTE','alocacao'=>'nao-alocar','subtarefas'=>[],'regime'=>'','data_fato'=>null,'dias_multa'=>'','observacoes'=>null,'servidores'=>[]];
        $guias = new Guias($pdo);
        $result = $operation === 'create' ? $guias->cadastrarGuia($payload) : $guias->atualizarGuia($payload);
        $assert(($result['ok'] ?? null) === false && $snapshot() === $before && !$pdo->inTransaction(), "$operation aceitou ou deixou escrita com $table MyISAM.");
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->exec("ALTER TABLE `$table` ENGINE=InnoDB");
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        echo "PASS $operation recusa $table MyISAM sem escrita\n";
    }
}
// Uma transação que pertence ao chamador não pode ser encerrada nem revertida pelo serviço.
foreach (['create','edit'] as $operation) {
    $reset();
    $pdo->beginTransaction();
    $pdo->exec("INSERT INTO partes (id,nome_completo) VALUES ('PRT-EXTERNA','Anterior externa')");
    $payload = ['id_guia'=>'GUIA-EXISTENTE','tipo'=>'Guia de Execução','processo'=>$number,'reu'=>'Pessoa Sintetica','prioridades'=>[],'etapa'=>'Cadastro','status'=>'Pendente','user_cadastro'=>'TESTE','alocacao'=>'nao-alocar','subtarefas'=>[],'regime'=>'','data_fato'=>null,'dias_multa'=>'','observacoes'=>null,'servidores'=>[]];
    $before = $snapshot();
    $guias = new Guias($pdo);
    $result = $operation === 'create' ? $guias->cadastrarGuia($payload) : $guias->atualizarGuia($payload);
    $assert(($result['ok'] ?? null) === false && $pdo->inTransaction() && $snapshot() === $before, "$operation afetou transação externa.");
    $pdo->rollBack();
    $assert((int)$pdo->query("SELECT COUNT(*) FROM partes WHERE id='PRT-EXTERNA'")->fetchColumn() === 0, 'Rollback do chamador falhou.');
    echo "PASS $operation preservou transação externa\n";
}
// Em Linux nomes são case-sensitive: tabela homônima não pode ditar engine da tabela usada.
if ($caseMode === 0) {
$pdo->exec('CREATE TABLE guiasusuarios (id_alocacao VARCHAR(40) PRIMARY KEY) ENGINE=MyISAM');
$reset();
$payload = ['tipo'=>'Guia de Execução','processo'=>'','reu'=>'','prioridades'=>[],'etapa'=>'Cadastro','status'=>'Pendente','user_cadastro'=>'TESTE','alocacao'=>'nao-alocar','subtarefas'=>[]];
$result = (new Guias($pdo))->cadastrarGuia($payload);
$assert(($result['ok'] ?? null) === true, 'Homônima MyISAM impediu tabela exata InnoDB.');
$reset();
$pdo->exec('ALTER TABLE guiasusuarios ENGINE=InnoDB');
$pdo->exec('ALTER TABLE guiasUsuarios ENGINE=MyISAM');
$before = $snapshot();
$result = (new Guias($pdo))->cadastrarGuia($payload);
$assert(($result['ok'] ?? null) === false && $snapshot() === $before && !$pdo->inTransaction(), 'Homônima InnoDB mascarou tabela exata MyISAM.');
$pdo->exec('ALTER TABLE guiasUsuarios ENGINE=InnoDB');
$pdo->exec('DROP TABLE guiasusuarios');
echo "PASS guard distinguindo guiasUsuarios e guiasusuarios com engines opostas\n";
} else { echo "SKIP tabelas homônimas em modo 1 (MySQL as considera idênticas).\n"; }
