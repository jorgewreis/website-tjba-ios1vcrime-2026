(function (global) {
  const App = global.App || (global.App = {});

  function somenteDigitos(value) {
    return (value || "").replace(/\D+/g, "");
  }

  function formatar(digitos) {
    return digitos.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, "$1.$2.$3-$4");
  }

  function aplicarMascaraParcial(valor) {
    const digits = somenteDigitos(valor).slice(0, 11);
    const parts = [
      digits.slice(0, 3),
      digits.slice(3, 6),
      digits.slice(6, 9),
      digits.slice(9, 11)
    ];

    let out = parts[0];
    if (parts[1]) out += `.${parts[1]}`;
    if (parts[2]) out += `.${parts[2]}`;
    if (parts[3]) out += `-${parts[3]}`;
    return out;
  }

  function validar(digitos11) {
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

  async function verifica(selector = "#cpf") {
    const input = document.querySelector(selector);
    if (!input) return false;
    const digits = somenteDigitos(input.value);
    if (digits.length !== 11 || !validar(digits)) {
      input.value = "";
      return false;
    }
    input.value = formatar(digits);
    return true;
  }

  async function init(selector = "#cpf") {
    const input = document.querySelector(selector);
    if (!input) return;

    input.addEventListener("input", () => {
      input.value = aplicarMascaraParcial(input.value);
    });

    ["change", "blur"].forEach((event) => {
      input.addEventListener(event, () => verifica(selector));
    });
  }

  const CPF = {
    somenteDigitos,
    formatar,
    aplicarMascaraParcial,
    validar,
    init,
    verifica
  };

  App.CPF = CPF;
})(window);
