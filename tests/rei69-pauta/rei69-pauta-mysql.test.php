<?php
declare(strict_types=1);

$fixtureStage = 'setup';
set_exception_handler(static function (Throwable $exception) use (&$fixtureStage): void {
    $allowed = ['setup', 'setup_env', 'setup_connect', 'setup_database', 'setup_sentinel', 'setup_require', 'setup_mode', 'setup_ddl', 'setup_seed_classes', 'setup_seed_sectors', 'setup_seed_domain', 'setup_seed_links', 'setup_seed_part', 'setup_service', 'positive_update', 'positive_add', 'sector_denial', 'cross_link', 'defender', 'witness', 'pauta_transition_lock', 'pauta_allocation_lock', 'reu_principal_lock', 'pauta_atomic_derived', 'dashboard_strict_series', 'atas_csv'];
    $code = in_array($fixtureStage, $allowed, true) ? $fixtureStage : 'unknown';
    fwrite(STDERR, "REI69_FIXTURE_FAILURE code=$code\n");
    if ($code === 'atas_csv') {
        $mysqlCode = $exception instanceof PDOException ? (int) ($exception->errorInfo[1] ?? 0) : 0;
        fwrite(STDERR, 'REI69_FIXTURE_DETAIL area=atas_csv line=' . $exception->getLine() . ' mysql=' . $mysqlCode . "\n");
    }
    exit(1);
});
// Fixture CI-only: no application configuration and no TCP fallback.
$fixtureStage = 'setup_env';
$dsn = 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei69_pauta_test;charset=utf8mb4';
$marker = (string) getenv('REI69PAUTA_TEST_DISPOSABLE_MARKER');
if (getenv('REI69PAUTA_TEST_DISPOSABLE') !== '1' || getenv('REI69PAUTA_TEST_REPO_ROOT') !== '/repo'
    || getenv('REI69PAUTA_MYSQL_DSN') !== $dsn || !preg_match('/^[a-f0-9]{64}$/', $marker)) {
    throw new RuntimeException('Fixture requires the dedicated CI runner.');
}
$fixtureStage = 'setup_connect';
$pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$fixtureStage = 'setup_database';
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'rei69_pauta_test'
    || !str_starts_with((string) $pdo->query('SELECT VERSION()')->fetchColumn(), '8.4.')
    || (string) $pdo->query('SELECT @@lower_case_table_names')->fetchColumn() !== getenv('REI69PAUTA_EXPECT_LOWER_CASE')
    || $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) !== ['rei69_pauta_test_guard']) {
    throw new RuntimeException('Unexpected database or schema.');
}
$fixtureStage = 'setup_sentinel';
$guards = $pdo->query('SELECT marker FROM rei69_pauta_test_guard')->fetchAll(PDO::FETCH_COLUMN);
if (count($guards) !== 1 || !hash_equals($marker, (string) $guards[0])) throw new RuntimeException('Sentinel mismatch.');
// DDL below is reached only after the dedicated runner and sentinel checks.
$fixtureStage = 'setup_require';
require_once '/repo/app/services/SalaAudienciasPautaService.php';
$fixtureStage = 'setup_mode';
$pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
$pdo->exec("SET time_zone='+00:00'");
$ddl = [
    'classes' => 'id VARCHAR(40) PRIMARY KEY,nome VARCHAR(80)',
    'processos' => 'id VARCHAR(40) PRIMARY KEY,numero_processo VARCHAR(40),classe_id VARCHAR(40),data_fato DATE,sigiloso INT,created_at DATETIME,updated_at DATETIME,id_setor_responsavel VARCHAR(40)',
    'setores' => 'id_setor VARCHAR(40) PRIMARY KEY,ativo INT NOT NULL',
    'usuario_setor' => 'usuario VARCHAR(40) NOT NULL,setor VARCHAR(40) NOT NULL,PRIMARY KEY(usuario,setor)',
    'partes' => 'id VARCHAR(40) PRIMARY KEY,cpf VARCHAR(20),nome_completo VARCHAR(100),sexo VARCHAR(20),data_nascimento DATE,profissao VARCHAR(80),created_at DATETIME,updated_at DATETIME',
    'processos_partes' => 'id VARCHAR(40) PRIMARY KEY,processo_id VARCHAR(40),parte_id VARCHAR(40),tipo_parte VARCHAR(40),polo VARCHAR(40),prioridade_mulher INT DEFAULT 0,prioridade_crianca_adolescente INT DEFAULT 0,prioridade_idoso INT DEFAULT 0,prioridade_pessoa_deficiencia INT DEFAULT 0,created_at DATETIME,updated_at DATETIME',
    'sala_audiencias_pauta' => 'id_pauta VARCHAR(40) PRIMARY KEY,ativo INT,processo VARCHAR(40),numero_sequencial INT,ano INT,tipo_audiencia VARCHAR(80),data_audiencia DATE,hora_audiencia TIME,status VARCHAR(40),data_hora_designacao DATETIME,hora_inicio TIME,hora_fim TIME,duracao_estimada_minutos INT',
    'sala_audiencias_pauta_parte' => 'id_parte VARCHAR(40) PRIMARY KEY,pauta VARCHAR(40),parte_id_global VARCHAR(40),processo_parte_id VARCHAR(40),nome_parte VARCHAR(100),tipo_parte VARCHAR(40),tipo_defensor VARCHAR(40),defensor_publico_id VARCHAR(40),defensor_publico_outro VARCHAR(100),defensor_privado_id VARCHAR(40),defensor_privado_outro VARCHAR(100),maioridade VARCHAR(40),expedicao VARCHAR(40),cumprimento VARCHAR(40),id_documento_expedido VARCHAR(40),id_documento_cumprido VARCHAR(40),contatos TEXT,criado_por VARCHAR(40),atualizado_por VARCHAR(40),ativo INT,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP',
    'processo_prioridades' => 'id VARCHAR(40) PRIMARY KEY,processo_id VARCHAR(40),codigo VARCHAR(80),origem VARCHAR(80),regra_detalhe TEXT,created_at DATETIME',
    'gabinete_reu_preso' => 'id VARCHAR(40) PRIMARY KEY,status VARCHAR(40)',
    'gabinete_reu_preso_processo' => 'id VARCHAR(40) PRIMARY KEY,processo_id VARCHAR(40),reu_preso_id VARCHAR(40),situacao VARCHAR(40)',
];
$fixtureStage = 'setup_ddl';
foreach ($ddl as $name => $columns) $pdo->exec("CREATE TABLE `$name` ($columns) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$fixtureStage = 'setup_seed_classes';
$pdo->exec("INSERT INTO classes VALUES ('C','Classe sintetica')");
$fixtureStage = 'setup_seed_sectors';
$pdo->exec("INSERT INTO setores VALUES ('SA',1),('SB',1),('SI',0); INSERT INTO usuario_setor VALUES ('synthetic','SA'),('synthetic-other','SB'),('synthetic-inactive','SI')");
$fixtureStage = 'setup_seed_domain';
foreach (['A', 'B'] as $key) {
    $pdo->prepare("INSERT INTO processos VALUES (?,?,'C',NULL,0,NOW(),NOW(),?)")->execute([$key, 'CNJ-SINTETICO-' . $key, 'S' . $key]);
    $pdo->prepare("INSERT INTO partes VALUES (?,NULL,?,'X','2000-01-01','Sintetica',NOW(),NOW())")->execute([$key, 'Pessoa Sintetica ' . $key]);
    $pdo->prepare("INSERT INTO sala_audiencias_pauta (id_pauta,ativo,processo,numero_sequencial,ano,tipo_audiencia,data_audiencia,hora_audiencia,status) VALUES (?,1,?,1,2026,'instrucao','2030-01-01','10:00:00','designada')")->execute(['P' . $key, $key]);
}
$fixtureStage = 'setup_seed_links';
foreach (['AA'=>['A','A'], 'AB'=>['A','B'], 'BA'=>['B','A'], 'BB'=>['B','B']] as $id => [$process, $person]) {
    $pdo->prepare("INSERT INTO processos_partes (id,processo_id,parte_id,tipo_parte,polo,created_at,updated_at) VALUES (?,?,?,'vitima','ativo',NOW(),NOW())")->execute([$id, $process, $person]);
}
$fixtureStage = 'setup_seed_part';
$pdo->exec("INSERT INTO sala_audiencias_pauta_parte (id_parte,pauta,parte_id_global,processo_parte_id,nome_parte,tipo_parte,maioridade,expedicao,cumprimento,ativo) VALUES ('PP-A','PA','A','AA','Pessoa Sintetica A','vitima','maior','Não expedido','pendente',1)");
function snapshot69p(PDO $pdo, array $tables): array {
    $out=[]; foreach ($tables as $table) { $rows=$pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC); usort($rows, static fn($a,$b)=>strcmp(json_encode($a),json_encode($b))); $out[$table]=$rows; } return $out;
}
$fixtureStage = 'setup_service';
$service = new SalaAudienciasPautaService($pdo);
$tables = array_keys($ddl);
$payload = ['parte_id_global'=>'A','processo_parte_id'=>'AA','nome_parte'=>'Pessoa Sintetica A','tipo_parte'=>'vitima','maioridade'=>'maior','expedicao'=>'Não expedido','cumprimento'=>'pendente'];
$fixtureStage = 'positive_update';
$control = $service->updateParte('PP-A', $payload, 'synthetic');
$current = $pdo->query("SELECT processo_parte_id,parte_id_global FROM sala_audiencias_pauta_parte WHERE id_parte='PP-A'")->fetch(PDO::FETCH_ASSOC);
if (($control['ok'] ?? false) !== true || $current !== ['processo_parte_id'=>'AA','parte_id_global'=>'A'] || $pdo->inTransaction()) throw new RuntimeException('Positive update control failed.');
$fixtureStage = 'positive_add';
$controlAddId = $service->addParte('PA', $payload, 'synthetic');
$controlAddStmt = $pdo->prepare('SELECT pauta,processo_parte_id,parte_id_global FROM sala_audiencias_pauta_parte WHERE id_parte=? AND ativo=1');
$controlAddStmt->execute([$controlAddId]);
if ($controlAddStmt->fetch(PDO::FETCH_ASSOC) !== ['pauta'=>'PA','processo_parte_id'=>'AA','parte_id_global'=>'A'] || $pdo->inTransaction()) throw new RuntimeException('Positive add control failed.');
$fixtureStage = 'sector_denial';
$beforeDenied = snapshot69p($pdo, $tables);
try {
    $service->addParte('PA', $payload, 'synthetic-no-sector');
    throw new RuntimeException('Cross-sector link mutation was accepted.');
} catch (PautaVinculoSetorNegado $e) {
    if ($e->getMessage() !== 'Acesso setorial necessário para alterar vínculo do processo.') throw new RuntimeException('Unexpected authorization failure.', 0, $e);
}
if ($pdo->inTransaction() || snapshot69p($pdo, $tables) !== $beforeDenied) throw new RuntimeException('Denied sector request changed synthetic data.');
foreach ([['synthetic-other', 'SA'], ['synthetic', null], ['synthetic-inactive', 'SI']] as [$actor, $responsible]) {
    $st = $pdo->prepare("UPDATE processos SET id_setor_responsavel=? WHERE id='A'");
    $st->execute([$responsible]);
    $beforeDeniedUpdate = snapshot69p($pdo, $tables);
    try {
        $service->updateParte('PP-A', $payload, $actor);
        throw new RuntimeException('Unauthorized update was accepted.');
    } catch (PautaVinculoSetorNegado $e) {
        if ($e->getMessage() !== 'Acesso setorial necessário para alterar vínculo do processo.') throw new RuntimeException('Unexpected authorization failure.', 0, $e);
    }
    if ($pdo->inTransaction() || snapshot69p($pdo, $tables) !== $beforeDeniedUpdate) throw new RuntimeException('Denied update changed synthetic data.');
}
$pdo->exec("UPDATE processos SET id_setor_responsavel='SA' WHERE id='A'");
$fixtureStage = 'cross_link';
foreach (['AB', 'BA', 'BB'] as $badLink) {
    foreach (['add', 'update'] as $operation) {
        $case = array_replace($payload, ['processo_parte_id'=>$badLink, 'tipo_parte'=>'testemunha_defesa']);
        $before = snapshot69p($pdo, $tables);
        try {
            if ($operation === 'add') $service->addParte('PA', $case, 'synthetic');
            else $service->updateParte('PP-A', $case, 'synthetic');
            throw new RuntimeException('Cross-process or cross-person reference was accepted.');
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() !== 'Vínculo de processo e pessoa incompatível com a audiência.') throw new RuntimeException('Unexpected validation failure.', 0, $e);
        }
        if ($pdo->inTransaction() || snapshot69p($pdo, $tables) !== $before) throw new RuntimeException('Rejected request changed synthetic data or left a transaction.');
    }
}
$fixtureStage = 'defender';
$defenderCase = array_replace($payload, ['processo_parte_id'=>'BA', 'tipo_defensor'=>'publico', 'defensor_publico_id'=>'outro', 'defensor_publico_outro'=>'Defensor Sintetico Novo']);
$beforeDefender = snapshot69p($pdo, $tables);
try {
    $service->addParte('PA', $defenderCase, 'synthetic');
    throw new RuntimeException('Cross-process reference with defender was accepted.');
} catch (InvalidArgumentException $e) {
    if ($e->getMessage() !== 'Vínculo de processo e pessoa incompatível com a audiência.') throw new RuntimeException('Unexpected defender validation failure.', 0, $e);
}
if ($pdo->inTransaction() || snapshot69p($pdo, $tables) !== $beforeDefender) throw new RuntimeException('Rejected defender request changed synthetic data.');
$fixtureStage = 'witness';
$witness = array_replace($payload, ['parte_id_global'=>'', 'processo_parte_id'=>'', 'nome_parte'=>'Testemunha Sintetica Sem Cadastro', 'tipo_parte'=>'testemunha_defesa']);
$witnessId = $service->addParte('PA', $witness, 'synthetic-no-sector');
$witnessStmt = $pdo->prepare('SELECT parte_id_global,processo_parte_id FROM sala_audiencias_pauta_parte WHERE id_parte=?');
$witnessStmt->execute([$witnessId]);
$witnessRow = $witnessStmt->fetch(PDO::FETCH_ASSOC);
if (!$witnessRow || trim((string)$witnessRow['parte_id_global']) !== '' || trim((string)$witnessRow['processo_parte_id']) !== '') throw new RuntimeException('Witness without global identity was not preserved.');
$beforeWitness = snapshot69p($pdo, $tables);
$updatedWitness = $service->updateParte($witnessId, array_replace($witness, ['nome_parte'=>'Testemunha Sintetica Atualizada']), 'synthetic-no-sector');
$afterWitness = snapshot69p($pdo, $tables);
$witnessStmt->execute([$witnessId]);
$updatedWitnessRow = $witnessStmt->fetch(PDO::FETCH_ASSOC);
$witnessNameStmt = $pdo->prepare('SELECT nome_parte FROM sala_audiencias_pauta_parte WHERE id_parte=?');
$witnessNameStmt->execute([$witnessId]);
if (($updatedWitness['ok'] ?? false) !== true || $pdo->inTransaction() || !$updatedWitnessRow
    || trim((string)$updatedWitnessRow['parte_id_global']) !== '' || trim((string)$updatedWitnessRow['processo_parte_id']) !== ''
    || $witnessNameStmt->fetchColumn() !== 'Testemunha Sintetica Atualizada'
    || $beforeWitness['processos_partes'] !== $afterWitness['processos_partes'] || $beforeWitness['partes'] !== $afterWitness['partes']) {
    throw new RuntimeException('Pure witness update failed or changed process links or people.');
}
foreach (['classes','processos','setores','usuario_setor'] as $unchangedTable) {
    if ($beforeWitness[$unchangedTable] !== $afterWitness[$unchangedTable]) throw new RuntimeException('Pure witness update changed an unrelated table.');
}

