const fs=require('fs'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync('app/views/internal/sala-audiencias-painel-estudantes.php','utf8');
const ns=source.indexOf('        function normalizePollingItem(id, item) {');
const ne=source.indexOf('        function applyUpdates(items) {',ns);
const start=source.indexOf('        function validPollingItem(id, item) {');
const end=source.indexOf('        var latestItemsById = buildCurrentItemsFromDom();',start);
assert(ns>=0&&ne>ns&&start>ne&&end>start);
let replies=[],reloads=0,focused='',timerCallback=null,abortCount=0,delay=0;
const section=name=>({name,rows:[],empty:{hidden:true},querySelector(q){if(q==='h2')return{focus(){focused=name;}};if(q==='.js-painel-empty')return this.empty;return this.rows.some(r=>!r.deleted)?{}:null;}});
const hoje=section('hoje'),minhas=section('minhas');
function makeRow(id,sec){const r={id,deleted:false,blocked:false,html:'old sensitive',getAttribute:k=>k==='data-indisponivel'?(r.blocked?'1':null):id,contains:()=>id==='b'&&sec===minhas,closest:()=>sec,remove(){r.deleted=true;}};sec.rows.push(r);return r;}
const rows=[makeRow('a',hoje),makeRow('b',hoje),makeRow('b',minhas)];
const ctx={collectPautas:()=>[...new Set(rows.filter(r=>!r.deleted).map(r=>r.id))],latestItemsById:{a:{status:'secret'},b:{status:'secret'}},
 applyUpdates:items=>{rows.filter(r=>!r.deleted).forEach(r=>{if(items[r.id]){r.blocked=!!items[r.id].indisponivel;r.html=r.blocked?'Audiência indisponível.':'new';}});},
 fetch:()=>{const reply=replies.shift();return reply instanceof Error?Promise.reject(reply):Promise.resolve(reply);},
 AbortController:class{constructor(){this.signal={};}abort(){abortCount++;}},
 setTimeout:(fn,ms)=>{timerCallback=fn;delay=ms;return 1;},clearTimeout:()=>{timerCallback=null;},
 window:{location:{reload(){reloads++;}}},document:{activeElement:{},querySelectorAll:q=>rows.filter(r=>!r.deleted&&(!q.includes('[data-indisponivel')||r.blocked)),getElementById:id=>id==='card-audiencias-hoje-estudante'?hoje:id==='card-minhas-inscricoes-estudante'?minhas:null},Set,Object,Array,String,Promise,Number};
vm.createContext(ctx);vm.runInContext(source.slice(ns,ne)+source.slice(start,end),ctx);
const full=id=>({id_pauta:id,status:'designada',data_audiencia:'2030-01-01',hora_audiencia:'12:00',data_hora_inicio_efetivo:'',data_hora_fim_efetivo:'',duracao_estimada_minutos:30,total_inscritos_pauta:5,ja_inscrito:true,vagas_esgotadas:true,pode_inscrever:false});
const response=(body,ok=true)=>({ok,json:()=>Promise.resolve(body)});
const snapshot=items=>({success:true,completo:true,consultados:['a','b'],items});
async function flush(){await new Promise(r=>setImmediate(r));}
async function run(reply){replies.push(reply);ctx.refreshStatusAndTime();await flush();}
function redacted(){assert(rows.filter(r=>!r.deleted).every(r=>r.blocked&&r.html==='Audiência indisponível.'));for(const id of ctx.collectPautas())assert.deepStrictEqual(Object.keys(ctx.latestItemsById[id]).sort(),['id_pauta','indisponivel']);}
function reset(){rows.forEach(r=>{r.blocked=false;});}
(async()=>{
 await run(response(snapshot({a:full('a'),b:full('b')})));assert(rows.every(r=>r.html==='new'));
 for(const bad of [-1,1.5,null,'5']){await run(response(snapshot({a:{...full('a'),total_inscritos_pauta:bad}})));redacted();assert(rows.every(r=>!r.deleted));reset();}
 const incomplete={...full('a')};delete incomplete.total_inscritos_pauta;
 await run(response(snapshot({a:incomplete})));redacted();assert(rows.every(r=>!r.deleted));
 await run(response(snapshot({a:full('a'),b:full('b')})));assert.strictEqual(reloads,1);reset();
 await run(response({success:false},false));redacted();reset();
 await run({ok:true,json:()=>Promise.reject(new Error('invalid JSON'))});redacted();reset();
 await run(new Error('network'));redacted();reset();
 await run(response(snapshot({a:{...full('a'),processo_sigiloso:true},b:full('b')})));assert(rows[0].blocked);reset();
 let resolveLate;const late=new Promise(resolve=>{resolveLate=resolve;});replies.push(late);ctx.refreshStatusAndTime();await flush();assert.strictEqual(delay,8000);timerCallback();await flush();redacted();assert.strictEqual(abortCount,1);
 resolveLate(response(snapshot({a:full('a'),b:full('b')})));await flush();redacted();assert.strictEqual(reloads,1);
 await run(response(snapshot({a:full('a'),b:full('b')})));assert.strictEqual(reloads,2);reset();
 await run(response(snapshot({a:full('a')})));assert(rows[1].deleted&&rows[2].deleted&&!rows[0].deleted);assert.strictEqual(focused,'minhas');assert.strictEqual(hoje.empty.hidden,true);assert.strictEqual(minhas.empty.hidden,false);assert(!('b' in ctx.latestItemsById));
 const onlyA={success:true,completo:true,consultados:['a'],items:{}};await run(response(onlyA));assert(rows[0].deleted);assert.strictEqual(hoje.empty.hidden,false);
 console.log('PASS polling: full absent removes, partial/failure redacts, timeout ignores late response');
})().catch(e=>{console.error(e);process.exitCode=1});
