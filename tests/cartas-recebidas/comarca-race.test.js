'use strict';
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const path = require('path');

function element(value) {
    const handlers = {};
    return { value, disabled: false, addEventListener(name, fn) { (handlers[name] ||= []).push(fn); },
        fire(name) { (handlers[name] || []).forEach(fn => fn({ preventDefault() {} })); } };
}
const elements = {
    'salvar-btn': element(''), 'filter-cidade': element('Bom Jesus'), 'filter-estado': element('BA'),
    'filter-tipo': element('Intimação'), 'filter-prazo': element('30'), 'hidden-comarca-id': element('BA')
};
const requests = [];
const toasts = [];
const window = {
    App: {}, document: { getElementById: id => elements[id] || null }, location: {},
    fetch(url, options) { return new Promise((resolve, reject) => requests.push({ url, options, resolve, reject })); },
    showToast(toast) { toasts.push(toast); }
};
window.window = window;
const source = fs.readFileSync(path.join(__dirname, '../../public/assets/js/shared/cartorio-cartas-recebidas-edit.js'), 'utf8');
vm.runInNewContext(source, { window, fetch: window.fetch, Promise, Date, String, Number });
window.App.CartorioCartasRecebidasEdit.initialize({ cartaId: 'C', initial: { finalidade: 'Intimação', prazoDias: 30, cidade: 'Bom Jesus', estado: 'BA' }, endpoints: { lookupCidade: '/lookup?cidade=' } });
const city = elements['filter-cidade'], state = elements['filter-estado'], hidden = elements['hidden-comarca-id'], save = elements['salvar-btn'];
async function settle() { await new Promise(resolve => setImmediate(resolve)); }
(async () => {
    city.value = 'Outra'; city.fire('input');
    assert.strictEqual(hidden.value, ''); assert.strictEqual(save.disabled, true);
    city.fire('blur'); assert.match(requests[0].url, /cidade=Outra&uf=BA/);
    state.value = 'PI'; state.fire('change');
    assert.strictEqual(hidden.value, ''); assert.strictEqual(save.disabled, true);
    assert.match(requests[1].url, /cidade=Outra&uf=PI/);
    requests[1].resolve({ ok: true, json: async () => ({ found: true, id: 'PI', uf: 'PI', nome: 'Outra' }) });
    await settle(); assert.strictEqual(hidden.value, 'PI'); assert.strictEqual(save.disabled, false);
    requests[0].resolve({ ok: true, json: async () => ({ found: true, id: 'BA', uf: 'BA', nome: 'Outra' }) });
    await settle(); assert.strictEqual(hidden.value, 'PI'); assert.strictEqual(state.value, 'PI');
    city.value = 'Inexistente'; city.fire('input'); city.fire('blur');
    assert.strictEqual(save.disabled, true);
    requests[2].resolve({ ok: true, json: async () => ({ found: false }) });
    await settle(); assert.strictEqual(hidden.value, ''); assert.strictEqual(save.disabled, true);
    assert.strictEqual(toasts.at(-1).title, 'Cidade não identificada');
    city.fire('blur'); requests[3].reject(new Error('network'));
    await settle(); assert.strictEqual(hidden.value, ''); assert.strictEqual(save.disabled, true);
    assert.strictEqual(toasts.at(-1).title, 'Consulta indisponível');
    city.value = 'Outra'; city.fire('input'); city.fire('blur');
    requests[4].resolve({ ok: true, json: async () => ({ found: true, id: 'SEDE-PI', uf: 'PI', nome: 'Sede', status_cidade: 'Agregada' }) });
    await settle(); save.fire('click');
    assert.strictEqual(requests[5].url, '/api/cartorio-cartas-recebidas/update.php');
    const payload = JSON.parse(requests[5].options.body);
    assert.strictEqual(payload.cidade_origem, 'Sede'); assert.strictEqual(payload.uf_origem, 'PI');
    assert.strictEqual(payload.comarca_origem_id, 'SEDE-PI');
    city.value = 'Nova cidade'; city.fire('input');
    assert.strictEqual(save.disabled, true);
    requests[5].reject(new Error('network'));
    await settle(); assert.strictEqual(hidden.value, ''); assert.strictEqual(save.disabled, true);
    const countBeforeEmptyUf = requests.length;
    state.value = ''; state.fire('change');
    assert.strictEqual(requests.length, countBeforeEmptyUf);
    assert.strictEqual(hidden.value, ''); assert.strictEqual(save.disabled, true);
    assert.strictEqual(toasts.at(-1).title, 'Estado obrigatório');
    console.log('PASS comarca: invalidação, UF, consulta pendente e respostas fora de ordem');
})().catch(e => { console.error(e); process.exitCode = 1; });
