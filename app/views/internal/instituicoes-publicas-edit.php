<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/InstituicoesPublicasService.php';
require_once __DIR__ . '/../../../config/security.php';

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') { header('Location: /frame.php?page=instituicoespublicas'); exit; }
$connection = Conection::getInstance()->getConnection();
$service = new InstituicoesPublicasService($connection);
$inst = $service->find($id);
if ($inst === null) { header('Location: /frame.php?page=instituicoespublicas'); exit; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar Instituição</title>
            <?php include __DIR__ . '/_internal_head_styles.php'; ?>

    <?php include __DIR__ . '/_toast_script.php'; ?>
    <script src="/assets/js/shared/cnpj.js"></script>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno - Instituições -</span> <span class="specific">Editar</span></div>
        <h1 class="title-page"><?php echo htmlspecialchars($inst['nome'] ?? 'Instituição'); ?></h1>
    </div>
    <div class="container-full card-page">
        <input type="hidden" id="inst-id" value="<?php echo htmlspecialchars($inst['id']); ?>">
        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Dados</h2></div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="nome">Nome <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-600 height-md white" id="nome" placeholder="Digite o nome completo" value="<?php echo htmlspecialchars($inst['nome'] ?? ''); ?>" required>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="nome_fantasia">Nome fantasia / Sigla</label>
                <input type="text" class="width-300 height-md white" id="nome_fantasia" value="<?php echo htmlspecialchars($inst['nome_fantasia'] ?? ''); ?>">
            </div>
            <div class="col">
                <label for="cnpj">CNPJ</label>
                <input type="text" class="width-400 height-md white" id="cnpj" placeholder="00.000.000/0000-00" value="<?php
                    $cnpjVal = preg_replace('/\D+/', '', (string) ($inst['cnpj'] ?? ''));
                    echo htmlspecialchars($cnpjVal !== '' && strlen($cnpjVal) === 14 ? Security::formatCNPJ($cnpjVal) : ($inst['cnpj'] ?? ''));
                ?>">
            </div>
        </div>

        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Endereços</h2></div>
        <div id="enderecos-list">
            <?php foreach ($inst['enderecos'] ?? [] as $e): ?>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md" data-id="<?php echo htmlspecialchars($e['id'] ?? ''); ?>">
                <div class="col"><?php echo htmlspecialchars(trim(($e['logradouro'] ?? '') . ($e['numero'] ? ' ' . $e['numero'] : '') . ($e['bairro'] ? ' - ' . $e['bairro'] : '') . ($e['cidade'] ? ' - ' . $e['cidade'] : '') . ($e['uf'] ? '/' . $e['uf'] : ''))); ?></div>
                <div class="col"><?php echo !empty($e['principal']) ? 'Principal' : '—'; ?></div>
                <div class="col"><button type="button" class="btn btn-primary-outline btn-del-end" data-id="<?php echo htmlspecialchars($e['id'] ?? ''); ?>">Excluir</button></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="new-end-cep">CEP</label>
                <input type="text" class="width-400 height-md white" id="new-end-cep" placeholder="00000-000" maxlength="9">
            </div>
            <div class="col">
                <label for="buscar-cep-btn" style="color: transparent;">-</label>
                <button type="button" class="btn btn-primary-outline" id="buscar-cep-btn">Buscar CEP</button>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="new-end-logradouro">Logradouro</label>
                <input type="text" class="width-600 height-md white" id="new-end-logradouro" placeholder="Rua, avenida...">
            </div>
            <div class="col">
                <label for="new-end-numero">Número</label>
                <input type="text" class="width-200 height-md white" id="new-end-numero" placeholder="Nº">
            </div>
            <div class="col">
                <label for="new-end-complemento">Complemento</label>
                <input type="text" class="width-400 height-md white" id="new-end-complemento" placeholder="Sala, andar...">
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="new-end-bairro">Bairro</label>
                <input type="text" class="width-400 height-md white" id="new-end-bairro" placeholder="Bairro">
            </div>
            <div class="col">
                <label for="new-end-cidade">Cidade</label>
                <input type="text" class="width-300 height-md white" id="new-end-cidade" placeholder="Cidade">
            </div>
            <div class="col">
                <label for="new-end-uf">UF</label>
                <input type="text" class="width-100 height-md white" id="new-end-uf" placeholder="UF" maxlength="2">
            </div>
            <label class="inline"><input type="checkbox" id="new-end-principal"> Principal</label>
            <button type="button" class="btn btn-secondary-outline" id="add-end-btn">Adicionar endereço</button>
        </div>

        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Telefones</h2></div>
        <div id="telefones-list">
            <?php foreach ($inst['telefones'] ?? [] as $t): ?>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md" data-id="<?php echo htmlspecialchars($t['id'] ?? ''); ?>">
                <div class="col"><?php echo htmlspecialchars($t['tipo'] ?? '—'); ?></div>
                <div class="col"><?php echo htmlspecialchars($t['numero'] ?? ''); ?></div>
                <div class="col"><?php echo htmlspecialchars($t['ramal'] ?? '—'); ?></div>
                <div class="col"><?php echo !empty($t['principal']) ? 'Principal' : '—'; ?></div>
                <div class="col"><button type="button" class="btn btn-secondary-outline btn-del-tel" data-id="<?php echo htmlspecialchars($t['id'] ?? ''); ?>">Excluir</button></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <input type="text" class="width-300 height-md white" id="new-tel-tipo" placeholder="Tipo">
            <input type="text" class="width-300 height-md white" id="new-tel-numero" placeholder="(00) 00000-0000">
            <input type="text" class="width-300 height-md white" id="new-tel-ramal" placeholder="Ramal">
            <label class="inline"><input type="checkbox" id="new-tel-principal"> Principal</label>
            <button type="button" class="btn btn-primary-outline" id="add-tel-btn">Adicionar telefone</button>
        </div>

        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Setores</h2></div>
        <div id="setores-list">
            <?php foreach ($inst['setores'] ?? [] as $s): ?>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md" data-id="<?php echo htmlspecialchars($s['id'] ?? ''); ?>">
                <div class="col"><?php echo htmlspecialchars($s['nome_setor'] ?? ''); ?></div>
                <div class="col"><?php echo htmlspecialchars($s['sigla'] ?? '—'); ?></div>
                <div class="col"><button type="button" class="btn btn-secondary-outline btn-del-setor" data-id="<?php echo htmlspecialchars($s['id'] ?? ''); ?>">Excluir</button></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <input type="text" class="width-600 height-md white" id="new-setor-nome" placeholder="Nome do setor *">
            <input type="text" class="width-300 height-md white" id="new-setor-sigla" placeholder="Sigla">
            <button type="button" class="btn btn-primary-outline" id="add-setor-btn">Adicionar setor</button>
        </div>

        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
            <button type="button" class="btn btn-primary" id="salvar-btn">Salvar</button>
        </div>

    </div>
    <script>
        (function () {
            if (!window.App || !App.InstituicoesPublicasEdit || typeof App.InstituicoesPublicasEdit.initialize !== 'function') {
                return;
            }

            App.InstituicoesPublicasEdit.initialize();
        })();
    </script>
</body>
</html>
