const fs=require('fs'),vm=require('vm'),assert=require('assert/strict'),path=require('path');
// Deterministic synthetic DOM/fetch; no browser, server, credentials or real data.
// Default: checkout containing this test. Optional argument: another checkout for review.
const root=path.resolve(process.argv[2] || path.join(__dirname,'..'));
const files={direct:'app/views/internal/consulta-partes-create.php',process:'public/assets/js/shared/consulta-processos-edit.js',common:'public/assets/js/shared/record-edit-common.js',subject:'public/assets/js/shared/consulta-partes-edit.js'};
const read=n=>fs.readFileSync(path.join(root,files[n]),'utf8');
const direct=read('direct').match(/<script>\s*(\(function\(\) \{[\s\S]*?)<\/script>/)[1],processJs=read('process'),common=read('common'),subject=read('subject');
function fixture(flow){const els={},calls=[],resolvers=[],timers=[];let reloads=0;
 function el(id,value=''){const e={id,value,disabled:false,listeners:{},style:{},dataset:{},parentNode:{prepend(n){els[n.id]=n}},addEventListener(k,f){this.listeners[k]=f},setAttribute(k,v){this[k]=v},getAttribute(k){return this[k]},focus(){},children:[],appendChild(x){this.children.push(x)},querySelector(){return this.children.find(x=>x.dataset.parteId)||null}};e.options=e.children;e.selectedIndex=0;els[id]=e;return e}
 let button,del;if(flow==='direct'){button=el('cadastrar-btn');el('nome_completo','Pessoa Sintetica');el('sexo');el('data_nascimento');el('voltar-btn');el('add-documento-btn');el('add-telefone-btn')}
 else if(flow==='process'){button=el('btn-processo-parte-adicionar');let sel=el('processo-parte-nome-selecao');sel.value='__nova__';sel.children.push({value:'__nova__',dataset:{},textContent:''});el('processo-parte-nome-status');el('processo-parte-nome','Pessoa Sintetica');el('processo-parte-id');el('processo-parte-tipo','reu');el('processo-parte-polo','passivo')}
 else{button=el('add-doc-btn');del=el('del');del['data-id']='SYN-DOC';el('parte-id','SYN-P');el('new-doc-tipo','RG');el('new-doc-numero','SYN-123')}
 const doc={getElementById:id=>els[id]||null,querySelectorAll:s=>s==='.btn-del-doc'?[del]:[],querySelector:()=>null,createElement:()=>el('new')};
 const fetch=(url,opts)=>{calls.push({url,payload:opts.body?JSON.parse(opts.body):null});return new Promise((resolve,reject)=>resolvers.push({resolve,reject}))};
 const win={document:doc,location:{href:'',reload(){reloads++}},App:{},PROCESSO_ASSUNTOS_PROCESSO_ID:'SYN-P',setTimeout:(fn)=>{timers.push(fn);return timers.length},clearTimeout:()=>{}};const ctx={window:win,document:doc,fetch,console,setTimeout,clearTimeout};vm.createContext(ctx);
 if(flow==='direct')vm.runInContext(direct,ctx);else{vm.runInContext(common,ctx);vm.runInContext(flow==='process'?processJs:subject,ctx);win.App[flow==='process'?'ConsultaProcessosEdit':'ConsultaPartesEdit'].initialize({})}
 const click=(el=button)=>el.listeners.click.call(el);function respond(status,data,invalid=false){let p=resolvers.shift();assert.ok(p);p.resolve({ok:status<400,status,json:()=>invalid?Promise.reject(new SyntaxError('invalid')):Promise.resolve(data),text:()=>Promise.resolve(invalid?'{':JSON.stringify(data))})}
 function reject(){const p=resolvers.shift();assert.ok(p);p.reject(new Error('falha sintética'));}
 return{button,del,els,calls,click,respond,reject,timers,reloads:()=>reloads};}
const tick=()=>new Promise(setImmediate);
(async()=>{
 for(const flow of ['direct','process','document'])for(const mode of ['500','invalid']){let f=fixture(flow);f.click();f.click();assert.equal(f.calls.length,1);f.respond(500,{error:'erro'},mode==='invalid');await tick();assert.equal(f.button.disabled,true);f.click();assert.equal(f.calls.length,1);assert.ok(f.els[flow==='document'?'documento-resultado-incerto':'pessoa-resultado-incerto']);}
 for(const result of ['400','500','invalid','success']){let f=fixture('process');f.click();f.respond(201,{id:'SYN-NEW'});await tick();assert.equal(f.calls.length,2);assert.equal(f.calls[1].payload.parte_id,'SYN-NEW');
 f.respond(result==='success'?201:result==='400'?400:500,result==='success'?{id:'SYN-LINK'}:{error:'erro'},result==='invalid');await tick();if(result!=='success'){assert.equal(f.els['processo-parte-id'].value,'SYN-NEW');assert.equal(f.els['processo-parte-nome-status'].textContent,'Pessoa cadastrada; v\u00ednculo pendente');assert.equal(f.els['processo-parte-nome-selecao'].options[0].dataset.parteId,'SYN-NEW');}
 if(result==='400'){assert.equal(f.button.disabled,false);f.click();await tick();assert.equal(f.calls.length,3);assert.equal(f.calls[2].url,'/api/processos-partes/create.php');assert.equal(f.calls[2].payload.parte_id,'SYN-NEW')}
 else {assert.equal(f.button.disabled,true);f.click();assert.equal(f.calls.length,2);if(result==='success')assert.equal(f.reloads(),1);else assert.ok(f.els['pessoa-resultado-incerto']);}}
 for(const flow of ['direct','document']){let f=fixture(flow);f.click();f.respond(201,{id:'SYN-NEW'});await tick();f.click();assert.equal(f.calls.length,1);assert.equal(f.button.disabled,true);}
 {let f=fixture('process');f.els['processo-parte-nome'].listeners.input();f.timers.shift()();assert.equal(f.calls.length,1);f.click();assert.equal(f.calls.length,2);assert.equal(f.els['processo-parte-nome'].disabled,true);f.respond(200,{pessoas:[{id:'SYN-B',nome_completo:'Pessoa B'}]});await tick();assert.equal(f.els['processo-parte-nome'].value,'Pessoa Sintetica');assert.equal(f.els['processo-parte-id'].value,'');f.respond(201,{id:'SYN-A'});await tick();f.respond(400,{error:'recusa'});await tick();f.click();await tick();assert.equal(f.calls[3].payload.parte_id,'SYN-A');assert.equal(f.els['processo-parte-nome'].value,'Pessoa Sintetica');}
 {let f=fixture('process');f.click();f.respond(201,{id:'SYN-A'});await tick();f.respond(400,{error:'recusa'});await tick();assert.equal(f.els['processo-parte-id'].value,'SYN-A');assert.ok(f.els['processo-parte-nome-status'].textContent);assert.equal(f.els['processo-parte-nome'].disabled,false);f.els['processo-parte-nome'].value='Pessoa B';f.els['processo-parte-nome'].listeners.input();assert.equal(f.els['processo-parte-id'].value,'');assert.equal(f.els['processo-parte-nome-status'].textContent,'');assert.equal(f.calls.length,2);}
 // Testes independentes para JSON malformado em 200/400, transporte e exclusão.
 for(const flow of ['direct','process','document','delete']) for(const mode of ['invalid200','invalid400','lost','400','500']) {
   const f=fixture(flow==='delete'?'document':flow), target=flow==='delete'?f.del:f.button;
   f.click(target); f.click(target); assert.equal(f.calls.length,1);
   if(mode==='lost') f.reject(); else f.respond(mode==='invalid200'?200:(mode==='500'?500:400),{error:'recusa sintetica'},mode.startsWith('invalid'));
   await tick(); assert.equal(f.reloads(),0);
   if(mode==='400') {
     assert.equal(f.button.disabled,false); if(f.del) assert.equal(f.del.disabled,false);
     f.click(target); assert.equal(f.calls.length,2);
   } else {
     assert.equal(f.button.disabled,true); if(f.del) assert.equal(f.del.disabled,true);
     assert.ok(f.els[flow==='document'||flow==='delete'?'documento-resultado-incerto':'pessoa-resultado-incerto']);
     f.click(target); assert.equal(f.calls.length,1);
   }
 }
 // Uma nova seleção após a recusa substitui o ID preservado no retry.
 {const f=fixture('process'); f.click();f.respond(201,{id:'SYN-A'});await tick();f.respond(400,{error:'recusa'});await tick();
  const sel=f.els['processo-parte-nome-selecao'];sel.children.push({value:'SYN-B',dataset:{parteId:'SYN-B',nomeParte:'Pessoa B'},textContent:'Pessoa B'});sel.selectedIndex=1;sel.value='SYN-B';sel.listeners.change();
  assert.equal(f.els['processo-parte-id'].value,'SYN-B');assert.equal(f.els['processo-parte-nome'].value,'Pessoa B');f.click();await tick();assert.equal(f.calls.length,3);assert.equal(f.calls[2].payload.parte_id,'SYN-B'); }
 console.log('PASS 35 cenários DOM: eventos diretos, erro, reuso, vínculo incerto e navegação pendente.');
})().catch(e=>{console.error(e);process.exitCode=1});
