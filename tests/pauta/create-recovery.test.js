const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class Element {
  constructor(value = '') { this.value = value; this.listeners = {}; this.attrs = {}; this.disabled = false; this.textContent = ''; this.options = []; this.selectedIndex = -1; this.classList = { add() {}, remove() {} }; this.style = { removeProperty() {} }; }
  addEventListener(name, fn) { (this.listeners[name] ||= []).push(fn); }
  fire(name) { for (const fn of this.listeners[name] || []) fn({ target: this }); }
  setAttribute(name, value) { this.attrs[name] = value; }
  getAttribute(name) { return this.attrs[name] || null; }
  removeAttribute(name) { delete this.attrs[name]; }
  querySelector() { return null; }
  appendChild() {}
}

async function main() {
  const view = fs.readFileSync('app/views/internal/sala-audiencias-pauta-create.php', 'utf8');
  assert.match(view, /<label for="audiencia-classe">Classe processual[\s\S]*?<span[^>]*id="processo-ap-info"[^>]*aria-live="polite"[^>]*><\/span><\/label>/, 'status deve ser separado do rótulo');
  const ids = ['audiencia-processo', 'audiencia-tipo', 'audiencia-data', 'audiencia-hora', 'audiencia-sala', 'audiencia-status', 'audiencia-classe', 'hidden-classe-codigo', 'hidden-classe-descricao', 'btn-audiencia-cadastrar', 'processo-ap-info'];
  const el = Object.fromEntries(ids.map(id => [id, new Element()]));
  Object.assign(el['audiencia-processo'], { value: '12345678901234567890' });
  el['audiencia-data'].value = '2099-01-01';
  el['audiencia-hora'].value = '10:00';
  el['audiencia-sala'].value = 'audin';
  el['audiencia-classe'].value = 'Classe';
  let resolveCreate, requests = 0, lastPayload;
  const listeners = {};
  const document = { getElementById: id => el[id] || null, createElement: () => new Element(), querySelectorAll: () => [el['btn-audiencia-cadastrar']] };
  el['processo-ap-info'].textContent = 'Consultando o DataJud...';
  const window = { document, location: {}, AUDIENCIA_HORARIOS_GRADE: [], addEventListener(name, fn) { (listeners[name] ||= []).push(fn); }, dispatchEvent(event) { for (const fn of listeners[event.type] || []) fn(event); }, normalizeAndValidateCNJValue: v => ({ ok: v.length === 20, formatted: v }) };
  const fetch = (url, options) => {
    if (url.includes('occupied-slots')) return Promise.resolve({ ok: true, json: async () => ({ available: ['10:00'] }) });
    requests++;
    lastPayload = JSON.parse(options.body);
    return new Promise(resolve => { resolveCreate = resolve; });
  };
  const context = { window, document, fetch, setTimeout, clearTimeout, console, CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } }, sessionStorage: { setItem() {} } };
  vm.runInNewContext(fs.readFileSync('public/assets/js/modules/datajud/datajud-cycle-helper.js', 'utf8'), context);
  vm.runInNewContext(fs.readFileSync('public/assets/js/modules/audiencias/pauta-create.js', 'utf8'), context);
  await new Promise(resolve => setImmediate(resolve));
  el['audiencia-hora'].value = '10:00';
  const button = el['btn-audiencia-cadastrar'];
  button.fire('click');
  assert.equal(button.disabled, false, 'falha de campo obrigatório deve permitir recuperação');
  el['audiencia-tipo'].value = 'Instrução';
  el['audiencia-sala'].fire('input');
  assert.equal(button.disabled, false, 'mudança de sala não deve bloquear');
  el['audiencia-classe'].attrs['aria-busy'] = 'true';
  button.fire('click');
  assert.equal(requests, 0, 'DataJud pendente não envia cadastro');
  delete el['audiencia-classe'].attrs['aria-busy'];
  el['audiencia-classe'].value = 'Classe';
  window.dispatchEvent({ type: 'cnjValidated', detail: { inputId: 'audiencia-processo' } });
  button.fire('click');
  assert.equal(requests, 0, 'pré-consulta local pendente bloqueia mesmo antes de aria-busy');
  window.dispatchEvent({ type: 'datajudClasseResolved', detail: { processoInputId: 'outro', success: true } });
  button.fire('click');
  assert.equal(requests, 0, 'resultado de outro campo não libera cadastro');
  window.dispatchEvent({ type: 'datajudClasseResolved', detail: { processoInputId: 'audiencia-processo', success: true } });
  button.fire('click');
  assert.equal(requests, 1);
  assert.equal(button.disabled, true);
  button.fire('click');
  for (const fn of listeners.datajudClasseResolved || []) fn({ detail: { processoInputId: 'audiencia-processo', success: false } });
  assert.equal(requests, 1, 'submissão em curso não pode duplicar');
  assert.equal(button.disabled, true, 'resposta antiga não libera submissão');
  resolveCreate({ ok: false, json: async () => ({ error: 'falhou' }) });
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(button.disabled, false, 'erro do servidor permite nova tentativa');
  el['audiencia-classe'].value = 'CLASSE NÃO INFORMADA';
  button.fire('click');
  assert.equal(requests, 2, 'ausência de classe permite cadastro');
  assert.equal(lastPayload.classe_descricao, '', 'placeholder não é enviado como classe');
  assert.equal(lastPayload.classe_codigo, '');
  resolveCreate({ ok: false, json: async () => ({ error: 'falhou' }) });
  await new Promise(resolve => setImmediate(resolve));
  el['hidden-classe-codigo'].value = '123';
  button.fire('click');
  assert.equal(lastPayload.classe_codigo, '123', 'código válido não é descartado');
  assert.equal(lastPayload.classe_descricao, '');
  resolveCreate({ ok: false, json: async () => ({ error: 'falhou' }) });
  await new Promise(resolve => setImmediate(resolve));
  const op = window.DataJudCycle.getOperationId();
  window.DataJudCycle.startLoading({ processoInput: el['audiencia-processo'], classeInput: el['audiencia-classe'], statusElement: el['processo-ap-info'] });
  assert.equal(el['audiencia-classe'].getAttribute('aria-busy'), 'true');
  el['audiencia-processo'].value = '123';
  el['audiencia-processo'].fire('input');
  assert.equal(window.DataJudCycle.isStaleOperation(op), true, 'mudança invalida operação anterior');
  assert.equal(el['audiencia-classe'].getAttribute('aria-busy'), null, 'cancelamento limpa estado de carregamento');
  assert.equal(el['processo-ap-info'].textContent, '', 'cancelamento limpa mensagem de consulta anterior');
  assert.equal(el['audiencia-classe'].value, '');
  assert.equal(el['hidden-classe-descricao'].value, '');
  console.log('PASS recuperação de validação, DataJud e submissão de pauta (DOM/fetch simulados)');
}
async function prefillWithRealDatajud() {
  const ids = ['audiencia-processo', 'audiencia-tipo', 'audiencia-data', 'audiencia-hora', 'audiencia-sala', 'audiencia-status', 'audiencia-classe', 'hidden-classe-codigo', 'hidden-classe-descricao', 'btn-audiencia-cadastrar', 'processo-ap-info'];
  const el = Object.fromEntries(ids.map(id => [id, new Element()]));
  el['audiencia-processo'].id = 'audiencia-processo';
  el['audiencia-processo'].dataset = {};
  el['audiencia-processo'].value = '12345678901234567890';
  el['audiencia-tipo'].value = 'Instrução';
  el['audiencia-data'].value = '2099-01-01';
  el['audiencia-hora'].value = '10:00';
  el['audiencia-sala'].value = 'audin';
  let releaseDatajud, sent = 0, payload;
  const syncPayloads = [];
  const reads = [];
  const localTimers = [];
  const handlers = {};
  const document = { readyState: 'loading', addEventListener() {}, getElementById: id => el[id] || null, createElement: () => new Element(), querySelectorAll: () => [el['btn-audiencia-cadastrar']] };
  const window = { document, location: {}, AUDIENCIA_HORARIOS_GRADE: [], DATAJUD_SKIP_CLASSE_SYNC: true, addEventListener(name, fn) { (handlers[name] ||= []).push(fn); }, dispatchEvent(event) { for (const fn of handlers[event.type] || []) fn(event); }, normalizeAndValidateCNJValue: v => ({ ok: v.length === 20, formatted: v, digits: v }) };
  const fetch = (url, options) => {
    if (url.includes('occupied-slots')) return Promise.resolve({ ok: true, json: async () => ({ available: ['10:00'] }) });
    if (url.includes('classe-read')) return new Promise(resolve => { reads.push(resolve); });
    if (url.includes('search-processo')) return new Promise(resolve => { releaseDatajud = resolve; });
    if (url.includes('assunto-sync')) { syncPayloads.push(JSON.parse(options.body)); return Promise.resolve({ ok: true, status: 200, text: async () => '{}' }); }
    if (url.includes('/create.php')) { sent++; payload = JSON.parse(options.body); return Promise.resolve({ ok: false, json: async () => ({ error: 'simulado' }) }); }
    throw Error('URL inesperada ' + url);
  };
  const context = { window, document, fetch, setTimeout(fn, ms) { if (ms !== 10000) return setTimeout(fn, ms); const timer = { fn, active: true }; localTimers.push(timer); return timer; }, clearTimeout(timer) { if (timer && 'active' in timer) timer.active = false; else clearTimeout(timer); }, AbortController, console, CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } }, sessionStorage: { setItem() {} } };
  for (const file of ['public/assets/js/modules/datajud/datajud-cycle-helper.js', 'public/assets/js/modules/datajud/datajud-api.js', 'public/assets/js/modules/audiencias/pauta-create.js']) vm.runInNewContext(fs.readFileSync(file, 'utf8'), context);
  await new Promise(resolve => setImmediate(resolve));
  el['audiencia-hora'].value = '10:00';
  window.dispatchEvent({ type: 'cnjValidated', detail: { inputId: 'audiencia-processo', digits: el['audiencia-processo'].value } });
  assert.equal(reads.length, 1);
  el['btn-audiencia-cadastrar'].fire('click');
  assert.equal(sent, 0, 'leitura local em curso não pode enviar classe vazia');
  el['audiencia-processo'].value = '22345678901234567890';
  el['audiencia-processo'].fire('input');
  window.dispatchEvent({ type: 'cnjValidated', detail: { inputId: 'audiencia-processo', digits: el['audiencia-processo'].value } });
  assert.equal(reads.length, 2);
  reads[0]({ ok: true, json: async () => ({ classe: { codigo: '999', nome: 'Classe antiga' } }) });
  await new Promise(resolve => setImmediate(resolve));
  el['btn-audiencia-cadastrar'].fire('click');
  assert.equal(sent, 0, 'resposta local obsoleta não libera nova consulta');
  assert.equal(el['audiencia-classe'].value, '', 'resposta local obsoleta não preenche classe');
  reads[1]({ ok: true, json: async () => ({ classe: { codigo: '123', nome: 'Classe local' } }) });
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(typeof releaseDatajud, 'function');
  el['btn-audiencia-cadastrar'].fire('click');
  assert.equal(sent, 1, 'prefill local permite cadastrar enquanto DataJud continua');
  assert.equal(payload.classe_codigo, '123');
  assert.equal(payload.classe_descricao, 'Classe local');
  releaseDatajud({ status: 200, ok: true, headers: { get: () => 'application/json' }, json: async () => ({ data: null }) });
  await new Promise(resolve => setImmediate(resolve));
  window.dispatchEvent({ type: 'cnjValidated', detail: { inputId: 'audiencia-processo', digits: el['audiencia-processo'].value } });
  assert.equal(reads.length, 3);
  const timer = localTimers.findLast(t => t.active);
  assert.ok(timer, 'leitura local tem timeout');
  timer.fn();
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(timer.active, false, 'timeout é limpo após falha');
  releaseDatajud({ status: 200, ok: true, headers: { get: () => 'application/json' }, json: async () => ({ data: null }) });
  await new Promise(resolve => setImmediate(resolve));
  el['btn-audiencia-cadastrar'].fire('click');
  assert.equal(sent, 2, 'falha após timeout permite continuar com classe não informada');
  await new Promise(resolve => setImmediate(resolve));
  window.dispatchEvent({ type: 'cnjValidated', detail: { inputId: 'audiencia-processo', digits: el['audiencia-processo'].value } });
  assert.equal(reads.length, 4, 'nova consulta pode ser iniciada após timeout');
  reads[2]({ ok: true, json: async () => ({ classe: { codigo: '999', nome: 'Tardia' } }) });
  await new Promise(resolve => setImmediate(resolve));
  el['btn-audiencia-cadastrar'].fire('click');
  assert.equal(sent, 2, 'resposta atrasada após timeout não libera nova consulta');
  reads[3]({ ok: true, json: async () => ({ classe: { codigo: '321', nome: 'Nova' } }) });
  await new Promise(resolve => setImmediate(resolve));
  el['btn-audiencia-cadastrar'].fire('click');
  assert.equal(sent, 3, 'prefill na tentativa posterior libera cadastro');
  assert.equal(payload.classe_codigo, '321');
  releaseDatajud({ status: 200, ok: true, headers: { get: () => 'application/json' }, json: async () => ({ data: null }) });
  await new Promise(resolve => setImmediate(resolve));
  console.log('PASS janela de classe local e prefill (API DataJud real, rede e DOM simulados)');
}
main().then(prefillWithRealDatajud).catch(error => { console.error(error); process.exitCode = 1; });
