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
let focusMoved=false;
row.contains=()=>true;
row.closest=()=>({querySelector:selector=>selector==='h2'?{focus(){focusMoved=true;}}:null});
const audienceLinks = ['label', 'anchor'].map(() => ({
    attributes: { 'data-audience-link-pauta': 'TERMINAL' },
    style: { display: 'none' },
    getAttribute(name) { return this.attributes[name] || null; }
}));
const context = {
    activeElement: null,
    document: {
        querySelectorAll(selector) {
            if (selector === '.register.register-painel-estudante[data-pauta]') return [row];
            if (selector === '[data-audience-link-pauta]') return audienceLinks;
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

vm.createContext(context);
vm.runInContext(source.slice(start, end) + '\napplyUpdates({ BLOCKED: { id_pauta: "BLOCKED", indisponivel: true } });', context);

assert.strictEqual(row.innerHTML, '<p class="padding-x-lg padding-y-md">Audiência indisponível.</p>');
assert.strictEqual(row.attributes['data-indisponivel'], '1');
assert.strictEqual(focusMoved,true, 'Focused controls must move focus to the section heading.');
row.contains=()=>false;
assert.doesNotMatch(row.innerHTML, /href=|Baixar ata|Designada/);
row.innerHTML = '<a href="/api/sala-audiencias-ata/download.php?pauta=ENROLLED">Baixar ata</a><span>Em andamento</span>';
row.attributes['data-pauta'] = 'ENROLLED';
delete row.attributes['data-indisponivel'];
vm.runInContext('applyUpdates({ ENROLLED: { id_pauta: "ENROLLED", status: "em_andamento", ja_inscrito: true, pode_inscrever: false } });', context);
assert.match(row.innerHTML, /href=.*download\.php\?pauta=ENROLLED/);
assert.match(row.innerHTML, /Baixar ata|Em andamento/);
assert.strictEqual(row.attributes['data-indisponivel'], undefined);

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
// Defensively redact a full-capacity non-enrolled response even if an older
// endpoint mistakenly returns fields instead of the minimal payload.
row.innerHTML = '<a href="https://synthetic.invalid/private">secret</a><span>10:00</span>';
row.attributes['data-pauta'] = 'CAPACITY';
delete row.attributes['data-indisponivel'];
vm.runInContext('applyUpdates({ CAPACITY: { id_pauta:"CAPACITY", vagas_esgotadas:true, ja_inscrito:false, status:"designada" } });', context);
assert.strictEqual(row.innerHTML, '<p class="padding-x-lg padding-y-md">Audiência indisponível.</p>');
assert.doesNotMatch(row.innerHTML, /secret|href=|10:00/);
// Legacy responses must redact regardless of enrolled status; false-like
// values are safe and unexpected flag values fail closed.
for (const flag of ['processo_sigiloso','envolve_menor','tem_prioridade_bloqueio','qtd_menor']) {
    for (const enrolled of [true, false]) {
        row.innerHTML='<a href="https://synthetic.invalid/private">secret</a><span>10:00</span>';
        row.attributes['data-pauta']='LEGACY'; delete row.attributes['data-indisponivel'];
        const item={id_pauta:'LEGACY',ja_inscrito:enrolled,status:'designada'}; item[flag]=1;
        context.applyUpdates({LEGACY:item});
        assert.strictEqual(row.innerHTML, '<p class="padding-x-lg padding-y-md">Audiência indisponível.</p>', flag);
        assert.doesNotMatch(row.innerHTML,/href=|secret|10:00/);
    }
}
for (const enrollment of [undefined, null, 'true', {}, 2]) {
    const item={id_pauta:'CAPACITY',vagas_esgotadas:true}; if (enrollment !== undefined) item.ja_inscrito=enrollment;
    assert.strictEqual(context.normalizePollingItem('CAPACITY',item).indisponivel,true);
}
for (const value of [false,0,'0']) assert.notStrictEqual(context.normalizePollingItem('SAFE',{id_pauta:'SAFE',processo_sigiloso:value}).indisponivel,true);
for (const value of [null,'false',{},2]) assert.strictEqual(context.normalizePollingItem('SAFE',{id_pauta:'SAFE',processo_sigiloso:value}).indisponivel,true);
// Exercise the actual renderer independently of the synthetic DOM updates.
const enrollmentStart = source.indexOf('        function renderInscricaoUI(wrapper, item) {');
const enrollmentEnd = source.indexOf('\n        function renderVagas(', enrollmentStart);
assert(enrollmentStart >= 0 && enrollmentEnd > enrollmentStart);
context.statusForItem = () => 'designada';
vm.runInContext(source.slice(enrollmentStart, enrollmentEnd), context);
const wrapper = { innerHTML: '', style: {}, getAttribute: () => 'SYN' };
context.renderInscricaoUI(wrapper, { id_pauta:'SYN', ja_inscrito:true, vagas_esgotadas:true });
assert.match(wrapper.innerHTML, /Voce esta inscrito/);
assert.doesNotMatch(wrapper.innerHTML, /Não é permitida mais nenhuma inscrição/);
context.renderInscricaoUI(wrapper, { id_pauta:'SYN', ja_inscrito:false, vagas_esgotadas:true });
assert.match(wrapper.innerHTML, /Não é permitida mais nenhuma inscrição/);
console.log('PASS polling redige audiência indisponível, preserva inscritos em status terminais e oculta link terminal de não inscritos');
