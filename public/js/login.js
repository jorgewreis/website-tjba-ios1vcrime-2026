function initRecuperaSenha({
  formId = "resetForm",
  cpfId = "cpf",
  emailId = "email",
  csrfId = "csrf_token",
  erroId = "erro",
  alertId = "alert-login",
  alertTitle = "#alert-login .alert-header .text",
  endpoint = "/envia_mail.php",
} = {}) {

  const form = document.getElementById(formId);
  const cpfInput = document.getElementById(cpfId);
  const emailInput = document.getElementById(emailId);
  const csrfInput = document.getElementById(csrfId);
  const erroSpan = document.getElementById(erroId);
  const alertBox = document.getElementById(alertId);
  const alertT = document.querySelector(alertTitle);

  if (!form || !cpfInput || !emailInput || !erroSpan || !alertBox) return;

  const onlyDigits = (v) => (v || "").toString().replace(/\D+/g, "");

  const setAlert = (type, title, msg) => {
    alertBox.classList.remove("danger", "success");
    if (type) alertBox.classList.add(type);

    erroSpan.textContent = msg || "";
    if (alertT) alertT.textContent = title || "";

    alertBox.style.display = "block";
  };

  const clearAlert = () => {
    erroSpan.textContent = "";
    alertBox.style.display = "none";
    alertBox.classList.remove("danger", "success");
    if (alertT) alertT.textContent = "";
  };

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearAlert();

    const cpfDigits = onlyDigits(cpfInput.value);
    const email = (emailInput.value || "").trim();
    const csrf = csrfInput ? csrfInput.value : null;

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

      const contentType = resp.headers.get("content-type") || "";
      let data = null;

      if (contentType.includes("application/json")) {
        data = await resp.json().catch(() => null);
      }

      // Mensagem SEMPRE neutra
      if (!resp.ok || !data || !data.success) {
        setAlert(
          "primary",
          "Aviso",
          "Se os dados estiverem corretos, você receberá instruções por e-mail."
        );
        form.reset();
        return;
      }

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
