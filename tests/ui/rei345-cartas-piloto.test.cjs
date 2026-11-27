'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const chromium = process.env.RENDER_ONLY === '1' ? null : require(process.env.PLAYWRIGHT_MODULE || 'playwright').chromium;
const root = path.resolve(__dirname,'../..');
const dir = fs.mkdtempSync(path.join(os.tmpdir(),'rei345-fixture-'));
const put = (name,contents) => {
  const f=path.join(dir,name);
  fs.mkdirSync(path.dirname(f),{recursive:true});
  fs.writeFileSync(f,contents);
};
const copy = f => put(f,fs.readFileSync(path.join(root,f)));
const stub = (folder,name,body) => put('app/'+folder+'/'+name+'.php','<?php class '+name+'{function __construct(...$a){} '+body+'}');
const cpInternal = f => copy('app/views/internal/'+f);
const render=(route,role,embed=false,id=null,paginada=false,pageNumber=1,status='',nivel=null)=>{
  const file={
    cartasexpedidas:'cartorio-cartas-expedidas-list.php',
    cartasexpedidasnova:'cartorio-cartas-expedidas-list.php',
    cartasexpedidascreate:'cartorio-cartas-expedidas-create.php',
    cartasexpedidasedit:'cartorio-cartas-expedidas-edit.php',
    cartasexpedidasrules:'cartorio-cartas-expedidas-rules.php'
  }[route];
  return JSON.parse(execFileSync(process.env.PHP_BIN || 'php',['-d','display_errors=stderr','-r',
    'ob_start(); register_shutdown_function(static function(){echo json_encode(["body"=>ob_get_clean(),"status"=>http_response_code() ?: 200], JSON_THROW_ON_ERROR);}); require "fixture.php"; require "app/helpers/CartasExpedidasEstadoFluxo.php"; $_SERVER["REQUEST_METHOD"]="GET"; $_SESSION=["id_usuario"=>"T1"]; $GLOBALS["testrole"]='+JSON.stringify(role)+'; $GLOBALS["nivelFixture"]='+JSON.stringify(nivel)+'; $_GET=["page"=>'+JSON.stringify(route)+',"piloto"=>"'+(embed?'1':'0')+'","id"=>'+JSON.stringify(id)+',"processo"=>'+JSON.stringify(paginada?'fixture-paginacao':'')+',"page_number"=>'+JSON.stringify(pageNumber)+',"status"=>'+JSON.stringify(status)+']; require "'+'app/views/internal/'+file+'";'],
    {cwd:dir,encoding:'utf8'}));
};

