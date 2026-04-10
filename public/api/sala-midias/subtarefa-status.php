<?php
declare(strict_types=1);

require __DIR__ . '/_common.php';
require_once dirname(__DIR__, 3) . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once dirname(__DIR__, 3) . '/app/access/AccessEvaluator.php';

try {
    $usuarioId = salaMidiasCurrentUserId();
    if ($usuarioId === '') {
        salaMidiasJson(['error' => 'Sessão expirada.'], 401);
        exit;
    }
    if (!AccessEvaluator::canAccessPage('salamidiasedit', UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioId))) {
        salaMidiasJson(['error' => 'Acesso negado.'], 403);
        exit;
    }

    $payload = salaMidiasReadPayload();
    $subtarefaId = trim((string) ($payload['subtarefa_id'] ?? ''));
    $status = trim((string) ($payload['status'] ?? ''));
    if ($subtarefaId === '' || $status === '') {
        salaMidiasJson(['error' => 'Subtarefa e status são obrigatórios.'], 400);
        exit;
    }
    if ($status !== 'concluida') {
        salaMidiasJson(['error' => 'Apenas conclusão de subtarefa é suportada nesta etapa.'], 400);
        exit;
    }

    $dados = isset($payload['dados']) && is_array($payload['dados']) ? $payload['dados'] : [];
    $service = new SalaMidiasService(Conection::getInstance()->getConnection());
    $service->concluirSubtarefa($subtarefaId, $usuarioId, $dados);
    salaMidiasJson(['success' => true]);
} catch (InvalidArgumentException $e) {
    salaMidiasJson(['error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    salaMidiasJson(['error' => 'Erro ao concluir subtarefa: ' . $e->getMessage()], 500);
}
