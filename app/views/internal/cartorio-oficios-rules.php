<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../../helpers/AlocacaoRegrasUsuariosHelper.php';

function resolveUsuarioNomeOficios(PDO $connection, ?string $usuarioId): string
{
    $usuarioId = trim((string) ($usuarioId ?? ''));
    if ($usuarioId === '') {
        return 'Nenhum usuário definido';
    }

    $stmt = $connection->prepare('SELECT `nome`, `sobrenome` FROM `usuarios` WHERE `id_usuario` = :usuario_id LIMIT 1');
    $stmt->bindValue(':usuario_id', $usuarioId);
    $stmt->execute();

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($usuario === false) {
        return 'Usuário não encontrado';
    }

    $nome      = trim((string) ($usuario['nome'] ?? ''));
    $sobrenome = trim((string) ($usuario['sobrenome'] ?? ''));
    $nomeCompleto = $sobrenome !== '' ? $nome . ' ' . $sobrenome : $nome;

    return $nomeCompleto !== '' ? $nomeCompleto : 'Nome não informado';
}

$connection = Conection::getInstance()->getConnection();

$perfisAlterarRegra = ['ADMIN', 'DIRETORA', 'MAGISTRADA'];

require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
$userPerfis = [];
$userId     = $_SESSION['id_usuario'] ?? null;
if ($userId !== null && trim((string) $userId) !== '') {
    $userPerfis = UsuarioPerfisAutorizacao::perfisDoUsuario(trim((string) $userId));
}
$podeAlterarRegra = count(array_intersect($userPerfis, $perfisAlterarRegra)) > 0;

$ruleName        = 'ALLOCATE-CARTORIOOFICIOS';
$ruleDescription = 'Alocação automática - Ofícios (Cartório)';
$errors          = [];
$successMessage  = '';
$usersList       = [];
$selectedUserId  = '';
$ruleRow         = null;
$ruleUsuarioId   = '';
$ruleUsuarioNome = 'Nenhum usuário definido';
$idsInternosElegiveis = [];

$nomeCurtoModulo = 'Ofícios (Cartório)';
$locationAll     = 'Interno - Cartório - Ofícios -';
$description     = 'Defina o servidor responsável pela alocação automática dos ofícios do cartório.';
$voltarPage      = 'oficioscartorio';