// A second connection to the same sentinel-checked disposable schema.
$fixtureStage = 'pauta_transition_lock';
$peer = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$peer->exec('SET SESSION innodb_lock_wait_timeout=1');
$peerPauta = new SalaAudienciasPautaService($peer);
$pdo->exec('ALTER TABLE sala_audiencias_pauta ADD data_hora_inicio_efetivo DATETIME, ADD data_hora_fim_efetivo DATETIME, ADD atualizado_por VARCHAR(40)');
$pdo->prepare("UPDATE sala_audiencias_pauta SET data_audiencia=?, hora_audiencia='09:00:00' WHERE id_pauta='PA'")
    ->execute([date('Y-m-d')]);
$agenda = new SalaAudienciasPautaSequenceLock($pdo);
$agenda->acquire((int) date('Y'), 5, true);
try {
    try {
        $peerPauta->setStatusPainel('PA', 'iniciar', null);
        throw new RuntimeException('Uma segunda conexão ignorou o lock compartilhado da pauta.');
    } catch (SalaAudienciasPautaSequenceLockException) {
        // Expected: no change until the active schedule operation releases the lock.
    }
    if ($pdo->query("SELECT status FROM sala_audiencias_pauta WHERE id_pauta='PA'")->fetchColumn() !== 'designada') {
        throw new RuntimeException('A pauta foi alterada apesar do lock.');
    }
} finally {
    $agenda->release();
}
$peerPauta->setStatusPainel('PA', 'iniciar', null);
if ($pdo->query("SELECT status FROM sala_audiencias_pauta WHERE id_pauta='PA'")->fetchColumn() !== 'em_andamento') {
    throw new RuntimeException('A pauta não progrediu depois de liberar o lock.');
}

