<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/ProfissaoValidator.php';

/** @var list<string> $profissoesLista */
$profissoesLista = $profissoesLista ?? [];
/** @var string $profissaoAtual */
$profissaoAtual = isset($profissaoAtual) ? trim((string) $profissaoAtual) : '';
$outraVal = ProfissaoValidator::SELECT_OUTRA;
$profFoiNaLista = $profissaoAtual !== '' && in_array($profissaoAtual, $profissoesLista, true);
if ($profissaoAtual === '') {
    $selInicial = '';
} elseif ($profFoiNaLista) {
    $selInicial = $profissaoAtual;
} else {
    $selInicial = $outraVal;
}
?>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="profissao_selecao">Profissão</label>
                <select class="width-400 height-md white" id="profissao_selecao" name="profissao_selecao">
                    <option value="">Selecione</option>
                    <?php foreach ($profissoesLista as $p): ?>
                    <option value="<?php echo htmlspecialchars($p); ?>"<?php echo $selInicial === $p ? ' selected' : ''; ?>><?php echo htmlspecialchars($p); ?></option>
                    <?php endforeach; ?>
                    <option value="<?php echo htmlspecialchars($outraVal); ?>"<?php echo $selInicial === $outraVal ? ' selected' : ''; ?>>Outra</option>
                </select>
            </div>
            <div class="col" id="profissao-outra-wrap" style="display: <?php echo $selInicial === $outraVal ? 'block' : 'none'; ?>;">
                <label for="profissao_outra">Especifique a profissão</label>
                <input type="text" class="width-400 height-md white" id="profissao_outra" name="profissao_outra" maxlength="120" placeholder="Digite a profissão" value="<?php echo $selInicial === $outraVal ? htmlspecialchars($profissaoAtual) : ''; ?>">
            </div>
        </div>
        <script>
        window.PROFISSAO_SELECT_OUTRA = <?php echo json_encode($outraVal, JSON_UNESCAPED_UNICODE); ?>;
        </script>
