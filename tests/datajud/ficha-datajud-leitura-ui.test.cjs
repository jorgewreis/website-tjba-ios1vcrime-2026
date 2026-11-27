'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const script = fs.readFileSync(path.join(__dirname, '../../public/assets/js/shared/processo-ficha.js'), 'utf8');

async function render(http, payload, {broken = false, manualTry = false, localSubjects, subjectsOnField = false, editMode = false} = {}) {
    const calls = [];
    const status = {textContent: ''};
    const retry = {hidden: false, addEventListener(_event, listener) { this.onClick = listener; }};
    const start = {hidden: false, addEventListener(_event, listener) { this.onClick = listener; }};
    const list = {children: [], replaceChildren(...entries) { this.children = entries; }};
    const history = {hidden: true, addEventListener() {}};
    const fields = {vara: 'Vara local', classe: 'Classe local', sigilo: 'Sigilo local', assuntos: 'Assuntos locais'};
    const pane = {dataset: {fichaDatajud: 'PROCESSO_SINTETICO', fichaConsultaManual: editMode ? '' : undefined, fichaAssuntosLocais: subjectsOnField ? undefined : localSubjects}, querySelector(key) {
        return ({'[data-ficha-status]':status, '[data-ficha-tentar]':retry, '[data-ficha-consultar]':start, '[data-ficha-recentes]':list,
            '[data-ficha-historico]':history, '[data-ficha-todas]':list})[key];
    }};
    const document = {getElementById: () => null, querySelector(key) {
        if (key === '[data-ficha-datajud]') return pane;
        const field = key.replace(/^\[data-ficha-(.+)\]$/, '$1');
        return {dataset: {fichaAssuntosLocais: subjectsOnField && field === 'assuntos' ? localSubjects : undefined}, hasAttribute: () => field === 'sigilo', set textContent(val) { fields[field] = val; }};
    }};
    const response = {status: http, ok: http >= 200 && http < 300, async json() {
        if (broken) throw new Error('fixture não JSON');
        return payload;
    }};
    vm.runInNewContext(script, {document, fetch: async (url, options) => {
        calls.push([url, options.method, JSON.parse(options.body), options.credentials, options.cache]);
        return response;
    }});
    await new Promise(setImmediate);
    if (editMode) {
        assert.equal(calls.length, 0, 'edição não deve abrir consulta paralela automaticamente');
        assert.equal(start.hidden, false);
        await Promise.all([start.onClick(), start.onClick()]);
        assert.equal(start.hidden, true);
    }
    assert.deepEqual(calls, [['/api/processos/datajud-ficha.php','POST',
        {processo_id:'PROCESSO_SINTETICO'}, 'same-origin','no-store']]);
    if (manualTry) {
        await retry.onClick();
        assert.equal(calls.length, 2, 'retry requires a click; no automatic retry');
    }
    return {message:status.textContent, retry:!retry.hidden, fields};
}

(async () => {
    const cases = [
        [503,'datajud_throttled',/limitou a consulta/,true],
        [503,'datajud_incomplete',/consulta incompleta/,true],
        [503,'datajud_transport',/conectar ao DataJud/,true],
        [503,'datajud_unavailable',/temporariamente indisponível/,true],
        [502,'datajud_authorization',/autenticação da integração/,false],
        [503,'datajud_not_configured',/não está configurada/,false],
        [409,'datajud_ambiguous',/mais de um registro/,false],
        [422,'datajud_not_queryable',/Confira o cadastro/,false],
        [502,'datajud_throttled',/Não foi possível consultar/,true],
        [503,'codigo_inexistente',/Não foi possível consultar/,true],
        [503,'toString',/Não foi possível consultar/,true],
    ];
    for (const [http,code,re,shouldRetry] of cases) {
        const result = await render(http, {estado:'erro',code,error:'mensagem externa não confiável'},
            {manualTry:code === 'datajud_throttled' && http === 503});
        assert.match(result.message,re);
        assert.doesNotMatch(result.message,/mensagem externa/);
        assert.equal(result.retry,shouldRetry);
        assert.deepEqual(result.fields,{vara:'Vara local',classe:'Classe local',sigilo:'Sigilo local',assuntos:'Assuntos locais'});
    }
    const empty = await render(200,{estado:'indisponivel'});
    assert.match(empty.message,/Não há dados públicos verificáveis/);
    assert.equal(empty.retry,false);
    const edit = await render(503,{estado:'erro',code:'datajud_throttled'},{editMode:true,manualTry:true});
    assert.match(edit.message,/limitou a consulta/);
    assert.equal(edit.retry,true);
    const editOk = await render(200, {estado:'disponivel', vara:'Vara fictícia', classe:'Classe fictícia',
        assuntos:['Remoto'], movimentos:[]}, {editMode:true,localSubjects:'["Manual"]'});
    assert.equal(editOk.fields.vara,'Vara fictícia');
    assert.equal(editOk.fields.classe,'Classe fictícia');
    assert.equal(editOk.fields.assuntos,'Remoto; Manual');
    const editMissing = await render(200, {estado:'disponivel', vara:'', classe:'',
        assuntos:[], movimentos:[]}, {editMode:true,localSubjects:'["Manual"]'});
    assert.equal(editMissing.fields.vara,'Vara local');
    assert.equal(editMissing.fields.classe,'Classe local');
    assert.equal(editMissing.fields.assuntos,'Manual');
    const restricted = await render(200,{estado:'restrito',sigilo:'Sigilo externo'});
    assert.match(restricted.message,/indisponível na base pública por sigilo/);
    assert.equal(restricted.fields.sigilo,'Sigilo local');
    assert.equal(restricted.retry,false);
    const ok = await render(200,{estado:'disponivel',varA:'ignorado',vara:'Vara de teste',classe:'Classe de teste',movimentos:[]});
    assert.match(ok.message,/Nenhuma movimentação/);
    assert.equal(ok.fields.vara,'Vara de teste');
    assert.equal(ok.fields.classe,'Classe de teste');
    const legacy = await render(502,{estado:'erro'});
    assert.match(legacy.message,/Não foi possível consultar/);
    const nonJson = await render(502,null,{broken:true});
    assert.match(nonJson.message,/Não foi possível consultar/);
    const invalid = await render(200,{estado:'erro'});
    assert.match(invalid.message,/Não foi possível consultar/);
    const combined = await render(200,{estado:'disponivel',assuntos:['Dano  moral','Obrigação','dano moral','',42]},
        {localSubjects:JSON.stringify(['  obrigação ', 'Assunto local', '<rótulo>'])});
    assert.equal(combined.fields.assuntos,'Dano  moral; Obrigação; Assunto local; <rótulo>');
    const atField = await render(200,{estado:'disponivel',assuntos:['Remoto']},
        {localSubjects:JSON.stringify(['Local']),subjectsOnField:true});
    assert.equal(atField.fields.assuntos,'Remoto; Local');
    const localOnly = await render(200,{estado:'disponivel',assuntos:[]},
        {localSubjects:JSON.stringify(['Manual'])});
    assert.equal(localOnly.fields.assuntos,'Manual');
    const oldMarkup = await render(200,{estado:'disponivel',assuntos:[]});
    assert.equal(oldMarkup.fields.assuntos,'Assuntos locais');
    const badData = await render(200,{estado:'disponivel',assuntos:['Remoto']},{localSubjects:'não JSON'});
    assert.equal(badData.fields.assuntos,'Remoto');
    console.log('PASS: 25 cenários sintéticos de feedback e preservação da ficha DataJud.');
})().catch(error => { console.error(error); process.exitCode = 1; });
