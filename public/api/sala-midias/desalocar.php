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
    $alocadoId = trim((string) ($payload['alocado_id'] ?? ''));
    if ($alocadoId === '') {
        salaMidiasJson(['error' => 'Alocação não informada.'], 400);
        exit;
    }
    $service = new SalaMidiasService(Conection::getInstance()->getConnection());
    salaMidiasJson(['deleted' => $service->removeAlocado($alocadoId)]);
} catch (InvalidArgumentException $e) {
    salaMidiasJson(['error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    salaMidiasJson(['error' => 'Erro ao remover alocação: ' . $e->getMessage()], 500);
}
