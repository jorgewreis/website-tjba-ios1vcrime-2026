/* Optional Chromium regression. Renders the real views with read-only synthetic data. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname,'../..');
const views=['cartorio-editais-create','cartorio-editais-edit','cartorio-editais-list'];
function render() {
    const dir=fs.mkdtempSync(path.join(os.tmpdir(),'vara-doc-layout-'));
    function put(file,data) {
        const dst=path.join(dir,file);
        fs.mkdirSync(path.dirname(dst),{recursive:true});
        fs.writeFileSync(dst,data);
    }
    const copy=f=>put(f,fs.readFileSync(path.join(root,f)));
    const stub=(folder,name,body='')=>put(`app/${folder}/${name}.php`,`<?php class ${name} {function __construct(...$a){} ${body}}`);
    try {
        for(const f of fs.readdirSync(path.join(root,'app/views/internal'))) if(f.startsWith('_')&&f.endsWith('.php')) copy('app/views/internal/'+f);
        for(const f of views) copy('app/views/internal/'+f+'.php');
        copy('app/views/internal/partials/editais-v2-process.php');
        copy('app/views/internal/partials/processo-v2.php');
        copy('app/helpers/EditaisUiCopy.php');
        stub('helpers','UsuarioPerfisAutorizacao','static function perfisDoUsuario($id){return ["ADMIN"];}');
        stub('helpers','ProcessoBlocoListagemHelper','static function appendBlocoData($db,$rows){return $rows;}');
        stub('helpers','RegistroMetadataHelper','static function viewerContextFromSession(){return [];} static function fromListRow(...$a){return ["show_edit"=>true,"edit_href"=>"/fixture-edit"]; }');
        stub('access','AccessEvaluator','static function canAccessPage(...$args){return true;}');
        stub('repositories','ProcessosRepository');
        stub('repositories','ClassesRepository');
        stub('repositories','EditaisSubtarefasRepository',`
            const TIPO_PUBLICACAO="certificacao_publicacao", TIPO_DECURSO="certificacao_decurso_prazo";
            const ORDEM_PUBLICACAO=1, ORDEM_DECURSO=2, ORDENS_ATIVAS=[1,2];
            static function isOrdemAtiva($o){return in_array($o,[1,2],true);}
            static function isTipoAtivo($t){return in_array($t,[self::TIPO_PUBLICACAO,self::TIPO_DECURSO],true);}
            function findByEditalId($id){return [];}`);
        stub('services','EditaisService',`
            function find($id){return ["numero_processo"=>"0000000-00.2026.0.00.0000","classe_nome"=>"Classe sintética","status"=>"pendente","tipo_edital"=>"Citação","prazo_dias"=>15];}
            function getStatusCounts($a){return [];}
            function listEditais(...$a){return ["total"=>123,"rows"=>[["IdEdital"=>"E","numero_processo"=>"0000000-00.2026.0.00.0000","tipo_edital"=>"Citação","Status"=>"pendente","SubtarefasAll"=>[["id"=>"S","ordem"=>1,"tipo"=>"certificacao_publicacao","status"=>"pendente"]]]]];}`);
        put('app/helpers/CartorioTimelineHelper.php',`<?php function cartorioTimelineBuildViewData(...$a){return [
            'showProgress'=>true,'progressValue'=>1,'progressMax'=>4,'currentTaskTitle'=>'Aguardando conferência de documento da carta pela unidade responsável',
            'segments'=>array_fill(0,4,['class'=>'pendente','title'=>'Etapa sintética']),
            'cadastro'=>['minorText'=>'Carta cadastrada','dateLabel'=>'26/11/2026 às 10:00'],
            'rows'=>[['minorText'=>'Aguardando conferência','majorText'=>'Descrição sintética longa para avaliar o encaixe do histórico da carta','dateLabel'=>'26/11/2026 às 11:00']]
        ];}`);
        put('fixture.php',`<?php class Conection {static function getInstance(){return new self;} function getConnection(){
            $db=new PDO("sqlite::memory:"); $db->exec("CREATE TABLE usuarios (id_usuario TEXT, nome TEXT, sobrenome TEXT); INSERT INTO usuarios VALUES ('S','Pessoa','Sintética')"); return $db;
        }} $_GET=['id'=>'DOC']; $_SESSION=['id_usuario'=>'S']; $_SERVER['REQUEST_METHOD']='GET';
        require_once __DIR__.'/app/repositories/ProcessosRepository.php'; require_once __DIR__.'/app/repositories/ClassesRepository.php'; require __DIR__.'/app/views/internal/'. $argv[1] .'.php';`);
        return Object.fromEntries(views.map(f=>[f,execFileSync(process.env.PHP_BIN||'php',['-d','display_errors=stderr',path.join(dir,'fixture.php'),f],{encoding:'utf8'})]));
    } finally { fs.rmSync(dir,{recursive:true,force:true}); }
}
(async()=>{
    const html=render();
    const browser=await chromium.launch({executablePath:process.env.CHROMIUM_BIN||undefined,headless:true});
    try {
        for(const width of [320,375,390,430,768,1365]) {
            const page=await browser.newPage({viewport:{width,height:740}});
            page.setDefaultTimeout(5000);
            await page.route('**/*',route=>{
                const u=new URL(route.request().url());
                if(u.hostname!=='fixture.test') return route.abort();
                const fixture=html[u.pathname.slice(1)];
                if(fixture) return route.fulfill({contentType:'text/html; charset=utf-8',body:process.env.MEASURE_BASELINE?fixture.replace(/page-internal page-operational-(form|list)/,'page-internal'):fixture});
                if(process.env.MEASURE_BASELINE && /\/editais-(v2|rows)\.css$/.test(u.pathname)) return route.fulfill({contentType:'text/css',body:execFileSync('git',['show','HEAD:public'+u.pathname],{cwd:root})});
                const f=path.join(root,'public',u.pathname);
                if(u.pathname.startsWith('/assets/')&&fs.existsSync(f)) return route.fulfill({path:f});
                return route.fulfill({status:404});
            });
            for(const f of views) {
                await page.goto('http://fixture.test/'+f);
                const m=await page.locator('.card-page').evaluate(card=>{
                    const box=card.getBoundingClientRect();
                    const xs=[...card.querySelectorAll('input:not([type="hidden"],[type="radio"]),select,textarea,.btn,.subtask-date,.carta-row-action,.pagination-controls button')].flatMap(el=>{
                        const r=el.getBoundingClientRect();
                        return r.width>0&&r.height>0?[{id:el.id||el.className,l:r.left,r:r.right,h:r.height,control:!el.classList.contains('subtask-date')}]:[];
                    });
                    return {left:box.left,right:box.right,scroll:document.documentElement.scrollWidth,xs};
                });
                if(process.env.MEASURE_BASELINE){if(width===320)console.log(f,m.scroll,Math.round(Math.max(...m.xs.map(x=>x.r))));continue;}
                assert.ok(m.xs.length>=4,f+' synthetic view did not render controls');
                assert.ok(m.scroll<=width+1,f+' viewport overflow '+m.scroll+' at '+width);
                if(width<768) for(const x of m.xs) {
                    assert.ok(x.l>=m.left-1&&x.r<=m.right+1,f+' out of card '+JSON.stringify(x));
                    if(x.control) assert.ok(x.h>=43.9,f+' target too small '+x.id);
                }
                if(f.endsWith('list')) {
                    await page.locator('#filter-status').focus();
                    assert.equal(await page.locator('#filter-status').evaluate(el=>document.activeElement===el),true);
                    if(width===320) {
                        await page.locator('.edital-row-expand').click();
                        const expanded=page.locator('.editais-v2-subtasks').first();
                        assert.equal(await expanded.isVisible(),true);
                        const inside=await expanded.locator('input[type=date],button').evaluateAll(xs=>xs.filter(x=>x.getClientRects().length>0).every(x=>{ const b=x.getBoundingClientRect();return b.right<=310&&b.left>=10&&b.height>=44; }));
                        assert.equal(inside,true,'expanded actions stay inside the card');
                        await page.getByRole('button',{name:'Próxima página'}).click();
                        await page.waitForURL(/page_number=2/);
                    }
                } else if(f.endsWith('edit')) {
                    await page.locator('#edit-observacoes').fill('Observação sintética');
                    assert.equal(await page.locator('#edit-observacoes').inputValue(),'Observação sintética');
                }
            }
            await page.close();
        }
        console.log('Three editais views fit 320/375/390/430/768/1365 px.');
    } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exitCode=1;});
