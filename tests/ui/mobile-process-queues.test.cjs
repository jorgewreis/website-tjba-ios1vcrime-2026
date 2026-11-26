/* Optional Chromium regression. Renders the real views with read-only synthetic data. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname,'../..');
const views=['consulta-processos-list','cartorio-guias-list'];
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

        for (const f of fs.readdirSync(path.join(root,'app/views/internal/partials'))) if(f.endsWith('.php')) copy('app/views/internal/partials/'+f);
        stub('helpers','UsuarioPerfisAutorizacao','static function perfisDoUsuario($id){return ["ADMIN"];}');

        stub('helpers','RegistroMetadataHelper','static function viewerContextFromSession(){return [];} static function fromListRow(...$a){return [];}');
        stub('access','AccessEvaluator','static function canAccessPage(...$a){return true;}');
        stub('helpers','ProcessoBlocoListagemHelper','static function appendBlocoData($db,$rows){return $rows;}');
        stub('helpers','CartorioAlocacaoChipHelper','static function ordenarAlocadosPorTipo($a){return $a;}');
        stub('repositories','GuiasSubtarefasRepository',Array.from({length:8},(_,i)=>'const TIPO_ORDEM_'+(i+1)+'="tipo_'+(i+1)+'";').join(' '));
        const row='["id"=>"P","numero_processo"=>"0000000-00.2026.0.00.0000","bloco_processo_classe_texto"=>"Classe sintética de teste","bloco_processo_assunto_texto"=>"Descrição sintética do assunto","bloco_processo_abrir_href"=>"/fixture-edit"]';
        stub('services','ConsultaProcessosService',`function list(...$a){return ["rows"=>[${row}],"total"=>123];}`);
        stub('services','GuiasService',`function getStatusCounts($f){return [];} function getTipoCounts($f){return [];}
            function listGuias(...$a){$row=${row}; $row+=["IdGuia"=>"G","Tipo"=>"Guia de Execução Definitiva","Denunciado"=>"Pessoa sintética com nome composto","Status"=>"pendente","Subtarefas"=>[["id"=>"S","tipo"=>"tipo_1","ordem"=>1,"status"=>"pendente"]]]; $row["SubtarefasAll"]=$row["Subtarefas"]; return ["rows"=>[$row],"total"=>123];}`);
        put('app/helpers/Conection.php',`<?php class Conection {static function getInstance(){return new self;} function getConnection(){
            $db=new PDO("sqlite::memory:"); $db->exec("CREATE TABLE usuarios (id_usuario TEXT, nome TEXT, sobrenome TEXT, cadastro_ativo INT); INSERT INTO usuarios VALUES ('S','Pessoa','Sintética',1)"); return $db;
        }}`);
        put('fixture.php',`<?php $_GET=['numero'=>'0000000-00.2026.0.00.0000']; $_SESSION=['id_usuario'=>'S']; $_SERVER['REQUEST_METHOD']='GET';
        require_once __DIR__.'/app/helpers/Conection.php';
        require __DIR__.'/app/views/internal/'. $argv[1] .'.php';`);
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
                if(fixture) return route.fulfill({contentType:'text/html; charset=utf-8',body:process.env.MEASURE_BASELINE?fixture.replace('page-internal page-operational-list page-process-queue-list','page-internal'):fixture});
                if(process.env.MEASURE_BASELINE && u.pathname.endsWith('/process-queue-mobile.css')) return route.fulfill({body:'',contentType:'text/css'});
                const f=path.join(root,'public',u.pathname);
                if(u.pathname.startsWith('/assets/')&&fs.existsSync(f)) return route.fulfill({path:f});
                return route.fulfill({status:404});
            });
            for(const f of views) {
                await page.goto('http://fixture.test/'+f);
                const m=await page.locator('.card-page').evaluate(card=>{
                    const box=card.getBoundingClientRect();
                    const xs=[...card.querySelectorAll('input:not([type="hidden"],[type="checkbox"],[type="radio"]),select,textarea,.btn,.pagination-controls button,.button-table')].flatMap(el=>{
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
                if(f==='cartorio-guias-list') {
                    await page.locator('#filter-status').focus();
                    assert.equal(await page.locator('#filter-status').evaluate(el=>document.activeElement===el),true);
                    if(width===320) {
                        await page.locator('.button-table').first().click();
                        const expanded=page.locator('#subtask-G');
                        assert.equal(await expanded.isVisible(),true);
                        const field=expanded.locator('.subtask-dias-multa');
                        await field.fill('23');
                        assert.equal(await field.inputValue(),'23');
                        const box=await field.boundingBox();
                        assert.ok(box&&box.height>=44&&box.x+box.width<=308);
                    }
                } else {
                    assert.equal(await page.locator('[data-processo-id="P"]').count(),1);
                    await page.locator('#filter-parte').focus();
                    assert.equal(await page.locator('#filter-parte').evaluate(el=>document.activeElement===el),true);
                }
            }
            await page.close();
        }
        console.log('Two process/queue views fit 320/375/390/430/768/1365 px.');
    } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exitCode=1;});
