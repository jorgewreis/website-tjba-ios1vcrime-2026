/* Optional visual/interaction regression using real PHP views and synthetic fixtures. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = process.env.MOBILE_SOURCE_ROOT || path.resolve(__dirname, '../..');
const internal = ['cartorio-cartas-expedidas-list', 'cartorio-cartas-recebidas-list', 'cartorio-oficios-list', 'gabinete-oficios-list'];
const publicForms = { '/':'login-form', '/recupera_senha.php':'recupera-form',
    '/cadastro_estudantes.php':'cadastro-form', '/redefinir_senha.php':'redefinir-senha-form', '/alterar-senha.php':'alterar-senha-form' };
function fixtures() {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'vara-mobile-entry-'));
    function put(file, data) {
        const dest = path.join(dir, file);
        fs.mkdirSync(path.dirname(dest), {recursive:true});
        fs.writeFileSync(dest, data);
    }
    const copy = file => put(file, fs.readFileSync(path.join(root,file)));
    const render = (file, setup='') => execFileSync(process.env.PHP_BIN || 'php', ['-d','display_errors=stderr', '-d','session.save_path='+dir, '-r',
        `chdir(${JSON.stringify(dir)}); require 'app/helpers/bootstrap.php'; require 'config/security.php'; require 'app/helpers/CartasExpedidasEstadoFluxo.php'; $_SERVER["REQUEST_METHOD"]="GET"; ${setup} require ${JSON.stringify(file)};`], {encoding:'utf8'});
    const stub = (folder, cls, body) => put(`app/${folder}/${cls}.php`, `<?php class ${cls} {function __construct(...$args){} ${body}}`);
    try {
        put('config/config.php', '<?php');
        put('config/security.php', `<?php const PASSWORD_MIN_LENGTH=8; const PASSWORD_MAX_LENGTH=64;
            class Security { static function isAuthenticated(){return false;} static function requireAuthentication(){}
            static function generateCSRFToken(){return "synthetic";} static function passwordPolicySummary(){return "letras, números e símbolos";}}`);
        put('app/helpers/bootstrap.php', `<?php class Conection { static function getInstance(){return new self;}
            function getConnection(){return new PDO("sqlite::memory:");} }`);
        stub('repositories','FeriadosRepository','function getByDate($d){return null;}');
        stub('services','PasswordResetService','function validateResetToken($t){return $t === "synthetic" ? ["id"=>"fake"] : null;}');
        copy('app/helpers/RandomPhrase.php');
        copy('app/helpers/CartasExpedidasEstadoFluxo.php');
        copy('app/helpers/CartasExpedidasEnvioRegra.php');
        stub('repositories','AlocacoesRegrasRepository','function findByRegra($n){return null;}');
        copy('public/_layout_login.php');
        const html = {};
        for (const [route,file] of Object.entries(publicForms)) {
            const inner = '/views/public/'+file+'.php';
            copy('public'+inner);
            html[route] = render('public/_layout_login.php', `$iframeSrc=${JSON.stringify(inner)};`);
            html[inner] = render('public'+inner, '$_SESSION=["password_reset_form_token"=>"synthetic"];');
        }
        for (const f of fs.readdirSync(path.join(root,'app/views/internal'))) if (f.startsWith('_') && f.endsWith('.php')) copy('app/views/internal/'+f);
        for (const f of internal) copy('app/views/internal/'+f+'.php');
        for (const f of ['cartas-recebidas-v2-row','cartas-expedidas-v2-row']) copy('app/views/internal/partials/'+f+'.php');
        stub('helpers','ProcessoBlocoListagemHelper','static function appendBlocoData($db,$rows){return $rows;}');
        const metadata='static function viewerContextFromSession(){return [];} static function fromListRow(...$a){return [];}';
        stub('helpers','RegistroMetadataHelper',metadata+' static function linhasHistoricoV2CartasExpedidas(...$a){return [];}');
        stub('helpers','OficioRegistroMetadataHelper',metadata);
        stub('helpers','CartorioAlocacaoChipHelper','static function filterForOficiosList($a,$row){return [];} static function ordenarAlocadosPorTipo($a){return $a;}');
        stub('repositories','AlocacoesRegrasRepository','function findByRegra($r){return null;}');
        stub('repositories','OficiosCartorioSubtarefasRepository','const TIPO_ENVIAR="enviar"; const TIPO_JUNTAR="juntar";');
        stub('repositories','CartasRecebidasSubtarefasRepository','');
        stub('repositories','CartasExpedidasSubtarefasRepository','static function resolverSubtarefaAtivaPorLista($s){return null;}');
        stub('helpers','CartasRecebidasUiCopy','const STATUS_AGUARDANDO_CUMPRIMENTO="aguardando_cumprimento"; const STATUS_AGUARDANDO_DEVOLUCAO="aguardando_devolucao"; static function resolverTituloTarefaListagem(...$a){return "Aguardando cumprimento";} static function rotuloBadgeStatusTarefa($s){return $s;} static function classeCssBadgeStatusTarefa($s){return "status-pending";} static function tarefaEstaEmAndamento($s){return true;}');
        stub('helpers','CartasExpedidasUiCopy','static function resolverTituloTarefaListagem(...$a){return "Aguardando envio";} static function classeCssBadgeStatusTarefaListagem(...$a){return "status-pending";}');
        const methods = 'function getStatusCounts($f){return [];} function getFinalidadeCounts($f){return [];} function listAnosComOficios(){return [2026];}';
        const result = (method,row) => `function ${method}(...$a){return ["total"=>123,"rows"=>[${row}]];}`;
        const carta = '["IdCarta"=>"test-letter","Processo"=>"0000000-00.2026.0.00.0000","Comarca"=>"Comarca de teste","Finalidade"=>"Intimação para audiência","Status"=>"aguardando_cumprimento","Prazo"=>30]';
        const oficio = '["IdOficio"=>"test-oficio","Numero"=>12,"Ano"=>2026,"Processo"=>"0000000-00.2026.0.00.0000","TipoOficio"=>"Comunicação sintética de acompanhamento institucional","Status"=>"finalizada","FormaCumprimento"=>"cartorio"]';
        stub('services','CartasRecebidasService',methods+result('listCartas',carta));
        stub('services','CartasExpedidasService',methods+result('listCartas',carta.replace('aguardando_cumprimento','finalizada')));
        for (const cls of ['OficiosCartorioService','OficiosGabineteService']) stub('services',cls,methods+result('listOficios',oficio));
        for (const f of internal) html['/'+f] = render('app/views/internal/'+f+'.php', '$_GET=["ano"=>"2026"]; $_SESSION=[];');
        return html;
    } finally { fs.rmSync(dir,{recursive:true,force:true}); }
}
(async () => {
    const html = fixtures();
    const browser = await chromium.launch({executablePath:process.env.CHROMIUM_BIN || undefined, headless:true});
    try {
        for (const width of [320,375,390,430,768,1365]) {
            const page = await browser.newPage({viewport:{width,height:740}});
            page.setDefaultTimeout(5000);
            await page.route('**/*', route => {
                const u = new URL(route.request().url());
                if (u.hostname !== 'fixture.test') return route.abort();
                if (html[u.pathname]) return route.fulfill({contentType:'text/html; charset=utf-8',body:html[u.pathname]});
                const asset = path.join(root,'public',u.pathname);
                if (u.pathname.startsWith('/assets/') && fs.existsSync(asset)) return route.fulfill({path:asset});
                return route.fulfill({status:404});
            });
            const measure = async (scope, selectors, mobile) => {
                const metrics = await scope.locator('body').evaluate((_,q) => {
                    const w=document.documentElement.clientWidth;
                    return {w, scroll:document.documentElement.scrollWidth, bad:[...document.querySelectorAll(q)].flatMap(x=>{
                        const r=x.getBoundingClientRect();
                        return r.height>0 ? [{label:x.id||x.className,left:r.left,right:r.right,width:r.width,h:r.height}] : [];
                    })};
                }, selectors);
                if (!process.env.MEASURE_ONLY) {
                    assert.ok(metrics.scroll <= metrics.w+1, JSON.stringify(metrics));
                    assert.ok(metrics.bad.length>0, 'at least one intended control is visible');
                    if (mobile) assert.deepEqual(metrics.bad.filter(x=>x.left < -1 || x.right > metrics.w+1 || x.h < 43.9 || x.width < 43.9), []);
                }
                return metrics;
            };
            for (const [route,form] of Object.entries(publicForms)) {
                await page.goto('http://fixture.test'+route);
                const frame = page.frameLocator('#login-iframe');
                await frame.locator('.form-login').first().waitFor({state:'attached'});
                const m=await measure(frame,'.form-login input:not([type=checkbox],[type=hidden]), .form-login select, .form-login button',width<=767);
                const outer=await page.locator('#login-iframe').boundingBox();
                if (process.env.MEASURE_ONLY) console.log(route,width,'iframe',outer?.width,'scroll',m.scroll,'vs',m.w);
                else if (width<=767) assert.ok(outer && Math.abs(outer.width-width)<2);
                else assert.ok(outer && outer.width<=500);
                if (route==='/' && width===320 && !process.env.MEASURE_ONLY) {
                    await frame.locator('[data-nav-href="/recupera_senha.php"]').click();
                    await page.waitForURL('**/recupera_senha.php');
                    await page.frameLocator('#login-iframe').locator('#email').fill('teste@example.invalid');
                }
            }
            for (const name of internal) {
                await page.goto('http://fixture.test/'+name);
                const m=await measure(page,'#filter-status,#filter-tipo,#filter-ano,#list-export-v2,.pagination-controls button',width<=767);
                if (process.env.MEASURE_ONLY) console.log(name,width,m.scroll,m.bad.length,Math.max(...m.bad.map(x=>x.right)));
                else {
                    if (width === 320 && name.includes('cartas')) {
                        await page.locator('.pagination-controls button[data-nav-href*="page_number=2"]').first().click();
                        await page.waitForURL(/page_number=2/);
                    }
                    await page.locator('#filter-status').focus();
                    assert.equal(await page.locator('#filter-status').evaluate(el=>el===document.activeElement),true);
                    if (name.includes('cartas')) assert.equal(await page.locator('.carta-expedida-v2-row').count(),1);
                    if (name.includes('oficios')) assert.equal(await page.locator('[data-oficio-id]').first().isVisible(),true);
                }
            }
            await page.close();
        }
        console.log('Login, quatro formulários públicos e quatro listas: 320/375/390/430/768/1365 px.');
    } finally { await browser.close(); }
})().catch(err=>{ console.error(err); process.exitCode=1; });
