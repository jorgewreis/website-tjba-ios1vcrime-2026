<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detalhes do processo</title>
    <?php require __DIR__ . '/processo-ficha-assets.php'; ?>
</head>
<body class="ficha-processo">
<main class="ficha-shell">
    <header class="ficha-header">
        <div><p class="ficha-contexto">Consultas / Processos</p><h1>Detalhes do processo</h1>
        <p class="ficha-numero"><?= $eFicha($processo['numero_processo'] ?? '') ?></p></div>
        <nav aria-label="Ações da ficha" class="ficha-acoes">
            <a class="ficha-button" data-fechar-ficha href="<?= $eFicha($linkListaFicha) ?>">Voltar</a>
            <?php if ($podeEditarFicha): ?><a class="ficha-button ficha-primary" href="<?= $eFicha($linkEdicaoFicha) ?>">Editar dados</a><?php endif; ?>
            <span class="ficha-readonly">Somente leitura</span>
        </nav>
    </header>
    <div class="ficha-grid">
        <div class="ficha-editor">
            <section class="ficha-secao" aria-labelledby="ficha-dados-titulo">
                <h2 id="ficha-dados-titulo">Dados do processo</h2>
                <dl class="ficha-campos">
                    <dt>Segmento</dt><dd><?= $eFicha($origemFicha['segmento'] ?? 'Não informado') ?></dd>
                    <dt>Tribunal</dt><dd><?= $eFicha($origemFicha['tribunal'] ?? 'Não informado') ?></dd>
                    <dt>Comarca de origem</dt><dd><?php
                        $origemRotulo = $origemFicha['comarca'] !== null
                            ? $origemFicha['unidade'] . ' - ' . $origemFicha['comarca']
                            : ($origemFicha['unidade'] ?? 'Não identificada');
                        echo $eFicha($origemRotulo);
                    ?></dd>
                    <dt>Vara</dt><dd data-ficha-vara>Não informada</dd>
                    <dt>Classe</dt><dd data-ficha-classe><?= $eFicha($classeNomeAtual ?: 'Não informada') ?></dd>
                    <dt>Assuntos</dt><dd data-ficha-assuntos><?php
                        $rotulosAssuntos = array_values(array_unique(array_filter(array_column($assuntosFicha, 'assunto_descricao'))));
                        echo $eFicha($rotulosAssuntos ? implode('; ', $rotulosAssuntos) : 'Não informados');
                    ?></dd>
                    <dt>Sigilo</dt><dd data-ficha-sigilo<?= $temNivelFicha ? ' data-ficha-sigilo-local' : '' ?>><?= $eFicha($rotuloSigiloFicha) ?></dd>
                    <dt>Setor ativo</dt><dd><?= $eFicha($setorNomeExibido) ?></dd>
                    <dt>Prioridades</dt><dd><?php
                        $rotulosPrioridades = array_filter(array_column($prioridadesFichaEstado['prioridades'] ?? [], 'label'));
                        echo $eFicha($rotulosPrioridades ? implode('; ', $rotulosPrioridades) : 'Nenhuma registrada');
                    ?></dd>
                </dl>
                <h3>Partes cadastradas</h3>
                <?php if ($partes === []): ?><p class="ficha-secundario">Nenhuma parte vinculada.</p><?php else: ?>
                    <ul class="ficha-partes">
                        <?php foreach ($partes as $parte): ?>
                        <li><strong><?= $eFicha(trim((string) ($parte['nome_completo'] ?? '')) ?: 'Nome não informado') ?></strong>
                        <span><?= $eFicha(match ($parte['polo'] ?? '') {
                            'ativo'=>'Polo ativo','passivo'=>'Polo passivo','terceiros'=>'Terceiros',default=>'Polo não informado'
                        }) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
        <?php require __DIR__ . '/processo-ficha-contexto.php'; ?>
    </div>
</main>
</body>
</html>
