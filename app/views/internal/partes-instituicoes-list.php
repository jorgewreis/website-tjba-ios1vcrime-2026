<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../repositories/PartesInstituicoesRepository.php';
require_once __DIR__ . '/../../repositories/PartesRepository.php';
require_once __DIR__ . '/../../repositories/InstituicoesPublicasRepository.php';

$parteId = trim((string) ($_GET['parte_id'] ?? ''));
$instituicaoId = trim((string) ($_GET['instituicao_id'] ?? ''));

$connection = Conection::getInstance()->getConnection();
$repo = new PartesInstituicoesRepository($connection);
$rows = [];
$parteNome = '';
$instituicaoNome = '';

if ($parteId !== '') {
    $partesRepo = new PartesRepository($connection);
    $p = $partesRepo->findById($parteId);
    $parteNome = $p['nome_completo'] ?? $parteId;
    $rows = $repo->findByParteId($parteId);
} elseif ($instituicaoId !== '') {
    $instRepo = new InstituicoesPublicasRepository($connection);
    $inst = $instRepo->findById($instituicaoId);
    $instituicaoNome = $inst['nome'] ?? $instituicaoId;
    $rows = $repo->findByInstituicaoId($instituicaoId);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php include __DIR__ . '/_theme_init.php'; ?>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vínculos pessoa-instituição</title>
    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/color.css">
    <link rel="stylesheet" href="/assets/css/shared/page.css">
    <link rel="stylesheet" href="/assets/css/shared/form.css">
    <link rel="stylesheet" href="/assets/css/shared/table.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno - Consultas -</span> <span class="specific">Vínculos pessoa-instituição</span></div>
        <h1 class="title-page">Vínculos pessoa-instituição</h1>
        <p class="description-page">Consulte por pessoa (parte) ou por instituição.</p>
    </div>
    <div class="card-page">
        <form method="get" class="row filter" id="form-filters">
            <input type="hidden" name="page" value="partesinstituicoeslist">
            <div class="col filter">
                <label for="parte_id">ID da pessoa (parte)</label>
                <input type="text" class="width-md height-md" id="parte_id" name="parte_id" placeholder="Ex: PRT-..." value="<?php echo htmlspecialchars($parteId); ?>">
            </div>
            <div class="col filter">
                <label for="instituicao_id">ID da instituição</label>
                <input type="text" class="width-md height-md" id="instituicao_id" name="instituicao_id" placeholder="Ex: INS-..." value="<?php echo htmlspecialchars($instituicaoId); ?>">
            </div>
            <div class="col filter">
                <button type="submit" class="btn btn-primary">Consultar</button>
            </div>
        </form>
        <?php if ($parteId === '' && $instituicaoId === ''): ?>
            <div class="row table"><div class="col"><span class="major-info">Informe o ID da pessoa ou da instituição e clique em Consultar.</span></div></div>
        <?php elseif (!empty($rows)): ?>
            <div class="row table header"><?php echo $parteId !== '' ? 'Instituições vinculadas a ' . htmlspecialchars($parteNome) : 'Pessoas vinculadas a ' . htmlspecialchars($instituicaoNome); ?></div>
            <table class="table-list">
                <thead>
                    <tr>
                        <?php if ($instituicaoId !== ''): ?>
                            <th>Pessoa</th><th>CPF</th>
                        <?php endif; ?>
                        <?php if ($parteId !== ''): ?>
                            <th>Instituição</th>
                        <?php endif; ?>
                        <th>Cargo/Função</th><th>Data inclusão</th><th>Ativo</th><th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <?php if ($instituicaoId !== ''): ?>
                            <td><?php echo htmlspecialchars($r['parte_nome'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($r['parte_cpf'] ?? '—'); ?></td>
                        <?php endif; ?>
                        <?php if ($parteId !== ''): ?>
                            <td><?php echo htmlspecialchars($r['instituicao_nome'] ?? $r['instituicao_sigla'] ?? '—'); ?></td>
                        <?php endif; ?>
                        <td><?php echo htmlspecialchars($r['cargo_funcao'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($r['data_inclusao'] ?? '—'); ?></td>
                        <td><?php echo !empty($r['ativo']) ? 'Sim' : 'Não'; ?></td>
                        <td>
                            <a href="/frame.php?page=partesinstituicoesedit&id=<?php echo urlencode($r['id'] ?? ''); ?>" class="btn btn-secondary-outline">Editar</a>
                            <?php if ($parteId !== ''): ?>
                                <a href="/frame.php?page=consultapartesedit&id=<?php echo urlencode($r['parte_id'] ?? ''); ?>" class="btn btn-secondary-outline">Ver pessoa</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="row buttons">
                <a href="/frame.php?page=partesinstituicoescreate" class="btn btn-primary">Novo vínculo</a>
            </div>
        <?php else: ?>
            <div class="row table"><div class="col"><span class="major-info">Nenhum vínculo encontrado.</span></div></div>
            <div class="row buttons">
                <a href="/frame.php?page=partesinstituicoescreate" class="btn btn-primary">Novo vínculo</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
