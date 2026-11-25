<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/repositories/SalaAudienciasEstudanteInscricaoRepository.php';
require_once __DIR__ . '/../../app/helpers/SalaAudienciasEstudantePrivacidade.php';
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('NOW', fn() => '2026-01-01 00:00:00');
$db->sqliteCreateFunction('CURDATE', fn() => date('Y-m-d', strtotime('+1 day')));
date_default_timezone_set('America/Bahia');
$today = date('Y-m-d');
$dbTodaySimulado = date('Y-m-d', strtotime('+1 day'));
if ($dbTodaySimulado === $today) throw new RuntimeException('A fixture deve simular o descompasso de data entre PHP e MySQL.');
foreach ([
    'CREATE TABLE sala_audiencias_estudante_inscricao (id TEXT, pauta TEXT, usuario TEXT, created_at TEXT)',
    'CREATE TABLE sala_audiencias_pauta (id_pauta TEXT, ativo INTEGER, processo TEXT, tipo_audiencia TEXT, data_audiencia TEXT, hora_audiencia TEXT, status TEXT, data_hora_inicio_efetivo TEXT, data_hora_fim_efetivo TEXT, duracao_estimada_minutos INTEGER, localizacao TEXT, link_audiencia TEXT)',
    'CREATE TABLE processos (id TEXT, numero_processo TEXT, classe_id TEXT, sigiloso INTEGER)',
    'CREATE TABLE classes (id TEXT, nome TEXT)',
    'CREATE TABLE sala_audiencias_ata (pauta TEXT, id_ata TEXT, expires_at TEXT, nome_original TEXT, uploaded_at TEXT, arquivo_relativo TEXT)',
    'CREATE TABLE sala_audiencias_pauta_parte (pauta TEXT, ativo INTEGER, maioridade TEXT)',
    'CREATE TABLE processo_prioridades (processo_id TEXT, codigo TEXT)',
] as $sql) $db->exec($sql);
foreach (['sigilo','menor','prioridade','medida','livre','outro'] as $id) {
    $db->prepare('INSERT INTO processos VALUES (?, ?, NULL, ?)')->execute([$id,'SEGREDO-'.$id,$id === 'sigilo' ? 1 : 0]);
    $db->prepare("INSERT INTO sala_audiencias_pauta (id_pauta,ativo,processo,tipo_audiencia,data_audiencia,hora_audiencia,status) VALUES (?,1,?,'TIPO',?,'10:00','designada')")->execute([$id,$id,$today]);
    $db->prepare("INSERT INTO sala_audiencias_estudante_inscricao (id,pauta,usuario,created_at) VALUES (?, ?, ?, '2025-01-01')")->execute(['inscricao-'.$id,$id,$id === 'outro' ? 'outro' : 'usuario']);
    $db->prepare("INSERT INTO sala_audiencias_ata VALUES (?, 'ata', '2025-01-01', 'SEGREDO.pdf', '2025-01-01', 'arquivo')")->execute([$id]);
}
$db->exec("INSERT INTO sala_audiencias_pauta_parte VALUES ('menor',1,'menor')");
$db->exec("INSERT INTO processo_prioridades VALUES ('prioridade','violencia_domestica')");
$db->exec("INSERT INTO processo_prioridades VALUES ('medida','medida_protetiva')");
$rows=(new SalaAudienciasEstudanteInscricaoRepository($db))->listInscricoesVisiveisParaUsuario('usuario');
$ids=array_column($rows,'pauta'); sort($ids);
if ($ids !== ['medida','menor','prioridade','sigilo']) throw new RuntimeException('Filtro de inscrições não preservou somente bloqueadas esperadas.');
foreach ($rows as $row) if (!SalaAudienciasEstudantePrivacidade::indisponivel($row)) throw new RuntimeException('Linha bloqueada não reconhecida.');
$hojeRows=(new SalaAudienciasEstudanteInscricaoRepository($db))->listHojeDesignadasElegiveis('usuario');
$medidaHoje=array_values(array_filter($hojeRows, static fn(array $row): bool => $row['id_pauta'] === 'medida'));
if (count($medidaHoje) !== 1 || !SalaAudienciasEstudantePrivacidade::indisponivel($medidaHoje[0])) {
    throw new RuntimeException('Pauta com medida_protetiva não foi bloqueada na listagem do painel.');
}
echo "PASS: privacy redaction and Bahia-local today query remain stable when simulated MySQL CURDATE differs\n";
