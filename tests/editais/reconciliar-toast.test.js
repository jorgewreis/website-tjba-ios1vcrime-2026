const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const calls = [];
const button = {
    disabled: false,
    addEventListener(name, handler) { this.handler = handler; }
};
const result = {
    solicitados: 2,
    editais_alterados: 2,
    editais_com_etapas_criadas: 1,
    etapas_criadas: 2,
    editais_com_datas_calculadas: 1,
    inalterados: 0,
    nao_encontrados: 0,
    finalizados: 1,
    falhas: 0
};
const window = {
    App: {
        CartorioTaskList: {
            initialize(config) {
                config.onReady({
                    notify(...args) { calls.push(['notify', ...args]); },
                    queuePostReloadToast(...args) { calls.push(['queue', ...args]); }
                });
            }
        }
    },
    document: {
        getElementById(id) { return id === 'reconciliar-editais-btn' ? button : null; },
        querySelectorAll() { return []; }
    },
    confirm() { return true; },
    fetch() {
        return Promise.resolve({ ok: true, json: () => Promise.resolve({ success: true, resultado: result }) });
    },
    location: { reload() { calls.push(['reload']); } }
};

vm.runInNewContext(fs.readFileSync('public/assets/js/shared/cartorio-editais-list.js', 'utf8'), {
    window,
    fetch: window.fetch
});
window.App.CartorioEditaisList.initialize({ editalIds: ['E1', 'E2'] });

(async () => {
    button.handler();
    await new Promise(resolve => setImmediate(resolve));
    const queued = calls.findIndex(call => call[0] === 'queue');
    const reloaded = calls.findIndex(call => call[0] === 'reload');
    assert(queued >= 0, 'success summary must be queued for display after reload');
    assert(reloaded > queued, 'summary must be queued before reloading');
    assert.equal(calls.filter(call => call[0] === 'notify').length, 0, 'success must not use an ephemeral toast');
    assert.match(calls[queued][3], /1 tiveram as datas calculadas/);
    assert.match(calls[queued][3], /1 foram finalizados/);
    console.log('PASS REI-114 UI toast: reconciliation totals survive list reload');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
