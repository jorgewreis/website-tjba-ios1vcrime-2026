function sleep(qtd, tipo) {
  const t = String(tipo).trim().toLowerCase();
  const n = Number(qtd);

  if (!Number.isFinite(n) || n < 0) {
    throw new TypeError("qtd inválida. Use um número >= 0.");
  }

  const multipliers = {
    ms: 1, msec: 1, millisecond: 1, milliseconds: 1,
    s: 1000, sec: 1000, segundo: 1000, segundos: 1000,
    min: 60000, minuto: 60000, minutos: 60000
  };

  const mult = multipliers[t];
  if (!mult) {
    throw new TypeError('tipo inválido. Use "ms", "s" ou "min".');
  }

  return new Promise((resolve) => setTimeout(resolve, n * mult));
}


/* ===================================
   SISTEMA DE VALIDAÇÃO DE FORMULÁRIOS
   =================================== */

/**
 * Valida o dígito verificador de um número de processo CNJ
 * Formato: NNNNNNN-DD.AAAA.J.TT.OOOO
 * @param {string} numeroProcesso - Número do processo (com ou sem formatação)
 * @returns {boolean} - True se o dígito verificador está correto
 */
function validarDigitoVerificadorCNJ(numeroProcesso) {
    // Remove caracteres não numéricos
    const apenasNumeros = numeroProcesso.replace(/\D/g, '');
    
    // Verifica se tem 20 dígitos
    if (apenasNumeros.length !== 20) {
        return false;
    }
    
    // Extrai as partes do número
    // Formato: NNNNNNN-DD.AAAA.J.TT.OOOO
    const nnnnnnn = apenasNumeros.substring(0, 7);  // Número sequencial
    const dd = apenasNumeros.substring(7, 9);       // Dígito verificador
    const aaaa = apenasNumeros.substring(9, 13);    // Ano
    const j = apenasNumeros.substring(13, 14);      // Segmento da Justiça
    const tt = apenasNumeros.substring(14, 16);     // Tribunal
    const oooo = apenasNumeros.substring(16, 20);   // Origem
    
    // Conforme Resolução CNJ 65/2008, Anexo VIII:
    // 1. Desloca DD para o final e substitui por 00
    // 2. Monta: NNNNNNNAAAAJTROOOO00
    const numeroParaCalculo = nnnnnnn + aaaa + j + tt + oooo + '00';
    
    // 3. Calcula: DD = 98 - (número módulo 97)
    const resto = BigInt(numeroParaCalculo) % 97n;
    
    // Calcula o dígito verificador esperado
    const dvCalculado = 98n - resto;
    
    // Formata o DV com 2 dígitos
    const dvEsperado = dvCalculado.toString().padStart(2, '0');
    
    // Compara com o DV informado
    return dd === dvEsperado;
}

/**
 * Formata o número do processo CNJ com autocomplete para TJBA
 * @param {string} valor - Número do processo sem formatação
 * @param {boolean} autoComplete - Se deve autocompletar com .8.05.0103
 * @returns {string} - Número formatado NNNNNNN-DD.AAAA.J.TT.OOOO
 */
function formatarNumeroProcessoCNJ(valor, autoComplete = false) {
    const apenasNumeros = valor.replace(/\D/g, '');
    
    if (apenasNumeros.length === 0) return '';
    if (apenasNumeros.length <= 7) return apenasNumeros;
    if (apenasNumeros.length <= 9) return `${apenasNumeros.substring(0, 7)}-${apenasNumeros.substring(7)}`;
    if (apenasNumeros.length <= 13) {
        const formatted = `${apenasNumeros.substring(0, 7)}-${apenasNumeros.substring(7, 9)}.${apenasNumeros.substring(9)}`;
        
        // Se digitou até o ano completo (13 dígitos) e autocomplete está ativo
        if (apenasNumeros.length === 13 && autoComplete) {
            return formatted + '.8.05.0103';
        }
        return formatted;
    }
    if (apenasNumeros.length <= 14) return `${apenasNumeros.substring(0, 7)}-${apenasNumeros.substring(7, 9)}.${apenasNumeros.substring(9, 13)}.${apenasNumeros.substring(13)}`;
    if (apenasNumeros.length <= 16) return `${apenasNumeros.substring(0, 7)}-${apenasNumeros.substring(7, 9)}.${apenasNumeros.substring(9, 13)}.${apenasNumeros.substring(13, 14)}.${apenasNumeros.substring(14)}`;
    
    return `${apenasNumeros.substring(0, 7)}-${apenasNumeros.substring(7, 9)}.${apenasNumeros.substring(9, 13)}.${apenasNumeros.substring(13, 14)}.${apenasNumeros.substring(14, 16)}.${apenasNumeros.substring(16, 20)}`;
}

/**
 * Regras de validação por ID do campo
 */
