<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../app/helpers/BootstrapHelper.php';

header('Content-Type: application/json; charset=utf-8');

if (!Security::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$maxSize = 10 * 1024 * 1024; // 10 MB
$allowedTypes = [
    'application/pdf',
    'image/png',
    'image/jpeg',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/msword'
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'message' => 'Arquivo não enviado']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Erro no upload']);
    exit;
}

if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'Arquivo muito grande']);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
if (!in_array($mime, $allowedTypes, true)) {
    echo json_encode(['success' => false, 'message' => 'Tipo de arquivo não permitido']);
    exit;
}

$storageDir = __DIR__ . '/../../storage/documents';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0750, true);
}

$basename = bin2hex(random_bytes(12));
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$target = $storageDir . '/' . $basename . '.' . $ext;
if (!move_uploaded_file($file['tmp_name'], $target)) {
    echo json_encode(['success' => false, 'message' => 'Falha ao salvar arquivo']);
    exit;
}

// opcional: gravar metadados no banco (se houver tabela)
$pdo = getDatabaseConnection();
try {
    $id = bin2hex(random_bytes(8));
    $title = trim((string)($_POST['title'] ?? ''));
    $stmt = $pdo->prepare("INSERT INTO documentos (id_documento, titulo, caminho, mime, tamanho, user_cadastro, data_cadastro) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $userId = Security::getUserId() ?? 'sistema';
    $stmt->execute([$id, $title, $target, $mime, (int)$file['size'], $userId]);
} catch (Throwable $e) {
    // Se a tabela não existir ou erro, apenas logar e prosseguir
    error_log('documents API: ' . $e->getMessage());
}

echo json_encode(['success' => true, 'message' => 'Arquivo enviado com sucesso']);
exit;

