<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/ConsultaProcessosService.php';
require_once __DIR__ . '/../../repositories/ProcessosPartesRepository.php';

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') {
    header('Location: /frame.php?page=consultaprocessos');
    exit;
}

$connection = Conection::getInstance()->getConnection();
$service = new ConsultaProcessosService($connection);
$processo = $service->get($id);
if ($processo === null) {
    header('Location: /frame.php?page=consultaprocessos');
    exit;
}

$partes = $processo['partes'] ?? [];

$ordemTiposPartes = [
    'reu' => 0,
    'indiciado' => 1,
    'sentenciado' => 2,
    'requerido' => 3,
    'vitima' => 4,
    'requerente' => 5,
    'testemunha_denuncia' => 6,
    'testemunha_defesa' => 7,
    'advogado' => 8,
    'defensor_publico' => 9,
];
$tipoPartesLabels = [
    'reu' => 'Réu',
    'indiciado' => 'Indiciado',
    'sentenciado' => 'Sentenciado',
    'requerido' => 'Requerido',
    'vitima' => 'Vítima',
    'requerente' => 'Requerente',
    'testemunha_denuncia' => 'Testemunha de denúncia',
    'testemunha_defesa' => 'Testemunha de defesa',
    'advogado' => 'Advogado',
    'defensor_publico' => 'Defensor público',
];

$partesPorTipo = [];
foreach ($partes as $parte) {
    $tipo = (string) ($parte['tipo_parte'] ?? '');
    if ($tipo === '') {
        continue;
    }
    if (!isset($partesPorTipo[$tipo])) {
        $partesPorTipo[$tipo] = [];
    }
    $partesPorTipo[$tipo][] = $parte;
}
uksort($partesPorTipo, static function (string $a, string $b) use ($ordemTiposPartes): int {
    $ia = $ordemTiposPartes[$a] ?? 999;
    $ib = $ordemTiposPartes[$b] ?? 999;
    if ($ia === $ib) {
        return strcmp($a, $b);
    }

    return $ia <=> $ib;
});
foreach ($partesPorTipo as $tipo => $lista) {
    usort($partesPorTipo[$tipo], static function (array $a, array $b): int {
        return strcmp((string) ($a['nome_completo'] ?? ''), (string) ($b['nome_completo'] ?? ''));
    });
}

$polosLabels = [
    'ativo' => 'Polo ativo',
    'passivo' => 'Polo passivo',
];

