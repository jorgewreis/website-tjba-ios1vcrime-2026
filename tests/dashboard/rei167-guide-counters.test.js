'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const view = fs.readFileSync(path.join(root, 'app/views/internal/dashboard.php'), 'utf8');
const source = fs.readFileSync(path.join(root, 'public/assets/js/dashboard-interno.js'), 'utf8');
const runnerPayloadFile = process.env.REI167_PAYLOAD_FILE;
const runnerScenarios = ['expedicao', 'envio', 'zero', 'falha', 'gabinete'];
function readRunnerPayloads() {
  if (!runnerPayloadFile) return null;
  const prefix = 'REI167_PAYLOAD ';
  const entries = fs.readFileSync(runnerPayloadFile, 'utf8').split(/\r?\n/)
    .filter(line => line.startsWith(prefix))
    .map(line => JSON.parse(line.slice(prefix.length)));
  assert.equal(entries.length, runnerScenarios.length, 'runner output must contain exactly five REI167_PAYLOAD lines');
  assert.deepEqual(entries.map(entry => entry.scenario).sort(), [...runnerScenarios].sort(), 'runner output must contain all five unique scenarios');
  for (const entry of entries) assert.ok(entry.payload?.setor && entry.payload?.geral, `missing API payload for ${entry.scenario}`);
  return entries;
}
for (const id of ['dash-queue-guias-expedicao-row', 'dash-queue-guias-envio-row', 'dash-queue-guias-expedicao', 'dash-queue-guias-envio']) {
  assert.match(view, new RegExp(`id="${id}"`), `dashboard view must contain ${id}`);
}
assert.match(view, /<b>Guias<\/b><br>aguardando expedição/);
assert.match(view, /<b>Guias<\/b><br>aguardando envio/);

class Element {
  constructor(id = '') {
    this.id = id; this.hidden = false; this.textContent = ''; this.dataset = {}; this.children = [];
    this.className = ''; this.classes = new Set();
    this.classList = {
      add: (...values) => values.forEach(value => this.classes.add(value)),
      remove: (...values) => values.forEach(value => this.classes.delete(value)),
      contains: value => this.classes.has(value)
    };
  }
  append(...elements) { this.children.push(...elements); }
  replaceChildren(...elements) { this.textContent = ''; this.children = [...elements]; }
  querySelectorAll(selector) { return selector === ':scope > .dash-sector-metric' ? this.children.filter(e => e.className.includes('dash-sector-metric')) : []; }
  querySelector(selector) { return selector === 'strong' ? this.strong || null : null; }
}

const ids = [...view.matchAll(/id="([^"]+)"/g)].map(match => match[1]);
const elements = new Map(ids.map(id => [id, new Element(id)]));
for (const id of ['dash-queue-expedidas-row','dash-queue-recebidas-row','dash-queue-oficios-row','dash-queue-editais-row','dash-overdue-expedidas-row','dash-overdue-recebidas-row','dash-queue-guias-expedicao-row','dash-queue-guias-envio-row']) {
  const row = elements.get(id); if (row) { row.className = 'dash-sector-metric'; row.strong = elements.get(id.replace(/-row$/, '').replace('row','')); }
}
for (const [rowId, strongId] of [
  ['dash-queue-expedidas-row','dash-queue-expedidas'], ['dash-queue-recebidas-row','dash-queue-recebidas'],
  ['dash-queue-oficios-row','dash-queue-oficios'], ['dash-queue-editais-row','dash-queue-editais'],
  ['dash-overdue-expedidas-row','dash-overdue-expedidas'], ['dash-overdue-recebidas-row','dash-overdue-recebidas'],
  ['dash-queue-guias-expedicao-row','dash-queue-guias-expedicao'], ['dash-queue-guias-envio-row','dash-queue-guias-envio']
]) if (elements.has(rowId)) elements.get(rowId).strong = elements.get(strongId);

