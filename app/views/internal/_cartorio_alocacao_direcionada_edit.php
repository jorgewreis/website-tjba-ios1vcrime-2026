<?php
declare(strict_types=1);

/**
 * Bloco de alocações na edição: chips (com remoção conforme regra do desalocar) + opcional select para gestão.
 *
 * Variáveis esperadas:
 * - $connection (PDO)
 * - $cartorioAlocacaoPrefix string (ex.: guias, editais)
 * - $cartorioAlocacaoEntityId string
 * - $cartorioAlocacaoDesalocarEndpoint string (ex.: /api/cartorio-guias/desalocar.php)
 * - $cartorioAlocacaoAlocarEndpoint string
 * - $cartorioAlocacaoAlocados array (alocado_id, iniciais, nome_completo, usuario_id, tipo)
 * - $cartorioAlocacaoBloqueada bool (tarefa finalizada/cancelada ou equivalente)
 * - $cartorioAlocacaoUserId string (sessão)
 * - $cartorioAlocacaoIsGestao bool (ADMIN, CHEFIA, DIRETORA, MAGISTRADA)
 */

if (!isset($connection, $cartorioAlocacaoPrefix, $cartorioAlocacaoEntityId, $cartorioAlocacaoDesalocarEndpoint, $cartorioAlocacaoAlocarEndpoint, $cartorioAlocacaoAlocados)
    || !$connection instanceof PDO) {
    return;
}

$__capEd = preg_replace('/[^a-z0-9_-]/i', '', (string) $cartorioAlocacaoPrefix);
if ($__capEd === '') {
    return;
}

require_once __DIR__ . '/../../helpers/AlocacaoTarefasPerfisHelper.php';
require_once __DIR__ . '/../../helpers/AlocacaoRegrasUsuariosHelper.php';
require_once __DIR__ . '/../../helpers/CartorioAlocacaoChipHelper.php';

$__entityEd = trim((string) $cartorioAlocacaoEntityId);
$__desalocarEp = trim((string) $cartorioAlocacaoDesalocarEndpoint);
$__alocarEp = trim((string) $cartorioAlocacaoAlocarEndpoint);
$__alocsEd = is_array($cartorioAlocacaoAlocados) ? $cartorioAlocacaoAlocados : [];
$__bloqueadaEd = !empty($cartorioAlocacaoBloqueada);
$__uidEd = trim((string) ($cartorioAlocacaoUserId ?? ''));
$__gestaoEd = !empty($cartorioAlocacaoIsGestao);

$__showAlocDirEd = ($__uidEd !== '' && AlocacaoTarefasPerfisHelper::usuarioAdminOuChefiaPodeAlocarOutrosCadastro($__uidEd));
$__alocsEd = CartorioAlocacaoChipHelper::ordenarAlocadosPorTipo($__alocsEd);
$__usersAlocEd = [];
if ($__showAlocDirEd && !$__bloqueadaEd && count($__alocsEd) < 5) {
    try {
        $__usersAlocEd = array_values(array_filter(
            AlocacaoRegrasUsuariosHelper::listUsuariosInternos($connection),
            static function (array $usuario) use ($__uidEd): bool {
                return trim((string) ($usuario['id'] ?? '')) !== $__uidEd;
            }
        ));
    } catch (Throwable $e) {
        $__usersAlocEd = [];
    }
}
?>
            <div class="display-flex row row-full align-center padding-x-md padding-y-sm border-bottom"><h3 style="margin:0;font-size:1rem;">Alocações</h3></div>
            <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-sm" id="<?php echo htmlspecialchars($__capEd, ENT_QUOTES, 'UTF-8'); ?>-alocacao-edit-root">
                <div class="display-flex row row-full padding-y-sm gap-y-sm align-center flex-wrap">
                    <div class="display-flex row row-fit flex-wrap gap-y-sm allocate-chips-row allocate-users">
                    <?php if (!empty($__alocsEd)): ?>
                        <?php foreach ($__alocsEd as $alocado):
                            $chipAl = CartorioAlocacaoChipHelper::buildChipData($alocado, [
                                'entity_id' => $__entityEd,
                                'origem' => $__capEd . '.edit',
                            ]);
                            if ($chipAl === null) {
                                continue;
                            }
                            $tipoAl = $chipAl['tipo'];
                            $iniciaisAl = htmlspecialchars($chipAl['iniciais'], ENT_QUOTES, 'UTF-8');
                            $chipTitleAl = $chipAl['title'];
                            $chipAriaAl = $chipAl['aria_label'];
                            $classeTipoAl = $chipAl['classe_tipo'];
                            $alocadoIdStrAl = htmlspecialchars($chipAl['alocado_id'], ENT_QUOTES, 'UTF-8');
                            $usuarioIdStrAl = $chipAl['usuario_id'];
                            $podeRemoverAl = $__gestaoEd || ($tipoAl === 'specific' && trim((string) $usuarioIdStrAl) === $__uidEd);
                            ?>
                        <span class="user-allocate-wrap">
                            <span class="user-allocate<?php echo $classeTipoAl; ?>" title="<?php echo htmlspecialchars($chipTitleAl, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($chipAriaAl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $iniciaisAl; ?></span>
                            <?php if ($podeRemoverAl && $__desalocarEp !== ''): ?>
                            <button type="button"
                                class="user-allocate-remove js-desalocar"
                                data-alocado-id="<?php echo $alocadoIdStrAl; ?>"
                                data-tipo="<?php echo htmlspecialchars((string) $tipoAl, ENT_QUOTES, 'UTF-8'); ?>"
                                data-endpoint="<?php echo htmlspecialchars($__desalocarEp, ENT_QUOTES, 'UTF-8'); ?>"
                                title="Remover alocação"
                                aria-label="Remover alocação">×</button>
                            <?php endif; ?>
                        </span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="minor-info">Nenhuma alocação registrada.</span>
                    <?php endif; ?>
                    </div>
                </div>
                <?php if ($__showAlocDirEd && !$__bloqueadaEd && count($__alocsEd) < 5): ?>
                <div class="display-flex col col-full gap-y-sm padding-y-sm">
                    <span class="minor-info">Alocar servidores à tarefa (opcional) — perfis ADMIN, CHEFIA, DIRETORA ou MAGISTRADA</span>
                    <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                        <div class="col">
                            <label for="<?php echo htmlspecialchars($__capEd, ENT_QUOTES, 'UTF-8'); ?>-alocar-usuario-select">Servidor</label>
                            <select class="width-500 height-md" id="<?php echo htmlspecialchars($__capEd, ENT_QUOTES, 'UTF-8'); ?>-alocar-usuario-select">
                                <option value="">Selecione…</option>
                                <?php foreach ($__usersAlocEd as $u): ?>
                                <option value="<?php echo htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col">
                            <button type="button" class="btn btn-secondary height-md" id="<?php echo htmlspecialchars($__capEd, ENT_QUOTES, 'UTF-8'); ?>-alocar-adicionar">Adicionar</button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <script>
            (function () {
                if (!window.App || !App.CartorioAlocacaoDirecionadaEdit || typeof App.CartorioAlocacaoDirecionadaEdit.init !== 'function') {
                    return;
                }
                App.CartorioAlocacaoDirecionadaEdit.init({
                    prefix: <?php echo json_encode($__capEd, JSON_UNESCAPED_UNICODE); ?>,
                    entityId: <?php echo json_encode($__entityEd, JSON_UNESCAPED_UNICODE); ?>,
                    alocarEndpoint: <?php echo json_encode($__alocarEp, JSON_UNESCAPED_UNICODE); ?>
                });
            })();
            </script>
