<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../repositories/ProcessosPartesRepository.php';

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') { header('Location: /frame.php?page=consultaprocessos'); exit; }

$connection = Conection::getInstance()->getConnection();
$repo = new ProcessosPartesRepository($connection);
$vinculo = $repo->findById($id);
if ($vinculo === null) { header('Location: /frame.php?page=consultaprocessos'); exit; }

$tiposValidos = [
    'reu' => 'Réu', 'indiciado' => 'Indiciado', 'sentenciado' => 'Sentenciado', 'requerido' => 'Requerido',
    'vitima' => 'Vítima', 'requerente' => 'Requerente', 'testemunha_defesa' => 'Testemunha defesa',
    'testemunha_denuncia' => 'Testemunha denúncia', 'advogado' => 'Advogado', 'defensor_publico' => 'Defensor público',
];
$polosValidos = ['ativo' => 'Ativo', 'passivo' => 'Passivo', 'terceiros' => 'Terceiros'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar vínculo processo-parte</title>
            <?php include __DIR__ . '/_internal_head_styles.php'; ?>

    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno - Consultas -</span> <span class="specific">Editar vínculo</span></div>
        <h1 class="title-page">Editar vínculo: <?php echo htmlspecialchars($vinculo['numero_processo'] ?? ''); ?> — <?php echo htmlspecialchars($vinculo['nome_completo'] ?? ''); ?></h1>
    </div>
    <div class="container-full card-page">
        <input type="hidden" id="vinculo-id" value="<?php echo htmlspecialchars($vinculo['id'] ?? ''); ?>">
        <input type="hidden" id="processo-id" value="<?php echo htmlspecialchars($vinculo['processo_id'] ?? ''); ?>">
        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Dados do vínculo</h2></div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col"><strong>Processo:</strong> <?php echo htmlspecialchars($vinculo['numero_processo'] ?? '—'); ?></div>
            <div class="col"><strong>Parte:</strong> <?php echo htmlspecialchars($vinculo['nome_completo'] ?? '—'); ?> (<?php echo htmlspecialchars($vinculo['cpf'] ?? '—'); ?>)</div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="tipo_parte">Tipo de parte <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-200 height-md white" id="tipo_parte" required>
                    <option value="">Selecione o tipo</option>
                    <?php foreach ($tiposValidos as $val => $label): ?>
                        <option value="<?php echo htmlspecialchars($val); ?>"<?php echo (($vinculo['tipo_parte'] ?? '') === $val) ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col">
                <label for="polo">Polo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-200 height-md white" id="polo" required>
                    <option value="">Selecione o polo</option>
                    <?php foreach ($polosValidos as $val => $label): ?>
                        <option value="<?php echo htmlspecialchars($val); ?>"<?php echo (($vinculo['polo'] ?? '') === $val) ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label><input type="checkbox" id="prioridade_mulher"<?php echo !empty($vinculo['prioridade_mulher']) ? ' checked' : ''; ?>> Prioridade mulher</label>
            </div>
            <div class="col">
                <label><input type="checkbox" id="prioridade_crianca_adolescente"<?php echo !empty($vinculo['prioridade_crianca_adolescente']) ? ' checked' : ''; ?>> Prioridade criança/adolescente</label>
            </div>
            <div class="col">
                <label><input type="checkbox" id="prioridade_idoso"<?php echo !empty($vinculo['prioridade_idoso']) ? ' checked' : ''; ?>> Prioridade idoso</label>
            </div>
            <div class="col">
                <label><input type="checkbox" id="prioridade_pessoa_deficiencia"<?php echo !empty($vinculo['prioridade_pessoa_deficiencia']) ? ' checked' : ''; ?>> Prioridade pessoa com deficiência</label>
            </div>
        </div>
        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button type="button" class="btn btn-secondary" data-nav-href="/frame.php?page=consultaprocessosedit&id=<?php echo urlencode($vinculo['processo_id'] ?? ''); ?>">Voltar</button>
            <button type="button" class="btn btn-primary" id="salvar-btn">Salvar</button>
        </div>
    </div>
    <script>
        (function () {
            if (!window.App || !App.ProcessosPartesEdit || typeof App.ProcessosPartesEdit.initialize !== 'function') {
                return;
            }

            App.ProcessosPartesEdit.initialize();
        })();
    </script>
</body>
</html>
