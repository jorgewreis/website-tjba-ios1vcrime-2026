<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/services/GabineteMonitoramentoEletronicoService.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE processos (id TEXT, numero_processo TEXT, classe_id TEXT, data_fato TEXT, sigiloso INTEGER, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE classes (id TEXT, codigo TEXT, nome TEXT)');
$db->exec('CREATE TABLE partes (id TEXT, nome_completo TEXT)');
$db->exec('CREATE TABLE processos_partes (id TEXT, processo_id TEXT, parte_id TEXT, tipo_parte TEXT, polo TEXT)');
$db->exec("INSERT INTO processos VALUES ('P', '0000001-69.2020.8.05.0001', NULL, NULL, 1, '', '')");
$db->exec("INSERT INTO partes VALUES ('A', 'Pessoa homônima sintética')");
$db->exec("INSERT INTO processos_partes VALUES ('V', 'P', 'A', 'vitima', 'ativo')");
$before = [];
foreach (['processos','classes','partes','processos_partes'] as $table) $before[$table] = $db->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);
$service = new GabineteMonitoramentoEletronicoService($db);
$novo = $service->ensureProcessoEListarReusParaCadastro('0000002-54.2020.8.05.0001', '123', 'Classe enviada');
$existente = $service->ensureProcessoEListarReusParaCadastro('0000001-69.2020.8.05.0001', '123', 'Classe enviada');
foreach ([[$novo, ''], [$existente, 'P']] as [$result, $expectedId]) {
    if ($result['processo_id'] !== $expectedId || $result['reus'] !== [] || empty($result['mensagem'])) {
        throw new RuntimeException('Consulta vazia deve oferecer entrada manual.');
    }
}
foreach ($before as $table=>$rows) {
    if ($db->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC) !== $rows) throw new RuntimeException("Consulta alterou $table.");
}
$db->exec("INSERT INTO processos_partes VALUES ('R', 'P', 'A', 'reu', 'passivo')");
$comReu = $service->ensureProcessoEListarReusParaCadastro('0000001-69.2020.8.05.0001');
if (count($comReu['reus']) !== 1 || $comReu['reus'][0]['id'] !== 'R') throw new RuntimeException('Réu vinculado não retornou.');
echo "PASS consulta de réus sem escrita e entrada manual (SQLite sintético; sem DataJud)\n";
