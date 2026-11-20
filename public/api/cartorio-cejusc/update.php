<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
$basePath = dirname(__DIR__, 3);
require_once $basePath . '/config/security.php';
require_once $basePath . '/app/access/AccessEvaluator.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/ApiJsonResponse.php';
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/services/CejuscRegistrosService.php';
if (!Security::isAuthenticated() || !bootstrapRequireTrustedPostRequest()) { ApiJsonResponse::send(['error' => 'Requisição inválida.'], 403); exit; }
if (!AccessEvaluator::canAccessPage('cejuscedit', UsuarioPerfisAutorizacao::perfisDoUsuario(trim((string) ($_SESSION['id_usuario'] ?? ''))))) { ApiJsonResponse::send(['error' => 'Acesso negado.'], 403); exit; }
try { $payload = ApiJsonResponse::readJsonPayload(); $id = trim((string) ($payload['id'] ?? '')); unset($payload['id']); $ok = (new CejuscRegistrosService(Conection::getInstance()->getConnection()))->update($id, $payload, trim((string) ($_SESSION['id_usuario'] ?? ''))); ApiJsonResponse::send(['success' => $ok]); }
catch (InvalidArgumentException $e) { ApiJsonResponse::send(['error' => $e->getMessage()], 400); }
catch (Throwable $e) { ApiJsonResponse::send(['error' => 'Erro ao atualizar registro CEJUSC.'], 500); }
