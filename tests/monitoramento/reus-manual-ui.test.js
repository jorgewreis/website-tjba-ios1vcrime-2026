const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class Element {
  constructor(value = '') { this.value = value; this.style = {}; this.handlers = {}; this.disabled = false; this.required = false; this.children = []; }
  addEventListener(name, fn) { this.handlers[name] = fn; }
  appendChild(child) { this.children.push(child); }
  focus() {}
}
const el = {
  'btn-gab-mon-registrar': new Element(),
  'gab-mon-reu-select': new Element(),
  'gab-mon-reu-nome': new Element(),
  'filter-processo': new Element('00000016920208050001'),
  'filter-classe': new Element(),
};
const events = {}, notices = [];
let payload = { processo_id: '', reus: [], mensagem: 'Nenhum réu vinculado encontrado. Informe o nome manualmente.' };
let delayed = false;
let fetchCount = 0;
const pending = [];
const document = { getElementById: id => el[id] || null, createElement: () => new Element() };
const window = { document, App: { Page: { notify: (...args) => notices.push(args) } }, location: {},
  addEventListener(name, fn) { events[name] = fn; } };
const fetch = (url, options) => { fetchCount++; return delayed ? new Promise((resolve, reject) => pending.push({ url, options, resolve, reject })) : Promise.resolve({ ok: true, json: async () => payload }); };
vm.runInNewContext(fs.readFileSync('public/assets/js/shared/gme-create.js', 'utf8'), { window, fetch, console, AbortController, setTimeout, clearTimeout });
window.App.GmeCreate.initialize({ reusEndpoint: '/synthetic', searchPeopleEndpoint: '/people', createEndpoint: '/create' });
async function run() {
  events.datajudClasseResolved({ detail: { processoInputId: 'filter-processo' } });
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(el['gab-mon-reu-select'].style.display, 'none');
  assert.equal(el['gab-mon-reu-nome'].style.display, '');
  assert.equal(el['gab-mon-reu-nome'].required, true);
  assert.equal(el['gab-mon-reu-nome'].disabled, false);
  assert.ok(notices.some(args => args[2] === payload.mensagem), 'mensagem de entrada manual não exibida');
  el['gab-mon-reu-nome'].value = 'Nome em edição';
  const chamadasAnteriores = fetchCount;
  events.datajudClasseResolved({ detail: { processoInputId: 'filter-processo' } });
  assert.equal(fetchCount, chamadasAnteriores, 'DataJud tardio não recarrega réus para o mesmo CNJ');
  assert.equal(el['gab-mon-reu-nome'].value, 'Nome em edição', 'DataJud tardio preserva edição');
  payload = { processo_id: 'P', reus: [{ id: 'R', nome: 'Réu sintético', tipo_parte: 'reu' }] };
  events.cnjValidated({ detail: { inputId: 'filter-processo', digits: el['filter-processo'].value } });
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(el['gab-mon-reu-select'].style.display, '');
  assert.equal(el['gab-mon-reu-nome'].style.display, 'none');
  assert.ok(el['gab-mon-reu-select'].children.some(option => option.value === 'processo_parte:R'));
  assert.equal(el['gab-mon-reu-select'].value, '', 'réu vinculado também exige escolha');
  assert.equal(el['btn-gab-mon-registrar'].disabled, true, 'não enviar vínculo implícito');
  el['gab-mon-reu-select'].value = 'processo_parte:R';
  el['gab-mon-reu-select'].handlers.change.call(el['gab-mon-reu-select']);
  const antesResolucaoTardia = fetchCount;
  events.datajudClasseResolved({ detail: { processoInputId: 'filter-processo' } });
  assert.equal(fetchCount, antesResolucaoTardia);
  assert.equal(el['gab-mon-reu-select'].value, 'processo_parte:R', 'DataJud tardio preserva seleção');
  delayed = true;
  el['filter-processo'].value = '00000016920208050001';
  events.cnjValidated({ detail: { inputId: 'filter-processo', digits: el['filter-processo'].value } });
  el['filter-processo'].value = '00000025420208050001';
  el['filter-processo'].handlers.input();
  events.cnjValidated({ detail: { inputId: 'filter-processo', digits: el['filter-processo'].value } });
  assert.equal(pending.length, 2);
  pending[1].resolve({ ok: true, json: async () => ({ reus: [], mensagem: 'Informe manualmente.' }) });
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(el['gab-mon-reu-nome'].disabled, false);
  el['gab-mon-reu-nome'].value = 'Nome atual';
  pending[0].resolve({ ok: true, json: async () => ({ reus: [{ id: 'OLD', nome: 'Réu antigo' }] }) });
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(el['gab-mon-reu-select'].style.display, 'none', 'resposta antiga reabriu seleção');
  assert.equal(el['gab-mon-reu-nome'].value, 'Nome atual', 'resposta antiga sobrescreveu entrada');
  assert.ok(!el['gab-mon-reu-select'].children.some(option => option.value === 'processo_parte:OLD'));
  events.cnjValidated({ detail: { inputId: 'filter-processo', digits: el['filter-processo'].value } });
  pending[2].reject(new Error('falha sintética'));
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(el['gab-mon-reu-nome'].disabled, false, 'falha deve liberar entrada manual');
  assert.ok(notices.some(args => args[2] === 'falha sintética'));
  events.cnjValidated({ detail: { inputId: 'filter-processo', digits: el['filter-processo'].value } });
  pending[3].resolve({ ok: true, json: async () => ({ reus: [{ id: 'NEW', nome: 'Réu atual' }] }) });
  await new Promise(resolve => setImmediate(resolve));
  assert.ok(el['gab-mon-reu-select'].children.some(option => option.value === 'processo_parte:NEW'), 'nova tentativa deve recuperar seleção');
  el['gab-mon-reu-select'].value = 'outro';
  el['gab-mon-reu-select'].handlers.change.call(el['gab-mon-reu-select']);
  el['gab-mon-reu-nome'].value = 'Nome Primeiro';
  el['gab-mon-reu-nome'].handlers.input();
  el['gab-mon-reu-nome'].handlers.blur();
  el['gab-mon-reu-nome'].value = 'Nome Segundo';
  el['gab-mon-reu-nome'].handlers.input();
  el['gab-mon-reu-nome'].handlers.blur();
  pending[5].resolve({ ok: true, json: async () => ({ pessoas: [
    { id: 'SECOND', nome_completo: 'Nome Segundo', cpf: '12345678901' },
    { id: 'HOMONIMO', nome_completo: 'Nome Segundo', cpf: '98765432100' },
    { id: 'SEMCPF', nome_completo: 'Nome Segundo', cpf: '', profissao_exibicao: 'Profissão' }
  ] }) });
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(el['gab-mon-reu-select'].value, '', 'lista não deve selecionar pessoa implicitamente');
  assert.equal(el['btn-gab-mon-registrar'].disabled, true, 'exigir escolha explícita');
  assert.ok(el['gab-mon-reu-select'].children.some(option => option.textContent.includes('123.456.789-01')));
  assert.ok(el['gab-mon-reu-select'].children.some(option => option.textContent.includes('987.654.321-00')));
  assert.ok(el['gab-mon-reu-select'].children.some(option => option.textContent.includes('CPF não informado')));
  pending[4].resolve({ ok: true, json: async () => ({ pessoas: [{ id: 'FIRST', nome_completo: 'Nome Primeiro' }] }) });
  await new Promise(resolve => setImmediate(resolve));
  assert.ok(el['gab-mon-reu-select'].children.some(option => option.value === 'parte:SECOND'));
  assert.ok(!el['gab-mon-reu-select'].children.some(option => option.value === 'parte:FIRST'), 'sugestão antiga foi aplicada');
  el['gab-mon-reu-select'].value = 'outro';
  el['gab-mon-reu-select'].handlers.change.call(el['gab-mon-reu-select']);
  el['gab-mon-reu-nome'].value = 'Terceiro Nome';
  el['gab-mon-reu-nome'].handlers.input();
  el['gab-mon-reu-nome'].handlers.blur();
  el['filter-processo'].value = '00000036920208050001';
  el['filter-processo'].handlers.input();
  pending[6].resolve({ ok: true, json: async () => ({ pessoas: [{ id: 'THIRD', nome_completo: 'Terceiro Nome' }] }) });
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(el['gab-mon-reu-select'].style.display, 'none', 'sugestão do processo anterior reabriu seleção');
  assert.ok(!el['gab-mon-reu-select'].children.some(option => option.value === 'parte:THIRD'));
  el['gab-mon-reu-nome'].value = 'Quarto Nome';
  el['gab-mon-reu-nome'].handlers.input();
  el['gab-mon-reu-nome'].handlers.blur();
  assert.equal(el['btn-gab-mon-registrar'].disabled, true, 'consulta pendente deve bloquear cadastro');
  pending[7].reject(new Error('falha sintética'));
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(el['btn-gab-mon-registrar'].disabled, true, 'falha exige confirmação');
  assert.ok(notices.some(args => args[1] === 'Consulta de pessoas'), 'falha deve ser informada');
  assert.ok(el['gab-mon-reu-select'].children.some(option => option.value === '__repetir__'));
  el['gab-mon-reu-select'].value = '__repetir__';
  el['gab-mon-reu-select'].handlers.change.call(el['gab-mon-reu-select']);
  assert.equal(pending[8].url.includes('/people'), true, 'repetir inicia nova consulta');
  pending[8].resolve({ ok: true, json: async () => ({ pessoas: [{ id: 'P', nome_completo: 'Quarto Nome', cpf: '' }] }) });
  await new Promise(resolve => setImmediate(resolve));
  assert.ok(el['gab-mon-reu-select'].children.some(option => option.textContent === 'Usar nome informado: Quarto Nome'));
  el['gab-mon-reu-select'].value = '__novo__';
  el['gab-mon-reu-select'].handlers.change.call(el['gab-mon-reu-select']);
  const antesBlur = pending.length;
  el['gab-mon-reu-nome'].handlers.blur();
  assert.equal(pending.length, antesBlur, 'nome confirmado não é pesquisado novamente');
  assert.equal(el['btn-gab-mon-registrar'].disabled, false);
  el['btn-gab-mon-registrar'].handlers.click();
  assert.equal(pending[9].url, '/create');
  assert.equal(JSON.parse(pending[9].options.body).reu_complemento, 'Quarto Nome');
  pending[9].resolve({ ok: false, json: async () => ({ error: 'simulado' }) });
  await new Promise(resolve => setImmediate(resolve));
  el['gab-mon-reu-nome'].value = 'Quarto Nome Alterado';
  el['gab-mon-reu-nome'].handlers.input();
  el['gab-mon-reu-nome'].handlers.blur();
  assert.equal(pending[10].url.includes('/people'), true, 'editar invalida escolha anterior');
  pending[10].resolve({ ok: true, json: async () => ({ pessoas: [] }) });
  await new Promise(resolve => setImmediate(resolve));
  el['gab-mon-reu-nome'].value = 'Nome sem blur';
  el['gab-mon-reu-nome'].handlers.input();
  el['btn-gab-mon-registrar'].handlers.click();
  assert.equal(pending[11].url.includes('/people'), true, 'submit sem blur deve consultar antes');
  pending[11].resolve({ ok: true, json: async () => ({ pessoas: [] }) });
  await new Promise(resolve => setImmediate(resolve));
  el['btn-gab-mon-registrar'].handlers.click();
  assert.equal(pending[12].url, '/create', 'lista vazia validada permite enviar');
  assert.equal(JSON.parse(pending[12].options.body).reu_complemento, 'Nome sem blur');
  pending[12].resolve({ ok: false, json: async () => ({ error: 'simulado' }) });
  await new Promise(resolve => setImmediate(resolve));
  console.log('PASS orientação manual e seleção existente (DOM/fetch simulados)');
}
run().catch(error => { console.error(error); process.exitCode = 1; });