$fixtureStage = 'pauta_allocation_lock';
$pdo->exec('CREATE TABLE sala_audiencias_pauta_alocados (
    id VARCHAR(40) PRIMARY KEY, pauta_id VARCHAR(40), usuario_id VARCHAR(40), tipo VARCHAR(16),
    created_at DATETIME, UNIQUE (pauta_id, usuario_id)
) ENGINE=InnoDB');
$pautaAloc = new SalaAudienciasPautaAlocadosRepository($pdo);
$first = $pautaAloc->addAlocado('PA', 'U1', SalaAudienciasPautaAlocadosRepository::TIPO_ALL);
foreach (['U2', 'U3', 'U4'] as $uid) $service->addAlocadoUsuario('PA', $uid);
$pdo->beginTransaction();
try {
    $pautaAloc->addAlocado('PA', 'U5');
    try {
        $peerPauta->addAlocadoUsuario('PA', 'U6');
        throw new RuntimeException('A escrita não esperou a transação da alocação anterior.');
    } catch (PDOException $exception) {
        if ((int) ($exception->errorInfo[1] ?? 0) !== 1205 || $peer->inTransaction()) {
            throw $exception;
        }
    }
    $pdo->commit();
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}
try {
    $peerPauta->addAlocadoUsuario('PA', 'U6');
    throw new RuntimeException('A sexta alocação foi aceita após a primeira transação.');
} catch (InvalidArgumentException $exception) {
    if (!str_contains($exception->getMessage(), 'Limite de 5')) throw $exception;
}
if ((new SalaAudienciasPautaAlocadosRepository($peer))->addAlocado('PA', 'U1') !== $first
    || $pautaAloc->countByPautaId('PA') !== 5 || $peer->inTransaction()) {
    throw new RuntimeException('Repetição idempotente ou capacidade da pauta não foi preservada.');
}

