<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../app/helpers/bootstrap.php';
require_once __DIR__ . '/../app/repositories/FeriadosRepository.php';
require_once __DIR__ . '/../app/helpers/RandomPhrase.php';

// Evita HTML em cache com token CSRF antigo após logout (login falhava até F5).
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

if (Security::isAuthenticated()) {
    header('Location: /home.php');
    exit;
}

$hoje = new DateTimeImmutable('today');
$isFimDeSemana = in_array((int) $hoje->format('N'), [6, 7], true);
$feriadoInfo = null;
try {
    $conn = Conection::getInstance()->getConnection();
    $feriadosRepo = new FeriadosRepository($conn);
    $feriadoInfo = $feriadosRepo->getByDate($hoje);
} catch (Throwable $e) {
    error_log('index feriado check: ' . $e->getMessage());
}
$isFeriado    = $feriadoInfo !== null;
$isDiaNaoUtil = $isFimDeSemana || $isFeriado;

function formatarDataExtenso(DateTimeInterface $data): string
{
    $dias  = ['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];
    $meses = [1=>'janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
    $dia   = (int) $data->format('j');
    $mes   = $meses[(int) $data->format('n')] ?? $data->format('n');
    return $dia . ' de ' . $mes;
}

$infoExpediente = null;
if ($isFimDeSemana) {
    $diaNome = ['segunda','terça','quarta','quinta','sexta','sábado','domingo'][(int)$hoje->format('N') - 1];
    $infoExpediente = 'Hoje é ' . $diaNome . ', não temos expediente nesta data.';
} elseif ($isFeriado && $feriadoInfo) {
    $dataExtenso = formatarDataExtenso($hoje);
    $descricao   = trim((string) ($feriadoInfo['descricao'] ?? ''));
    if ($descricao === '') {
        $descricao = trim((string) ($feriadoInfo['tipo_feriado'] ?? 'feriado'));
    }
    $infoExpediente = 'Hoje é ' . $dataExtenso . ', feriado ' . $descricao . ', não temos expediente hoje.';
}

$iframeSrc      = '/views/public/login-form.php';
$pageTitle      = 'Primeira Vara Crime de Ilhéus - Sistema de Gestão';
$leftPhrase     = RandomPhrase::getRandom();
$leftBannerInfo = $infoExpediente;

include __DIR__ . '/_layout_login.php';
