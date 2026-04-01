<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../services/Produtividade.php';

Security::requireAuthentication();

$routePage = trim((string) ($_GET['page'] ?? 'produtividade'));

// Filtros
$userFilter = trim((string) ($_GET['user'] ?? ''));
$dataInicio = trim((string) ($_GET['data_inicio'] ?? ''));
$dataFim = trim((string) ($_GET['data_fim'] ?? ''));
$visible = (int) ($_GET['visible'] ?? 1);

$connection = Conection::getInstance()->getConnection();
$produtividade = new Produtividade($connection);

// Buscar usuários para filtro
$usuarios = [];
try {
    $stmt = $connection->query("SELECT DISTINCT UserProd FROM Produtividade WHERE Visible = 1 ORDER BY UserProd");
    $usuarios = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $usuarios = [];
}

// Buscar dados de produtividade
$produtividadeData = [];
$totalPontos = 0;
try {
    $sql = "SELECT IdProd, ProcProd, DescProd, PontosProd, DataProd, UserProd, Visible 
            FROM Produtividade 
            WHERE Visible = ?";

    $params = [$visible];
    if ($userFilter !== '') {
        $sql .= " AND UserProd = ?";
        $params[] = $userFilter;
    }
    if ($dataInicio !== '') {
        $sql .= " AND DataProd >= ?";
        $params[] = $dataInicio;
    }
    if ($dataFim !== '') {
        $sql .= " AND DataProd <= ?";
        $params[] = $dataFim;
    }

    $sql .= " ORDER BY DataProd DESC, IdProd DESC";

    $stmt = $connection->prepare($sql);
    $stmt->execute($params);
    $produtividadeData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular total de pontos
    $totalPontos = array_sum(array_column($produtividadeData, 'PontosProd'));
} catch (Exception $e) {
    $produtividadeData = [];
    $totalPontos = 0;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtividade - Primeira Vara Crime de Ilhéus</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link rel="stylesheet" href="/public/assets/css/dashboard.css">
    <link rel="stylesheet" href="/public/assets/css/forms.css">
    <link rel="stylesheet" href="/public/assets/css/tables.css">
    <script src="/public/assets/js/jquery-3.6.0.min.js"></script>
    <script src="/public/assets/js/utils.js"></script>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-content">
                <span class="all">Interno - Produtividade -</span>
                <h1 class="title-page">Produtividade</h1>
                <p class="description-page">Relatório de produtividade dos usuários do sistema.</p>
            </div>
        </header>

        <main class="main-content">
            <!-- Filtros -->
            <section class="filters-section">
                <h2>Filtros</h2>
                <form method="GET" action="" class="filters-form">
                    <input type="hidden" name="page" value="<?php echo htmlspecialchars($routePage); ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="user">Usuário:</label>
                            <select name="user" id="user">
                                <option value="">Todos</option>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <option value="<?php echo htmlspecialchars($usuario); ?>" <?php echo $userFilter === $usuario ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($usuario); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="data_inicio">Data Início:</label>
                            <input type="date" name="data_inicio" id="data_inicio" value="<?php echo htmlspecialchars($dataInicio); ?>">
                        </div>

                        <div class="form-group">
                            <label for="data_fim">Data Fim:</label>
                            <input type="date" name="data_fim" id="data_fim" value="<?php echo htmlspecialchars($dataFim); ?>">
                        </div>

                        <div class="form-group">
                            <label for="visible">Visível:</label>
                            <select name="visible" id="visible">
                                <option value="1" <?php echo $visible === 1 ? 'selected' : ''; ?>>Sim</option>
                                <option value="0" <?php echo $visible === 0 ? 'selected' : ''; ?>>Não</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <a href="?page=<?php echo htmlspecialchars($routePage); ?>" class="btn btn-secondary">Limpar</a>
                    </div>
                </form>
            </section>

            <!-- Resumo -->
            <section class="summary-section">
                <div class="summary-card">
                    <h3>Total de Registros</h3>
                    <span class="summary-value"><?php echo count($produtividadeData); ?></span>
                </div>
                <div class="summary-card">
                    <h3>Total de Pontos</h3>
                    <span class="summary-value"><?php echo number_format($totalPontos, 2, ',', '.'); ?></span>
                </div>
            </section>

            <!-- Tabela de Produtividade -->
            <section class="table-section">
                <h2>Registros de Produtividade</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Processo</th>
                                <th>Descrição</th>
                                <th>Pontos</th>
                                <th>Data</th>
                                <th>Usuário</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($produtividadeData)): ?>
                                <tr>
                                    <td colspan="6" class="no-data">Nenhum registro de produtividade encontrado.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($produtividadeData as $registro): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($registro['IdProd']); ?></td>
                                        <td><?php echo htmlspecialchars($registro['ProcProd']); ?></td>
                                        <td><?php echo htmlspecialchars($registro['DescProd']); ?></td>
                                        <td><?php echo number_format((float) $registro['PontosProd'], 2, ',', '.'); ?></td>
                                        <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($registro['DataProd']))); ?></td>
                                        <td><?php echo htmlspecialchars($registro['UserProd']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <script>
        // Funcionalidades JavaScript se necessário
    </script>
</body>
</html>