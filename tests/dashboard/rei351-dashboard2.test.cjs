const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../..');
const view = fs.readFileSync(path.join(root, 'app/views/internal/dashboard-2.php'), 'utf8');
const frame = fs.readFileSync(path.join(root, 'public/frame.php'), 'utf8');
const api = fs.readFileSync(path.join(root, 'public/api/dashboard/resumo-v2.php'), 'utf8');
const access = fs.readFileSync(path.join(root, 'app/access/PageAccessRegistry.php'), 'utf8');
const oldView = fs.readFileSync(path.join(root, 'app/views/internal/dashboard.php'), 'utf8');
const home = fs.readFileSync(path.join(root, 'public/home.php'), 'utf8');

assert.match(frame, /'dashboard'\s+=>\s+\$PAGES\s*\.\s*'\/dashboard\.php'/);
assert.match(frame, /'dashboard2'\s+=>\s+\$PAGES\s*\.\s*'\/dashboard-2\.php'/);
assert.match(frame, /DASHBOARD2_ENABLED',\s*'0'/);
assert.match(api, /AccessEvaluator::canAccessPage\('dashboard2'/);
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
  const getElementById = id => {
    if (!elements.has(id)) elements.set(id, new Element(id));
    return elements.get(id);
  };
  let onReady;
  const document = { getElementById, createElement: () => new Element(), addEventListener: (_name, callback) => { onReady = callback; } };
  const sandbox = {
    document,
    Intl,
    fetch: async () => ({ ok: responseOk, json: async () => payload }),
    console,
  };
  vm.runInNewContext(fs.readFileSync(path.join(root, 'public/assets/js/dashboard-2.js'), 'utf8'), sandbox);
  await onReady();
  return elements;
}

(async () => {
  const data = await executeDashboard({
    geral: {
      cartas_cadastradas: { expedidas: 3, recebidas: 5, ultimos_30_dias: { expedidas: [1, 2], recebidas: [2, 3] } },
      oficios_cadastrados: { ano: { cartorio: 2, gabinete: 4 } },
      tarefas_abertas: 8,
    },
    setor: { conectado: true, filas: [{ key: 'cartas_expedidas', label: 'Envio de carta', count: 0 }] },
    agenda: { audiencias_7d: 0 },
    alertas: [],
  });
  assert.equal(data.get('d2-letters').textContent, '8');
  assert.equal(data.get('d2-offices').textContent, '6');
  assert.equal(data.get('d2-queues').textContent, '8');
  assert.equal(data.get('d2-agenda').textContent, 'Indisponível');
  assert.equal(data.get('d2-lotacao').children[0].children[1].textContent, '0');

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
  for (const id of ['d2-letters', 'd2-offices', 'd2-queues', 'd2-agenda']) assert.equal(unavailable.get(id).textContent, 'Indisponível');
  assert.equal(unavailable.get('d2-lotacao').children[0].children[1].textContent, 'Indisponível');

  const failed = await executeDashboard({ error: 'falha sintética' }, false);
  assert.equal(failed.get('dashboard2-error').hidden, false);
  assert.equal(failed.get('d2-letters').textContent, 'Indisponível');
  process.stdout.write('rei351-dashboard2: PASS\n');
})().catch(error => { console.error(error); process.exitCode = 1; });
