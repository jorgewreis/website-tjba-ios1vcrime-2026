(function (global) {
  const App = global.App || (global.App = {});

  async function validateSessionToken(endpoint = "/admin/api/checkToken.php") {
    if (App.User && typeof App.User.validateSessionToken === "function") {
      return App.User.validateSessionToken(endpoint);
    }
    try {
      const response = await fetch(endpoint);
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const result = await response.json();
      return result.valid === true || result.success === true;
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
  function performLogout(endpoint = "/logout.php") {
    const ownToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
    let csrfToken = ownToken;

    // A ação pode ser chamada de um iframe da mesma origem; nesse caso,
    // o token pertence ao documento principal que contém a navegação.
    if (!csrfToken) {
      try {
        csrfToken = global.top?.document
          ?.querySelector('meta[name="csrf-token"]')
          ?.getAttribute("content") || "";
      } catch (_) {
        csrfToken = "";
      }
    }

    if (!csrfToken) {
      console.error("Não foi possível localizar o token CSRF do logout.");
      showNotification("Não foi possível encerrar a sessão. Atualize a página e tente novamente.", "error");
      return;
    }

    // Use uma submissão de formulário no topo da janela. Ao contrário de
    // fetch + redirect, isso preserva o fluxo normal de cookies e navegação
    // mesmo quando o botão é acionado dentro de uma moldura.
    const form = document.createElement("form");
    form.method = "POST";
    form.action = endpoint;
    form.target = "_top";
    form.style.display = "none";

    const tokenInput = document.createElement("input");
    tokenInput.type = "hidden";
    tokenInput.name = "csrf_token";
    tokenInput.value = csrfToken;
    form.appendChild(tokenInput);

    document.body.appendChild(form);
    form.submit();
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
    confirmAction,
    getUserPerfis: function (endpoint = "/admin/api/getPerfis.php", options = {}) {
      if (App.User && typeof App.User.fetchPerfis === "function") {
        return App.User.fetchPerfis(endpoint, options);
      }
      return Promise.resolve([]);
    }
  };
})(window);
