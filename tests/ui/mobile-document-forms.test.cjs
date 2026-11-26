/* Optional Chromium regression. Renders the real views with read-only synthetic data. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname,'../..');
const create = ['cartorio-cartas-expedidas-create','cartorio-cartas-recebidas-create'];
const edit = ['cartorio-cartas-expedidas-edit','cartorio-cartas-recebidas-edit','cartorio-oficios-edit','gabinete-oficios-edit'];
const views = [...create,...edit];
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
        stub('helpers','UsuarioPerfisAutorizacao','static function perfisDoUsuario($id){return ["ADMIN"];}');
        stub('helpers','CartorioAlocacaoChipHelper','static function ordenarAlocadosPorTipo($a){return $a;}');
        stub('repositories','TribunaisRepository');
        stub('repositories','ProcessosRepository','function findById($id){return ["numero_processo"=>"0000000-00.2026.0.00.0000","classe_id"=>"C"];}');
        stub('repositories','ClassesRepository','function findById($id){return ["nome"=>"Classe sintética com nome extenso","codigo"=>"100"];}');
        for(const name of ['CartasExpedidas','CartasRecebidas']) {
            stub('services',name+'Service','function find($id){return ["processo_origem_id"=>"P","finalidade"=>"Intimação para Audiência","cidade_destino"=>"Comarca Sintética","uf_destino"=>"BA","prazo_dias"=>30,"status"=>"em_andamento"]; }');
            stub('repositories',name+'SubtarefasRepository','function findByCartaId($id){return [];}');
        }
        for(const name of ['OficiosCartorio','OficiosGabinete']) {
            stub('services',name+'Service','function find($id){return ["id"=>"O","numero_sequencial"=>12,"ano"=>2026,"numero_processo"=>"0000000-00.2026.0.00.0000","classe_nome"=>"Classe sintética","tipo_oficio"=>"Comunicação de teste","status"=>"pendente","forma_cumprimento"=>"cartorio"]; }');
            stub('repositories',name+'Repository','function getMaxSequencialForYear($ano){return 12;}');
        }
        stub('repositories','OficiosCartorioSubtarefasRepository','const TIPO_ENVIAR="enviar"; const TIPO_JUNTAR="juntar"; function findByOficioId($id){return [["tipo"=>"enviar","status"=>"pendente"]];}');
        stub('repositories','OficiosCartorioAlocadosRepository','function findByOficioId($id){return [];}');
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
                if(fixture) return route.fulfill({contentType:'text/html; charset=utf-8',body:process.env.MEASURE_BASELINE?fixture.replace('page-internal page-operational-form','page-internal'):fixture});
                const f=path.join(root,'public',u.pathname);
                if(u.pathname.startsWith('/assets/')&&fs.existsSync(f)) return route.fulfill({path:f});
                return route.fulfill({status:404});
            });
            for(const f of views) {
                await page.goto('http://fixture.test/'+f);
                const m=await page.locator('.card-page').evaluate(card=>{
                    const box=card.getBoundingClientRect();
                    const xs=[...card.querySelectorAll('input:not([type="hidden"]),select,textarea,.btn,.subtask-date')].flatMap(el=>{
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
                if(width===320&&f.endsWith('recebidas-edit')) {
                    const retry=page.locator('#comarca-lookup-retry');
                    assert.equal(await retry.isVisible(),false);
                    await page.locator('#filter-cidade').fill('Cidade sintética');
                    assert.equal(await page.locator('#filter-cidade').inputValue(),'Cidade sintética');
                    await retry.evaluate(el=>el.hidden=false);
                    const box=await retry.boundingBox();
                    assert.ok(box&&box.height>=44&&box.x+box.width<=320);
                }
                const back=page.locator(f==='gabinete-oficios-edit'?'#gab-voltar-btn':'#voltar-btn');
                await back.focus();
                assert.ok(await back.evaluate(el=>document.activeElement===el),f+' back focus');
            }
            await page.close();
        }
        console.log('Six document forms fit 320/375/390/430/768/1365 px.');
    } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exitCode=1;});
