const fs = require('fs'), vm = require('vm'), assert = require('assert');
const events = {}, notifications = []; let response = null, reloads = 0;
const element = (id, value = '') => ({id,value,checked:false,disabled:false,handlers:{},focus(){this.focused=true},addEventListener(e,fn){this.handlers[e]=fn},getAttribute(){return id}});
const elements = {}; for (const [id,v] of Object.entries({'inst-id':'i1','nome':'Nome','nome_fantasia':'','cnpj':'','observacoes':'entrada preservada','salvar-btn':'','add-tel-btn':'','new-tel-numero':'123','new-tel-tipo':'','new-tel-ramal':'','new-tel-principal':'','add-setor-btn':'','new-setor-nome':'Setor','new-setor-sigla':''})) elements[id]=element(id,v);
const deletes = {'.btn-del-tel':[element('t1')],'.btn-del-setor':[element('s1')]};
const common = {notify:(...args)=>notifications.push(args),postJson:()=>response,queuePostReloadToast:()=>{}};
const win={App:{RecordEditCommon:common},document:{getElementById:id=>elements[id]||null,querySelectorAll:s=>deletes[s]||[]},location:{reload(){reloads++}}};
vm.runInNewContext(fs.readFileSync('public/assets/js/shared/instituicoes-publicas-edit.js','utf8'),{window:win}); win.App.InstituicoesPublicasEdit.initialize({});
const click=async el=>{el.handlers.click.call(el); await new Promise(resolve=>setImmediate(resolve));};
(async()=>{
 elements.nome.value=' '; await click(elements['salvar-btn']); assert.match(notifications.pop()[2],/nome/i); assert.equal(elements.nome.focused,true);
 elements.nome.value='Nome'; response=Promise.resolve({ok:false,data:{error:'Instituição não encontrada.'}}); await click(elements['salvar-btn']); assert.match(notifications.pop()[2],/não encontrada/); assert.equal(elements.observacoes.value,'entrada preservada'); assert.equal(elements['salvar-btn'].disabled,false);
 response=Promise.reject(new Error('offline')); await click(elements['salvar-btn']); assert.match(notifications.pop()[2],/conexão/i); assert.equal(elements['salvar-btn'].disabled,false);
 response=Promise.resolve({ok:true,data:{success:true}}); await click(elements['salvar-btn']); assert.equal(notifications.pop()[0],'success');
 for(const el of [elements['add-tel-btn'],elements['add-setor-btn'],deletes['.btn-del-tel'][0],deletes['.btn-del-setor'][0]]) {response=Promise.reject(new Error('offline')); await click(el); assert.match(notifications.pop()[2],/conexão/i); response=Promise.resolve({ok:false,data:{error:'Falha específica'}}); await click(el); assert.equal(notifications.pop()[2],'Falha específica');}
 assert.equal(reloads,0); assert.equal(elements['new-tel-numero'].value,'123'); assert.equal(elements['new-setor-nome'].value,'Setor'); console.log('REI-149 UI tests passed');
})().catch(e=>{console.error(e);process.exitCode=1});
