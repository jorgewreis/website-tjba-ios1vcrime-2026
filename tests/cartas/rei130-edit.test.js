const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
class Element { constructor(value='') { this.value=value; this.disabled=false; this.handlers={}; } addEventListener(e,f) { (this.handlers[e] ||= []).push(f); } emit(e) { for (const f of this.handlers[e] || []) f({preventDefault(){}}); } }
const els = {'salvar-btn':new Element(), 'filter-cidade':new Element('Ilhéus'), 'filter-estado':new Element('BA'), 'filter-tipo':new Element('Diligência'), 'filter-prazo':new Element('0'), 'hidden-comarca-id':new Element('a'), 'comarca-lookup-status':new Element(), 'comarca-lookup-retry':new Element()};
const requests=[];
const fetch=(url,opts)=>new Promise((resolve,reject)=>requests.push({url,opts,resolve,reject}));
const win={App:{},document:{getElementById:(id)=>els[id] || null},location:{},sessionStorage:{},setTimeout};
vm.runInNewContext(fs.readFileSync('public/assets/js/shared/cartorio-cartas-recebidas-edit.js','utf8'),{window:win,fetch,Promise,Date,JSON,encodeURIComponent});
win.App.CartorioCartasRecebidasEdit.initialize({cartaId:'1',initial:{finalidade:'Diligência',prazoDias:'0',cidade:'Ilhéus',estado:'BA'},endpoints:{lookupCidade:'/lookup?cidade='}});
const tick=()=>new Promise(r=>setImmediate(r));
(async()=>{
  els['filter-cidade'].value='Cidade A'; els['filter-cidade'].emit('input'); assert.equal(els['hidden-comarca-id'].value,''); assert.equal(els['salvar-btn'].disabled,true);
  els['filter-cidade'].emit('blur'); assert.match(requests[0].url,/cidade=Cidade%20A&uf=BA/);
  els['filter-cidade'].value='Cidade B'; els['filter-cidade'].emit('input'); els['filter-cidade'].emit('blur');
  requests[1].resolve({ok:true,json:()=>Promise.resolve({found:true,id:'b',uf:'BA'})}); await tick();
  requests[0].resolve({ok:true,json:()=>Promise.resolve({found:true,id:'a',uf:'BA'})}); await tick();
  assert.equal(els['hidden-comarca-id'].value,'b'); assert.equal(els['salvar-btn'].disabled,false);
  els['filter-estado'].value='SP'; els['filter-estado'].emit('change'); assert.equal(els['hidden-comarca-id'].value,''); assert.equal(els['salvar-btn'].disabled,true);
  requests[2].resolve({ok:true,json:()=>Promise.resolve({found:false})}); await tick(); assert.equal(els['salvar-btn'].disabled,true); assert.match(els['comarca-lookup-status'].textContent,/não encontrada/); assert.equal(els['comarca-lookup-retry'].hidden,false);
  els['comarca-lookup-retry'].emit('click'); requests[3].reject(new Error('rede')); await tick(); assert.match(els['comarca-lookup-status'].textContent,/não foi possível/i); assert.equal(els['comarca-lookup-retry'].hidden,false);
  els['comarca-lookup-retry'].emit('click'); requests[4].resolve({ok:true,json:()=>Promise.resolve({found:true,id:'sp',uf:'SP'})}); await tick(); assert.equal(els['hidden-comarca-id'].value,'sp'); assert.equal(els['comarca-lookup-retry'].hidden,true);
  els['filter-cidade'].value='Cidade Agregada'; els['filter-cidade'].emit('input'); els['filter-cidade'].emit('blur');
  assert.match(requests[5].url,/resolver_sede=1/);
  requests[5].resolve({ok:true,json:()=>Promise.resolve({found:true,id:'sede',nome:'Comarca Sede',uf:'SP',status_cidade:'Ativa'})}); await tick();
  assert.equal(els['filter-cidade'].value,'Comarca Sede'); assert.equal(els['hidden-comarca-id'].value,'sede'); assert.equal(els['salvar-btn'].disabled,false);
  console.log('OK interface simulada: UF, inválida, respostas fora de ordem');
})().catch(e=>{console.error(e);process.exitCode=1});
