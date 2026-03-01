<?php
declare(strict_types=1);

Security::requireAuthentication();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Guias - Primeira Vara Crime</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/custom.css">
</head>
<body class="page-internal">
    <?php
    $title = 'Guias';
    $description = 'Espaço onde as guias são emitidas, acompanhadas e encaminhadas, para que o trabalho seja continuo e eficiente.';
    include __DIR__ . '/_page_header.php';
    ?>

    <!-- main content -->
    <main class="container-fluid align-items-start justify-content-start">
        <div class="container-md d-flex align-items-start justify-content-start">            
            <div class="container-fluid" style="width: 100%;">

                <!-- cards statistics -->
                <div class="row gap-3 mx-0">
                    <div class="col card p-4">
                        <div class="row align-items-start justify-content-start">
                            <div class="" style="width: fit-content">
                                <div class="avatar bg-primary-light" style="width: 38px; height: 38px;">                                   <img src="./../../../public/assets/icons/List-Bullets--Streamline-Ultimate.png" alt="Icon" style="width: 20px; height: 20px;">
                                </div>
                            </div>
                            <div class="col fs-4 fw-semibold ps-3" style="line-height: 38px;">7</div>
                        </div>
                        <div class="row py-2">
                            <div class="col fs-6 text-secondary fw-semibold">Guias cadastradas</div>
                        </div>
                        <div class="row">
                            <div class="" style="width: fit-content; line-height: 25px;">
                                <div class="badge bg-danger">-12,5%</div>
                            </div>
                            <div class="col fs-7 ps-0" style="display: flex; align-items: center; line-height: 25px;">que o mês anterior</div>
                        </div>
                    </div>
                    <div class="col px-3 card p-4">
                        <div class="row align-items-start justify-content-start">
                            <div class="" style="width: fit-content">
                                <div class="avatar bg-danger-light" style="width: 38px; height: 38px">
                                    <img src="./../../../public/assets/icons/Pencil-Write-1-Alternate--Streamline-Ultimate.png" alt="Icon" style="width: 20px; height: 20px;">
                                </div>
                            </div>
                            <div class="col fs-4 fw-semibold ps-3" style="line-height: 38px;">6</div>
                        </div>
                        <div class="row py-2">
                            <div class="col fs-6 text-secondary fw-semibold">Aguardando expedição</div>
                        </div>
                        <div class="row">
                            <div class="" style="width: fit-content">
                                <div class="badge bg-primary">2</div>
                            </div>
                            <div class="col fs-7 ps-0" style="display: flex; align-items: center; line-height: 25px;">alocados pra você</div>
                        </div>
                    </div>
                    <div class="col px-3 card p-4">
                        <div class="row align-items-start justify-content-start">
                            <div class="" style="width: fit-content">
                                <div class="avatar bg-primary-light" style="width: 38px; height: 38px">
                                    <img src="./../../../public/assets/icons/Pen-Write--Streamline-Ultimate.png" alt="Icon" style="width: 20px; height: 20px;">
                                </div>
                            </div>
                            <div class="col fs-4 fw-semibold ps-3" style="line-height: 38px;">0</div>
                        </div>
                        <div class="row py-2">
                            <div class="col fs-6 text-secondary fw-semibold">Aguardando assinatura</div>
                        </div>
                        <div class="row">
                            <div class="" style="width: fit-content">
                                <div class="badge bg-primary">nenhum</div>
                            </div>
                            <div class="col fs-7 ps-0" style="display: flex; align-items: center; line-height: 25px;">alocado pra você</div>
                        </div>
                    </div>
                    <div class="col px-3 card p-4">
                        <div class="row align-items-start justify-content-start">
                            <div class="" style="width: fit-content">
                                <div class="avatar bg-warning-light" style="width: 38px; height: 38px">
                                    <img src="./../../../public/assets/icons/Share-1--Streamline-Ultimate.png" alt="Icon" style="width: 20px; height: 20px;">
                                </div>
                            </div>
                            <div class="col fs-4 fw-semibold ps-3" style="line-height: 38px;">1</div>
                        </div>
                        <div class="row py-2">
                            <div class="col fs-6 text-secondary fw-semibold">Aguardando envio</div>
                        </div>
                        <div class="row">
                            <div class="" style="width: fit-content">
                                <div class="badge bg-primary">nenhum</div>
                            </div>
                            <div class="col fs-7 ps-0" style="display: flex; align-items: center; line-height: 25px;">alocado pra você</div>
                        </div>
                    </div>
                </div>

                <!-- table page -->
                <div class="container-fluid card mt-4 py-3" style="width: 100%; margin: 0 auto;">

                    <!-- filters -->
                    <div class="row">
                        <div class="col">
                            <h3 class="fs-5 mb-1 ms-2">Lista de Guias</h3>
                        </div>
                    </div>
                    <div class="row border-bottom p-2">
                        <div class="col-3">                            
                            <select class="form-select">
                                <option value="" selected>Tipo - Todos</option>
                                <option value="Guia de Execução Definitiva">Guia de Execução Definitiva</option>
                                <option value="Guia de Recolhimento Provisória">Guia de Recolhimento Provisória</option>
                                <option value="Guia de Recolhimento Definitiva">Guia de Recolhimento Definitiva</option>
                            </select>
                        </div>
                        <div class="col-3">
                            <select class="form-select">
                                <option value="" selected>Status - Todos</option>
                                <option value="Pendente">Pendente</option>
                                <option value="Em andamento">Em andamento</option>
                                <option value="Concluída">Concluída</option>
                            </select>
                        </div>
                        <div class="col-3"></div>
                    </div>

                    <!-- pagination and actions -->
                    <div class="row border-bottom p-2 justify-content-end align-items-center">
                        <div class="col-4"></div>
                        <div class="col-2">
                            <select class="form-select">
                                <option value="10">10</option>
                                <option value="25" selected>25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                        <div class="col-3">
                            <select class="form-select">
                                <option value="" selected disabled>Exportar</option>
                                <option value="print">Imprimir lista</option>
                                <option value="pdf">Exportar PDF</option>
                            </select>
                        </div>
                        <div class="col-3 text-end">
                            <button type="button" class="btn btn-primary">Novo cadastro</button>
                        </div>                        
                    </div>

                    <!-- table -->
                    <div class="row">
                        <div class="col p-0">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col-3">Processo</th>
                                        <th scope="col-3">Tipo</th>
                                        <th scope="col-3">Detalhes</th>
                                        <th scope="col-3">Informações</th>
                                        <th scope="col-2">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>                                            
                                        <td>8005592-50.2023.8.05.0103</td>
                                        <td>Guia de Recolhimento Definitiva</td>
                                        <td>Reginaldo Martins Dantas</td>
                                        <td><button type="button" class="btn btn-sm btn-outline-primary" disabled>JW</button><button type="button" class="btn btn-sm btn-warning" disabled>Aguardando envio</button></td>
                                        <td><button type="button" class="btn btn-sm btn-outline-primary">Editar</button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>        
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>