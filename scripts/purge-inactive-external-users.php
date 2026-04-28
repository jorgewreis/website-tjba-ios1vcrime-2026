<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/Conection.php';
require_once __DIR__ . '/../app/services/ExternalUsersCleanupService.php';

$daysWithoutAccess = 30;
if (isset($argv[1]) && is_numeric($argv[1])) {
    $daysWithoutAccess = max(1, (int) $argv[1]);
}

$connection = Conection::getInstance()->getConnection();
$service = new ExternalUsersCleanupService($connection);
$result = $service->purgeInactiveStudents($daysWithoutAccess);

echo json_encode([
    'success' => true,
    'days_without_access' => $daysWithoutAccess,
    'deleted_users' => $result['deleted_users'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
