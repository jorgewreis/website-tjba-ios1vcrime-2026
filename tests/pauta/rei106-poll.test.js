const fs = require('fs'), vm = require('vm'), assert = require('assert');
const source = fs.readFileSync('app/views/internal/sala-audiencias-painel-estudantes.php', 'utf8');
const start = source.indexOf('        var statusRequestInFlight = false;');
const end = source.indexOf('        var latestItemsById = buildCurrentItemsFromDom();', start);
assert(start >= 0 && end > start);
let replies = [], removed = [], focused = '', updates = 0;
const makeSection = name => ({name, empty:{hidden:true}, rows:[], querySelector(q){
 if (q === 'h2') return {focus(){focused=name}};
 if (q === '.js-painel-empty') return this.empty;
 return this.rows.some(r=>!r.deleted) ? {} : null;
}});
const hoje=makeSection('hoje'), minhas=makeSection('minhas');
function makeRow(id, section) { const row={deleted:false,getAttribute:()=>id,contains:()=>id==='b' && section===minhas,closest:()=>section,remove(){this.deleted=true;removed.push(section.name+':'+id)}}; section.rows.push(row); return row; }
const rows=[makeRow('a',hoje),makeRow('b',hoje),makeRow('b',minhas)];
const ctx = {collectPautas: () => [...new Set(rows.filter(r=>!r.deleted).map(r=>r.getAttribute()))], latestItemsById: {a:{},b:{}}, applyUpdates: () => updates++, fetch: () => Promise.resolve(replies.shift()), document:{activeElement:{}, querySelectorAll:()=>rows.filter(r=>!r.deleted), getElementById:(id)=>id==='card-audiencias-hoje-estudante'?hoje:id==='card-minhas-inscricoes-estudante'?minhas:null}, Set, Object, Array, String, Promise};
vm.createContext(ctx); vm.runInContext(source.slice(start,end),ctx);
const response = (body,ok=true) => ({ok,json:()=>Promise.resolve(body)});
async function run(body,ok=true) { replies.push(response(body,ok)); ctx.refreshStatusAndTime(); await new Promise(resolve=>setImmediate(resolve)); }
(async()=>{
 await run({success:true,items:{a:{id_pauta:'a'}},completo:true,consultados:['a']}); assert.deepStrictEqual(removed,[]);
 await run({success:false},false); assert.deepStrictEqual(removed,[]);
 await run({success:true,items:{a:null},completo:true,consultados:['a','b']}); assert.deepStrictEqual(removed,[]);
 await run({success:true,items:{a:{id_pauta:'a'},b:{id_pauta:'b'}},completo:true,consultados:['a','b']}); assert.deepStrictEqual(removed,[]); assert.strictEqual(updates,1);
 await run({success:true,items:{a:{id_pauta:'a'}},completo:true,consultados:['a','b']}); assert.deepStrictEqual(removed,['hoje:b','minhas:b']); assert.strictEqual(focused,'minhas'); assert.strictEqual(hoje.empty.hidden,true); assert.strictEqual(minhas.empty.hidden,false); assert(!('b' in ctx.latestItemsById));
 await run({success:true,items:{},completo:true,consultados:['a']}); assert.deepStrictEqual(removed,['hoje:b','minhas:b','hoje:a']); assert.strictEqual(hoje.empty.hidden,false); assert.strictEqual(minhas.empty.hidden,false); assert.strictEqual(updates,3);
 console.log('REI-106 polling: OK');
})().catch(e=>{console.error(e);process.exitCode=1});
