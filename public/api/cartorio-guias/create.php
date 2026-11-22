<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['guiascreate'], true);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/GuiasService.php';

$payload = file_get_contents('php://input') ?: '';
$parsed = json_decode($payload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$usuarioCadastroId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
if ($usuarioCadastroId === '') {
    http_response_code(401);
    echo json_encode(['error' => 'Usuário não autenticado.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$parsed['usuario_cadastro_id'] = $usuarioCadastroId;

function assertCnjFormattedValue(array $input, string $key, string $fieldLabel): void
{
    if (!array_key_exists($key, $input)) {
        return;
    }
    $value = trim((string) ($input[$key] ?? ''));
    if ($value === '') {
        return;
    }
    if (!preg_match('/^\d{7}-\d{2}\.\d{4}\.\d\.\d{2}\.\d{4}$/', $value)) {
        throw new \InvalidArgumentException($fieldLabel . ' deve ser enviado no formato CNJ completo (0000000-00.0000.0.00.0000).');
    }
}

try {
    assertCnjFormattedValue($parsed, 'numero_processo', 'Número do processo');
    if (trim((string) ($parsed['numero_processo'] ?? '')) === '') throw new InvalidArgumentException('Número do processo é obrigatório.');
    if (trim((string) ($parsed['parte_id'] ?? $parsed['reu_id'] ?? '')) === ''
        && trim((string) ($parsed['denunciado'] ?? '')) === ''
        && trim((string) ($parsed['cpf'] ?? '')) === '') throw new InvalidArgumentException('Informe ou selecione a pessoa.');
    if (!is_string($parsed['idempotency_key'] ?? null)) throw new InvalidArgumentException('Atualize a página para enviar com segurança.');
    $key = strtolower(trim($parsed['idempotency_key']));
    if (preg_match('/^[a-f0-9]{64}$/D', $key) !== 1) throw new InvalidArgumentException('Atualize a página para enviar com segurança.');
    $parsed['idempotency_key'] = $key;
    $actorFingerprint = $parsed['actor_fingerprint'] ?? null;
    if (!is_string($actorFingerprint) || !hash_equals(hash('sha256', $usuarioCadastroId), $actorFingerprint)) {
        http_response_code(409);
        echo json_encode(['error' => 'A sessão mudou durante o envio. Recarregue a página.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $connection = Conection::getInstance()->getConnection();
    $service = new GuiasService($connection);
    $newId = $service->create($parsed);
    $replayed = $service->wasIdempotencyReplay();
    $body = ['id' => $newId];
    try {
        $guia = $service->findForCreateResponse($newId);
        if ($guia === null && $replayed) {
            http_response_code(410);
            echo json_encode(['error' => 'O envio anterior foi confirmado, mas a guia não está mais disponível.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($guia !== null && isset($guia['valor_multa']) && $guia['valor_multa'] !== '' && $guia['valor_multa'] !== null) {
            $body['valor_multa_calculado'] = 'R$ ' . number_format((float) $guia['valor_multa'], 2, ',', '.');
        }
    } catch (\Throwable $exception) {
        error_log('cartorio-guias/create: complemento indisponível (' . get_class($exception) . ')');
        if ($replayed) {
            // Sem verificar a existência da guia anterior, não informar sucesso
            // nem descartar a chave que permite nova tentativa segura.
            http_response_code(503);
            echo json_encode(['error' => 'Não foi possível verificar o envio anterior. Tente novamente.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        // Nesta requisição a criação já foi confirmada; o complemento é opcional.
    }
    http_response_code(201);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
} catch (\DomainException $exception) {
    http_response_code(409);
    echo json_encode(['error' => 'Este envio já foi usado com outros dados.'], JSON_UNESCAPED_UNICODE);
} catch (\InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $exception) {
    error_log('cartorio-guias/create: erro interno (' . get_class($exception) . ')');
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível criar a guia.'], JSON_UNESCAPED_UNICODE);
}
