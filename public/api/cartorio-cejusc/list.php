<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
$basePath = dirname(__DIR__, 3);
require_once $basePath . '/config/security.php';
require_once $basePath . '/app/access/AccessEvaluator.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/services/CejuscRegistrosService.php';
if (!Security::isAuthenticated()) { http_response_code(401); echo json_encode(['error' => 'Sessão expirada.'], JSON_UNESCAPED_UNICODE); exit; }
$userId = trim((string) ($_SESSION['id_usuario'] ?? ''));
if (!AccessEvaluator::canAccessPage('cejusc', UsuarioPerfisAutorizacao::perfisDoUsuario($userId))) { http_response_code(403); echo json_encode(['error' => 'Acesso negado.'], JSON_UNESCAPED_UNICODE); exit; }
$perPage = 15;
if (!in_array($perPage, [15], true)) $perPage = 15;
$page = max(1, (int) ($_GET['page_number'] ?? 1));
$filters = ['q' => trim((string) ($_GET['q'] ?? '')), 'mes_envio' => trim((string) ($_GET['mes_envio'] ?? ''))];
try {
    $result = (new CejuscRegistrosService(Conection::getInstance()->getConnection()))->list($filters, $perPage, ($page - 1) * $perPage);
    $total = (int) $result['total'];
    echo json_encode(['data' => $result['rows'], 'pagination' => ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'total_pages' => $total > 0 ? (int) ceil($total / $perPage) : 0], 'filters' => $filters], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { http_response_code(500); echo json_encode(['error' => 'Erro ao buscar registros CEJUSC.'], JSON_UNESCAPED_UNICODE); }
