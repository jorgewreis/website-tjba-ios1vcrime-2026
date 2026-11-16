const assert = require('assert');
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync('public/assets/js/modules/audiencias/pauta-edit.js', 'utf8');

async function run(action, scenario, focusMode) {
  const events = {}, notices = [], calls = [];
  const buttons = {};
  for (const id of ['btn-audiencia-edit-cancelar', 'btn-audiencia-edit-excluir']) {
    buttons[id] = {disabled: false, addEventListener: (_, fn) => {events[id] = fn;}};
  }
  const document = {getElementById: id => buttons[id] || null, querySelectorAll: () => [], body: {}, documentElement: {}};
  const selected = buttons[action === 'cancel' ? 'btn-audiencia-edit-cancelar' : 'btn-audiencia-edit-excluir'];
  selected.focus = () => { document.activeElement = selected; };
  if (focusMode) document.activeElement = selected;
  const location = {href: '/frame.php?page=salapautaedit&id=TESTE', reload() { this.reloaded = true; }, search: ''};
  const window = {location, PAUTA_EDIT_ID: 'TESTE', App: {Page: {notify: (...args) => notices.push(args), createSecondClickGuard: () => () => true}}};
  const fetch = (url, opts) => { calls.push({url, opts}); return scenario(); };
  vm.runInNewContext(source, {window, document, App: window.App, fetch, URLSearchParams, Promise, setTimeout, clearTimeout, console});
  events[action === 'cancel' ? 'btn-audiencia-edit-cancelar' : 'btn-audiencia-edit-excluir']();
  const other = {};
  if (focusMode) document.activeElement = focusMode === 'moved' ? other : document.body;
  await new Promise(resolve => setTimeout(resolve, 0));
  assert.equal(calls.length, 1);
  assert.equal(JSON.parse(calls[0].opts.body).id, 'TESTE');
  assert.equal(buttons['btn-audiencia-edit-cancelar'].disabled, false);
  return {location, notices, focusRestored: document.activeElement === selected, focusOther: document.activeElement === other};
}
(async () => {
  for (const action of ['cancel', 'delete']) {
    const success = await run(action, () => Promise.resolve({ok: true, status: 200, json: () => Promise.resolve({success: true})}));
    assert.equal(action === 'cancel' ? success.location.reloaded : success.location.href === '/frame.php?page=salapauta', true);
    for (const scenario of [
      () => Promise.resolve({ok: false, status: 401, json: () => Promise.resolve({error: 'sessão'})}),
      () => Promise.resolve({ok: false, status: 403, json: () => Promise.resolve({error: 'negado'})}),
      () => Promise.resolve({ok: false, status: 500, json: () => Promise.resolve({error: 'detalhe interno'})}),
      () => Promise.resolve({ok: true, status: 200, json: () => Promise.resolve({success: false})}),
      () => Promise.resolve({ok: true, status: 200, json: () => Promise.reject(new SyntaxError('JSON'))}),
      () => Promise.reject(new Error('rede')),
    ]) {
      const result = await run(action, scenario);
      assert.equal(result.location.reloaded, undefined);
      assert.equal(result.location.href, '/frame.php?page=salapautaedit&id=TESTE');
      assert(result.notices.some(n => n[0] === 'error'));
      assert(!JSON.stringify(result.notices).includes('detalhe interno'));
    }
  }
  const failure = () => Promise.resolve({ok: false, status: 403, json: () => Promise.resolve({error: 'negado'})});
  const lost = await run('cancel', failure, 'lost');
  assert.equal(lost.focusRestored, true);
  const moved = await run('delete', failure, 'moved');
  assert.equal(moved.focusOther, true);
  console.log('PASS foco restaurado somente quando perdido');
  console.log('PASS cancelamento e exclusão: sucesso, HTTP, JSON inválido, sucesso falso e rede');
})().catch(error => { console.error(error); process.exitCode = 1; });
