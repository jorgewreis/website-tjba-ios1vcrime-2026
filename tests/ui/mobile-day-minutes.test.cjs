/* Optional Chromium regression. Renders the real views with read-only synthetic data. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname,'../..');
const views=['sala-audiencias-painel-dia','sala-audiencias-atas-list'];
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
        stub('helpers','RegistroMetadataHelper','static function viewerContextFromSession(){return [];} static function fromListRow(...$a){return [];}');
        stub('repositories','SalaAudienciasEstudanteInscricaoRepository','function listDetalhesByPautaIds($ids){return [];} function countInscritosPorPautaIds($ids){return [];}');
        const row='["id_pauta"=>"A","numero_processo"=>"0000000-00.2026.0.00.0000","data_audiencia"=>date("Y-m-d"),"hora_audiencia"=>"09:00:00","tipo_audiencia"=>"Instrução e julgamento com descrição extensa","status"=>"designada","localizacao"=>"audin","link_audiencia"=>"https://fixture.test/audin"]';
        stub('services','SalaAudienciasPautaService',`function findIdEmAndamentoNaData($d){return null;} function list(...$a){return ["rows"=>[${row}],"total"=>123];}`);
        stub('services','SalaAudienciasAtaService',`function listRealizadasParaUpload(...$a){
            $pending=${row}; $expired=$pending; $expired["id_pauta"]="B"; $expired["id_ata"]="expired";
            return ["rows"=>[$pending,$expired],"total"=>123]; }`);
        put('app/helpers/Conection.php',`<?php class Conection {static function getInstance(){return new self;} function getConnection(){
            $db=new PDO("sqlite::memory:"); $db->exec("CREATE TABLE usuarios (id_usuario TEXT, nome TEXT, sobrenome TEXT, cadastro_ativo INT); INSERT INTO usuarios VALUES ('S','Pessoa','Sintética',1)"); return $db;
        }}`);
        put('fixture.php',`<?php $_GET=['id'=>'A','id_parte'=>'P']; $_SESSION=['id_usuario'=>'S']; $_SERVER['REQUEST_METHOD']='GET';
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
                if(fixture) return route.fulfill({contentType:'text/html; charset=utf-8',body:process.env.MEASURE_BASELINE?fixture.replace(/page-internal page-operational-list page-hearing-(day|minutes)/,'page-internal'):fixture});
                if(process.env.MEASURE_BASELINE && u.pathname.endsWith('/day-minutes-mobile.css')) return route.fulfill({body:'',contentType:'text/css'});
                const f=path.join(root,'public',u.pathname);
                if(u.pathname.startsWith('/assets/')&&fs.existsSync(f)) return route.fulfill({path:f});
                return route.fulfill({status:404});
            });
            for(const f of views) {
                await page.goto('http://fixture.test/'+f);
                const m=await page.locator('.card-page').evaluate(card=>{
                    const box=card.getBoundingClientRect();
                    const xs=[...card.querySelectorAll('.btn,.pagination-controls button,.pagination-controls a,.painel-dia-actions .btn-editar')].flatMap(el=>{
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
                if(f.endsWith('atas-list')) {
                    const upload=page.locator('.btn-upload-ata');
                    assert.equal(await upload.count(),1);
                    assert.equal(await page.locator('[data-pauta="B"] .btn-upload-ata').count(),0);
                    await upload.focus();
                    assert.equal(await upload.evaluate(el=>document.activeElement===el),true);
                    const file=page.locator('input.ata-file-input');
                    assert.ok(await file.evaluate(el=>el.getBoundingClientRect().width<=1));
                    if(width===320) {
                        const chooser=page.waitForEvent('filechooser');
                        await upload.click();
                        await chooser; // Never choose or send a real file.
                    }
                } else {
                    const start=page.locator('[data-audiencia-iniciar]');
                    assert.ok(await start.isEnabled());
                    assert.ok(await page.locator('[data-audiencia-encerrar]').isDisabled());
                    await start.focus();
                    assert.equal(await start.evaluate(el=>document.activeElement===el),true);
                    assert.ok(await page.locator('a[href="https://fixture.test/audin"]').isVisible());
                    assert.ok(await page.locator('.timeline-day-ruler-track').isVisible());
                }
            }
            await page.close();
        }
        console.log('Two day/minutes views fit 320/375/390/430/768/1365 px.');
    } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exitCode=1;});
