<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/ConsultaProcessosService.php';

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') { header('Location: /frame.php?page=consultaprocessos'); exit; }
$connection = Conection::getInstance()->getConnection();
$service = new ConsultaProcessosService($connection);
$processo = $service->get($id);
if ($processo === null) { header('Location: /frame.php?page=consultaprocessos'); exit; }

$partes = $processo['partes'] ?? [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php include __DIR__ . '/_theme_init.php'; ?>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Processo <?php echo htmlspecialchars($processo['numero_processo'] ?? ''); ?></title>
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
        <div class="location"><span class="all">Interno - Consultas -</span> <span class="specific">Processo</span></div>
        <h1 class="title-page"><?php echo htmlspecialchars($processo['numero_processo'] ?? 'Processo'); ?></h1>
        <p class="description-page">Classe: <?php echo htmlspecialchars($processo['classe_nome'] ?? '—'); ?></p>
    </div>
    <div class="card-page">
        <input type="hidden" id="processo-id" value="<?php echo htmlspecialchars($processo['id'] ?? ''); ?>">
        <div class="row table header">Partes vinculadas</div>
        <?php if (!empty($partes)): ?>
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
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="row table"><div class="col"><span class="major-info">Nenhuma parte vinculada.</span></div></div>
        <?php endif; ?>
        <div class="row buttons">
            <a href="/frame.php?page=consultaprocessos" class="btn btn-secondary">Voltar</a>
            <a href="/frame.php?page=processospartescreate&processo_id=<?php echo urlencode($processo['id'] ?? ''); ?>" class="btn btn-primary">Vincular parte</a>
        </div>
    </div>
</body>
</html>
