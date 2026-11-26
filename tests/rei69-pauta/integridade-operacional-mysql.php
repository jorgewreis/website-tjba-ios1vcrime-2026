<?php
declare(strict_types=1);

// Incluído somente pela fixture cuja conexão e sentinela foram verificadas.
if (!isset($pdo, $peer, $dsn, $service) || !$pdo instanceof PDO || !$peer instanceof PDO
    || !isset($guards, $marker) || !hash_equals($marker, (string) ($guards[0] ?? ''))
    || $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'rei69_pauta_test') {
    throw new LogicException('Conexão sintética da fixture obrigatória.');
}

$fixtureStage = 'pauta_delete_atomic';
$pdo->exec("INSERT INTO sala_audiencias_pauta_alocados VALUES ('FIX-PB','PB','U7','specific',NOW())");
$before = snapshot69p($peer, ['sala_audiencias_pauta', 'sala_audiencias_pauta_alocados']);
$pdo->exec("CREATE TRIGGER fixture_pauta_delete BEFORE DELETE ON sala_audiencias_pauta_alocados FOR EACH ROW
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture_delete'");
try {
    $service->delete('PA', 'synthetic');
    throw new RuntimeException('Falha sintética de persistência não propagada.');
} catch (PDOException $exception) {
    if ((string) $exception->getCode() !== '45000') throw $exception;
}
if ($pdo->inTransaction() || snapshot69p($peer, array_keys($before)) !== $before) {
    throw new RuntimeException('Pauta ou alocações alteradas sem confirmação.');
}
$pdo->exec('DROP TRIGGER fixture_pauta_delete');
if (!$service->delete('PA', 'synthetic') || $service->delete('PA', 'synthetic')
    || (int) $peer->query("SELECT ativo FROM sala_audiencias_pauta WHERE id_pauta='PA'")->fetchColumn() !== 0
    || $peer->query('SELECT pauta_id FROM sala_audiencias_pauta_alocados')->fetchAll(PDO::FETCH_COLUMN) !== ['PB']) {
    throw new RuntimeException('Exclusão da pauta não foi seletiva ou repetível.');
}

$fixtureStage = 'oficios_sequence';
require_once '/repo/app/services/OficiosGabineteService.php';
$pdo->exec('CREATE TABLE oficios_gabinete (
    id VARCHAR(40) PRIMARY KEY, processo_id VARCHAR(40), numero_processo VARCHAR(40),
    ano INT NOT NULL, numero_sequencial INT NOT NULL, tipo_oficio VARCHAR(100), status VARCHAR(30),
    criado_por VARCHAR(40), atualizado_por VARCHAR(40), created_at DATETIME, updated_at DATETIME,
    UNIQUE (ano,numero_sequencial)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$ano = (int) date('Y');
$old = $pdo->prepare("INSERT INTO oficios_gabinete (id,ano,numero_sequencial) VALUES ('OFG-OUTRO-ANO',?,10)");
$old->execute([$ano - 1]);
$issuer = new OficiosGabineteService($pdo);
$anotherIssuer = new OficiosGabineteService($peer);
$oficio = ['tipo_oficio' => 'Outros'];
$first = $issuer->create($oficio);
$lockName = 'fokus_ofg_' . substr(hash('sha256', 'rei69_pauta_test:' . $ano), 0, 48);
$controlLock = $pdo->prepare('SELECT GET_LOCK(?,0)');
$controlLock->execute([$lockName]);
if ((int) $controlLock->fetchColumn() !== 1) throw new RuntimeException('Lock de controle indisponível.');
$controlLock->closeCursor();
try {
    foreach ([fn () => $anotherIssuer->create($oficio), fn () => $anotherIssuer->delete($first)] as $op) {
        try {
            $op();
            throw new LogicException('Mutação não esperou a reserva anual.');
        } catch (RuntimeException $exception) {
            if (!str_contains($exception->getMessage(), 'sequencial de ofícios está ocupado') || $peer->inTransaction()) throw $exception;
        }
    }
} finally {
    $unlock = $pdo->prepare('SELECT RELEASE_LOCK(?)');
    $unlock->execute([$lockName]);
    $unlock->closeCursor();
}
$second = $anotherIssuer->create($oficio);
try {
    $issuer->delete($first);
    throw new RuntimeException('O penúltimo ofício foi removido.');
} catch (InvalidArgumentException) {
}
if (!$issuer->delete($second)['deleted'] || !$issuer->delete($first)['deleted']) {
    throw new RuntimeException('Exclusão em ordem inversa ao cadastro não foi confirmada.');
}
$replacement = $anotherIssuer->create($oficio);
if ((int) $peer->query("SELECT numero_sequencial FROM oficios_gabinete WHERE ano=$ano")->fetchColumn() !== 1
    || $peer->query("SELECT id FROM oficios_gabinete WHERE ano< $ano")->fetchAll(PDO::FETCH_COLUMN) !== ['OFG-OUTRO-ANO']
    || $replacement === $first) {
    throw new RuntimeException('Sequencial pós-exclusão ou outro exercício mudou indevidamente.');
}

$fixtureStage = 'feriados_cleanup';
require_once '/repo/app/services/FeriadosService.php';
$pdo->exec('CREATE TABLE feriados (
    id VARCHAR(64) PRIMARY KEY, tipo_feriado VARCHAR(32), data_inicio DATE, data_fim DATE,
    regra_data VARCHAR(32), descricao VARCHAR(255), ativo INT, created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB');
$calendarConnection = new class($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]) extends PDO {
    public string $fixtureFailure = '';
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if ($this->fixtureFailure === '' || !str_contains($query, 'RELEASE_LOCK(')) {
            return parent::query($query, $fetchMode, ...$fetchModeArgs);
        }
        $at = $this->fixtureFailure;
        $this->fixtureFailure = '';
        if ($at === 'after') parent::query($query, $fetchMode, ...$fetchModeArgs)->closeCursor();
        throw new PDOException('Falha artificial no encerramento do bloqueio.');
    }
};
$calendar = new FeriadosService($calendarConnection);
foreach (['before','after'] as $pos) {
    $calendarConnection->fixtureFailure = $pos;
    $holiday = $calendar->create(['tipo_feriado'=>'municipal', 'data_inicio'=>'2040-05-01',
        'data_fim'=>'2040-05-01', 'regra_data'=>'unica', 'ativo'=>0, 'descricao'=>'Sintético']);
    $found = $peer->prepare('SELECT id FROM feriados WHERE id=?');
    $found->execute([$holiday['id']]);
    if ($found->fetchColumn() !== $holiday['id'] || (int) $peer->query("SELECT IS_FREE_LOCK('fokus-law-feriados-recalc')")->fetchColumn() !== 1) {
        throw new RuntimeException('O resultado confirmado ou a liberação do feriado não foi preservado.');
    }
}
$calendarConnection->fixtureFailure = 'before';
try {
    $calendar->delete('FER-' . str_repeat('1', 26));
    throw new RuntimeException('Erro de operação ausente.');
} catch (InvalidArgumentException) {
}
if ((int) $peer->query('SELECT COUNT(*) FROM feriados')->fetchColumn() !== 2
    || (int) $peer->query("SELECT IS_FREE_LOCK('fokus-law-feriados-recalc')")->fetchColumn() !== 1) {
    throw new RuntimeException('Rollback ou liberação após erro não confirmados.');
}

$fixtureStage = 'guias_readonly';
require_once '/repo/app/services/GuiasService.php';
$pdo->exec('ALTER TABLE guias DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci,
    MODIFY id VARCHAR(40) COLLATE utf8mb4_unicode_ci, MODIFY status VARCHAR(40) COLLATE utf8mb4_unicode_ci,
    ADD numero_processo VARCHAR(40), ADD processo_id VARCHAR(40), ADD parte_id VARCHAR(40), ADD denunciado VARCHAR(120),
    ADD tipo VARCHAR(40), ADD etapa VARCHAR(40), ADD data_fato DATE, ADD dias_multa INT, ADD valor_multa DECIMAL(10,2),
    ADD updated_at DATETIME, ADD criado_por VARCHAR(40), ADD atualizado_por VARCHAR(40)');
$pdo->exec('ALTER TABLE guias_subtarefas ADD id VARCHAR(40) PRIMARY KEY, ADD dados_especificos TEXT, ADD created_at DATETIME');
$pdo->exec('CREATE TABLE guias_alocados (id VARCHAR(40), guia_id VARCHAR(40), usuario_id VARCHAR(40), tipo VARCHAR(40), created_at DATETIME)
    ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$pdo->exec('CREATE TABLE salario_minimo (vigencia_inicio DATE, valor DECIMAL(10,2)) ENGINE=InnoDB');
$pdo->exec("INSERT INTO salario_minimo VALUES ('2020-01-01',1500)");
$pdo->exec("INSERT INTO guias (id,status,numero_processo,denunciado,tipo,data_fato,dias_multa,valor_multa,updated_at) VALUES
    ('LEG-NULL','pendente','fixture-focus','Pessoa; Sintética','definitiva','2020-06-01',60,NULL,'2026-11-01'),
    ('LEG-VAL','pendente','fixture-focus-2','Pessoa Sintética','definitiva','2020-06-01',60,999,'2026-11-01'),
    ('FORA','pendente','outro','Fora do filtro','definitiva','2020-06-01',60,NULL,'2026-11-01')");
$guideTables = ['guias', 'guias_subtarefas', 'guias_alocados'];
$beforeGuides = snapshot69p($peer, $guideTables);
$guides = new GuiasService($pdo);
foreach ([0, 1] as $round) {
    $list = $guides->listGuias(['processo'=>'fixture-focus'],15,0);
    $map = array_column($list['rows'], null, 'IdGuia');
    if ($list['total'] !== 2 || count($map) !== 2 || (float) ($map['LEG-NULL']['valor_multa'] ?? 0) !== 3000.0
        || (float) ($map['LEG-VAL']['valor_multa'] ?? 0) !== 999.0 || $map['LEG-NULL']['SubtarefasAll'] !== []
        || (float) $guides->find('LEG-NULL')['valor_multa'] !== 3000.0) {
        throw new RuntimeException('Leitura de guias legadas ou filtros não preservados.');
    }
    $export = InternalListExportHandlers::dispatch('guias',$pdo,'synthetic',['processo'=>'fixture-focus']);
    ob_start();
    ListExportHelper::streamCsv($export['slug'],$export['title'],'',$export['columns'],$export['rows']);
    $csv = ob_get_clean();
    if (count($export['rows']) !== 2 || !str_contains($csv, '"Pessoa; Sintética"')
        || str_contains($csv, 'Fora do filtro') || str_contains($csv, 'Deprecated')
        || snapshot69p($peer, $guideTables) !== $beforeGuides) {
        throw new RuntimeException('CSV, filtros ou invariância dos registros não confirmados.');
    }
}
$guides->criarPrimeiraSubtarefa('LEG-NULL');
if ((int) $peer->query("SELECT COUNT(*) FROM guias_subtarefas WHERE guia_id='LEG-NULL'")->fetchColumn() !== 8
    || (float) $peer->query("SELECT valor_multa FROM guias WHERE id='LEG-NULL'")->fetchColumn() !== 3000.0
    || $peer->query("SELECT valor_multa FROM guias WHERE id='FORA'")->fetchColumn() !== null) {
    throw new RuntimeException('Inicialização explícita não gravou apenas a guia selecionada.');
}
