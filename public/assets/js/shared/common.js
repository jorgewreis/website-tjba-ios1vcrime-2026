(function (global) {
  const App = global.App || (global.App = {});

  async function validateSessionToken(endpoint = "/admin/api/checkToken.php") {
    try {
      const response = await fetch(endpoint);
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const result = await response.json();
      return result.valid === true;
    } catch (error) {
      console.error("Erro ao validar token:", error);
      return false;
    }
  }

  /**
   * Faz logout com segurança: invalida sessão/token no servidor e redireciona para a tela de login.
   * @param {string} [endpoint] - URL do logout. Use "/logout.php" para a área pública.
   * @param {boolean} [redirectOnly] - Se true, após a requisição redireciona sem esperar JSON (ex.: logout.php que retorna 302).
   */
  async function performLogout(endpoint = "/logout.php", redirectOnly = true) {
    const goLogin = () => window.location.replace("/index.php?nocache=" + Date.now());
    try {
      const response = await fetch(endpoint, {
        method: "POST",
        credentials: "same-origin",
        cache: "no-store",
        redirect: "manual"
      });
      if (redirectOnly) {
        // Força nova página sem cache (token CSRF no formulário deve bater com a sessão nova).
        goLogin();
        return;
      }
      const result = await response.json();
      if (result && result.success) {
        goLogin();
      } else {
        goLogin();
      }
    } catch (error) {
      console.error("Erro ao fazer logout:", error);
      goLogin();
    }
  }

  function showNotification(message, type = "info", duration = 3000) {
    const id = `notification-${Date.now()}`;
    const html = `
      <div id="${id}" class="notification notification-${type}">
        <span>${message}</span>
        <button class="close-btn">×</button>
      </div>
    `;

    const container =
      document.getElementById("notifications-container") ||
      (() => {
        const div = document.createElement("div");
        div.id = "notifications-container";
        div.style.cssText = "position: fixed; top: 20px; right: 20px; z-index: 9999;";
        document.body.appendChild(div);
        return div;
      })();

    container.insertAdjacentHTML("beforeend", html);

    const notification = document.getElementById(id);
    const closeButton = notification?.querySelector(".close-btn");
    closeButton?.addEventListener("click", () => notification?.remove());

    if (duration > 0) {
      setTimeout(() => {
        notification?.remove();
      }, duration);
    }
  }

  async function confirmAction(message) {
    return Promise.resolve(window.confirm(message));
  }

  App.Common = {
    validateSessionToken,
    performLogout,
    showNotification,
    confirmAction
  };
})(window);
