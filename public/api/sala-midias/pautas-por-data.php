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
    if (!AccessEvaluator::canAccessPage('salamidiascreate', UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioId))) {
        salaMidiasJson(['error' => 'Acesso negado.'], 403);
        exit;
    }

    $dataAudiencia = trim((string) ($_GET['data_audiencia'] ?? ''));
    $numeroProcesso = trim((string) ($_GET['numero_processo'] ?? ''));
    if ($dataAudiencia === '') {
        salaMidiasJson(['error' => 'Data da audiência é obrigatória.'], 400);
        exit;
    }

    $service = new SalaMidiasService(Conection::getInstance()->getConnection());
    $rows = $service->listPautasConcluidasPorData($dataAudiencia, $numeroProcesso);
    salaMidiasJson(['rows' => $rows]);
} catch (InvalidArgumentException $e) {
    salaMidiasJson(['error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    salaMidiasJson(['error' => 'Erro ao carregar audiências por data: ' . $e->getMessage()], 500);
}
