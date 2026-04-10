<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../../helpers/AlocacaoRegrasUsuariosHelper.php';
require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';

function resolveUsuarioNomeSalaMidias(PDO $connection, ?string $usuarioId): string
{
    $usuarioId = trim((string) ($usuarioId ?? ''));
    if ($usuarioId === '') {
        return 'Nenhum usuário definido';
    }
    $stmt = $connection->prepare('SELECT `nome`,`sobrenome` FROM `usuarios` WHERE `id_usuario` = :id LIMIT 1');
    $stmt->bindValue(':id', $usuarioId);
    $stmt->execute();
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($u === false) {
        return 'Usuário não encontrado';
    }
    $nome = trim((string) ($u['nome'] ?? ''));
    $sobrenome = trim((string) ($u['sobrenome'] ?? ''));

    return trim($nome . ' ' . $sobrenome);
}

$connection = Conection::getInstance()->getConnection();
$userId = trim((string) ($_SESSION['id_usuario'] ?? ''));
$userPerfis = $userId !== '' ? UsuarioPerfisAutorizacao::perfisDoUsuario($userId) : [];
$podeAlterarRegra = count(array_intersect($userPerfis, ['ADMIN', 'DIRETORA', 'MAGISTRADA', 'CHEFIA'])) > 0;

$ruleName = 'ALLOCATE-MIDIAS';
$ruleDescription = 'Alocação automática - Sala de Audiências - Mídias';
$errors = [];
$successMessage = '';
$selectedUserId = '';
$usersList = [];
$ruleUsuarioId = '';
$ruleUsuarioNome = 'Nenhum usuário definido';
$idsInternosElegiveis = [];

$nomeCurtoModulo = 'Mídias';
$locationAll = 'Interno - Sala de Audiências - Mídias -';
$description = 'Defina o servidor responsável pela alocação automática das tarefas de mídia.';
$voltarPage = 'salamidias';

try {
    $usersList = AlocacaoRegrasUsuariosHelper::listUsuariosInternos($connection);
    $idsInternosElegiveis = array_column($usersList, 'id');
    $repo = new AlocacoesRegrasRepository($connection);
    $row = $repo->findByRegra($ruleName);
    $selectedUserId = trim((string) ($row['usuario_id'] ?? ''));
    $ruleUsuarioId = $selectedUserId;
    $ruleUsuarioNome = resolveUsuarioNomeSalaMidias($connection, $selectedUserId);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string) ($_POST['action'] ?? 'save'));
        if (!$podeAlterarRegra) {
            $errors[] = 'Seu perfil não tem permissão para alterar esta regra.';
        } elseif ($action === 'clear') {
            $repo->clearAllocation($ruleName, $userId !== '' ? $userId : null);
            $selectedUserId = '';
            $ruleUsuarioId = '';
            $ruleUsuarioNome = resolveUsuarioNomeSalaMidias($connection, null);
            $successMessage = 'Alocação removida com sucesso.';
        } else {
            $postedUserId = trim((string) ($_POST['id_user'] ?? ''));
            if ($postedUserId === '') {
                $errors[] = 'Selecione um servidor antes de salvar.';
            } elseif (!AlocacaoRegrasUsuariosHelper::isUsuarioElegivelParaRegraInterna($connection, $postedUserId)) {
                $errors[] = 'Usuário selecionado não é elegível.';
            } else {
                $saved = $repo->save($ruleName, $ruleDescription, $postedUserId, $userId !== '' ? $userId : null);
                $selectedUserId = trim((string) ($saved['usuario_id'] ?? $postedUserId));
                $ruleUsuarioId = $selectedUserId;
                $ruleUsuarioNome = resolveUsuarioNomeSalaMidias($connection, $selectedUserId);
                $successMessage = 'Regra atualizada com sucesso.';
            }
        }
    }
} catch (Throwable $e) {
    $errors[] = 'Erro ao carregar regra: ' . $e->getMessage();
}

$locationSpecificPrefix = $nomeCurtoModulo . ' - Regras';
$resumoRowClass = (trim($ruleUsuarioId) !== '' && in_array($ruleUsuarioId, $idsInternosElegiveis, true)) ? 'success' : 'info';

include __DIR__ . '/_cartorio_rules_head.php';
?>
<div class="container-full card-page">
    <form method="post" class="rule-form" style="width:100%">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Regras de alocação automática</h2>
        </div>

        <div class="display-flex row row-full filters justify-between padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label for="filter-servidor-alocacao">Servidor</label>
                <select class="width-600 height-md" id="filter-servidor-alocacao" name="id_user" data-original-user-name="<?php echo htmlspecialchars($ruleUsuarioNome, ENT_QUOTES, 'UTF-8'); ?>" data-original-user-id="<?php echo htmlspecialchars($selectedUserId, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $podeAlterarRegra ? '' : 'disabled'; ?>>
                    <option value="" disabled<?php echo $selectedUserId === '' ? ' selected' : ''; ?>>Selecione aqui</option>
                    <?php foreach ($usersList as $user): ?>
                        <option value="<?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?>"<?php echo $selectedUserId === $user['id'] ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php include __DIR__ . '/_cartorio_rules_summary_row.php'; ?>

        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
            <?php if ($podeAlterarRegra): ?>
                <button type="submit" class="btn btn-danger-outline" id="remover-alocacao-btn" name="action" value="clear" formnovalidate disabled>Remover alocação</button>
                <button type="submit" class="btn btn-primary" id="salvar-btn" name="action" value="save" disabled>Salvar</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php include __DIR__ . '/_cartorio_rules_scripts.php'; ?>
</body>
</html>
