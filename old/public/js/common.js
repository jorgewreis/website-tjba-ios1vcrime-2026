/**
 * common.js - Funções comuns para páginas internas (protegidas)
 * Responsável por utilitários usados em páginas autenticadas
 * Não deve conter código de autenticação (login/recuperação de senha)
 * 
 * NOTA: CPF utilities estão em cpf.js
 * NOTA: Login e password reset estão em auth.js
 */

/**
 * Valida token de sessão via API
 * @param {string} endpoint - URL do endpoint de validação de token
 * @returns {Promise<boolean>} - true se token válido
 */
async function validateSessionToken(endpoint = "/admin/api/checkToken.php") {
  try {
    const response = await fetch(endpoint);
    const result = await response.json();
    return result.valid === true;
  } catch (err) {
    console.error("Erro ao validar token:", err);
    return false;
  }
}

/**
 * Realiza logout via API
 * @param {string} endpoint - URL do endpoint de logout
 */
async function performLogout(endpoint = "/admin/api/logout.php") {
  try {
    const response = await fetch(endpoint, { method: "POST" });
    const result = await response.json();
    
    if (result.success) {
      window.location.href = "/index.php";
    }
  } catch (err) {
    console.error("Erro ao fazer logout:", err);
    window.location.href = "/index.php";
  }
}

/**
 * Mostra mensagem de notificação temporária
 * @param {string} message - Mensagem a exibir
 * @param {string} type - Tipo: 'success', 'error', 'info', 'warning'
 * @param {number} duration - Duração em milissegundos (0 = permanente)
 */
function showNotification(message, type = "info", duration = 3000) {
  const notificationId = "notification-" + Date.now();
  const notificationHTML = `
    <div id="${notificationId}" class="notification notification-${type}">
      <span>${message}</span>
      <button class="close-btn">×</button>
    </div>
  `;

  const container = document.getElementById("notifications-container") || 
                   (() => {
                     const div = document.createElement("div");
                     div.id = "notifications-container";
                     div.style.cssText = "position: fixed; top: 20px; right: 20px; z-index: 9999;";
                     document.body.appendChild(div);
                     return div;
                   })();

  container.insertAdjacentHTML("beforeend", notificationHTML);

  const notificationEl = document.getElementById(notificationId);
  const closeBtn = notificationEl.querySelector(".close-btn");

  closeBtn.addEventListener("click", () => {
    notificationEl.remove();
  });

  if (duration > 0) {
    setTimeout(() => {
      notificationEl.remove();
    }, duration);
  }
}

/**
 * Confirma ação com modal/diálogo
 * @param {string} message - Mensagem de confirmação
 * @returns {Promise<boolean>} - true se confirmado
 */
async function confirmAction(message) {
  return new Promise((resolve) => {
    if (confirm(message)) {
      resolve(true);
    } else {
      resolve(false);
    }
  });
}

// Expõe funções públicas
window.validateSessionToken = validateSessionToken;
window.performLogout = performLogout;
window.showNotification = showNotification;
window.confirmAction = confirmAction;
