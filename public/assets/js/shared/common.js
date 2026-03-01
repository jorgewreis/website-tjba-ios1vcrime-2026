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

  async function performLogout(endpoint = "/admin/api/logout.php") {
    try {
      const response = await fetch(endpoint, { method: "POST" });
      const result = await response.json();
      if (result.success) {
        window.location.href = "/index.php";
      } else {
        window.location.href = "/index.php";
      }
    } catch (error) {
      console.error("Erro ao fazer logout:", error);
      window.location.href = "/index.php";
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
