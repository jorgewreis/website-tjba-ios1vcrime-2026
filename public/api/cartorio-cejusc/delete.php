<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/cartorio-cejusc/delete.php');
$basePath = dirname(__DIR__, 3);
require_once $basePath . '/config/security.php';
require_once $basePath . '/app/access/AccessEvaluator.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/ApiJsonResponse.php';
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/DeleteAuthorization.php';
require_once $basePath . '/app/services/CejuscRegistrosService.php';
if (!Security::isAuthenticated() || !bootstrapRequireTrustedPostRequest()) { ApiJsonResponse::send(['error' => 'Requisição inválida.'], 403); exit; }
$userId = trim((string) ($_SESSION['id_usuario'] ?? ''));
$perfisUsuario = UsuarioPerfisAutorizacao::perfisDoUsuario($userId);
if (!AccessEvaluator::canAccessPage('cejuscedit', $perfisUsuario)) { ApiJsonResponse::send(['error' => 'Acesso negado.'], 403); exit; }
if (!DeleteAuthorization::perfisIncluemServidorOuChefia($perfisUsuario)) { ApiJsonResponse::send(['error' => 'Seu perfil não tem permissão para remover registro CEJUSC.'], 403); exit; }
try { $payload = ApiJsonResponse::readJsonPayload(); $id = trim((string) ($payload['id'] ?? '')); $deleted = (new CejuscRegistrosService(Conection::getInstance()->getConnection()))->delete($id); ApiJsonResponse::send(['deleted' => $deleted]); }
catch (InvalidArgumentException $e) { ApiJsonResponse::send(['error' => $e->getMessage()], 400); }
catch (Throwable $e) {
    ApiErrorReporter::logUnexpected($e, 'public/api/cartorio-cejusc/delete.php'); ApiJsonResponse::send(['error' => 'Erro ao excluir registro CEJUSC.'], 500); }
