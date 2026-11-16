<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['consultaprocessosedit'], true);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/CnjValidator.php';
require_once $basePath . '/app/services/DataJudService.php';
require_once $basePath . '/app/services/ProcessoAssuntoSyncService.php';

$payload = json_decode(file_get_contents('php://input') ?: '');

try {
    $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($usuarioId === '') {
        http_response_code(401);
        echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Uma lista vazia não comprova que o DataJud devolveu um lote completo.
    // Rejeite também entradas parcialmente inválidas antes de qualquer escrita:
    // descartá-las alteraria o conjunto que a sincronização deve preservar.
    if (!is_object($payload) || !isset($payload->numero) || !is_string($payload->numero)
        || !isset($payload->assuntos) || !is_array($payload->assuntos) || $payload->assuntos === []) {
        throw new InvalidArgumentException('Informe o número e uma lista não vazia de assuntos válidos.');
    }
    $numero = $payload->numero;
    $assuntos = [];
    foreach ($payload->assuntos as $item) {
        if (!is_object($item)) {
            throw new InvalidArgumentException('Lista de assuntos inválida.');
        }
        $codigo = null;
        foreach (['codigo', 'codigoAssunto', 'numAssunto'] as $key) {
            if (!property_exists($item, $key) || $item->$key === null || (is_string($item->$key) && trim($item->$key) === '')) continue;
            $codigo = $item->$key;
            break;
        }
        $descricao = '';
        foreach (['descricao', 'descricaoAssunto', 'nome'] as $key) {
            if (!property_exists($item, $key) || $item->$key === null || (is_string($item->$key) && trim($item->$key) === '')) continue;
            $descricao = $item->$key;
            break;
        }
        if ((!is_string($codigo) && !is_int($codigo))
            || !preg_match('/[A-Za-z0-9]/', (string) $codigo)
            || !is_string($descricao)) {
            throw new InvalidArgumentException('Lista de assuntos inválida.');
        }
        $assuntos[] = ['codigo' => (string) $codigo, 'descricao' => $descricao];
    }

    try {
        $digits = CnjValidator::digits20OrThrow($numero);
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // O lote enviado pelo navegador não comprova origem nem completude.
    // Consulte o DataJud no servidor e nunca use o payload do cliente como fonte
    // para reconciliar vínculos persistidos.
    $config = require $basePath . '/public/api/datajud/config.php';
    if (($config['api_key'] ?? '') === '') {
        throw new RuntimeException('Integração DataJud não configurada.');
    }
    $fonte = (new DataJudService($config))->searchProcesso($digits);
    $assuntosFonte = $fonte['assuntos'] ?? null;
    if (($fonte['fonte_confirmada_para_sincronizacao'] ?? false) !== true
        || ($fonte['assuntos_completos'] ?? false) !== true
        || !empty($fonte['sigilo_restrito'])
        || !empty($fonte['indisponivel_na_base_publica'])
        || !is_array($assuntosFonte) || $assuntosFonte === []) {
        http_response_code(422);
        echo json_encode(['error' => 'O DataJud não retornou assuntos completos para sincronização.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $service = new ProcessoAssuntoSyncService(Conection::getInstance()->getConnection());
    $result = $service->syncFromDataJud($digits, $assuntosFonte);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('processos/assunto-sync.php: falha interna.');
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao sincronizar assuntos.'], JSON_UNESCAPED_UNICODE);
}
