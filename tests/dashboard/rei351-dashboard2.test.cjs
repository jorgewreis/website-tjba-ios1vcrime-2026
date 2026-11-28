const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../..');
const view = fs.readFileSync(path.join(root, 'app/views/internal/dashboard-2.php'), 'utf8');
const frame = fs.readFileSync(path.join(root, 'public/frame.php'), 'utf8');
const api = fs.readFileSync(path.join(root, 'public/api/dashboard/resumo-v2.php'), 'utf8');
const access = fs.readFileSync(path.join(root, 'app/access/PageAccessRegistry.php'), 'utf8');
const summaryService = fs.readFileSync(path.join(root, 'app/services/DashboardResumoService.php'), 'utf8');
const oldView = fs.readFileSync(path.join(root, 'app/views/internal/dashboard.php'), 'utf8');
const home = fs.readFileSync(path.join(root, 'public/home.php'), 'utf8');

assert.match(frame, /'dashboard'\s+=>\s+\$PAGES\s*\.\s*'\/dashboard\.php'/);
assert.match(frame, /'dashboard2'\s+=>\s+\$PAGES\s*\.\s*'\/dashboard-2\.php'/);
assert.match(frame, /DASHBOARD2_ENABLED',\s*'0'/);
assert.match(api, /AccessEvaluator::canAccessPage\('dashboard2'/);
assert.match(api, /new DashboardResumoService\(Conection::getInstance\(\)->getConnection\(\),\s*true\)/, 'v2 usa métrica estrita; dashboard atual conserva o padrão');
assert.match(summaryService, /private bool \$strictDashboard2Metrics/);
assert.match(summaryService, /if \(\$this->strictDashboard2Metrics\) \{\s*\$lettersRegistered\[\$kind\] = null;\s*\$last30DaysLetters\[\$kind\] = array_fill\(0, 30, null\);/);
assert.match(access, /'dashboard2'\s*=>\s*\['kind'\s*=>\s*'all_internal'\]/);
assert.match(home, /DASHBOARD2_ENABLED',\s*'0'/);
assert.match(home, /AccessEvaluator::canAccessPage\('dashboard2',\s*\$userPerfisEfectivos\)/);
assert.match(home, /appendDashboard2TestLink\(\);[\s\S]*appendLogoutItem\(\);/);
assert.match(home, /link\.dataset\.target\s*=\s*'dashboard2'/);
assert.match(home, /Dashboard 2 \(teste\)/);
assert.match(view, /Visão geral[\s\S]*Meu trabalho[\s\S]*Fluxo operacional/);
assert.match(view, /Lotação[\s\S]*Criticidade[\s\S]*Radar de prazos[\s\S]*Qualidade operacional[\s\S]*Prontidão das audiências/);
assert.match(view, /Agenda[\s\S]*Próxima ação[\s\S]*Evolução de produtividade[\s\S]*Alertas críticos/);
assert.match(oldView, /dashboard-interno\.js/);

class Element {
  constructor(id = '') { this.id = id; this.textContent = ''; this.hidden = false; this.children = []; }
  append(...children) { this.children.push(...children); }
  replaceChildren(...children) { this.children = children; }
}

async function executeDashboard(payload, responseOk = true) {
  const elements = new Map();
  const charts = [];
  const getElementById = id => {
    if (!elements.has(id)) {
      const element = new Element(id);
      if (id === 'd2-volume-chart') element.hidden = true;
      elements.set(id, element);
    }
    return elements.get(id);
  };
  let onReady;
  const document = { getElementById, createElement: () => new Element(), addEventListener: (_name, callback) => { onReady = callback; } };
  const sandbox = {
    document,
    Intl,
    Chart: function Chart(canvas, config) { canvas.hidden = false; charts.push(config); },
    fetch: async () => ({ ok: responseOk, json: async () => payload }),
    console,
  };
  vm.runInNewContext(fs.readFileSync(path.join(root, 'public/assets/js/dashboard-2.js'), 'utf8'), sandbox);
  await onReady();
  elements.charts = charts;
  return elements;
}

(async () => {
  const data = await executeDashboard({
    geral: {
      cartas_cadastradas: {
        expedidas: 3,
        recebidas: 5,
        ultimos_30_dias: {
          labels: Array.from({ length: 30 }, (_, index) => `D${index + 1}`),
          expedidas: [3, ...Array(29).fill(0)],
          recebidas: [5, ...Array(29).fill(0)],
        },
      },
      oficios_cadastrados: { ano: { cartorio: 2, gabinete: 4 } },
      tarefas_abertas: 8,
    },
    setor: { conectado: true, filas: [
      { key: 'cartas_expedidas', label: 'Envio de carta', count: 0 },
      { key: 'guias', label: 'Guia', error: true, count: null },
    ] },
    agenda: { audiencias_7d: 0 },
    alertas: [],
    fila_parcial: false,
  });
  assert.equal(data.get('d2-letters-sent').textContent, '3');
  assert.equal(data.get('d2-letters-received').textContent, '5');
  assert.equal(data.get('d2-offices').textContent, '6');
  assert.equal(data.get('d2-queues').textContent, '8');
  assert.equal(data.get('d2-agenda').textContent, 'Indisponível');
  assert.equal(data.get('d2-lotacao').children[0].children[1].textContent, '0');
  assert.equal(data.get('d2-lotacao').children[1].children[1].textContent, 'Indisponível');
  assert.equal(data.get('d2-volume-chart').hidden, false);
  assert.equal(data.get('d2-volume-data-body').children.length, 30);
  assert.equal(data.charts.length, 1);

  const unavailable = await executeDashboard({
    geral: {
      cartas_cadastradas: { expedidas: null, recebidas: null, ultimos_30_dias: { expedidas: Array(30).fill(0), recebidas: Array(30).fill(0) } },
      oficios_cadastrados: { ano: { cartorio: null, gabinete: null } },
      tarefas_abertas: null,
    },
    setor: { conectado: true, filas: [{ key: 'cartas_expedidas', label: 'Envio de carta', error: true, count: null }] },
    agenda: { audiencias_7d: null },
    alertas: null,
  });
  for (const id of ['d2-letters-sent', 'd2-letters-received', 'd2-offices', 'd2-queues', 'd2-agenda']) assert.equal(unavailable.get(id).textContent, 'Indisponível');
  assert.equal(unavailable.get('d2-lotacao').children[0].children[1].textContent, 'Indisponível');
  assert.equal(unavailable.get('d2-volume-chart').hidden, true, 'séries de zeros sem total confirmado não desenham');
  assert.equal(unavailable.get('d2-volume-data-body').children.length, 0);

  const mismatched = await executeDashboard({
    geral: { cartas_cadastradas: { expedidas: 4, recebidas: 0, ultimos_30_dias: {
      labels: Array.from({ length: 30 }, (_, index) => `D${index + 1}`),
      expedidas: [3, ...Array(29).fill(0)], recebidas: Array(30).fill(0),
    } } },
  });
  assert.equal(mismatched.get('d2-letters-sent').textContent, 'Indisponível', 'total divergente não é exibido');
  assert.equal(mismatched.get('d2-volume-chart').hidden, true, 'total divergente não desenha gráfico');

  const partialQueues = await executeDashboard({ geral: { tarefas_abertas: 8 }, fila_parcial: true, setor: { conectado: true, filas: [] }, alertas: [] });
  assert.equal(partialQueues.get('d2-queues').textContent, 'Indisponível');

  const failed = await executeDashboard({ error: 'falha sintética' }, false);
  assert.equal(failed.get('dashboard2-error').hidden, false);
  assert.equal(failed.get('d2-letters-sent').textContent, 'Indisponível');
  process.stdout.write('rei351-dashboard2: PASS\n');
})().catch(error => { console.error(error); process.exitCode = 1; });
