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
        <title>Pauta de Audiências - Edição</title>
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
            <span class="specific">Edição</span>
        </div>
        <h1 class="title-page">Editar audiência</h1>
        <p class="description-page">Atualização de audiência, partes vinculadas e documentos de expedição/cumprimento.</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Dados da audiência</h2>
        </div>

        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="audiencia-edit-processo">Número do processo</label>
                    <input type="text" class="width-400 height-md readonly" id="audiencia-edit-processo" value="0800234-11.2026.8.05.0103" readonly>
                </div>
                <div class="col">
                    <label for="audiencia-edit-classe">Classe</label>
                    <input type="text" class="width-600 height-md readonly" id="audiencia-edit-classe" value="Ação Penal - Procedimento Ordinário" readonly>
                </div>
            </div>
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="audiencia-edit-tipo">Tipo de audiência <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-500 height-md" id="audiencia-edit-tipo">
                        <option value="instrucao" selected>Instrução e Julgamento</option>
                        <option value="custodia">Custódia</option>
                        <option value="conciliacao">Conciliação</option>
                    </select>
                </div>
                <div class="col">
                    <label for="audiencia-edit-data">Data <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="date" class="width-300 height-md" id="audiencia-edit-data" value="2026-03-26">
                </div>
                <div class="col">
                    <label for="audiencia-edit-hora">Hora <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="time" class="width-300 height-md" id="audiencia-edit-hora" value="09:00">
                </div>
                <div class="col">
                    <label for="audiencia-edit-sala">Localização <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-300 height-md" id="audiencia-edit-sala">
                        <option value="presencial">Presencial</option>
                        <option value="lifesize" selected>Lifesize</option>
                        <option value="audin">Audin</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Adicionar parte</h2>
        </div>
        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="parte-nome">Nome da parte <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" class="width-700 height-md" id="parte-nome" placeholder="Digite o nome da parte">
                </div>
                <div class="col">
                    <label for="parte-tipo">Tipo de parte <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-400 height-md" id="parte-tipo">
                        <option value="">Selecione...</option>
                        <option value="reu">Réu</option>
                        <option value="vitima">Vítima</option>
                        <option value="testemunha_defesa">Testemunha da Defesa</option>
                        <option value="testemunha_denuncia">Testemunha da Denúncia</option>
                    </select>
                </div>
                <div class="col">
                    <label for="parte-maioridade">Maioridade <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-300 height-md" id="parte-maioridade">
                        <option value="">Selecione...</option>
                        <option value="adulto">Adulto</option>
                        <option value="menor">Menor</option>
                    </select>
                </div>
            </div>

            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="parte-expedicao">Expedição <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-400 height-md" id="parte-expedicao">
                        <option value="">Selecione...</option>
                        <option value="mandado">Mandado de Intimação</option>
                        <option value="oficio">Ofício</option>
                        <option value="edital">Edital</option>
                    </select>
                </div>
                <div class="col">
                    <label for="parte-cumprimento">Cumprimento <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-400 height-md" id="parte-cumprimento">
                        <option value="">Selecione...</option>
                        <option value="pendente">Aguardando cumprimento</option>
                        <option value="cumprido">Cumprido</option>
                        <option value="nao_cumprido">Não cumprido</option>
                    </select>
                </div>
                <div class="col">
                    <label for="parte-id-doc-expedido">ID do documento expedido</label>
                    <input type="text" class="width-300 height-md" id="parte-id-doc-expedido" placeholder="Ex.: 789873">
                </div>
                <div class="col">
                    <label for="parte-id-doc-cumprido">ID do documento cumprido</label>
                    <input type="text" class="width-300 height-md" id="parte-id-doc-cumprido" placeholder="Ex.: 789977">
                </div>
            </div>
            <div class="display-flex row row-full justify-end gap-y-md">
                <button type="button" class="btn btn-secondary-outline" id="btn-parte-limpar">Limpar parte</button>
                <button type="button" class="btn btn-primary" id="btn-parte-adicionar">Adicionar parte</button>
            </div>
        </div>

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Partes cadastradas</h2>
        </div>
        <div class="display-flex col col-full register border-bottom">
            <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                    <div class="col width-500">
                        <div class="minor-info">Réu</div>
                        <span class="major-info text-ellipsis">Fernando Alves de Souza</span>
                    </div>
                    <div class="col width-600">
                        <div class="minor-info">Documento expedido - ID: <span class="id-info">#789873</span></div>
                        <span class="major-info text-ellipsis">Mandado de Intimação</span>
                    </div>
                    <div class="col width-400">
                        <span class="minor-info">Status</span>
                        <div class="major-info"><span class="mini-status status-pending"></span>Aguardando cumprimento</div>
                    </div>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                    <div class="col button-table button-edit" data-parte-edit="PARTE-001" title="Editar parte"><i class="fi fi-rr-file-edit"></i></div>
                    <div class="col button-table" data-parte-remove="PARTE-001" title="Remover parte"><i class="fi fi-rr-trash"></i></div>
                </div>
            </div>
        </div>

        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button type="button" class="btn btn-secondary" id="btn-audiencia-edit-voltar">Voltar</button>
            <button type="button" class="btn btn-danger-outline" id="btn-audiencia-edit-cancelar">Cancelar audiência</button>
            <button type="button" class="btn btn-danger" id="btn-audiencia-edit-excluir">Excluir audiência</button>
            <button type="button" class="btn btn-primary" id="btn-audiencia-edit-salvar">Salvar alterações</button>
        </div>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <script src="/assets/js/modules/audiencias/pauta-edit.js"></script>
</body>
</html>
