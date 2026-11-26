/* Optional Chromium check for the real views, with synthetic read-only data. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '../..');
const render = require('./mobile-operations-fixture.cjs');
const setup = {
    dashboard: ['#dash-queue-guias-expedicao', '#dash-queue-expedidas-responsaveis', '#dash-queue-guias-expedicao-responsaveis', '#dash-alerts', '.dash-production-grid', '#dash-chart-cadastros-semana'],
    process: ['#btn-proc-datajud-sync', '#process-responsible-sector-select', '#proc-classe-atual', '#processo-assunto-descricao', '#processo-parte-nome', '#process-responsible-sector-submit'],
    create: ['#filter-processo', '#filter-denunciado', '#filter-tipo', '#filter-data-fato', '#cadastrar-btn', '#voltar-btn'],
    edit: ['#edit-processo', '#edit-observacoes', '#edit-tipo', '#edit-data-fato', '#cancelar-tarefa-btn', '#excluir-btn', '#salvar-btn', '.timeline-header', '.timeline .subtask-date']
};
const sample = {
    setor: {conectado:true,areas:['CARTORIO'],filas:[{key:'cartas_expedidas',type:'aguardando_envio',count:5},{key:'guias',type:'expedicao_guia',count:12},{key:'guias',type:'envio_guia',count:9}],responsaveis_regras:{cartas_expedidas_envio:'Pessoa responsável pelo envio',guias:'ResponsávelDeTesteComNomeBemComprido'},responsaveis_proprios:{cartas_expedidas_envio:false,guias:false}},
    alertas:[{key:'cumprimento_expedido_excedido',label:'Prazo para cumprimento de uma medida com descrição extensa',detail:'Acompanhar o prazo desta tarefa sintética',value:45,unit:'days'}],
    geral:{cartas_cadastradas:{expedidas:1730,recebidas:245,ultimos_30_dias:{labels:['24/11','25/11','26/11'],expedidas:[2,4,2],recebidas:[0,1,0]}}},
    usuario:{oficios_expedidos_30d:1234,medidas_protetivas_expedidas_30d:123}
};
(async () => {
    const html = render(root);
    const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_BIN || undefined, headless: true });
    try {
        for (const [name, selectors] of Object.entries(setup)) {
            for (const width of [320, 390, 430, 768, 1365]) {
                const page = await browser.newPage({ viewport: { width, height: 850 } });
                let syncCount = 0;
                let dashboardUnavailable = false;
                await page.route('**/*', route => {
                    const u = new URL(route.request().url());
                    if (u.hostname !== 'fixture.test') return route.abort();
                    if (u.pathname === '/') return route.fulfill({ contentType: 'text/html; charset=utf-8', body: html[name] });
                    if (u.pathname === '/api/dashboard/resumo.php') return route.fulfill(dashboardUnavailable
                        ? { status:503, json:{error:'Indicadores temporariamente indisponíveis.'} } : { json:sample });
                    if (u.pathname === '/api/processos/datajud-sync.php') {
                        syncCount++;
                        return route.fulfill({ status: 503, json: { error:'Fonte temporariamente indisponível.' } });
                    }
                    if (u.pathname.startsWith('/assets/')) {
                        const f = path.join(root, 'public', u.pathname);
                        if (fs.existsSync(f)) return route.fulfill({ path: f });
                    }
                    return route.fulfill({ status: 404 });
                });
                await page.goto('http://fixture.test/');
                await page.evaluate(() => document.fonts.ready.then(() => true));
                if (name === 'dashboard') {
                    await page.locator('#dash-sector-column').waitFor();
                    assert.equal(await page.locator('#dash-queue-guias-expedicao').innerText(), '12');
                    assert.equal(await page.locator('#dash-queue-expedidas').innerText(), '5');
                    assert.match(await page.locator('#dash-queue-expedidas-responsaveis').innerText(), /Responsável: Pessoa/);
                }
                const metrics = await page.evaluate(selectors => {
                    const container = document.querySelector('.card-page, .dashboard-shell').getBoundingClientRect();
                    const out = selectors.map(selector => {
                        const r = document.querySelector(selector)?.getBoundingClientRect();
                        return r ? [selector, Math.round(r.left), Math.round(r.right), Math.round(r.height)] : [selector, null];
                    });
                    return { width: document.documentElement.scrollWidth, bounds:[Math.round(container.left), Math.round(container.right)], controls: out };
                }, selectors);
                console.log(JSON.stringify({ name,viewport:width,...metrics }));
                if (process.env.MOBILE_MEASURE_ONLY !== '1') {
                    assert.equal(metrics.width, width, name + ' must not overflow viewport');
                    for (const [label,left,right,height] of metrics.controls) {
                        assert.ok(left !== null && left >= metrics.bounds[0] && right <= metrics.bounds[1] + 1, label + ' must fit container');
                        if (width <= 430 && label.startsWith('#') && (await page.locator(label).evaluate(el=>el.matches('button,input,select,textarea')))) assert.ok(height >= 44, label + ' touch target');
                    }
                    if (name === 'dashboard' && width <= 430) {
                        assert.equal(await page.locator('.dash-production-grid').evaluate(el => getComputedStyle(el).gridTemplateColumns.split(' ').length), 1);
                        assert.ok(await page.locator('.dashboard-column > header').first().evaluate(el => el.getBoundingClientRect().height < 100));
                        if (width === 320) {
                            dashboardUnavailable = true;
                            await page.reload();
                            await page.getByText('Indicadores temporariamente indisponíveis.', {exact:true}).waitFor();
                            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth), 320);
                        }
                    }
                    if (name === 'process' && width === 390) {
                        await page.locator('#btn-proc-datajud-sync').focus();
                        await page.keyboard.press('Enter');
                        await page.getByText('Fonte temporariamente indisponível.', {exact:true}).waitFor();
                        assert.equal(syncCount, 1);
                    }
                    if (name === 'edit' && width === 390) {
                        await page.locator('#edit-observacoes').fill('Nova observação sintética');
                        assert.equal(await page.locator('#edit-observacoes').inputValue(), 'Nova observação sintética');
                    }
                }
                await page.close();
            }
        }
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
