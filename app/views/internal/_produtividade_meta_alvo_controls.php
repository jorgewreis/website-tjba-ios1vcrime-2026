<?php
declare(strict_types=1);
/**
 * Controles de alvo (setor select / perfil select / usuário text) para metas de produtividade.
 *
 * Variáveis esperadas:
 * - PDO $connection
 * - string $metaFormTipoAlvo
 * - string $metaFormAlvoValor
 */
require_once __DIR__ . '/../../helpers/ProdutividadeMetaAlvoOptions.php';

$__setores = ProdutividadeMetaAlvoOptions::setorChoices();
$__perfis = ProdutividadeMetaAlvoOptions::perfilValuesForSelect($connection);

$__selSetor = '';
foreach (array_keys($__setores) as $__cod) {
    if (strtoupper(trim($metaFormAlvoValor)) === $__cod) {
        $__selSetor = $__cod;
        break;
    }
}
if ($__selSetor === '' && $metaFormTipoAlvo === 'setor') {
    $__keys = array_keys($__setores);
    $__selSetor = $__keys[0] ?? 'CARTORIO';
}

if ($metaFormTipoAlvo === 'perfil' && $metaFormAlvoValor !== '' && !in_array($metaFormAlvoValor, $__perfis, true)) {
    array_unshift($__perfis, $metaFormAlvoValor);
}
?>
<div class="col">
    <label for="alvo_valor_setor">Alvo <span class="label-hint-obrigatorio">*obrigatório</span></label>
    <select id="alvo_valor_setor" class="width-500 height-md white" autocomplete="off" style="<?php echo $metaFormTipoAlvo !== 'setor' ? 'display:none;' : ''; ?>" <?php echo $metaFormTipoAlvo === 'setor' ? 'name="alvo_valor" required' : 'disabled'; ?>>
        <?php foreach ($__setores as $__val => $__label): ?>
        <option value="<?php echo htmlspecialchars($__val, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $__selSetor === $__val ? ' selected' : ''; ?>><?php echo htmlspecialchars($__label, ENT_QUOTES, 'UTF-8'); ?></option>
        <?php endforeach; ?>
    </select>
    <select id="alvo_valor_perfil" class="width-500 height-md white" autocomplete="off" style="<?php echo $metaFormTipoAlvo !== 'perfil' ? 'display:none;' : ''; ?>" <?php echo $metaFormTipoAlvo === 'perfil' ? 'name="alvo_valor" required' : 'disabled'; ?>>
        <option value="">Selecione o perfil</option>
        <?php foreach ($__perfis as $__p): ?>
        <?php $__plab = ProdutividadeMetaAlvoOptions::perfilLabel($__p); ?>
        <option value="<?php echo htmlspecialchars($__p, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($__p, ENT_QUOTES, 'UTF-8'); ?>"<?php echo strcasecmp($metaFormAlvoValor, $__p) === 0 ? ' selected' : ''; ?>><?php echo htmlspecialchars($__plab, ENT_QUOTES, 'UTF-8'); ?></option>
        <?php endforeach; ?>
    </select>
    <input type="text" id="alvo_valor_usuario" class="width-500 height-md white" autocomplete="off" style="<?php echo $metaFormTipoAlvo !== 'usuario' ? 'display:none;' : ''; ?>"
           value="<?php echo htmlspecialchars($metaFormTipoAlvo === 'usuario' ? $metaFormAlvoValor : '', ENT_QUOTES, 'UTF-8'); ?>"
           placeholder="ID do usuário (id_usuario)"
           <?php echo $metaFormTipoAlvo === 'usuario' ? 'name="alvo_valor" required' : 'disabled'; ?>>
</div>
<script>
(function () {
    var tipo = document.getElementById('tipo_alvo');
    var selSetor = document.getElementById('alvo_valor_setor');
    var selPerfil = document.getElementById('alvo_valor_perfil');
    var inpUser = document.getElementById('alvo_valor_usuario');
    if (!tipo || !selSetor || !selPerfil || !inpUser) return;

    function sync() {
        var t = tipo.value;
        selSetor.removeAttribute('name');
        selSetor.disabled = true;
        selSetor.required = false;
        selSetor.style.display = 'none';

        selPerfil.removeAttribute('name');
        selPerfil.disabled = true;
        selPerfil.required = false;
        selPerfil.style.display = 'none';

        inpUser.removeAttribute('name');
        inpUser.disabled = true;
        inpUser.required = false;
        inpUser.style.display = 'none';

        if (t === 'setor') {
            selSetor.style.display = '';
            selSetor.disabled = false;
            selSetor.required = true;
            selSetor.setAttribute('name', 'alvo_valor');
        } else if (t === 'perfil') {
            selPerfil.style.display = '';
            selPerfil.disabled = false;
            selPerfil.required = true;
            selPerfil.setAttribute('name', 'alvo_valor');
        } else {
            inpUser.style.display = '';
            inpUser.disabled = false;
            inpUser.required = true;
            inpUser.setAttribute('name', 'alvo_valor');
        }
    }

    tipo.addEventListener('change', function () {
        if (tipo.value === 'usuario' && inpUser.value === '') {
            inpUser.value = '';
        }
        sync();
    });
    sync();
})();
</script>
