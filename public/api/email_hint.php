<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../app/helpers/Conection.php';

header('Content-Type: application/json; charset=utf-8');

$cpf = isset($_GET['cpf']) ? (string) $_GET['cpf'] : '';
$cpf = Security::normalizeCPF($cpf);

if (!Security::validarCPF($cpf)) {
    echo json_encode(['hint' => null]);
    exit;
}

$cpfFormatado = Security::formatCPF($cpf);

try {
    $pdo = Conection::getInstance()->getConnection();
    $stmt = $pdo->prepare("
        SELECT email
        FROM usuarios
        WHERE cpf = ?
        LIMIT 1
    ");
    $stmt->execute([$cpfFormatado]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('email_hint.php: ' . $e->getMessage());
    echo json_encode(['hint' => null]);
    exit;
}

if (!$row || empty($row['email'])) {
    echo json_encode(['hint' => null]);
    exit;
}

$email = $row['email'];
$at = strpos($email, '@');
if ($at === false) {
    echo json_encode(['hint' => null]);
    exit;
}

$local = substr($email, 0, $at);
$domain = substr($email, $at);
$len = strlen($local);
if ($len <= 2) {
    $masked = str_repeat('*', $len) . $domain;
} else {
    $masked = substr($local, 0, 2) . str_repeat('*', min($len - 2, 5)) . $domain;
}

echo json_encode(['hint' => $masked]);
