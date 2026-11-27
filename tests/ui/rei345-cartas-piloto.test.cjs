'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
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
const render=(route,role,embed=false)=>{
  const file={
    cartasexpedidas:'cartorio-cartas-expedidas-list.php',
    cartasexpedidasnova:'cartorio-cartas-expedidas-nova.php',
    cartasexpedidascreate:'cartorio-cartas-expedidas-create.php',
    cartasexpedidasrules:'cartorio-cartas-expedidas-rules.php'
  }[route];
  return execFileSync(process.env.PHP_BIN || 'php',['-d','display_errors=stderr','-r',
    'require "fixture.php"; require "app/helpers/CartasExpedidasEstadoFluxo.php"; $_SERVER["REQUEST_METHOD"]="GET"; $_SESSION=["id_usuario"=>"T1"]; $GLOBALS["testrole"]='+JSON.stringify(role)+'; $_GET=["page"=>'+JSON.stringify(route)+',"piloto"=>"'+(embed?'1':'0')+'"]; require "'+'app/views/internal/'+file+'";'],
    {cwd:dir,encoding:'utf8'});
};

(async()=>{
  let browser;
  try {
    for(const f of fs.readdirSync(path.join(root,'app/views/internal'))) if(f.startsWith('_')&&f.endsWith('.php')) cpInternal(f);
    for(const f of ['cartorio-cartas-expedidas-list','cartorio-cartas-expedidas-nova','cartorio-cartas-expedidas-create','cartorio-cartas-expedidas-rules','partials/cartas-expedidas-v2-row','partials/cartas-expedidas-piloto-list','partials/cartas-expedidas-piloto-row','partials/cartas-expedidas-piloto-prioridade']) cpInternal(f+'.php');
    for(const f of ['CartasExpedidasPrioridade','CartasExpedidasEstadoFluxo','CartasExpedidasUiCopy','CartasExpedidasEnvioRegra','DeleteAuthorization']) copy('app/helpers/'+f+'.php');
    copy('app/access/ProfileNormalizer.php');
    stub('helpers','UsuarioPerfisAutorizacao','static function perfisDoUsuario($id){return [$GLOBALS["testrole"]];}');
    stub('helpers','ProcessoBlocoListagemHelper','static function appendBlocoData($db,$rows){return $rows;}');
    stub('helpers','RegistroMetadataHelper','static function viewerContextFromSession(){return [];} static function fromListRow(...$a){return ["show_edit"=>true,"edit_href"=>"/frame.php?page=cartasexpedidasedit"]; } static function linhasHistoricoV2CartasExpedidas(...$a){return [["rotulo"=>"Carta cadastrada","data_hora"=>"17/11/2026","autor"=>"Equipe sintética"]];}');
    stub('repositories','AlocacoesRegrasRepository','function findByRegra($key){return ["usuario_id"=>"T1"];}');
    stub('repositories','CartasExpedidasSubtarefasRepository','const TIPO_ENVIO="aguardando_envio",TIPO_DEVOLUCAO="aguardando_devolucao",ORDEM_ENVIO=1,ORDEM_DEVOLUCAO=2; static function resolverSubtarefaAtivaPorLista($rows){return $rows[0]??null;}');
    stub('services','CartasExpedidasService',`function listCartas(...$a){return ["total"=>2,"rows"=>[
       ["IdCarta"=>"C1","numero_processo"=>"0000041-00.2026.0.00.0000","Status"=>"aguardando envio","EtapaEfetiva"=>"envio","Comarca"=>"Una","Estado"=>"BA","Finalidade"=>"Citação","Prazo"=>30,"bloco_processo_abrir_href"=>"/frame.php?page=consultaprocessosedit","SubtarefasAll"=>[["id"=>"S1","tipo"=>"aguardando_envio"]]],
       ["IdCarta"=>"C2","numero_processo"=>"0000042-00.2026.0.00.0000","Status"=>"aguardando cumprimento","EtapaEfetiva"=>"devolucao","Comarca"=>"Uruçuca","Estado"=>"BA","Finalidade"=>"Intimação","Prazo"=>20,"SubtarefasAll"=>[["id"=>"S2","tipo"=>"aguardando_devolucao"]]]
    ]];} function getStatusCounts($f){return ["aguardando envio"=>1,"aguardando cumprimento"=>1];} function getFinalidadeCounts($f){return ["Citação"=>1,"Intimação"=>1];}`);
    put('fixture.php',`<?php
      class Security {static function generateCSRFToken(){return 'fixture-token';}}
      class Conection {static function getInstance(){return new self;}
       function getConnection(){
        $db=new PDO('sqlite::memory:');
        $db->exec("CREATE TABLE cartas_expedidas(id TEXT,prioridade_tarefa TEXT); INSERT INTO cartas_expedidas VALUES ('C1','critico'),('C2',NULL);
          CREATE TABLE usuarios(id_usuario TEXT,nome TEXT,sobrenome TEXT,cadastro_ativo INT); INSERT INTO usuarios VALUES ('T1','Lia','Martins',1);
          CREATE TABLE usuario_perfil(usuario TEXT,perfil TEXT); INSERT INTO usuario_perfil VALUES ('T1','ADMIN');");
        return $db;
      }}`);
    fs.symlinkSync(path.join(root,'public'),path.join(dir,'public'));
    const roleMap=new Map();
    browser=await chromium.launch({executablePath:process.env.CHROMIUM_BIN||undefined,headless:true});
    for(const [width,role,mayDelete] of [[1440,'ADMIN',true],[900,'CHEFIA',true],[767,'CHEFIA',true],[390,'DIRETORA',false],[320,'SERVIDOR-CARTORIO',false]]) {
      const p=await browser.newPage({viewport:{width,height:900}});
      p.setDefaultTimeout(5000);
      await p.addInitScript(()=>{Object.defineProperty(navigator,'clipboard',{value:{writeText:async text=>{window.copied=text;}}});});
      await p.route('**/*',r=>{
        const u=new URL(r.request().url());
        if(u.hostname!=='fixture.test') return r.abort();
        if(u.pathname==='/shell') {
          return r.fulfill({contentType:'text/html; charset=utf-8',body:'<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/css/shared/layout.css"><style>body{margin:0}</style><div class="layout-wraper--home"><nav class="layout-navbar">Menu flutuante</nav><iframe class="target-page" src="/frame.php?page=cartasexpedidasnova"></iframe></div>'});
        }
        if(u.pathname==='/frame.php') {
          const key=role+'|'+u.searchParams.get('page')+'|'+u.searchParams.get('piloto');
          if(!roleMap.has(key)) roleMap.set(key,render(u.searchParams.get('page'),role,u.searchParams.has('piloto')));
          return r.fulfill({contentType:'text/html; charset=utf-8',body:roleMap.get(key)});
        }
        const f=path.join(root,'public',u.pathname);
        if(u.pathname.startsWith('/assets/') && fs.existsSync(f)) return r.fulfill({path:f});
        return r.fulfill({status:404});
      });
      if(width<=767) {
        await p.goto('http://fixture.test/shell');
        const shellBar=await p.locator('.layout-navbar').boundingBox();
        const title=await p.frameLocator('iframe').locator('.ce-header h1').boundingBox();
        assert.ok(shellBar && title && title.y>=shellBar.y+shellBar.height+12,'floating shell must not overlap heading at '+width);
      }
      await p.goto('http://fixture.test/frame.php?page=cartasexpedidasnova');
      if(width<=767) {
        const [dest,actions,entry,search,status,goal]=await Promise.all(['.ce-entry .ce-destino','.ce-entry .ce-row-actions','.ce-entry','.ce-toolbar input[type=search]','.ce-toolbar select[name=status]','.ce-toolbar select[name=finalidade]'].map(sel=>p.locator(sel).first().boundingBox()));
        assert.ok(Math.abs((dest.y+dest.height/2)-(actions.y+actions.height/2))<2,'actions must share metadata row');
        assert.ok(entry.height<118,'mobile row should stay compact');
        assert.ok(search.width>width-50,'full-width mobile search');
        if(width<480) assert.ok(status.width>width-50 && goal.width>width-50,'use readable full-width filters on phone');
        else assert.ok(Math.abs(status.y-goal.y)<2,'filters should share the row on wider screens');
        assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'list must not overflow horizontally');
      }
      assert.equal(await p.locator('.ce-entry').count(),2);
      assert.equal(await p.locator('.ce-entry').first().locator('.ce-priority').getAttribute('aria-label'),'Crítico');
      assert.equal(await p.locator('.ce-entry').last().locator('.ce-priority').getAttribute('aria-label'),'Sem prioridade definida');
      await p.locator('.ce-cnj').first().click();
      assert.equal(await p.evaluate(()=>window.copied),'0000041-00.2026.0.00.0000');
      await p.locator('.ce-entry').first().locator('.ce-dropdown summary').click();
      assert.deepEqual(await p.locator('.ce-entry').first().locator('.ce-dropdown > div > *').allTextContents(),['Ver detalhes','Abrir processo','Texto para WhatsApp']);
      await p.getByRole('button',{name:'Ver detalhes'}).first().click();
      assert.equal(await p.getByRole('tabpanel',{name:'Resumo'}).isVisible(),true);
      assert.equal(await p.getByRole('link',{name:'Editar',exact:true}).count(),1);
      assert.equal(await p.getByRole('button',{name:'Excluir carta'}).count(),Number(mayDelete));
      assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'overflow at '+width);
      await p.getByRole('tab',{name:'Histórico (1)'}).click();
      assert.match(await p.getByRole('tabpanel',{name:'Histórico (1)'}).textContent(),/Carta cadastrada/);
      await p.getByRole('button',{name:'Fechar painel'}).click();
      await p.locator('.ce-entry').last().locator('.ce-dropdown summary').click();
      await p.locator('.ce-entry').last().getByRole('button',{name:'Ver detalhes'}).click();
      assert.equal(await p.getByRole('button',{name:'Reiniciar envio'}).count(),1);
      assert.equal(await p.getByRole('link',{name:'Editar',exact:true}).count(),0);
      await p.getByRole('button',{name:'Fechar painel'}).click();
      await p.getByRole('link',{name:'Novo cadastro'}).click();
      const create=p.frameLocator('#ce-panel iframe');
      assert.equal(await create.locator('#ce-prioridade-tarefa').inputValue(),'medio');
      await p.getByRole('button',{name:'Fechar painel'}).click();
      await p.getByRole('link',{name:'Regra de envio'}).click();
      const rules=p.frameLocator('#ce-panel iframe');
      assert.match(await rules.locator('.ce-rule-now').textContent(),/Lia Martins/);
      assert.equal(await rules.locator('[name=csrf_token]').count(),role==='ADMIN'||role==='DIRETORA'?1:0);
      await p.goto('http://fixture.test/frame.php?page=cartasexpedidas');
      assert.equal(await p.locator('.carta-expedida-v2-row').count(),2);
      assert.equal(await p.locator('#ce-panel').count(),0);
      await p.close();
    }
    console.log('PASS: rotas coexistem; cinco viewports; cópia, menu por etapa, abas, prioridade, regra e perfis.');
  } finally {
    if(browser) await browser.close();
    fs.rmSync(dir,{recursive:true,force:true});
  }
})().catch(error=>{console.error(error);process.exitCode=1;});
