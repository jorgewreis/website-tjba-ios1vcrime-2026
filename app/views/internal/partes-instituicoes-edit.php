<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../repositories/PartesInstituicoesRepository.php';
require_once __DIR__ . '/../../repositories/PartesRepository.php';
require_once __DIR__ . '/../../repositories/InstituicoesPublicasRepository.php';
require_once __DIR__ . '/../../repositories/InstituicoesSetoresRepository.php';

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') { header('Location: /frame.php?page=partesinstituicoeslist'); exit; }

$connection = Conection::getInstance()->getConnection();
$repo = new PartesInstituicoesRepository($connection);
$vinculo = $repo->findById($id);
if ($vinculo === null) { header('Location: /frame.php?page=partesinstituicoeslist'); exit; }

$partesRepo = new PartesRepository($connection);
$instRepo = new InstituicoesPublicasRepository($connection);
$parte = $partesRepo->findById((string) ($vinculo['parte_id'] ?? ''));
$inst = $instRepo->findById((string) ($vinculo['instituicao_id'] ?? ''));
$parteNome = $parte['nome_completo'] ?? $vinculo['parte_id'] ?? '—';
$instNome = $inst['nome'] ?? $vinculo['instituicao_id'] ?? '—';

$setores = [];
if (!empty($vinculo['instituicao_id'])) {
    $setoresRepo = new InstituicoesSetoresRepository($connection);
    $setores = $setoresRepo->findByInstituicaoId((string) $vinculo['instituicao_id']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar vínculo pessoa-instituição</title>
            <?php include __DIR__ . '/_internal_head_styles.php'; ?>

    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno - Consultas -</span> <span class="specific">Editar vínculo</span></div>
        <h1 class="title-page">Editar vínculo: <?php echo htmlspecialchars($parteNome); ?> — <?php echo htmlspecialchars($instNome); ?></h1>
    </div>
    <div class="container-full card-page">
        <input type="hidden" id="vinculo-id" value="<?php echo htmlspecialchars($vinculo['id'] ?? ''); ?>">
        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Dados do vínculo</h2></div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col"><strong>Pessoa:</strong> <?php echo htmlspecialchars($parteNome); ?> <button type="button" class="btn btn-secondary-outline" data-nav-href="/frame.php?page=consultapartesedit&id=<?php echo urlencode($vinculo['parte_id'] ?? ''); ?>">Ver pessoa</button></div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col"><strong>Instituição:</strong> <?php echo htmlspecialchars($instNome); ?></div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="setor_id">Setor</label>
                <select class="width-200 height-md white" id="setor_id">
                    <option value="">— Nenhum —</option>
                    <?php foreach ($setores as $s): ?>
                        <option value="<?php echo htmlspecialchars($s['id'] ?? ''); ?>"<?php echo (($vinculo['setor_id'] ?? '') === ($s['id'] ?? '')) ? ' selected' : ''; ?>><?php echo htmlspecialchars($s['nome_setor'] ?? $s['sigla'] ?? $s['id'] ?? ''); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col">
                <label for="profissao">Profissão</label>
                <input type="text" class="width-200 height-md white" id="profissao" maxlength="120" value="<?php echo htmlspecialchars($vinculo['profissao'] ?? ''); ?>">
            </div>
            <div class="col">
                <label for="cargo_funcao">Cargo / Função</label>
                <input type="text" class="width-200 height-md white" id="cargo_funcao" value="<?php echo htmlspecialchars($vinculo['cargo_funcao'] ?? ''); ?>">
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="data_inclusao">Data de inclusão <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="date" class="width-200 height-md white" id="data_inclusao" value="<?php echo htmlspecialchars($vinculo['data_inclusao'] ?? ''); ?>" required>
            </div>
            <div class="col">
                <label for="data_inicio">Data início</label>
                <input type="date" class="width-200 height-md white" id="data_inicio" value="<?php echo htmlspecialchars($vinculo['data_inicio'] ?? ''); ?>">
            </div>
            <div class="col">
                <label for="data_fim">Data fim</label>
                <input type="date" class="width-200 height-md white" id="data_fim" value="<?php echo htmlspecialchars($vinculo['data_fim'] ?? ''); ?>">
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label><input type="checkbox" id="ativo"<?php echo !empty($vinculo['ativo']) ? ' checked' : ''; ?>> Ativo</label>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="observacoes">Observações</label>
                <textarea class="width-600 height-md white" id="observacoes" rows="2"><?php echo htmlspecialchars($vinculo['observacoes'] ?? ''); ?></textarea>
            </div>
        </div>
        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button type="button" class="btn btn-secondary" data-nav-href="/frame.php?page=partesinstituicoeslist">Voltar</button>
            <button type="button" class="btn btn-primary" id="salvar-btn">Salvar</button>
        </div>
    </div>
    <script>
        (function () {
            if (!window.App || !App.PartesInstituicoesEdit || typeof App.PartesInstituicoesEdit.initialize !== 'function') {
                return;
            }

            App.PartesInstituicoesEdit.initialize();
        })();
    </script>
</body>
</html>
