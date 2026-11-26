const assert = require('assert');
const fs = require('fs');
const vm = require('vm');

const source = fs.readFileSync('public/assets/js/shared/copy-process-number.js', 'utf8');

async function run(responses) {
    const handlers = {};
    const calls = [];
    const notifications = [];
    const queuedToasts = [];
    let reloads = 0;
    const window = {
        App: {
            Page: {
                notify: (...args) => notifications.push(args),
                queuePostReloadToast: (...args) => queuedToasts.push(args),
                flushPostReloadToast() {}
            }
        },
        location: { reload: () => { reloads += 1; } }
    };
    const document = {
        addEventListener: (name, handler) => { handlers[name] = handler; }
    };
    const fetch = (url, options) => {
        calls.push({ url, options });
        const response = responses.shift();
        assert(response, `unexpected request: ${url}`);
        return Promise.resolve({
            ok: response.ok,
            status: response.status,
            json: () => response.nonJson ? Promise.reject(new SyntaxError('HTML')) : Promise.resolve(response.body)
        });
    };

    vm.runInNewContext(source, { window, document, fetch, Promise, JSON, console });

    const attributes = { 'data-processo-numero': '12345678901234567890' };
    const button = {
        dataset: {},
        style: {},
        getAttribute: name => attributes[name] || null,
        setAttribute() {},
        removeAttribute() {}
    };
    handlers.click({
        preventDefault() {},
        target: { closest: selector => selector === '.js-processo-datajud-sync' ? button : null }
    });
    await new Promise(resolve => setImmediate(resolve));
    return { calls, notifications, queuedToasts, reloads, button };
}

(async () => {
    const fallback = await run([
        { ok: false, body: { code: 'acesso_setorial_obrigatorio', error: 'Acesso ao processo negado.' } },
        { ok: true, body: { action: 'updated' } }
    ]);
    assert.deepStrictEqual(fallback.calls.map(call => call.url), [
        '/api/processos/datajud-sync.php',
        '/api/processos/classe-sync.php'
    ]);
    assert.deepStrictEqual(JSON.parse(fallback.calls[1].options.body), { numero: '12345678901234567890' });
    assert.strictEqual(fallback.reloads, 1);
    assert.strictEqual(fallback.notifications.length, 0);
    assert.match(fallback.queuedToasts[0][2], /Classe sincronizada/);
    assert.match(fallback.queuedToasts[0][2], /assuntos não foram atualizados/);

    const deniedOperation = await run([
        { ok: false, body: { code: 'forbidden', error: 'Operação não autorizada.' } }
    ]);
    assert.strictEqual(deniedOperation.calls.length, 1, 'other 403 responses must not fall back');
    assert.strictEqual(deniedOperation.reloads, 0);
    assert.strictEqual(deniedOperation.notifications.length, 1);
    assert.strictEqual(deniedOperation.notifications[0][2], 'Operação não autorizada.');

    const cf = await run([{ ok: false, status: 403, nonJson: true }]);
    assert.strictEqual(cf.calls.length, 1);
    assert.strictEqual(cf.reloads, 0);
    assert.match(cf.notifications[0][2], /servidor não autorizou/);
    const throttled = await run([{ ok: false, status: 503, body: { code: 'datajud_throttled', error: 'O DataJud limitou a consulta.' } }]);
    assert.strictEqual(throttled.calls.length, 1);
    assert.strictEqual(throttled.reloads, 0);
    assert.match(throttled.notifications[0][2], /DataJud limitou/);

    console.log('PASS REI-72/76 DataJud fallback preserves class-only permission and reports skipped subjects');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
