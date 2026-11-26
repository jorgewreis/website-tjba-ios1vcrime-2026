<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/SalaAudienciasAtaService.php';
require_once __DIR__ . '/../../app/helpers/SalaAudienciasInscricaoIndisponivel.php';

$timezoneOriginal = date_default_timezone_get();
try {
    foreach (['America/Bahia', 'UTC'] as $timezone) {
        date_default_timezone_set($timezone);
        if (!SalaAudienciasAtaService::dataPermiteInscricaoEstudante(date('Y-m-d'))) {
            throw new RuntimeException('A política deve permitir inscrição na data local da audiência.');
        }
        foreach ([date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('+1 day')), ''] as $dataBloqueada) {
            if (SalaAudienciasAtaService::dataPermiteInscricaoEstudante($dataBloqueada)) {
                throw new RuntimeException('A política aceitou data ausente ou diferente do dia local.');
            }
        }
    }
} finally {
    date_default_timezone_set($timezoneOriginal);
}

$agoraFixo = strtotime('2026-11-23 10:00:00');
if ($agoraFixo === false
    || SalaAudienciasAtaService::normalizarStatusPauta('designada', '2026-11-23', '09:59:59', $agoraFixo) !== 'atrasada'
    || SalaAudienciasAtaService::normalizarStatusPauta('agendada', '2026-11-23', '09:59:59', $agoraFixo) !== 'atrasada'
    || SalaAudienciasAtaService::normalizarStatusPauta('designada', '2026-11-23', '10:00:00', $agoraFixo) !== 'designada'
    || SalaAudienciasAtaService::normalizarStatusPauta('encerrada', '2026-11-23', '09:00:00', $agoraFixo) !== 'finalizada') {
    throw new RuntimeException('A normalização determinística do status da pauta divergiu.');
}

// Use horários fixos dentro do dia local: somar 1h ao relógio real
// atravessava a meia-noite e convertia uma audiência futura em atrasada.
$dataHojeElegibilidade = date('Y-m-d');
$agoraElegibilidade = strtotime($dataHojeElegibilidade . ' 10:00:00');
$horaFuturaElegibilidade = '11:00:00';
$elegiveisStatus = [
    'id_pauta' => 'pauta-status-sintetica',
    'ja_inscrito' => 0,
    'status' => 'designada',
    'data_audiencia' => $dataHojeElegibilidade,
    'hora_audiencia' => $horaFuturaElegibilidade,
];
if (SalaAudienciasAtaService::statusIndisponivelParaEstudanteNaoInscrito($elegiveisStatus, $agoraElegibilidade)
    || !SalaAudienciasAtaService::statusIndisponivelParaEstudanteNaoInscrito(array_merge($elegiveisStatus, ['status' => 'em_andamento']), $agoraElegibilidade)
    || !SalaAudienciasAtaService::statusIndisponivelParaEstudanteNaoInscrito(array_merge($elegiveisStatus, ['data_audiencia' => date('Y-m-d', strtotime('+1 day', $agoraElegibilidade))]), $agoraElegibilidade)
    || SalaAudienciasAtaService::statusIndisponivelParaEstudanteNaoInscrito(array_merge($elegiveisStatus, ['ja_inscrito' => 1, 'status' => 'em_andamento']), $agoraElegibilidade)
    || SalaAudienciasAtaService::statusIndisponivelParaEstudanteNaoInscrito(array_merge($elegiveisStatus, ['ja_inscrito' => 1, 'status' => 'finalizada']), $agoraElegibilidade)
    || !SalaAudienciasAtaService::statusIndisponivelParaEstudanteNaoInscrito(array_merge($elegiveisStatus, ['total_inscritos_pauta' => 5]), $agoraElegibilidade)
    || !SalaAudienciasAtaService::statusIndisponivelParaEstudanteNaoInscrito(array_merge($elegiveisStatus, ['total_inscritos_pauta' => 6]), $agoraElegibilidade)
    || SalaAudienciasAtaService::statusIndisponivelParaEstudanteNaoInscrito(array_merge($elegiveisStatus, ['ja_inscrito'=>1, 'total_inscritos_pauta'=>6]), $agoraElegibilidade)) {
    throw new RuntimeException('A elegibilidade restringiu inscritos ou expôs status/data de não inscritos.');
}
echo "PASS: elegibilidade por status/data redige apenas novos não inscritos e preserva inscritos\n";

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
    $db->prepare("INSERT INTO sala_audiencias_pauta VALUES (?, 1, ?, ?, '23:59:59', 'designada')")
        ->execute([$pauta, $processo, $dataHojeElegibilidade]);
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
