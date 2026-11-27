'use strict';
const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname,'../..');
const ficha = '/frame.php?page=consultaprocessosedit&id=PROC-FICTICIO';
const modalAssets = '<link rel="stylesheet" href="/assets/css/shared/processo-ficha-modal.css"><script src="/assets/js/shared/processo-ficha-modal.js" defer></script>';
const doc = (head, body) => '<!doctype html><meta name="viewport" content="width=device-width, initial-scale=1">' + head + '<body>' + body + '</body>';
(async () => {
    const nav = fs.readFileSync(path.join(root,'public/home.php'),'utf8');
    assert.match(nav,/id="frame" data-ficha-workspace/);
    assert.match(nav, /_processo_ficha_modal_assets\.php/);
    const browser = await chromium.launch({executablePath:process.env.CHROMIUM_BIN || undefined});
    try {
        for (const width of [1440,390]) {
            const page = await browser.newPage({viewport:{width,height:850},reducedMotion:width===390?'reduce':'no-preference'});
            let listLoads = 0, editLoads = 0;
            await page.route('**/*', rt => {
                const u = new URL(rt.request().url());
                if (u.hostname !== 'fixture.test') return rt.abort();
                const html = body => rt.fulfill({contentType:'text/html; charset=utf-8',body});
                if (u.pathname === '/home.php') return html(doc(modalAssets,
                    '<iframe title="Área da Vara" data-ficha-workspace src="/frame.php?page=lista&amp;status=pendente" style="width:100%;height:730px;border:0"></iframe>'));
                if (u.pathname === '/frame.php' && u.searchParams.get('page') === 'lista') {
                    listLoads++;
                    return html(doc(modalAssets + '<script src="/assets/js/shared/navigate-buttons.js" defer></script>',
                        '<input id="filtro" value="pendente"><p><a href="' + ficha + '">Abrir processo</a>' +
                        '<span role="button" tabindex="0" data-nav-href="' + ficha + '">Ver processo</span></p><div style="height:1200px"></div>'));
                }
                if (u.pathname === '/frame.php' && u.searchParams.get('page') === 'consultaprocessosedit') {
                    const modo = u.searchParams.get('modo') === 'editar';
                    if(modo) editLoads++;
                    const query = new URL(u);
                    if (modo) query.searchParams.delete('modo'); else query.searchParams.set('modo','editar');
                    const action = modo ? 'Voltar aos detalhes' : 'Editar dados';
                    return html(doc(modalAssets, '<h1>' + (modo ? 'Editar dados do processo' : 'Detalhes do processo') +
                        '</h1><a href="' + query.pathname+query.search.replaceAll('&','&amp;') + '">' + action + '</a>' +
                        '<p><a href="/frame.php?page=lista" data-fechar-ficha>Voltar</a></p>'));
                }
                const f=path.join(root,'public',u.pathname);
                if(u.pathname.startsWith('/assets/') && fs.existsSync(f)) return rt.fulfill({path:f});
                return rt.fulfill({status:404});
            });
            await page.goto('http://fixture.test/home.php');
            const source = page.frameLocator('[data-ficha-workspace]');
            const atalho = source.getByRole('link',{name:'Abrir processo'});
            await source.locator('#filtro').fill('alterado sem recarregar');
            const originalBox = await atalho.boundingBox();
            await atalho.click();
            const dialog = page.getByRole('dialog',{name:'Ficha do processo'});
            const view = page.frameLocator('[data-ficha-modal-content]');
            await view.getByRole('heading',{name:'Detalhes do processo'}).waitFor();
            assert.ok(await dialog.isVisible());
            const style = await page.locator('.processo-ficha-modal').evaluate(e=>[getComputedStyle(e).position,getComputedStyle(e).boxShadow]);
            assert.equal(style[0],'absolute');
            assert.notEqual(style[1],'none');
            assert.deepEqual(await atalho.boundingBox(),originalBox);
            await view.getByRole('link',{name:'Editar dados'}).click();
            await view.getByRole('heading',{name:'Editar dados do processo'}).waitFor();
            assert.equal(editLoads,1);
            await view.getByRole('link',{name:'Voltar aos detalhes'}).click();
            await view.getByRole('heading',{name:'Detalhes do processo'}).waitFor();
            await view.getByRole('link',{name:'Voltar',exact:true}).click();
            await dialog.waitFor({state:'hidden'});
            assert.equal(listLoads,1);
            assert.equal(await source.locator('#filtro').inputValue(),'alterado sem recarregar');
            assert.ok(await atalho.evaluate(e=>e.ownerDocument.activeElement===e),'foco restaurado');
            await source.getByRole('button',{name:'Ver processo'}).press('Enter');
            await view.getByRole('heading',{name:'Detalhes do processo'}).waitFor();
            await view.getByRole('link',{name:'Editar dados'}).press('Escape');
            await dialog.waitFor({state:'hidden'});
            assert.equal(listLoads,1);
            await page.goto('http://fixture.test/frame.php?page=lista&status=pendente');
            await page.getByRole('link',{name:'Abrir processo'}).click();
            await view.getByRole('heading',{name:'Detalhes do processo'}).waitFor();
            await page.getByRole('button',{name:'Fechar ficha do processo'}).click();
            await dialog.waitFor({state:'hidden'});
            assert.equal(new URL(page.url()).searchParams.get('page'),'lista');
            await page.goto('http://fixture.test'+ficha);
            assert.ok(await page.getByRole('heading',{name:'Detalhes do processo'}).isVisible(),'link direto permanece válido');
            await page.close();
        }
        console.log('PASS: drawer global e direto, foco, teclado, edição, origem intacta, 1440/390 px.');
    } finally { await browser.close(); }
})().catch(e=>{ console.error(e); process.exitCode=1; });
