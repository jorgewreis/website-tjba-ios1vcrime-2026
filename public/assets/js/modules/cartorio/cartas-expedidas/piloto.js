(() => {
  'use strict';
  const panel = document.getElementById('ce-panel');
  const content = document.getElementById('ce-panel-content');
  const close = panel.querySelector('[data-ce-close]');
  const notices = document.getElementById('ce-feedback');
  const title = document.getElementById('ce-panel-title');
  let trigger, timer, frame, dirty = false, busy = false, success = false;

  function notify(message) {
    notices.textContent = message;
    notices.hidden = false;
    clearTimeout(timer);
    timer = setTimeout(() => { notices.hidden = true; }, 4200);
  }
  function exit() {
    if (dirty && !success && !window.confirm('Descartar as alterações não salvas?')) return;
    panel.hidden = true;
    content.replaceChildren();
    frame = null;
    dirty = success = false;
    if (trigger?.isConnected) trigger.focus();
  }
  function show(label, source, node) {
    if (dirty && !success && !window.confirm('Descartar as alterações não salvas?')) return;
    const menu = source.closest('.ce-dropdown');
    if (!panel.contains(source)) trigger = menu?.querySelector('summary') || source;
    if (menu) menu.open = false;
    title.textContent = label;
    content.replaceChildren(node);
    panel.hidden = false;
    frame = node instanceof HTMLIFrameElement ? node : null;
    dirty = success = false;
    close.focus();
  }
  function finishCreate() {
    success = true;
    dirty = false;
    exit();
    const address = new URL(location.href);
    address.searchParams.set('piloto_ok', 'cadastro');
    location.replace(address);
  }
  function showFrame(link) {
    const iframe = document.createElement('iframe');
    iframe.title = link.dataset.title;
    iframe.src = link.href;
    iframe.addEventListener('load', () => {
      if (iframe !== frame) return;
      let url;
      try { url = new URL(iframe.contentWindow.location.href); } catch (_) { return; }
      if (url.searchParams.get('page') === 'cartasexpedidasnova' && url.searchParams.get('piloto_ok') === '1') {
        // Fallback for the older form assets while an updated deploy propagates.
        if (new URL(iframe.src).searchParams.get('page') === 'cartasexpedidascreate') finishCreate();
        else { success = true; dirty = false; window.location.reload(); }
        return;
      }
      const formDoc = iframe.contentDocument;
      if (!formDoc) return;
      const mark = event => { if (event.target.matches('input:not([type=hidden]), select, textarea')) dirty = true; };
      formDoc.addEventListener('input', mark);
      formDoc.addEventListener('change', mark);
      formDoc.addEventListener('keydown', event => { if (event.key === 'Escape' && !event.defaultPrevented) exit(); });
    });
    show(link.dataset.title, link, iframe);
  }
  function switchTab(button) {
    for (const tab of content.querySelectorAll('[data-ce-tab]')) {
      const active = tab === button;
      tab.setAttribute('aria-selected', String(active));
      tab.tabIndex = active ? 0 : -1;
      const pane = document.getElementById(tab.getAttribute('aria-controls'));
      if (pane) pane.hidden = !active;
    }
  }
  const actions = {
    continuar: ['/api/cartorio-cartas-expedidas/concluir-subtarefa.php', id => ({subtarefa_id:id,dados_especificos:null})],
    reiniciar: ['/api/cartorio-cartas/reiniciar.php', id => ({carta_id:id,modulo:'expedidas'}), 'Reiniciar esta carta e voltar para Aguardando envio?'],
    cancelar: ['/api/cartorio-cartas-expedidas/cancel.php', id => ({id}), 'Cancelar esta carta? O registro não será excluído.'],
    excluir: ['/api/cartorio-cartas-expedidas/delete.php', id => ({id}), 'Excluir definitivamente esta carta e suas subtarefas?']
  };
  async function act(button) {
    const action = actions[button.dataset.ceAction];
    if (!action || busy || !button.dataset.id) return;
    const [url, data, confirmText] = action;
    if (confirmText && !window.confirm(confirmText)) return;
    busy = button.disabled = true;
    try {
      const response = await fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(data(button.dataset.id))});
      const result = await response.json();
      if (!response.ok || !(result.success === true || result.deleted === true)) throw new Error(result.error || 'Não foi possível concluir a ação.');
      const address = new URL(location.href);
      address.searchParams.set('piloto_ok','1');
      location.replace(address);
    } catch (error) {
      notify(error.message || 'Não foi possível concluir a ação.');
      busy = button.disabled = false;
    }
  }
  document.addEventListener('click', async event => {
    const target = event.target.closest('a,button,summary');
    document.querySelectorAll('.ce-dropdown[open]').forEach(menu => { if (!menu.contains(event.target)) menu.open = false; });
    if (!target) return;
    if (target.matches('[data-ce-frame]')) { event.preventDefault(); showFrame(target); }
    else if (target.matches('[data-ce-close]')) exit();
    else if (target.matches('[data-ce-print]')) window.print();
    else if (target.matches('[data-ce-details]')) {
      const template = document.getElementById(target.dataset.ceDetails);
      if (template instanceof HTMLTemplateElement) show('Detalhes da carta',target,template.content.cloneNode(true));
    } else if (target.matches('[data-ce-tab]')) switchTab(target);
    else if (target.matches('[data-ce-action]')) await act(target);
    else if (target.matches('[data-ce-copy]')) {
      try {
        await navigator.clipboard.writeText(target.dataset.ceCopy);
        if (target.closest('.ce-dropdown')) { target.closest('.ce-dropdown').open = false; target.closest('.ce-dropdown').querySelector('summary').focus(); }
        notify(target.classList.contains('ce-cnj') ? 'Número copiado.' : 'Texto copiado.');
      } catch (_) { notify('Não foi possível copiar.'); }
    }
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      const menu = event.target.closest('.ce-dropdown[open]');
      if (menu) {
        event.preventDefault();
        menu.open = false;
        menu.querySelector('summary').focus();
        return;
      }
      if (!panel.hidden) { event.preventDefault(); exit(); return; }
    }
    if (!event.target.matches('[data-ce-tab]') || !['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
    const tabs = [...content.querySelectorAll('[data-ce-tab]')];
    const i = tabs.indexOf(event.target);
    const next = tabs[event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length-1 : (i+(event.key==='ArrowRight'?1:-1)+tabs.length)%tabs.length];
    event.preventDefault();
    switchTab(next);
    next.focus();
  });
  window.addEventListener('message', event => {
    if (!frame || event.origin !== location.origin || event.source !== frame.contentWindow) return;
    if (event.data?.type === 'ce-piloto-close') exit();
    if (event.data?.type === 'ce-piloto-created' && new URL(frame.src).searchParams.get('page') === 'cartasexpedidascreate') finishCreate();
    if (event.data?.type === 'ce-piloto-saved') { success = true; dirty = false; notify('Alterações salvas.'); window.location.reload(); }
  });
  const tempos = [...document.querySelectorAll('[data-ce-stage-seconds]')];
  if (tempos.length) {
    // A idade inicial vem do servidor; o relógio do dispositivo não altera a base.
    const inicioLocal = performance.now();
    window.setInterval(() => {
      const decorrido = Math.floor((performance.now() - inicioLocal) / 1000);
      for (const tempo of tempos) {
        const minutos = Math.floor((Number(tempo.dataset.ceStageSeconds) + decorrido) / 60);
        const horas = Math.floor(minutos / 60);
        const dias = Math.floor(horas / 24);
        const meses = Math.floor(dias / 30);
        const resumo = meses > 0 ? meses + (meses === 1 ? 'mês' : 'meses')
          : dias > 0 ? dias + 'd' : horas > 0 ? horas + 'h' : minutos > 0 ? minutos + 'min' : '<1min';
        if (tempo.textContent !== resumo) tempo.textContent = resumo;
      }
    }, 15000);
  }
  const collapsedForPrint = new Set();
  window.addEventListener('beforeprint', () => {
    document.querySelectorAll('.ce-group:not([open])').forEach(group => { collapsedForPrint.add(group); group.open = true; });
  });
  window.addEventListener('afterprint', () => {
    collapsedForPrint.forEach(group => { group.open = false; });
    collapsedForPrint.clear();
  });
  const url = new URL(location.href);
  if (url.searchParams.has('piloto_ok')) {
    notify(url.searchParams.get('piloto_ok') === 'cadastro' ? 'Carta cadastrada.' : 'Operação concluída.');
    url.searchParams.delete('piloto_ok');
    history.replaceState(null,'',url);
  }
})();