$fixtureStage = 'reu_principal_lock';
require_once '/repo/app/services/GabineteReusPresosService.php';
$pdo->exec("ALTER TABLE gabinete_reu_preso ADD controle_tipo VARCHAR(16), ADD proxima_revisao DATE, ADD updated_at DATETIME");
$pdo->exec("ALTER TABLE gabinete_reu_preso_processo ADD principal INT, ADD data_prisao DATE, ADD created_at DATETIME, ADD updated_at DATETIME");
$pdo->exec('CREATE TABLE gabinete_reu_preso_movimentacao (reu_preso_id VARCHAR(40), processo_id VARCHAR(40), data_movimentacao DATE, tipo_codigo VARCHAR(80)) ENGINE=InnoDB');
$pdo->exec("INSERT INTO gabinete_reu_preso (id,status,controle_tipo) VALUES ('R','ativo','principal')");
$pdo->exec("INSERT INTO gabinete_reu_preso_processo (id,processo_id,reu_preso_id,situacao,principal,data_prisao) VALUES
    ('L1','A','R','solto',1,'2026-01-01'),('L2','B','R','solto',0,'2026-02-01')");
$peerGabinete = new GabineteReusPresosService($peer);
$pdo->beginTransaction();
try {
    (new GabineteReuPresoRepository($pdo))->findById('R', true);
    try {
        $peerGabinete->setPrincipal('R', 'L2');
        throw new RuntimeException('A troca não esperou o bloqueio do réu.');
    } catch (PDOException $exception) {
        if ((int) ($exception->errorInfo[1] ?? 0) !== 1205 || $peer->inTransaction()) throw $exception;
    }
} finally {
    $pdo->rollBack();
}
$peerGabinete->setPrincipal('R', 'L2');
if ($pdo->query("SELECT id FROM gabinete_reu_preso_processo WHERE reu_preso_id='R' AND principal=1")->fetchAll(PDO::FETCH_COLUMN) !== ['L2']
    || $pdo->query("SELECT proxima_revisao FROM gabinete_reu_preso WHERE id='R'")->fetchColumn() !== '2026-05-02') {
    throw new RuntimeException('A troca não deixou um único principal com o prazo correspondente.');
}

$fixtureStage = 'pauta_atomic_derived';
$pdo->exec("CREATE TRIGGER fixture_pauta_timing BEFORE UPDATE ON sala_audiencias_pauta FOR EACH ROW
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture_timings'");
foreach (['add', 'update'] as $operation) {
    $before = snapshot69p($pdo, $tables);
    try {
        if ($operation === 'add') {
            $service->addParte('PA', array_replace($witness, ['nome_parte'=>'Outra testemunha sintética']), 'synthetic');
        } else {
            $service->updateParte($witnessId, array_replace($witness, ['nome_parte'=>'Edição sintética']), 'synthetic');
        }
        throw new RuntimeException('A falha da etapa derivada não foi propagada.');
    } catch (PDOException $exception) {
        if ((string) $exception->getCode() !== '45000' || !str_contains($exception->getMessage(), 'fixture_timings')) {
            throw $exception;
        }
    }
    if ($pdo->inTransaction() || snapshot69p($pdo, $tables) !== $before) {
        throw new RuntimeException('A falha de duração não reverteu parte, vínculo e demais dados.');
    }
}
$pdo->exec('DROP TRIGGER fixture_pauta_timing');

$fixtureStage = 'dashboard_strict_series';
require_once '/repo/app/services/DashboardResumoService.php';
$pdo->exec("SET SESSION sql_mode=CONCAT(@@sql_mode,',ONLY_FULL_GROUP_BY')");
$pdo->exec('CREATE TABLE guias (id VARCHAR(40) PRIMARY KEY, status VARCHAR(40), created_at DATETIME) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE guias_subtarefas (guia_id VARCHAR(40), ordem INT, tipo VARCHAR(40), status VARCHAR(40), updated_at DATETIME) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE dashboard_conclusoes (usuario_id VARCHAR(40), modulo VARCHAR(40), concluido_em DATETIME) ENGINE=InnoDB');
$pdo->exec("INSERT INTO guias VALUES ('G1','concluída',CURDATE()),('G2','concluída',CURDATE()),('G3','concluída',DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'),INTERVAL 1 DAY))");
$pdo->exec("INSERT INTO dashboard_conclusoes VALUES
    ('U','oficios',CURDATE()),('U','oficios',CURDATE()),('U','cartas_recebidas',DATE_SUB(CURDATE(),INTERVAL 1 DAY)),
    ('outro','oficios',CURDATE())");
$d = (new DashboardResumoService($pdo))->resumo('U',['CARTORIO'],'CARTORIO');
if ($d['geral']['cadastros_mensais']['values'][4] !== 1 || $d['geral']['cadastros_mensais']['values'][5] !== 2
    || $d['geral']['conclusoes_semanais'][5] !== 1 || $d['geral']['conclusoes_semanais'][6] !== 3
    || $d['usuario']['weekly'][5] !== 1 || $d['usuario']['weekly'][6] !== 2
    || $d['usuario']['weekly_by_module']['oficios'][6] !== 2) {
    throw new RuntimeException('As séries não preservaram agrupamentos mensais, globais e por usuário.');
}

$fixtureStage = 'atas_csv';
require_once '/repo/app/helpers/InternalListExportHandlers.php';
require_once '/repo/app/helpers/ListExportHelper.php';
$pdo->exec("ALTER TABLE sala_audiencias_pauta ADD localizacao VARCHAR(120), ADD created_at DATETIME, ADD updated_at DATETIME, ADD criado_por VARCHAR(40)");
$pdo->exec('CREATE TABLE usuarios (id_usuario VARCHAR(40) PRIMARY KEY, nome VARCHAR(80), sobrenome VARCHAR(80)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$pdo->exec('CREATE TABLE sala_audiencias_ata (id_ata VARCHAR(40) PRIMARY KEY, pauta VARCHAR(40), expires_at DATETIME,
    nome_original VARCHAR(80), uploaded_at DATETIME, arquivo_relativo VARCHAR(120), uploaded_by VARCHAR(40)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$pdo->exec("UPDATE sala_audiencias_pauta SET status='finalizada',data_audiencia=CURDATE() WHERE id_pauta IN ('PA','PB')");
$pdo->exec("UPDATE processos SET sigiloso=1 WHERE id='B'");
$pdo->exec("INSERT INTO sala_audiencias_ata (id_ata,pauta,expires_at,uploaded_at,arquivo_relativo) VALUES
    ('ATA_A','PA',DATE_ADD(NOW(),INTERVAL 1 DAY),'2026-11-20 14:35:21','ata-publica.pdf'),
    ('ATA_B','PB',DATE_ADD(NOW(),INTERVAL 1 DAY),'2026-11-20 12:30:00','ata-sigilosa.pdf')");
$a = InternalListExportHandlers::dispatch('salaata',$pdo,'synthetic',[]);
if (count($a['rows']) !== 1 || $a['rows'][0]['uploaded_at'] !== '20/11/2026 14:35') {
    throw new RuntimeException('Data de envio incorreta ou política de seleção da exportação não preservada.');
}
ob_start();
ListExportHelper::streamCsv($a['slug'],$a['title'],'',$a['columns'],$a['rows']);
$csv = ob_get_clean();
if (!str_contains($csv,'20/11/2026 14:35') || !str_contains($csv,'ata-publica.pdf')
    || str_contains($csv,'ata-sigilosa.pdf') || str_contains($csv,'Deprecated')) {
    throw new RuntimeException('O CSV não preservou a data de envio ou a exclusão de atas sigilosas.');
}

echo "PASS REI69 pauta cross-process synthetic service regression\n";
