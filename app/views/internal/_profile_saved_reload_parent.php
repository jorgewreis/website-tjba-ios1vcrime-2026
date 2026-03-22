<?php
declare(strict_types=1);
/**
 * Após salvar dados pessoais, o iframe redireciona para o dashboard com ?profile_saved=1.
 * Recarrega a home (parent) para atualizar avatar e barra superior; o toast é exibido na home via sessão.
 */
?>
<script>
(function () {
    if (window.parent === window) return;
    var params = new URLSearchParams(window.location.search || '');
    if (params.get('profile_saved') !== '1') return;
    try {
        window.parent.location.reload();
    } catch (e) {}
})();
</script>
