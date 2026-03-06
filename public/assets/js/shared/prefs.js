/**
 * Preferências em cookies: lembrar usuário (CPF) e tema do sistema (claro, escuro, auto).
 */
(function (global) {
  const App = global.App || (global.App = {});

  const COOKIE_PREFIX = "tjba_1vcrime_";
  const COOKIE_CPF = COOKIE_PREFIX + "remember_cpf";
  const COOKIE_THEME = COOKIE_PREFIX + "theme";
  const COOKIE_MAX_AGE_DAYS = 365;

  function getCookie(name) {
    const parts = document.cookie.split(";").map((s) => s.trim());
    for (const part of parts) {
      const [key, ...v] = part.split("=");
      if (key === name) return decodeURIComponent((v.join("=") || "").replace(/^"(.*)"$/, "$1"));
    }
    return null;
  }

  function setCookie(name, value, maxAgeDays) {
    const maxAge = (maxAgeDays ?? COOKIE_MAX_AGE_DAYS) * 24 * 60 * 60;
    const secure = window.location.protocol === "https:" ? "; Secure" : "";
    document.cookie =
      name + "=" + encodeURIComponent(value) + "; path=/; max-age=" + maxAge + "; SameSite=Lax" + secure;
  }

  function deleteCookie(name) {
    document.cookie = name + "=; path=/; max-age=0";
  }

  function getRememberCpf() {
    return getCookie(COOKIE_CPF);
  }

  function setRememberCpf(cpf) {
    if (cpf && cpf.replace(/\D/g, "").length === 11) {
      setCookie(COOKIE_CPF, cpf);
    } else {
      deleteCookie(COOKIE_CPF);
    }
  }

  function clearRememberCpf() {
    deleteCookie(COOKIE_CPF);
  }

  function getTheme() {
    const t = getCookie(COOKIE_THEME);
    return t === "claro" || t === "escuro" || t === "auto" ? t : "auto";
  }

  function setTheme(theme) {
    if (theme === "claro" || theme === "escuro" || theme === "auto") {
      setCookie(COOKIE_THEME, theme);
    }
  }

  function applyTheme() {
    const theme = getTheme();
    const root = document.documentElement;
    if (theme === "auto") {
      root.removeAttribute("data-theme");
      root.setAttribute("data-theme", "auto");
    } else {
      root.setAttribute("data-theme", theme);
    }
  }

  function applyThemeFromCookie() {
    applyTheme();
  }

  App.Prefs = {
    getCookie,
    setCookie,
    deleteCookie,
    getRememberCpf,
    setRememberCpf,
    clearRememberCpf,
    getTheme,
    setTheme,
    applyTheme,
    applyThemeFromCookie
  };
})(window);
