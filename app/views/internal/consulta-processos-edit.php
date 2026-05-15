<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../repositories/SalaAudienciasPautaParteRepository.php';
require_once __DIR__ . '/../../services/ConsultaProcessosService.php';
require_once __DIR__ . '/../../services/ProcessoPrioridadesService.php';

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') { header('Location: /frame.php?page=consultaprocessos'); exit; }
$connection = Conection::getInstance()->getConnection();
$service = new ConsultaProcessosService($connection);
$processo = $service->get($id);
if ($processo === null) { header('Location: /frame.php?page=consultaprocessos'); exit; }

$partes = $processo['partes'] ?? [];
$assuntosFicha = is_array($processo['assuntos'] ?? null) ? $processo['assuntos'] : [];
$processoIdFicha = trim((string) ($processo['id'] ?? ''));
$nMenoresPauta = $processoIdFicha !== ''
    ? (new SalaAudienciasPautaParteRepository($connection))->countPartesMenoresAtivasPorProcesso($processoIdFicha)
    : 0;
$prioridadesFichaEstado = $processoIdFicha !== ''
    ? (new ProcessoPrioridadesService($connection))->getEstadoEdicaoPorProcessoId($processoIdFicha, $nMenoresPauta)
    : null;
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
        <div class="display-flex row row-full justify-between align-center flex-wrap gap-y-md" style="gap: 12px;">
            <div class="display-flex col" style="width: auto; flex: 1 1 auto;">
                <h1 class="title-page"><?php echo htmlspecialchars($processo['numero_processo'] ?? 'Processo'); ?></h1>
                <p class="description-page" style="margin-top: 0;">Classe: <?php echo htmlspecialchars($processo['classe_nome'] ?? '—'); ?></p>
            </div>
            <div class="display-flex row row-fit align-center justify-end gap-y-md">
                <button type="button" class="btn btn-primary-outline" data-nav-href="/frame.php?page=processosedit&id=<?php echo urlencode($processo['id'] ?? ''); ?>">Editar processo</button>
            </div>
        </div>
    </div>
    <div class="container-full card-page">
        <input type="hidden" id="processo-id" value="<?php echo htmlspecialchars($processo['id'] ?? ''); ?>">
        <?php if ($prioridadesFichaEstado !== null): ?>
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Prioridades processuais</h2>
        </div>
        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-md" id="prioridades-processuais-wrap">
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="prioridade-catalog-select">Incluir prioridade</label>
                    <select class="width-500 height-md" id="prioridade-catalog-select">
                        <option value="" selected disabled>Selecione...</option>
                    </select>
                </div>
                <button type="button" class="btn btn-primary height-md" id="btn-prioridade-adicionar">Adicionar</button>
            </div>
            <div id="prioridades-processuais-list" class="display-flex col col-full gap-y-sm" aria-live="polite"></div>
        </div>
        <?php endif; ?>
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Assuntos processuais</h2>
        </div>
        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-md" id="processo-assuntos-wrap">
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="processo-assunto-codigo">Código do assunto</label>
                    <input type="text" id="processo-assunto-codigo" class="height-md" placeholder="Ex.: 10247" maxlength="20" autocomplete="off" title="Consulta automática após 1 s sem digitar; Enter consulta na hora.">
                </div>
                <div class="col width-500">
                    <label for="processo-assunto-descricao">Descrição do assunto</label>
                    <input type="text" id="processo-assunto-descricao" class="height-md" placeholder="Preenchida automaticamente se o código existir no catálogo" readonly>
                </div>
                <button type="button" class="btn btn-primary height-md" id="btn-processo-assunto-vincular">Vincular assunto</button>
            </div>
            <div id="processo-assuntos-list" class="display-flex col col-full gap-y-sm" aria-live="polite"></div>
        </div>
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
                            <button type="button" class="btn btn-secondary-outline" data-nav-href="/frame.php?page=consultapartesedit&id=<?php echo urlencode($p['parte_id'] ?? ''); ?>">Ver pessoa</button>
                            <button type="button" class="btn btn-secondary-outline" data-nav-href="/frame.php?page=processospartesedit&id=<?php echo urlencode($p['id'] ?? ''); ?>">Editar vínculo</button>
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
            <button type="button" class="btn btn-secondary" data-nav-href="/frame.php?page=consultaprocessos">Voltar</button>
            <button type="button" class="btn btn-primary" data-nav-href="/frame.php?page=processospartescreate&processo_id=<?php echo urlencode($processo['id'] ?? ''); ?>">Vincular parte</button>
        </div>
    </div>
    <?php if ($prioridadesFichaEstado !== null): ?>
    <script>
        window.PROCESSO_PRIORIDADES_PROCESSO_ID = <?php echo json_encode($processoIdFicha, JSON_UNESCAPED_UNICODE); ?>;
        window.PROCESSO_PRIORIDADES_INITIAL = <?php echo json_encode($prioridadesFichaEstado, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="/assets/js/modules/audiencias/pauta-edit-prioridades.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/pauta-edit-prioridades.js') ?: time(); ?>"></script>
    <?php endif; ?>
    <script>
        window.PROCESSO_ASSUNTOS_PROCESSO_ID = <?php echo json_encode($processoIdFicha, JSON_UNESCAPED_UNICODE); ?>;
        window.PROCESSO_ASSUNTOS_INITIAL = <?php echo json_encode($assuntosFicha, JSON_UNESCAPED_UNICODE); ?>;
        window.PROCESSO_ASSUNTO_FIND_ENDPOINT = '/api/assuntos/find-by-codigo.php';
        window.PROCESSO_ASSUNTO_LINK_ENDPOINT = '/api/processos/assunto-link.php';
        window.PROCESSO_ASSUNTO_UNLINK_ENDPOINT = '/api/processos/assunto-unlink.php';
    </script>
    <script>
        (function () {
            if (!window.App || !App.ConsultaProcessosEdit || typeof App.ConsultaProcessosEdit.initialize !== 'function') {
                return;
            }

            App.ConsultaProcessosEdit.initialize();
        })();
    </script>
</body>
</html>
