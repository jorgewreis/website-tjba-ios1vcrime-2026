// Funções e flags de permissões
function obterPerfisUsuario() {
    try {
        const raw = sessionStorage.getItem('UserLogin');
        if (!raw) return [];
        const arr = JSON.parse(raw);
        const user = Array.isArray(arr) ? arr[0] : null;
        const nucleo = String(user?.nucleo || '').toLowerCase();
        return nucleo
            .split(',')
            .map((p) => p.trim())
            .filter((p) => p !== '');
    } catch (e) {
        return [];
    }
}

function usuarioPodeExcluir() {
    const perfis = obterPerfisUsuario();
    const permitidos = new Set(['diretora', 'magistrada', 'admin']);
    return perfis.some((p) => permitidos.has(p));
}

function usuarioPodeAlocar() {
    const perfis = obterPerfisUsuario();
    const permitidos = new Set(['diretora', 'magistrada', 'admin']);
    return perfis.some((p) => permitidos.has(p));
}

function usuarioEhServidor() {
    const perfis = obterPerfisUsuario();
    // Servidor pode usar auto alocação (mesmo que tenha outros perfis)
    return perfis.includes('servidor');
}

const podeExcluir = usuarioPodeExcluir();
const podeAlocar = usuarioPodeAlocar();
const ehServidor = usuarioEhServidor();
const podeEditarConcluidas = podeExcluir;

// Controle de botões com base em permissão
const deleteButtons = document.querySelectorAll('.action-button.error');
if (!podeExcluir && deleteButtons.length) {
    deleteButtons.forEach((btn) => {
        btn.style.display = 'none';
        btn.setAttribute('aria-hidden', 'true');
    });
}

// Botão "Alocar usuário" - só para admin/diretora/magistrada
const btnAlocarUsuario = document.querySelectorAll('.btn-alocar-usuario');
if (!podeAlocar && btnAlocarUsuario.length) {
    btnAlocarUsuario.forEach((btn) => {
        btn.style.display = 'none';
        btn.setAttribute('aria-hidden', 'true');
    });
}

// Botões de remover alocação - só para admin/diretora/magistrada
const btnRemoverAlocacao = document.querySelectorAll('.btn-remover-alocacao');
if (!podeAlocar && btnRemoverAlocacao.length) {
    btnRemoverAlocacao.forEach((btn) => {
        btn.style.display = 'none';
        btn.setAttribute('aria-hidden', 'true');
    });
}

// Botão "Auto alocação" - só para servidor
const btnAutoAlocacao = document.querySelectorAll('.btn-auto-alocacao');
if (!ehServidor && btnAutoAlocacao.length) {
    btnAutoAlocacao.forEach((btn) => {
        btn.style.display = 'none';
        btn.setAttribute('aria-hidden', 'true');
    });
}

// Helpers visuais
function limparBlocosDireitaVazios() {
    document.querySelectorAll('.proposal-details-right .proposal-right-block').forEach((block) => {
        const hasVisible = Array.from(block.children).some((child) => {
            if (!(child instanceof HTMLElement)) return false;
            const style = window.getComputedStyle(child);
            return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0';
        });

        if (!hasVisible) {
            block.style.display = 'none';
            block.setAttribute('aria-hidden', 'true');
        } else {
            block.style.display = '';
            block.removeAttribute('aria-hidden');
        }
    });
}

function habilitarEdicaoGuiasConcluidas() {
    if (!podeEditarConcluidas) return;

    document.querySelectorAll('.proposal-closed-row.is-done').forEach((closedRow) => {
        closedRow.classList.add('can-edit');
        const parentRow = closedRow.closest('[data-id]');
        if (parentRow) {
            parentRow.classList.add('can-edit');
        }
    });
}

function statusIndicaConcluida(status) {
    const s = String(status || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();

    return s.includes('conclu') || s.includes('feito');
}

function liberarLinhasAposConclusao() {
    const rows = document.querySelectorAll('.row.table-row[data-id]');
    rows.forEach((row) => {
        row.classList.remove('table-row--locked');
        const closedRow = row.querySelector('.proposal-closed-row');
        if (closedRow) {
            closedRow.classList.remove('is-locked');
            const toggle = closedRow.querySelector('.proposal-closed-toggle');
            if (toggle) toggle.classList.remove('expanded');
        }
    });
}

function aplicarModoLinhaConcluida(row, statusReferencia) {
    if (!row) return;

    const statusElement = row.querySelector('.proposal-status-bar, .proposal-status, [data-status], .bg-label-info, .bg-label-alert, .bg-label-success, .bg-label-error');
    const closedRow = row.querySelector('.proposal-closed-row');
    const statusAtual = String(statusReferencia || statusElement?.textContent || '').trim();
    const concluida = statusIndicaConcluida(statusAtual);

    row.classList.toggle('proposal-parent-completed', concluida);
    if (closedRow) {
        closedRow.classList.toggle('is-done', concluida);

        let closedNote = closedRow.querySelector('.proposal-closed-note');
        if (concluida) {
            if (!closedNote) {
                closedNote = document.createElement('div');
                closedNote.className = 'proposal-closed-note';
                closedNote.textContent = 'Esta guia será removida automaticamente ao final do dia.';
                closedRow.appendChild(closedNote);
            }
        } else if (closedNote) {
            closedNote.remove();
        }
    }

    const checkbox = row.querySelector('.proposal-process-left .check');
    if (checkbox instanceof HTMLInputElement) {
        if (concluida) {
            checkbox.checked = false;
            checkbox.disabled = true;
        } else if (!row.classList.contains('table-row--locked')) {
            checkbox.disabled = false;
        }
    }

    if (concluida) {
        row.classList.remove('table-row--active');
        liberarLinhasAposConclusao();
        if (statusElement) {
            aplicarStatusVisualGuia(row, 'Concluída');
            if (statusElement.classList.contains('status-with-subtask')) {
                const statusText = statusElement.querySelector('.status-text');
                if (statusText) {
                    statusText.textContent = 'Concluída';
                }
            } else {
                statusElement.textContent = 'Concluída';
            }
        }
    }
}

limparBlocosDireitaVazios();
habilitarEdicaoGuiasConcluidas();

// Esconder botão de auto alocação se usuário já estiver alocado
if (ehServidor) {
    let userCpf = '';
    try {
        const raw = sessionStorage.getItem('UserLogin');
        if (raw) {
            const arr = JSON.parse(raw);
            const user = Array.isArray(arr) ? arr[0] : null;
            userCpf = String(user?.cpf || '').trim();
        }
    } catch (e) {
        userCpf = '';
    }

    if (userCpf) {
        document.querySelectorAll('.row.table-row').forEach((row) => {
            const alocados = row.querySelectorAll('.user-allocated[data-user-id]');
            const usuarioJaAlocado = Array.from(alocados).some((div) => {
                return div.dataset.userId === userCpf;
            });

            if (usuarioJaAlocado) {
                const btnAuto = row.querySelector('.btn-auto-alocacao');
                if (btnAuto) {
                    btnAuto.style.display = 'none';
                    btnAuto.setAttribute('aria-hidden', 'true');
                }
            }
        });
    }
}

limparBlocosDireitaVazios();
habilitarEdicaoGuiasConcluidas();
