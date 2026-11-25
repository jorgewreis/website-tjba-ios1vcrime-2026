<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/SalaAudienciasAtaService.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach ([
    'CREATE TABLE sala_audiencias_pauta (id_pauta TEXT PRIMARY KEY, ativo INTEGER, processo TEXT, data_audiencia TEXT, hora_audiencia TEXT, status TEXT)',
    'CREATE TABLE processos (id TEXT, numero_processo TEXT, classe_id TEXT, sigiloso INTEGER)',
    'CREATE TABLE classes (id TEXT, nome TEXT)',
    'CREATE TABLE processo_prioridades (id TEXT, processo_id TEXT, codigo TEXT, origem TEXT, regra_detalhe TEXT, created_at TEXT)',
    'CREATE TABLE sala_audiencias_pauta_parte (pauta TEXT, ativo INTEGER, maioridade TEXT)',
    'CREATE TABLE sala_audiencias_estudante_inscricao (id TEXT PRIMARY KEY, pauta TEXT, usuario TEXT, created_at TEXT)',
] as $sql) {
    $db->exec($sql);
}

$insertPauta = $db->prepare('INSERT INTO sala_audiencias_pauta VALUES (?, 1, NULL, ?, \'10:00\', \'designada\')');
$insertPauta->execute(['pauta-capacidade', date('Y-m-d')]);
$insertPauta->execute(['pauta-duplicada', date('Y-m-d')]);
$insertInscricao = $db->prepare('INSERT INTO sala_audiencias_estudante_inscricao VALUES (?, ?, ?, CURRENT_TIMESTAMP)');
for ($i = 1; $i <= 5; $i++) {
    $insertInscricao->execute(['inscricao-' . $i, 'pauta-capacidade', 'usuario-' . $i]);
}
$insertInscricao->execute(['inscricao-dup', 'pauta-duplicada', 'usuario-repetido']);

$service = new SalaAudienciasAtaService($db);
$limitMessage = 'O limite de 5 estudantes por audiência foi atingido. Não é possível efetuar novas inscrições.';
try {
    $service->inscreverEstudante('pauta-capacidade', 'usuario-novo');
    throw new RuntimeException('Serviço aceitou a sexta inscrição.');
} catch (InvalidArgumentException $e) {
    if ($e->getMessage() !== $limitMessage) {
        throw $e;
    }
}
if ($db->inTransaction()) {
    throw new RuntimeException('A transação da tentativa no limite não foi revertida.');
}
if ((int) $db->query("SELECT COUNT(*) FROM sala_audiencias_estudante_inscricao WHERE pauta='pauta-capacidade'")->fetchColumn() !== 5) {
    throw new RuntimeException('A tentativa no limite alterou as inscrições.');
}

try {
    $service->inscreverEstudante('pauta-duplicada', 'usuario-repetido');
    throw new RuntimeException('Serviço aceitou inscrição duplicada.');
} catch (InvalidArgumentException $e) {
    if ($e->getMessage() !== 'Você já está inscrito nesta audiência.') {
        throw $e;
    }
}
if ($db->inTransaction()) {
    throw new RuntimeException('A transação da tentativa duplicada não foi revertida.');
}
if ((int) $db->query("SELECT COUNT(*) FROM sala_audiencias_estudante_inscricao WHERE pauta='pauta-duplicada'")->fetchColumn() !== 1) {
    throw new RuntimeException('A tentativa duplicada alterou as inscrições.');
}

$db->beginTransaction();
try {
    $service->inscreverEstudante('pauta-duplicada', 'outro-usuario');
    throw new RuntimeException('Serviço aceitou transação externa.');
} catch (LogicException $e) {
    if ($e->getMessage() !== 'A inscrição deve iniciar sua própria transação.') {
        throw $e;
    }
}
$db->rollBack();

echo "PASS: limite, duplicidade e propriedade da transação são preservados; SQLite não valida concorrência InnoDB\n";
