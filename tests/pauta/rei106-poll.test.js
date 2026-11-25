const fs = require('fs'), vm = require('vm'), assert = require('assert');
const source = fs.readFileSync('app/views/internal/sala-audiencias-painel-estudantes.php', 'utf8');
const start = source.indexOf('        var statusRequestInFlight = false;');
const end = source.indexOf('        var latestItemsById = buildCurrentItemsFromDom();', start);
assert(start >= 0 && end > start);
let replies = [], removed = [], focused = '', updates = 0, reloads = 0;
const enrollment = {style:{},getAttribute:()=> 'a',innerHTML:''}, enrollmentB = {style:{},getAttribute:()=> 'b',innerHTML:''};
const makeSection = name => ({name, empty:{hidden:true}, rows:[], querySelector(q){
 if (q === 'h2') return {focus(){focused=name}};
 if (q === '.js-painel-empty') return this.empty;
 return this.rows.some(r=>!r.deleted) ? {} : null;
}});
const hoje=makeSection('hoje'), minhas=makeSection('minhas');
function makeRow(id, section) { const row={deleted:false,blocked:false,getAttribute:k=>k==='data-indisponivel'?(row.blocked?'1':null):id,setAttribute:(k,v)=>{if(k==='data-indisponivel')row.blocked=v==='1';},querySelector:()=>({classList:{toggle(){}}}),contains:()=>id==='b' && section===minhas,closest:()=>section,remove(){this.deleted=true;removed.push(section.name+':'+id)}}; section.rows.push(row); return row; }
const rows=[makeRow('a',hoje),makeRow('b',hoje),makeRow('b',minhas)];
const ctx = {collectPautas: () => [...new Set(rows.filter(r=>!r.deleted).map(r=>r.getAttribute('data-pauta')))], latestItemsById: {a:{status:'designada',data_audiencia:'ANTERIOR'},b:{}}, fetch: () => Promise.resolve(replies.shift()), window:{location:{reload(){reloads++}}}, document:{activeElement:{}, querySelectorAll:q=>q==='[data-inscricao-ui]'?[enrollment,enrollmentB]:q==='[data-vagas-pauta]'?[]:q.includes('.js-time-block')?[]:q.includes('[data-status-label-pauta]')||q.includes('[data-status-pauta]')?[]:rows.filter(r=>!r.deleted && (!q.includes('[data-indisponivel')||r.blocked)), getElementById:(id)=>id==='card-audiencias-hoje-estudante'?hoje:id==='card-minhas-inscricoes-estudante'?minhas:null}, enrollment, Set, Object, Array, String, Promise};
const helpersStart = source.indexOf('        function pad2(n)');
assert(helpersStart >= 0 && start > helpersStart);
vm.createContext(ctx); vm.runInContext(source.slice(helpersStart,start),ctx); vm.runInContext(source.slice(start,end),ctx);
const originalApplyUpdates = ctx.applyUpdates;
ctx.applyUpdates = items => { updates++; originalApplyUpdates(items); };
const response = (body,ok=true) => ({ok,json:()=>Promise.resolve(body)});
async function run(body,ok=true) { replies.push(response(body,ok)); ctx.refreshStatusAndTime(); await new Promise(resolve=>setImmediate(resolve)); }
(async()=>{
 await run({success:true,items:{a:{id_pauta:'a'}},completo:true,consultados:['a']}); assert.deepStrictEqual(removed,[]);
 await run({success:false},false); assert.deepStrictEqual(removed,[]);
 await run({success:true,items:{a:null},completo:true,consultados:['a','b']}); assert.deepStrictEqual(removed,[]);
 await run({success:true,items:{a:{id_pauta:'a'},b:{id_pauta:'b'}},completo:true,consultados:['a','b']}); assert.deepStrictEqual(removed,[]); assert.strictEqual(updates,1);
 await run({success:true,items:{a:{id_pauta:'a',indisponivel:true},b:{id_pauta:'b'}},completo:true,consultados:['a','b']}); assert.deepStrictEqual(Object.keys(ctx.latestItemsById.a).sort(),['id_pauta','indisponivel']);
 await run({success:true,items:{a:{id_pauta:'a',status:'designada'},b:{id_pauta:'b'}},completo:true,consultados:['a','b']}); assert.strictEqual(reloads,1); rows[0].blocked=false;
 await run({success:true,items:{a:{id_pauta:'a',status:'designada',ja_inscrito:true,vagas_esgotadas:true,total_inscritos_pauta:5,pode_inscrever:false},b:{id_pauta:'b',status:'designada',ja_inscrito:false,vagas_esgotadas:true,total_inscritos_pauta:5,pode_inscrever:false}},completo:true,consultados:['a','b']});
 assert.deepStrictEqual(removed,[]); assert.strictEqual(reloads,1); assert.strictEqual(ctx.latestItemsById.a.ja_inscrito,true); assert.strictEqual(ctx.latestItemsById.a.vagas_esgotadas,true); assert.strictEqual(ctx.latestItemsById.a.pode_inscrever,false); assert.strictEqual(updates,3); ctx.renderInscricaoUI(enrollment,ctx.latestItemsById.a); assert(enrollment.innerHTML.includes('Voce esta inscrito.'), enrollment.innerHTML); assert(!enrollment.innerHTML.includes('mais nenhuma'));
 await run({success:true,items:{a:{id_pauta:'a'}},completo:true,consultados:['a','b']}); assert.deepStrictEqual(removed,['hoje:b','minhas:b']); assert.strictEqual(focused,'minhas'); assert.strictEqual(hoje.empty.hidden,true); assert.strictEqual(minhas.empty.hidden,false); assert(!('b' in ctx.latestItemsById));
 await run({success:true,items:{},completo:true,consultados:['a']}); assert.deepStrictEqual(removed,['hoje:b','minhas:b','hoje:a']); assert.strictEqual(hoje.empty.hidden,false); assert.strictEqual(minhas.empty.hidden,false); assert.strictEqual(updates,5);
 console.log('REI-106 polling: OK');
})().catch(e=>{console.error(e);process.exitCode=1});
