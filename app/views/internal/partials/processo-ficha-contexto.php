<?php
$assuntosLocaisAvulsos = array_values(array_filter(array_map(
    static fn(array $item): ?string => ($item['origem'] ?? '') === 'datajud' ? null : ($item['assunto_descricao'] ?? null),
    $assuntosFicha
)));
?>
<aside class="ficha-context-panel" aria-label="Referências e movimentações do processo"
    <?php if (!$sigilosoFicha): ?>data-ficha-datajud="<?= $eFicha($processoIdFicha) ?>"
    data-ficha-assuntos-locais="<?= $eFicha(json_encode($assuntosLocaisAvulsos, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)) ?>"
    <?php endif; ?>>
    <section class="ficha-secao">
        <h2>Últimas movimentações</h2>
        <p class="ficha-secundario">Fonte: API pública do DataJud</p>
        <p data-ficha-status role="status"><?= $sigilosoFicha
            ? 'Processo sigiloso no cadastro. O histórico não é consultado na base pública.'
            : 'Consulte os dados e as movimentações quando precisar.' ?></p>
        <?php if (!$sigilosoFicha): ?>
            <button type="button" class="ficha-button" data-ficha-consultar>Consultar dados e movimentações</button>
        <?php endif; ?>
        <button type="button" class="ficha-button" data-ficha-tentar hidden>Tentar novamente</button>
        <ol class="ficha-timeline" data-ficha-recentes></ol>
        <details class="ficha-historico" data-ficha-historico hidden>
            <summary>Ver todas as movimentações</summary>
            <ol class="ficha-timeline" data-ficha-todas></ol>
        </details>
    </section>
</aside>
