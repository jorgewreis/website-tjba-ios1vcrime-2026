<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <?php include __DIR__ . '/_theme_init.php'; ?>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Consulta CEP - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/reset.css">
        <link rel="stylesheet" href="/assets/css/shared/color.css">
        <link rel="stylesheet" href="/assets/css/shared/page.css">
        <link rel="stylesheet" href="/assets/css/shared/form.css">
        <link rel="stylesheet" href="/assets/css/shared/table.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Consultas - CEP -</span>
            <span class="specific">Consulta</span>
        </div>
        <h1 class="title-page">Consulta CEP</h1>
        <p class="description-page">Pesquise por CEP ou por parte do endereço.</p>
    </div>

    <div class="card-page">

        <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Consulta</div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <div class="col">
                <label for="filter-busca">CEP ou Endereço <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-xl height-md white" id="filter-busca" placeholder="Digite um CEP ou um endereço, como jasmin, nelson costa, ilheus" required>
            </div>
        </div>

        <div class="row info padding-tb-md">
            <span>*Para endereço, informe no formato <strong style="margin-left: 3px">logradouro, cidade</strong> ou <strong style="margin-left: 3px">logradouro, bairro, cidade</strong>. Se informar apenas o logradouro, a consulta considera Ilhéus/BA como padrão.</span>
        </div>

        <div class="row table right buttons padding-tb-md padding-lr-lg border-top row-gap-10">
            <button class="btn btn-secondary" id="limpar-btn" type="button">Limpar</button>
            <button class="btn btn-primary" id="consultar-btn" type="button">Consultar</button>
        </div>

        <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Resultados</div>
        <div class="row table between padding-tb-lg padding-lr-md border-bottom">
            <div class="col right">
                <div class="col">
                    <label for="items-per-page">Itens por página</label>
                    <select class="width-sm height-md white" id="items-per-page">
                        <option value="5">5</option>
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="col full-line register" id="resultado-container">
            <div class="row table">
                <div class="col full-line">
                    <label id="consulta-feedback" class="status-placeholder" aria-live="polite"></label>
                </div>
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
            var buscaInput = document.getElementById('filter-busca');
            var consultarBtn = document.getElementById('consultar-btn');
            var limparBtn = document.getElementById('limpar-btn');
            var itemsPerPageSelect = document.getElementById('items-per-page');
            var resultadoContainer = document.getElementById('resultado-container');
            var feedback = document.getElementById('consulta-feedback');
            var currentResults = [];
            var currentPage = 1;

            function escapeHtml(value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function formatCep(value) {
                var digits = String(value || '').replace(/\D/g, '');
                if (digits.length !== 8) return value || '';
                return digits.slice(0, 2) + '.' + digits.slice(2, 5) + '-' + digits.slice(5);
            }

            function getItemsPerPage() {
                var value = parseInt(itemsPerPageSelect && itemsPerPageSelect.value ? itemsPerPageSelect.value : '10', 10);
                return [5, 10, 25, 50].indexOf(value) !== -1 ? value : 10;
            }

            function getPaginationButtons(totalPages, current) {
                var buttons = [];
                var start = Math.max(1, current - 2);
                var end = Math.min(totalPages, current + 2);
                for (var pageNumber = start; pageNumber <= end; pageNumber += 1) {
                    buttons.push(pageNumber);
                }
                return buttons;
            }

            function getTrailingPages(totalPages, buttons) {
                var trailing = [];
                if (buttons.length === 0) {
                    return trailing;
                }
                var lastVisible = buttons[buttons.length - 1];
                if (lastVisible >= totalPages) {
                    return trailing;
                }
                var start = Math.max(totalPages - 1, lastVisible + 1);
                for (var pageNumber = start; pageNumber <= totalPages; pageNumber += 1) {
                    if (buttons.indexOf(pageNumber) === -1) {
                        trailing.push(pageNumber);
                    }
                }
                return trailing;
            }

            function setFeedback(message, type) {
                if (!feedback) return;
                feedback.innerHTML = message
                    ? '<label class="padding-lr-lg status ' + (type === 'error' ? 'status-cancelled' : 'status-finished') + '">' + escapeHtml(message) + '</label>'
                    : '';
            }

            function resetResultContainer() {
                if (!resultadoContainer) return;
                resultadoContainer.innerHTML = '' +
                    '<div class="row table between padding-tb-lg padding-lr-md">' +
                        '<div class="col">' +
                            '<label id="consulta-feedback" class="status-placeholder" aria-live="polite"></label>' +
                        '</div>' +
                    '</div>';
                feedback = document.getElementById('consulta-feedback');
            }

            function renderResultRow(item) {
                return '' +
                    '<div class="row table between full-line padding-tb-sm padding-lr-md border-bottom">' +
                        '<div class="row left fit row-gap-10">' +
                            '<div class="col" style="min-width: 300px;">' +
                                '<label style="font-size: 10px;">Logradouro</label>' +
                                '<span class="major-info text-ellipsis" style="margin: 0 0 0 12px;">' + escapeHtml(item.logradouro || '') + '</span>' +
                            '</div>' +
                            '<div class="col" style="width: 210px;">' +
                                '<label style="font-size: 10px;">Bairro</label>' +
                                '<span class="major-info text-ellipsis" style="margin: 0 0 0 12px;">' + escapeHtml(item.bairro || '') + '</span>' +
                            '</div>' +
                            '<div class="col" style="width: 150px;">' +
                                '<label style="font-size: 10px;">Cidade</label>' +
                                '<span class="major-info text-ellipsis" style="margin: 0 0 0 12px;">' + escapeHtml(item.cidade || '') + ' - ' + escapeHtml(item.uf || '') + '</span>' +
                            '</div>' +
                        '</div>' +
                        '<div class="row right fit row-gap-10">' +
                            '<div class="col" style="width: 110px;">' +
                                '<label style="font-size: 10px;">CEP</label>' +
                                '<span class="major-info text-ellipsis" style="margin: 0 0 0 12px;">' + escapeHtml(formatCep(item.cep || '')) + '</span>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
            }

            function renderPagination(totalItems, page, perPage) {
                var totalPages = totalItems > 0 ? Math.ceil(totalItems / perPage) : 1;
                var startItem = totalItems > 0 ? ((page - 1) * perPage) + 1 : 0;
                var endItem = totalItems > 0 ? Math.min(page * perPage, totalItems) : 0;
                var isPrevDisabled = page <= 1;
                var isNextDisabled = page >= totalPages;
                var paginationButtons = getPaginationButtons(totalPages, page);
                var trailingPages = getTrailingPages(totalPages, paginationButtons);
                var paginationEllipsis = trailingPages.length > 0 && paginationButtons[paginationButtons.length - 1] < (totalPages - 1);

                return '' +
                    '<div class="row table between padding-tb-lg padding-lr-md">' +
                        '<span class="left pagination-info" id="consulta-feedback">Mostrando ' + startItem + '-' + endItem + ' de ' + totalItems + ' endereços (' + totalPages + ' páginas, ' + perPage + ' itens por página)</span>' +
                        '<div class="right pagination-controls">' +
                            '<a class="pagination-symbol' + (isPrevDisabled ? ' disabled' : '') + '" href="#" data-page-action="prev" ' + (isPrevDisabled ? 'aria-disabled="true"' : '') + '>' +
                                '<img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior">' +
                            '</a>' +
                            paginationButtons.map(function (pageNumber) {
                                return '<a class="pagination-number' + (pageNumber === page ? ' active' : '') + '" href="#" data-page-number="' + pageNumber + '">' + pageNumber + '</a>';
                            }).join('') +
                            (paginationEllipsis
                                ? '<a class="pagination-symbol pagination-ellipsis" aria-hidden="true"><img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas"></a>'
                                : '') +
                            trailingPages.map(function (pageNumber) {
                                return '<a class="pagination-number' + (pageNumber === page ? ' active' : '') + '" href="#" data-page-number="' + pageNumber + '">' + pageNumber + '</a>';
                            }).join('') +
                            '<a class="pagination-symbol' + (isNextDisabled ? ' disabled' : '') + '" href="#" data-page-action="next" ' + (isNextDisabled ? 'aria-disabled="true"' : '') + '>' +
                                '<img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página">' +
                            '</a>' +
                        '</div>' +
                    '</div>';
            }

            function attachPaginationEvents(totalItems, page, perPage) {
                var totalPages = totalItems > 0 ? Math.ceil(totalItems / perPage) : 1;
                document.querySelectorAll('[data-page-number]').forEach(function (link) {
                    link.addEventListener('click', function (event) {
                        event.preventDefault();
                        var targetPage = parseInt(this.getAttribute('data-page-number') || '1', 10);
                        if (!isNaN(targetPage) && targetPage >= 1 && targetPage <= totalPages && targetPage !== currentPage) {
                            currentPage = targetPage;
                            renderCurrentResults();
                        }
                    });
                });

                document.querySelectorAll('[data-page-action]').forEach(function (link) {
                    link.addEventListener('click', function (event) {
                        event.preventDefault();
                        if (this.classList.contains('disabled')) {
                            return;
                        }
                        var action = this.getAttribute('data-page-action');
                        if (action === 'prev' && currentPage > 1) {
                            currentPage -= 1;
                            renderCurrentResults();
                        } else if (action === 'next' && currentPage < totalPages) {
                            currentPage += 1;
                            renderCurrentResults();
                        }
                    });
                });
            }

            function renderSingleResult(data) {
                if (!resultadoContainer) return;
                currentResults = [data];
                currentPage = 1;
                resultadoContainer.innerHTML = '' +
                    renderResultRow(data) +
                    renderPagination(1, 1, getItemsPerPage());
                feedback = document.getElementById('consulta-feedback');
                attachPaginationEvents(1, 1, getItemsPerPage());
            }

            function renderCurrentResults() {
                if (!resultadoContainer) return;
                var perPage = getItemsPerPage();
                var totalItems = currentResults.length;
                var totalPages = totalItems > 0 ? Math.ceil(totalItems / perPage) : 1;

                if (currentPage > totalPages) {
                    currentPage = totalPages;
                }
                if (currentPage < 1) {
                    currentPage = 1;
                }

                var start = (currentPage - 1) * perPage;
                var pageResults = currentResults.slice(start, start + perPage);

                var html = "";
                pageResults.forEach(function (item) {
                    html += renderResultRow(item);
                });

                html += renderPagination(totalItems, currentPage, perPage);

                resultadoContainer.innerHTML = html;
                feedback = document.getElementById('consulta-feedback');
                attachPaginationEvents(totalItems, currentPage, perPage);
            }

            function renderListResults(results) {
                currentResults = Array.isArray(results) ? results.slice() : [];
                currentPage = 1;
                renderCurrentResults();
            }

            function showError(message) {
                resetResultContainer();
                setFeedback(message, 'error');
                if (typeof showToast === 'function') {
                    showToast({
                        type: 'error',
                        variant: 'full',
                        title: 'Erro',
                        message: message
                    });
                }
            }

            function performSearch() {
                var query = (buscaInput && buscaInput.value ? buscaInput.value : '').trim();
                if (!query) {
                    showError('Informe um CEP ou endereço para consulta.');
                    return;
                }

                consultarBtn.disabled = true;
                resetResultContainer();
                setFeedback('Consultando...', 'success');

                fetch('/api/cep/consulta.php?q=' + encodeURIComponent(query), {
                    method: 'GET',
                    credentials: 'same-origin'
                })
                    .then(function (response) {
                        return response.json().then(function (body) {
                            return { ok: response.ok, body: body };
                        });
                    })
                    .then(function (result) {
                        if (!result.ok) {
                            throw new Error(result.body && result.body.error ? result.body.error : 'Não foi possível concluir a consulta.');
                        }

                        if (result.body.mode === 'cep' && result.body.data) {
                            renderSingleResult(result.body.data);
                            return;
                        }

                        if (Array.isArray(result.body.results)) {
                            renderListResults(result.body.results);
                            return;
                        }

                        throw new Error('Resposta inválida da consulta.');
                    })
                    .catch(function (error) {
                        showError(error && error.message ? error.message : 'Erro ao consultar CEP.');
                    })
                    .finally(function () {
                        consultarBtn.disabled = false;
                    });
            }

            consultarBtn?.addEventListener('click', performSearch);
            limparBtn?.addEventListener('click', function () {
                if (buscaInput) buscaInput.value = '';
                currentResults = [];
                currentPage = 1;
                resetResultContainer();
                setFeedback('', 'success');
                buscaInput?.focus();
            });
            itemsPerPageSelect?.addEventListener('change', function () {
                if (currentResults.length > 1) {
                    currentPage = 1;
                    renderCurrentResults();
                } else if (currentResults.length === 1) {
                    renderSingleResult(currentResults[0]);
                }
            });
            buscaInput?.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    performSearch();
                }
            });
        })();
    </script>
</body>
</html>
