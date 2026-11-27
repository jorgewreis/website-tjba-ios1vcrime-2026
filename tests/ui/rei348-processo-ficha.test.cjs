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
            'app/views/internal/partials/processo-ficha-alertas-sigilo.php',
            'app/views/internal/_internal_head_css.php','app/views/internal/_processo_ficha_modal_assets.php','app/helpers/ProcessoFicha.php','app/helpers/CnjValidator.php',
            'app/helpers/ProcessoNivelSigilo.php','app/repositories/ProcessoVaraRepository.php','app/DataJud/DataJudTribunalResolver.php','app/access/ProfileNormalizer.php',
            'public/assets/css/shared/processo-ficha.css','public/assets/js/shared/processo-ficha.js',
            'public/assets/css/shared/processo-ficha-modal.css','public/assets/js/shared/processo-ficha-modal.js',
            'public/assets/js/shared/processo-nivel-sigilo.js','public/assets/css/shared/painel-deslizante.css',
            'public/assets/js/shared/painel-deslizante.js']) copy(f);
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
                if (($_GET["id"]??"") !== "P5") {
                    $db->exec("ALTER TABLE processos ADD COLUMN vara_nome TEXT; UPDATE processos SET vara_nome='Vara local fictícia' WHERE id='P1'");
                }
                return $db;
            }`);
        stub('helpers','ProcessoSetorAuthorization','static function canAccessProcess(...$x){return $GLOBALS["perfil"]!=="SERVIDOR-GABINETE";}');
        stub('helpers','ProcessResponsibleSectorDefault','static function resolve(...$x){return ["id_setor"=>"CT"];}');
        stub('helpers','ParteDocumentoHelper','');
        stub('repositories','SalaAudienciasPautaParteRepository','function countPartesMenoresAtivasPorProcesso($id){return 0;}');
        stub('repositories','ProcessoNivelSigiloRepository','function disponivel(){return ($_GET["id"]??"")!=="P6";} function porIds($ids){return ["P2"=>2];}');
        stub('repositories','PartesDocumentosRepository','');
        stub('repositories','PartesInstituicoesRepository','');
        stub('repositories','ProcessosRepository','function findById($id){return ["id"=>$id,"numero_processo"=>"0001234-76.2026.8.05.0103","sigiloso"=>in_array($id,["P2","P6"],true) && ($_GET["fixture_publico"]??"")!=="1"]; }');
        stub('services','ConsultaProcessosService',`function get($id){return (new ProcessosRepository())->findById($id)+[
            "classe_nome"=>"Classe cadastrada fictícia","classe_origem"=>$id==="P2"?"datajud":"manual","assuntos"=>[
              ["id"=>"VINCULO-AF","assunto_id"=>"AF","origem"=>"manual","assunto_codigo"=>"100","assunto_descricao"=>"Assunto cadastrado"],
              ["id"=>"VINCULO-PROT","assunto_id"=>"APROT","origem"=>"datajud","assunto_codigo"=>"101","assunto_descricao"=>"Assunto protegido"]],
            "partes"=>[
              ["id"=>"DEF","nome_completo"=>"Pessoa fictícia testemunha defesa","polo"=>"terceiros","tipo_parte"=>"testemunha_defesa"],
              ["id"=>"PF","parte_id"=>"PF","nome_completo"=>"Pessoa fictícia A","polo"=>"passivo","tipo_parte"=>"reu"],
              ["id"=>"REQ","nome_completo"=>"Pessoa fictícia requerente","polo"=>"ativo","tipo_parte"=>"requerente"],
              ["id"=>"ADV","nome_completo"=>"Pessoa fictícia defesa","polo"=>"passivo","tipo_parte"=>"advogado"],
              ["id"=>"ADV-A","nome_completo"=>"Pessoa fictícia procuradora","polo"=>"ativo","tipo_parte"=>"advogado"],
              ["id"=>"ANC","nome_completo"=>"Pessoa fictícia autor","polo"=>"ativo","tipo_parte"=>"autor"],
              ["id"=>"VIT","nome_completo"=>"Pessoa fictícia vítima","polo"=>"ativo","tipo_parte"=>"vitima"],
              ["id"=>"DEN","nome_completo"=>"Pessoa fictícia testemunha denúncia","polo"=>"terceiros","tipo_parte"=>"testemunha_denuncia"],
              ["id"=>"IND","nome_completo"=>"Pessoa fictícia indiciada","polo"=>"passivo","tipo_parte"=>"indiciado"],
              ["id"=>"DESC","nome_completo"=>"Pessoa fictícia desconhecida","polo"=>"legado","tipo_parte"=>"perito_ad_hoc"]
            ]];}`);
        put('app/services/ProcessoSigiloCadastroService.php', `<?php
            class ProcessoSigiloCadastroErro extends RuntimeException {}
            class ProcessoSigiloCadastroService {
                function __construct($db){}
                static function perfilPermitido($perfils){return in_array($perfils[0],["ADMIN","SERVIDOR-CARTORIO"],true);}
                function preflight($id){
                    if($id==="P6") throw new ProcessoSigiloCadastroErro("Registro do nível indisponível até a atualização do banco.");
                    return ["protecao"=>$id==="P4"];
                }
            }`);
        stub('services','ProcessoPrioridadesService','function getEstadoEdicaoPorProcessoId(...$x){return ["prioridades"=>[["codigo"=>$x[0]==="P4"?"sigiloso":"fixture","label"=>$x[0]==="P4"?"Sigiloso":"Réu preso","origem"=>"manual"]],"catalogo"=>[]];}');
        const routeSource = fs.readFileSync(path.join(root,'public/frame.php'),'utf8');
        const listSource = fs.readFileSync(path.join(root,'app/helpers/ProcessoBlocoListagemHelper.php'),'utf8');
        assert.match(routeSource, /'consultaprocessosedit'\s*=>\s*\$PAGES \. '\/consulta-processos-edit\.php'/);
        assert.match(listSource, /frame\.php\?page=consultaprocessosedit&id=/);
        browser = await chromium.launch({executablePath:process.env.CHROMIUM_BIN || undefined});
        for (const largura of [1440,390,320]) {
            const pg = await browser.newPage({viewport:{width:largura,height:960}});
            pg.setDefaultTimeout(7000);
            let pedidos = 0, grava = 0, falha = true, sigiloGravado = false;
            await pg.route('**/*', async rt => {
                const u = new URL(rt.request().url());
                if(u.hostname !== 'fixture.test') return rt.abort();
                if(u.pathname === '/api/processos/datajud-ficha.php'){
                    pedidos++;
                    const id = rt.request().postDataJSON().processo_id;
                    assert.ok(['P1','P3','P4','P5','P2'].includes(id));
                    if(id==='P2') { assert.equal(sigiloGravado,true); return rt.fulfill({json:{estado:'indisponivel'}}); }
                    if(id==='P3') return rt.fulfill({json:{estado:'restrito',sigilo:null}});
                    if(id==='P4') return rt.fulfill({json:{estado:'disponivel',sigilo:'Público',movimentos:[]}});
                    if(falha){falha=false; return rt.fulfill({status:502,json:{estado:'erro'}});}
                    return rt.fulfill({json:{estado:'disponivel',vara:'Vara de destino fictícia',classe:'Classe na fonte',
                        assuntos:['Assunto na fonte'],sigilo:'Público',movimentos:[
                            {nome:'Movimento 2',data:'2026-11-24T12:00:00Z'}, {nome:'Movimento 4',data:'2026-11-26T12:00:00Z'},
                            {nome:'<b>Movimento 1</b>',data:'2026-11-23T12:00:00Z'}, {nome:'Movimento 3',data:'2026-11-25T12:00:00Z'}]
                    }});
                }
                if(u.pathname === '/api/classes/find-by-codigo.php') return rt.fulfill({json:{classe:u.searchParams.get('codigo')==='29'?{nome:'Classe oficial 29'}:null}});
                if(u.pathname === '/api/assuntos/find-by-codigo.php') return rt.fulfill({json:{assunto:u.searchParams.get('codigo')==='21'?{descricao:'Assunto oficial 21'}:null}});
                if(u.pathname === '/api/processos/assunto-link.php'){
                    assert.deepEqual(rt.request().postDataJSON(),{processo_id:'P1',codigo:'21'});
                    return rt.fulfill({json:{action:'created',assunto:{id:'N1',codigo:'21',descricao:'Assunto oficial 21'}}});
                }
                if(u.pathname === '/api/processos/update-classe.php'){
                    assert.deepEqual(rt.request().postDataJSON(),{processo_id:'P1',codigo:'29'});
                    return rt.fulfill({json:{success:true}});
                }
                if(u.pathname === '/api/processos/vara.php'){
                    assert.deepEqual(rt.request().postDataJSON(),{processo_id:'P1',vara:'Vara local editada'});
                    return rt.fulfill({json:{success:true}});
                }
                if(u.pathname === '/api/processos/nivel-sigilo.php'){
                    assert.deepEqual(rt.request().postDataJSON(),{processo_id:'P2',nivel_sigilo:'publico',confirmei_pje:true});
                    sigiloGravado = true;
                    return rt.fulfill({json:{success:true,alterado:true,sigiloso:false,nivel_sigilo:null}});
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
                    if (sigiloGravado && u.searchParams.get('id')==='P2') u.searchParams.set('fixture_publico','1');
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
            assert.equal(await pg.getByRole('heading',{name:'Dados do processo',exact:true}).count(),1);
            assert.equal(await pg.getByText('Ano',{exact:true}).count(),0);
            assert.equal(await pg.getByText('Somente leitura',{exact:true}).count(),0);
            assert.equal(await pg.getByText('Comarca de origem',{exact:true}).count(),0);
            assert.equal(await pg.locator('dt').filter({hasText:/^Comarca$/}).count(),1);
            assert.equal(await pg.locator('[data-ficha-sigilo]').textContent(),'Público');
            assert.deepEqual(await pg.locator('.ficha-polo').allTextContents(), ['Polo ativo','Polo passivo','Terceiros','Polo não informado']);
            const papeis = async (polo) => pg.locator('[aria-labelledby="ficha-polo-'+polo+'"] .ficha-parte-papel').allTextContents();
            assert.deepEqual(await papeis('ativo'),['Autor','Requerente','Advogado']);
            assert.deepEqual(await papeis('passivo'),['Réu','Advogado do réu','Indiciado']);
            assert.deepEqual(await papeis('terceiros'),['Vítima','Testemunha da Denúncia','Testemunha da Defesa']);
            assert.deepEqual(await papeis('indefinido'),['Perito ad hoc']);
            assert.deepEqual(await pg.locator('.ficha-partes strong').allTextContents(),['Pessoa fictícia A','Pessoa fictícia vítima']);
            assert.equal(await pg.locator('dt').filter({hasText:/^Classe$/}).count(),1);
            assert.equal(await pg.locator('dt').filter({hasText:/^Assuntos$/}).count(),1);
            assert.equal(await pg.locator('[data-ficha-classe]').textContent(),'Classe cadastrada fictícia');
            assert.equal(await pg.locator('[data-ficha-assuntos]').textContent(),'Assunto cadastrado; Assunto protegido');
            assert.equal(await pg.locator('[data-ficha-vara]').textContent(),'Vara local fictícia');
            assert.deepEqual(JSON.parse(await pg.locator('[data-ficha-datajud]').getAttribute('data-ficha-assuntos-locais')),['Assunto cadastrado']);
            await pg.getByRole('button',{name:'Tentar novamente'}).click();
            await pg.getByText('Vara de destino fictícia').waitFor();
            assert.deepEqual(await pg.locator('[data-ficha-recentes] strong').allTextContents(),['Movimento 4','Movimento 3','Movimento 2']);
            await pg.getByText('Ver todas as movimentações').click();
            await pg.locator('[data-ficha-todas] li').nth(3).waitFor();
            assert.equal(await pg.locator('[data-ficha-todas] li').count(),4);
            assert.equal(await pg.locator('[data-ficha-todas] b').count(),0);
            assert.equal(await pg.locator('[data-ficha-todas] strong').last().textContent(),'<b>Movimento 1</b>');
            assert.equal(await pg.locator('[data-ficha-vara]').textContent(),'Vara de destino fictícia');
            assert.match(await pg.locator('#ficha-dados-titulo + dl').textContent(),/0103 - Ilhéus/);
            assert.equal(await pg.locator('[data-ficha-classe]').textContent(),'Classe na fonte');
            assert.deepEqual(await pg.evaluate(()=>document.documentElement.scrollWidth<=innerWidth ? [] :
                [...document.querySelectorAll('.ficha-shell *')].filter(e=>e.getBoundingClientRect().right>innerWidth)
                    .slice(0,8).map(e=>[e.tagName,e.className,e.getBoundingClientRect().right])),[], 'sem overflow em '+largura);
            if (process.env.FICHA_SCREENSHOTS) await pg.screenshot({path:path.join(process.env.FICHA_SCREENSHOTS, 'leitura-' + largura + '.png')});
            await pg.getByRole('link',{name:'Editar dados'}).click();
            await pg.getByRole('heading',{name:'Editar dados do processo'}).waitFor();
            assert.equal(await pg.locator('#process-responsible-sector-select').inputValue(),'CT');
            assert.equal(await pg.getByRole('heading',{name:'Dados do processo',exact:true}).count(),1);
            assert.equal(await pg.locator('#processo-vara-local').inputValue(),'Vara local fictícia');
            assert.equal(await pg.locator('#processo-nivel-sigilo').inputValue(),'publico');
            assert.equal(await pg.getByRole('button',{name:'Salvar classificação'}).isDisabled(),true);
            assert.equal(await pg.locator('#processo-sigilo-confirmacao').isVisible(),false);
            assert.equal(await pg.getByText('DataJud',{exact:true}).count(),0,'o assunto sincronizado não carrega selo');
            assert.equal(await pg.locator('[data-assunto-id="APROT"] .btn-assunto-desvincular').count(),0);
            const codigoClasse = pg.locator('#proc-classe-codigo');
            await codigoClasse.fill('99');
            await codigoClasse.press('Enter');
            await pg.getByText('Código de classe não encontrado no catálogo.').waitFor();
            assert.equal(await pg.locator('#btn-proc-classe-vincular').isDisabled(),true);
            await codigoClasse.fill('29');
            await codigoClasse.press('Enter');
            await pg.waitForFunction(()=>document.querySelector('#proc-classe-nome-existente').value==='Classe oficial 29');
            assert.equal(await pg.locator('#proc-classe-nome-existente').getAttribute('readonly'),'');
            assert.equal(await pg.locator('#btn-proc-classe-vincular').isEnabled(),true);
            assert.equal(await pg.locator('#proc-classe-nome-novo').count(),0);
            await Promise.all([pg.waitForEvent('load'), pg.locator('#btn-proc-classe-vincular').click()]);
            const codigoAssunto = pg.locator('#processo-assunto-codigo');
            await codigoAssunto.fill('99');
            await codigoAssunto.press('Enter');
            await pg.waitForFunction(()=>document.querySelector('#processo-assunto-descricao').placeholder==='Código não encontrado no catálogo');
            assert.equal(await pg.locator('#btn-processo-assunto-vincular').isDisabled(),true);
            assert.equal(await pg.locator('#processo-assunto-descricao').getAttribute('readonly'),'');
            await codigoAssunto.fill('21');
            await codigoAssunto.press('Enter');
            await pg.locator('#btn-processo-assunto-vincular').click();
            await pg.getByText('21 - Assunto oficial 21',{exact:true}).waitFor();
            await pg.locator('#processo-vara-local').fill('Vara local editada');
            await Promise.all([pg.waitForEvent('load'), pg.getByRole('button',{name:'Salvar Vara local'}).click()]);
            assert.deepEqual(await pg.locator('[data-parte-id] .ficha-parte-nome strong').allTextContents(), ['Pessoa fictícia A','Pessoa fictícia vítima']);
            assert.equal(await pg.getByText(/justificativa/i).count(),0);
            if(process.env.FICHA_SCREENSHOTS) await pg.screenshot({path:path.join(process.env.FICHA_SCREENSHOTS, 'edicao-' + largura + '.png')});
            assert.deepEqual(await pg.evaluate(()=>document.documentElement.scrollWidth<=innerWidth ? [] :
                [...document.querySelectorAll('.ficha-shell *')].filter(e=>e.getBoundingClientRect().right>innerWidth)
                    .slice(0,8).map(e=>[e.tagName,e.className,e.getBoundingClientRect().right])),[], 'sem overflow em '+largura);
            assert.ok(await pg.locator('#proc-classe-codigo').isVisible());
            assert.equal(await pg.locator('#processo-assuntos-list .btn-assunto-desvincular').getAttribute('data-assunto-id'),'AF');
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
            await pg.getByRole('link',{name:'Editar dados'}).click();
            assert.equal(await pg.locator('#proc-classe-codigo').count(),0);
            assert.equal(await pg.locator('#processo-nivel-sigilo').inputValue(),'2');
            assert.match(await pg.getByText('Classe sincronizada com o DataJud.',{exact:false}).textContent(),/Só outra sincronização/);
            const leituraOutroSetor = await pg.goto('http://fixture.test/frame.php?page=consultaprocessosedit&id=P2&perfil=SERVIDOR-GABINETE');
            assert.equal(leituraOutroSetor.status(),200);
            assert.equal(await pg.getByText('Pessoa fictícia A',{exact:true}).count(),1);
            assert.equal(await pg.getByRole('link',{name:'Editar dados'}).count(),0);
            assert.equal(pedidos,antes,'nenhuma consulta pública do processo sigiloso');
            const negada = await pg.goto('http://fixture.test/frame.php?page=consultaprocessosedit&id=P2&perfil=SERVIDOR-GABINETE&modo=editar');
            assert.equal(negada.status(),403);
            await pg.goto('http://fixture.test/frame.php?page=consultaprocessosedit&id=P2&perfil=SERVIDOR-CARTORIO&modo=editar');
            assert.equal(await pg.locator('#process-responsible-sector-select').count(),0,'não depende do editor de lotação');
            await pg.locator('#processo-nivel-sigilo').selectOption('publico');
            assert.equal(await pg.locator('#processo-sigilo-confirmacao').isVisible(),true);
            assert.equal(await pg.getByRole('button',{name:'Salvar classificação'}).isDisabled(),true);
            await pg.locator('#processo-sigilo-confirmei-pje').check();
            await Promise.all([pg.waitForEvent('load'),pg.getByRole('button',{name:'Salvar classificação'}).click()]);
            assert.equal(await pg.locator('[data-ficha-sigilo]').textContent(),'Público');
            await pg.goto('http://fixture.test/frame.php?page=consultaprocessosedit&id=P3');
            await pg.getByText('Histórico indisponível na base pública por sigilo.').waitFor();
            assert.equal(await pg.locator('[data-ficha-sigilo]').textContent(),'Público');
            assert.equal(await pg.locator('[data-ficha-sigilo-datajud]').isVisible(),true);
            assert.equal(await pg.locator('[data-ficha-sigilo-datajud]').textContent(),'Restrição indicada pelo DataJud · nível não informado');
            await Promise.all([
                pg.waitForResponse(r=>r.url().includes('datajud-ficha.php')),
                pg.goto('http://fixture.test/frame.php?page=consultaprocessosedit&id=P4')
            ]);
            await pg.waitForFunction(()=>document.querySelector('[data-ficha-status]').textContent !== 'Movimentações ainda não carregadas.');
            assert.equal(await pg.locator('[data-ficha-sigilo]').textContent(),'Público');
            assert.equal(await pg.locator('[data-ficha-alerta-protecao]').isVisible(),true);
            await pg.goto('http://fixture.test/frame.php?page=consultaprocessosedit&id=P4&modo=editar');
            assert.equal(await pg.locator('#processo-nivel-sigilo option[value="publico"]').isDisabled(),true);
            await pg.goto('http://fixture.test/frame.php?page=consultaprocessosedit&id=P6&modo=editar');
            assert.equal(await pg.locator('[data-ficha-sigilo]').textContent(),'Sigiloso · nível não informado');
            assert.equal(await pg.locator('#processo-nivel-sigilo').count(),0);
            assert.equal(await pg.getByText('Registro do nível indisponível até a atualização do banco.').isVisible(),true);
            await pg.goto('http://fixture.test/frame.php?page=consultaprocessosedit&id=P5&modo=editar');
            assert.equal(await pg.getByText('Registro local da Vara indisponível até a atualização do banco.').count(),1);
            assert.equal(await pg.locator('#processo-vara-local').count(),0);
            await pg.close();
        }
        console.log('PASS: ficha, edição e histórico em 1440, 390 e 320 px; retorno com filtros, consulta e sigilo.');
    } finally {
        if(browser) await browser.close();
        fs.rmSync(dir,{recursive:true,force:true});
    }
})().catch(e=>{ console.error(e); process.exitCode=1; });
