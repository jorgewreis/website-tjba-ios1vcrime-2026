const { strict: assert } = require('node:assert');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const code = fs.readFileSync(path.join(__dirname, '../../public/assets/js/shared/processos-edit.js'), 'utf8');
async function run(result, ok, expectsReload) {
    let handler, request, count = 0, resolveFetch;
    const button = {
        dataset: { numero: '0000000-00.2026.0.00.0000', processId: 'P1' },
        textContent: 'Sincronizar', addEventListener(event, callback) { handler = callback; }
    };
    const status = {textContent: ''};
    const window = {
        document: {getElementById: id => ({'btn-proc-datajud-sync':button,'proc-datajud-sync-status':status})[id]},
        fetch: (url, options) => { request = {url, options}; return new Promise(resolve => { resolveFetch = resolve; }); },
        sessionStorage: {getItem:()=>null,setItem:()=>{}},
        location: {reload:()=>{ count++; }}
    };
    vm.runInNewContext(code, {window});
    window.App.ProcessosEdit.initialize();
    const pending = handler();
    assert.equal(button.disabled, true);
    await handler();
    assert.equal(request.url, '/api/processos/datajud-sync.php');
    assert.equal(request.options.credentials, 'same-origin');
    assert.deepEqual(JSON.parse(request.options.body), {origem:'ficha',numero:button.dataset.numero,processo_id:'P1'});
    resolveFetch({ok, json: async()=>result});
    await pending;
    assert.equal(status.textContent, result.message ?? result.error);
    assert.equal(count, expectsReload ? 1 : 0);
    assert.equal(button.disabled, expectsReload);
}
(async()=>{
    await run({success:true, message:'Sem alteração', classe:{action:'noop'}, assuntos:{action:'noop'}},true,false);
    await run({success:true, message:'Assuntos alterados', classe:{action:'noop'}, assuntos:{action:'updated'}},true,true);
    await run({error:'Fonte indisponível'},false,false);
    console.log('PASS: ficha DataJud envia uma vez, recarrega só quando muda e mostra erro sem retry.');
})().catch(error=>{console.error(error);process.exitCode=1;});
