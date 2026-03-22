/**
 * TextFormatter — único ponto de formatação de nomes de pessoas e cidades no front-end.
 * Sincronizar alterações com app/helpers/TextFormatter.php (formatProperName).
 *
 * Artigos/preposições (de, da, do, …) permanecem em minúsculas em qualquer posição,
 * para que sobrenomes como "da Silva" não virem "Da Silva" quando o campo é só o sobrenome.
 */
(function (global) {
  /** Mesma lista que TextFormatter::DEFAULT_PROPER_NAME_EXCEPTIONS no PHP. */
  var DEFAULT_EXCEPTIONS = ["de", "da", "do", "das", "dos", "e", "em", "no", "na"];

  function onlyLetters(value) {
    return (value || "").replace(/[^A-Za-zÀ-ú\s]+/g, " ").trim();
  }

  function capitalizeToken(token, exceptions) {
    var lower = token.toLowerCase();
    if (exceptions.indexOf(lower) !== -1) {
      return lower;
    }
    return lower.charAt(0).toUpperCase() + lower.slice(1);
  }

  function formatProperName(value, maxLength, exceptions) {
    if (maxLength == null) maxLength = 255;
    if (!exceptions) exceptions = DEFAULT_EXCEPTIONS;
    if (!value) return "";
    var clean = onlyLetters(value).split(/\s+/).filter(Boolean);
    var transformed = clean.map(function (token) {
      return capitalizeToken(token, exceptions);
    });
    var formatted = transformed.join(" ");
    if (formatted.length > maxLength) {
      formatted = formatted.slice(0, maxLength).trim();
    }
    return formatted;
  }

  function ensureGlobal() {
    if (typeof global.TextFormatter === "undefined") {
      global.TextFormatter = {};
    }
    return global.TextFormatter;
  }

  var exported = ensureGlobal();
  exported.EXCEPTIONS = DEFAULT_EXCEPTIONS.slice();
  exported.formatProperName = formatProperName;
})(
  typeof window !== "undefined" ? window : global
);
