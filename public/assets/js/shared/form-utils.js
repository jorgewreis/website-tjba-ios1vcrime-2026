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
/**
 * Regras de validação por ID do campo
 */
const validationRules = {
    'form-processo': {
        maxLength: 25,
        required: true,
        pattern: /^\d{7}-\d{2}\.\d{4}\.\d{1}\.\d{2}\.\d{4}$/,
        customValidation(value) {
            if (typeof validarDigitoVerificadorCNJ === 'function') {
                return validarDigitoVerificadorCNJ(value);
            }
            return true;
        },
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
  validateInput,
  applyValidationFeedback,
  initFormValidation,
  validateForm,
  showToast,
  enqueueToast,
  flushToastQueue,
  showToastAndReload
};

