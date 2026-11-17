<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/cartorio-cartas-recebidas/avancar.php');
require_once dirname(__DIR__, 3) . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['cartasrecebidasedit'], true);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/CartorioSubtarefaUsuarioSessao.php';
require_once $basePath . '/app/repositories/CartasRecebidasSubtarefasRepository.php';
require_once $basePath . '/app/services/CartasRecebidasService.php';
require_once $basePath . '/app/services/DashboardResumoService.php';

$connection = Conection::getInstance()->getConnection();
$rawPayload = file_get_contents('php://input') ?: '';
$parsed = json_decode($rawPayload, true);
if (!is_array($parsed)) $parsed = $_POST;
$cartaId = trim((string) ($parsed['carta_id'] ?? ''));
if ($cartaId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador da carta é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $repo = new CartasRecebidasSubtarefasRepository($connection);
    $ativa = $repo->findPendenteOuEmAndamentoByCartaId($cartaId)[0] ?? null;
    if (!is_array($ativa) || trim((string) ($ativa['id'] ?? '')) === '') {
        throw new InvalidArgumentException('Não há movimentação pendente para esta carta.');
    }
    $subtarefaId = trim((string) $ativa['id']);
    $before = DashboardResumoService::statusAnterior($connection, 'cartas_recebidas_subtarefas', $subtarefaId);
    $dados = CartorioSubtarefaUsuarioSessao::mergeDadosEspecificosComSessao($connection, null);
    $resultado = (new CartasRecebidasService($connection))->concluirSubtarefa($subtarefaId, $dados);
    DashboardResumoService::registrarConclusao($connection, $before, 'cartas_recebidas', 'CARTORIO', trim((string) ($_SESSION['id_usuario'] ?? '')));
    echo json_encode(array_merge(['success' => true], is_array($resultado) ? $resultado : []), JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao movimentar carta recebida.'], JSON_UNESCAPED_UNICODE);
}
