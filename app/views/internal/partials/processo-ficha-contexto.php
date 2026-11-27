<aside class="ficha-context-panel" aria-label="Referências e movimentações do processo"
    <?= !$sigilosoFicha ? 'data-ficha-datajud="' . $eFicha($processoIdFicha) . '"' : '' ?>>
    <?php if ($modoEdicao): ?>
    <section class="ficha-secao">
        <h2>Origem pelo número CNJ</h2>
        <p><?= $eFicha(implode(' · ', array_filter([$origemFicha['segmento'], $origemFicha['tribunal'],
            $origemFicha['comarca'] !== null ? $origemFicha['comarca'] . ' (origem)' : $origemFicha['unidade']]))) ?: 'Não informada' ?></p>
    </section>
    <section class="ficha-secao">
        <h2>Vara informada pelo DataJud</h2>
        <p data-ficha-vara>Não informada</p>
        <p>Sigilo: <span data-ficha-sigilo<?= $temNivelFicha ? ' data-ficha-sigilo-local' : '' ?>><?= $eFicha($rotuloSigiloFicha) ?></span></p>
    </section>
    <?php endif; ?>
    <section class="ficha-secao">
        <h2>Últimas movimentações</h2>
        <p class="ficha-secundario">Fonte: API pública do DataJud</p>
        <p data-ficha-status role="status"><?= $sigilosoFicha
            ? 'Processo sigiloso no cadastro. O histórico não é consultado na base pública.'
            : 'Movimentações ainda não carregadas.' ?></p>
        <button type="button" class="ficha-button" data-ficha-tentar hidden>Tentar novamente</button>
        <ol class="ficha-timeline" data-ficha-recentes></ol>
        <details class="ficha-historico" data-ficha-historico hidden>
            <summary>Ver todas as movimentações</summary>
            <ol class="ficha-timeline" data-ficha-todas></ol>
        </details>
    </section>
</aside>
