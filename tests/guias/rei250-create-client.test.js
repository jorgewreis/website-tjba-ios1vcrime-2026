// DOM e HTTP simulados: não valida navegador ou autenticação reais.
const assert = require('assert');
const fs = require('fs');
const vm = require('vm');
const { webcrypto } = require('crypto');
const { TextEncoder } = require('util');
const source = fs.readFileSync('public/assets/js/shared/cartorio-guias-create.js', 'utf8');
const storage = new Map();
const sessionStorage = { getItem: k => storage.has(k) ? storage.get(k) : null, setItem: (k,v) => storage.set(k,String(v)), removeItem: k => storage.delete(k) };
const requests = [];
let behavior = 'network';
let choice = false;
let choiceQueue = [];
let duplicate = false;
let storageUnavailable = false;
let cryptoUnavailable = false;
let kind = 'Guia';
function makePage(actor) {
    let click;
    const button = { disabled: false, addEventListener: (type, fn) => { if (type === 'click') click = fn; } };
    const elements = { 'cadastrar-btn': button, 'filter-processo': { addEventListener() {}, blur() {} }, 'filter-denunciado': { blur() {} }, 'filter-dias-multa': { value: '' }, 'confirmar-nova-pessoa': { checked: true } };
    const values = { 'filter-processo': '0000001-69.2020.8.05.0001', 'filter-denunciado': 'Pessoa Sintetica', 'filter-classe': '', 'filter-regime': '' };
    const helpers = {
        initCreateButtonGate: () => ({ setWaiting() {}, setError() {}, setReady() {}, setSubmitting() {}, setInitial() {} }),
        notify() {}, getValue: k => k === 'filter-tipo' ? kind : values[k] || '',
        normalizeProcessValue: value => ({ ok: true, value }), formatProperName: value => value,
        buildClassePayload: () => ({})
    };
    const unavailableStorage = { getItem() { throw new Error('storage unavailable'); }, setItem() { throw new Error('storage unavailable'); } };
    const window = { App: { CartorioCreateHelpers: helpers }, document: { getElementById: id => elements[id] || null }, addEventListener() {}, crypto: cryptoUnavailable ? null : webcrypto, sessionStorage: storageUnavailable ? unavailableStorage : sessionStorage, confirm: () => choiceQueue.length ? choiceQueue.shift() : choice, location: { href: '' } };
    window.fetch = async (url, options) => {
        if (url.includes('check-duplicate')) return { status: 200, json: async () => ({ exists: duplicate }) };
        requests.push(JSON.parse(options.body));
        if (behavior === 'network') throw new Error('synthetic network loss');
        if (behavior === 'temporary') return { status: 503, json: async () => ({ error: 'Não foi possível verificar o envio anterior. Tente novamente.' }) };
        if (behavior === 'gone') return { status: 410, json: async () => ({ error: 'removida' }) };
        return { status: 201, json: async () => ({ id: 'GUI-SYNTHETIC' }) };
    };
    const context = { window, fetch: window.fetch, TextEncoder, Uint8Array, console };
    vm.runInNewContext(source, context);
    window.App.CartorioGuiasCreate.initialize({ actorFingerprint: actor });
    return { click: () => click({ preventDefault() {} }), window, elements };
}
async function waitFor(predicate) {
    for (let i = 0; i < 200; i++) { if (predicate()) return; await new Promise(resolve => setTimeout(resolve, 5)); }
    throw new Error('Timeout de fixture de UI.');
}
(async () => {
    const actorA = 'a'.repeat(64), actorB = 'b'.repeat(64);
    let page = makePage(actorA);
    page.click(); await waitFor(() => requests.length === 1);
    const first = requests[0].idempotency_key;
    assert.match(first, /^[a-f0-9]{64}$/);
    page = makePage(actorA); // reload da mesma aba após resposta perdida
    page.click(); await waitFor(() => requests.length === 2);
    assert.strictEqual(requests[1].idempotency_key, first);
    page = makePage(actorB); // mudança de ator: recusa enquanto usuário não confirma
    duplicate = true;
    choiceQueue = [true, false]; // confirma duplicata, recusa abandonar pendência
    page.click(); await new Promise(resolve => setTimeout(resolve, 80));
    assert.strictEqual(requests.length, 2);
    duplicate = false;
    choice = true;
    page.click(); await waitFor(() => requests.length === 3);
    assert.notStrictEqual(requests[2].idempotency_key, first);
    assert.strictEqual(requests[2].actor_fingerprint, actorB);
    const second = requests[2].idempotency_key;
    behavior = 'temporary';
    page.click(); await waitFor(() => requests.length === 4);
    await new Promise(resolve => setTimeout(resolve, 30));
    assert.strictEqual(JSON.parse(storage.values().next().value).key, second);
    behavior = 'gone'; choice = false;
    page.click(); await waitFor(() => requests.length === 5);
    assert.strictEqual(requests[4].idempotency_key, second);
    await new Promise(resolve => setTimeout(resolve, 30));
    assert.ok(storage.size > 0);
    choice = true;
    page.click(); await waitFor(() => requests.length === 6);
    await waitFor(() => storage.size === 0);
    behavior = 'success';
    page.click(); await waitFor(() => requests.length === 7);
    assert.notStrictEqual(requests[6].idempotency_key, second);
    await waitFor(() => page.window.location.href !== '');
    storageUnavailable = true;
    page = makePage(actorA);
    page.click(); await new Promise(resolve => setTimeout(resolve, 80));
    assert.strictEqual(requests.length, 7);
    storageUnavailable = false; cryptoUnavailable = true;
    page = makePage(actorA);
    page.click(); await new Promise(resolve => setTimeout(resolve, 80));
    assert.strictEqual(requests.length, 7);
    cryptoUnavailable = false;
    page = makePage(actorA);
    page.elements['confirmar-nova-pessoa'].checked = false;
    page.click(); await new Promise(resolve => setTimeout(resolve, 80));
    assert.strictEqual(requests.length, 7, 'Nome sem CPF ou confirmação não deve ser enviado');
    page.elements['filter-denunciado-select'] = { style: { display: '' }, value: '', options: [], selectedIndex: 0 };
    page.elements['confirmar-nova-pessoa'].checked = true;
    page.click(); await new Promise(resolve => setTimeout(resolve, 80));
    assert.strictEqual(requests.length, 7, 'Lista sem escolha não deve usar o nome oculto');
    page.elements['filter-denunciado-select'] = { style: { display: '' }, value: 'PRT-SELECIONADA', selectedIndex: 0, options: [{ textContent: 'Pessoa Sintetica - 529.982.247-25', dataset: { nomeParte: 'Pessoa Sintetica' } }] };
    page.click(); await waitFor(() => requests.length === 8);
    assert.strictEqual(requests[7].reu_id, 'PRT-SELECIONADA');
    assert.strictEqual(requests[7].denunciado, 'Pessoa Sintetica');
    assert.ok(!('confirmar_nova_pessoa' in requests[7]));
    console.log('PASS REI-250: retry, troca de ator e descarte explícito após 410 (DOM/HTTP simulados).');
})().catch(error => { console.error(error); process.exitCode = 1; });
