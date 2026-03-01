<?php
declare(strict_types=1);

// Bloco de conexão e consulta (adaptado de backup/guias.php)
$connection = null;
require_once __DIR__ . '/../class/Connect.php';

if (!isset($connection) || !($connection instanceof mysqli)) {
    die('Conexão inválida. Verifique Connect.php');
}

$connection->set_charset('utf8mb4');
$connection->query("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
$connection->query("SET collation_connection = 'utf8mb4_unicode_ci'");
if (!$connection->select_db('ios1_users')) {
    die('Erro ao selecionar banco de dados.');
}

$sql = "SELECT `id_guia`, `processo`, `tipo`, `denunciado`, `status`, `etapa`, `urgencia`, `data_cadastro`
        FROM guia
        ORDER BY `data_cadastro` DESC";

$hasQueryError = false;
$q = $connection->query($sql);
if ($q === false) {
    $mysqlErrorMsg = $connection->error;
    error_log('Erro na consulta SQL em new/guias.php: ' . $mysqlErrorMsg);
    $countGuia = 0;
    $hasQueryError = true;
} else {
    $hasQueryError = false;
    $countGuia = (int)$q->num_rows;
}

$tzApp = new DateTimeZone('America/Bahia');
$parseCadastro = static function ($raw) use ($tzApp): DateTimeImmutable {
    $raw = trim((string)$raw);
    if ($raw === '') {
        return new DateTimeImmutable('now', $tzApp);
    }
    if (ctype_digit($raw)) {
        return (new DateTimeImmutable("@{$raw}"))->setTimezone($tzApp);
    }
    if (preg_match('/[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+\-]\d{2}:?\d{2})$/', $raw)) {
        $dt = new DateTimeImmutable($raw);
        return $dt->setTimezone($tzApp);
    }
    $formats = ['Y-m-d H:i:s.u', 'Y-m-d H:i:s', 'd/m/Y H:i:s'];
    foreach ($formats as $fmt) {
        $dt = DateTimeImmutable::createFromFormat($fmt, $raw, $tzApp);
        if ($dt instanceof DateTimeImmutable) return $dt;
    }
    return new DateTimeImmutable('now', $tzApp);
};

$humanDiff = static function (int $diffSeconds): string {
    if ($diffSeconds < 0) $diffSeconds = 0;
    if ($diffSeconds < 60) return $diffSeconds . ' seg';
    if ($diffSeconds < 3600) return (string)floor($diffSeconds / 60) . ' min';
    if ($diffSeconds < 86400) return (string)floor($diffSeconds / 3600) . ' h';
    if ($diffSeconds < 604800) {
        $dias = (int)floor($diffSeconds / 86400);
        return $dias . ' dia' . ($dias > 1 ? 's' : '');
    }
    if ($diffSeconds < 2592000) return (string)floor($diffSeconds / 604800) . ' sem';
    if ($diffSeconds < 31536000) {
        $meses = (int)floor($diffSeconds / 2592000);
        return $meses . ' mês' . ($meses > 1 ? 'es' : '');
    }
    $anos = (int)floor($diffSeconds / 31536000);
    return $anos . ' ano' . ($anos > 1 ? 's' : '');
};

$tipoLabels = [
    'Guia - Tipo não informado'      => 'Guia - Tipo não informado',
    'Guia (corrigir)' => 'Guia - Corrigir',
    'Guia (reenviar)' => 'Guia - Reenviar',
    'Guia de Execução Definitiva'  => 'Guia de Execução Definitiva',
    'Guia de Recolhimento Provisria'  => 'Guia de Recolhimento Provisória',
    'Guia de Recolhimento Definitiva'  => 'Guia de Recolhimento Definitiva',
];

$allowedActionsByStatus = static function (string $status): array {
    $s = mb_strtolower(trim($status), 'UTF-8');
    switch ($s) {
        case 'aguardando detalhamento': return ['doc-excluir'];
        case 'aguardando expedição': return ['doc-expedir', 'doc-excluir'];
        case 'aguardando assinatura da juíza': return ['doc-assinar', 'doc-enviar', 'doc-excluir'];
        case 'aguardando correção': return ['doc-expedir', 'doc-excluir'];
        case 'aguardando envio':
        case 'aguardando reenvio': return ['doc-enviar', 'doc-excluir'];
        case 'aguardando juntada de termo':
        case 'aguardando juntada do termo': return ['doc-juntar', 'doc-excluir'];
        default: return ['doc-excluir'];
    }
};

// Prepared statement to fetch classe description when available (copiado de guias.php)
$sqlClasse = "SELECT c.descricao
              FROM processos p
              LEFT JOIN classes c ON p.classe = c.cod
              WHERE p.numero = ?
              LIMIT 1";
$stmtClasse = $connection->prepare($sqlClasse);

// Buscar servidores do cartório (Usuarios com UserCargo = 'Servidor')
$servidores = [];
$rsServ = $connection->query("SELECT UserId, UserNome FROM Usuarios WHERE UserCargo = 'Servidor' ORDER BY UserNome");
if ($rsServ) {
    while ($r = $rsServ->fetch_assoc()) {
        $servidores[] = $r;
    }
    $rsServ->free();
}

// Prepared statement para buscar servidores alocados por guia
$sqlServAlloc = "SELECT gs.id_user, u.UserNome
                FROM guia_servidores gs
                LEFT JOIN Usuarios u ON u.UserId = gs.id_user
                WHERE gs.id_guia = ?
                ORDER BY u.UserNome";
$stmtServAlloc = $connection->prepare($sqlServAlloc);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guias</title>
    <!-- Reusar estilos do layout Cartas Expedidas -->
    <link rel="stylesheet" href="./../css/pages.css">
    <link rel="stylesheet" href="./../css/structure.css">
    <link rel="stylesheet" href="./../css/form.css">
    <link rel="stylesheet" href="./../css/table.css">
    <script type="text/javascript" src="./../js/common.js"></script>
</head>
<body class="page" data-theme="light">
    <div class="container">
        <div class="modal page full">
            <div class="left">
                <span>cartório | guias | lista</span>
                <span class="title">Guias</span>
            </div>
            <div class="right">
                <img src="./../img/icones/Folder-Text--Streamline-Ultimate.png" alt="Folder">
            </div>
        </div>

        <div class="layout">
            <div class="left">
                <div class="modal form">
                    <div class="title-bar"><span class="title-name">Novo cadastro</span></div>
                    <!-- Formulário de criação copiado de backup/guias.php -->
                    <form class="document-create" name="docCreate" action="./../fast-request/crt-guias-create.php" method="post" autocomplete="off">
                        <div class="form-line">
                            <div class="form-box">
                                <label for="doc-class">Tipo de Guia</label>
                                <select class="medio" name="doc-class" id="doc-class" required>
                                    <option value="" selected disabled>Selecione aqui</option>
                                    <option value="Guia - Tipo não informado">Guia Nova</option>
                                    <option value="Guia (corrigir)">Guia com erros - Refazer</option>
                                    <option value="Guia (reenviar)">Guia faltando peças - Reenviar</option>
                                </select>
                                <input class="pequeno" type="hidden" id="doc-user" name="doc-user">
                            </div>                        
                            <div class="form-box">
                                <label for="doc-process">Processo</label>
                                <input class="medio" type="text" id="doc-process" name="doc-process" required inputmode="numeric" placeholder="0000000-00.0000.0.00.0000">
                            </div>
                        </div>
                        <div class="form-line">
                            <div class="form-box">
                                <label for="doc-denunciado">Denunciado</label>
                                <input class="grande" type="text" id="doc-denunciado" name="doc-denunciado" required>
                            </div>                        
                            <div class="form-box">
                                <label for="doc-prio">Importância</label>
                                <select class="pequeno" name="doc-prio" id="doc-prio" required>
                                    <option value="0" selected>Normal</option>
                                    <option value="1">Importante</option>
                                    <option value="2">Urgente</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-line">
                            <div class="form-box">
                                <label for="doc-servers">Servidores (alocar)</label>
                                <select name="doc-servers[]" id="doc-servers" class="grande" multiple size="5" aria-label="Servidores" style="height: auto;">
                                    <?php
                                    foreach ($servidores as $srv) {
                                        $sid = htmlspecialchars($srv['UserId'], ENT_QUOTES, 'UTF-8');
                                        $sname = htmlspecialchars($srv['UserNome'], ENT_QUOTES, 'UTF-8');
                                        echo "<option value=\"{$sid}\">{$sname}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-box">
                                <label for="allocation-mode">Modo de alocação</label>
                                <select id="allocation-mode" name="allocation-mode" class="pequeno" aria-label="Modo de alocação">
                                    <option value="direct" selected>Alocação direta</option>
                                    <option value="draw">Alocaão por sorteio</option>
                                    <option value="selected">Alocaão para selecionados</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-line">
                            <div class="form-box">
                                <button type="submit" id="create" class="primary">Cadastrar</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="modal form">
                    <div class="title-bar"><span class="title-name">Lista de Guias</span></div>
                    <div class="table">
                        <div class="table-block" style="padding: 10px 20px;">
                            <div class="block-line" style="font-size: 12px; color: #555;">                                
                            <?php
                            if ($hasQueryError) {
                                echo '<div class="alert error">Erro ao consultar o banco de dados.</div>';
                                echo '<span class="table__subtitle">Nenhum registro para exibir</span>';
                            } else {
                                if ($countGuia === 0) {
                                    echo '<span class="table__subtitle">Nenhum registro para exibir</span>';
                                } elseif ($countGuia === 1) {
                                    echo '<span class="table__subtitle">Exibindo 1 registro</span>';
                                } else {
                                    echo '<span class="table__subtitle">Exibindo ' . $countGuia . ' registros</span>';
                                }
                            }
                            ?>
                            </div>
                        </div>

                        <?php
                        if (!$hasQueryError && $q instanceof mysqli_result) {
                            $now = new DateTimeImmutable('now', $tzApp);
                            while ($row = $q->fetch_assoc()) {
                                $idRaw = (string)($row['id_guia'] ?? '');
                                $procRaw = trim((string)($row['processo'] ?? ''));
                                $tipoRaw = (string)($row['tipo'] ?? '');
                                $statusRaw = (string)($row['status'] ?? '');
                                $reuRaw = (string)($row['denunciado'] ?? '');
                                $etapaRaw = (string)($row['etapa'] ?? '');
                                $urgencia = (int)($row['urgencia'] ?? 0);

                                $id = htmlspecialchars($idRaw, ENT_QUOTES, 'UTF-8');
                                $procHtml = htmlspecialchars($procRaw, ENT_QUOTES, 'UTF-8');
                                $tipoHtml = htmlspecialchars($tipoRaw, ENT_QUOTES, 'UTF-8');
                                $statusHtml = htmlspecialchars($statusRaw, ENT_QUOTES, 'UTF-8');
                                $reuHtml = htmlspecialchars($reuRaw, ENT_QUOTES, 'UTF-8');
                                $etapaHtml = htmlspecialchars($etapaRaw, ENT_QUOTES, 'UTF-8');
                                $prioLabel = 'Normal';
                                if ($urgencia === 1) $prioLabel = 'Importante';
                                elseif ($urgencia === 2) $prioLabel = 'Urgente';

                                $createdLocal = $parseCadastro($row['data_cadastro'] ?? null);
                                $data_fmt = $createdLocal->format('d/m/Y \à\s H:i:s');
                                $diffSeconds = $now->getTimestamp() - $createdLocal->getTimestamp();
                                $timeInit = htmlspecialchars($humanDiff($diffSeconds), ENT_QUOTES, 'UTF-8');

                                // statusClass simples
                                $statusClass = '';
                                if (mb_strtolower($statusRaw, 'UTF-8') === 'aguardando assinatura da juza') $statusClass = 'stat-yellow';
                                elseif (mb_strtolower($statusRaw, 'UTF-8') === 'aguardando envio' || mb_strtolower($statusRaw, 'UTF-8') === 'aguardando reenvio') $statusClass = 'stat-red';
                                elseif (mb_strtolower($statusRaw, 'UTF-8') === 'aguardando juntada de termo') $statusClass = 'stat-orange';

                                // Render no layout de cartasexpedidas (simplificado)
                                ?>
                                <div class="table-block" data-id="<?php echo $id; ?>" data-urgencia="<?php echo $urgencia; ?>">
                                    <div class="table-row header" style="border-radius: 0;">
                                        <div class="block-line" style="width: 100%;">
                                            <div class="table-cell etapas" style="justify-content: space-between; width: 100%;">
                                                <div class="left">
                                                    <?php
                                                        // Etapas em ordem. Steps completed before the current should be 'finished',
                                                        // the current step is 'current' and following steps are 'await'.
                                                        $steps = [
                                                            'aguardando detalhamento',
                                                            'aguardando expedição',
                                                            'aguardando assinatura da juíza',
                                                            'aguardando envio',
                                                            'aguardando juntada de termo'
                                                        ];

                                                        $currentIndex = null;
                                                        $statusLower = mb_strtolower($statusRaw, 'UTF-8');
                                                        if ($statusLower === 'finalizada' || $statusLower === 'finalizado') {
                                                            // all finished
                                                            $currentIndex = count($steps);
                                                        } else {
                                                            foreach ($steps as $i => $s) {
                                                                if (stripos($statusLower, $s) !== false) {
                                                                    $currentIndex = $i;
                                                                    break;
                                                                }
                                                            }
                                                        }

                                                        // render classes
                                                        for ($i = 0; $i < count($steps); $i++) {
                                                            if ($currentIndex === null) {
                                                                $cls = 'await';
                                                                $showText = false;
                                                            } elseif ($i < $currentIndex) {
                                                                $cls = 'finished';
                                                                $showText = false;
                                                            } elseif ($i === $currentIndex) {
                                                                $cls = 'current';
                                                                $showText = true;
                                                            } else {
                                                                $cls = 'await';
                                                                $showText = false;
                                                            }
                                                            $stepTag = ($i === 0) ? 'etapa-inicio' : 'etapa';
                                                            echo '<div class="'.$stepTag.' '.$cls.'">'.($showText ? htmlspecialchars($statusRaw, ENT_QUOTES, 'UTF-8') : '').'</div>';
                                                        }

                                                        // etapa-fim: considered finished when status is finalizada or currentIndex == last step
                                                        $etapaFimClass = ($currentIndex !== null && ($currentIndex >= count($steps) || $statusLower === 'finalizada' || $statusLower === 'finalizado')) ? 'finished' : 'await';
                                                    ?>
                                                    <div class="etapa-fim <?php echo $etapaFimClass; ?>"></div>
                                                </div>

                                                <div class="right">                                                
                                                    <?php if ($urgencia === 2) { ?>
                                                        <div class="etapa current" style="background-color: crimson; color: white;">Urgente</div>
                                                    <?php } elseif ($urgencia === 1) { ?>
                                                        <div class="etapa current" style="background-color: orange; color: white;">Importante</div>
                                                    <?php } ?>
                                                    
                                                    <?php
                                                    $allocatedNames = [];
                                                    if ($stmtServAlloc instanceof mysqli_stmt) {
                                                        $stmtServAlloc->bind_param("s", $idRaw);
                                                        if ($stmtServAlloc->execute()) {
                                                            $resAlloc = $stmtServAlloc->get_result();
                                                            if ($resAlloc) {
                                                                while ($rowAlloc = $resAlloc->fetch_assoc()) {
                                                                    $allocatedNames[] = '<div class="etapa current">'.htmlspecialchars($rowAlloc['UserNome'] ?? $rowAlloc['id_user'], ENT_QUOTES, 'UTF-8').'</div>';
                                                                }
                                                                $resAlloc->free();
                                                            }
                                                        }
                                                    } else {
                                                        // fallback: query directly
                                                        $qAlloc = $connection->query("SELECT gs.id_user, u.UserNome FROM guia_servidores gs LEFT JOIN Usuarios u ON u.UserId = gs.id_user WHERE gs.id_guia = '" . $connection->real_escape_string($idRaw) . "' ORDER BY u.UserNome");
                                                        if ($qAlloc) {
                                                            while ($ra = $qAlloc->fetch_assoc()) {
                                                                $allocatedNames[] = '<div class="etapa current" style="background-color: #cce6ff;">'.htmlspecialchars($ra['UserNome'] ?? $ra['id_user'], ENT_QUOTES, 'UTF-8').'</div>';
                                                            }
                                                            $qAlloc->free();
                                                        }
                                                    }
                                                    echo !empty($allocatedNames) ? implode('', $allocatedNames) : '<div class="etapa current" style="margin-left: 10px; color:#666; background-color: gainsboro;">Nenhum servidor alocado</div>';
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="table-row content <?php echo $statusClass; ?>">
                                        <div class="block-box process">
                                            <div class="table-cell doc-process-title">Processo</div>
                                            <div class="table-cell doc-process" style="display:flex;align-items:center;gap:6px;">
                                                <span><?php echo $procHtml; ?></span>
                                                <span class="fi-rr-copy-alt" title="Copiar número do processo" style="cursor: pointer; color: #555;"></span>
                                            </div>
                                        </div>
                                        <div class="block-box description" style="width: calc(100% - 220px - 350px - 200px);">
                                            <div class="table-cell doc-description-title">Tipo</div>
                                            <div class="table-cell doc-description"><?php echo $tipoHtml; ?></div>
                                        </div>
                                        <div class="block-box city" style="width: 350px">
                                            <div class="table-cell doc-city-title">Denunciado</div>
                                            <div class="table-cell doc-city"><?php echo $reuHtml; ?></div>
                                        </div>
                                        
                                        <div class="block-box actions" style="width:200px;">
                                            <div class="table-cell doc-term-title">Ações</div>
                                            <div class="table-cell doc-term" style="display:flex;gap:8px;align-items:center;">
                                                <select class="actions-select" aria-label="Alterar status">
                                                    <option value="" selected disabled>Selecione...</option>
                                                    <?php
                                                    $allowed = $allowedActionsByStatus($statusRaw);
                                                    foreach ($allowed as $action) {
                                                        $label = '';
                                                        switch ($action) {
                                                            case 'doc-expedir': $label = 'Expedir'; break;
                                                            case 'doc-assinar': $label = 'Assinar'; break;
                                                            case 'doc-enviar': $label = 'Enviar'; break;
                                                            case 'doc-juntar': $label = 'Juntar termo'; break;
                                                            case 'doc-excluir': $label = 'Excluir'; break;
                                                            default: $label = $action; break;
                                                        }
                                                        echo '<option value="'.htmlspecialchars($action,ENT_QUOTES,'UTF-8').'">'.$label.'</option>';
                                                    }
                                                    ?>
                                                </select>
                                                <?php if (trim($tipoRaw) === 'Guia - Tipo não informado') { ?>
                                                    <select class="type-change-select" aria-label="Alterar tipo" style="margin-right:6px;">
                                                        <option value="" selected disabled>Alterar tipo...</option>
                                                        <option value="Guia de Execução Definitiva">Guia de Execução Definitiva</option>
                                                        <option value="Guia de Recolhimento Provisória">Guia de Recolhimento Provisória</option>
                                                        <option value="Guia de Recolhimento Definitiva">Guia de Recolhimento Definitiva</option>
                                                    </select>
                                                <?php } ?>
                                                <button type="button" class="actions-save" data-id="<?php echo $id; ?>">Salvar</button>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            <?php
                            } // end while
                        } // end if (!$hasQueryError && $q instanceof mysqli_result)
                        ?>
                    </div>
                </div>
            </div>

            <div class="right" style="visibility: hidden;">                
            </div>
        </div>
    </div>

    <!-- Scripts: reaproveitar e adaptar de guias.php -->
    <script type="text/javascript" src="./../js/request.js"></script>
    <script type="text/javascript" src="./../js/forms.js"></script>
    <script type="text/javascript" src="./../assets/js/common.js"></script>

    <script>
    "use strict";
    function obterUsuarioLogado() {
        try {
            const dados = sessionStorage.getItem('UserLogin');
            const arr = dados ? JSON.parse(dados) : null;
            if (Array.isArray(arr) && arr[0]?.usuario) return arr[0].usuario;
        } catch (e) {}
        return '';
    }

    function atualizarCampoDocUser() {
        const docUser = document.querySelector('#doc-user');
        if (!docUser) return;
        const nomeDoUsuario = obterUsuarioLogado() || window.parent?.document?.querySelector('.partial-name')?.textContent?.trim() || '';
        docUser.value = nomeDoUsuario;
    }

    function filtrarRegistrosPorProcesso(valor) {
        const termo = (valor || '').trim().toLowerCase();
        const btnClear = document.querySelector('#btn-search-clear');
        document.querySelectorAll('.table-block').forEach(row => {
            const num = row.querySelector('.doc-process')?.textContent.trim().toLowerCase() || '';
            row.style.display = (termo === '' || num.includes(termo)) ? '' : 'none';
        });
    }

    function copiarNumeroDoProcesso(icon) {
        const row = icon.closest('.table-block');
        const num = row?.querySelector('.doc-process')?.textContent.trim();
        if (!num) return;
        const copiar = (navigator.clipboard && navigator.clipboard.writeText)
            ? navigator.clipboard.writeText(num)
            : new Promise((res, rej) => {
                try {
                    const ta = document.createElement('textarea');
                    ta.value = num;
                    document.body.appendChild(ta);
                    ta.select();
                    const ok = document.execCommand('copy');
                    ta.remove();
                    ok ? res() : rej(new Error('copy failed'));
                } catch (err) { rej(err); }
            });
        copiar.catch(() => alert('Falha ao copiar número do processo'));
    }

    function initDocMenus(root=document) {
        const REG = '.table-block';
        const ACTIVATOR = '.col-block';
        const MENU = '.table-menu';
        let openMenu = null, uid = 0;
        root.querySelectorAll(REG).forEach(reg => {
            const menu = reg.querySelector(MENU);
            if (!menu) return;
            const btn = menu.closest(ACTIVATOR);
            if (!btn) return;
            if (!menu.id) menu.id = `table-menu-${++uid}`;
            btn.setAttribute('role','button');
            btn.setAttribute('tabindex','0');
            btn.setAttribute('aria-haspopup','menu');
            btn.setAttribute('aria-controls', menu.id);
            btn.setAttribute('aria-expanded','false');
            menu.setAttribute('role','menu');
        });
        root.addEventListener('click', e => {
            const btn = e.target.closest(`${REG} ${ACTIVATOR}`);
            if (btn) {
                e.preventDefault(); e.stopPropagation();
                const menu = btn.querySelector(MENU);
                if (!menu) return;
                if (openMenu && openMenu !== menu) { openMenu.setAttribute('hidden',''); openMenu.closest(ACTIVATOR)?.setAttribute('aria-expanded','false'); }
                const willOpen = menu.hasAttribute('hidden');
                if (willOpen) { menu.removeAttribute('hidden'); btn.setAttribute('aria-expanded','true'); openMenu = menu; }
                else { menu.setAttribute('hidden',''); btn.setAttribute('aria-expanded','false'); openMenu = null; }
                return;
            }
            if (openMenu && !e.target.closest(MENU)) { openMenu.setAttribute('hidden',''); openMenu = null; }
        });
    }

    // Envia ação para o servidor (refatoração reutilizável)
    async function sendGuiaAction(id, action) {
        if (!id) return;
                const isDelete = action === 'doc-excluir' || action === 'doc-juntar';
        if (isDelete && !confirm('Deseja realmente excluir esta guia?')) return;

        const ACTION_TO_STATUS = {
            'doc-expedir': 'Aguardando assinatura da juíza',
            'doc-assinar': 'Aguardando envio',
                    'doc-enviar' : 'Aguardando juntada do termo'
        };

        const movType = ACTION_TO_STATUS[action] || (action === 'doc-excluir' ? '' : action);
        const params = new URLSearchParams({
            'mov-process': id,
            'mov-type': isDelete ? '' : movType,
            'guia': movType,
            'mov-user': obterUsuarioLogado()
        });
        const url = isDelete ? '../fast-request/crt-guias-delete.php' : '../fast-request/crt-guias-mov.php';

        try {
            const resp = await fetch(url, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: params
            });
            const text = await resp.text();
            let data = {};
            try { data = JSON.parse(text); } catch {}
            const ok = resp.ok && (data.success === true || data.ok === true || text.toLowerCase().includes('ok'));
            if (ok) location.reload();
            else alert(`Erro: ${(data.message || text || resp.statusText)}`.slice(0,300));
        } catch (err) {
            console.error(err); alert('Falha na comunicação com o servidor.');
        }
    }

    // Envia alteração de tipo para o servidor
    async function sendTypeChange(id, newType) {
        if (!id || !newType) return;
        const params = new URLSearchParams({
            'mov-process': id,
            'change_tipo': newType,
            'mov-user': obterUsuarioLogado()
        });
        try {
            const r = await fetch('../fast-request/crt-guias-mov.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: params
            });
            const txt = await r.text();
            let data = {};
            try { data = JSON.parse(txt); } catch {}
            const ok = r.ok || data.success === true || data.ok === true || txt.toLowerCase().includes('ok');
            if (ok) { location.reload(); return; }
            alert('Erro ao alterar tipo. ' + (data.message || txt || r.statusText));
        } catch (err) {
            console.error(err); alert('Falha na comunicação com o servidor.');
        }
    }
    // Formatação do nome da pessoa: iniciais maiúsculas exceto preposições
    function formatarNomePessoa(el) {
        if (!el) return;
        let s = String(el.value || '').trim();
        if (s.length === 0) { el.value = ''; return; }
        // Limita a 200 caracteres
        if (s.length > 200) s = s.slice(0,200);
        const small = ['de','da','dos','das','do','e','van','von','von','da','das','do','dos','di','del','la','le'];
        const parts = s.split(/\s+/).map((token, idx) => {
            // preservar hyphenated names
            return token.split('-').map((sub, jdx) => {
                sub = sub.toLowerCase();
                if (idx !== 0 && small.includes(sub)) return sub;
                return sub.charAt(0).toUpperCase() + sub.slice(1);
            }).join('-');
        });
        el.value = parts.join(' ');
    }

    // Validaão/formatador do número do processo (formato CNJ 20 dígitos)
    function validarNumeroProcesso(inputEl) {
        if (!inputEl) return false;
        let v = String(inputEl.value || '').trim();
        // extrai apenas dgitos
        const digits = v.replace(/\D/g,'');
        if (digits.length === 0) { inputEl.value = ''; return false; }
        if (digits.length !== 20) {
            // inválido - limpa ou mantém parcial
            // opcional: apenas avisar e manter como está
            alert('Número do processo inválido. Deve conter 20 dígitos (formato 0000000-00.0000.0.00.0000).');
            inputEl.focus();
            return false;
        }
        // formata: 7-2-4-1-2-4
        const a = digits.substr(0,7);
        const b = digits.substr(7,2);
        const c = digits.substr(9,4);
        const d = digits.substr(13,1);
        const e = digits.substr(14,2);
        const f = digits.substr(16,4);
        inputEl.value = `${a}-${b}.${c}.${d}.${e}.${f}`;
        return true;
    }

    document.addEventListener('DOMContentLoaded', () => {
        const input = document.querySelector('#search-proc-input');
        if (input) input.addEventListener('input', () => filtrarRegistrosPorProcesso(input.value));
        document.addEventListener('click', e => {
            const icon = e.target.closest('.fi-rr-copy-alt');
            if (icon) copiarNumeroDoProcesso(icon);
        });
        initDocMenus();
        // Validações e formatações do formulário de cadastro
        const proc = document.querySelector("#doc-process");
        atualizarCampoDocUser();
        if (proc) {
            proc.addEventListener("change", () => validarNumeroProcesso(proc));
            proc.addEventListener("paste", () => {
                setTimeout(() => validarNumeroProcesso(proc), 0);
            });
        }

        const den = document.querySelector("#doc-denunciado");
        if (den) {
            den.addEventListener("blur", () => formatarNomePessoa(den));
            den.addEventListener("change", () => formatarNomePessoa(den));
        }

        // Lógica de seleção múltipla de servidores e habilitaão do modo de alocação
        const serversSel = document.querySelector('#doc-servers');
        const allocation = document.querySelector('#allocation-mode');
        if (serversSel && allocation) {
            serversSel.addEventListener('change', () => {
                const cnt = Array.from(serversSel.selectedOptions).length;
                const optDirect = allocation.querySelector('option[value="direct"]');
                const optDraw = allocation.querySelector('option[value="draw"]');
                if (cnt <= 1) {
                    if (optDraw) optDraw.disabled = true;
                    if (optDirect) optDirect.disabled = false;
                    // se nenhum selecionado, reabilita sorteio
                    if (cnt === 0 && optDraw) optDraw.disabled = false;
                    allocation.value = 'direct';
                } else {
                    if (optDirect) optDirect.disabled = true;
                    if (allocation.value === 'direct') allocation.value = 'draw';
                    if (optDraw) optDraw.disabled = false;
                }
            });
        }

        // Antes de enviar, garantir validações
        const docForm = document.querySelector('form.document-create');
        if (docForm) {
            docForm.addEventListener('submit', (ev) => {
                const denEl = document.querySelector('#doc-denunciado');
                if (denEl) formatarNomePessoa(denEl);
                const procEl = document.querySelector('#doc-process');
                if (procEl && !validarNumeroProcesso(procEl)) {
                    ev.preventDefault();
                    return false;
                }
            });
        }
        document.addEventListener('click', async e => {
            const btn = e.target.closest('.table-menu-item');
            if (btn) {
                const action = btn.dataset.action;
                const reg = btn.closest('.table-block');
                const id = reg?.dataset.id;
                if (!action || !id) return;
                await sendGuiaAction(id, action);
                return;
            }

            // salvar a partir do select + botão
            const saveBtn = e.target.closest('.actions-save');
            if (saveBtn) {
                const id = saveBtn.dataset.id;
                const row = document.querySelector(`.table-block[data-id="${id}"]`);
                const typeSel = row?.querySelector('.type-change-select');
                if (typeSel && typeSel.value) {
                    // prioridade para alteração de tipo
                    await sendTypeChange(id, typeSel.value);
                    return;
                }
                const sel = row?.querySelector('.actions-select');
                const action = sel?.value;
                if (!action) { alert('Selecione uma aço antes de salvar.'); return; }
                await sendGuiaAction(id, action);
                return;
            }
        });
    });
    </script>
</body>
</html>
