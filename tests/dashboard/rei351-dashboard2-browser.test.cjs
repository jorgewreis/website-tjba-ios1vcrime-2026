const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const base = process.env.REI351_PREVIEW_URL || 'http://127.0.0.1:8173';
const evidence = process.env.REI351_EVIDENCE_DIR || fs.mkdtempSync(path.join(os.tmpdir(), 'rei351-ui-evidence-'));
fs.mkdirSync(evidence, { recursive: true });

async function prepare(page) {
  await page.route('**/*', route => {
    const url = new URL(route.request().url());
    if (url.hostname === 'fonts.googleapis.com') {
      return route.fulfill({ status: 200, contentType: 'text/css', body: '/* External fonts disabled in isolated synthetic preview. */' });
    }
    return url.hostname === '127.0.0.1' && url.port === '8173' ? route.continue() : route.abort();
  });
}

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_BIN || undefined });
  try {
    for (const [name, routePath] of [['dashboard', '/preview-dashboard'], ['dashboard2', '/preview-dashboard2']]) {
      const desktop = await browser.newPage({ viewport: { width: 1680, height: 1000 }, deviceScaleFactor: 1 });
      await prepare(desktop);
      const pageErrors = [];
      const consoleErrors = [];
      desktop.on('pageerror', error => pageErrors.push(error.message));
      desktop.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
      const response = await desktop.goto(base + routePath, { waitUntil: 'domcontentloaded' });
      assert.equal(response.status(), 200, `${name} HTTP`);
      await desktop.getByText('DADOS SINTÉTICOS · PRÉVIA VISUAL LOCAL · NÃO É HOMOLOGAÇÃO').waitFor();
      await desktop.waitForTimeout(250);
      const desktopState = await desktop.evaluate(() => ({
        width: innerWidth,
        documentWidth: document.documentElement.scrollWidth,
        dashboard: Boolean(document.querySelector('#dashboard-interno, #dashboard2')),
      }));
      assert.equal(desktopState.documentWidth, desktopState.width, `${name} sem overflow desktop`);
      assert.equal(desktopState.dashboard, true, `${name} conteúdo disponível`);
      if (name === 'dashboard2') {
        const rows = await desktop.locator('.dashboard2-row').evaluateAll(items => items.map(row => [...row.querySelectorAll(':scope > section h2')].map(h => h.textContent.trim())));
        assert.deepEqual(rows, [
          ['Visão geral da Vara', 'Meu trabalho', 'Fluxo operacional'],
          ['Lotação', 'Criticidade', 'Radar de prazos', 'Qualidade operacional', 'Prontidão das audiências'],
          ['Agenda', 'Próxima ação', 'Evolução de produtividade', 'Alertas críticos'],
        ]);
        const desktopLayout = await desktop.locator('.dashboard2-row').evaluateAll(items => items.map(row => getComputedStyle(row).gridTemplateColumns));
        assert.equal(desktopLayout[0].split(' ').length, 3, '3 cards na primeira faixa desktop');
        assert.equal(desktopLayout[1].split(' ').length, 5, '5 cards na segunda faixa desktop');
        assert.equal(desktopLayout[2].split(' ').length, 3, 'terceira faixa usa três colunas, com alertas sob a evolução');
        const alerts = await desktop.locator('.dashboard2-alerts').boundingBox();
        const evolution = await desktop.locator('.dashboard2-evolution').boundingBox();
        assert.ok(alerts.y >= evolution.y + evolution.height, 'alertas ficam abaixo da evolução');
        assert.ok(alerts.height >= 112, 'card de alertas comporta sua cobertura e estado');
        assert.equal(await desktop.locator('.dashboard2-stat').count(), 8, 'oito indicadores da Vara');
        assert.equal(await desktop.locator('#d2-volume-chart').isVisible(), true, 'série sintética completa desenha o gráfico');
        assert.equal((await desktop.locator('#d2-letters-sent').textContent()).trim(), '105', 'total expedidas concilia com a série sintética');
        assert.equal((await desktop.locator('#d2-letters-received').textContent()).trim(), '73', 'total recebidas concilia com a série sintética');
        assert.equal(await desktop.locator('#d2-volume-data-body tr').count(), 30, 'tabela acessível contém os 30 dias do gráfico');
        assert.equal(await desktop.locator('#d2-volume-data-caption').textContent(), 'Dados diários do gráfico de cadastros de cartas, últimos 30 dias');
        assert.equal((await desktop.locator('#d2-agenda').textContent()).trim(), 'Indisponível', 'agenda sem contrato completo continua indisponível');
        const readiness = await desktop.locator('[aria-labelledby="d2-readiness-title"] .dashboard2-state-grid').evaluate(el => el.scrollWidth <= el.clientWidth);
        assert.equal(readiness, true, 'indicadores de prontidão não transbordam o card');
      }
      await desktop.screenshot({ path: path.join(evidence, `${name}-desktop.png`), fullPage: true });
      assert.deepEqual(pageErrors, [], `${name} sem erro de JavaScript`);
      assert.deepEqual(consoleErrors, [], `${name} sem erros de console`);
      await desktop.close();

      const mobile = await browser.newPage({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 1, isMobile: true, hasTouch: true });
      await prepare(mobile);
      const mobileErrors = [];
      mobile.on('pageerror', error => mobileErrors.push(error.message));
      mobile.on('console', message => { if (message.type() === 'error') mobileErrors.push(message.text()); });
      const mobileResponse = await mobile.goto(base + routePath, { waitUntil: 'domcontentloaded' });
      assert.equal(mobileResponse.status(), 200, `${name} mobile HTTP`);
      await mobile.getByText('DADOS SINTÉTICOS · PRÉVIA VISUAL LOCAL · NÃO É HOMOLOGAÇÃO').waitFor();
      await mobile.waitForTimeout(250);
      const mobileState = await mobile.evaluate(() => ({ width: innerWidth, documentWidth: document.documentElement.scrollWidth }));
      assert.equal(mobileState.documentWidth, mobileState.width, `${name} sem overflow mobile`);
      if (name === 'dashboard2') {
        const tops = await mobile.locator('.dashboard2-row > section').evaluateAll(items => items.map(item => item.getBoundingClientRect().top));
        assert.ok(tops.every((top, index) => index === 0 || top >= tops[index - 1]), 'ordem dos cards preservada no celular');
        assert.equal(await mobile.locator('.dashboard2-stat').count(), 8, 'oito indicadores preservados no celular');
        assert.equal(await mobile.locator('#d2-volume-chart').isVisible(), true, 'série sintética completa desenha no celular');
        assert.equal(await mobile.locator('#d2-volume-data-body tr').count(), 30, 'dados do gráfico acessíveis no celular');
      }
      await mobile.screenshot({ path: path.join(evidence, `${name}-mobile.png`), fullPage: true });
      assert.deepEqual(mobileErrors, [], `${name} mobile sem erro de JavaScript/console`);
      await mobile.close();
    }
    console.log(`PASS desktop/mobile para as duas páginas; evidências sintéticas: ${evidence}`);
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
