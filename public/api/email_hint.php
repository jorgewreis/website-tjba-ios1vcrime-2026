<?php
declare(strict_types=1);


require_once dirname(__DIR__, 2) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/email_hint.php');
header('Content-Type: application/json; charset=utf-8');
http_response_code(404);
echo json_encode(['message' => 'Recurso não encontrado.']);
