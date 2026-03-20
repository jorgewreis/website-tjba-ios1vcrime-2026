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
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Processo <?php echo htmlspecialchars($processo['numero_processo'] ?? ''); ?></title>
            <?php include __DIR__ . '/_internal_head_styles.php'; ?>

    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno - Consultas -</span> <span class="specific">Processo</span></div>
        <h1 class="title-page"><?php echo htmlspecialchars($processo['numero_processo'] ?? 'Processo'); ?></h1>
        <p class="description-page">Classe: <?php echo htmlspecialchars($processo['classe_nome'] ?? '—'); ?></p>
    </div>
    <div class="container-full card-page">
        <input type="hidden" id="processo-id" value="<?php echo htmlspecialchars($processo['id'] ?? ''); ?>">
        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Partes vinculadas</h2></div>
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
                            <a href="/frame.php?page=processospartesedit&id=<?php echo urlencode($p['id'] ?? ''); ?>" class="btn btn-secondary-outline">Editar vínculo</a>
                            <button type="button" class="btn btn-secondary-outline btn-desvincular" data-id="<?php echo htmlspecialchars($p['id'] ?? ''); ?>" data-processo-id="<?php echo htmlspecialchars($processo['id'] ?? ''); ?>">Desvincular</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="col subtask subtask-collapsed padding-lr-md full-line">
                <div class="display-flex row row-full justify-between padding-y-md border-top">
                    <div class="col no-data"><span class="major-info">Nenhuma parte vinculada.</span></div>
                </div>
            </div>
        <?php endif; ?>
        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <a href="/frame.php?page=consultaprocessos" class="btn btn-secondary">Voltar</a>
            <a href="/frame.php?page=processospartescreate&processo_id=<?php echo urlencode($processo['id'] ?? ''); ?>" class="btn btn-primary">Vincular parte</a>
        </div>
    </div>
    <script>
    function queuePostReloadToast(type, title, message) {
        try {
            var key = 'cartorio.postReloadToast';
            var raw = sessionStorage.getItem(key);
            var fila = raw ? JSON.parse(raw) : [];
            var lista = Array.isArray(fila) ? fila : ((fila && fila.message) ? [fila] : []);
            lista.push({ type: type, title: title, message: message });
            sessionStorage.setItem(key, JSON.stringify(lista));
        } catch (e) {}
    }
    var pendingConfirmations = {};
    function requireSecondClick(key, message) {
        var now = Date.now();
        var validUntil = Number(pendingConfirmations[key] || 0);
        if (validUntil > now) {
            delete pendingConfirmations[key];
            return true;
        }
        pendingConfirmations[key] = now + 5000;
        if (typeof showToast === 'function') showToast({ type: 'info', variant: 'full', title: 'Confirmação', message: message });
        return false;
    }
    document.querySelectorAll('.btn-desvincular').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var processoId = this.getAttribute('data-processo-id');
            if (!id || !processoId) return;
            if (!requireSecondClick('desvincular-processo-parte-' + id, 'Clique novamente em Desvincular em até 5 segundos para confirmar.')) return;
            this.disabled = true;
            fetch('/api/processos-partes/delete.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id }) })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.deleted) {
                        queuePostReloadToast('success', 'Sucesso', 'Vínculo removido com sucesso.');
                        window.location.reload();
                    }
                    else { if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: d.error || 'Erro.' }); btn.disabled = false; }
                })
                .catch(function() { btn.disabled = false; });
        });
    });
    </script>
</body>
</html>
