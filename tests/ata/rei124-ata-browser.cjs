'use strict';

const { chromium } = require('playwright-core');
const { createHash } = require('node:crypto');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  try {
    const page = await browser.newPage();
    await page.context().addCookies([{ name: 'PHPSESSID', value: 'rei124ataadmin', url: 'http://127.0.0.1:18766' }]);
    await page.goto('http://127.0.0.1:18766/rei124-ata-ui-fixture.php', { waitUntil: 'domcontentloaded' });
    const [chooser] = await Promise.all([
      page.waitForEvent('filechooser'),
      page.locator('.register[data-pauta="p"] .btn-upload-ata').click(),
    ]);
    await chooser.setFiles({
      name: 'rei124-ata-browser-synthetic.pdf',
      mimeType: 'application/pdf',
      buffer: Buffer.from('%PDF-1.4\nsynthetic browser upload\n%%EOF\n'),
    });
    await page.getByRole('button', { name: 'Substituir PDF' }).waitFor({ state: 'visible', timeout: 15000 });
    await page.getByText('rei124-ata-browser-synthetic.pdf').waitFor({ state: 'visible', timeout: 15000 });
    const persistedSha = await page.locator('#persisted-ata').getAttribute('data-sha256');
    const expectedSha = createHash('sha256').update('%PDF-1.4\nsynthetic browser upload\n%%EOF\n').digest('hex');
    if (persistedSha !== expectedSha) throw new Error('O SHA-256 persistido diverge dos bytes enviados pela interface.');
    console.log('PASS Playwright Chromium: seletor real, POST multipart, reload, nome e SHA-256 persistidos');
  } finally {
    await browser.close();
  }
})().catch(error => {
  console.error(error && error.stack ? error.stack : String(error));
  process.exitCode = 1;
});
