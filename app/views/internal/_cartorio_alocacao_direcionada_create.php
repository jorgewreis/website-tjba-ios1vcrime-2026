<?php
declare(strict_types=1);

/** @var PDO $connection */
/** @var string $cartorioAlocacaoPrefix prefixo único para ids (ex.: guias, ceapa) */

if (!isset($connection) || !isset($cartorioAlocacaoPrefix) || !$connection instanceof PDO) {
    return;
}

$__capAloc = preg_replace('/[^a-z0-9_-]/i', '', (string) $cartorioAlocacaoPrefix);
if ($__capAloc === '') {
    return;
}

require_once __DIR__ . '/../../helpers/AlocacaoTarefasPerfisHelper.php';
require_once __DIR__ . '/../../helpers/AlocacaoRegrasUsuariosHelper.php';

$__uidAloc = trim((string) ($_SESSION['id_usuario'] ?? ''));
$__showAlocDir = ($__uidAloc !== '' && AlocacaoTarefasPerfisHelper::usuarioAdminOuChefiaPodeAlocarOutrosCadastro($__uidAloc));
$__usersAloc = [];
if ($__showAlocDir) {
    try {
        $__usersAloc = AlocacaoRegrasUsuariosHelper::listUsuariosInternos($connection);
    } catch (Throwable $e) {
        $__usersAloc = [];
    }
}
?>
<?php if ($__showAlocDir): ?>
            <div class="display-flex col col-full gap-y-sm padding-x-md padding-y-md border-bottom">
                <span class="minor-info">Alocar servidores à tarefa (opcional) — perfis ADMIN, CHEFIA, DIRETORA ou MAGISTRADA</span>
                <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                    <div class="col">
                        <label for="<?php echo htmlspecialchars($__capAloc, ENT_QUOTES, 'UTF-8'); ?>-alocar-usuario-select">Servidor</label>
                        <select class="width-500 height-md" id="<?php echo htmlspecialchars($__capAloc, ENT_QUOTES, 'UTF-8'); ?>-alocar-usuario-select">
                            <option value="">Selecione…</option>
                            <?php foreach ($__usersAloc as $u): ?>
                            <option value="<?php echo htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col">
                        <button type="button" class="btn btn-secondary height-md" id="<?php echo htmlspecialchars($__capAloc, ENT_QUOTES, 'UTF-8'); ?>-alocar-adicionar">Adicionar</button>
                    </div>
                </div>
                <div class="display-flex row row-fit flex-wrap gap-y-sm" id="<?php echo htmlspecialchars($__capAloc, ENT_QUOTES, 'UTF-8'); ?>-alocar-chips" aria-live="polite"></div>
            </div>
<?php endif; ?>
