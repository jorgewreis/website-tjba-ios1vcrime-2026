<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../repositories/PartesRepository.php';
require_once __DIR__ . '/../../services/PessoasService.php';
require_once __DIR__ . '/../../../config/security.php';

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') {
    header('Location: /frame.php?page=consultapartes');
    exit;
}

$connection = Conection::getInstance()->getConnection();
$service = new PessoasService($connection);
$pessoa = $service->find($id);
if ($pessoa === null) {
    header('Location: /frame.php?page=consultapartes');
    exit;
}

$partesRepoProf = new PartesRepository($connection);
$profissoesLista = $partesRepoProf->listProfissoesDistintas();
$profissaoAtual = trim((string) ($pessoa['profissao'] ?? ''));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
        <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar pessoa - Consulta Partes</title>
            <?php include __DIR__ . '/_internal_head_styles.php'; ?>

    <?php include __DIR__ . '/_toast_script.php'; ?>
    <script src="/assets/js/shared/cpf.js"></script>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno - Consultas -</span> <span class="specific">Editar pessoa</span></div>
        <h1 class="title-page"><?php echo htmlspecialchars($pessoa['nome_completo'] ?? 'Pessoa'); ?></h1>
        <p class="description-page">Dados básicos, telefones, endereços e vínculos com instituições.</p>
    </div>
    <div class="container-full card-page">
        <input type="hidden" id="parte-id" value="<?php echo htmlspecialchars(trim((string) ($pessoa['id'] ?? $pessoa['ID'] ?? ''))); ?>">
        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Dados básicos</h2></div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="cpf">CPF</label>
                <input type="text" class="width-300 height-md white readyonl" id="cpf" value="<?php
                    $cpfVal = $pessoa['cpf'] ?? '';
                    echo htmlspecialchars(strlen($cpfVal) === 11 ? Security::formatCPF($cpfVal) : $cpfVal);
                ?>" readonly>
            </div>
            <div class="col">
                <label for="nome_completo">Nome completo</label>
                <input type="text" class="width-600 height-md white" id="nome_completo" placeholder="Digite o nome completo" value="<?php echo htmlspecialchars($pessoa['nome_completo'] ?? ''); ?>">
            </div>
            <div class="col">
                <label for="sexo">Sexo</label>
                <select class="width-200 height-md white" id="sexo">
                    <option value="">Selecione</option>
                    <option value="Feminino"<?php echo ($pessoa['sexo'] ?? '') === 'Feminino' ? ' selected' : ''; ?>>Feminino</option>
                    <option value="Masculino"<?php echo ($pessoa['sexo'] ?? '') === 'Masculino' ? ' selected' : ''; ?>>Masculino</option>
                    <option value="Outro"<?php echo ($pessoa['sexo'] ?? '') === 'Outro' ? ' selected' : ''; ?>>Outro</option>
                </select>
            </div>
            <div class="col">
                <label for="data_nascimento">Data de nascimento</label>
                <input type="date" class="width-300 height-md white" id="data_nascimento" value="<?php echo htmlspecialchars($pessoa['data_nascimento'] ?? ''); ?>">
            </div>
        </div>

        <?php include __DIR__ . '/_partes_profissao_fields.php'; ?>

        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Telefones</h2></div>
        <div id="telefones-list">
            <?php foreach ($pessoa['telefones'] ?? [] as $t): ?>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md" data-id="<?php echo htmlspecialchars($t['id'] ?? ''); ?>">
                <div class="col"><?php echo htmlspecialchars($t['tipo'] ?? '—'); ?></div>
                <div class="col"><?php echo htmlspecialchars($t['numero'] ?? ''); ?></div>
                <div class="col"><button type="button" class="btn btn-secondary-outline btn-del-tel" data-id="<?php echo htmlspecialchars($t['id'] ?? ''); ?>">Excluir</button></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="new-tel-tipo">Tipo</label>
                <select class="width-300 height-md white" id="new-tel-tipo">
                    <option value="">Selecione</option>
                    <option value="Telefone fixo">Telefone fixo</option>
                    <option value="Celular">Celular</option>
                </select>
            </div>
            <div class="col">
                <label for="new-tel-numero">Número</label>
                <input type="text" class="width-300 height-md white" id="new-tel-numero" placeholder="(00) 00000-0000">
            </div>
            <div class="col">
                <label for="add-tel-btn" style="color: transparent;">-</label>
                <button type="button" class="btn btn-secondary-outline" id="add-tel-btn">Adicionar telefone</button>
            </div>
        </div>

        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Endereços</h2></div>
        <div id="enderecos-list">
            <?php foreach ($pessoa['enderecos'] ?? [] as $e): ?>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md" data-id="<?php echo htmlspecialchars($e['id'] ?? ''); ?>">
                <div class="col"><?php echo htmlspecialchars(trim(($e['logradouro'] ?? '') . ' ' . ($e['numero'] ?? '') . ' ' . ($e['bairro'] ?? '') . ' ' . ($e['cidade'] ?? ''))); ?></div>
                <div class="col"><button type="button" class="btn btn-primary-outline btn-del-end" data-id="<?php echo htmlspecialchars($e['id'] ?? ''); ?>">Excluir</button></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="new-end-cep">CEP</label>
                <input type="text" class="width-200 height-md white" id="new-end-cep" placeholder="00000-000" maxlength="9">
            </div>
            <div class="col">
                <label for="buscar-cep-btn" style="color: transparent;">-</label>
                <button type="button" class="btn btn-primary-outline" id="buscar-cep-btn">Buscar CEP</button>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="new-end-logradouro">Logradouro</label>
                <input type="text" class="width-600 height-md white" id="new-end-logradouro" placeholder="Rua, avenida...">
            </div>
            <div class="col">
                <label for="new-end-numero">Número</label>
                <input type="text" class="width-200 height-md white" id="new-end-numero" placeholder="Nº">
            </div>
            <div class="col">
                <label for="new-end-complemento">Complemento</label>
                <input type="text" class="width-400 height-md white" id="new-end-complemento" placeholder="Sala, andar...">
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="new-end-bairro">Bairro</label>
                <input type="text" class="width-400 height-md white" id="new-end-bairro" placeholder="Bairro">
            </div>
            <div class="col">
                <label for="new-end-cidade">Cidade</label>
                <input type="text" class="width-300 height-md white" id="new-end-cidade" placeholder="Cidade">
            </div>
            <div class="col">
                <label for="new-end-uf">UF</label>
                <input type="text" class="width-200 height-md white" id="new-end-uf" placeholder="UF" maxlength="2">
            </div>
            <div class="col">
                <label class="inline"><input type="checkbox" id="new-end-principal"> Principal</label>
            </div>
            <div class="col">
                <label for="add-end-btn" style="color: transparent;">-</label>
                <button type="button" class="btn btn-primary-outline" id="add-end-btn">Adicionar endereço</button>
            </div>
        </div>

        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Vínculos com processos</h2></div>
        <div class="display-flex col col-full" id="vinculos-processos">
            <?php foreach ($pessoa['vinculos_processos'] ?? [] as $vp): ?>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col"><?php echo htmlspecialchars($vp['numero_processo'] ?? $vp['processo_id'] ?? '—'); ?></div>
                <div class="col"><?php echo htmlspecialchars($vp['tipo_parte'] ?? ''); ?> / <?php echo htmlspecialchars($vp['polo'] ?? ''); ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($pessoa['vinculos_processos'])): ?>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md"><span class="minor-info">Nenhum vínculo com processo.</span></div>
            <?php endif; ?>
        </div>

        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-top"><h2>Vínculos com instituições</h2></div>
        <div class="display-flex col col-full" id="vinculos-inst-list">
            <?php foreach ($pessoa['vinculos_instituicoes'] ?? [] as $vi): ?>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md" data-id="<?php echo htmlspecialchars($vi['id'] ?? ''); ?>">
                <div class="col"><?php echo htmlspecialchars($vi['instituicao_nome'] ?? $vi['instituicao_sigla'] ?? '—'); ?></div>
                <div class="col"><?php echo htmlspecialchars($vi['cargo_funcao'] ?? '—'); ?></div>
                <div class="col">
                    <button type="button" class="btn btn-secondary-outline" data-nav-href="/frame.php?page=partesinstituicoesedit&id=<?php echo urlencode($vi['id'] ?? ''); ?>">Editar</button>
                    <button type="button" class="btn btn-secondary-outline btn-del-vinculo" data-id="<?php echo htmlspecialchars($vi['id'] ?? ''); ?>">Excluir</button>
                </div>
            </div>
            <?php endforeach; ?>        
            <div class="display-flex row row-fit justify-start padding-x-md padding-y-md">
                <button type="button" id="vincular-instituicao-btn" class="btn btn-primary-outline">Vincular instituição</button>
            </div>
        </div>
        <div class="display-flex row row-full justify-end padding-x-md padding-y-md border-top">
            <div class="display-flex row row-fit justify-end gap-y-md">
                <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
                <button type="button" class="btn btn-primary" id="salvar-btn">Salvar dados</button>
            </div>
        </div>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <script>
        (function () {
            if (!window.App || !App.ConsultaPartesEdit || typeof App.ConsultaPartesEdit.initialize !== 'function') {
                return;
            }

            App.ConsultaPartesEdit.initialize();
        })();
    </script>
</body>
</html>
