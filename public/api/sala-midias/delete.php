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
    $perfis = UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioId);
    if (!AccessEvaluator::canAccessPage('salamidiasedit', $perfis)) {
        salaMidiasJson(['error' => 'Acesso negado.'], 403);
        exit;
    }

    /** Exclusão restrita a admin ou chefia. */
    $perfisPermitidosExcluir = ['ADMIN', 'CHEFIA'];
    if (count(array_intersect($perfis, $perfisPermitidosExcluir)) === 0) {
        salaMidiasJson(['error' => 'Seu perfil não tem permissão para excluir. Apenas admin ou chefia.'], 403);
        exit;
    }

    $payload = salaMidiasReadPayload();
    $id = trim((string) ($payload['id'] ?? ''));
    if ($id === '') {
        salaMidiasJson(['error' => 'ID é obrigatório.'], 400);
        exit;
    }

    $service = new SalaMidiasService(Conection::getInstance()->getConnection());
    $resultado = $service->delete($id, $usuarioId);
    salaMidiasJson($resultado);
} catch (InvalidArgumentException $e) {
    salaMidiasJson(['error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    salaMidiasJson(['error' => 'Erro ao excluir tarefa de mídia: ' . $e->getMessage()], 500);
}
