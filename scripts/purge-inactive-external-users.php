<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../app/helpers/Conection.php';
require_once __DIR__ . '/../app/services/ExternalUsersCleanupService.php';

try {
    $service = new ExternalUsersCleanupService(Conection::getInstance()->getConnection());
    $result = $service->purgeInactiveStudents();
    echo json_encode(['success' => $result['failed_users'] === 0] + $result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit($result['failed_users'] === 0 ? 0 : 1);
} catch (Throwable $exception) {
    error_log('Falha na rotina de exclusão de usuários externos inativos: ' . $exception->getMessage());
    fwrite(STDERR, "Falha na rotina de exclusão de usuários externos inativos.\n");
    exit(1);
}