const validationRules = {
    'form-processo': {
        maxLength: 25,
        required: true,
        pattern: /^\d{7}-\d{2}\.\d{4}\.\d{1}\.\d{2}\.\d{4}$/,
        customValidation: validarDigitoVerificadorCNJ,
        errorMessages: {
            required: 'O número do processo é obrigatório',
            pattern: 'Formato inválido. Use: NNNNNNN-DD.AAAA.J.TT.OOOO',
            customValidation: 'Dígito verificador inválido',
            maxLength: 'Número do processo não pode exceder 25 caracteres'
        },
        successMessage: 'Número de processo válido'
    }
};

/**
 * Valida um campo de input baseado em suas regras
 * @param {HTMLInputElement} input - Elemento input a ser validado
 * @returns {Object} - {valid: boolean, message: string}
 */
function validateInput(input) {
    const rules = validationRules[input.id];
    
    if (!rules) {
        return { valid: true, message: '' };
    }
    
    const value = input.value.trim();
    
    // Validação de campo obrigatório
    if (rules.required && value === '') {
        return { valid: false, message: rules.errorMessages.required };
    }
    
    // Se não é obrigatório e está vazio, é válido
    if (!rules.required && value === '') {
        return { valid: true, message: '' };
    }
    
    // Validação de tamanho máximo
    if (rules.maxLength && value.length > rules.maxLength) {
        return { valid: false, message: rules.errorMessages.maxLength };
    }
    
    // Validação de padrão (regex)
    if (rules.pattern && !rules.pattern.test(value)) {
        return { valid: false, message: rules.errorMessages.pattern };
    }
    
    // Validação customizada
    if (rules.customValidation && !rules.customValidation(value)) {
        return { valid: false, message: rules.errorMessages.customValidation };
    }        
}

/**
 * Aplica feedback visual no input baseado no resultado da validação
 * @param {HTMLInputElement} input - Elemento input
 * @param {Object} validationResult - Resultado da validação
 */
function applyValidationFeedback(input, validationResult) {
    // Remove classes anteriores
    input.classList.remove('error', 'success');
    
    // Remove mensagens anteriores
    const existingMessage = input.parentElement.querySelector('.error-message, .success-message');
    if (existingMessage) {
        existingMessage.remove();
    }
    
    // Se o campo está vazio e não é obrigatório, não mostra feedback
    const rules = validationRules[input.id];
    if (!rules || (!rules.required && input.value.trim() === '')) {
        return;
    }
    
    // Aplica classe e mensagem apropriada
    if (validationResult.valid) {
        input.classList.add('success');
        if (validationResult.message && input.value.trim() !== '') {
            const successMsg = document.createElement('span');
            successMsg.className = 'success-message';
            successMsg.textContent = validationResult.message;
            input.parentElement.appendChild(successMsg);
        }
    } else {
        input.classList.add('error');
        if (validationResult.message) {
            const errorMsg = document.createElement('span');
            errorMsg.className = 'error-message';
            errorMsg.textContent = validationResult.message;
            input.parentElement.appendChild(errorMsg);
        }
    }
}

/**
 * Inicializa a validação de formulários
 */
function initFormValidation() {
    // Encontra todos os inputs que têm regras de validação
    Object.keys(validationRules).forEach(inputId => {
        const input = document.getElementById(inputId);
        
        if (!input) return;
        
        // Formatação automática para campos de processo
        if (inputId.includes('processo') || inputId.includes('process')) {
            let lastValue = '';
            
            input.addEventListener('input', function(e) {
                const cursorPosition = (typeof this.selectionStart === 'number') ? this.selectionStart : this.value.length;
                const oldValue = lastValue;
                const newValue = this.value;
                
                // Detecta se o usuário digitou um ponto após o ano (13 dígitos numéricos)
                const apenasNumeros = newValue.replace(/\D/g, '');
                const shouldAutoComplete = apenasNumeros.length === 13 && !oldValue.endsWith('.8.05.0103');
                
                if (shouldAutoComplete) {
                    // Autocompleta com TJBA
                    this.value = formatarNumeroProcessoCNJ(newValue, true);
                    
                    // Posiciona cursor no final (seguro para ambientes sem API de seleção)
                    try { if (typeof this.setSelectionRange === 'function') this.setSelectionRange(this.value.length, this.value.length); } catch (err) { /* ignore */ }
                    
                    // Valida imediatamente após autocomplete
                    setTimeout(() => {
                        const result = validateInput(this);
                        applyValidationFeedback(this, result);
                    }, 100);
                } else {
                    // Formatação normal
                    const oldLength = this.value.length;
                    this.value = formatarNumeroProcessoCNJ(this.value, false);
                    
                    // Ajusta a posição do cursor (protege caso setSelectionRange não esteja disponível)
                    const newLength = this.value.length;
                    const diff = newLength - oldLength;
                    try { if (typeof this.setSelectionRange === 'function') this.setSelectionRange(cursorPosition + diff, cursorPosition + diff); } catch (err) { /* ignore */ }
                }
                
                lastValue = this.value;
            });
            
            // Adiciona evento de tecla para detectar ponto
            input.addEventListener('keydown', function(e) {
                const apenasNumeros = this.value.replace(/\D/g, '');
                
                // Se digitou ponto e tem exatamente 13 dígitos, autocompleta
                if (e.key === '.' && apenasNumeros.length === 13) {
                    e.preventDefault();
                    this.value = formatarNumeroProcessoCNJ(this.value + '.', true);
                    
                    // Valida imediatamente
                    setTimeout(() => {
                        const result = validateInput(this);
                        applyValidationFeedback(this, result);
                    }, 100);
                }
            });
        }
        
        // Validação em tempo real (ao sair do campo)
        input.addEventListener('blur', function() {
            const result = validateInput(this);
            applyValidationFeedback(this, result);
        });
        
        // Remove feedback ao focar no campo
        input.addEventListener('focus', function() {
            this.classList.remove('error', 'success');
            const message = this.parentElement.querySelector('.error-message, .success-message');
            if (message) {
                message.remove();
            }
        });
    });
}

