function cpfSomenteDigitos(v) {
  return (v || "").replace(/\D+/g, "");
}

function cpfFormatar(digitos) {
  // "12345678901" -> "123.456.789-01"
  return digitos.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, "$1.$2.$3-$4");
}

// Máscara "viva" (enquanto digita) sem travar o usuário
function cpfAplicarMascaraParcial(valor) {
  const d = cpfSomenteDigitos(valor).slice(0, 11);

  // monta parcial: 123.456.789-01 (conforme vai tendo dígitos)
  const p1 = d.slice(0, 3);
  const p2 = d.slice(3, 6);
  const p3 = d.slice(6, 9);
  const p4 = d.slice(9, 11);

  let out = p1;
  if (p2) out += "." + p2;
  if (p3) out += "." + p3;
  if (p4) out += "-" + p4;

  return out;
}

function cpfValido(digitos11) {
  if (!/^\d{11}$/.test(digitos11)) return false;
  if (/^(\d)\1{10}$/.test(digitos11)) return false;

  const calcDV = (base) => {
    let soma = 0;
    for (let i = 0; i < base.length; i++) {
      soma += Number(base[i]) * (base.length + 1 - i);
    }
    const resto = soma % 11;
    return resto < 2 ? 0 : 11 - resto;
  };

  const dv1 = calcDV(digitos11.slice(0, 9));
  const dv2 = calcDV(digitos11.slice(0, 9) + dv1);

  return dv1 === Number(digitos11[9]) && dv2 === Number(digitos11[10]);
}

// Valida no "change/blur" e, se válido, deixa COM máscara no input
async function verificaCPF(selector = "#cpf") {
  const input = document.querySelector(selector);
  if (!input) return false;

  const digitos = cpfSomenteDigitos(input.value);

  if (!digitos || digitos.length !== 11 || !cpfValido(digitos)) {
    input.value = "";
    return false;
  }

  input.value = cpfFormatar(digitos); // armazena com máscara no input
  return true;
}

// Inicializa eventos do CPF
async function initCPF(selector = "#cpf") {
  const input = document.querySelector(selector);
  if (!input) return;

  // Máscara enquanto digita
  input.addEventListener("input", function () {
    const masked = cpfAplicarMascaraParcial(this.value);
    this.value = masked;
  });

  // Validação ao sair/trocar
  input.addEventListener("change", async () => {
    await verificaCPF(selector);
  });

  input.addEventListener("blur", async () => {
    await verificaCPF(selector);
  });
}

// Inicializa submit do login
async function initLogin(formSelector = "#loginForm", endpoint = "login.php", erroId = "erro", alertId = "alert-login") {
  const form = document.querySelector(formSelector);
  if (!form) return;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const formData = new FormData(form);

    const response = await fetch(endpoint, {
      method: "POST",
      body: formData,
    });

    const result = await response.json();

    if (result.success) {
      window.location.href = endpoint;
    } else {
      const erro = document.getElementById(erroId);
      if (erro) erro.textContent = result.message;

      const alertBox = document.getElementById(alertId);
      if (alertBox) alertBox.style.display = "flex";
    }
  });
}

// expõe só o que o index.php precisa chamar
window.initCPF = initCPF;
window.initLogin = initLogin;
window.verificaCPF = verificaCPF; // se você quiser chamar manualmente também
