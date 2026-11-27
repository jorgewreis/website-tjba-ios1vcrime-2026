<?php if ($alertaProtecaoFicha): ?>
    <dt>Alerta de proteção</dt><dd data-ficha-alerta-protecao>O processo tem prioridade que sinaliza proteção interna. Isso não confirma o nível de sigilo cadastral.</dd>
<?php endif; ?>
<?php if (!$sigilosoFicha): ?>
    <dt id="ficha-sigilo-datajud-rotulo" hidden>Restrição externa</dt>
    <dd data-ficha-sigilo-datajud aria-labelledby="ficha-sigilo-datajud-rotulo" hidden></dd>
<?php endif; ?>
