const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function extract(source, name) {
  const start = source.indexOf('    function ' + name + '(');
  assert.ok(start >= 0);
  const open = source.indexOf('{', start); let depth = 0;
  for (let i = open; i < source.length; i++) {
    if (source[i] === '{') depth++;
    if (source[i] === '}' && --depth === 0) return source.slice(start, i + 1);
  }
  throw new Error('função incompleta');
}
async function flush() { for (let i=0;i<5;i++) await new Promise(resolve => setImmediate(resolve)); }
async function run(mode) {
 const create = mode === 'create';
 const file = `public/assets/js/modules/audiencias/pauta-${mode}.js`;
 const source = fs.readFileSync(file, 'utf8');
 const fn = create ? 'refreshHoraDisponiveisCreate' : 'refreshHoraEditDisponiveis';
 const counter = create ? 'horariosCreateRequest' : 'horariosEditRequest';
 const dateId = create ? 'audiencia-data' : 'audiencia-edit-data';
 const hourId = create ? 'audiencia-hora' : 'audiencia-edit-hora';
 const date = {value:''}, hour = {value:''}; const calls=[], fills=[], notices=[], extraFlags=[];
 const document = {getElementById(id) { return id === dateId ? date : id === hourId ? hour : null; }};
 const context = {document,window:{ PAUTA_HORA_FORA_GRADE:'08:13' },dataOriginalPauta:'2099-01-01',horariosEditUnresolved:false,encodeURIComponent,Promise,Array,String,notifyByKey(...a){notices.push(a)},fetch(url){return new Promise((resolve,reject)=>calls.push({url,resolve,reject}))}, readGradeFromWindow:()=>['grade'],readGradeEdit:()=>['grade'],buildUrlOccupiedSlots:d=>d,fillHoraSelectCreate(a){fills.push([...a]);hour.value=''},fillHoraSelectEdit(a,flag){fills.push([...a]);extraFlags.push(flag);hour.value=''}};
 vm.createContext(context);vm.runInContext(`var ${counter}=0, horarios${create ? 'Create' : 'Edit'}PendingDate='', horarios${create ? 'Create' : 'Edit'}PendingHour='';\n${extract(source,fn)}`,context);
 date.value='2099-01-01'; context[fn](); date.value='2099-01-02'; context[fn]();
 calls[1].resolve({ok:true,json:async()=>({available:['11:00']})}); await flush();
 calls[0].resolve({ok:true,json:async()=>({available:['09:00']})}); await flush();
 assert.deepEqual(fills.at(-1),['11:00'],`${mode}: resposta antiga não altera horários`);
 if (!create) assert.equal(extraFlags.at(-1),false,'horário excepcional não acompanha data nova');
 date.value='2099-01-03';context[fn](); date.value='2099-01-04';context[fn](); calls[3].resolve({ok:true,json:async()=>({available:['14:00']})}); await flush(); calls[2].reject(new Error('rede'));await flush();assert.deepEqual(fills.at(-1),['14:00']);assert.equal(notices.length,0,`${mode}: erro antigo ignorado`);
 date.value='2099-01-05';context[fn]();calls[4].reject(new Error('rede'));await flush();assert.deepEqual(fills.at(-1),[],`${mode}: falha atual não oferece grade não verificada`);assert.equal(notices.length,1);
 date.value='2099-01-06';context[fn]();date.value='';context[fn]();calls[5].resolve({ok:true,json:async()=>({available:['15:00']})});await flush();assert.deepEqual(fills.at(-1),['grade'],`${mode}: limpeza invalida pedido pendente`);
 if (!create) { assert.equal(extraFlags.at(-1),false); date.value='2099-01-01';context[fn]();calls[6].resolve({ok:true,json:async()=>({available:['10:00']})});await flush();assert.equal(extraFlags.at(-1),true,'retorno à data original permite hora excepcional'); }
}
function testDirtyDuringLookup() {
 const source=fs.readFileSync('public/assets/js/modules/audiencias/pauta-edit.js','utf8');
 const elements={'audiencia-edit-data':{value:'2099-01-01'},'audiencia-edit-hora':{value:'10:00'}};
 const context={document:{getElementById:id=>elements[id]||null},String,JSON,horariosEditUnresolved:false,horariosEditPendingDate:'2099-01-01',horariosEditPendingHour:'10:00'};
 vm.createContext(context);vm.runInContext(extract(source,'currentAudienciaSnapshot'),context);
 const baseline=context.currentAudienciaSnapshot();elements['audiencia-edit-hora'].value='';context.horariosEditUnresolved=true;
 assert.equal(context.currentAudienciaSnapshot(),baseline,'carregamento/falha não causa alerta de mudança falsa');
 elements['audiencia-edit-data'].value='2099-01-02';assert.notEqual(context.currentAudienciaSnapshot(),baseline,'mudança real de data continua detectada');
 elements['audiencia-edit-data'].value='2099-01-01';context.horariosEditUnresolved=false;assert.notEqual(context.currentAudienciaSnapshot(),baseline,'resultado final sem hora continua detectado');
}
async function testSavedDateUpdatesException() {
 const source=fs.readFileSync('public/assets/js/modules/audiencias/pauta-edit.js','utf8');
 const elements={'audiencia-edit-data':{value:'2099-01-02'},'audiencia-edit-hora':{value:'10:00'}};
 const context={document:{getElementById:id=>elements[id]||null},window:{PAUTA_HORA_FORA_GRADE:'08:13'},String,Promise,JSON,dataOriginalPauta:'2099-01-01',initialAudienciaSnapshot:'',resolvePautaEditId:()=> '1',syncHiddenPautaEditId(){},currentAudienciaSnapshot:()=> 'saved',notifyByKey(){},fetch:async()=>({ok:true,json:async()=>({success:true})})};
 vm.createContext(context);vm.runInContext(extract(source,'saveAudiencia'),context);
 assert.equal(await context.saveAudiencia(false),true);assert.equal(context.dataOriginalPauta,'2099-01-02');assert.equal(context.window.PAUTA_HORA_FORA_GRADE,'','salvar nova data remove exceção anterior');
 context.dataOriginalPauta='2099-01-01';context.window.PAUTA_HORA_FORA_GRADE='08:13';elements['audiencia-edit-data'].value='2099-01-01';elements['audiencia-edit-hora'].value='08:13';
 assert.equal(await context.saveAudiencia(false),true);assert.equal(context.dataOriginalPauta,'2099-01-01');assert.equal(context.window.PAUTA_HORA_FORA_GRADE,'08:13','salvar mesma data e hora preserva exceção');
 elements['audiencia-edit-data'].value='2099-01-03';elements['audiencia-edit-hora'].value='11:00';context.fetch=async()=>({ok:false,json:async()=>({error:'falhou'})});
 assert.equal(await context.saveAudiencia(false),false);assert.equal(context.dataOriginalPauta,'2099-01-01');assert.equal(context.window.PAUTA_HORA_FORA_GRADE,'08:13','falha preserva referência anterior');
}
(async()=>{await run('create');await run('edit');testDirtyDuringLookup();await testSavedDateUpdatesException();console.log('PASS horários obsoletos: criação e edição (fetch/DOM simulados)')})().catch(e=>{console.error(e);process.exitCode=1});
