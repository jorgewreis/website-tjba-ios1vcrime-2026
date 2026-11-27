(() => {
  document.querySelector('[data-ce-rule-remove]')?.addEventListener('click', event => {
    if (!window.confirm('Remover o responsável da Regra de envio?')) event.preventDefault();
  });
  if (parent === window) return;
  const cancel = document.getElementById('voltar-btn');
  const ruleBack = document.querySelector('[data-ce-rule-back]');
  for (const button of [cancel,ruleBack]) {
    button?.addEventListener('click', event => {
      event.preventDefault();
      event.stopImmediatePropagation();
      parent.postMessage({type:'ce-piloto-close'},location.origin);
    }, true);
  }
  if (document.querySelector('[data-ce-rule-saved]')) parent.postMessage({type:'ce-piloto-saved'},location.origin);
})();