let responseQueue = [];
const document = {
  getElementById: id => elements.get(id) || null,
  createElement: tag => { const el = new Element(); el.tagName = tag.toUpperCase(); return el; },
  addEventListener: (event, callback) => { if (event === 'DOMContentLoaded') document.ready = callback; }
};
const window = {};
const sandbox = { window, document, fetch: async () => responseQueue.shift(), console, Number, Date, Math, Promise };
vm.runInNewContext(source, sandbox, { filename: 'dashboard-interno.js' });
const permitted = (filas, sector = {conectado:true,areas:['CARTORIO']}) => ({
  ok: true, json: async () => ({
    geral: {filas:sector.generalFilas||filas,cartas_cadastradas:{expedidas:4,recebidas:2},oficios_cadastrados:{ano:{cartorio:1,gabinete:2}}},
    setor: {...sector,filas,prazos_vencidos:{expedidas:0,recebidas:0},prazos_vencidos_dias:{}},
    usuario:{modules:[],oficios_expedidos_30d:0,medidas_protetivas_expedidas_30d:0}, alertas:[]
  })
});
const denied = {ok:false,json:async()=>({error:'Acesso negado'})};
async function refresh(filasOrResponse, sector) {
  const response = Array.isArray(filasOrResponse)
    ? permitted(filasOrResponse, sector)
    : filasOrResponse?.payload && filasOrResponse?.scenario
      ? {ok:true,json:async()=>filasOrResponse.payload}
      : filasOrResponse;
  responseQueue.push(response);
  await document.ready();
  await new Promise(resolve => setImmediate(resolve));
}
function value(id) { const el=elements.get(id); return el ? el.textContent + el.children.map(child => typeof child === 'string' ? child : child.textContent).join('') : undefined; }

