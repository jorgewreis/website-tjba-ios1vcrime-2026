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
if (!AccessEvaluator::canAccessPage('cejusccreate', UsuarioPerfisAutorizacao::perfisDoUsuario(trim((string) ($_SESSION['id_usuario'] ?? ''))))) { ApiJsonResponse::send(['error' => 'Acesso negado.'], 403); exit; }
try { $payload = ApiJsonResponse::readJsonPayload(); $id = (new CejuscRegistrosService(Conection::getInstance()->getConnection()))->create($payload, trim((string) ($_SESSION['id_usuario'] ?? ''))); ApiJsonResponse::send(['id' => $id], 201); }
catch (CejuscRegistroDuplicadoException $e) { ApiJsonResponse::send(['error' => $e->getMessage(), 'requires_confirmation' => true, 'duplicate' => $e->duplicate()], 409); }
catch (InvalidArgumentException $e) { ApiJsonResponse::send(['error' => $e->getMessage()], 400); }
catch (Throwable $e) { ApiJsonResponse::send(['error' => 'Erro ao criar registro CEJUSC.'], 500); }
