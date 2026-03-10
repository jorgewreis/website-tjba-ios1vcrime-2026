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
    <?php include __DIR__ . '/_theme_init.php'; ?>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar vínculo pessoa-instituição</title>
    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/color.css">
    <link rel="stylesheet" href="/assets/css/shared/page.css">
    <link rel="stylesheet" href="/assets/css/shared/form.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
    <script src="/assets/js/shared/text-formatter.js"></script>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno - Consultas -</span> <span class="specific">Editar vínculo</span></div>
        <h1 class="title-page">Editar vínculo: <?php echo htmlspecialchars($parteNome); ?> — <?php echo htmlspecialchars($instNome); ?></h1>
    </div>
    <div class="card-page">
        <input type="hidden" id="vinculo-id" value="<?php echo htmlspecialchars($vinculo['id'] ?? ''); ?>">
        <div class="row table header">Dados do vínculo</div>
        <div class="row filter">
            <div class="col filter"><strong>Pessoa:</strong> <?php echo htmlspecialchars($parteNome); ?> <a href="/frame.php?page=consultapartesedit&id=<?php echo urlencode($vinculo['parte_id'] ?? ''); ?>" class="btn btn-secondary-outline">Ver pessoa</a></div>
        </div>
        <div class="row filter">
            <div class="col filter"><strong>Instituição:</strong> <?php echo htmlspecialchars($instNome); ?></div>
        </div>
        <div class="row filter">
            <div class="col filter">
                <label for="setor_id">Setor</label>
                <select class="width-md height-md" id="setor_id">
                    <option value="">— Nenhum —</option>
                    <?php foreach ($setores as $s): ?>
                        <option value="<?php echo htmlspecialchars($s['id'] ?? ''); ?>"<?php echo (($vinculo['setor_id'] ?? '') === ($s['id'] ?? '')) ? ' selected' : ''; ?>><?php echo htmlspecialchars($s['nome_setor'] ?? $s['sigla'] ?? $s['id'] ?? ''); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col filter">
                <label for="profissao">Profissão</label>
                <input type="text" class="width-md height-md" id="profissao" value="<?php echo htmlspecialchars($vinculo['profissao'] ?? ''); ?>">
            </div>
            <div class="col filter">
                <label for="cargo_funcao">Cargo / Função</label>
                <input type="text" class="width-md height-md" id="cargo_funcao" value="<?php echo htmlspecialchars($vinculo['cargo_funcao'] ?? ''); ?>">
            </div>
        </div>
        <div class="row filter">
            <div class="col filter">
                <label for="data_inclusao">Data de inclusão *</label>
                <input type="date" class="width-md height-md" id="data_inclusao" value="<?php echo htmlspecialchars($vinculo['data_inclusao'] ?? ''); ?>" required>
            </div>
            <div class="col filter">
                <label for="data_inicio">Data início</label>
                <input type="date" class="width-md height-md" id="data_inicio" value="<?php echo htmlspecialchars($vinculo['data_inicio'] ?? ''); ?>">
            </div>
            <div class="col filter">
                <label for="data_fim">Data fim</label>
                <input type="date" class="width-md height-md" id="data_fim" value="<?php echo htmlspecialchars($vinculo['data_fim'] ?? ''); ?>">
            </div>
        </div>
        <div class="row filter">
            <div class="col filter">
                <label><input type="checkbox" id="ativo"<?php echo !empty($vinculo['ativo']) ? ' checked' : ''; ?>> Ativo</label>
            </div>
        </div>
        <div class="row filter">
            <div class="col filter">
                <label for="observacoes">Observações</label>
                <textarea class="width-xl height-md" id="observacoes" rows="2"><?php echo htmlspecialchars($vinculo['observacoes'] ?? ''); ?></textarea>
            </div>
        </div>
        <div class="row buttons">
            <a href="/frame.php?page=partesinstituicoeslist" class="btn btn-secondary">Voltar</a>
            <button type="button" class="btn btn-primary" id="salvar-btn">Salvar</button>
        </div>
    </div>
    <script>
    document.getElementById('salvar-btn').addEventListener('click', function() {
        var id = document.getElementById('vinculo-id').value;
        var dataInclusao = (document.getElementById('data_inclusao').value || '').trim();
        if (!dataInclusao) {
            if (typeof showToast === 'function') showToast({ type: 'error', message: 'Data de inclusão é obrigatória.' });
            return;
        }
        var payload = {
            id: id,
            setor_id: (document.getElementById('setor_id').value || '').trim() || null,
            profissao: (document.getElementById('profissao').value || '').trim() || null,
            cargo_funcao: (document.getElementById('cargo_funcao').value || '').trim() || null,
            data_inclusao: dataInclusao,
            data_inicio: (document.getElementById('data_inicio').value || '').trim() || null,
            data_fim: (document.getElementById('data_fim').value || '').trim() || null,
            ativo: document.getElementById('ativo').checked,
            observacoes: (document.getElementById('observacoes').value || '').trim() || null
        };
        this.disabled = true;
        fetch('/api/pessoas/vinculos-instituicoes/update.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) {
                    if (typeof showToast === 'function') showToast({ type: 'success', message: 'Vínculo atualizado.' });
                    window.location.href = '/frame.php?page=partesinstituicoeslist';
                } else {
                    if (typeof showToast === 'function') showToast({ type: 'error', message: d.error || 'Erro.' });
                    document.getElementById('salvar-btn').disabled = false;
                }
            })
            .catch(function() { document.getElementById('salvar-btn').disabled = false; });
    });
    </script>
</body>
</html>
