(() => {
  'use strict';

  const unavailable = 'Indisponível';
  const setText = (id, value) => {
    const node = document.getElementById(id);
    if (node) node.textContent = value === null || value === undefined ? unavailable : String(value);
  };
  const format = value => new Intl.NumberFormat('pt-BR').format(value);

  function addRow(container, label, value) {
    const row = document.createElement('div');
    row.className = 'dashboard2-data-row';
    const name = document.createElement('span');
    name.textContent = label;
    const count = document.createElement('strong');
    count.textContent = value === null || value === undefined ? unavailable : format(value);
    row.append(name, count);
    container.append(row);
  }

  function renderQueues(data) {
    const container = document.getElementById('d2-lotacao');
    if (!container) return;
    container.replaceChildren();
    const queues = data.setor?.conectado ? data.setor.filas : [];
    if (!data.setor?.conectado) {
      addRow(container, 'Área não disponível no perfil', null);
      return;
    }
    const visible = queues.filter(queue => ['cartas_expedidas', 'cartas_recebidas', 'guias', 'editais', 'oficios'].includes(queue.key));
    if (!visible.length) addRow(container, 'Filas operacionais', data.fila_parcial ? null : 0);
    visible.forEach(queue => addRow(container, queue.label, queue.error ? null : queue.count));
  }

  function renderAlerts(alerts) {
    const container = document.getElementById('d2-alerts-list');
    if (!container) return;
    container.replaceChildren();
    if (!Array.isArray(alerts)) {
      addRow(container, 'Alertas', null);
      return;
    }
    if (alerts.length === 0) {
      addRow(container, 'Alertas de cartas cobertas pelo resumo', 0);
      return;
    }
    alerts.forEach(alert => {
      const copy = `${alert.label || 'Alerta'} ${alert.detail || ''}`.trim();
      addRow(container, copy, alert.unit === 'days' ? `${format(alert.value)} dias` : alert.value);
    });
  }

  async function load() {
    const error = document.getElementById('dashboard2-error');
    try {
      const response = await fetch('/api/dashboard/resumo-v2.php', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'Não foi possível carregar os indicadores.');

      const letters = data.geral?.cartas_cadastradas?.ultimos_30_dias;
      const letterCount = data.geral?.cartas_cadastradas?.expedidas !== null
        && data.geral?.cartas_cadastradas?.recebidas !== null
        && Array.isArray(letters?.expedidas) && Array.isArray(letters?.recebidas)
        ? letters.expedidas.reduce((sum, count) => sum + count, 0) + letters.recebidas.reduce((sum, count) => sum + count, 0)
        : null;
      setText('d2-letters', letterCount === null ? null : format(letterCount));

      const offices = data.geral?.oficios_cadastrados?.ano;
      setText('d2-offices', offices && Number.isInteger(offices.cartorio) && Number.isInteger(offices.gabinete)
        ? format(offices.cartorio + offices.gabinete) : null);

      setText('d2-queues', data.geral?.tarefas_abertas === null || data.geral?.tarefas_abertas === undefined
        ? null : format(data.geral.tarefas_abertas));
      setText('d2-agenda', data.agenda?.audiencias_7d === null || data.agenda?.audiencias_7d === undefined
        ? null : format(data.agenda.audiencias_7d));
      renderQueues(data);
      renderAlerts(data.alertas);
    } catch (cause) {
      if (error) {
        error.textContent = cause?.message || 'Não foi possível carregar os indicadores.';
        error.hidden = false;
      }
      ['d2-letters', 'd2-offices', 'd2-queues', 'd2-agenda'].forEach(id => setText(id, null));
      ['d2-lotacao', 'd2-alerts-list'].forEach(id => {
        const container = document.getElementById(id);
        if (container) { container.replaceChildren(); addRow(container, 'Dados', null); }
      });
    }
  }

  document.addEventListener('DOMContentLoaded', load);
})();
