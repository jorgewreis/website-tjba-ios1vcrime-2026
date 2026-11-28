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
    return url.hostname === '127.0.0.1' && url.port === '8173' ? route.continue() : route.abort();
  });
}

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_BIN || undefined });
  try {
    for (const [name, routePath] of [['dashboard', '/preview-dashboard'], ['dashboard2', '/preview-dashboard2']]) {
      const desktop = await browser.newPage({ viewport: { width: 1680, height: 1000 }, deviceScaleFactor: 1 });
      await prepare(desktop);
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
          ['Visão geral', 'Meu trabalho', 'Fluxo operacional'],
          ['Lotação', 'Criticidade', 'Radar de prazos', 'Qualidade operacional', 'Prontidão das audiências'],
          ['Agenda', 'Próxima ação', 'Evolução de produtividade', 'Alertas críticos'],
        ]);
        const desktopLayout = await desktop.locator('.dashboard2-row').evaluateAll(items => items.map(row => getComputedStyle(row).gridTemplateColumns));
        assert.equal(desktopLayout[0].split(' ').length, 3, '3 cards na primeira faixa desktop');
        assert.equal(desktopLayout[1].split(' ').length, 5, '5 cards na segunda faixa desktop');
        assert.equal(desktopLayout[2].split(' ').length, 4, '4 cards na terceira faixa desktop');
      }
      await desktop.screenshot({ path: path.join(evidence, `${name}-desktop.png`), fullPage: true });
      await desktop.close();

      const mobile = await browser.newPage({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 1, isMobile: true, hasTouch: true });
      await prepare(mobile);
      const mobileResponse = await mobile.goto(base + routePath, { waitUntil: 'domcontentloaded' });
      assert.equal(mobileResponse.status(), 200, `${name} mobile HTTP`);
      await mobile.getByText('DADOS SINTÉTICOS · PRÉVIA VISUAL LOCAL · NÃO É HOMOLOGAÇÃO').waitFor();
      await mobile.waitForTimeout(250);
      const mobileState = await mobile.evaluate(() => ({ width: innerWidth, documentWidth: document.documentElement.scrollWidth }));
      assert.equal(mobileState.documentWidth, mobileState.width, `${name} sem overflow mobile`);
      if (name === 'dashboard2') {
        const tops = await mobile.locator('.dashboard2-row > section').evaluateAll(items => items.map(item => item.getBoundingClientRect().top));
        assert.ok(tops.every((top, index) => index === 0 || top >= tops[index - 1]), 'ordem dos cards preservada no celular');
      }
      await mobile.screenshot({ path: path.join(evidence, `${name}-mobile.png`), fullPage: true });
      await mobile.close();
    }
    console.log(`PASS desktop/mobile para as duas páginas; evidências sintéticas: ${evidence}`);
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
