(() => {
  'use strict';

  const unavailable = 'Indisponível';
  const setText = (id, value) => {
    const node = document.getElementById(id);
    if (node) node.textContent = value === null || value === undefined ? unavailable : String(value);
  };
  const safeCount = value => Number.isSafeInteger(value) && value >= 0 ? value : null;
  const format = value => new Intl.NumberFormat('pt-BR').format(value);

  function addRow(container, label, value) {
    const row = document.createElement('div');
    row.className = 'dashboard2-data-row';
    const name = document.createElement('span');
    name.textContent = label;
    const count = document.createElement('strong');
    count.textContent = value === null || value === undefined ? unavailable : String(value);
    row.append(name, count);
    container.append(row);
  }

  function renderQueues(data) {
    const container = document.getElementById('d2-lotacao');
    if (!container) return;
    container.replaceChildren();
    const queues = data.setor?.conectado === true && Array.isArray(data.setor.filas) ? data.setor.filas : [];
    if (data.setor?.conectado !== true) {
      addRow(container, 'Área não disponível no perfil', null);
      return;
    }
    const visible = queues.filter(queue => ['cartas_expedidas', 'cartas_recebidas', 'guias', 'editais', 'oficios'].includes(queue.key));
    if (!visible.length) addRow(container, 'Filas operacionais retornadas', data.fila_parcial === false ? '0' : null);
    visible.forEach(queue => {
      const count = queue.error ? null : safeCount(queue.count);
      addRow(container, queue.label, count === null ? null : format(count));
    });
  }

  function renderAlerts(alerts) {
    const container = document.getElementById('d2-alerts-list');
    if (!container) return;
    container.replaceChildren();
    if (!Array.isArray(alerts)) {
      addRow(container, 'Alertas cobertos pelo resumo', null);
      return;
    }
    if (alerts.length === 0) {
      addRow(container, 'Alertas de cartas cobertos pelo resumo', '0');
      return;
    }
    alerts.forEach(alert => {
      const copy = `${alert.label || 'Alerta'} ${alert.detail || ''}`.trim();
      const value = safeCount(alert.value);
      const formatted = value === null ? null : alert.unit === 'days' ? `${format(value)} dias` : format(value);
      addRow(container, copy, formatted);
    });
  }

  function renderVolumeChart(data) {
    const canvas = document.getElementById('d2-volume-chart');
    const status = document.getElementById('d2-volume-unavailable');
    const series = data.geral?.cartas_cadastradas?.ultimos_30_dias;
    const labels = series?.labels;
    const sent = series?.expedidas;
    const received = series?.recebidas;
    const totals = data.geral?.cartas_cadastradas;
    const sum = values => values.reduce((total, value) => total + value, 0);
    const validSeries = Array.isArray(labels) && labels.length === 30
      && labels.every(label => typeof label === 'string' && label.length > 0)
      && Array.isArray(sent) && sent.length === labels.length
      && Array.isArray(received) && received.length === labels.length
      && [...sent, ...received].every(value => safeCount(value) !== null)
      && safeCount(totals?.expedidas) !== null && safeCount(totals?.recebidas) !== null
      && safeCount(sum(sent)) !== null && safeCount(sum(received)) !== null
      && sum(sent) === totals.expedidas && sum(received) === totals.recebidas;

    const body = document.getElementById('d2-volume-data-body');
    if (body) body.replaceChildren();
    if (!canvas || !status || !validSeries) return;
    if (body) labels.forEach((label, index) => {
      const row = document.createElement('tr');
      [label, format(sent[index]), format(received[index])].forEach(value => {
        const cell = document.createElement('td');
        cell.textContent = value;
        row.append(cell);
      });
      body.append(row);
    });
    if (typeof Chart === 'undefined') return;
    canvas.hidden = false;
    status.hidden = true;
    new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          { label: 'Cartas expedidas cadastradas', data: sent, borderColor: '#6412e9', backgroundColor: 'rgba(100, 18, 233, .08)', fill: true, tension: .32, pointRadius: 1.5, borderWidth: 2 },
          { label: 'Cartas recebidas cadastradas', data: received, borderColor: '#a77bea', backgroundColor: 'rgba(167, 123, 234, .08)', fill: true, tension: .32, pointRadius: 1.5, borderWidth: 2 },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: true, labels: { boxWidth: 9, boxHeight: 9, usePointStyle: true, font: { size: 11 } } } },
        scales: {
          x: { grid: { display: false }, ticks: { maxTicksLimit: 8, maxRotation: 0, font: { size: 11 } } },
          y: { beginAtZero: true, ticks: { precision: 0, maxTicksLimit: 4, font: { size: 11 } } },
        },
      },
    });
  }

  async function load() {
    const error = document.getElementById('dashboard2-error');
    try {
      const response = await fetch('/api/dashboard/resumo-v2.php', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'Não foi possível carregar os indicadores.');

      const letters = data.geral?.cartas_cadastradas;
      const letterSeries = letters?.ultimos_30_dias;
      const reconciles = (key, total) => Array.isArray(letterSeries?.[key])
        && letterSeries[key].length === 30
        && letterSeries[key].every(value => safeCount(value) !== null)
        && safeCount(total) !== null
        && safeCount(letterSeries[key].reduce((sum, value) => sum + value, 0)) !== null
        && letterSeries[key].reduce((sum, value) => sum + value, 0) === total;
      setText('d2-letters-sent', reconciles('expedidas', letters?.expedidas) ? format(letters.expedidas) : null);
      setText('d2-letters-received', reconciles('recebidas', letters?.recebidas) ? format(letters.recebidas) : null);

      const offices = data.geral?.oficios_cadastrados?.ano;
      const cartorio = safeCount(offices?.cartorio);
      const gabinete = safeCount(offices?.gabinete);
      setText('d2-offices', cartorio === null || gabinete === null ? null : format(cartorio + gabinete));

      const totalQueues = safeCount(data.geral?.tarefas_abertas);
      setText('d2-queues', data.fila_parcial === false && totalQueues !== null ? format(totalQueues) : null);
      // The legacy contract covers D0..D+7 and uses CURDATE; the approved card needs D0..D+6 in America/Bahia.
      setText('d2-agenda', null);
      renderVolumeChart(data);
      renderQueues(data);
      renderAlerts(data.alertas);
    } catch (cause) {
      if (error) {
        error.textContent = cause?.message || 'Não foi possível carregar os indicadores.';
        error.hidden = false;
      }
      ['d2-letters-sent', 'd2-letters-received', 'd2-offices', 'd2-queues', 'd2-agenda'].forEach(id => setText(id, null));
      ['d2-lotacao', 'd2-alerts-list'].forEach(id => {
        const container = document.getElementById(id);
        if (container) { container.replaceChildren(); addRow(container, 'Dados', null); }
      });
      const canvas = document.getElementById('d2-volume-chart');
      const status = document.getElementById('d2-volume-unavailable');
      if (canvas) canvas.hidden = true;
      if (status) { status.textContent = 'Série indisponível: falha ao carregar o resumo.'; status.hidden = false; }
      const seriesBody = document.getElementById('d2-volume-data-body');
      if (seriesBody) seriesBody.replaceChildren();
    }
  }

  document.addEventListener('DOMContentLoaded', load);
})();
