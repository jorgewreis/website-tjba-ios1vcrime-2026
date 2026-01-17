/**
 * auth.js - Funções de autenticação (páginas públicas)
 * Responsável por login e recuperação de senha
 * Dependências: cpf.js (para validação de CPF)
 */

/**
 * Inicializa o formulário de login
 * @param {string} formSelector - Seletor do formulário de login
 * @param {string} endpoint - URL do endpoint de login
 * @param {string} erroId - ID do elemento para exibir erro
 * @param {string} alertId - ID da caixa de alerta
 */
async function initLogin(formSelector = "#loginForm", endpoint = "login.php", erroId = "erro", alertId = "alert-login") {
  const form = document.querySelector(formSelector);
  if (!form) return;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const formData = new FormData(form);

    try {
      const response = await fetch(endpoint, {
        method: "POST",
        body: formData,
      });

      const result = await response.json();

      if (result.success) {
        window.location.href = "/home.php";
      } else {
        const erro = document.getElementById(erroId);
        if (erro) erro.textContent = result.message;

        const alertBox = document.getElementById(alertId);
        if (alertBox) alertBox.style.display = "flex";
      }
    } catch (err) {
      const erro = document.getElementById(erroId);
      if (erro) erro.textContent = "Erro de conexão. Tente novamente mais tarde.";

      const alertBox = document.getElementById(alertId);
      if (alertBox) alertBox.style.display = "flex";
    }
  });
}

/**
 * Inicializa o formulário de recuperação de senha
 * @param {string} formSelector - Seletor do formulário de reset
 * @param {string} cpfSelector - Seletor do input CPF
 * @param {string} emailSelector - Seletor do input email
 * @param {string} endpoint - URL do endpoint de recuperação
 */
async function initRecuperaSenha(formSelector = "#resetForm", cpfSelector = "#cpf", emailSelector = "#email", endpoint = "/envia_mail.php") {
  const form = document.querySelector(formSelector);
  const cpfInput = document.querySelector(cpfSelector);
  const emailInput = document.querySelector(emailSelector);

  if (!form || !cpfInput || !emailInput) return;

  const onlyDigits = (v) => (v || "").toString().replace(/\D+/g, "");

  const getAlertBox = () => document.getElementById("alert-login");
  const getAlertTitle = () => document.querySelector("#alert-login .alert-header .text");
  const getErroSpan = () => document.getElementById("erro");

  const setAlert = (type, title, msg) => {
    const alertBox = getAlertBox();
    const alertT = getAlertTitle();
    const erroSpan = getErroSpan();

    if (!alertBox) return;

    alertBox.classList.remove("danger", "success", "primary");
    if (type) alertBox.classList.add(type);

    if (erroSpan) erroSpan.textContent = msg || "";
    if (alertT) alertT.textContent = title || "";

    alertBox.style.display = "block";
  };

  const clearAlert = () => {
    const alertBox = getAlertBox();
    const erroSpan = getErroSpan();

    if (erroSpan) erroSpan.textContent = "";
    if (alertBox) {
      alertBox.style.display = "none";
      alertBox.classList.remove("danger", "success", "primary");
    }
  };

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
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

    const submitBtn = form.querySelector('button[type="submit"]');
    const oldBtnText = submitBtn ? submitBtn.textContent : null;

    try {
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Processando...";
      }

      const resp = await fetch(endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
        },
        body: JSON.stringify({
          cpf: cpfDigits,
          email: email
        })
      });

      // Mensagem SEMPRE neutra por segurança
      setAlert(
        "primary",
        "Aviso",
        "Se os dados estiverem corretos, você receberá instruções por e-mail."
      );
      form.reset();

    } catch (err) {
      setAlert(
        "danger",
        "Erro",
        "Erro de conexão. Tente novamente mais tarde."
      );
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = oldBtnText || "Solicitar reset";
      }
    }
  });
}

// Expõe funções públicas
window.initLogin = initLogin;
window.initRecuperaSenha = initRecuperaSenha;
