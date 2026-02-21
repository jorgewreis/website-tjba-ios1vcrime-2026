<?php
declare(strict_types=1);
// expects: $details array (key=>value)
$details = $details ?? [];
?>
<div class="error-details mt-3 container-xl" id="errorDetails" hidden aria-hidden="true">
    <div class="card border-0 shadow-sm">
        <div class="card-body small">
            <h6>Detalhes técnicos</h6>
            <dl class="row">
                <?php foreach ($details as $k => $v): ?>
                    <dt class="col-sm-3 text-truncate"><?php echo htmlspecialchars((string)$k); ?></dt>
                    <dd class="col-sm-9"><code class="text-break"><?php echo htmlspecialchars((string)$v); ?></code></dd>
                <?php endforeach; ?>
            </dl>
            <p class="mb-0 text-muted">Compartilhe o código acima com a equipe de suporte para investigação.</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('click', function (e) {
    if (e.target && e.target.matches && e.target.matches('.toggle-error-details')) {
        const btn = e.target;
        const details = document.getElementById('errorDetails');
        const expanded = details.getAttribute('aria-hidden') === 'false';
        if (expanded) {
            details.hidden = true;
            details.setAttribute('aria-hidden','true');
            btn.setAttribute('aria-expanded','false');
            btn.textContent = 'Mostrar detalhes técnicos';
        } else {
            details.hidden = false;
            details.setAttribute('aria-hidden','false');
            btn.setAttribute('aria-expanded','true');
            btn.textContent = 'Ocultar detalhes técnicos';
        }
    }
});
</script>

