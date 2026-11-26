const fs=require('node:fs');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const path=require('node:path');
const root=path.resolve(__dirname,'../..');
const read=p=>fs.readFileSync(path.join(root,p),'utf8');
const views='cartorio-cartas-expedidas-create cartorio-cartas-recebidas-create cartorio-cejusc-create cartorio-editais-create cartorio-guias-create gabinete-monitoramento-eletronico-create gabinete-oficios-list cartorio-oficios-list sala-audiencias-pauta-create'.split(' ');
for(const v of views) {
    const html=read('app/views/internal/'+v+'.php');
    assert.doesNotMatch(html,/<(?:label|select)\b[^>]*(?:for|id)="(?:audiencia-classe|filter-classe)"|id="processo-ap-info"/,v);
    for(const tag of html.match(/<input\b[^>]*\bid="(?:audiencia-classe|filter-classe)"[^>]*>/g)||[]) {
        assert.match(tag,/type="hidden"/,v);
        assert.match(tag,/data-datajud-silent-loading="true"/,v);
    }
}
const events={};
const attrs=new Map([['data-datajud-silent-loading','true']]);
const classes=new Set();
const classe={value:'',placeholder:'',style:{removeProperty(){}},getAttribute:k=>attrs.get(k),setAttribute:(k,v)=>attrs.set(k,v),removeAttribute:k=>attrs.delete(k),classList:{add:x=>classes.add(x),remove:x=>classes.delete(x)}};
const processo={id:'filter-processo'};
const app={document:{querySelectorAll:()=>[]},dispatchEvent:e=>{events[e.type]=e.detail;}};
const ctx=vm.createContext({window:app,document:app.document,CustomEvent:class{constructor(type,{detail}){this.type=type;this.detail=detail;}},setTimeout:()=>1,clearTimeout:()=>{}});
vm.runInContext(read('public/assets/js/modules/datajud/datajud-cycle-helper.js'),ctx);
const code={value:''}, description={value:''};
const dom={classeInput:classe,processoInput:processo,classeCodigoField:code,classeDescricaoField:description};
let session=app.DataJudCycle.startLoading(dom);
assert.equal(classe.placeholder,'');
assert.equal(attrs.has('aria-busy'),false);
assert.equal(classes.has('status-warning'),false);
app.DataJudCycle.finishSuccess(session,dom,{codigo:'355',descricao:'Classe sintética'});
assert.equal(code.value,'355');
assert.equal(description.value,'Classe sintética');
assert.equal(events.datajudClasseResolved.success,true);
session=app.DataJudCycle.startLoading(dom);
app.DataJudCycle.finishFailure(session,dom,'http',{});
assert.equal(code.value,'');
assert.equal(events.datajudClasseResolved.success,false);
const ficha=read('app/views/internal/consulta-processos-edit.php');
assert.doesNotMatch(ficha,/id="process-responsible-sector-reason"/);
assert.match(ficha,/id="proc-datajud-sync-status"/);

(async()=>{
    const controls={};
    for(const id of ['form','select','submit','status','current']) controls['process-responsible-sector-'+id]={};
    const el=id=>controls['process-responsible-sector-'+id];
    let submit,payload;
    Object.assign(el('form'),{dataset:{processId:'P'},addEventListener:(_,fn)=>{submit=fn;}});
    Object.assign(el('select'),{value:'C',selectedIndex:0,options:[{textContent:'Cartório'}]});
    vm.runInNewContext(read('public/assets/js/shared/processo-setor-responsavel.js'),{
        document:{getElementById:id=>controls[id]||null},
        fetch:async(_,req)=>{payload=JSON.parse(req.body);return {ok:true,json:async()=>({success:true,sector_id:'C',changed:true})};}
    });
    await submit({preventDefault(){}});
    assert.deepEqual(payload,{processo_id:'P',setor_id:'C'});
    assert.match(el('status').textContent,/auditada/);
    console.log('PASS cadastros silenciosos, enriquecimento em segundo plano e setor sem justificativa');
})().catch(e=>{console.error(e);process.exitCode=1;});
