<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Pauta de Audiências - Editar Parte</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">
    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Sala de Audiências - Pauta -</span>
            <span class="specific">Editar parte</span>
        </div>
        <h1 class="title-page">Editar parte cadastrada</h1>
        <p class="description-page">Atualize os dados da parte vinculada à audiência e os documentos relacionados.</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Contexto da audiência</h2>
        </div>
        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="parte-edit-audiencia">Audiência</label>
                    <input type="text" class="width-300 height-md readonly" id="parte-edit-audiencia" value="001/2026" readonly>
                </div>
                <div class="col">
                    <label for="parte-edit-processo">Número do processo</label>
                    <input type="text" class="width-500 height-md readonly" id="parte-edit-processo" value="0800234-11.2026.8.05.0103" readonly>
                </div>
            </div>
        </div>

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Dados da parte</h2>
        </div>
        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="parte-edit-nome">Nome da parte <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" class="width-700 height-md" id="parte-edit-nome" value="Fernando Alves de Souza">
                </div>
                <div class="col">
                    <label for="parte-edit-tipo">Tipo de parte <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-400 height-md" id="parte-edit-tipo">
                        <option value="reu" selected>Réu</option>
                        <option value="vitima">Vítima</option>
                        <option value="testemunha_defesa">Testemunha da Defesa</option>
                        <option value="testemunha_denuncia">Testemunha da Denúncia</option>
                    </select>
                </div>
                <div class="col">
                    <label for="parte-edit-maioridade">Maioridade <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-300 height-md" id="parte-edit-maioridade">
                        <option value="adulto" selected>Adulto</option>
                        <option value="menor">Menor</option>
                    </select>
                </div>
            </div>

            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="parte-edit-expedicao">Expedição <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-400 height-md" id="parte-edit-expedicao">
                        <option value="mandado" selected>Mandado de Intimação</option>
                        <option value="oficio">Ofício</option>
                        <option value="edital">Edital</option>
                    </select>
                </div>
                <div class="col">
                    <label for="parte-edit-cumprimento">Cumprimento <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-400 height-md" id="parte-edit-cumprimento">
                        <option value="pendente" selected>Aguardando cumprimento</option>
                        <option value="cumprido">Cumprido</option>
                        <option value="nao_cumprido">Não cumprido</option>
                    </select>
                </div>
                <div class="col">
                    <label for="parte-edit-id-expedido">ID do documento expedido</label>
                    <input type="text" class="width-300 height-md" id="parte-edit-id-expedido" value="789873">
                </div>
                <div class="col">
                    <label for="parte-edit-id-cumprido">ID do documento cumprido</label>
                    <input type="text" class="width-300 height-md" id="parte-edit-id-cumprido" value="">
                </div>
            </div>
        </div>

        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button type="button" class="btn btn-secondary" id="btn-parte-edit-voltar">Voltar</button>
            <button type="button" class="btn btn-danger-outline" id="btn-parte-edit-remover">Remover parte</button>
            <button type="button" class="btn btn-primary" id="btn-parte-edit-salvar">Salvar alterações</button>
        </div>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <script src="/assets/js/modules/audiencias/pauta-parte-edit.js"></script>
</body>
</html>
