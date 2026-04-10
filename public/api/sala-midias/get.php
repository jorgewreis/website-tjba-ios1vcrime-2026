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
    if (!AccessEvaluator::canAccessPage('salamidias', UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioId))) {
        salaMidiasJson(['error' => 'Acesso negado.'], 403);
        exit;
    }

    $id = trim((string) ($_GET['id'] ?? ''));
    if ($id === '') {
        salaMidiasJson(['error' => 'ID é obrigatório.'], 400);
        exit;
    }

    $service = new SalaMidiasService(Conection::getInstance()->getConnection());
    $row = $service->find($id);
    if ($row === null) {
        salaMidiasJson(['error' => 'Registro não encontrado.'], 404);
        exit;
    }
    salaMidiasJson(['row' => $row]);
} catch (InvalidArgumentException $e) {
    salaMidiasJson(['error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    salaMidiasJson(['error' => 'Erro ao carregar registro: ' . $e->getMessage()], 500);
}
