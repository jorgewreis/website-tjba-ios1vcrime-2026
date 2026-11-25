// Execute only the polling function against synthetic responses, no browser/DB.
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const source = fs.readFileSync('app/views/internal/sala-audiencias-painel-dia.php', 'utf8');
const start = source.indexOf('            function refreshTimelineData() {');
const end = source.indexOf('\n            renderTimeline();', start);
assert(start >= 0 && end > start);
let reply;
let updates = 0;
let renders = 0;
const context = {
  itemsById: { TEST: { status: 'designada' } },
  allFinished: () => false,
  updateRegisterLineState: () => { updates++; },
  renderTimeline: () => { renders++; },
  fetch: () => Promise.resolve(reply),
  Object, JSON, Promise
};
vm.createContext(context);
vm.runInContext(source.slice(start, end), context);
const response = (body, ok = true) => ({ ok, json: () => Promise.resolve(body) });
async function step(body, ok) {
  reply = response(body, ok);
  context.refreshTimelineData();
  await new Promise(resolve => setImmediate(resolve));
}
(async () => {
  await step({success:false}, false);
  assert.strictEqual(updates, 0);
  await step({success:true, items:{TEST:{id_pauta:'TEST', status:'realizada'}}, completo:true, consultados:['TEST'], observacao_alterada:false}, true);
  assert.strictEqual(context.itemsById.TEST.status, 'realizada');
  assert.strictEqual(updates, 1);
  assert.strictEqual(renders, 1);
  console.log('PASS painel-dia consumer sintético');
})().catch(error => { console.error(error); process.exitCode = 1; });