(async()=>{
  let browser;
  try {
    for(const f of fs.readdirSync(path.join(root,'app/views/internal'))) if(f.startsWith('_')&&f.endsWith('.php')) cpInternal(f);
    for(const f of ['cartorio-cartas-expedidas-list','cartorio-cartas-expedidas-create','cartorio-cartas-expedidas-rules','cartorio-cartas-expedidas-edit','partials/cartas-expedidas-v2-row','partials/cartas-expedidas-piloto-list','partials/cartas-expedidas-piloto-row','partials/cartas-expedidas-piloto-prioridade']) cpInternal(f+'.php');
    for(const f of ['CartasExpedidasPrioridade','ProcessoNivelSigilo','CartasExpedidasTempoEtapa','CartasExpedidasEstadoFluxo','CartasExpedidasUiCopy','CartasExpedidasEnvioRegra','DeleteAuthorization']) copy('app/helpers/'+f+'.php');
    copy('app/access/ProfileNormalizer.php');
    stub('helpers','UsuarioPerfisAutorizacao','static function perfisDoUsuario($id){return [$GLOBALS["testrole"]];}');
    stub('helpers','ProcessoBlocoListagemHelper','static function appendBlocoData($db,$rows){return $rows;}');
    stub('helpers','RegistroMetadataHelper','static function viewerContextFromSession(){return [];} static function fromListRow(...$a){return ["show_edit"=>true,"edit_href"=>"/frame.php?page=cartasexpedidasedit"]; } static function linhasHistoricoV2CartasExpedidas(...$a){return [["rotulo"=>"Carta cadastrada","data_hora"=>"17/11/2026","autor"=>"Equipe sintética"]];}');
    stub('repositories','AlocacoesRegrasRepository','function findByRegra($key){return ["usuario_id"=>"T1"];}');
    stub('repositories','CartasExpedidasSubtarefasRepository','const TIPO_ENVIO="aguardando_envio",TIPO_DEVOLUCAO="aguardando_devolucao",ORDEM_ENVIO=1,ORDEM_DEVOLUCAO=2; function findByCartaId($id){return [];} static function resolverSubtarefaAtivaPorLista($rows){return $rows[0]??null;}');
    stub('repositories','TribunaisRepository','');
    put('app/helpers/CartorioTimelineHelper.php','<?php function cartorioTimelineBuildViewData(...$a){return ["statusLabel"=>"Aguardando envio","cadastro"=>["minorText"=>"Carta cadastrada"]];}');
    stub('services','CartasExpedidasService',`function find($id){return $id === "C1" ? ["processo_origem_id"=>"P1","finalidade"=>"Intimação de Decisão Interlocutória","cidade_destino"=>"Una","uf_destino"=>"BA","comarca_destino_id"=>"COMARCA-SINTETICA","prazo_dias"=>30,"status"=>"aguardando envio","prioridade_tarefa"=>"critico"] : null;} function listCartas($f,$limit,$offset){$rows=[
       ["IdCarta"=>"C1","Data"=>date("Y-m-d H:i:s", time()-10*60-5),"bloco_processo_prioridades"=>[["codigo"=>"preso","label"=>"Réu preso"],["codigo"=>"idoso","label"=>"Pessoa idosa"],["codigo"=>"medida","label"=>"Medida protetiva de urgência"]],"numero_processo"=>"0000041-00.2026.0.00.0000","Status"=>"aguardando envio","EtapaEfetiva"=>"envio","Comarca"=>"Una","Estado"=>"BA","Finalidade"=>"Intimação de Decisão Interlocutória","Prazo"=>30,"bloco_processo_abrir_href"=>"/frame.php?page=consultaprocessosedit","SubtarefasAll"=>[["id"=>"S1","tipo"=>"aguardando_envio"]]],
       ["IdCarta"=>"C2","processo_sigiloso"=>true,"processo_nivel_sigilo"=>$GLOBALS["nivelFixture"]??null,"bloco_processo_prioridades"=>[["codigo"=>"violencia","label"=>"Violência doméstica"]],"Data"=>date("Y-m-d H:i:s", time()-90*86400),"CriadoPor"=>"T2","CriadoPorNome"=>"Noa Silva de Teste","numero_processo"=>"0000042-00.2026.0.00.0000","Status"=>"aguardando cumprimento","EtapaEfetiva"=>"devolucao","Comarca"=>"Uruçuca","Estado"=>"BA","Finalidade"=>"Intimação","Prazo"=>20,"SubtarefasAll"=>[["id"=>"S2","tipo"=>"aguardando_devolucao"],["id"=>"S1B","tipo"=>"aguardando_envio","status"=>"concluida","updated_at"=>date("Y-m-d H:i:s", time()-8*86400-60)]]]
    ];
    if (($f["processo"]??"") === "fixture-paginacao") {
      $original=$rows; $rows=[];
      for($i=0;$i<33;$i++) { $c=$original[$i%2===0?0:1]; $c["IdCarta"]="P".$i; $rows[]=$c; }
    }
    if (($f["status"]??"") === "aguardando_envio") $rows=array_values(array_filter($rows, fn($c)=>$c["EtapaEfetiva"]==="envio"));
    if (($f["status"]??"") === "aguardando_cumprimento" || ($f["piloto_sem_envio"]??false)) $rows=array_values(array_filter($rows, fn($c)=>$c["EtapaEfetiva"]==="devolucao"));
    return ["total"=>count($rows),"rows"=>array_slice($rows,$offset,$limit)];
    } function getStatusCounts($f){return ($f["processo"]??"") === "fixture-paginacao" ? ["aguardando envio"=>17,"aguardando cumprimento"=>16] : ["aguardando envio"=>1,"aguardando cumprimento"=>1];} function getFinalidadeCounts($f){return ["Intimação de Decisão Interlocutória"=>1,"Intimação"=>1];}`);
    put('fixture.php',`<?php
      class ProcessosRepository {function __construct($db){} function findById($id){return ['numero_processo'=>'0000041-00.2026.0.00.0000'];}}
      class Security {static function generateCSRFToken(){return 'fixture-token';}}
      class Conection {static function getInstance(){return new self;}
       function getConnection(){
        $db=new PDO('sqlite::memory:');
        $db->exec("CREATE TABLE cartas_expedidas(id TEXT,prioridade_tarefa TEXT); INSERT INTO cartas_expedidas VALUES ('C1','critico'),('C2',NULL);
          CREATE TABLE usuarios(id_usuario TEXT,nome TEXT,sobrenome TEXT,cadastro_ativo INT); INSERT INTO usuarios VALUES ('T1','Lia','Bastos Facundo de Martins',1);
          CREATE TABLE usuario_perfil(usuario TEXT,perfil TEXT); INSERT INTO usuario_perfil VALUES ('T1','ADMIN');");
        return $db;
      }}`);
    // Fixture views only use filemtime for these assets; the routes below
    // serve the real files. Copy versioned assets instead of symlinking.
    for(const f of [
      'css/modules/cartorio/cartas-expedidas-piloto.css',
      'js/modules/cartorio/cartas-expedidas/piloto.js',
      'js/modules/cartorio/cartas-expedidas/piloto-form.js',
      'css/shared/processo-ficha-modal.css','js/shared/processo-ficha-modal.js',
      'css/shared/painel-deslizante.css','js/shared/painel-deslizante.js'
    ]) copy('public/assets/'+f);
    // Verifica a renderização real da tag e do template, também sem navegador.
    for (const [nivel, rotulo] of [[null,'Segredo de Justiça'],[1,'Segredo de Justiça'],[2,'Sigilo Mínimo'],[3,'Sigilo Médio'],[4,'Sigilo Intenso'],[5,'Sigilo Absoluto']]) {
      const {body,status}=render('cartasexpedidas','ADMIN',false,null,false,1,'',nivel);
      assert.equal(status,200);
      assert.equal(body.split('data-ce-indicator="sigilo"').length-1,1,'sem sigilo não ganha cadeado');
      assert.ok(body.includes('<dt>Sigilo</dt><dd>'+rotulo+'</dd>'),'nível oficial no resumo do ícone');
      assert.ok(body.includes('<span>Sigilo</span><span>'+rotulo+'</span>'),'nível nos detalhes continua igual');
      assert.ok(body.includes('<dt>Prioridade</dt><dd>Violência doméstica</dd>'));
    }
    const frameSource = fs.readFileSync(path.join(root,'public/frame.php'),'utf8');
    const homeSource = fs.readFileSync(path.join(root,'public/home.php'),'utf8');
    for (const route of ['cartasexpedidas','cartasexpedidasnova']) {
      assert.match(frameSource, new RegExp("'" + route + "' +=> \\$PAGES \\. '/cartorio-cartas-expedidas-list\\.php'"));
    }
    assert.equal((homeSource.match(/target: 'cartasexpedidas'/g) || []).length, 1);
    assert.doesNotMatch(homeSource, /target: 'cartasexpedidasnova'/);
    for (const route of ['cartasexpedidas','cartasexpedidasnova']) {
      const {body,status} = render(route,'ADMIN',false,null,true,2,'aguardando_cumprimento',5);
      assert.equal(status,200);
      assert.match(body, /name="page" value="cartasexpedidas"/);
      assert.match(body, /Página 2 de 2/);
      assert.match(body, /value="aguardando_cumprimento" selected/);
      assert.match(body, /page=cartasexpedidas&amp;status=aguardando_cumprimento/);
      assert.doesNotMatch(body, /Voltar à versão atual|carta-expedida-v2-row/);
    }
    if (process.env.RENDER_ONLY === '1') {
      console.log('Renderização PHP de todos os níveis e das duas URLs compatíveis: OK');
      return;
    }
    const roleMap=new Map();
    browser=await chromium.launch({executablePath:process.env.CHROMIUM_BIN||undefined,headless:true});
    for(const [width,role,mayDelete] of [[1640,'ADMIN',true],[1440,'ADMIN',true],[900,'CHEFIA',true],[767,'CHEFIA',true],[390,'DIRETORA',false],[320,'SERVIDOR-CARTORIO',false]]) {
      const p=await browser.newPage({viewport:{width,height:900},hasTouch:width<=420});
      p.setDefaultTimeout(5000);
      if(width===1640) await p.clock.install();
      await p.addInitScript(()=>{Object.defineProperty(navigator,'clipboard',{value:{writeText:async text=>{window.copied=text;}}});});
      let cityCalls=0, createCalls=0, editCalls=0, nestedListRequests=0, listReloads=0;
      const createKeys=[];
      let releaseCreate;
      await p.route('**/*',async r=>{
        const u=new URL(r.request().url());
        if(u.hostname!=='fixture.test') return r.abort();
        if(u.pathname==='/shell' || u.pathname==='/shell-flush') {
          return r.fulfill({contentType:'text/html; charset=utf-8',body:'<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/css/shared/layout.css"><style>body{margin:0}'+(u.pathname==='/shell-flush'?'.layout-wraper--home .target-page{margin-top:0}':'')+'</style><div class="layout-wraper--home"><nav class="layout-navbar">Menu flutuante</nav><iframe class="target-page" src="/frame.php?page=cartasexpedidas"></iframe></div>'});
        }
        if(u.pathname==='/api/cartorio-cartas-expedidas/buscar-cidade.php') {
          cityCalls++;
          return r.fulfill({json:{found:true,uf:'BA',is_ccm:false}});
        }
        if(u.pathname==='/api/cartorio-cartas-expedidas/create.php') {
          createCalls++;
          createKeys.push(r.request().postDataJSON().idempotency_key);
          if(createCalls===1) {
            await new Promise(resolve=>{releaseCreate=resolve;});
            return r.fulfill({status:500,json:{error:'Falha temporária sintética'}});
          }
          return r.fulfill({status:201,json:{id:'CARTA-SINTETICA'}});
        }
        if(u.pathname==='/api/cartorio-cartas-expedidas/update.php') {
          editCalls++;
          assert.equal(r.request().postDataJSON().prioridade_tarefa,'urgente');
          if(editCalls===1) return r.fulfill({status:500,json:{error:'Falha temporária de edição sintética'}});
          return r.fulfill({json:{success:true}});
        }
        if(u.pathname==='/frame.php') {
          if(u.searchParams.get('page')==='cartasexpedidas') {
            if(r.request().frame().parentFrame()) nestedListRequests++;
            else listReloads++;
          }
          const key=role+'|'+u.searchParams.get('page')+'|'+u.searchParams.get('piloto')+'|'+u.searchParams.get('id')+'|'+u.searchParams.get('processo')+'|'+u.searchParams.get('page_number')+'|'+u.searchParams.get('status');
          if(!roleMap.has(key)) roleMap.set(key,render(u.searchParams.get('page'),role,u.searchParams.has('piloto'),u.searchParams.get('id'),u.searchParams.get('processo')==='fixture-paginacao',Number(u.searchParams.get('page_number')||1),u.searchParams.get('status')||''));
          return r.fulfill({contentType:'text/html; charset=utf-8',...roleMap.get(key)});
        }
        const f=path.join(root,'public',u.pathname);
        if(u.pathname.startsWith('/assets/') && fs.existsSync(f)) return r.fulfill({path:f});
        return r.fulfill({status:404});
      });
      for(const shell of width<=767 ? ['/shell'] : ['/shell','/shell-flush']) {
        await p.goto('http://fixture.test'+shell);
        const shellBar=await p.locator('.layout-navbar').boundingBox();
        const header=await p.frameLocator('iframe').locator('.ce-header').boundingBox();
        assert.ok(shellBar && header && header.y>=shellBar.y+shellBar.height+12,'floating shell must not overlap header at '+width+' '+shell);
      }
      await p.goto('http://fixture.test/frame.php?page=cartasexpedidas');
      const queueWidth=await p.locator('.ce-queue').evaluate(el=>el.getBoundingClientRect().width);
      if(width>=768) {
        const search=await p.getByRole('searchbox',{name:'Pesquisar por processo'}).boundingBox();
        assert.ok(search && search.width>=220 && search.width<=250,'desktop process search must stay compact at '+width);
      }
      if(width<=767) {
        const [dest,actions,entry,search,status,goal]=await Promise.all(['.ce-entry .ce-destino','.ce-entry .ce-row-actions','.ce-entry','.ce-toolbar input[type=search]','.ce-toolbar select[name=status]','.ce-toolbar select[name=finalidade]'].map(sel=>p.locator(sel).first().boundingBox()));
        if(width>420) assert.ok(Math.abs((dest.y+dest.height/2)-(actions.y+actions.height/2))<2,'actions must share metadata row');
        else assert.ok(actions.y + actions.height > dest.y && actions.y < dest.y + dest.height + 20,'ações permanecem no bloco da comarca e prazo');
        assert.ok(entry.height<138,'mobile row should stay compact at '+width+' (height='+entry.height+')');
        assert.ok(search.width>width-50,'full-width mobile search');
        if(width<480) assert.ok(status.width>width-50 && goal.width>width-50,'use readable full-width filters on phone');
        else assert.ok(Math.abs(status.y-goal.y)<2,'filters should share the row on wider screens');
        assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'list must not overflow horizontally');
      }
      assert.equal(await p.locator('.ce-entry').count(),2);
      assert.deepEqual(await p.locator('[data-ce-stage-seconds]').allTextContents(),['10min','8d']);
      assert.ok(await p.locator('.ce-stage-badge').first().isVisible());
      const lacre=p.locator('.ce-entry [data-ce-indicator="sigilo"]');
      assert.equal(await lacre.count(),1,'somente a carta marcada sigilosa tem cadeado');
      assert.equal(await p.locator('.ce-entry .ce-double-tag').count(),0,'lista não empilha tags');
      // A folha de estilos do painel não pode reformatar nem esconder a lista.
      await p.locator('.ce-entry .ce-dropdown summary').first().click();
      await p.getByRole('button',{name:'Ver detalhes',exact:true}).click();
      const floating = p.locator('#ce-panel');
      assert.equal(await floating.evaluate(el=>getComputedStyle(el).position),'absolute');
      assert.notEqual(await floating.evaluate(el=>getComputedStyle(el).boxShadow),'none');
      assert.equal(await p.locator('.ce-queue').evaluate(el=>el.getBoundingClientRect().width),queueWidth);
      assert.ok(await p.locator('.ce-queue').isVisible());
      await p.getByRole('button',{name:'Fechar painel'}).click();
      await floating.waitFor({state:'hidden'});
      const priorities=p.getByLabel('Prioridades do processo',{exact:true});
      assert.ok(await priorities.isVisible());
      await priorities.focus();
      await p.keyboard.press('Enter');
      const disclosure=priorities.locator('..');
      assert.ok(await disclosure.locator('dl').isVisible());
      assert.equal(await disclosure.locator('dd').textContent(),'Réu preso, Pessoa idosa, Medida protetiva de urgência');
      await p.keyboard.press('Escape');
      assert.equal(await disclosure.locator('dl').isVisible(),false);
      assert.ok(await priorities.evaluate(el=>el===document.activeElement));
      const both=p.getByLabel('Sigilo e prioridades do processo',{exact:true});
      const cnj=both.locator('xpath=../../button[@data-ce-copy]');
      const [cnjBox,iconBox]=await Promise.all([cnj.boundingBox(),both.boundingBox()]);
      assert.ok(cnjBox && iconBox && iconBox.x>=cnjBox.x+cnjBox.width-1 && Math.abs((cnjBox.y+cnjBox.height/2)-(iconBox.y+iconBox.height/2))<2,'ícones ao lado do número em '+width+' '+JSON.stringify({cnjBox,iconBox}));
      if(width<=420) await p.touchscreen.tap(iconBox.x+iconBox.width/2,iconBox.y+iconBox.height/2);
      else await both.click();
      assert.deepEqual(await both.locator('..').locator('dd').allTextContents(),['Segredo de Justiça','Violência doméstica']);
      await p.locator('.ce-header h1').click();
      assert.equal(await both.locator('..').locator('dl').isVisible(),false);
      if(width>=768) assert.equal(await p.locator('.ce-columns span:nth-child(7)').textContent(),'Tempo na etapa');
      if(width===1640) {
        await p.clock.fastForward(61_000);
        assert.equal(await p.locator('[data-ce-stage-seconds]').first().textContent(),'11min');
      }
      for (const actions of await p.locator('.ce-row-actions').all()) {
        const action=await actions.locator('.ce-button.small').boundingBox();
        const menu=await actions.locator('summary').boundingBox();
        assert.ok(action && menu && Math.abs(action.height-menu.height)<1,'row buttons must have equal heights at '+width);
      }
      assert.equal(await p.locator('.ce-tools a[href="?page=cartasexpedidas"]').count(),0);
      const finalidade=p.locator('.ce-entry').first().locator('.ce-finalidade');
      assert.equal(await finalidade.getAttribute('title'),'Intimação de Decisão Interlocutória');
      assert.equal(await finalidade.textContent(),'Finalidade: Intimação de Decisão Interlocutória');
      assert.equal(await p.locator('.ce-entry').first().locator('.ce-assignee').textContent(),'Responsável pelo envio: Lia Martins');
      assert.equal(await p.locator('.ce-entry').last().locator('.ce-assignee').textContent(),'Responsável pelo cumprimento: Noa Teste');
      assert.equal(await p.locator('.ce-entry').first().locator('.ce-priority').getAttribute('aria-label'),'Crítico');
      assert.equal(await p.locator('.ce-entry').last().locator('.ce-priority').getAttribute('aria-label'),'Sem prioridade definida');
      await p.locator('.ce-cnj').first().click();
      assert.equal(await p.evaluate(()=>window.copied),'0000041-00.2026.0.00.0000');
      await p.locator('.ce-entry').first().locator('.ce-dropdown summary').click();
      assert.deepEqual(await p.locator('.ce-entry').first().locator('.ce-dropdown > div > *').allTextContents(),['Ver detalhes','Abrir processo','Texto para WhatsApp']);
      await p.getByRole('button',{name:'Ver detalhes'}).first().click();
      const resumo=p.getByRole('tabpanel',{name:'Resumo'});
      assert.equal(await resumo.isVisible(),true);
      assert.match(await resumo.textContent(),/FinalidadeIntimação de Decisão Interlocutória/);
      assert.match(await resumo.textContent(),/Responsável pelo envioLia Martins/);
      assert.doesNotMatch(await resumo.textContent(),/Bastos Facundo/);
      assert.equal(await p.getByRole('link',{name:'Editar',exact:true}).count(),1);
      assert.equal(await p.getByRole('button',{name:'Excluir carta'}).count(),Number(mayDelete));
      assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'overflow at '+width);
      await p.getByRole('tab',{name:'Histórico (1)'}).click();
      assert.match(await p.getByRole('tabpanel',{name:'Histórico (1)'}).textContent(),/Carta cadastrada/);
      await p.getByRole('link',{name:'Editar',exact:true}).click();
      const edit=p.frameLocator('#ce-panel iframe');
      assert.equal(await edit.locator('#filter-tipo').inputValue(),'Intimação de Decisão Interlocutória');
      assert.equal(await edit.locator('#ce-prioridade-tarefa').inputValue(),'critico');
      assert.equal(await edit.getByText('Subtarefas - Timeline',{exact:true}).count(),0);
      const saveEdit=edit.locator('#salvar-btn');
      assert.equal(await saveEdit.count(),1);
      const expectOneRefresh=async (closePanel, expectedUrl)=>{
        const before=listReloads;
        await Promise.all([p.waitForEvent('load'),closePanel()]);
        assert.equal(await p.locator('#ce-panel iframe').count(),0);
        assert.equal(await p.locator('#ce-panel').isHidden(),true);
        assert.equal(listReloads,before+1);
        assert.equal(p.url(),expectedUrl);
      };
      if(width===1640 || width===390) {
        const before=listReloads;
        const filteredUrl=await p.evaluate(()=>{
          const address=new URL(location.href);
          address.searchParams.set('processo','0000041-00.2026.0.00.0000');
          history.replaceState(null,'',address);
          return location.href;
        });
        await edit.locator('#ce-prioridade-tarefa').selectOption('urgente');
        const failedEdit=p.waitForResponse(r=>r.url().includes('/cartorio-cartas-expedidas/update.php') && r.status()===500);
        await saveEdit.click();
        await failedEdit;
        await saveEdit.waitFor();
        assert.equal(await edit.locator('#ce-prioridade-tarefa').inputValue(),'urgente');
        assert.equal(listReloads,before,'saving error keeps the editor open and does not refresh the list');
        await expectOneRefresh(()=>saveEdit.click(),filteredUrl);
        assert.equal(editCalls,2);
        assert.equal(await p.getByText('Alterações salvas.',{exact:true}).isVisible(),true);
      } else {
        const closeEdit=width===1440
          ? ()=>edit.locator('#voltar-btn').click()
          : ()=>p.getByRole('button',{name:'Fechar painel'}).click();
        await expectOneRefresh(closeEdit,p.url());
      }
      await p.locator('.ce-entry').last().locator('.ce-dropdown summary').click();
      await p.locator('.ce-entry').last().getByRole('button',{name:'Ver detalhes'}).click();
      assert.equal(await p.getByRole('button',{name:'Reiniciar envio'}).count(),1);
      assert.match(await p.getByRole('tabpanel',{name:'Resumo'}).textContent(),/Responsável pelo cumprimentoNoa Teste/);
      assert.deepEqual(await p.locator('#ce-panel .ce-process-secrecy span').allTextContents(),['Sigilo','Segredo de Justiça']);
      assert.equal(await p.getByRole('link',{name:'Editar',exact:true}).count(),0);
      await p.getByRole('button',{name:'Fechar painel'}).click();
      await p.getByRole('link',{name:'Novo cadastro'}).click();
      const create=p.frameLocator('#ce-panel iframe');
      assert.equal(await create.locator('#ce-prioridade-tarefa').inputValue(),'medio');
      if(width===1640 || width===390) {
        // Prepare the form with a synthetic CNJ and the event which unlocks
        // it after the lookup; no real DataJud or CREATE call leaves the fixture.
        await p.evaluate(()=>history.replaceState(null,'',location.href+'&status=aguardando_envio'));
        await create.locator('body').evaluate(el=>{
          const doc=el.ownerDocument;
          doc.querySelector('#filter-processo').value='0000001-69.2020.8.05.0001';
          const tipo=doc.querySelector('#filter-tipo');
          tipo.value=[...tipo.options].find(option=>option.value).value;
          doc.querySelector('#filter-estado').value='BA';
          doc.defaultView.dispatchEvent(new CustomEvent('datajudClasseResolved',{detail:{processoInputId:'filter-processo'}}));
        });
        const cityLookup=p.waitForResponse(r=>r.url().includes('/buscar-cidade.php'));
        await create.locator('#filter-cidade').fill('Una');
        await cityLookup;
        const submit=create.locator('#cadastrar-btn');
        const savingRequest=p.waitForRequest(r=>r.url().includes('/cartorio-cartas-expedidas/create.php'));
        await submit.click();
        await savingRequest;
        assert.equal(await submit.isDisabled(),true);
        const spinner=await submit.locator('.ce-create-spinner').evaluate(el=>{
          const css=getComputedStyle(el);
          return [css.animationName, css.animationTimingFunction, css.transformOrigin,css.width,css.height];
        });
        assert.deepEqual(spinner.slice(0,2),['ce-create-turn','linear']);
        assert.equal(spinner[3],spinner[4],'spinner remains square');
        assert.equal(parseFloat(spinner[2]),parseFloat(spinner[3])/2,'spinner rotates around its own center');
        await create.locator('body').evaluate(el=>el.ownerDocument.defaultView.dispatchEvent(new CustomEvent('datajudClasseResolved')));
        assert.equal(await submit.isDisabled(),true,'late lookup must not unlock saving');
        releaseCreate();
        await submit.getByText('Cadastrar',{exact:true}).waitFor();
        assert.equal(await create.locator('#filter-cidade').inputValue(),'Una');
        await submit.click();
        await p.getByText('Carta cadastrada.',{exact:true}).waitFor();
        assert.equal(await p.locator('#ce-panel iframe').count(),0);
        assert.equal(new URL(p.url()).searchParams.get('status'),'aguardando_envio');
        assert.equal(nestedListRequests,width<=767?1:2,'no list navigations inside the form; only initial shell previews');
        assert.equal(cityCalls,1,'reuse the city/UF lookup when saving and retrying');
        assert.equal(createCalls,2);
        assert.match(createKeys[0],/^[a-f0-9]{64}$/);
        assert.equal(createKeys[0],createKeys[1],'retry keeps the idempotency key');
      } else {
        const closeCreate=width===900
          ? ()=>create.locator('#filter-cidade').press('Escape')
          : ()=>p.getByRole('button',{name:'Fechar painel'}).click();
        await expectOneRefresh(closeCreate,p.url());
      }
      if (mayDelete) {
        await p.getByRole('link',{name:'Regra de envio'}).click();
        const rules=p.frameLocator('#ce-panel iframe');
        assert.match(await rules.locator('.ce-rule-now').textContent(),/Lia Bastos Facundo de Martins/);
        assert.equal(await rules.locator('[name=csrf_token]').count(),1);
      } else {
        assert.ok(await p.getByRole('button',{name:'Regra de envio'}).isDisabled());
        assert.equal(await p.getByRole('link',{name:'Regra de envio'}).count(),0);
        const refused=await p.goto('http://fixture.test/frame.php?page=cartasexpedidasrules&piloto=1');
        assert.equal(refused.status(),403);
        assert.equal(await p.locator('form').count(),0);
      }
      await p.goto('http://fixture.test/frame.php?page=cartasexpedidasnova&status=aguardando_envio');
      assert.equal(await p.locator('.carta-expedida-v2-row').count(),0);
      assert.equal(await p.locator('.ce-entry').count(),1);
      assert.equal(await p.locator('#ce-panel').count(),1);
      assert.equal(await p.locator('.ce-toolbar select[name=status]').inputValue(),'aguardando_envio');
      await p.getByRole('button',{name:'Filtrar'}).click();
      assert.equal(new URL(p.url()).searchParams.get('page'),'cartasexpedidas');
      await p.goto('http://fixture.test/frame.php?page=cartasexpedidasedit&id=C1');
      assert.equal(await p.getByText('Subtarefas - Timeline',{exact:true}).count(),1);
      assert.equal(await p.locator('#filter-tipo').inputValue(),'Intimação de Decisão Interlocutória');
      if(width===1640) {
        await p.goto('http://fixture.test/frame.php?page=cartasexpedidas&processo=fixture-paginacao');
        const envio=p.locator('details.ce-group.envio');
        const outras=p.locator('details.ce-group.devolucao');
        assert.equal(await p.locator('details.ce-group').first().getAttribute('class'),'ce-group envio');
        assert.equal(await envio.locator('.ce-entry').count(),17);
        assert.equal(await envio.locator('summary > .ce-tag').textContent(),'17');
        assert.equal(await outras.locator('.ce-entry').count(),15);
        assert.equal(await outras.locator('summary > small').textContent(),'16 no total');
        assert.equal(await p.getByText('Versão piloto',{exact:true}).count(),0);
        await p.getByRole('link',{name:'Próxima'}).click();
        assert.equal(await envio.locator('.ce-entry').count(),17);
        assert.equal(await outras.locator('.ce-entry').count(),1);
        await p.goto('http://fixture.test/frame.php?page=cartasexpedidas&processo=fixture-paginacao&status=aguardando_envio');
        assert.equal(await envio.locator('.ce-entry').count(),17);
        assert.equal(await outras.count(),0);
        assert.equal(await p.getByRole('link',{name:'Próxima'}).count(),0);
        await p.goto('http://fixture.test/frame.php?page=cartasexpedidas&processo=fixture-paginacao&status=aguardando_cumprimento');
        assert.equal(await envio.count(),0);
        assert.equal(await outras.locator('.ce-entry').count(),15);
      }
      await p.close();
    }
    console.log('PASS: rota principal e alias; seis viewports; cópia, menu por etapa, abas, prioridade, regra e perfis.');
  } finally {
    if(browser) await browser.close();
    fs.rmSync(dir,{recursive:true,force:true});
  }
})().catch(error=>{console.error(error);process.exitCode=1;});