/**
 * Valida todos os campos de um formulário
 * @param {HTMLFormElement} form - Formulário a ser validado
 * @returns {boolean} - True se todos os campos são válidos
 */
function validateForm(form) {
    let isValid = true;
    
    const inputs = form.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], textarea, select');
    
    inputs.forEach(input => {
        if (validationRules[input.id]) {
            const result = validateInput(input);
            applyValidationFeedback(input, result);
            
            if (!result.valid) {
                isValid = false;
            }
        }
    });
    
    return isValid;
}

// Inicializa a validação quando o DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFormValidation);
} else {
    initFormValidation();
}

/* ===================================
   TOASTS (COMUM)
   =================================== */

const TOAST_QUEUE_KEY = 'toast_queue_v1';
const TOAST_DEFER_KEY = 'toast_defer_v1';

function normalizeToast(type, title, message, details) {
    const defaults = {
        success: {
            title: 'Sucesso',
            message: 'A operação foi concluída.',
            details: 'Se precisar, você pode acompanhar os detalhes na lista.'
        },
        error: {
            title: 'Erro',
            message: 'Não foi possível concluir a operação.',
            details: 'Verifique os dados e tente novamente.'
        },
        warning: {
            title: 'Atenção',
            message: 'Há uma ação que precisa de cuidado.',
            details: 'Revise as informações antes de continuar.'
        },
        info: {
            title: 'Informação',
            message: 'Atualização disponível.',
            details: 'Nenhuma ação é necessária neste momento.'
        },
        productivity: {
            title: 'Produtividade registrada',
            message: 'Pontos adicionados ao seu registro.',
            details: 'Esse registro aparece no relatório mensal.'
        },
        tip: {
            title: 'Dica',
            message: 'Você pode agilizar esta etapa.',
            details: 'Use os atalhos disponíveis na tela.'
        }
    };

    const fallback = defaults[type] || defaults.info;
    return {
        title: (title && String(title).trim()) || fallback.title,
        message: (message && String(message).trim()) || fallback.message,
        details: (details && String(details).trim()) || fallback.details
    };
}

function showToast(type, title, message, details) {
    if (sessionStorage.getItem(TOAST_DEFER_KEY) === '1') {
        enqueueToast(type, title, message, details);
        return;
    }
    if (window.Toast && typeof window.Toast[type] === 'function') {
        const normalized = normalizeToast(type, title, message, details);
        window.Toast[type](normalized.title, normalized.message, normalized.details);
        return;
    }
}

function enqueueToast(type, title, message, details) {
    const item = { type, title, message, details };
    try {
        const raw = sessionStorage.getItem(TOAST_QUEUE_KEY);
        const list = raw ? JSON.parse(raw) : [];
        const next = Array.isArray(list) ? list : [];
        next.push(item);
        sessionStorage.setItem(TOAST_QUEUE_KEY, JSON.stringify(next));
    } catch (e) {
        console.warn('Falha ao enfileirar toast:', e);
    }
}

function flushToastQueue() {
    try {
        sessionStorage.removeItem(TOAST_DEFER_KEY);
        const raw = sessionStorage.getItem(TOAST_QUEUE_KEY);
        if (!raw) return;
        const list = JSON.parse(raw);
        sessionStorage.removeItem(TOAST_QUEUE_KEY);
        if (!Array.isArray(list)) return;
        list.forEach((item) => {
            if (!item) return;
            showToast(item.type || 'info', item.title || '', item.message || '', item.details || '');
        });
    } catch (e) {
        console.warn('Falha ao exibir fila de toasts:', e);
        sessionStorage.removeItem(TOAST_QUEUE_KEY);
    }
}

function showToastAndReload(type, title, message, details) {
    sessionStorage.setItem(TOAST_DEFER_KEY, '1');
    enqueueToast(type, title, message, details);
    location.reload();
}

flushToastQueue();

window.FormUtils = {
  sleep,
  validarDigitoVerificadorCNJ,
  formatarNumeroProcessoCNJ,
  validateInput,
  applyValidationFeedback,
  initFormValidation,
  validateForm,
  showToast,
  enqueueToast,
  flushToastQueue,
  showToastAndReload
};

