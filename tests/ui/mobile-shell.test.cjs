/* Optional synthetic browser check; run with PHP and Playwright/Chromium installed. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { execFileSync } = require('node:child_process');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const source = path.resolve(__dirname, '../..');

function renderHome() {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'vara-shell-fixture-'));
    const put = (name, content) => {
        const file = path.join(dir, name);
        fs.mkdirSync(path.dirname(file), { recursive: true });
        fs.writeFileSync(file, content);
    };
    const copy = (name) => put(name, fs.readFileSync(path.join(source, name)));
    try {
        for (const f of ['public/home.php', 'public/assets/css/shared/layout.css', 'public/assets/js/shared/menu.js', 'app/helpers/UserAccessHelper.php', 'app/helpers/UserInitialsHelper.php']) copy(f);
        put('config/config.php', "<?php $_SESSION = ['token'=>'synthetic','id_usuario'=>'SYNTHETIC','nome'=>'Pessoa Sintética'];");
        put('config/security.php', "<?php class Security { static function requireAuthentication() {} static function generateCSRFToken() {return 'synthetic';} static function resolveDefaultSessionProfile($p) {return 'SERVIDOR-CARTORIO';} }");
        put('app/helpers/bootstrap.php', '<?php');
        put('app/helpers/Conection.php', '<?php class Conection {static function getInstance(){return new self;} function getConnection(){throw new RuntimeException("No production database in fixture");}}');
        put('app/helpers/EffectiveAccessProfiles.php', '<?php class EffectiveAccessProfiles {static function applyDefaultOperacionalIfCumulative($p){} static function canOfferAdminModeToggle($p){return false;} static function isAdminSuppressed(){return false;}}');
        put('app/helpers/UsuarioPerfisAutorizacao.php', "<?php class UsuarioPerfisAutorizacao {static function perfisDoUsuario($id){return ['SERVIDOR-CARTORIO'];}}");
        put('app/models/Usuario.php', "<?php class Usuario {function validarToken($t){return ['id_usuario'=>'SYNTHETIC','nome'=>'Pessoa Sintética'];} function listarPerfisUsuario($id){return ['SERVIDOR-CARTORIO'];}}");
        return execFileSync(process.env.PHP_BIN || 'php', ['-d', 'display_errors=stderr', path.join(dir, 'public/home.php')], { encoding: 'utf8' });
    } finally {
        fs.rmSync(dir, { recursive: true, force: true });
    }
}

(async () => {
    const html = renderHome();
    const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_BIN || undefined, headless: true });
    try {
        for (const width of [320, 390, 430, 767, 768, 1365]) {
            const page = await browser.newPage({ viewport: { width, height: 760 } });
            const frameRequests = [];
            await page.route('**/*', (route) => {
                const url = new URL(route.request().url());
                if (url.hostname !== 'fixture.test') return route.abort();
                if (url.pathname === '/') return route.fulfill({ contentType: 'text/html; charset=utf-8', body: html });
                if (url.pathname === '/frame.php') {
                    const target = url.searchParams.get('page');
                    frameRequests.push(target);
                    return route.fulfill({ contentType: 'text/html; charset=utf-8', body: '<!doctype html><h1>Página sintética</h1>' });
                }
                if (url.pathname.startsWith('/assets/')) {
                    const asset = path.join(source, 'public', url.pathname);
                    if (fs.existsSync(asset)) return route.fulfill({ path: asset });
                }
                return route.fulfill({ status: 404 });
            });
            await page.goto('http://fixture.test/');
            await page.waitForFunction(() => !!window.menuController);
            const menu = page.locator('#layout-menu');
            const open = page.locator('button[aria-controls=layout-menu]');
            const frame = page.locator('#frame');
            const nav = page.locator('.layout-navbar');
            const nb = await nav.boundingBox();
            const fb = await frame.boundingBox();
            assert.ok(nb && nb.x >= 0 && nb.x + nb.width <= width + 1);
            assert.ok(fb && fb.x >= 0 && fb.x + fb.width <= width + 1);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth), width);
            if (width < 768) {
                assert.ok(fb.width >= width - 1 && fb.y === 0 && fb.height === 760, 'iframe uses visible viewport');
                assert.ok((await open.boundingBox()).width >= 44);
                assert.equal(await menu.evaluate(el => el.inert), true);
                await open.click();
                assert.equal(await open.getAttribute('aria-expanded'), 'true');
                const close = page.getByRole('button', { name: 'Fechar menu principal' });
                assert.equal(await close.evaluate(el => document.activeElement === el), true);
                await page.keyboard.press('Shift+Tab');
                assert.equal(await menu.evaluate(el => el.contains(document.activeElement)), true);
                await page.keyboard.press('Escape');
                assert.equal(await open.getAttribute('aria-expanded'), 'false');
                assert.equal(await open.evaluate(el => document.activeElement === el), true);
                await open.click();
                await page.locator('.layout-overlay').click({ position: { x: width - 10, y: 380 } });
                assert.equal(await open.getAttribute('aria-expanded'), 'false');
                await open.click();
                const button = menu.locator('button[data-target=guias]');
                await button.waitFor();
                const target = await button.getAttribute('data-target');
                await button.click();
                await page.waitForFunction(target => new URL(document.getElementById('frame').contentWindow.location.href).searchParams.get('page') === target, target);
                assert.equal(frameRequests.filter(x => x === target).length, 1);
                assert.equal(await open.getAttribute('aria-expanded'), 'false');
                await page.getByRole('button', { name: 'Configurações e perfil' }).click();
                await page.getByRole('menuitem', { name: 'Alterar dados pessoais' }).click();
                await page.waitForFunction(() => new URL(document.getElementById('frame').contentWindow.location.href).searchParams.get('page') === 'alterardados');
                assert.equal(frameRequests.filter(x => x === 'alterardados').length, 1);
            } else if (width >= 1200) {
                assert.equal(await menu.evaluate(el => el.inert), false);
                assert.equal(await page.locator('.layout-wraper').evaluate(el => el.classList.contains('menu-fixed')), true);
                assert.ok(Math.abs(fb.x - 260) < 1);
            } else {
                assert.equal(await menu.evaluate(el => el.inert), true);
            }
            console.log(JSON.stringify({ width, frameWidth: fb.width, frameHeight: fb.height, navbarWidth: nb.width }));
            await page.close();
        }
    } finally {
        await browser.close();
    }
})().catch((error) => { console.error(error); process.exitCode = 1; });
