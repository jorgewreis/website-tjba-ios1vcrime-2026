const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const source = fs.readFileSync('app/views/internal/sala-audiencias-atas-estudantes.php', 'utf8');
const marker = 'var requestInFlight = false;';
const markerIndex = source.indexOf(marker);
const start = source.lastIndexOf('(function () {', markerIndex);
const close = source.indexOf('    })();', markerIndex);
assert(start >= 0 && markerIndex > start && close > markerIndex);

let reply;
let reloads = 0;
let intervalMs = 0;
let visibilityHandler;
let focusedTitle = '';
let activeElement;
const response = (body, ok = true) => ({ ok, json: () => Promise.resolve(body) });
const rows = [
  makeRow('BLOCKED', '<button data-nav-href="/api/sala-audiencias-ata/download.php?pauta=BLOCKED">Baixar</button>'),
  makeRow('ALLOWED', '<button data-nav-href="/api/sala-audiencias-ata/download.php?pauta=ALLOWED">Baixar</button>'),
  makeRow('MISSING', '<button data-nav-href="/api/sala-audiencias-ata/download.php?pauta=MISSING">Baixar</button>')
];

function makeRow(id, html) {
  const attrs = { 'data-pauta': id };
  return {
    innerHTML: html,
    removed: false,
    getAttribute(name) { return attrs[name] || null; },
    hasAttribute(name) { return Object.prototype.hasOwnProperty.call(attrs, name); },
    setAttribute(name, value) { attrs[name] = value; },
    contains(element) { return element === this; },
    remove() { this.removed = true; }
  };
}
activeElement = rows[0];

const context = {
  document: {
    hidden: false,
    get activeElement() { return activeElement; },
    querySelectorAll: () => rows.filter(row => !row.removed),
    querySelector: () => ({ focus() { focusedTitle = 'Atas de Audiências'; } }),
    addEventListener(name, callback) { if (name === 'visibilitychange') visibilityHandler = callback; }
  },
  window: {
    setInterval(callback, ms) { intervalMs = ms; return 1; },
    location: { reload() { reloads += 1; } }
  },
  fetch: () => Promise.resolve(reply),
  JSON, Array, Object, String, Set, Promise, Error
};

const blockedSnapshot = {
  success: true,
  completo: true,
  consultados: ['BLOCKED', 'ALLOWED', 'MISSING'],
  items: {
    BLOCKED: { id_pauta: 'BLOCKED', indisponivel: true },
    ALLOWED: { id_pauta: 'ALLOWED', pode_inscrever: false }
  }
};
reply = response(blockedSnapshot);
vm.createContext(context);
vm.runInContext(source.slice(start, close + '    })();'.length), context);
const tick = async body => {
  reply = response(body);
  visibilityHandler();
  await new Promise(resolve => setImmediate(resolve));
  await new Promise(resolve => setImmediate(resolve));
};

(async () => {
  assert.strictEqual(intervalMs, 30000);
  assert.strictEqual(typeof visibilityHandler, 'function');

  await tick(blockedSnapshot);
  assert.match(rows[0].innerHTML, /Audiência indisponível/);
  assert.doesNotMatch(rows[0].innerHTML, /download\.php|BLOCKED/);
  assert.strictEqual(rows[0].getAttribute('data-indisponivel'), '1');
  assert.strictEqual(focusedTitle, 'Atas de Audiências');
  assert.match(rows[1].innerHTML, /download\.php/);
  assert.match(rows[2].innerHTML, /Audiência indisponível/);
  assert.doesNotMatch(rows[2].innerHTML, /download\.php/);

  reply = { ok: false, json: () => Promise.resolve({ success: false }) };
  visibilityHandler();
  await new Promise(resolve => setImmediate(resolve));
  await new Promise(resolve => setImmediate(resolve));
  assert.match(rows[1].innerHTML, /Não foi possível verificar/);
  assert.doesNotMatch(rows[1].innerHTML, /download\.php/);
  assert.strictEqual(rows[1].getAttribute('data-indisponivel'), 'unknown');
  assert.match(rows[0].innerHTML, /Audiência indisponível/);
  assert.strictEqual(rows[0].getAttribute('data-indisponivel'), '1');

  await tick(blockedSnapshot);
  assert.strictEqual(reloads, 1);
  console.log('REI-161 atas polling: PASS');
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
