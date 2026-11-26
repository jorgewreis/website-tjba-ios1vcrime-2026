<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/CartasExpedidasEnvioRegra.php';
require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';

$connection = Conection::getInstance()->getConnection();
$actor = trim((string) ($_SESSION['id_usuario'] ?? ''));
$podeAlterarRegra = $actor !== '' && CartasExpedidasEnvioRegra::podeAlterar(UsuarioPerfisAutorizacao::perfisDoUsuario($actor));
$error = '';
$saved = false;
$users = [];
$current = null;
$e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? null;
        $action = $_POST['action'] ?? null;
        $selected = $_POST['servidor'] ?? null;
        if (!$podeAlterarRegra || !is_string($token) || !Security::verifyCSRFToken($token)) {
            http_response_code(403);
            $error = 'Sem permissão ou sessão inválida para alterar a regra. Recarregue a página.';
        } elseif ($action === 'clear') {
            $repo = new AlocacoesRegrasRepository($connection);
            if ($repo->findByRegra(CartasExpedidasEnvioRegra::NOME) !== null) {
                $repo->clearAllocation(CartasExpedidasEnvioRegra::NOME, $actor);
            }
            $saved = true;
        } elseif ($action === 'save' && is_string($selected) && $selected !== ''
            && CartasExpedidasEnvioRegra::elegiveis($connection, $selected) !== []) {
            (new AlocacoesRegrasRepository($connection))->save(
                CartasExpedidasEnvioRegra::NOME,
                'Alocação automática do envio de cartas expedidas',
                $selected,
                $actor
            );
            $saved = true;
        } else {
            http_response_code(400);
            $error = 'Selecione um servidor interno ativo e elegível para a regra.';
        }
    }
    $current = CartasExpedidasEnvioRegra::responsavel($connection);
    $users = $podeAlterarRegra ? CartasExpedidasEnvioRegra::elegiveis($connection) : [];
} catch (Throwable $errorInternal) {
    error_log('cartas expedidas: falha na regra de envio');
    $error = 'Não foi possível confirmar a configuração da regra de envio.';
    $saved = false;
}

$locationAll = 'Interno - Cartório - Cartas Expedidas -';
$nomeCurtoModulo = 'Cartas Expedidas';
$usarFormularioOperacional = true;
$description = 'Configure o responsável pela fila de cartas aguardando envio.';
include __DIR__ . '/_cartorio_rules_head.php';
?>
<div class="container-full card-page">
    <div class="card-title"><h1 class="title-page">Regra de envio de cartas</h1></div>
    <div class="display-flex row row-full padding-x-md padding-y-md border-bottom">
        <p>Responsável atual: <strong><?php echo $e($current['nome'] ?? 'Nenhum servidor definido'); ?></strong></p>
    </div>
    <?php if ($error !== '' || $saved): ?>
    <div class="display-flex row row-full padding-x-md padding-y-md border-bottom" role="status">
        <?php echo $e($error !== '' ? $error : 'Regra atualizada com sucesso.'); ?>
    </div>
    <?php endif; ?>
    <?php if ($podeAlterarRegra): ?>
    <form method="post" class="display-flex col col-full">
        <input type="hidden" name="csrf_token" value="<?php echo $e(Security::generateCSRFToken()); ?>">
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom">
            <div class="col filter">
                <label for="regra-envio-servidor">Servidor responsável pelo envio</label>
                <select id="regra-envio-servidor" name="servidor" required class="width-600 height-md">
                    <option value="">Selecione um servidor</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $e($user['id']); ?>"<?php echo ($current['id'] ?? '') === $user['id'] ? ' selected' : ''; ?>><?php echo $e($user['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="card-buttons">
            <button type="submit" class="btn btn-primary" name="action" value="save">Salvar regra</button>
            <button type="submit" class="btn btn-danger-outline" name="action" value="clear" formnovalidate>Remover alocação</button>
        </div>
    </form>
    <?php endif; ?>
    <div class="display-flex row row-full padding-x-md padding-y-md">
        <a href="/frame.php?page=cartasexpedidas">Voltar para cartas expedidas</a>
    </div>
</div>
</body>
</html>