try {
    $usersList = AlocacaoRegrasUsuariosHelper::listUsuariosInternos($connection);
    if ($usersList === []) {
        $errors[] = 'Nenhum usuário interno elegível foi encontrado.';
    }
    $idsInternosElegiveis = array_column($usersList, 'id');

    $regrasRepo      = new AlocacoesRegrasRepository($connection);
    $ruleRow         = $regrasRepo->findByRegra($ruleName);
    $ruleUsuarioId   = (string) ($ruleRow['usuario_id'] ?? '');
    $ruleUsuarioNome = resolveUsuarioNomeOficios($connection, $ruleUsuarioId);
    $selectedUserId  = trim((string) ($ruleRow['usuario_id'] ?? ''));
    if ($selectedUserId !== '') {
        $idsInList = array_column($usersList, 'id');
        if (!in_array($selectedUserId, $idsInList, true)) {
            $usersList[] = ['id' => $selectedUserId, 'name' => $ruleUsuarioNome . ' (fora da lista atual)'];
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string) ($_POST['action'] ?? 'save'));
        if (!$podeAlterarRegra) {
            $errors[] = 'Seu perfil não tem permissão para alterar esta regra. Apenas admin, diretora ou magistrada podem alterar.';
        } elseif ($action === 'clear') {
            try {
                $usuarioAtualizaId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : null;
                $ruleRow           = $regrasRepo->clearAllocation($ruleName, $usuarioAtualizaId !== '' ? $usuarioAtualizaId : null);
                $ruleUsuarioId     = '';
                $ruleUsuarioNome   = resolveUsuarioNomeOficios($connection, null);
                $successMessage    = 'Alocação removida com sucesso. Os ofícios ficarão sem alocação automática por regra.';
                $selectedUserId    = '';
            } catch (\InvalidArgumentException | \RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            } catch (\Throwable $exception) {
                $errors[] = 'Erro inesperado ao remover a alocação: ' . $exception->getMessage();
            }
        } else {
            $postedUserId = trim((string) ($_POST['id_user'] ?? ''));
            if ($postedUserId === '') {
                $errors[] = 'Selecione um servidor antes de salvar.';
            } elseif (!AlocacaoRegrasUsuariosHelper::isUsuarioElegivelParaRegraInterna($connection, $postedUserId)) {
                $errors[] = 'Apenas servidores internos com perfil operacional podem ser alocados nesta regra.';
            } else {
                try {
                    $usuarioAtualizaId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : null;
                    $ruleRow           = $regrasRepo->save($ruleName, $ruleDescription, $postedUserId, $usuarioAtualizaId !== '' ? $usuarioAtualizaId : null);
                    $ruleUsuarioId     = (string) ($ruleRow['usuario_id'] ?? $postedUserId);
                    $ruleUsuarioNome   = resolveUsuarioNomeOficios($connection, $ruleUsuarioId);
                    $successMessage    = 'Regra atualizada com ID ' . ($ruleRow['id'] ?? 'sem ID') . '.';
                    $selectedUserId    = (string) ($ruleRow['usuario_id'] ?? $postedUserId);
                } catch (\InvalidArgumentException | \RuntimeException $exception) {
                    $errors[] = $exception->getMessage();
                } catch (\Throwable $exception) {
                    $errors[] = 'Erro inesperado ao salvar a regra: ' . $exception->getMessage();
                }
            }
        }
    }
} catch (\Throwable $exception) {
    $errors[] = 'Erro ao acessar as regras: ' . $exception->getMessage();
}

$locationSpecificPrefix = $nomeCurtoModulo . ' - Regras';
$resumoRowClass = (trim($ruleUsuarioId) !== '' && in_array($ruleUsuarioId, $idsInternosElegiveis, true)) ? 'success' : 'info';

include __DIR__ . '/_cartorio_rules_head.php';
?>

    <div class="container-full card-page">
        <form method="post" class="rule-form" style="width: 100%">
            <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
                <div class="display-flex row row-fit justify-start">
                    <h2>Regras de alocação automática</h2>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-md"></div>
            </div>

            <?php if (!$podeAlterarRegra): ?>
                <div class="display-flex row row-full padding-x-md padding-y-md border-bottom">
                    <div class="col">
                        <label class="status status-primary-light">Apenas perfis admin, diretora ou magistrada podem alterar esta regra. Você pode visualizar a configuração atual.</label>
                    </div>
                </div>
            <?php endif; ?>

            <div class="display-flex row row-full filters justify-between padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="display-flex row row-fit justify-start gap-y-md">
                    <div class="col filter">
                        <label for="filter-servidor-alocacao">Servidor</label>
                        <select class="width-600 height-md" id="filter-servidor-alocacao" name="id_user"
                            data-original-user-name="<?php echo htmlspecialchars($ruleUsuarioNome, ENT_QUOTES, 'UTF-8'); ?>"
                            data-original-user-id="<?php echo htmlspecialchars($selectedUserId, ENT_QUOTES, 'UTF-8'); ?>"
                            <?php echo $podeAlterarRegra ? '' : 'disabled'; ?>>
                            <option value="" disabled<?php echo $selectedUserId === '' ? ' selected' : ''; ?>>Selecione aqui</option>
                            <?php foreach ($usersList as $user): ?>
                                <option value="<?php echo htmlspecialchars($user['id'], ENT_QUOTES); ?>"<?php echo $selectedUserId === $user['id'] ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-md"></div>
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

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>
<?php include __DIR__ . '/_cartorio_rules_scripts.php'; ?>
</body>
</html>
