'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname,'../../public');
const css = name => '<link rel="stylesheet" href="/assets/css/' + name + '.css">';
const js = name => '<script defer src="/assets/js/' + name + '.js"></script>';
const shared = css('shared/painel-deslizante') + js('shared/painel-deslizante');
const meta = '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1">';
const fixture = id => (id === 'processo')
    ? meta + shared + css('shared/processo-ficha-modal') + js('shared/processo-ficha-modal') +
        '<div id="barra" style="position:fixed;inset:16px 0 auto;height:64px">Menu flutuante</div>' +
        '<a id="abrir" style="display:block;margin-top:106px;width:max-content" href="/frame.php?page=consultaprocessosedit&amp;id=FICTICIO">Ver processo</a>'
    : meta + shared + css('modules/cartorio/cartas-expedidas-piloto') + js('modules/cartorio/cartas-expedidas/piloto') +
        '<body class="ce-piloto"><main class="ce-workspace"><section class="ce-queue">' +
        '<button id="abrir" data-ce-details="fixture">Abrir carta</button><template id="fixture">Carta fictícia</template></section>' +
        '<dialog id="ce-layer" class="ce-layer"><aside id="ce-panel" class="ce-panel" data-painel-deslizante hidden>' +
        '<header><h2 id="ce-panel-title"></h2><button id="fechar" data-ce-close>Fechar</button></header>' +
        '<div id="ce-panel-content"></div></aside></dialog><p id="ce-feedback" hidden></p></main></body>';

(async () => {
    const browser = await chromium.launch({executablePath:process.env.CHROMIUM_BIN || undefined});
    try {
        for (const width of [1173,390]) for (const kind of ['processo','carta']) {
            const p = await browser.newPage({viewport:{width,height:725}});
            await p.addInitScript(() => {
                const original = HTMLDialogElement.prototype.showModal;
                HTMLDialogElement.prototype.showModal = function (...args) {
                    const result = original.apply(this,args);
                    const el = this.querySelector('[data-painel-deslizante]');
                    if (el) window.inicial = {left:el.getBoundingClientRect().left,right:innerWidth};
                    return result;
                };
            });
            await p.route('**/*', rt => {
                const u = new URL(rt.request().url());
                if (u.hostname !== 'fixture.test') return rt.abort();
                if(u.pathname === '/fixture') return rt.fulfill({contentType:'text/html',body:fixture(kind)});
                if(u.pathname === '/frame.php') return rt.fulfill({contentType:'text/html',body:meta+css('shared/processo-ficha')+
                    '<body class="ficha-processo"><main class="ficha-shell"><header class="ficha-header"><h1>Ficha fictícia</h1></header></main></body>'});
                const f=path.join(root,u.pathname);
                if(u.pathname.startsWith('/assets/') && fs.existsSync(f)) return rt.fulfill({path:f});
                return rt.fulfill({status:404});
            });
            await p.goto('http://fixture.test/fixture');
            const card = p.locator('[data-painel-deslizante]');
            const open = p.locator('#abrir');
            const close = kind === 'carta' ? p.locator('#fechar') : p.getByRole('button',{name:'Fechar ficha do processo'});
            const size = await open.boundingBox();
            await open.click();
            const start = await p.evaluate(()=>window.inicial);
            assert.ok(start.left >= width,kind+' deve começar integralmente fora da janela');
            const entrada = await card.evaluate(el => {
                const transition=el.getAnimations().find(a=>a.transitionProperty==='transform');
                transition.pause(); transition.currentTime=400;
                const x=el.getBoundingClientRect().left;
                return [transition.effect.getTiming().duration,x];
            });
            assert.equal(entrada[0],1300);
            // Fecha ainda entrando: a saída não pode teletransportar para a posição aberta.
            const saida = await card.evaluate(el => {
                const x=el.getBoundingClientRect().left;
                el.querySelector('header button').click();
                const v=el.getAnimations().find(a=>a.transitionProperty==='transform');
                const delta=Math.abs(el.getBoundingClientRect().left-x);
                v.pause(); v.currentTime=1699;
                const fora=el.getBoundingClientRect().left>=innerWidth;
                const duracao=v.effect.getTiming().duration;
                v.finish();
                return [duracao,delta,fora];
            });
            assert.equal(saida[0],1700);
            assert.ok(saida[1] < 3,'fechamento interrompido não pode saltar: '+saida[1]);
            assert.equal(saida[2],true);
            await card.waitFor({state:'hidden'});
            assert.deepEqual(await open.boundingBox(),size);

            await open.click();
            await card.evaluate(el => el.getAnimations().forEach(a=>a.finish()));
            const box = await card.boundingBox();
            assert.ok(box.y >= (width > 640 ? 90 : 68),kind+' mantém cabeçalho visível');
            if(kind==='processo'){
                if(width===1173) assert.ok(box.width<=900,'card mais estreito no mesmo tamanho da captura');
                const h=p.frameLocator('[data-ficha-modal-content]').locator('h1');
                await h.waitFor();
                await p.waitForFunction(()=>document.querySelector('[data-ficha-modal-content]')?.contentDocument.documentElement.classList.contains('ficha-no-painel'));
                assert.ok(await h.evaluate(el=>parseFloat(getComputedStyle(el).fontSize)<=32),'título mais compacto');
            }
            await p.emulateMedia({reducedMotion:'reduce'});
            await close.click();
            await card.waitFor({state:'hidden'});
            await p.close();
        }
        console.log('PASS: cartas e ficha começam fora da área visível; 1300/1700 ms, reversão sem salto, largura, folga e fonte.');
    } finally { await browser.close(); }
})().catch(e=>{ console.error(e); process.exitCode=1; });
