<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../app/helpers/BootstrapHelper.php';
require_once __DIR__ . '/../../app/models/Usuario.php';
require_once __DIR__ . '/../../app/services/Guias.php';

header('Content-Type: application/json; charset=utf-8');

if (!Security::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$token = Security::getToken();
$usuario = new Usuario();
$user = ($token !== null && $token !== '') ? $usuario->validarToken($token) : null;
if ($user === null) {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão expirada']);
    exit;
}

$id = trim((string)($_GET['id'] ?? ''));
if ($id === '') {
    echo json_encode(['success' => false, 'message' => 'ID da guia obrigatório (id).']);
    exit;
}

$pdo = getDatabaseConnection();
$service = new Guias($pdo);
$guia = $service->obterGuia($id);
if (!$guia) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Guia não encontrada.']);
    exit;
}

// Gera HTML simples para o PDF (pode ser melhorado com template)
$html = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Guia ' . htmlspecialchars($guia['id_guia']) . '</title>';
$html .= '<style>body{font-family: Arial, sans-serif;font-size:12px;}h1{font-size:16px;}table{width:100%;border-collapse:collapse;}td,th{padding:6px;border:1px solid #ddd;text-align:left;}</style>';
$html .= '</head><body>';
$html .= '<h1>Guia: ' . htmlspecialchars($guia['id_guia']) . '</h1>';
$html .= '<table>';
$html .= '<tr><th>Campo</th><th>Valor</th></tr>';
$html .= '<tr><td>Tipo</td><td>' . htmlspecialchars($guia['tipo']) . '</td></tr>';
$html .= '<tr><td>Processo</td><td>' . htmlspecialchars($guia['processo']) . '</td></tr>';
$html .= '<tr><td>Denunciado</td><td>' . htmlspecialchars($guia['denunciado']) . '</td></tr>';
$html .= '<tr><td>Status</td><td>' . htmlspecialchars($guia['status']) . '</td></tr>';
$html .= '<tr><td>Data Fato</td><td>' . htmlspecialchars($guia['data_fato']) . '</td></tr>';
$html .= '<tr><td>Dias Multa</td><td>' . htmlspecialchars((string)$guia['dias_multa']) . '</td></tr>';
$html .= '<tr><td>Observações</td><td>' . nl2br(htmlspecialchars($guia['observacoes'])) . '</td></tr>';
$html .= '</table>';
$html .= '<p>Emitido em ' . date('Y-m-d H:i:s') . '</p>';
$html .= '</body></html>';

// Tenta usar Dompdf se disponível
$pdfDir = __DIR__ . '/../../storage/documents';
if (!is_dir($pdfDir)) {
    @mkdir($pdfDir, 0750, true);
}
$filename = 'guia_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $guia['id_guia']) . '_' . time() . '.pdf';
$targetPath = $pdfDir . '/' . $filename;

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    if (class_exists('\\Dompdf\\Dompdf')) {
        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        file_put_contents($targetPath, $dompdf->output());
        $url = '/storage/documents/' . $filename;
        echo json_encode(['success' => true, 'url' => $url]);
        exit;
    }
}

// Fallback: tenta usar wkhtmltopdf via comando (se disponível)
$escapedHtmlFile = sys_get_temp_dir() . '/guia_' . bin2hex(random_bytes(6)) . '.html';
file_put_contents($escapedHtmlFile, $html);
$escapedPdf = $targetPath;
$cmd = 'which wkhtmltopdf > /dev/null 2>&1 && wkhtmltopdf ' . escapeshellarg($escapedHtmlFile) . ' ' . escapeshellarg($escapedPdf);
exec($cmd, $output, $ret);
if ($ret === 0 && file_exists($escapedPdf)) {
    unlink($escapedHtmlFile);
    echo json_encode(['success' => true, 'url' => '/storage/documents/' . $filename]);
    exit;
}

// Se chegou aqui, não há gerador disponível
unlink($escapedHtmlFile);
http_response_code(500);
echo json_encode(['success' => false, 'message' => 'Gerador de PDF não encontrado. Instale dompdf via composer: composer require dompdf/dompdf']);
exit;

