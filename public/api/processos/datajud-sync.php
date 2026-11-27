<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/processos/datajud-sync.php');
header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/CnjValidator.php';
require_once $basePath . '/app/helpers/ProcessoSetorAuthorization.php';
require_once $basePath . '/app/repositories/ProcessosRepository.php';
require_once $basePath . '/app/services/DataJudService.php';
require_once $basePath . '/app/services/ProcessoDataJudSyncService.php';

ApiAccessGuard::requireAccess(['consultaprocessos'], true);

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$numero = is_string($payload['numero'] ?? null) ? $payload['numero'] : '';
$fromDetails = ($payload['origem'] ?? null) === 'ficha';
$expectedId = is_string($payload['processo_id'] ?? null) ? trim($payload['processo_id']) : '';
$escritaIniciada = false;
$autorizacaoSetorialNegada = false;
$fonteDataJudValidada = false;

try {
    $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($usuarioId === '') {
        http_response_code(401);
        echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $digits = CnjValidator::digits20OrThrow($numero);
    $config = require $basePath . '/public/api/datajud/config.php';
    if (($config['api_key'] ?? '') === '') {
        throw new RuntimeException('Integração DataJud não configurada.');
    }

    $connection = Conection::getInstance()->getConnection();
    $processo = (new ProcessosRepository($connection))->findByNumero($digits);
    if ($processo === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Processo não encontrado no cadastro local.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $processoId = trim((string) ($processo['id'] ?? ''));
    if ($processoId === '' || !ProcessoSetorAuthorization::canAccessProcess($connection, $usuarioId, $processoId, false)) {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso ao processo não autorizado.', 'code' => 'acesso_setorial_obrigatorio'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($fromDetails && ($expectedId === '' || !hash_equals($processoId, $expectedId))) {
        http_response_code(409);
        echo json_encode(['error' => 'A ficha mudou. Recarregue a página antes de sincronizar.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!empty($processo['sigiloso'])) {
        throw new DataJudLocalSigiloException();
    }
    $resultadoBusca = (new DataJudService($config))->searchProcesso($digits, true);
    if (($resultadoBusca['resultado_unico_verificado'] ?? null) !== true
        || ($resultadoBusca['sigilo_ausencia_verificada'] ?? null) !== true
        || ($resultadoBusca['fonte_confirmada_para_sincronizacao'] ?? null) !== true) {
        http_response_code(422);
        echo json_encode(['error' => 'O DataJud não retornou resultado único e verificável para sincronização.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!empty($resultadoBusca['sigilo_restrito'])) {
        http_response_code(422);
        echo json_encode(['error' => 'O DataJud indica sigilo incompatível com esta sincronização.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $estadoAssuntos = $resultadoBusca['assuntos_estado'] ?? null;
    if (!in_array($estadoAssuntos, ['vazio', 'presente'], true)
        || !is_array($resultadoBusca['assuntos'] ?? null)
        || !array_is_list($resultadoBusca['assuntos'])
        || ($estadoAssuntos === 'vazio' && $resultadoBusca['assuntos'] !== [])
        || ($estadoAssuntos === 'presente' && $resultadoBusca['assuntos'] === [])) {
        http_response_code(422);
        echo json_encode(['error' => 'O DataJud não retornou assuntos completos e verificáveis para sincronização.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $classe = $resultadoBusca['classe'] ?? [];
    if (!is_array($classe) || ($classe !== [] && array_is_list($classe))) {
        http_response_code(422);
        echo json_encode(['error' => 'O DataJud retornou classe inválida para sincronização.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // O mapper representa classe ausente com as duas chaves nulas.
    if ($classe !== [] && array_key_exists('codigo', $classe) && array_key_exists('descricao', $classe)
        && $classe['codigo'] === null && $classe['descricao'] === null) {
        $classe = [];
    }
    $assuntos = is_array($resultadoBusca['assuntos'] ?? null) ? $resultadoBusca['assuntos'] : [];
    if ($assuntos !== [] && ($resultadoBusca['assuntos_completos'] ?? null) !== true) {
        http_response_code(422);
        echo json_encode(['error' => 'O DataJud retornou assuntos incompletos; nenhum dado foi sincronizado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $codigoFonte = $classe['codigo'] ?? null;
    $descricaoFonte = $classe['descricao'] ?? null;
    if ($classe !== [] && (!(is_int($codigoFonte) || is_string($codigoFonte))
        || !preg_match('/^[0-9]+$/D', (string) $codigoFonte)
        || (int) $codigoFonte <= 0 || !is_string($descricaoFonte) || trim($descricaoFonte) === '')) {
        http_response_code(422);
        echo json_encode(['error' => 'O DataJud retornou classe inválida para sincronização.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $classeCodigo = $classe === [] ? '' : (string) $codigoFonte;
    $classeDescricao = $classe === [] ? '' : trim($descricaoFonte);
    $classeResultado = null;
    $assuntosResultado = null;

    if ($classeCodigo === '' && $assuntos === []) {
        http_response_code(422);
        echo json_encode([
            'error' => 'O DataJud não retornou classe nem assuntos sincronizáveis para este processo.',
            'sigilo_restrito' => !empty($resultadoBusca['sigilo_restrito']),
            'indisponivel_na_base_publica' => !empty($resultadoBusca['indisponivel_na_base_publica']),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $fonteDataJudValidada = true;
    $beforeWrite = static function (PDO $writeConnection) use (
        $digits,
        $processoId,
        $usuarioId,
        &$autorizacaoSetorialNegada,
        &$escritaIniciada
    ): bool {
        $lockSql = 'SELECT `numero_processo` FROM `processos` WHERE `id` = :processo_id LIMIT 1';
        if ($writeConnection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $lockSql .= ' FOR UPDATE';
        }
        $lock = $writeConnection->prepare($lockSql);
        $lock->execute([':processo_id' => $processoId]);
        $numeroAtual = $lock->fetchColumn();
        $lock->closeCursor();
        $digitosAtuais = $numeroAtual === false ? '' : preg_replace('/\D+/', '', (string) $numeroAtual);
        if (!is_string($digitosAtuais) || !hash_equals($digits, $digitosAtuais)
            || !ProcessoSetorAuthorization::canModifyProcess($writeConnection, $usuarioId, $processoId)) {
            $autorizacaoSetorialNegada = true;
            return false;
        }

        $escritaIniciada = true;
        return true;
    };
    $syncService = $fromDetails
        ? new ProcessoDataJudSyncService($connection, onlyChangedFields: true)
        : new ProcessoDataJudSyncService($connection);
    $resultadoSync = $syncService->sync(
        $digits,
        $classeCodigo === '' ? null : ['codigo' => $classeCodigo, 'descricao' => $classeDescricao],
        $fromDetails && $estadoAssuntos === 'vazio' ? null : $assuntos,
        !$fromDetails && $estadoAssuntos === 'vazio',
        $beforeWrite
    );
    $classeResultado = $resultadoSync['classe'];
    $assuntosResultado = $resultadoSync['assuntos'];

    $mensagens = [];
    if ($classeResultado !== null) {
        $acaoClasse = trim((string) ($classeResultado['action'] ?? ''));
        if ($acaoClasse === 'created') {
            $mensagens[] = 'Classe do processo cadastrada a partir do DataJud.';
        } elseif ($acaoClasse === 'updated') {
            $mensagens[] = 'Classe do processo atualizada a partir do DataJud.';
        } elseif ($acaoClasse === 'noop') {
            $mensagens[] = 'Classe do processo já estava sincronizada.';
        }
    }
    if ($assuntosResultado !== null) {
        $acaoAssuntos = trim((string) ($assuntosResultado['action'] ?? ''));
        if ($acaoAssuntos === 'created') {
            $mensagens[] = 'Assuntos do processo cadastrados a partir do DataJud.';
        } elseif ($acaoAssuntos === 'updated') {
            $mensagens[] = 'Assuntos do processo atualizados a partir do DataJud.';
        } elseif ($acaoAssuntos === 'noop') {
            $mensagens[] = 'Assuntos do processo já estavam sincronizados.';
        }
    }

    echo json_encode([
        'success' => true,
        'message' => implode(' ', $mensagens),
        'classe' => $classeResultado,
        'assuntos' => $assuntosResultado,
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    if ($autorizacaoSetorialNegada) {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso ao processo não autorizado.', 'code' => 'acesso_setorial_obrigatorio'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($escritaIniciada) {
        http_response_code(409);
        echo json_encode(['error' => 'Não foi possível confirmar a sincronização. Confira os dados antes de tentar novamente.', 'code' => 'sincronizacao_resultado_indeterminado'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (DataJudAmbiguousResponseException $e) {
    http_response_code(422);
    echo json_encode(['error' => 'O DataJud retornou mais de um registro; nenhum dado foi sincronizado.'], JSON_UNESCAPED_UNICODE);
} catch (ProcessoClasseOrigemIndisponivelException $e) {
    http_response_code(503);
    echo json_encode(['error' => $e->getMessage(), 'code' => 'classe_origem_indisponivel'], JSON_UNESCAPED_UNICODE);
} catch (DataJudUpstreamException|DataJudLocalSigiloException $e) {
    $e->respondForSync();
} catch (RuntimeException $e) {
    http_response_code(502);
    echo json_encode(['error' => ApiErrorReporter::genericMessage($e, 'public/api/processos/datajud-sync.php')], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($autorizacaoSetorialNegada) {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso ao processo não autorizado.', 'code' => 'acesso_setorial_obrigatorio'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($escritaIniciada) {
        http_response_code(409);
        echo json_encode(['error' => 'Não foi possível confirmar a sincronização. Confira os dados antes de tentar novamente.', 'code' => 'sincronizacao_resultado_indeterminado'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao sincronizar dados do processo: ' . ApiErrorReporter::genericMessage($e, 'public/api/processos/datajud-sync.php')], JSON_UNESCAPED_UNICODE);
}
