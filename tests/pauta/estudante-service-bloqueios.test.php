<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/SalaAudienciasAtaService.php';
require_once __DIR__ . '/../../app/helpers/SalaAudienciasInscricaoIndisponivel.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach ([
    'CREATE TABLE sala_audiencias_pauta (id_pauta TEXT, ativo INTEGER, processo TEXT, data_audiencia TEXT, hora_audiencia TEXT, status TEXT)',
    'CREATE TABLE processos (id TEXT, numero_processo TEXT, classe_id TEXT, sigiloso INTEGER)',
    'CREATE TABLE classes (id TEXT, nome TEXT)',
    'CREATE TABLE processo_prioridades (id TEXT, processo_id TEXT, codigo TEXT, origem TEXT, regra_detalhe TEXT, created_at TEXT)',
    'CREATE TABLE sala_audiencias_pauta_parte (pauta TEXT, ativo INTEGER, maioridade TEXT)',
    'CREATE TABLE sala_audiencias_estudante_inscricao (id TEXT, pauta TEXT, usuario TEXT, created_at TEXT)',
] as $sql) {
    $db->exec($sql);
}

$service = new SalaAudienciasAtaService($db);
$codes = PrioridadesProcessuaisCatalog::codigosBloqueantesInscricaoEstudante();
foreach ($codes as $i => $code) {
    $processo = 'processo-' . $i;
    $pauta = 'pauta-' . $i;
    $db->prepare('INSERT INTO processos VALUES (?, ?, NULL, 0)')->execute([$processo, 'SEGREDO-' . $i]);
    $db->prepare("INSERT INTO sala_audiencias_pauta VALUES (?, 1, ?, date('now'), '10:00', 'designada')")
        ->execute([$pauta, $processo]);
    $db->prepare('INSERT INTO processo_prioridades VALUES (?, ?, ?, \'manual\', NULL, CURRENT_TIMESTAMP)')
        ->execute(['prioridade-' . $i, $processo, $code]);
    try {
        $service->inscreverEstudante($pauta, 'estudante-sintetico');
        throw new RuntimeException('Serviço aceitou prioridade bloqueante: ' . $code);
    } catch (SalaAudienciasInscricaoIndisponivel $e) {
        if ($e->getMessage() !== 'Audiência indisponível.') {
            throw new RuntimeException('O serviço revelou motivo sensível: ' . $code);
        }
    }
}

$count = (int) $db->query('SELECT COUNT(*) FROM sala_audiencias_estudante_inscricao')->fetchColumn();
if ($count !== 0) {
    throw new RuntimeException('A inscrição bloqueada produziu persistência.');
}
echo "PASS: serviço rejeita todas as prioridades canônicas com resposta mínima e sem inscrição\n";
