<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carta Expedida - Teste</title>
    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/v2-colors.css">
    <link rel="stylesheet" href="/assets/css/shared/v2-page-list.css">
    <link rel="stylesheet" href="/assets/css/shared/form.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
</head>
<body data-theme="light">

    <div class="page-card-list">
        <div class="page-card-list-title">
            <div class="page-card-list-title-line">
                <div class="line-left title">
                    <span class="page-title-text">Cartas Expedidas</span>
                </div>
            </div>
            <div class="page-card-list-title-line">
                <div class="line-left description">
                    <span class="page-description-text">Documentos expedidos pelo Cartório para cumprimento por outras Comarcas</span>
                </div>
                <div class="line-right">
                    <div class="button-icon-left">
                        <i class="fi fi-rr-search" aria-hidden="true"></i>
                    </div>
                    <div class="button-icon-left media-1024">
                        <i class="fi fi-rr-filter" aria-hidden="true"></i>
                    </div>
                    <div class="button-icon-left">
                        <i class="fi fi-rr-file-export" aria-hidden="true"></i>
                    </div>
                    <div class="button-icon-left">
                        <i class="fi fi-rr-list" aria-hidden="true"></i>
                    </div>
                    <div class="buttons">
                        <div class="button-btn btn-primary">
                            <span class="btn-icon-text">Novo cadastro</span>
                            <div class="btn-icon hidden">
                                <i class="fi fi-rr-spinner" aria-hidden="true"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-line">
            <div class="card-line left">
                <div class="page-card-list-document">
                    <i class="fi fi-rr-file-pdf" aria-hidden="true"></i>
                    <span class="page-card-list-document-text">Passo a passo - Expedição de Cartas Precatórias</span>
                </div>
                <div class="page-card-list-document disabled">
                    <i class="fi fi-rr-file-pdf" aria-hidden="true"></i>
                    <span class="page-card-list-document-text">Infográfico - Fluxo de Cumprimento</span>
                </div>
            </div>
        </div>

        <div class="card-line full media-768">
            <div class="card-line left">
                <div class="line-description">Quantidade de itens por página</div>
            </div>
            <div class="card-line right">
                <select class="width-100 height-md">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
        <div class="card-line full media-768">
            <div class="card-line left">
                <div class="line-description">Exportar lista</div>
            </div>
            <div class="card-line right">
                <select class="width-200 height-md">
                    <option value="pdf" selected>PDF</option>
                    <option value="csv">CSV</option>
                    <option value="print">Imprimir</option>
                </select>
            </div>
        </div>
        <div class="card-line full media-1024">
            <div class="card-line left">
                <div class="line-description">Filtrar por</div>
            </div>
            <div class="card-line right">
                <select class="width-300 height-md">
                    <option value="todos" selected>Todos os status</option>
                    <option value="aguardando_envio">Aguardando envio</option>
                    <option value="aguardando_cumprimento">Aguardando cumprimento</option>
                    <option value="concluída">Finalizada</option>
                    <option value="cancelada">Cancelada</option>
                </select>
                <select class="width-300 height-md">
                    <option value="todos" selected>Todas as finalidades</option>
                    <option value="intimação_para_audiência">Intimação para Audiência</option>
                    <option value="intimação_de_decisão_interlocutória">Intimação de Decisão Interlocutória</option>
                    <option value="intimação_da_sentença">Intimação da Sentença</option>
                    <option value="citação_para_apresentar_defesa">Citação para apresentar Defesa</option>
                    <option value="citação_para_apresentar_alegações_finais">Citação para Alegações Finais</option>
                    <option value="suspensão_condicional">Suspensão Condicional</option>
                    <option value="outra">Outra não listada</option>
                </select>
            </div>
        </div>

        <div class="card-line full results">
            <div class="card-line left">
                <div class="line-results">
                    <span class="line-results-text">12 documentos encontrados</span>
                </div>
            </div>
        </div>

        <div class="register-line full">
            <div class="card-line left">
                <div class="register-block">
                    <div class="register-block-process">
                        <span class="register-block-process-number">8002565-23.2025.8.05.0103</span>
                        <div class="register-block-process-icons">
                            <img class="icon-mini-process" src="/assets/icons/Resize-Expand-Corner--Streamline-Ultimate.png" alt="Abrir">
                            <img class="icon-mini-process" src="/assets/icons/Task-Checklist--Streamline-Ultimate.png" alt="Copiar">
                        </div>
                        <div class="register-block-process-icons">
                            <img class="register-block-process-priority-icon" src="/assets/icons/Lock-6--Streamline-Ultimate.png" alt="Prioridade">
                            <img class="register-block-process-priority-icon" src="/assets/icons/Crime-Man-Inmate--Streamline-Ultimate.png" alt="Prioridade">
                            <img class="register-block-process-priority-icon" src="/assets/icons/Police-Rotating-Light-1--Streamline-Ultimate.png" alt="Prioridade">
                        </div>
                    </div>
                    <div class="register-block-process-classe valid">
                        <span class="register-block-process-classe-text">Ação Penal - Procedimento Ordinário</span>
                    </div>
                    <div class="register-block-process-assuntos valid">
                        <span class="register-block-process-assuntos-text">Tráfico de drogas, entorpecentes e condutas afins; Porte Ilegal de Arma de Fogo; Lesão Corporal Leve; Apropriação Indébita de Bem</span>
                    </div>
                </div>

                <div class="block-separator-line"></div>

                <div class="register-block">
                    <div class="register-block-title">Dados da Carta</div>
                    <div class="register-block-content">
                        <div class="register-block-content-item">
                            <span class="register-block-content-item-text">Destino:</span>
                            <span class="register-block-content-item-value">São Paulo - SP</span>
                        </div>
                        <div class="register-block-content-item">
                            <span class="register-block-content-item-text">Finalidade:</span>
                            <span class="register-block-content-item-value">Intimação para Audiência</span>
                        </div>
                        <div class="register-block-content-item">
                            <span class="register-block-content-item-text">Prazo:</span>
                            <span class="register-block-content-item-value">30 dias</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-line right">
                <div class="register-block">
                    <div class="register-block-status">
                        <div class="badge-allocated allocated-direct">
                            <span class="badge-allocated-text">Jorge Reis</span>
                            <span class="badge-allocated-icon hidden"><i class="fi fi-rr-cross-circle" aria-hidden="true"></i></span>
                        </div>
                        <div class="badge-status-primary status-current">
                            <span class="badge-status-text">Em andamento</span>
                        </div>
                    </div>
                    <div class="register-block-status">
                        <div class="button-icon-right">
                            <img src="/assets/icons/Notes-Edit--Streamline-Ultimate.png" alt="Editar">
                        </div>
                        <div class="register-block-status-text status-current">
                            <span class="register-block-status-text-text">Aguardando envio</span>
                            <div class="button-icon-internal">
                                <img src="/assets/icons/Navigation-Arrows-Right-1--Streamline-Ultimate.png" alt="Continuar">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="block-separator-line"></div>

                <div class="register-block media-1024">
                    <div class="register-block-title">Histórico</div>
                    <div class="register-block-content">19.05 - 12:00 | Cadastro: <span class="register-block-content-text">Moacir Bastos</span></div>
                </div>
            </div>
        </div>

        <div class="register-line full">
            <div class="card-line left">
                <div class="register-block"></div>
            </div>
            <div class="card-line right">
                <div class="register-block">
                    <div class="register-block-status">
                        <div class="badge-allocated allocated-direct">
                            <span class="badge-allocated-text">Jorge Reis</span>
                            <span class="badge-allocated-icon hidden"><i class="fi fi-rr-cross-circle" aria-hidden="true"></i></span>
                        </div>
                        <div class="badge-status-primary status-pending">
                            <span class="badge-status-text">Pendente</span>
                        </div>
                    </div>
                    <div class="register-block-status">
                        <div class="button-icon-right">
                            <img src="/assets/icons/Notes-Edit--Streamline-Ultimate.png" alt="Editar">
                        </div>
                        <div class="register-block-status-text status-pending">
                            <span class="register-block-status-text-text">Aguardando cumprimento</span>
                            <div class="button-icon-internal">
                                <img src="/assets/icons/Navigation-Arrows-Right-1--Streamline-Ultimate.png" alt="Continuar">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="register-block media-1024">
                    <div class="register-block-title">Histórico</div>
                    <div class="register-block-content">19.05 - 12:00 | Cadastro: <span class="register-block-content-text">Moacir Bastos</span></div>
                    <div class="register-block-content">19.05 - 17:00 | Atualização: <span class="register-block-content-text">Jorge Reis</span></div>
                </div>
            </div>
        </div>

        <div class="register-line full">
            <div class="card-line left">
                <div class="register-block"></div>
            </div>
            <div class="card-line right">
                <div class="register-block">
                    <div class="register-block-status">
                        <div class="badge-status-primary status-finished">
                            <span class="badge-status-text">Finalizado</span>
                        </div>
                    </div>
                    <div class="register-block-status">
                        <div class="button-icon-right">
                            <img src="/assets/icons/Notes-Edit--Streamline-Ultimate.png" alt="Editar">
                        </div>
                    </div>
                </div>
                <div class="register-block media-1024">
                    <div class="register-block-title">Histórico</div>
                    <div class="register-block-content">19.05 - 12:00 | Cadastro: <span class="register-block-content-text">Moacir Bastos</span></div>
                    <div class="register-block-content">19.05 - 17:00 | Atualização: <span class="register-block-content-text">Jorge Reis</span></div>
                    <div class="register-block-content">20.05 - 09:40 | Juntada: <span class="register-block-content-text">Jorge Reis</span></div>
                </div>
            </div>
        </div>

        <div class="register-line full">
            <div class="card-line left">
                <div class="register-block"></div>
            </div>
            <div class="card-line right">
                <div class="register-block">
                    <div class="register-block-status">
                        <div class="badge-status-primary status-canceled">
                            <span class="badge-status-text">Cancelada</span>
                        </div>
                    </div>
                    <div class="register-block-status">
                        <div class="button-icon-right">
                            <img src="/assets/icons/Notes-Edit--Streamline-Ultimate.png" alt="Editar">
                        </div>
                    </div>
                </div>
                <div class="register-block media-1024">
                    <div class="register-block-title">Histórico</div>
                    <div class="register-block-content">19.05 - 12:00 | Cadastro: <span class="register-block-content-text">Moacir Bastos</span></div>
                    <div class="register-block-content text-canceled">19.05 - 17:00 | Cancelamento: <span class="register-block-content-text">Jorge Reis</span></div>
                </div>
            </div>
        </div>

    </div>
</body>
</html>
