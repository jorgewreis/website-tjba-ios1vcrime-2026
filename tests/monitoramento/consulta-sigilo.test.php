<?php
declare(strict_types=1);

// Dados exclusivamente sintéticos. Réu já vinculado evita qualquer chamada DataJud.
require_once __DIR__ . '/../../app/services/GabineteMonitoramentoEletronicoService.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE processos (id TEXT, numero_processo TEXT, classe_id TEXT, data_fato TEXT, sigiloso INTEGER, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE partes (id TEXT, nome_completo TEXT)');
$db->exec('CREATE TABLE processos_partes (id TEXT, processo_id TEXT, parte_id TEXT, tipo_parte TEXT, polo TEXT)');
$db->exec("INSERT INTO processos VALUES ('P-SIG', '0000001-69.2020.8.05.0001', NULL, NULL, 1, '', '')");
$db->exec("INSERT INTO partes VALUES ('PARTE-SINTETICA', 'Nome Sintetico')");
$db->exec("INSERT INTO processos_partes VALUES ('VINCULO', 'P-SIG', 'PARTE-SINTETICA', 'reu', 'passivo')");
$service = new GabineteMonitoramentoEletronicoService($db);
$direto = $service->listarReusDoProcesso('P-SIG');
$porNumero = $service->ensureProcessoEListarReusParaCadastro('0000001-69.2020.8.05.0001');
if (count($direto) !== 1 || $direto[0]['id'] !== 'VINCULO'
    || $porNumero['processo_id'] !== 'P-SIG' || $porNumero['reus'] !== $direto
    || (int) $db->query("SELECT sigiloso FROM processos WHERE id='P-SIG'")->fetchColumn() !== 1) {
    throw new RuntimeException('Consulta sintética de processo sigiloso falhou ou alterou sigilo.');
}
echo "PASS consulta de processo sigiloso por ID e CNJ com réu existente (SQLite; sem DataJud)\n";
