const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('public/assets/js/shared/cartorio-editais-create.js', 'utf8');
let clickHandler;
let createCalls = 0;
let redirect = '';
let generatedKeys = 0;
let tipoEdital = 'intimacao';
let blockStorageWrites = false;
const stored = new Map();
const requests = [];
const notifications = [];
const button = { addEventListener(type, handler) { if (type === 'click') clickHandler = handler; } };
const gate = { setInitial() {}, setWaiting() {}, setError() {}, setReady() {}, setSubmitting() {} };
const helpers = {
    initCreateButtonGate() { return gate; },
    getValue(id) { return id === 'filter-processo' ? '0000000-00.0000.0.00.0000' : (id === 'filter-tipo-edital' ? tipoEdital : ''); },
    normalizeProcessValue(value) { return { ok: true, value }; },
    notify(message) { notifications.push(message); },
    buildClassePayload() { return {}; }
};
const global = {
    App: { CartorioCreateHelpers: helpers },
    document: {
        getElementById(id) { return id === 'cadastrar-btn' ? button : (id === 'filter-prazo-dias' ? { value: '5' } : null); }
    },
    crypto: { randomUUID() { generatedKeys += 1; return generatedKeys === 1 ? '123e4567-e89b-42d3-a456-426614174000' : '123e4567-e89b-42d3-a456-426614174001'; } },
    addEventListener() {},
    sessionStorage: {
        getItem(key) { return stored.get(key) || null; },
        setItem(key, value) { if (blockStorageWrites) throw new Error('synthetic storage unavailable'); stored.set(key, value); },
        removeItem(key) { stored.delete(key); }
    },
    location: { set href(value) { redirect = value; } },
    fetch(url, options) {
        requests.push({ url, body: options.body });
        createCalls += 1;
        if (createCalls === 1) {
            // Server commits, but the response is lost before the browser sees it.
            return Promise.reject(new TypeError('synthetic network failure after commit'));
        }
        if (createCalls === 3) {
            return Promise.resolve({ ok: false, status: 409, json: () => Promise.resolve({ error: 'Chave já usada com outros dados.' }) });
        }
        return Promise.resolve({ ok: true, json: () => Promise.resolve({ id: 'EDI-synthetic' }) });
    }
};

function initialize() {
    vm.runInNewContext(source, { window: global, fetch: global.fetch });
    global.App.CartorioEditaisCreate.initialize({});
}
initialize();

async function tick() { await new Promise(resolve => setImmediate(resolve)); await new Promise(resolve => setImmediate(resolve)); }

(async () => {
    clickHandler();
    await tick();
    const firstBody = JSON.parse(requests[0].body);
    const key = firstBody.idempotency_key;
    assert.equal(requests.length, 1, 'create request should be the only request when its response is lost');
    assert.equal(stored.get('cartorio-editais-create-request'), key, 'idempotency key must be persisted before sending');

    // A page reload must restore the same key and retry the create endpoint.
    initialize();
    clickHandler();
    await tick();
    assert.equal(requests.length, 2, 'retry should call create with the same request key');
    assert.equal(requests[0].url, '/api/cartorio-editais/create.php');
    assert.equal(requests[1].url, '/api/cartorio-editais/create.php');
    assert.equal(JSON.parse(requests[1].body).idempotency_key, key, 'retry must reuse the original idempotency key');
    assert.equal(generatedKeys, 1, 'reload retry must not generate a new key');

    assert.equal(stored.has('cartorio-editais-create-request'), false, 'successful lost-response retry should clear pending state');
    assert.equal(redirect, '/frame.php?page=editais', 'same-key retry should finish the original create');

    // A changed payload with a previously persisted key is a distinct conflict.
    redirect = '';
    stored.set('cartorio-editais-create-request', key);
    tipoEdital = 'citacao';
    initialize();
    clickHandler();
    await tick();
    assert.equal(requests.length, 3, 'changed-payload attempt should issue one request and stop on 409');
    assert.equal(JSON.parse(requests[2].body).idempotency_key, key, 'conflict should exercise the prior key');
    assert.equal(JSON.parse(requests[2].body).tipo_edital, 'citacao');
    assert.equal(stored.has('cartorio-editais-create-request'), false, '409 conflict should clear the unusable pending key');
    assert.equal(redirect, '', '409 conflict must not redirect or retry automatically');
    assert.match(notifications[notifications.length - 1], /Confira a lista de editais.*iniciar uma nova tentativa/, '409 should tell the user how to start a new attempt');

    initialize();
    clickHandler();
    await tick();
    assert.equal(requests.length, 4, 'user retry after conflict should issue one new create request');
    assert.notEqual(JSON.parse(requests[3].body).idempotency_key, key, 'new intentional attempt should use a new key');
    assert.equal(generatedKeys, 2);
    assert.equal(stored.has('cartorio-editais-create-request'), false, 'successful new request should clear request state');
    assert.equal(redirect, '/frame.php?page=editais');

    redirect = '';
    blockStorageWrites = true;
    initialize();
    clickHandler();
    await tick();
    assert.equal(requests.length, 4, 'storage failure must stop before sending a create request');
    assert.equal(redirect, '', 'storage failure must not redirect');
    assert.match(notifications[notifications.length - 1], /guardar a tentativa de cadastro com segurança/, 'storage failure should be explained');
    process.stdout.write('PASS REI-100 lost-response retry and changed-payload conflict recovery\n');
})().catch(error => { process.stderr.write(String(error) + '\n'); process.exitCode = 1; });
