'use strict';

// Synthetic browser gate: no PHP bootstrap, credentials, server, or database.
// Supply REI167_PAYLOAD_FILE from run-rei167-mysql-isolated.ps1 and optionally
// REI167_PLAYWRIGHT_MODULE when Playwright is installed outside this checkout.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const crypto = require('node:crypto');
const { chromium } = require(process.env.REI167_PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '../..');
const payloadFile = process.env.REI167_PAYLOAD_FILE;
assert.ok(payloadFile, 'REI167_PAYLOAD_FILE is required; fabricated fallback payloads are forbidden');
const hash = data => crypto.createHash('sha256').update(data).digest('hex');
const rawPayload = fs.readFileSync(payloadFile);
const entries = rawPayload.toString('utf8').split(/\r?\n/)
  .filter(line => line.startsWith('REI167_PAYLOAD '))
  .map(line => JSON.parse(line.slice('REI167_PAYLOAD '.length)));
const scenarios = ['expedicao', 'envio', 'zero', 'falha', 'gabinete'];
assert.deepEqual(entries.map(e => e.scenario).sort(), [...scenarios].sort());
const payloads = Object.fromEntries(entries.map(e => [e.scenario, e.payload]));
const viewFile = path.join(root, 'app/views/internal/dashboard.php');
const jsFile = path.join(root, 'public/assets/js/dashboard-interno.js');
const view = fs.readFileSync(viewFile, 'utf8');
// Keep all actual HTML. Omit only PHP blocks (auth/bootstrap, shared includes,
// and asset version expressions). This is not authenticated PHP rendering.
const html = view.replace(/<\?php[\s\S]*?\?>/g, '');
assert.ok(html.includes('Guias aguardando expedição'));
const artifacts = fs.mkdtempSync(path.join(os.tmpdir(), 'rei167-browser-'));

(async () => {
  const browser = await chromium.launch({ headless: true });
  const evidence = { browser: browser.version(), payloadFile: path.resolve(payloadFile),
    payloadSha256: hash(rawPayload), viewSha256: hash(view),
    javascriptSha256: hash(fs.readFileSync(jsFile)), scenarios: [], artifacts };
  try {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, serviceWorkers: 'block' });
    const page = await context.newPage();
    const errors = [];
    const unexpectedRequests = [];
    page.on('pageerror', e => errors.push(e.message));
    page.on('console', m => { if (['error', 'warning'].includes(m.type())) errors.push(m.text()); });
    let current = 'expedicao';
    let requestCount = 0;
    await page.route('**/*', async route => {
      const url = new URL(route.request().url());
      // Suppress remote font stylesheet; use system fonts without external I/O.
      if (url.origin === 'https://fonts.googleapis.com') return route.fulfill({ contentType: 'text/css', body: '' });
      if (url.origin !== 'http://rei167.synthetic.test') {
        unexpectedRequests.push(url.href);
        return route.abort();
      }
      if (url.pathname === '/dashboard') return route.fulfill({ contentType: 'text/html', body: html });
      if (url.pathname === '/api/dashboard/resumo.php') {
        requestCount++;
        return route.fulfill({ contentType: 'application/json', body: JSON.stringify(payloads[current]) });
      }
      const assets = {
        '/assets/css/dashboard-interno.css': 'text/css',
        '/assets/js/dashboard-interno.js': 'application/javascript',
        '/assets/js/vendor-chart.umd.js': 'application/javascript',
        ...Object.fromEntries(['system', 'sector', 'work', 'agenda'].map(name =>
          ['/assets/img/backgrounds/dashboard-' + name + '.webp', 'image/webp']))
      };
      if (assets[url.pathname]) return route.fulfill({ contentType: assets[url.pathname], body: fs.readFileSync(path.join(root, 'public', url.pathname)) });
      unexpectedRequests.push(url.href);
      return route.abort();
    });
    await page.goto('http://rei167.synthetic.test/dashboard');
    assert.equal(await page.title(), 'Dashboard - Primeira Vara Crime de Ilhéus');
    const expected = { expedicao: ['1', '0'], envio: ['0', '1'], zero: ['0', '0'], falha: ['—', '—'], gabinete: ['—', '—'] };
    async function check(scenario) {
      const pair = expected[scenario];
      await page.waitForFunction(({ pair, visible }) => {
        const ids = ['dash-queue-guias-expedicao', 'dash-queue-guias-envio'];
        return ids.every((id, i) => document.getElementById(id).textContent === pair[i]) &&
          document.getElementById('dash-cartorio-queue').hidden === !visible;
      }, { pair, visible: scenario !== 'gabinete' });
      for (const id of ['dash-queue-guias-expedicao-row', 'dash-queue-guias-envio-row']) {
        assert.equal(await page.locator('#' + id).isVisible(), scenario !== 'gabinete', scenario + ': ' + id);
        assert.equal(await page.locator('#' + id).count(), 1, 'refresh must not duplicate rows');
      }
      assert.equal(await page.locator('#dashboard-erro').isVisible(), false);
      assert.ok((await page.locator('main').innerText()).length > 50, 'nonblank dashboard');
      assert.equal(await page.locator('vite-error-overlay, nextjs-portal').count(), 0);
    }
    for (const scenario of [...scenarios, 'expedicao', 'expedicao']) {
      current = scenario;
      // The production script exposes no refresh control. Dispatch its actual
      // lifecycle listener again, exercising load/reset in the SAME DOM.
      const before = requestCount;
      const response = page.waitForResponse('**/api/dashboard/resumo.php');
      await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
      await response;
      await check(scenario);
      assert.equal(requestCount, before + 1);
      evidence.scenarios.push({ scenario, viewport: '1440x1000', refresh: true, result: 'PASS' });
      await page.screenshot({ path: path.join(artifacts, scenario + '.png'), fullPage: true });
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await check('expedicao');
    await page.screenshot({ path: path.join(artifacts, 'mobile-expedicao.png'), fullPage: true });
    assert.deepEqual(unexpectedRequests, [], 'all network requests must be synthetic and allowlisted');
    assert.deepEqual(errors, [], 'browser runtime and console errors');
    evidence.consoleErrors = errors;
    evidence.unexpectedRequests = unexpectedRequests;
    evidence.mobile = '390x844 counters visible; no broad visual acceptance';
    fs.writeFileSync(path.join(artifacts, 'evidence.json'), JSON.stringify(evidence, null, 2));
    console.log('PASS: real Chromium, actual view HTML/CSS/vendor/chart/dashboard JS, five runner payloads, repeated same-DOM refresh, mobile counter visibility.');
    console.log(JSON.stringify(evidence, null, 2));
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
