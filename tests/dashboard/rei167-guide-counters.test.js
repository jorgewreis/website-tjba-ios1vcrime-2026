'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const view = fs.readFileSync(path.join(root, 'app/views/internal/dashboard.php'), 'utf8');
const source = fs.readFileSync(path.join(root, 'public/assets/js/dashboard-interno.js'), 'utf8');
const summary = fs.readFileSync(path.join(root, 'app/services/DashboardResumoService.php'), 'utf8');
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
assert.match(view, /<strong>Cadastro de Expedição de Cartas<\/strong><span>Últimos 30 dias<\/span>/);
assert.match(view, /<strong>Cadastro de Expedição de Ofícios<\/strong><span>Ano corrente<\/span>/);
assert.match(summary, /INTERVAL 29 DAY/, 'registration series must query a 30-day window including today');
assert.match(summary, /\$lettersRegistered\[\$kind\]\s*\+=\s*\$count/, 'letter headline must sum the 30 daily counts');
assert.match(summary, /MAKEDATE\(YEAR\(CURDATE\(\)\),1\)/, 'office headline must count the current calendar year');
assert.match(view, /<section[^>]*id="dash-sector-column"[^>]*hidden/,
  'outer sector column starts hidden until an indicator is available');

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
const chartInstances = [];
class Chart {
  static defaults = {font:{}};
  constructor(element, config) { this.element=element; this.config=config; chartInstances.push(this); }
  destroy() { this.destroyed=true; }
}
const window = {Chart};
const sandbox = { window, document, Chart, fetch: async () => responseQueue.shift(), console, Number, Date, Math, Promise };
vm.runInNewContext(source, sandbox, { filename: 'dashboard-interno.js' });
const permitted = (filas, sector = {conectado:true,areas:['CARTORIO']}) => ({
  ok: true, json: async () => ({
    geral: {filas:sector.generalFilas||filas,cartas_cadastradas:{expedidas:12,recebidas:7,ultimos_30_dias:{labels:Array.from({length:30},(_,i)=>`D${i+1}`),expedidas:Array.from({length:30},(_,i)=>i),recebidas:Array.from({length:30},(_,i)=>i+1)}},oficios_cadastrados:{ano:{cartorio:24,gabinete:16},ultimos_30_dias:{labels:Array.from({length:30},(_,i)=>`D${i+1}`),cartorio:Array.from({length:30},(_,i)=>i+2),gabinete:Array.from({length:30},(_,i)=>i+3)}}},
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
  assert.equal(elements.get('dash-sector-column').hidden, elements.get('dash-cartorio-queue').hidden,
    'outer sector column must follow actual queue visibility on every refresh');
}
function value(id) { const el=elements.get(id); return el ? el.textContent + el.children.map(child => typeof child === 'string' ? child : child.textContent).join('') : undefined; }

(async () => {
  const runnerPayloads = readRunnerPayloads();
  await refresh([{key:'guias',type:'expedicao_guia',count:2},{key:'guias',type:'envio_guia',count:1},{key:'oficios',type:'enviar_oficio',count:1}], {conectado:true,areas:['CARTORIO'],responsaveis_regras:{guias:'Ana Souza',oficios:'Bruno Lima'}});
  assert.equal(value('dash-registered-expedidas'), '12');
  assert.equal(value('dash-registered-recebidas'), '7');
  assert.equal(value('dash-offices-year-cartorio'), '24');
  assert.equal(value('dash-offices-year-gabinete'), '16');
  const lettersChart=chartInstances.find(chart=>chart.element.id==='dash-chart-cadastros-semana');
  const officesChart=chartInstances.find(chart=>chart.element.id==='dash-chart-oficios-semana');
  assert.equal(lettersChart.config.data.labels.length, 30);
  assert.deepEqual(lettersChart.config.data.labels, Array.from({length:30},(_,i)=>`D${i+1}`));
  assert.deepEqual(lettersChart.config.data.datasets[0].data, Array.from({length:30},(_,i)=>i));
  assert.deepEqual(lettersChart.config.data.datasets[1].data, Array.from({length:30},(_,i)=>i+1));
  assert.equal(officesChart.config.data.labels.length, 30);
  assert.deepEqual(officesChart.config.data.datasets[0].data, Array.from({length:30},(_,i)=>i+2));
  assert.deepEqual(officesChart.config.data.datasets[1].data, Array.from({length:30},(_,i)=>i+3));
  for (const id of ['dash-queue-guias-expedicao-responsaveis','dash-queue-guias-envio-responsaveis']) {
    assert.equal(value(id), 'Responsável: Ana');
    assert.equal(elements.get(id).children[0].tagName, 'IMG');
    assert.equal(elements.get(id).children[2].tagName, 'B');
    assert.equal(elements.get(id).hidden, false);
  }
  assert.equal(value('dash-queue-oficios-responsaveis'), 'Responsável: Bruno');
  for (const [count,tier] of [[1,'mint'],[4,'purple'],[10,'warning'],[11,'danger']]) {
    await refresh([{key:'guias',type:'expedicao_guia',count}], {conectado:true,areas:['CARTORIO'],responsaveis_regras:{guias:'Ana Souza'},responsaveis_proprios:{guias:true}});
    assert.equal(value('dash-queue-guias-expedicao-responsaveis'), 'Responsável: você');
    assert.equal(elements.get('dash-queue-guias-expedicao-row').classList.contains('dash-own-'+tier), true);
    for (const other of ['mint','purple','warning','danger'].filter(x=>x!==tier)) assert.equal(elements.get('dash-queue-guias-expedicao-row').classList.contains('dash-own-'+other), false);
  }
  await refresh([{key:'guias',type:'expedicao_guia',count:2}], {conectado:true,areas:['CARTORIO'],responsaveis_regras:{guias:'Ana Souza'},responsaveis_proprios:{guias:false}});
  assert.equal(value('dash-queue-guias-expedicao-responsaveis'), 'Responsável: Ana');
  assert.equal(elements.get('dash-queue-guias-expedicao-row').classList.contains('dash-own-mint'), false);
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
  assert.equal(elements.get('dashboard-erro').hidden, false, 'request error stays visible outside hidden sector');
  for (const [key,type] of [['cartas_expedidas','aguardando_envio'],['cartas_recebidas','devolucao'],['oficios','enviar_oficio'],['editais','certificacao_publicacao']]) {
    await refresh([{key,type,count:1}]);
    assert.equal(elements.get('dash-sector-column').hidden, false, `${key} keeps sector visible without guides`);
  }
  const overdueOnly = await permitted([]).json();
  overdueOnly.setor.prazos_vencidos.expedidas = 1;
  await refresh({ok:true,json:async()=>overdueOnly});
  assert.equal(elements.get('dash-sector-column').hidden, false, 'overdue indicator alone keeps sector visible');
  // The overdue card uses the same assignment even when the dispatch queue is empty.
  const assigned = await permitted([]).json();
  assigned.setor.prazos_vencidos = {expedidas:2,recebidas:1};
  assigned.setor.prazos_vencidos_dias = {expedidas:9,recebidas:3};
  assigned.setor.responsaveis_regras = {cartas_expedidas_envio:'Lia Teste'};
  for (const [ownAssignment,label] of [[false,'Lia'],[true,'você']]) {
    assigned.setor.responsaveis_proprios = {cartas_expedidas_envio:ownAssignment};
    await refresh({ok:true,json:async()=>assigned});
    assert.equal(value('dash-overdue-expedidas'), '2');
    assert.equal(elements.get('dash-overdue-expedidas-row').dataset.urgencyDays, '9');
    for (const id of ['dash-queue-expedidas-responsaveis','dash-overdue-expedidas-responsaveis']) {
      const target = elements.get(id);
      assert.equal(target.hidden,false);
      assert.equal(target.children.at(-1).textContent,label);
    }
    assert.equal(elements.get('dash-queue-expedidas-row').hidden,true);
    assert.equal(elements.get('dash-overdue-recebidas-responsaveis').hidden,true);
  }
  assigned.setor.responsaveis_regras = {};
  await refresh({ok:true,json:async()=>assigned});
  assert.equal(elements.get('dash-overdue-expedidas-responsaveis').hidden,true);
  assert.deepEqual(elements.get('dash-overdue-expedidas-responsaveis').children,[]);
  assert.equal(value('dash-overdue-expedidas'), '2');
  assigned.setor.areas = ['GABINETE'];
  assigned.setor.responsaveis_regras = {cartas_expedidas_envio:'Lia Teste'};
  await refresh({ok:true,json:async()=>assigned});
  assert.equal(elements.get('dash-overdue-expedidas-responsaveis').hidden,true);

  await refresh([{key:'guias',error:true,count:null}]);
  assert.equal(elements.get('dash-sector-column').hidden, false, 'guide error alone remains visible');
  await refresh([]);
  assert.equal(elements.get('dash-sector-column').hidden, true, 'empty refresh hides outer sector again');

  const respScope = {conectado:true, areas:['CARTORIO'], responsaveis_regras:{guias:'Lia Teste',oficios:'Davi Exemplo'}, responsaveis_proprios:{guias:true,oficios:false}};
  const respRows = [{key:'guias',type:'expedicao_guia',count:2},{key:'guias',type:'envio_guia',count:1},{key:'oficios',type:'enviar_oficio',count:1}];
  await refresh(respRows, respScope);
  for (const key of ['guias-expedicao','guias-envio','oficios']) {
    const field = elements.get('dash-queue-'+key+'-responsaveis');
    assert.equal(field.hidden,false);
    assert.equal(field.children.at(-1).textContent,key==='oficios'?'Davi':'você');
  }
  await refresh(respRows, {...respScope, responsaveis_regras:{guias:'Eva Atualizada'},responsaveis_proprios:{}});
  assert.equal(elements.get('dash-queue-guias-expedicao-responsaveis').children.at(-1).textContent, 'Eva');
  assert.equal(elements.get('dash-queue-oficios-responsaveis').hidden,true);
  await refresh(respRows,{...respScope,areas:['GABINETE']});
  assert.equal(elements.get('dash-queue-guias-expedicao-responsaveis').hidden,true);

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
