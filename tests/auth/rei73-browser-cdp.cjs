'use strict';
// Runs only in the isolated browser container provisioned by run-isolated-auth-e2e.ps1.
const { spawn } = require('node:child_process');
const http = require('node:http');
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
async function eventually(fn, message) {
  for (let i = 0; i < 100; i++) { try { const value = await fn(); if (value) return value; } catch {} await sleep(100); }
  throw new Error(message);
}
let phase = 'init';
let browserProcess;
const watchdog = setTimeout(() => {
  console.error('FAIL navegador etapa=timeout-global');
  if (browserProcess && browserProcess.exitCode === null && browserProcess.signalCode === null) browserProcess.kill('SIGKILL');
  process.exit(1);
}, 90000);
async function main() {
  const browser = spawn('/ms-playwright/chromium_headless_shell-1248/chrome-headless-shell-linux64/chrome-headless-shell', [
    '--headless', '--no-sandbox', '--disable-gpu', '--disable-background-networking', '--no-proxy-server',
    '--disable-features=NetworkServiceInProcess', '--no-first-run', '--no-default-browser-check',
    '--host-resolver-rules=MAP * ~NOTFOUND, EXCLUDE auth-sandbox.test, EXCLUDE localhost',
    '--remote-debugging-address=127.0.0.1', '--remote-debugging-port=9222',
    '--user-data-dir=/tmp/rei73-browser-profile', 'about:blank'
  ], { stdio: 'ignore' });
  browserProcess = browser;
  browser.on('error', () => {});
  try {
    phase = 'browser-start';
    const targets = await eventually(() => new Promise(resolve => {
      const request = http.get('http://127.0.0.1:9222/json/list', response => { let raw = ''; response.on('data', x => { raw += x; if (raw.length > 65536) request.destroy(); }); response.on('end', () => { try { resolve(JSON.parse(raw)); } catch { resolve(null); } }); response.on('error', () => resolve(null)); }); request.setTimeout(1000, () => request.destroy()); request.on('error', () => resolve(null));
    }), 'Chromium nao iniciou');
    const page = targets.find(x => x.type === 'page');
    if (!page || !page.webSocketDebuggerUrl.startsWith('ws://127.0.0.1:9222/')) throw new Error('Alvo CDP invalido');
    phase = 'websocket';
    const ws = new WebSocket(page.webSocketDebuggerUrl); await Promise.race([new Promise((resolve, reject) => { ws.onopen = resolve; ws.onerror = reject; }), sleep(10000).then(() => { throw new Error('timeout'); })]);
    let sequence = 0; let homeStatus = null; let loginStatus = null; let exceptions = 0; const pending = new Map();
    ws.onmessage = event => {
      const message = JSON.parse(event.data);
      if (message.id && pending.has(message.id)) { const p = pending.get(message.id); pending.delete(message.id); message.error ? p.reject(new Error('CDP: ' + message.error.message)) : p.resolve(message.result); }
      if (message.method === 'Network.responseReceived' && message.params.type === 'Document' && message.params.response.url === 'http://auth-sandbox.test:8080/home.php') homeStatus = message.params.response.status;
      if (message.method === 'Network.responseReceived' && message.params.response.url === 'http://auth-sandbox.test:8080/login.php') loginStatus = message.params.response.status;
      if (message.method === 'Runtime.exceptionThrown') exceptions++;
      if (message.method === 'Fetch.requestPaused') {
        const u = new URL(message.params.request.url);
        const allowed = u.protocol === 'http:' && u.hostname === 'auth-sandbox.test' && u.port === '8080';
        if (!allowed) console.log('DIAG navegador: bloqueado ' + JSON.stringify({http:u.protocol === 'http:', app:u.hostname === 'auth-sandbox.test', port:u.port === '8080'}));
        send(allowed ? 'Fetch.continueRequest' : 'Fetch.failRequest', allowed ? {requestId: message.params.requestId} : {requestId: message.params.requestId, errorReason: 'BlockedByClient'}).catch(() => {});
      }
    };
    function send(method, params = {}) { const id = ++sequence; return new Promise((resolve, reject) => { const timer = setTimeout(() => { pending.delete(id); reject(new Error('timeout')); }, 10000); pending.set(id, {resolve: v => { clearTimeout(timer); resolve(v); }, reject: e => { clearTimeout(timer); reject(e); }}); ws.send(JSON.stringify({id, method, params})); }); }
    async function evaluate(expression) { const result = await send('Runtime.evaluate', {expression, returnByValue:true, awaitPromise:true}); if (result.exceptionDetails) throw new Error('Falha de script na pagina'); return result.result.value; }
    phase = 'cdp-setup';
    await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable');
    await send('Fetch.enable', {patterns:[{urlPattern:'*',requestStage:'Request'}]});
    const navigation = await send('Page.navigate', {url:'http://auth-sandbox.test:8080/index.php'});
    if (navigation.errorText) { const known = ['net::ERR_NAME_NOT_RESOLVED','net::ERR_CONNECTION_REFUSED','net::ERR_CONNECTION_CLOSED','net::ERR_BLOCKED_BY_CLIENT','net::ERR_ADDRESS_UNREACHABLE','net::ERR_ABORTED']; console.log('DIAG navegador: ' + (known.includes(navigation.errorText) ? navigation.errorText : 'erro de rede nao classificado')); throw new Error('navegação falhou'); }
    phase = 'form';
    await eventually(() => evaluate("!!document.querySelector('#login-iframe')?.contentWindow?.App?.CPF && !!document.querySelector('#login-iframe')?.contentDocument?.querySelector('#loginForm button[type=submit]')"), 'form not loaded');
    phase = 'invalid-login';
    await evaluate("(()=>{const d=document.querySelector('#login-iframe').contentDocument; d.querySelector('#cpf').value='529.982.247-25'; d.querySelector('#senha').value='SenhaIncorreta!123'; d.querySelector('#loginForm').requestSubmit(); return true;})()");
    try { await eventually(() => evaluate("(()=>{const d=document.querySelector('#login-iframe').contentDocument; return d.querySelector('#erro')?.textContent.trim().length > 0 && d.defaultView.getComputedStyle(d.querySelector('#alert-login')).display !== 'none' && !d.querySelector('#loginForm button[type=submit]').disabled;})()"), 'invalid login validation failed'); } catch (error) { let flags = {}; try { flags = await evaluate("(()=>{const d=document.querySelector('#login-iframe').contentDocument; return JSON.stringify({message:!!d.querySelector('#erro')?.textContent.trim(),visible:d.defaultView.getComputedStyle(d.querySelector('#alert-login')).display !== 'none',enabled:!d.querySelector('#loginForm button[type=submit]').disabled});})()"); } catch {} console.log('DIAG navegador: login status=' + (Number.isInteger(loginStatus) ? loginStatus : 'none') + ' exceptions=' + exceptions + ' flags=' + flags); throw error; }
    console.log('PASS navegador: erro de login visivel e botao recuperado');
    phase = 'valid-login';
    await evaluate("(()=>{const d=document.querySelector('#login-iframe').contentDocument; d.querySelector('#senha').value='Ativo!12345'; d.querySelector('#loginForm').requestSubmit(); return true;})()");
    await eventually(async () => homeStatus === 200 && await evaluate("location.pathname === '/home.php' && document.readyState === 'complete' && document.documentElement.outerHTML.includes('USR-11111111111111111111111111')"), 'home validation failed');
    console.log('PASS navegador: home 200 com marcador sintetico');
    ws.close();
  } finally { if (browser.exitCode === null && browser.signalCode === null) { const exited = new Promise(resolve => browser.once('exit', resolve)); browser.kill('SIGTERM'); await Promise.race([exited, sleep(3000)]); if (browser.exitCode === null && browser.signalCode === null) { browser.kill('SIGKILL'); await Promise.race([exited, sleep(3000)]); } if (browser.exitCode === null && browser.signalCode === null) throw new Error('Chromium ainda ativo'); } }
}
main().then(() => { clearTimeout(watchdog); }, () => { clearTimeout(watchdog); console.error('FAIL navegador etapa=' + phase); process.exitCode = 1; });
