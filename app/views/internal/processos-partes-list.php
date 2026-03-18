<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/ConsultaProcessosService.php';

$processoId = trim((string) ($_GET['processo_id'] ?? ''));
$connection = Conection::getInstance()->getConnection();
$partes = [];
$processo = null;

if ($processoId !== '') {
    $service = new ConsultaProcessosService($connection);
    $processo = $service->get($processoId);
    if ($processo !== null) {
        $partes = $processo['partes'] ?? [];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vínculos processo-parte</title>
            <?php include __DIR__ . '/_internal_head_styles.php'; ?>

    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno - Consultas -</span> <span class="specific">Vínculos processo-parte</span></div>
        <h1 class="title-page">Vínculos processo-parte</h1>
        <p class="description-page">Informe o ID do processo para listar as partes vinculadas.</p>
    </div>
    <div class="card-page">

        <!-- FILTROS -->
        <form method="get" id="form-filters">
            <input type="hidden" name="page" value="processosparteslist">
            <div class="row table between padding-tb-lg padding-lr-md border-bottom">
                <div class="row left row-gap-10">
                    <div class="col">
                        <label for="processo_id">ID do processo</label>
                        <input type="text" class="width-md height-md white" id="processo_id" name="processo_id" placeholder="Ex: PRC-..." value="<?php echo htmlspecialchars($processoId); ?>">
                    </div>
                    <div class="col">
                        <button type="submit" class="btn btn-primary">Consultar</button>
                    </div>
                </div>
            </div>
        </form>

        <?php if ($processoId !== '' && $processo === null): ?>
            <div class="col subtask subtask-collapsed padding-lr-md full-line">
                <div class="row table between padding-tb-md border-top">
                    <div class="col table left no-data"><span class="major-info">Processo não encontrado.</span></div>
                </div>
            </div>
        <?php elseif (!empty($partes)): ?>
            <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Partes do processo <?php echo htmlspecialchars($processo['numero_processo'] ?? $processoId); ?></div>
            <table class="table-list">
                <thead><tr><th>Nome</th><th>CPF</th><th>Tipo</th><th>Polo</th><th>Ações</th></tr></thead>
                <tbody>
                    <?php foreach ($partes as $p): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($p['nome_completo'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($p['cpf'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($p['tipo_parte'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($p['polo'] ?? '—'); ?></td>
                        <td>
                            <a href="/frame.php?page=consultapartesedit&id=<?php echo urlencode($p['parte_id'] ?? ''); ?>" class="btn btn-secondary-outline">Ver pessoa</a>
                            <a href="/frame.php?page=processospartesedit&id=<?php echo urlencode($p['id'] ?? ''); ?>" class="btn btn-secondary-outline">Editar vínculo</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="row table right buttons padding-tb-md padding-lr-lg border-top row-gap-10">
                <a href="/frame.php?page=consultaprocessosedit&id=<?php echo urlencode($processoId); ?>" class="btn btn-primary">Abrir processo</a>
                <a href="/frame.php?page=processospartescreate&processo_id=<?php echo urlencode($processoId); ?>" class="btn btn-secondary">Vincular parte</a>
            </div>
        <?php elseif ($processoId !== ''): ?>
            <div class="col subtask subtask-collapsed padding-lr-md full-line">
                <div class="row table between padding-tb-md border-top">
                    <div class="col table left no-data"><span class="major-info">Nenhuma parte vinculada a este processo.</span></div>
                </div>
            </div>
            <div class="row table right buttons padding-tb-md padding-lr-lg border-top row-gap-10">
                <a href="/frame.php?page=processospartescreate&processo_id=<?php echo urlencode($processoId); ?>" class="btn btn-primary">Vincular parte</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
