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
let intervalCallback;
let deadlineCallback;
let initiallyUnavailable = false;
let hang = false;
let lateResolve;
let clickUpdate;
let lastPayload;
const notice = { hidden: true };
const noticeLabel = { textContent: '' };
let visibleRows = true;
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
    removeAttribute(name) { delete attrs[name]; },
    contains(element) { return element === this; },
    remove() { this.removed = true; }
  };
}
activeElement = rows[0];

const context = {
  document: {
    hidden: false,
    get activeElement() { return activeElement; },
    querySelectorAll: () => visibleRows ? rows.filter(row => !row.removed) : [],
    querySelector: (selector) => selector === '.card-page' ? ({ getAttribute: () => initiallyUnavailable ? 'a'.repeat(32) : '' }) : ({ focus() { focusedTitle = 'Atas de Audiências'; } }),
    getElementById(id) { return id === 'rei161-lista-alterada' ? notice : id === 'rei161-lista-mensagem' ? noticeLabel : id === 'rei161-atualizar-lista' ? { addEventListener(name, fn) { if (name === 'click') clickUpdate = fn; } } : null; },
    addEventListener(name, callback) { if (name === 'visibilitychange') visibilityHandler = callback; }
  },
  window: {
    setInterval(callback, ms) { intervalCallback = callback; intervalMs = ms; return 1; },
    location: { reload() { reloads += 1; } }
  },
  fetch: (_, options) => { lastPayload = JSON.parse(options.body); return hang ? new Promise(resolve => { lateResolve = resolve; }) : Promise.resolve(reply); },
  setTimeout(callback) { deadlineCallback = callback; return 1; },
  clearTimeout() {},
  AbortController: class { constructor() { this.signal = {}; } abort() {} },
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
  assert.strictEqual(reloads, 0);
  assert.strictEqual(notice.hidden, false, 'mudança deve ser anunciada sem recarga automática');
  clickUpdate();
  assert.strictEqual(reloads, 1);
  notice.hidden = true;
  initiallyUnavailable = true;
  intervalCallback();
  await new Promise(resolve => setImmediate(resolve));
  await new Promise(resolve => setImmediate(resolve));
  assert.strictEqual(lastPayload.observacao, 'a'.repeat(32));
  assert.strictEqual(reloads, 1, 'polling não deve recarregar a página');
  assert.match(noticeLabel.textContent, /Não foi possível verificar/, 'servidor antigo que ignora nonce deve falhar fechado');
  await tick({ ...blockedSnapshot, observacao_alterada: true });
  assert.strictEqual(notice.hidden, false, 'liberação deve oferecer atualização explícita');
  initiallyUnavailable = false;
  rows[1].removeAttribute('data-indisponivel');
  rows[1].innerHTML = '<button data-nav-href="/api/sala-audiencias-ata/download.php?pauta=ALLOWED">Baixar</button>';
  hang = true;
  visibilityHandler();
  await new Promise(resolve => setImmediate(resolve));
  assert.strictEqual(typeof deadlineCallback, 'function');
  deadlineCallback();
  await new Promise(resolve => setImmediate(resolve));
  await new Promise(resolve => setImmediate(resolve));
  assert.match(rows[1].innerHTML, /Não foi possível verificar/);
  assert.doesNotMatch(rows[1].innerHTML, /download\.php/);
  lateResolve(response(blockedSnapshot));
  await new Promise(resolve => setImmediate(resolve));
  assert.match(rows[1].innerHTML, /Não foi possível verificar/, 'resposta tardia não deve restaurar dados');
  hang = false;
  await tick(blockedSnapshot);
  assert.strictEqual(reloads, 1, 'nova consulta não deve recarregar automaticamente');
  initiallyUnavailable = true;
  visibleRows = false;
  notice.hidden = true;
  reply = response({ success: false }, false);
  visibilityHandler();
  await new Promise(resolve => setImmediate(resolve));
  await new Promise(resolve => setImmediate(resolve));
  assert.strictEqual(notice.hidden, false, 'nonce expirado em página só com bloqueadas não pode falhar silenciosamente');
  assert.match(noticeLabel.textContent, /Não foi possível verificar/);
  assert.strictEqual(reloads, 1);
  console.log('REI-161 atas polling: PASS');
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
