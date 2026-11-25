const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),crypto=require('node:crypto');
const {execFileSync}=require('node:child_process');
const os=require('node:os');
const root=path.resolve(__dirname,'../../..'),out=fs.mkdtempSync(path.join(os.tmpdir(),'rei73-oficios-ui-evidence-'));
console.log('Evidence directory:',out);
let playwright,playwrightEntry;
try {
 playwrightEntry=require.resolve(process.env.REI73_PLAYWRIGHT_MODULE||'playwright');
 playwright=require(playwrightEntry);
 assert.ok(playwright.chromium,'Playwright Chromium API unavailable');
} catch(error) {
 console.error('PREREQUISITE FAILED: supply installed playwright via normal Node resolution or REI73_PLAYWRIGHT_MODULE. No dependency/browser downloads are performed. '+error.message);
 process.exit(2);
}
const {chromium}=playwright;
const php=process.env.REI73_PHP||'php';
let states;
try {states=JSON.parse(execFileSync(php,[path.join(__dirname,'render.php')],{encoding:'utf8',timeout:15000}));}
catch(error){fs.writeFileSync(path.join(out,'failure.json'),JSON.stringify({phase:'render',error:error.stack,stderr:String(error.stderr||'')},null,2));console.error('Render failed; evidence:',out);process.exit(1);}
const trackedStatus=execFileSync('git',['status','--porcelain','--untracked-files=no'],{cwd:root,encoding:'utf8'});
const resourceHashes={},fontReplacements=[];
fs.writeFileSync(path.join(out,'states.json'),JSON.stringify(states,null,2));
let stage='envio',mode=400,release,documents=0;const posts=[],checks=[],errors=[],consoleErrors=[],unexpected=[];
function html(){return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Ofícios — auditoria sintética</title>'+['reset','color','layout','page','form','table'].map(x=>'<link rel="stylesheet" href="/assets/css/shared/'+x+'.css">').join('')+'</head><body><h1>Ofícios do Cartório — dados fictícios</h1><aside id="notifications" role="status"></aside>'+states[stage].row+'<script>window.showToast=p=>{window.__toasts=(window.__toasts||[]).concat([p]);document.getElementById("notifications").textContent=p.type+": "+p.message};</script><script src="/assets/js/shared/oficios-list.js"></script>'+states[stage].scripts+'</body></html>';}
(async()=>{const browser=await chromium.launch({headless:true});try{
 const ctx=await browser.newContext({viewport:{width:1440,height:900},serviceWorkers:'block'});
 await ctx.route('**/*',async route=>{
  const req=route.request(),url=new URL(req.url());
  if(url.origin==='https://fonts.googleapis.com'){fontReplacements.push({url:url.href,bodySha256:crypto.createHash('sha256').update('').digest('hex'),action:'replaced locally; no network'});return route.fulfill({contentType:'text/css',body:''});}
  if(url.origin!=='http://oficios.synthetic.test'){unexpected.push(url.href);return route.abort();}
  if(url.pathname==='/list'){documents++;return route.fulfill({contentType:'text/html',body:html()});}
  if(url.pathname==='/api/cartorio-oficios/concluir-subtarefa.php'){
   assert.equal(req.method(),'POST');const body=req.postDataJSON();assert.equal(body.subtarefa_id,stage==='envio'?'SYN-SEND':'SYN-JOIN');
   posts.push({stage,mode,body});await new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(new Error('Synthetic response was not released')),10000);release=()=>{clearTimeout(timer);resolve();};});
   if(mode==='network')return route.abort('failed');
   if(mode==='invalid-json')return route.fulfill({status:200,contentType:'application/json',body:'synthetic invalid JSON'});
   if(mode==='success'){stage=stage==='envio'?'juntada':'finalizada';return route.fulfill({json:{success:true,tarefa_principal_concluida:stage==='finalizada'}});}
   return route.fulfill({status:mode,json:{error:'Recusa sintética '+mode}});
  }
  if(url.pathname.startsWith('/assets/')){const p=path.resolve(root,'public','.'+url.pathname);assert.ok(p.startsWith(path.resolve(root,'public')+path.sep));if(fs.existsSync(p)){resourceHashes[path.relative(root,p).split(path.sep).join('/')]=crypto.createHash('sha256').update(fs.readFileSync(p)).digest('hex');return route.fulfill({path:p});}}
  unexpected.push(url.href);return route.abort();
 });
 const page=await ctx.newPage();page.setDefaultTimeout(10000);page.on('pageerror',e=>errors.push(e.message));page.on('console',m=>{if(m.type()==='error')consoleErrors.push({message:m.text(),url:m.location().url});});
 await page.goto('http://oficios.synthetic.test/list');assert.match(await page.title(),/Ofícios/);assert.match(await page.locator('body').innerText(),/Ofício fictício/);
 async function expand(){await page.locator('label.button-table').click();assert.equal(await page.locator('#subtask-SYN-OFFICIO').getAttribute('aria-hidden'),'false');}
 await expand();assert.equal(await page.locator('.btn-concluir-subtarefa-oficio').count(),1);assert.equal(await page.locator('[role=progressbar]').getAttribute('aria-valuenow'),'0');
 await page.screenshot({path:path.join(out,'01-envio.png'),fullPage:true});
 for(const response of [401,403,400,500,'network','invalid-json']){
  mode=response;release=null;const prior=documents;const btn=page.locator('.btn-concluir-subtarefa-oficio');await btn.click();
  await page.waitForFunction(()=>document.querySelector('.btn-concluir-subtarefa-oficio').disabled);
  for(let i=0;!release&&i<1000;i++)await new Promise(r=>setTimeout(r,5));assert.ok(release,'Intercepted request missing');
  assert.equal(await btn.isDisabled(),true);release();
  await page.waitForFunction(()=>!document.querySelector('.btn-concluir-subtarefa-oficio').disabled);
  assert.equal(documents,prior);assert.equal(stage,'envio');assert.equal(await page.locator('[role=progressbar]').getAttribute('aria-valuenow'),'0');
  assert.equal(await page.evaluate(()=>window.__toasts.at(-1).type),'error');checks.push({response,result:'button recovered; no reload or stage change'});
 }
 await page.screenshot({path:path.join(out,'02-error-recovered.png'),fullPage:true});
 for(const expected of ['juntada','finalizada']){
  mode='success';release=null;const prior=documents;await page.locator('.btn-concluir-subtarefa-oficio').click();
  for(let i=0;!release&&i<1000;i++)await new Promise(r=>setTimeout(r,5));assert.ok(release,'Intercepted request missing');
  const navigation=page.waitForEvent('load');release();await navigation;assert.equal(stage,expected);assert.equal(documents,prior+1);
  assert.equal(await page.evaluate(()=>window.__toasts.at(-1).type),'success');
  if(expected==='juntada'){await expand();assert.equal(await page.locator('.btn-concluir-subtarefa-oficio').getAttribute('data-subtarefa-id'),'SYN-JOIN');assert.equal(await page.locator('[role=progressbar]').getAttribute('aria-valuenow'),'1');}
  else assert.equal(await page.locator('.btn-concluir-subtarefa-oficio').count(),0);
  await page.screenshot({path:path.join(out,expected+'.png'),fullPage:true});
  await page.reload();assert.equal(await page.locator('.btn-concluir-subtarefa-oficio').count(),expected==='juntada'?1:0);if(expected==='juntada')await expand();
  checks.push({expected,result:'success toast; one reload; state survives manual reload'});
 }
 assert.deepEqual(errors,[]);assert.deepEqual(unexpected,[]);
 assert.equal(consoleErrors.length,5,'Only four deliberate HTTP errors and one deliberate network error are expected');
 for(const code of [401,403,400,500])assert.equal(consoleErrors.filter(e=>e.message.includes('status of '+code+' ')).length,1);
 assert.equal(consoleErrors.filter(e=>e.message==='Failed to load resource: net::ERR_FAILED').length,1);
 assert.ok(consoleErrors.every(e=>e.url==='http://oficios.synthetic.test/api/cartorio-oficios/concluir-subtarefa.php'),'Unexpected console error source');
 const files=['app/views/internal/cartorio-oficios-list.php','public/assets/js/shared/oficios-list.js'];
 fs.writeFileSync(path.join(out,'evidence.json'),JSON.stringify({base:execFileSync('git',['rev-parse','HEAD'],{cwd:root,encoding:'utf8'}).trim(),trackedStatus,browser:browser.version(),playwrightEntry,node:process.version,renderer:states._provenance,checks,posts,errors,consoleErrors,unexpected,resourceHashes,fontReplacements,hashes:Object.fromEntries(files.concat(['tests/ui/rei73-oficios-browser/run.cjs','tests/ui/rei73-oficios-browser/render.php']).map(f=>[f,crypto.createHash('sha256').update(fs.readFileSync(path.join(root,f))).digest('hex')])),limits:'Real PHP row and inline action fragments, real shared JS/CSS. Synthetic view states and intercepted responses; notification sink adapter. Google font CSS replaced by empty local response. No frame/login/auth/backend/database or sequence-rule proof. No production.'},null,2));
 console.log('PASS Chromium',browser.version(),checks.length,'scenarios;',posts.length,'POSTs; evidence',path.join(out,'evidence.json'));
}finally{await browser.close();}})().catch(e=>{fs.writeFileSync(path.join(out,'failure.json'),JSON.stringify({error:e.stack,checks,posts,errors,consoleErrors,unexpected},null,2));console.error(e);console.error('Evidence directory:',out);process.exitCode=1;});