$classeNomeAtual = trim((string) ($processo['classe_nome'] ?? ''));
$processoIdRaw = (string) ($processo['id'] ?? '');
$numeroProcesso = (string) ($processo['numero_processo'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Editar processo - <?php echo htmlspecialchars($numeroProcesso, ENT_QUOTES); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>
    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Consultas - Processo -</span>
            <span class="specific">Editar</span>
        </div>
        <h1 class="title-page"><?php echo htmlspecialchars($numeroProcesso !== '' ? $numeroProcesso : 'Processo', ENT_QUOTES); ?></h1>
        <p class="description-page">Edição manual da classe processual e visualização das partes vinculadas.</p>
    </div>

    <div class="container-full card-page">

        <input type="hidden" id="proc-id" value="<?php echo htmlspecialchars($processoIdRaw, ENT_QUOTES); ?>">

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2 style="margin: 0;">Identificação</h2>
        </div>

        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-md">

            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap: 12px;">
                <div class="col">
                    <label for="proc-numero">Número do processo</label>
                    <input type="text" class="width-500 height-md" id="proc-numero" value="<?php echo htmlspecialchars($numeroProcesso, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col">
                    <label for="proc-classe-atual">Classe vinculada atualmente</label>
                    <input type="text" class="width-500 height-md" id="proc-classe-atual" value="<?php echo htmlspecialchars($classeNomeAtual !== '' ? $classeNomeAtual : '—', ENT_QUOTES); ?>" readonly>
                </div>
            </div>

            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap: 12px;">
                <div class="col">
                    <label for="proc-classe-codigo">Código da classe processual</label>
                    <input type="text" class="width-300 height-md" id="proc-classe-codigo" placeholder="Ex.: 283" maxlength="20" autocomplete="off">
                </div>
                <button type="button" class="btn btn-primary-outline height-md" id="btn-proc-classe-buscar">Buscar</button>
            </div>

            <div class="display-flex col col-full gap-y-md" id="proc-classe-resultado" style="display: none;">

                <div class="display-flex row row-full flex-wrap align-end gap-y-md" id="proc-classe-bloco-existente" style="display: none; gap: 12px;">
                    <div class="col">
                        <label for="proc-classe-nome-existente">Classe encontrada no banco</label>
                        <input type="text" class="width-500 height-md" id="proc-classe-nome-existente" readonly>
                    </div>
                    <button type="button" class="btn btn-primary height-md" id="btn-proc-classe-vincular">Vincular ao processo</button>
                </div>

                <div class="display-flex row row-full flex-wrap align-end gap-y-md" id="proc-classe-bloco-novo" style="display: none; gap: 12px;">
                    <div class="col">
                        <label for="proc-classe-nome-novo">Nome da classe (não cadastrada)</label>
                        <input type="text" class="width-500 height-md" id="proc-classe-nome-novo" maxlength="200" placeholder="Informe o nome para cadastrar a classe">
                    </div>
                    <button type="button" class="btn btn-primary height-md" id="btn-proc-classe-cadastrar">Cadastrar classe e vincular</button>
                </div>

            </div>

        </div>

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2 style="margin: 0;">Partes cadastradas</h2>
        </div>

        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-md">
            <?php if (empty($partesPorTipo)): ?>
                <p class="minor-info" style="margin: 0;">Nenhuma parte vinculada ao processo.</p>
            <?php else: ?>
                <?php foreach ($partesPorTipo as $tipo => $listaPartes):
                    $labelTipo = $tipoPartesLabels[$tipo] ?? ucfirst(str_replace('_', ' ', (string) $tipo));
                    ?>
                <div class="display-flex col col-full gap-y-sm">
                    <span class="minor-info" style="font-weight: 600; color: var(--color-primary-active); font-size: 13px;"><?php echo htmlspecialchars($labelTipo, ENT_QUOTES); ?></span>
                    <ul class="display-flex col col-full" style="margin: 0; padding: 0; list-style: none;">
                        <?php foreach ($listaPartes as $parte):
                            $nomeParte = trim((string) ($parte['nome_completo'] ?? ''));
                            $cpfParte = trim((string) ($parte['cpf'] ?? ''));
                            $poloParte = (string) ($parte['polo'] ?? '');
                            $labelPolo = $polosLabels[$poloParte] ?? ($poloParte !== '' ? ucfirst($poloParte) : '—');
                            ?>
                        <li class="display-flex row row-full align-center justify-between border-bottom padding-y-sm" style="gap: 12px;">
                            <span class="major-info" style="flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($nomeParte !== '' ? $nomeParte : '—', ENT_QUOTES); ?></span>
                            <span class="minor-info width-200" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($cpfParte !== '' ? $cpfParte : '—', ENT_QUOTES); ?></span>
                            <span class="minor-info width-200" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($labelPolo, ENT_QUOTES); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md">
            <button type="button" class="btn btn-secondary" data-nav-href="/frame.php?page=consultaprocessosedit&id=<?php echo urlencode($processoIdRaw); ?>">Voltar</button>
        </div>

    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <script src="/assets/js/shared/processos-edit.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/shared/processos-edit.js') ?: time(); ?>"></script>
    <script>
        (function () {
            if (!window.App || !App.ProcessosEdit || typeof App.ProcessosEdit.initialize !== 'function') {
                return;
            }

            App.ProcessosEdit.initialize();
        })();
    </script>

</body>
</html>
