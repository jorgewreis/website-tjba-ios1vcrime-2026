<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../app/helpers/Conection.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$nomeCompleto = trim((string) ($payload['nome_completo'] ?? ''));
$cpf          = trim((string) ($payload['cpf'] ?? ''));
$telefone     = trim((string) ($payload['telefone'] ?? ''));
$whatsapp     = !empty($payload['telefone_whatsapp']);
$faculdade    = trim((string) ($payload['faculdade'] ?? ''));
$email        = trim((string) ($payload['email'] ?? ''));
$semestre     = trim((string) ($payload['semestre'] ?? ''));

$cpfNumerico = Security::normalizeCPF($cpf);
if (!Security::validarCPF($cpfNumerico)) {
    echo json_encode(['success' => false, 'message' => 'CPF inválido.']);
    exit;
}
$cpfFormatado = Security::formatCPF($cpfNumerico);

$telefoneNumerico = preg_replace('/\D/', '', $telefone);
if (strlen($telefoneNumerico) < 10) {
    echo json_encode(['success' => false, 'message' => 'Informe o telefone com DDD (mínimo 10 dígitos).']);
    exit;
}

if ($nomeCompleto === '' || strlen($nomeCompleto) < 3) {
    echo json_encode(['success' => false, 'message' => 'Nome completo é obrigatório (mínimo 3 caracteres).']);
    exit;
}

if ($faculdade === '') {
    echo json_encode(['success' => false, 'message' => 'Selecione uma faculdade.']);
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'E-mail inválido.']);
    exit;
}

try {
    $pdo = Conection::getInstance()->getConnection();

    $stmt = $pdo->prepare("
        SELECT id FROM estudantes WHERE cpf = ? LIMIT 1
    ");
    $stmt->execute([$cpfFormatado]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Este CPF já está cadastrado.']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO estudantes (
            nome_completo, cpf, telefone, telefone_whatsapp, faculdade, email, semestre
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $nomeCompleto,
        $cpfFormatado,
        $telefone,
        $whatsapp ? 1 : 0,
        $faculdade,
        $email === '' ? null : $email,
        $semestre === '' ? null : $semestre,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Cadastro realizado com sucesso. Você poderá consultar audiências, atas e pautas conforme orientações do sistema.',
    ]);
} catch (PDOException $e) {
    if ($e->getCode() == '42S02') {
        error_log('Tabela estudantes não existe. Execute o SQL em docs/sql/estudantes.sql');
        echo json_encode(['success' => false, 'message' => 'Sistema em configuração. Tente novamente mais tarde.']);
    } else {
        error_log('cadastro_estudantes API: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar cadastro. Tente novamente.']);
    }
} catch (Throwable $e) {
    error_log('cadastro_estudantes API: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente.']);
}
