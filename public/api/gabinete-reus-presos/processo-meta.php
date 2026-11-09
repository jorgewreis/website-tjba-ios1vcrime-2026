<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['reuspresosedit'], true);


header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/repositories/ProcessosRepository.php';
require_once $basePath . '/app/repositories/ClassesRepository.php';

$connection = Conection::getInstance()->getConnection();
$processosRepo = new ProcessosRepository($connection);
$classesRepo = new ClassesRepository($connection);

$payload = file_get_contents('php://input') ?: '';
$parsed = json_decode($payload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$numero = trim((string) ($parsed['numero_processo'] ?? $parsed['numero'] ?? ''));
$digits = preg_replace('/\D+/', '', $numero);
if (strlen($digits) !== 20) {
    echo json_encode(['found' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $processo = $processosRepo->findByNumero($digits);
    if ($processo === null) {
        echo json_encode(['found' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $classeCodigo = '';
    $classeDescricao = '';
    $classeId = trim((string) ($processo['classe_id'] ?? ''));
    if ($classeId !== '') {
        $classe = $classesRepo->findById($classeId);
        if ($classe !== null) {
            $classeCodigo = trim((string) ($classe['codigo'] ?? ''));
            $classeDescricao = trim((string) ($classe['nome'] ?? ''));
        }
    }

    $st = $connection->prepare(
        'SELECT gpp.`situacao`, gpp.`data_prisao`, gpp.`data_fato`
         FROM `gabinete_reu_preso_processo` gpp
         INNER JOIN `gabinete_reu_preso` grp ON grp.`id` = gpp.`reu_preso_id`
         WHERE gpp.`processo_id` = :pid
           AND grp.`controle_tipo` = :controle_tipo
         ORDER BY gpp.`updated_at` DESC, gpp.`created_at` DESC
         LIMIT 1'
    );
    $st->bindValue(':pid', (string) $processo['id']);
    $st->bindValue(':controle_tipo', (($_GET['contexto'] ?? '') === 'substituto') ? 'substituto' : 'principal');
    $st->execute();
    $gpp = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    $dataFato = trim((string) ($processo['data_fato'] ?? ''));
    if ($dataFato === '' && $gpp !== null) {
        $dataFato = trim((string) ($gpp['data_fato'] ?? ''));
    }

    echo json_encode([
        'found' => true,
        'processo_id' => (string) ($processo['id'] ?? ''),
        'classe_codigo' => $classeCodigo,
        'classe_descricao' => $classeDescricao,
        'situacao' => trim((string) ($gpp['situacao'] ?? '')),
        'data_prisao' => trim((string) ($gpp['data_prisao'] ?? '')),
        'data_fato' => $dataFato,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao carregar metadados do processo.'], JSON_UNESCAPED_UNICODE);
}