(async () => {
  const runnerPayloads = readRunnerPayloads();
  await refresh([{key:'guias',type:'expedicao_guia',count:2},{key:'guias',type:'envio_guia',count:1},{key:'oficios',type:'enviar_oficio',count:1}], {conectado:true,areas:['CARTORIO'],responsaveis_regras:{guias:'Ana Souza',oficios:'Bruno Lima'}});
  for (const id of ['dash-queue-guias-expedicao-responsaveis','dash-queue-guias-envio-responsaveis']) {
    assert.equal(value(id), 'Responsável: Ana');
    assert.equal(elements.get(id).children[1].tagName, 'B');
    assert.equal(elements.get(id).hidden, false);
  }
  assert.equal(value('dash-queue-oficios-responsaveis'), 'Responsável: Bruno');
  for (const id of ['dash-queue-expedidas-responsaveis','dash-queue-recebidas-responsaveis','dash-queue-editais-responsaveis','dash-overdue-expedidas-responsaveis','dash-overdue-recebidas-responsaveis']) assert.equal(elements.get(id).hidden, true);
  await refresh([{key:'guias',type:'expedicao_guia',count:2}], {conectado:true,areas:['CARTORIO'],responsaveis_regras:{guias:'  Érica   da Silva  '}});
  assert.equal(value('dash-queue-guias-expedicao-responsaveis'), 'Responsável: Érica');
  await refresh([{key:'guias',type:'expedicao_guia',count:2}], {conectado:true,areas:['CARTORIO'],responsaveis_regras:{guias:'Carla Dias'}});
  assert.equal(value('dash-queue-guias-expedicao-responsaveis'), 'Responsável: Carla');
  assert.equal(elements.get('dash-queue-oficios-responsaveis').hidden, true);
  await refresh([{key:'guias',type:'expedicao_guia',count:2}]);
  assert.equal(elements.get('dash-queue-guias-expedicao-responsaveis').hidden, true);
  assert.equal(value('dash-queue-guias-expedicao-responsaveis'), '');
  await refresh([
    {key:'guias',type:'expedicao_guia',count:2},
    {key:'cartas_expedidas',type:'aguardando_envio',count:4},
    {key:'editais',type:'certificacao_publicacao',count:3},
    {key:'oficios',type:'enviar_oficio',count:1}
  ]);
  assert.equal(value('dash-queue-guias-expedicao'), '2');
  assert.equal(value('dash-queue-guias-envio'), '0');
  assert.equal(elements.get('dash-queue-guias-expedicao-row').hidden, false);
  assert.equal(elements.get('dash-queue-guias-envio-row').hidden, true);
  assert.equal(value('dash-queue-expedidas'), '4');
  assert.equal(value('dash-queue-editais'), '3');
  assert.equal(value('dash-queue-oficios'), '1');
  assert.equal(elements.get('dash-cartorio-queue').hidden, false);
  const cartorioPayload = await permitted([{key:'guias',type:'expedicao_guia',count:2}]).json();
  assert.deepEqual(cartorioPayload.setor.filas, cartorioPayload.geral.filas);
  await refresh([{key:'guias',type:'expedicao_guia',count:2},{key:'cartas_expedidas',error:true,count:null}]);
  assert.equal(value('dash-queue-guias-expedicao'), '2'); // unrelated partial error does not poison guides
  await refresh([
    {key:'guias',type:'expedicao_guia',count:2},
    {key:'cartas_expedidas',type:'aguardando_envio',count:4}
  ]);
  assert.equal(value('dash-queue-guias-expedicao'), '2'); // repeated refresh replaces, never accumulates

  await refresh([{key:'guias',type:'detalhamento_informacoes',count:1}]); // transition through neither target stage
  assert.equal(value('dash-queue-guias-expedicao'), '0');
  assert.equal(value('dash-queue-guias-envio'), '0');
  await refresh([{key:'guias',type:'envio_guia',count:3}]);
  assert.equal(value('dash-queue-guias-expedicao'), '0');
  assert.equal(value('dash-queue-guias-envio'), '3');
  assert.equal(elements.get('dash-queue-guias-expedicao-row').hidden, true);
  assert.equal(elements.get('dash-queue-guias-envio-row').hidden, false);
  await refresh([]); // guide queue empty after sent
  assert.equal(value('dash-queue-guias-expedicao'), '0');
  assert.equal(value('dash-queue-guias-envio'), '0');
  assert.equal(elements.get('dash-cartorio-queue').hidden, true);
  for (const id of ['dash-queue-expedidas-row','dash-queue-recebidas-row','dash-queue-oficios-row','dash-queue-editais-row','dash-queue-guias-expedicao-row','dash-queue-guias-envio-row','dash-overdue-expedidas-row','dash-overdue-recebidas-row']) assert.equal(elements.get(id).hidden, true, id);

  await refresh([], {conectado:false,areas:['CARTORIO','GABINETE'],selecionado:'GABINETE',generalFilas:[{key:'guias',type:'expedicao_guia',count:8}]});
  assert.equal(elements.get('dash-cartorio-queue').hidden, true);
  assert.equal(elements.get('dash-queue-guias-expedicao-row').hidden, true);
  assert.equal(value('dash-queue-guias-expedicao'), '—');
  const gabinetePayload = await permitted([], {conectado:false,areas:['CARTORIO','GABINETE'],selecionado:'GABINETE',generalFilas:[{key:'guias',type:'expedicao_guia',count:8}]}).json();
  assert.equal(gabinetePayload.geral.filas[0].count, 8);
  assert.deepEqual(gabinetePayload.setor.filas, []);

  await refresh([{key:'guias',error:true,count:null},{key:'cartas_expedidas',type:'aguardando_envio',count:4}]);
  assert.equal(value('dash-queue-guias-expedicao'), '—');
  assert.equal(value('dash-queue-guias-envio'), '—');
  assert.equal(value('dash-queue-expedidas'), '4');
  assert.equal(elements.get('dash-queue-guias-expedicao-row').hidden, false);

  await refresh(denied);
  assert.equal(elements.get('dash-cartorio-queue').hidden, true);

  if (runnerPayloads) {
    for (const entry of runnerPayloads) {
      await refresh(entry);
      const rows = entry.payload.setor.filas || [];
      const broken = rows.some(row => row.key === 'guias' && row.error);
      const guideVisible = type => entry.scenario !== 'gabinete' && (broken || rows.some(row => row.key === 'guias' && row.type === type && Number(row.count) > 0));
      assert.equal(elements.get('dash-queue-guias-expedicao-row').hidden, !guideVisible('expedicao_guia'), `${entry.scenario}: dispatch row visibility`);
      assert.equal(elements.get('dash-queue-guias-envio-row').hidden, !guideVisible('envio_guia'), `${entry.scenario}: sending row visibility`);
      if (entry.scenario === 'expedicao') {
        assert.equal(value('dash-queue-guias-expedicao'), '1');
        assert.equal(value('dash-queue-guias-envio'), '0');
      } else if (entry.scenario === 'envio') {
        const expedicao = rows.find(row => row.key === 'guias' && row.type === 'expedicao_guia');
        assert.equal(value('dash-queue-guias-expedicao'), String(expedicao?.count ?? 0));
        assert.equal(value('dash-queue-guias-envio'), '1');
      } else if (entry.scenario === 'zero') {
        assert.equal(value('dash-queue-guias-expedicao'), '0');
        assert.equal(value('dash-queue-guias-envio'), '0');
      } else if (entry.scenario === 'falha') {
        assert.equal(value('dash-queue-guias-expedicao'), '—');
        assert.equal(value('dash-queue-guias-envio'), '—');
      } else {
        assert.deepEqual(entry.payload.setor.filas, [], 'Gabinete selection must not receive Cartório guide rows');
        assert.ok(entry.payload.geral.filas.some(row => row.key === 'guias'), 'synthetic general guide row should remain present');
      }
    }
    console.log('PASS: five synthetic MySQL payloads consumed by the dashboard UI');
  }
  console.log('PASS: REI-167 guide counters, empty/error states, refresh denial, and existing queue metrics');
})().catch(error => { console.error(error); process.exitCode = 1; });
