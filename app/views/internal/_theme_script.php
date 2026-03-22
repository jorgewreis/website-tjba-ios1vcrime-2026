<?php
/**
 * Aplica data-theme no <html> a partir do cookie (claro / escuro / auto).
 * Incluir após color.css.
 */
?>
<script>
(function() {
    var name = 'tjba_1vcrime_theme';
    var i = document.cookie.indexOf(name + '=');
    if (i === -1) return;
    i += name.length + 1;
    var end = document.cookie.indexOf(';', i);
    var t = decodeURIComponent((end === -1 ? document.cookie.slice(i) : document.cookie.slice(i, end)).trim());
    if (t === 'claro' || t === 'escuro' || t === 'auto') document.documentElement.setAttribute('data-theme', t);
})();
</script>
