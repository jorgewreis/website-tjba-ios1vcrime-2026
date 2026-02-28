<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

// Apenas inclui o arquivo. Ele irá definir $GLOBALS['connection'].
// Não atribua o retorno do require_once a $connection, pois ele retorna 1.
require_once __DIR__ . '/../class/Connect-New.php'; // Esta linha está correta como está
require_once __DIR__ . '/../class/Cartorio/CartasExpedidas/Rules/RulesAllocator.php';

use App\Cartorio\CartasExpedidas\Rules\RulesAllocator;

// Acessa a conexão PDO que foi definida globalmente pelo Connect-New.php
$connection = $GLOBALS['connection']; // <--- ESTA É A LINHA QUE PRECISA SER ADICIONADA/AJUSTADA

$ruleName = 'ALLOCATE-CARTASEXPEDIDAS';
$errors = [];
$successMessage = '';
$usersList = [];
$selectedUserId = '1';
$ruleRow = null;

try {
    // Agora $connection conterá o objeto PDO corretamente
    $allocator = new RulesAllocator($connection); // Linha 22
    $usersList = $allocator->getUsers();
    if (empty($usersList)) {
        $errors[] = 'Nenhum servidor foi encontrado na tabela Usuarios.';
    }

    $ruleRow = $allocator->getRule($ruleName);
    $selectedUserId = (string) ($ruleRow['id_user'] ?? $selectedUserId);
    if ($selectedUserId === '' && !empty($usersList)) {
        $selectedUserId = $usersList[0]['id'];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postedUserId = trim((string) ($_POST['id_user'] ?? ''));
        if ($postedUserId === '') {
            $errors[] = 'Selecione um servidor antes de salvar.';
        } else {
            try {
                $updatedRule = $allocator->assignServer($ruleName, $postedUserId);
                $successMessage = 'Regra atualizada com ID ' . ($updatedRule['id_rule'] ?? 'sem ID') . '.';
                $ruleRow = $updatedRule;
                $selectedUserId = (string) ($updatedRule['id_user'] ?? $postedUserId);
            } catch (\InvalidArgumentException | \RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            } catch (\Throwable $exception) {
                $errors[] = 'Erro inesperado ao salvar a regra: ' . $exception->getMessage();
            }
        }
    }
} catch (\Throwable $exception) {
    $errors[] = 'Erro ao acessar as regras: ' . $exception->getMessage();
}
?>
<!DOCTYPE html>
<html>
    <head>
        <meta lang="pt-BR">
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Template Model - Components - Buttons</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="./../v2026/public/assets/css/shared/reset.css">
        <link rel="stylesheet" href="./../v2026/public/assets/css/shared/variables.css">
        <link rel="stylesheet" href="./../v2026/public/assets/css/shared/page.css">
        <link rel="stylesheet" href="./../v2026/public/assets/css/shared/form.css">
        <link rel="stylesheet" href="./../v2026/public/assets/css/shared/table.css">

    </head>
<body class="page-internal">

    <!-- SPACING -->
    <div class="spacing-top-page"></div>

    <div class="header-page">
        <!-- Mapa de navegaão do site -->
        <div class="location">
            <span class="all">Internal - Cartório - Cartas Expedidas -</span>
            <span class="specific">Regras</span>
        </div>

        <!-- Título e descriço da página -->
        <h1 class="title-page">Regras de Alocação de Carta Expedida</h1>
        <p class="description-page">Caso tenha alguma dificuldade no preenchimento das regras, verifique os arquivos abaixo para obter ajuda.</p>

        <!-- Badges de arquivos para download -->
        <div class="badge file">
            <img src="./../v2026/public/assets/icons/Office-File-Pdf-1--Streamline-Ultimate.png" alt="Ícone de arquivo PDF">
            <span>Guia Completo - Divisão de Tarefas</span>
        </div>
    </div>

    <div class="card-page">
        <form method="post" class="rule-form" style="width: 100%">
            <div class="row table header">Regra de alocação automática - Envio</div>
            <div class="row filter">
                <div class="col filter">
                    <label for="filter-processo">Servidor</label>
                    <select class="width-md height-md" id="filter-processo" name="id_user" required>
                        <option value="" disabled<?php echo $selectedUserId === '' ? ' selected' : ''; ?>>Selecione um servidor</option>
                        <?php foreach ($usersList as $user): ?>
                            <option value="<?php echo htmlspecialchars($user['id'], ENT_QUOTES); ?>"<?php echo $selectedUserId === $user['id'] ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php if ($successMessage): ?>
                <div class="row filter">
                    <div class="col">
                        <label class="status status-finished"><?php echo htmlspecialchars($successMessage); ?></label>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="row filter">
                    <div class="col">
                        <?php foreach ($errors as $error): ?>
                            <label class="status status-cancelled"><?php echo htmlspecialchars($error); ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($ruleRow['id_rule'] ?? '')): ?>
                <div class="row filter">
                    <div class="col filter">
                        <label class="minor-info">Regra atual</label>
                        <span class="major-info"><?php echo htmlspecialchars($ruleRow['id_rule'] ?? ''); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            <div class="row filter">
                <div class="col">
                    <label class="status status-primary-light">*As tarefas de envio de Carta Expedida serão alocadas automaticamente para este usurio</label>
                </div>
            </div>

            <!-- Ações -->
            <div class="row buttons">
                <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>

    <!-- Develop Info -->
    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>
    <script>
        (() => {
            const btn = document.getElementById('voltar-btn');
            if (btn) {
                btn.addEventListener('click', (event) => {
                    event.preventDefault();
                    window.location.href = 'cartorio-cartas-expedidas-create.php';
                }, { capture: true });
            }
        })();
    </script>
</body>
</html>