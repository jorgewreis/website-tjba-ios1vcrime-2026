<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/SalaAudienciasPautaService.php';

$idParte = trim((string) ($_GET['id_parte'] ?? ''));
$service = new SalaAudienciasPautaService(Conection::getInstance()->getConnection());
$parte = $idParte !== '' ? $service->findParte($idParte) : null;
$tipoDefensorAtual = (string) ($parte['tipo_defensor'] ?? '');
$defensorPublicoAtual = (string) ($parte['defensor_publico_id'] ?? '');
$defensorPrivadoAtual = (string) ($parte['defensor_privado_id'] ?? '');
$defensorPrivadoOutroAtual = (string) ($parte['defensor_privado_outro'] ?? '');
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
            <span class="all">Interno - Cartório - Audiências -</span>
            <span class="specific">Editar parte cadastrada</span>
        </div>
        <h1 class="title-page">Editar parte cadastrada</h1>
        <p class="description-page">Atualize os dados da parte vinculada à audiência e os identificadores de expedição e cumprimento.</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Contexto da audiência</h2>
        </div>
        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="parte-edit-processo">Número do processo</label>
                    <input type="text" class="width-400 height-md readonly" id="parte-edit-processo" value="<?php echo htmlspecialchars((string) ($parte['numero_processo'] ?? ''), ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col">
                    <label for="parte-edit-tipo-audiencia">Tipo da audiência</label>
                    <input type="text" class="width-600 height-md readonly" id="parte-edit-tipo-audiencia" value="<?php echo htmlspecialchars((string) ($parte['tipo_audiencia'] ?? ''), ENT_QUOTES); ?>" readonly>
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
                    <input type="hidden" id="parte-edit-id" value="<?php echo htmlspecialchars((string) ($parte['id_parte'] ?? ''), ENT_QUOTES); ?>">
                    <input type="hidden" id="parte-edit-parte-global-id" value="<?php echo htmlspecialchars((string) ($parte['parte_id_global'] ?? ''), ENT_QUOTES); ?>">
                    <input type="hidden" id="parte-edit-processo-parte-id" value="<?php echo htmlspecialchars((string) ($parte['processo_parte_id'] ?? ''), ENT_QUOTES); ?>">
                    <input type="text" class="width-700 height-md" id="parte-edit-nome" value="<?php echo htmlspecialchars((string) ($parte['nome_parte'] ?? ''), ENT_QUOTES); ?>" autocomplete="off">
                </div>
                <div class="col">
                    <label for="parte-edit-tipo">Tipo de parte <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-400 height-md" id="parte-edit-tipo" required>
                        <?php $tipo = (string) ($parte['tipo_parte'] ?? ''); ?>
                        <option value="">Selecione o tipo</option>
                        <option value="reu"<?php echo $tipo === 'reu' ? ' selected' : ''; ?>>Réu</option>
                        <option value="vitima"<?php echo $tipo === 'vitima' ? ' selected' : ''; ?>>Vítima</option>
                        <option value="testemunha_defesa"<?php echo $tipo === 'testemunha_defesa' ? ' selected' : ''; ?>>Testemunha da Defesa</option>
                        <option value="testemunha_denuncia"<?php echo $tipo === 'testemunha_denuncia' ? ' selected' : ''; ?>>Testemunha da Denúncia</option>
                        <option value="outro"<?php echo $tipo === 'outro' ? ' selected' : ''; ?>>Outro</option>
                    </select>
                </div>
                <div class="col">
                    <label for="parte-edit-maioridade">Maioridade <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-300 height-md" id="parte-edit-maioridade">
                        <option value="">Selecione...</option>
                        <option value="adulto"<?php echo (string) ($parte['maioridade'] ?? '') === 'adulto' ? ' selected' : ''; ?>>Adulto</option>
                        <option value="menor"<?php echo (string) ($parte['maioridade'] ?? '') === 'menor' ? ' selected' : ''; ?>>Menor</option>
                    </select>
                </div>
            </div>

            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px; display:none;" id="parte-edit-defensor-wrapper">
                <div class="col">
                    <label for="parte-edit-tipo-defensor">Tipo de defensor</label>
                    <select class="width-400 height-md" id="parte-edit-tipo-defensor">
                        <option value="">Selecione o tipo</option>
                        <option value="publico"<?php echo $tipoDefensorAtual === 'publico' ? ' selected' : ''; ?>>Defensor público</option>
                        <option value="privado"<?php echo $tipoDefensorAtual === 'privado' ? ' selected' : ''; ?>>Defensor privado</option>
                    </select>
                </div>

                <div class="col" id="parte-edit-defensor-publico-wrapper" style="display:none;">
                    <label for="parte-edit-defensor-publico">Defensor Público</label>
                    <select class="width-600 height-md" id="parte-edit-defensor-publico">
                        <option value="">Selecione...</option>
                        <option value="dp-01"<?php echo $defensorPublicoAtual === 'dp-01' ? ' selected' : ''; ?>>Defensoria Pública - Núcleo 01</option>
                        <option value="dp-02"<?php echo $defensorPublicoAtual === 'dp-02' ? ' selected' : ''; ?>>Defensoria Pública - Núcleo 02</option>
                    </select>
                </div>

                <div class="col" id="parte-edit-defensor-privado-wrapper" style="display:none;">
                    <label for="parte-edit-defensor-privado">Defensor</label>
                    <select class="width-600 height-md" id="parte-edit-defensor-privado">
                        <option value="">Selecione o defensor</option>
                        <option value="adv-01"<?php echo $defensorPrivadoAtual === 'adv-01' ? ' selected' : ''; ?>>Dr. Exemplo da Silva</option>
                        <option value="adv-02"<?php echo $defensorPrivadoAtual === 'adv-02' ? ' selected' : ''; ?>>Dra. Exemplo Santos</option>
                        <option value="outro"<?php echo $defensorPrivadoAtual === 'outro' ? ' selected' : ''; ?>>Outro</option>
                    </select>
                </div>

                <div class="col" id="parte-edit-defensor-privado-outro-wrapper" style="display:none;">
                    <label for="parte-edit-defensor-privado-outro">Outro</label>
                    <input type="text" class="width-600 height-md" id="parte-edit-defensor-privado-outro" value="<?php echo htmlspecialchars($defensorPrivadoOutroAtual, ENT_QUOTES); ?>" placeholder="Digite o nome do defensor" autocomplete="off">
                </div>
            </div>

            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="parte-edit-expedicao">Expedição <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-500 height-md" id="parte-edit-expedicao">
                        <?php $exp = (string) ($parte['expedicao'] ?? ''); ?>
                        <option value="">Selecione...</option>
                        <option value="Não expedido"<?php echo $exp === 'Não expedido' ? ' selected' : ''; ?>>Não expedido</option>
                        <option value="Mandado de Intimação"<?php echo $exp === 'Mandado de Intimação' ? ' selected' : ''; ?>>Mandado de Intimação</option>
                        <option value="Ofício"<?php echo $exp === 'Ofício' ? ' selected' : ''; ?>>Ofício</option>
                        <option value="Edital"<?php echo $exp === 'Edital' ? ' selected' : ''; ?>>Edital</option>
                        <option value="Carta precatória"<?php echo $exp === 'Carta precatória' ? ' selected' : ''; ?>>Carta precatória</option>
                    </select>
                </div>
                <div class="col">
                    <label for="parte-edit-id-expedido">ID</label>
                    <input type="text" class="width-300 height-md" id="parte-edit-id-expedido" value="<?php echo htmlspecialchars((string) ($parte['id_documento_expedido'] ?? ''), ENT_QUOTES); ?>" autocomplete="off">
                </div>
            </div>
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="parte-edit-cumprimento">Cumprimento <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-500 height-md" id="parte-edit-cumprimento">
                        <?php $cum = (string) ($parte['cumprimento'] ?? ''); ?>
                        <option value="">Selecione...</option>
                        <option value="pendente"<?php echo $cum === 'pendente' ? ' selected' : ''; ?>>Aguardando cumprimento</option>
                        <option value="cumprido"<?php echo $cum === 'cumprido' ? ' selected' : ''; ?>>Cumprido</option>
                        <option value="nao_cumprido"<?php echo $cum === 'nao_cumprido' ? ' selected' : ''; ?>>Não cumprido</option>
                        <option value="parcial"<?php echo $cum === 'parcial' ? ' selected' : ''; ?>>Parcialmente cumprido</option>
                    </select>
                </div>
                <div class="col">
                    <label for="parte-edit-id-cumprido">ID</label>
                    <input type="text" class="width-300 height-md" id="parte-edit-id-cumprido" value="<?php echo htmlspecialchars((string) ($parte['id_documento_cumprido'] ?? ''), ENT_QUOTES); ?>" placeholder="Preencher quando houver cumprimento" autocomplete="off">
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

    <script src="/assets/js/modules/audiencias/pauta-parte-edit.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/pauta-parte-edit.js') ?: time(); ?>"></script>
</body>
</html>
