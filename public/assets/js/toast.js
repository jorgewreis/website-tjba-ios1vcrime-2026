/* ============================================
   SISTEMA DE NOTIFICAÇÕES TOAST
   ============================================
   Gerenciamento de notificações flutuantes
   ============================================ */

(function(window) {
    'use strict';

    /**
     * Classe para gerenciar notificações toast
     */
    class ToastManager {
        constructor() {
            this.container = null;
            this.toasts = [];
            this.init();
        }

        /**
         * Inicializa o container de toasts
         */
        init() {
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.className = 'toast-container';
                document.body.appendChild(this.container);
            }
        }

        /**
         * Calcula o tempo de leitura baseado no texto
         * @param {string} text - Texto completo da mensagem
         * @returns {number} Tempo em milissegundos
         */
        calculateReadTime(text) {
            // Média de 150 palavras por minuto (leitura mais confortável)
            const wordsPerMinute = 150;
            const words = text.trim().split(/\s+/).length;
            const minutes = words / wordsPerMinute;
            const milliseconds = minutes * 60 * 1000;
            
            // Mínimo 5s, máximo 15s
            return Math.max(5000, Math.min(15000, milliseconds));
        }

        /**
         * Cria e exibe um toast
         * @param {Object} options - Opções do toast
         * @param {string} options.type - Tipo: success, error, warning, info, tip, productivity
         * @param {string} options.title - Título do toast
         * @param {string} options.message - Mensagem principal
         * @param {string} [options.details] - Detalhes complementares
         * @param {number} [options.duration] - Duração em ms (auto se não informado)
         * @param {Function} [options.onClick] - Callback ao clicar
         * @param {Function} [options.onClose] - Callback ao fechar
         * @returns {HTMLElement} Elemento do toast criado
         */
        show(options) {
            const {
                type = 'info',
                title = '',
                message = '',
                details = '',
                duration = null,
                onClick = null,
                onClose = null
            } = options;

            // Calcular duração automaticamente se não fornecida
            const fullText = `${title} ${message} ${details}`;
            const autoDuration = duration !== null ? duration : this.calculateReadTime(fullText);

            // Capturar posições atuais para animação suave do empilhamento
            const previousPositions = this.getToastPositions();

            // Criar elemento do toast
            const toast = document.createElement('div');
            toast.className = `toast toast-${type} toast-entering`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'polite');
            toast.setAttribute('aria-atomic', 'true');

            // Criar header
            const header = document.createElement('div');
            header.className = 'toast-header';

            const titleEl = document.createElement('h3');
            titleEl.className = 'toast-title';
            titleEl.textContent = title;

            const closeBtn = document.createElement('button');
            closeBtn.className = 'toast-close';
            closeBtn.innerHTML = '×';
            closeBtn.setAttribute('aria-label', 'Fechar notificação');
            closeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.remove(toast, onClose);
            });

            header.appendChild(titleEl);
            header.appendChild(closeBtn);
            toast.appendChild(header);

            // Criar mensagem
            if (message) {
                const messageEl = document.createElement('p');
                messageEl.className = 'toast-message';
                messageEl.textContent = message;
                toast.appendChild(messageEl);
            }

            // Criar detalhes
            if (details) {
                const detailsEl = document.createElement('p');
                detailsEl.className = 'toast-details';
                detailsEl.textContent = details;
                toast.appendChild(detailsEl);
            }

            // Criar barra de progresso
            const progress = document.createElement('div');
            progress.className = 'toast-progress';
            progress.style.animationDuration = `${autoDuration}ms`;
            toast.appendChild(progress);

            // Click handler
            if (onClick && typeof onClick === 'function') {
                toast.addEventListener('click', (e) => {
                    if (!e.target.classList.contains('toast-close')) {
                        onClick(toast);
                    }
                });
            }

            // Adicionar ao container
            this.container.appendChild(toast);
            this.toasts.push(toast);

            // Remover classe de animação após entrada
            setTimeout(() => {
                toast.classList.remove('toast-entering');
            }, 500);

            // Animar movimento dos toasts existentes (empilhamento suave)
            this.animateReflow(previousPositions);

            // Auto-remoção após duração
            const removeTimeout = setTimeout(() => {
                this.remove(toast, onClose);
            }, autoDuration);

            // Armazenar timeout no elemento para possível cancelamento
            toast._removeTimeout = removeTimeout;

            return toast;
        }

        /**
         * Captura posições atuais dos toasts
         * @param {HTMLElement} [excludeToast] - Toast a excluir da captura
         * @returns {Map<HTMLElement, DOMRect>}
         */
        getToastPositions(excludeToast = null) {
            const positions = new Map();
            this.toasts.forEach((toast) => {
                if (!toast || toast === excludeToast || !toast.parentElement) return;
                positions.set(toast, toast.getBoundingClientRect());
            });
            return positions;
        }

        /**
         * Anima o deslocamento dos toasts após mudança de layout
         * @param {Map<HTMLElement, DOMRect>} previousPositions
         */
        animateReflow(previousPositions) {
            if (!previousPositions || previousPositions.size === 0) return;

            this.toasts.forEach((toast) => {
                if (!toast || !toast.parentElement) return;
                if (toast.classList.contains('toast-entering')) return;
                if (toast.classList.contains('toast-fade-out')) return;

                const previous = previousPositions.get(toast);
                if (!previous) return;

                const current = toast.getBoundingClientRect();
                const deltaY = previous.top - current.top;
                if (deltaY === 0) return;

                toast.style.transition = 'none';
                toast.style.transform = `translateY(${deltaY}px)`;

                requestAnimationFrame(() => {
                    toast.style.transition = 'transform 2s ease-in-out';
                    toast.style.transform = '';
                });
            });
        }

        /**
         * Remove um toast com animação
         * @param {HTMLElement} toast - Elemento do toast
         * @param {Function} [onClose] - Callback ao fechar
         */
        remove(toast, onClose) {
            if (!toast || !toast.parentElement) return;

            // Cancelar timeout de auto-remoção se existir
            if (toast._removeTimeout) {
                clearTimeout(toast._removeTimeout);
            }

            // Adicionar classe de fade out
            toast.classList.add('toast-fade-out');

            // Aguardar fim da animação antes de remover do DOM
            setTimeout(() => {
                const previousPositions = this.getToastPositions(toast);

                if (toast.parentElement) {
                    toast.remove();
                }
                
                // Remover da lista
                const index = this.toasts.indexOf(toast);
                if (index > -1) {
                    this.toasts.splice(index, 1);
                }

                // Callback de fechamento
                if (onClose && typeof onClose === 'function') {
                    onClose(toast);
                }

                this.animateReflow(previousPositions);
            }, 2000);
        }

        /**
         * Remove todos os toasts
         */
        clear() {
            this.toasts.forEach(toast => this.remove(toast));
        }

        /**
         * Atalhos para tipos comuns
         */
        success(title, message, details, duration) {
            return this.show({ type: 'success', title, message, details, duration });
        }

        error(title, message, details, duration) {
            return this.show({ type: 'error', title, message, details, duration });
        }

        warning(title, message, details, duration) {
            return this.show({ type: 'warning', title, message, details, duration });
        }

        alert(title, message, details, duration) {
            return this.show({ type: 'alert', title, message, details, duration });
        }

        info(title, message, details, duration) {
            return this.show({ type: 'info', title, message, details, duration });
        }

        tip(title, message, details, duration) {
            return this.show({ type: 'tip', title, message, details, duration });
        }

        hint(title, message, details, duration) {
            return this.show({ type: 'hint', title, message, details, duration });
        }

        productivity(title, message, details, duration) {
            return this.show({ type: 'productivity', title, message, details, duration });
        }
    }

    // Criar instância global
    const toastManager = new ToastManager();

    // Expor no window para uso global
    window.Toast = toastManager;

    // Expor também como módulo se necessário
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = ToastManager;
    }

})(window);
