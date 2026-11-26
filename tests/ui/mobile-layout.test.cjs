const fs=require('node:fs'),path=require('node:path'),{execFileSync}=require('node:child_process');
const assert=require('node:assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const render=require('./mobile-layout-fixture.cjs');
const current=path.resolve(__dirname,'../..');
const roots=[...(process.env.MOBILE_BASELINE?[path.resolve(process.env.MOBILE_BASELINE)]:[]),current];
const evidence=process.env.MOBILE_EVIDENCE_DIR;
if(evidence)fs.mkdirSync(evidence,{recursive:true});
(async()=>{
 const b=await chromium.launch({executablePath:process.env.CHROMIUM_BIN || undefined,headless:true});
 const cached=new Map();
 for(const [ix,root] of roots.entries()){
  const html=render(root);
  for(const name of ['profile','student']){
   for(const w of [320,390,430,681,1365]){
    const p=await b.newPage({viewport:{width:w,height:900}});
    let relax, polling=false;
    await p.route('**/*',async route=>{
     const u=new URL(route.request().url());
     if(u.hostname==='fixture.test'){
       if(u.pathname==='/') return route.fulfill({contentType:'text/html; charset=utf-8',body:html[name]});
       if(u.pathname.startsWith('/assets/')){const f=path.join(root,'public',u.pathname);return fs.existsSync(f)?route.fulfill({path:f}):route.fulfill({status:404});}
       if(u.pathname.includes('status-bulk')){polling=true;await new Promise(f=>{relax=f});return route.fulfill({status:503,contentType:'application/json',body:'{}'}).catch(()=>{});}
       return route.fulfill({status:404});
     }
     if(process.env.MOBILE_FETCH_FONTS==='1' && ['fonts.googleapis.com','fonts.gstatic.com','cdn-uicons.flaticon.com'].includes(u.hostname)){
      try{const key=u.href;if(!cached.has(key))cached.set(key,execFileSync('curl',['-fsSL','--max-time','4','-A','Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/131.0.0.0 Safari/537.36',key],{stdio:['ignore','pipe','ignore']}));return route.fulfill({body:cached.get(key),headers:{'content-type':u.hostname==='fonts.gstatic.com'?'font/woff2':'text/css','access-control-allow-origin':'*'}});}catch{}
     }
     return route.abort();
    });
    await p.goto('http://fixture.test/',{waitUntil:'domcontentloaded'});
    await p.evaluate(()=>document.fonts.ready.then(()=>true));
    const m=await p.evaluate((name)=>{
      const box=s=>{const e=document.querySelector(s);if(!e)return null;const r=e.getBoundingClientRect();return [r.left,r.right,r.width,r.top,r.bottom,r.height].map(x=>Math.round(x));};
      const root='[data-pauta="FIXTURE"]';const link=root+' [data-audience-link-pauta] a';
      return {vp:innerWidth,scroll:document.documentElement.scrollWidth,body:document.body.scrollWidth,
       header:box('.header-page'),card:box('.card-page'),font:document.fonts.check('12px "Syne Mono"'),
       ...(name==='profile'?{control:box('#edit-nome-completo'),phone:box('#edit-telefone')}:
       {row:box(root+' .register-line'),proc:box(root+' [data-processo-bloco]'),link:box(link),action:box(root+' .painel-estudante-inscricao-ui'),button:box(root+' .btn-inscrever-ata'),off:box('[data-pauta="OFF"]')})};
    },name);
    if(root===current){
      assert.equal(m.scroll,w,name+' width '+w);
      assert.ok(m.header[0]>=0 && m.header[1]<=w);
      if(name==='profile'){
        assert.ok(m.control[0]>=m.card[0] && m.control[1]<=m.card[1], 'nome dentro do cartão');
        if(w<=430)assert.ok(m.control[5]>=44 && m.phone[5]>=44);
      } else {
        assert.ok(m.row[0]>=m.card[0] && m.row[1]<=m.card[1] && m.proc[0]>=m.row[0] && m.proc[1]<=m.row[1], 'bloco dentro do cartão');
        if(w<1000)assert.ok(m.link[4]<m.action[3] && m.button[5]>=44 && m.link[5]>=44, 'link e inscrição sem sobreposição e com alvo tátil');
        await p.locator('[data-pauta="FIXTURE"] [data-audience-link-pauta] a').focus();
        assert.ok(await p.locator('[data-pauta="FIXTURE"] [data-audience-link-pauta] a').evaluate(e=>e===document.activeElement),'link com foco');
      }
    }
    if(evidence && (w===390 || w===1365))await p.screenshot({path:path.join(evidence,ix+'-'+name+'-'+w+'.png')});
    console.log(JSON.stringify({ix,name,w,m,polling}));
    if(relax){
      relax();
      if(root===current){
        await p.locator('[data-pauta="FIXTURE"][data-indisponivel="1"]').waitFor({timeout:3000});
        assert.equal(await p.locator('[data-pauta="FIXTURE"] .register-line').count(),0, 'estado indisponível não retém interface sensível');
        assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth),w);
      }
    }
    await p.close();
   }
  }
 }
 await b.close();
})().catch(e=>{console.error(e);process.exit(1)});
