<?php
declare(strict_types=1);

Security::requireAuthentication();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Primeira Vara Crime de Ilhéus - Sistema de Gestão</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/custom.css">
</head>
<body>
    <div class="container-fluid container-main py-4">
        <!-- Cabeçalho da página -->
        <header class="mb-4">
            <h1 class="h3 fw-semibold mb-1">Guias</h1>
            <p class="text-body-secondary small mb-0">
                Página dedicada ao controle dos atos formais que conduzem o sentenciado da decisão ao cumprimento,
                garantindo que cada passo da execução esteja documentado ad solemnitatem.
            </p>
        </header>

        <!-- Cards de estatísticas -->
        <section class="mb-4">
            <div class="row g-3">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card stats-card bg-primary text-white border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="stats-icon"><i class="fi fi-rr-truck"></i></span>
                                <span class="fs-4 fw-bold">7</span>
                            </div>
                            <h6 class="card-subtitle mb-1 opacity-90">Guias em andamento</h6>
                            <span class="badge bg-dark bg-opacity-25">-18.2%</span>
                            <p class="small mb-0 mt-1 opacity-75">em relação ao mês passado</p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card stats-card bg-warning text-dark border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="stats-icon"><i class="fi fi-rr-exclamation"></i></span>
                                <span class="fs-4 fw-bold">7</span>
                            </div>
                            <h6 class="card-subtitle mb-1">Aguardando assinatura</h6>
                            <span class="badge bg-dark bg-opacity-25">100%</span>
                            <p class="small mb-0 mt-1 opacity-75">do total de guias em andamento</p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card stats-card bg-danger text-white border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="stats-icon"><i class="fi fi-rr-network"></i></span>
                                <span class="fs-4 fw-bold">0</span>
                            </div>
                            <h6 class="card-subtitle mb-1 opacity-90">Aguardando envio</h6>
                            <span class="badge bg-dark bg-opacity-25">0%</span>
                            <p class="small mb-0 mt-1 opacity-75">do total de guias em andamento</p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card stats-card bg-info text-dark border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="stats-icon"><i class="fi fi-rr-clock-three"></i></span>
                                <span class="fs-4 fw-bold">7</span>
                            </div>
                            <h6 class="card-subtitle mb-1">Alocadas para você</h6>
                            <span class="badge bg-dark bg-opacity-25">100%</span>
                            <p class="small mb-0 mt-1 opacity-75">do total de guias em andamento</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Lista de guias -->
        <section>
            <h2 class="h5 fw-semibold mb-3">Lista de guias</h2>
            <div class="card border shadow-sm">
                <div class="card-header bg-white py-3">
                    <h3 class="h6 fw-semibold mb-0">Listagem de Guias</h3>
                    <p class="text-body-secondary small mb-0 mt-1">Visualize, filtre e gerencie as guias em andamento.</p>
                </div>
                <div class="card-body">
                    <!-- Filtros -->
                    <div class="row g-2 flex-wrap align-items-center mb-3">
                        <div class="col-auto">
                            <select class="form-select" aria-label="Filtrar por tipo">
                                <option>Filtrar por tipo</option>
                                <option>Guia de Execução Definitiva</option>
                                <option>Guia de Recolhimento Provisória</option>
                                <option>Guia de Recolhimento Definitiva</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <select class="form-select" aria-label="Filtrar por status">
                                <option>Filtrar por status</option>
                                <option>Não iniciada</option>
                                <option>Em andamento</option>
                                <option>Concluída</option>
                            </select>
                        </div>
                    </div>

                    <hr class="my-3">

                    <!-- Ações e itens por página -->
                    <div class="row g-2 flex-wrap align-items-center mb-3">
                        <div class="col-auto">
                            <select class="form-select form-select-sm" style="width: auto;" aria-label="Itens por página">
                                <option>Itens por página</option>
                                <option>10</option>
                                <option selected>25</option>
                                <option>50</option>
                                <option>100</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <select class="form-select form-select-sm" style="width: auto;" aria-label="Ações em lote">
                                <option>Gerar PDF</option>
                                <option>Imprimir</option>
                            </select>
                        </div>
                        <div class="col-auto ms-auto">
                            <button type="button" class="btn btn-primary btn-md">Cadastrar novo</button>
                        </div>
                    </div>

                    <hr class="my-3">

                    <!-- Lista de itens -->
                    <ul class="list-group list-group-flush mb-0">
                        <li class="list-group-item px-0">Usuário 1 - Administrador</li>
                        <li class="list-group-item px-0">Usuário 2 - Servidor</li>
                        <li class="list-group-item px-0">Usuário 3 - Estudante</li>
                        <li class="list-group-item px-0">Usuário 4 - Inativo</li>
                    </ul>

                    <hr class="my-3">

                    <!-- Paginação -->
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <p class="text-body-secondary small mb-0">Exibindo 1-4 de 24 itens</p>
                        <nav aria-label="Navegação da listagem">
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item disabled">
                                    <span class="page-link"><i class="fi fi-rr-angle-small-left"></i></span>
                                </li>
                                <li class="page-item active"><span class="page-link">1</span></li>
                                <li class="page-item"><a class="page-link" href="#">2</a></li>
                                <li class="page-item"><a class="page-link" href="#">3</a></li>
                                <li class="page-item"><a class="page-link" href="#">4</a></li>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                <li class="page-item"><a class="page-link" href="#">10</a></li>
                                <li class="page-item">
                                    <a class="page-link" href="#"><i class="fi fi-rr-angle-small-right"></i></a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
