// Exercise the live student-panel consumer with a redacted polling response.
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const source = fs.readFileSync('app/views/internal/sala-audiencias-painel-estudantes.php', 'utf8');
const start = source.indexOf('        function normalizePollingItem(id, item) {');
const end = source.indexOf('\n        function normalizeStatus(', start);
assert(start >= 0 && end > start, 'Could not locate the panel polling consumer.');

const row = {
    innerHTML: '<a href="/api/sala-audiencias-ata/download.php?pauta=BLOCKED">Baixar ata</a><span>Designada</span>',
    attributes: { 'data-pauta': 'BLOCKED' },
    contains: () => false,
    getAttribute(name) { return this.attributes[name] || null; },
    setAttribute(name, value) { this.attributes[name] = value; }
};
const audienceLinks = ['label', 'anchor'].map(() => ({
    attributes: { 'data-audience-link-pauta': 'TERMINAL' },
    style: { display: 'none' },
    getAttribute(name) { return this.attributes[name] || null; }
}));
const inscriptionWrapper = {
    attributes: { 'data-inscricao-ui': 'ENROLLED' },
    style: { display: '' },
    innerHTML: '',
    getAttribute(name) { return this.attributes[name] || null; },
    setAttribute(name, value) { this.attributes[name] = value; },
    querySelector() { return null; }
};
const context = {
    activeElement: null,
    document: {
        querySelectorAll(selector) {
            if (selector === '.register.register-painel-estudante[data-pauta]') return [row];
            if (selector === '[data-audience-link-pauta]') return audienceLinks;
            if (selector === '[data-inscricao-ui]') return [inscriptionWrapper];
            return [];
        },
        querySelector() { return null; }
    },
    latestItemsById: {},
    renderVisibleTimeBlocks() {},
    renderTimeline() {},
    updateRegisterLineState() {},
    renderVagas() {}
};
context.statusForItem = item => item.status;
context.escapeHtml = value => String(value);

vm.createContext(context);
const inscriptionStart = source.indexOf('        function renderInscricaoUI(wrapper, item) {');
const renderVagasStart = source.indexOf('\n        function renderVagas(item) {', inscriptionStart);
assert(inscriptionStart >= 0 && renderVagasStart > inscriptionStart, 'Could not locate the enrollment renderer.');
vm.runInContext(source.slice(inscriptionStart, renderVagasStart), context);
vm.runInContext(source.slice(start, end) + '\napplyUpdates({ BLOCKED: { id_pauta: "BLOCKED", indisponivel: true } });', context);

assert.strictEqual(row.innerHTML, '<p class="padding-x-lg padding-y-md">Audiência indisponível.</p>');
assert.strictEqual(row.attributes['data-indisponivel'], '1');
assert.doesNotMatch(row.innerHTML, /href=|Baixar ata|Designada/);
row.innerHTML = '<a href="/api/sala-audiencias-ata/download.php?pauta=ENROLLED">Baixar ata</a><span>Em andamento</span>';
row.attributes['data-pauta'] = 'ENROLLED';
delete row.attributes['data-indisponivel'];
vm.runInContext('applyUpdates({ ENROLLED: { id_pauta: "ENROLLED", status: "em_andamento", ja_inscrito: true, pode_inscrever: false } });', context);
assert.match(row.innerHTML, /href=.*download\.php\?pauta=ENROLLED/);
assert.match(row.innerHTML, /Baixar ata|Em andamento/);
assert.strictEqual(row.attributes['data-indisponivel'], undefined);
inscriptionWrapper.attributes['data-inscricao-ui'] = 'FULL_ENROLLED';
vm.runInContext('applyUpdates({ FULL_ENROLLED: { id_pauta: "FULL_ENROLLED", status: "designada", ja_inscrito: true, pode_inscrever: false, vagas_esgotadas: true, total_inscritos_pauta: 5 } });', context);
assert.match(inscriptionWrapper.innerHTML, /Voce esta inscrito\./, 'polling must keep an enrolled student identified when capacity is full');
assert.doesNotMatch(inscriptionWrapper.innerHTML, /Não é permitida mais nenhuma inscrição|Inscrever-me/);

const normalizeStart = source.indexOf('        function normalizeStatus(statusRaw) {');
const normalizeEnd = source.indexOf('\n        function classByStatus(', normalizeStart);
const terminalStart = source.indexOf('        function isTerminalStatus(statusRaw) {');
const terminalEnd = source.indexOf('\n        function renderTimeBlock(', terminalStart);
assert(normalizeStart >= 0 && normalizeEnd > normalizeStart && terminalStart >= 0 && terminalEnd > terminalStart);
vm.runInContext(source.slice(normalizeStart, normalizeEnd) + '\n' + source.slice(terminalStart, terminalEnd), context);
assert(source.includes('renderAudienceLinkState(pauta, finalizada, jaInscrito);'), 'Polling must pass enrollment state to audience-link rendering.');
for (const status of ['cancelada', 'redesignada', 'finalizada', 'realizada']) {
    assert.strictEqual(context.isTerminalStatus(status), true);
    context.renderAudienceLinkState('TERMINAL', context.isTerminalStatus(status), true);
    assert.deepStrictEqual(audienceLinks.map(link => link.style.display), ['', ''], `enrolled ${status}`);
    context.renderAudienceLinkState('TERMINAL', context.isTerminalStatus(status), false);
    assert.deepStrictEqual(audienceLinks.map(link => link.style.display), ['none', 'none'], `unregistered ${status}`);
}
context.renderAudienceLinkState('TERMINAL', false, false);
assert.deepStrictEqual(audienceLinks.map(link => link.style.display), ['', '']);

// Exercise the complete polling render function, not only its link helper.
const renderTimeStart = source.indexOf('        function renderTimeBlock(block, item, allItems) {');
const renderTimeEnd = source.indexOf('\n        function statusClass(', renderTimeStart);
assert(renderTimeStart >= 0 && renderTimeEnd > renderTimeStart, 'Could not locate the time-block renderer.');
const lines = { innerHTML: '' };
const label = { textContent: '' };
const block = {
    attributes: { 'data-pauta': 'TERMINAL', 'data-ja-inscrito': '1', 'data-include-date': '0' },
    getAttribute(name) { return this.attributes[name] || null; },
    querySelector(selector) {
        if (selector === '.js-time-lines') return lines;
        if (selector === '.js-time-label') return label;
        return null;
    },
    parentElement: { querySelector() { return null; } }
};
context.statusForItem = item => item.status;
context.parseDateTime = () => NaN;
context.forecastTimestamp = () => NaN;
context.fmtDateTime = () => '';
context.fmtTime = () => '';
context.escapeHtml = value => String(value);
vm.runInContext(source.slice(renderTimeStart, renderTimeEnd), context);
context.renderTimeBlock(block, { status: 'finalizada', ja_inscrito: true }, {});
assert.deepStrictEqual(audienceLinks.map(link => link.style.display), ['', ''], 'full polling renderer preserves the link for an enrolled user');
assert.strictEqual(label.textContent, 'Finalizada em:');
block.attributes['data-ja-inscrito'] = '0';
context.renderTimeBlock(block, { status: 'finalizada', ja_inscrito: false }, {});
assert.deepStrictEqual(audienceLinks.map(link => link.style.display), ['none', 'none'], 'full polling renderer hides terminal link for a non-enrolled user');
console.log('PASS polling redige audiência indisponível, preserva inscritos em status terminais e oculta link terminal de não inscritos');
