const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('public/assets/js/shared/cartorio-editais-create.js', 'utf8');
let clickHandler;
let callCount = 0;
let redirect = '';
const stored = new Map();
const requests = [];
const button = { addEventListener(type, handler) { if (type === 'click') clickHandler = handler; } };
const gate = { setInitial() {}, setWaiting() {}, setError() {}, setReady() {}, setSubmitting() {} };
const helpers = {
    initCreateButtonGate() { return gate; },
    getValue(id) { return id === 'filter-processo' ? '0000000-00.0000.0.00.0000' : (id === 'filter-tipo-edital' ? 'intimacao' : ''); },
    normalizeProcessValue(value) { return { ok: true, value }; },
    notify() {},
    buildClassePayload() { return {}; }
};
const global = {
    App: { CartorioCreateHelpers: helpers },
    document: {
        getElementById(id) { return id === 'cadastrar-btn' ? button : (id === 'filter-prazo-dias' ? { value: '5' } : null); }
    },
    addEventListener() {},
    sessionStorage: {
        getItem(key) { return stored.get(key) || null; },
        setItem(key, value) { stored.set(key, value); },
        removeItem(key) { stored.delete(key); }
    },
    location: { set href(value) { redirect = value; } },
    fetch(url, options) {
        requests.push({ url, body: options.body });
        callCount += 1;
        if (callCount === 1) return Promise.resolve({ ok: true, json: () => Promise.resolve({ id: 'EDI-synthetic' }) });
        if (callCount === 2) return Promise.resolve({ ok: false, json: () => Promise.resolve({ error: 'synthetic failure' }) });
        return Promise.resolve({ ok: true, json: () => Promise.resolve({}) });
    }
};

vm.runInNewContext(source, { window: global, fetch: global.fetch });
global.App.CartorioEditaisCreate.initialize({});

async function tick() { await new Promise(resolve => setImmediate(resolve)); await new Promise(resolve => setImmediate(resolve)); }

(async () => {
    clickHandler();
    await tick();
    assert.equal(requests.length, 2, 'first attempt should create the edital and then its subtasks');
    assert.equal(stored.get('cartorio-editais-pending-subtasks'), 'EDI-synthetic', 'failed step must preserve the created edital ID');
    clickHandler();
    await tick();
    assert.equal(requests.length, 3, 'retry should call only the idempotent subtasks endpoint');
    assert.equal(requests[2].url, '/api/cartorio-editais/criar-primeira-subtarefa.php');
    assert.equal(JSON.parse(requests[2].body).edital_id, 'EDI-synthetic');
    assert.equal(stored.has('cartorio-editais-pending-subtasks'), false, 'successful retry should clear pending state');
    assert.equal(redirect, '/frame.php?page=editais');
    process.stdout.write('PASS REI-100 create retry without duplicate edital\n');
})().catch(error => { process.stderr.write(String(error) + '\n'); process.exitCode = 1; });
