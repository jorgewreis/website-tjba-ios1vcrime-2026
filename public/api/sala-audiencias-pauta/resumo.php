<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/sala-audiencias-pauta/resumo.php');
$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/config/security.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath . '/app/access/AccessEvaluator.php';
require_once $basePath . '/app/helpers/AudienciaResumoBuilder.php';
require_once $basePath . '/app/helpers/PdfStreamHelper.php';

/**
 * Resumo da audiência em TXT ou PDF.
 *
 * GET ?id_pauta=<id>&formato=txt|pdf
 *
 * - Auth via Security + perfil habilitado para `salapautaedit`.
 * - 404 se a audiência não existir.
 * - 409 se o status normalizado não estiver em {designada, redesignada}
 *   (mesma lógica do edit screen em `sala-audiencias-pauta-edit.php`).
 * - TXT: text/plain UTF-8 com BOM, CRLF entre linhas. Marcadores WhatsApp
 *   permanecem literais (`*bold*`, `_italic_`, etc.).
 * - PDF: A4 retrato via Dompdf, marcadores WhatsApp convertidos em HTML.
 */

$resumoErroPlain = static function (int $code, string $message): void {
    http_response_code($code);
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=UTF-8');
    }
    echo $message;
    exit;
};

try {
    if (!Security::isAuthenticated()) {
        $resumoErroPlain(401, 'Sessão expirada. Faça login novamente.');
    }
    $userId = trim((string) ($_SESSION['id_usuario'] ?? ''));
    if ($userId === '') {
        $resumoErroPlain(401, 'Sessão expirada. Faça login novamente.');
    }

    $idPauta = trim((string) ($_GET['id_pauta'] ?? ''));
    $formato = strtolower(trim((string) ($_GET['formato'] ?? '')));
    if ($idPauta === '') {
        $resumoErroPlain(400, 'Parâmetro id_pauta é obrigatório.');
    }
    if ($formato !== 'txt' && $formato !== 'pdf') {
        $resumoErroPlain(400, 'Parâmetro formato deve ser "txt" ou "pdf".');
    }

    $userPerfils = UsuarioPerfisAutorizacao::perfisDoUsuario($userId);
    if (!AccessEvaluator::canAccessPage('salapautaedit', $userPerfils)) {
        $resumoErroPlain(403, 'Acesso negado.');
    }

    $pdo = Conection::getInstance()->getConnection();

    // Verifica a audiência e seu status antes de gastar tempo coletando partes.
    $st = $pdo->prepare('SELECT `id_pauta`, `status` FROM `sala_audiencias_pauta` WHERE `id_pauta` = :id AND `ativo` = 1 LIMIT 1');
    $st->bindValue(':id', $idPauta);
    $st->execute();
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row === false || !is_array($row)) {
        $resumoErroPlain(404, 'Audiência não encontrada.');
    }

    // Normalização espelhada do edit screen (designada/redesignada).
    $statusAtual = strtolower(trim((string) ($row['status'] ?? 'designada')));
    if ($statusAtual === 'agendada') {
        $statusAtual = 'designada';
    } elseif ($statusAtual === 'adiada') {
        $statusAtual = 'redesignada';
    } elseif ($statusAtual === 'realizada') {
        $statusAtual = 'finalizada';
    }
    if (!in_array($statusAtual, ['designada', 'redesignada'], true)) {
        $resumoErroPlain(409, 'O resumo só pode ser baixado em audiências com status "Designada" ou "Redesignada".');
    }

    $payload = AudienciaResumoBuilder::buildPayload($pdo, $idPauta);

    $filenameBase = 'resumo-audiencia-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $idPauta) . '-' . date('Ymd-His');

    if ($formato === 'txt') {
        $body = AudienciaResumoBuilder::renderTxt($payload);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.txt"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo $body;
        return;
    }

    // Verifica disponibilidade do Dompdf antes de qualquer header de PDF,
    // para que o errorHandler genérico ainda consiga emitir text/plain.
    if (!PdfStreamHelper::isAvailable()) {
        $resumoErroPlain(500, 'Gerador de PDF não encontrado. Instale Dompdf para habilitar a exportação.');
    }
    $html = AudienciaResumoBuilder::renderHtmlForPdf($payload);
    PdfStreamHelper::streamHtml($filenameBase, $html, [
        'paper' => 'A4',
        'orientation' => 'portrait',
        'isRemoteEnabled' => false,
        'filenameSuffix' => false,
    ]);
} catch (InvalidArgumentException $e) {
    $resumoErroPlain(400, $e->getMessage());
} catch (Throwable $e) {
    error_log('sala-audiencias-pauta/resumo: ' . ApiErrorReporter::genericMessage($e, 'public/api/sala-audiencias-pauta/resumo.php'));
    $resumoErroPlain(500, 'Erro ao gerar resumo da audiência.');
}
