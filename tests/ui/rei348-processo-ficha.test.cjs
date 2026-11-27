'use strict';
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname,'../..');
const dir = fs.mkdtempSync(path.join(os.tmpdir(),'ficha-ficticia-'));
function put(name, data) {
    const f = path.join(dir,name);
    fs.mkdirSync(path.dirname(f),{recursive:true});
    fs.writeFileSync(f,data);
}
function copy(name) { put(name,fs.readFileSync(path.join(root,name))); }
function stub(dir,name,body) { put('app/'+dir+'/'+name+'.php','<?php class '+name+' { function __construct(...$a) {} '+body+' }'); }
function render(url, perfil) {
    const query = Object.fromEntries(url.searchParams);
    return JSON.parse(execFileSync(process.env.PHP_BIN || 'php',['-r',
        'ob_start(); register_shutdown_function(static function(){echo json_encode(["body"=>ob_get_clean(),"status"=>http_response_code() ?: 200]);});'
        + 'require "fixture.php"; $_GET=json_decode('+JSON.stringify(JSON.stringify(query))+',true); '
        + '$userPerfils=['+JSON.stringify(perfil)+']; $GLOBALS["perfil"]=$userPerfils[0]; require "app/views/internal/consulta-processos-edit.php";'
    ],{cwd:dir,encoding:'utf8',maxBuffer:5_000_000}));
}
(async () => {
    let browser;
    try {
        for (const f of ['app/views/internal/consulta-processos-edit.php','app/views/internal/partials/processo-ficha-assets.php',
            'app/views/internal/partials/processo-ficha-contexto.php','app/views/internal/partials/processo-ficha-leitura.php',
            'app/views/internal/_internal_head_css.php','app/helpers/ProcessoFicha.php','app/helpers/CnjValidator.php',
            'app/helpers/ProcessoNivelSigilo.php','app/DataJud/DataJudTribunalResolver.php','app/access/ProfileNormalizer.php',
            'public/assets/css/shared/processo-ficha.css','public/assets/js/shared/processo-ficha.js']) copy(f);
        put('fixture.php',`<?php
            require __DIR__ . "/app/repositories/ProcessosRepository.php";
            function appIsHttpsRequest(){return false;}
            $_SESSION=["id_usuario"=>"U-FIXTURE"];
            $_SERVER["HTTP_HOST"]="fixture.test";
            $_SERVER["HTTP_REFERER"]="http://fixture.test/frame.php?page=cartasexpedidas&status=aguardando_envio";
        `);
        put('app/views/internal/_internal_head_styles.php', "<?php require __DIR__ . '/_internal_head_css.php'; ?><script src=\"/assets/js/shared/consulta-processos-edit.js\"></script>");
        put('app/views/internal/_toast_script.php','');
        put('app/views/internal/_theme_script.php','');
        put('app/views/internal/errors/403.php','Acesso negado');
        put('app/access/AccessEvaluator.php','<?php class AccessEvaluator { static function canAccessPage(...$args){return true;} }');
        stub('helpers','Conection',`static function getInstance(){return new self;}
            function getConnection(){ $db=new PDO("sqlite::memory:");
                $db->exec("CREATE TABLE processos(id TEXT,id_setor_responsavel TEXT); CREATE TABLE setores(id_setor TEXT,nome TEXT,ativo INT);
                   INSERT INTO processos VALUES('P1','CT'); INSERT INTO setores VALUES('CT','Cartório',1),('GB','Gabinete',1);");
                return $db;
            }`);
        stub('helpers','ProcessoSetorAuthorization','static function canAccessProcess(...$x){return $GLOBALS["perfil"]!=="SERVIDOR-GABINETE";}');
        stub('helpers','ProcessResponsibleSectorDefault','static function resolve(...$x){return ["id_setor"=>"CT"];}');
        stub('helpers','ParteDocumentoHelper','');
        stub('repositories','SalaAudienciasPautaParteRepository','function countPartesMenoresAtivasPorProcesso($id){return 0;}');
        stub('repositories','ProcessoNivelSigiloRepository','function disponivel(){return true;} function porIds($ids){return ["P2"=>2];}');
        stub('repositories','PartesDocumentosRepository','');
        stub('repositories','PartesInstituicoesRepository','');
        stub('repositories','ProcessosRepository','function findById($id){return ["id"=>$id,"numero_processo"=>"0001234-76.2026.8.05.0103","sigiloso"=>$id==="P2"]; }');
        stub('services','ConsultaProcessosService',`function get($id){return (new ProcessosRepository())->findById($id)+[
            "classe_nome"=>"Classe cadastrada fictícia","assuntos"=>[["id"=>"AF","assunto_codigo"=>"100","assunto_descricao"=>"Assunto cadastrado"]],
            "partes"=>[["id"=>"PF","parte_id"=>"PF","nome_completo"=>"Pessoa fictícia A","polo"=>"passivo","tipo_parte"=>"reu"]]];}`);
        stub('services','ProcessoPrioridadesService','function getEstadoEdicaoPorProcessoId(...$x){return ["prioridades"=>[["codigo"=>"fixture","label"=>"Réu preso","origem"=>"manual"]],"catalogo"=>[]];}');
        const routeSource = fs.readFileSync(path.join(root,'public/frame.php'),'utf8');
        const listSource = fs.readFileSync(path.join(root,'app/helpers/ProcessoBlocoListagemHelper.php'),'utf8');
        assert.match(routeSource, /'consultaprocessosedit'\s*=>\s*\$PAGES \. '\/consulta-processos-edit\.php'/);
        assert.match(listSource, /frame\.php\?page=consultaprocessosedit&id=/);
        browser = await chromium.launch({executablePath:process.env.CHROMIUM_BIN || undefined});
        for (const largura of [1440,390,320]) {
            const pg = await browser.newPage({viewport:{width:largura,height:960}});
            pg.setDefaultTimeout(7000);
            let pedidos = 0, grava = 0, falha = true;
            await pg.route('**/*', async rt => {
                const u = new URL(rt.request().url());
                if(u.hostname !== 'fixture.test') return rt.abort();
                if(u.pathname === '/api/processos/datajud-ficha.php'){
                    pedidos++;
                    assert.deepEqual(rt.request().postDataJSON(),{processo_id:'P1'});
                    if(falha){falha=false; return rt.fulfill({status:502,json:{estado:'erro'}});}
                    return rt.fulfill({json:{estado:'disponivel',vara:'Vara de destino fictícia',classe:'Classe na fonte',
                        assuntos:['Assunto na fonte'],sigilo:'Sem sigilo',movimentos:[
                            {nome:'Movimento 2',data:'2026-11-24T12:00:00Z'}, {nome:'Movimento 4',data:'2026-11-26T12:00:00Z'},
                            {nome:'<b>Movimento 1</b>',data:'2026-11-23T12:00:00Z'}, {nome:'Movimento 3',data:'2026-11-25T12:00:00Z'}]
                    }});
                }
                if(u.pathname === '/api/processos/setor-responsavel.php'){
                    grava++;
                    assert.equal(rt.request().postDataJSON().setor_id,'GB');
                    return rt.fulfill({json:{success:true,sector_id:'GB',changed:true}});
                }
                if(u.pathname.startsWith('/api/')) return rt.fulfill({json:{}});
                if(u.pathname === '/frame.php'){
                    if(u.searchParams.get('page') !== 'consultaprocessosedit') return rt.fulfill({
                        contentType:'text/html',body:'<a href="/frame.php?page=consultaprocessosedit&amp;id=P1">Ver processo</a>'
                    });
                    const result = render(u,u.searchParams.get('perfil') || 'ADMIN');
                    return rt.fulfill({contentType:'text/html; charset=utf-8',...result});
                }
                const f = path.join(root,'public',u.pathname);
                if(u.pathname.startsWith('/assets/') && fs.existsSync(f)) return rt.fulfill({path:f});
                return rt.fulfill({status:404});
            });
            await pg.goto('http://fixture.test/frame.php?page=cartasexpedidas&status=aguardando_envio');
            await pg.getByRole('link',{name:'Ver processo'}).click();
            await pg.getByRole('heading',{name:'Detalhes do processo'}).waitFor();
            assert.equal(await pg.locator('input,select,textarea').count(),0,'modo inicial não tem controles de gravação');
            await pg.getByRole('button',{name:'Tentar novamente'}).click();
            await pg.getByText('Vara de destino fictícia').waitFor();
            assert.deepEqual(await pg.locator('[data-ficha-recentes] strong').allTextContents(),['Movimento 4','Movimento 3','Movimento 2']);
            await pg.getByText('Ver todas as movimentações').click();
            await pg.locator('[data-ficha-todas] li').nth(3).waitFor();
            assert.equal(await pg.locator('[data-ficha-todas] li').count(),4);
            assert.equal(await pg.locator('[data-ficha-todas] b').count(),0);
            assert.equal(await pg.locator('[data-ficha-todas] strong').last().textContent(),'<b>Movimento 1</b>');
            assert.equal(await pg.locator('[data-ficha-vara]').textContent(),'Vara de destino fictícia');
            assert.match(await pg.locator('#ficha-origem-titulo + dl').textContent(),/Ilhéus/);
            assert.deepEqual(await pg.evaluate(()=>document.documentElement.scrollWidth<=innerWidth ? [] :
                [...document.querySelectorAll('.ficha-shell *')].filter(e=>e.getBoundingClientRect().right>innerWidth)
                    .slice(0,8).map(e=>[e.tagName,e.className,e.getBoundingClientRect().right])),[], 'sem overflow em '+largura);
            if (process.env.FICHA_SCREENSHOTS) await pg.screenshot({path:path.join(process.env.FICHA_SCREENSHOTS, 'leitura-' + largura + '.png')});
            await pg.getByRole('link',{name:'Editar dados'}).click();
            await pg.getByRole('heading',{name:'Editar dados do processo'}).waitFor();
            assert.equal(await pg.locator('#process-responsible-sector-select').inputValue(),'CT');
            assert.equal(await pg.getByText(/justificativa/i).count(),0);
            if(process.env.FICHA_SCREENSHOTS) await pg.screenshot({path:path.join(process.env.FICHA_SCREENSHOTS, 'edicao-' + largura + '.png')});
            assert.deepEqual(await pg.evaluate(()=>document.documentElement.scrollWidth<=innerWidth ? [] :
                [...document.querySelectorAll('.ficha-shell *')].filter(e=>e.getBoundingClientRect().right>innerWidth)
                    .slice(0,8).map(e=>[e.tagName,e.className,e.getBoundingClientRect().right])),[], 'sem overflow em '+largura);
            assert.ok(await pg.locator('#proc-classe-codigo').isVisible());
            if (process.env.FICHA_SCREENSHOTS) await pg.screenshot({path:path.join(process.env.FICHA_SCREENSHOTS, 'edicao-' + largura + '.png')});
            assert.ok(await pg.getByRole('button',{name:'Sincronizar classe e assuntos com o DataJud'}).isVisible());
            await pg.locator('#process-responsible-sector-select').selectOption('GB');
            await Promise.all([
                pg.waitForResponse(r=>r.url().includes('setor-responsavel.php')),
                pg.getByRole('button',{name:'Salvar setor'}).click()
            ]);
            assert.equal(grava,1);
            await pg.getByRole('link',{name:'Voltar aos detalhes'}).click();
            await pg.getByRole('link',{name:'Voltar',exact:true}).click();
            assert.equal(new URL(pg.url()).searchParams.get('status'),'aguardando_envio');
            const antes = pedidos;
            await pg.goto('http://fixture.test/frame.php?page=consultaprocessosedit&id=P2');
            assert.match(await pg.locator('[data-ficha-sigilo]').textContent(), /Sigilo Mínimo/);
            assert.equal(pedidos,antes);
            const leituraOutroSetor = await pg.goto('http://fixture.test/frame.php?page=consultaprocessosedit&id=P2&perfil=SERVIDOR-GABINETE');
            assert.equal(leituraOutroSetor.status(),200);
            assert.equal(await pg.getByText('Pessoa fictícia A').count(),1);
            assert.equal(await pg.getByRole('link',{name:'Editar dados'}).count(),0);
            assert.equal(pedidos,antes,'nenhuma consulta pública do processo sigiloso');
            const negada = await pg.goto('http://fixture.test/frame.php?page=consultaprocessosedit&id=P2&perfil=SERVIDOR-GABINETE&modo=editar');
            assert.equal(negada.status(),403);
            await pg.close();
        }
        console.log('PASS: ficha, edição e histórico em 1440, 390 e 320 px; retorno com filtros, consulta e sigilo.');
    } finally {
        if(browser) await browser.close();
        fs.rmSync(dir,{recursive:true,force:true});
    }
})().catch(e=>{ console.error(e); process.exitCode=1; });
