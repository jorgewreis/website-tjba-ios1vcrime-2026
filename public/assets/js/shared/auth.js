(function (global) {
  const App = global.App || (global.App = {});

  async function initLogin(
    formSelector = "#loginForm",
    endpoint = "login.php",
    erroId = "erro",
    alertId = "alert-login"
  ) {
    const form = document.querySelector(formSelector);
    if (!form) return;

    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const formData = new FormData(form);

      try {
        const response = await fetch(endpoint, {
          method: "POST",
          body: formData,
          credentials: "include"
        });

        const result = await response.json();

        // Só redireciona se o servidor confirmar sucesso (HTTP 2xx + success).
        // A home.php fará nova verificação de sessão/token ao carregar.
        if (response.ok && result && result.success === true) {
          if (global.App && global.App.Prefs) {
            const lembrar = form.querySelector("#lembrarUsuario");
            const cpfInput = form.querySelector('input[name="cpf"]');
            const cpf = cpfInput ? (cpfInput.value || "").replace(/\D/g, "") : "";
            if (lembrar && lembrar.checked && cpf.length === 11) {
              global.App.Prefs.setRememberCpf(cpfInput.value.trim());
            } else {
              global.App.Prefs.clearRememberCpf();
            }
          }
          window.location.replace("/home.php");
          return;
        }

        const erroElement = document.getElementById(erroId);
        if (erroElement) erroElement.textContent = result.message;

        const alertBox = document.getElementById(alertId);
        if (alertBox) alertBox.style.display = "flex";
      } catch (error) {
        const erroElement = document.getElementById(erroId);
        if (erroElement) {
          erroElement.textContent = "Erro de conexão. Tente novamente mais tarde.";
        }
        const alertBox = document.getElementById(alertId);
        if (alertBox) alertBox.style.display = "flex";
      }
    });
  }

  async function initRecuperaSenha(
    formSelector = "#resetForm",
    cpfSelector = "#cpf",
    emailSelector = "#email",
    endpoint = "/envia_mail.php"
  ) {
    const form = document.querySelector(formSelector);
    const cpfInput = document.querySelector(cpfSelector);
    const emailInput = document.querySelector(emailSelector);
    if (!form || !cpfInput || !emailInput) return;

    const onlyDigits = (value) => (value || "").toString().replace(/\D+/g, "");

    const elements = {
      alertBox: document.getElementById("alert-login"),
      alertTitle: document.querySelector("#alert-login .alert-header .text"),
      erroField: document.getElementById("erro")
    };

    const setAlert = (type, title, message) => {
      if (!elements.alertBox) return;
      elements.alertBox.classList.remove("danger", "success", "primary");
      if (type) {
        elements.alertBox.classList.add(type);
      }
      if (elements.erroField) {
        elements.erroField.textContent = message || "";
      }
      if (elements.alertTitle) {
        elements.alertTitle.textContent = title || "";
      }
      elements.alertBox.style.display = "block";
    };

    const clearAlert = () => {
      if (elements.alertBox) {
        elements.alertBox.style.display = "none";
        elements.alertBox.classList.remove("danger", "success", "primary");
      }
      if (elements.erroField) {
        elements.erroField.textContent = "";
      }
    };

    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      clearAlert();

      const cpfDigits = onlyDigits(cpfInput.value);
      const email = (emailInput.value || "").trim();

      if (cpfDigits.length !== 11) {
        setAlert("danger", "Erro", "Informe um CPF válido.");
        return;
      }

      if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        setAlert("danger", "Erro", "Informe um e-mail válido.");
        return;
      }

      const submitBtn = form.querySelector("button[type=\"submit\"]");
      const previousText = submitBtn ? submitBtn.textContent : "";

      try {
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.textContent = "Processando...";
        }

        const response = await fetch(endpoint, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json"
          },
          body: JSON.stringify({
            cpf: cpfDigits,
            email
          })
        });

        const result = await response.json().catch(() => ({}));
        const success = result && result.success === true;
        const emailSent = result && result.emailSent === true;
        const message = (result && result.message) || "Não foi possível processar. Tente novamente.";

        if (success) {
          setAlert(
            emailSent ? "success" : "primary",
            emailSent ? "E-mail enviado" : "Senha redefinida",
            message
          );
          form.reset();
        } else {
          setAlert("danger", "Erro", message);
        }
      } catch (error) {
        console.error("Falha ao enviar reset:", error);
        setAlert("danger", "Erro", "Erro de conexão. Tente novamente mais tarde.");
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = previousText || "Solicitar reset";
        }
      }
    });
  }

  App.Auth = {
    initLogin,
    initRecuperaSenha
  };
})(window);
