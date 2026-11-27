'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const cnj = require('../../public/assets/js/modules/datajud/cnj.js');
const clearMicrotasks = () => new Promise(resolve => setImmediate(resolve));
const makeElement = (value = '') => {
    const listeners = {};
    const attrs = new Map();
    return {
        value, dataset: {}, selectedOptions: [], classList: { add() {}, remove() {} },
        addEventListener: (key, fn) => { listeners[key] = fn; },
        getAttribute: key => attrs.get(key), setAttribute: (key, v) => attrs.set(key, v),
        removeAttribute: key => attrs.delete(key), querySelector: () => null,
        click: () => listeners.click(), focus() {}
    };
};

(async () => {
    for (const area of ['cartorio', 'gabinete']) {
        const view = read('app/views/internal/' + area + '-oficios-list.php');
        assert.match(view, /include.+_cnj_scripts\.php/);
        assert.doesNotMatch(view, /_datajud_scripts\.php|data-datajud-related|hidden-classe/);
        const ids = area === 'cartorio'
            ? { type: 'oficio-tipo', create: 'btn-oficio-registrar' }
            : { type: 'gab-oficio-tipo', create: 'btn-gab-oficio-registrar' };
        const zero = '0000001' + '20268050000';
        let numero = '';
        for (let dv = 0; dv < 100; dv++) {
            const candidate = cnj.formatarCNJ(zero.slice(0,7) + String(dv).padStart(2,'0') + zero.slice(7));
            if (cnj.validarCNJ(candidate)) { numero = candidate; break; }
        }
        assert.equal(cnj.validarCNJ(numero),true);
        const fields = {
            [ids.type]: makeElement('Ofício do Caso Sintético'),
            [ids.create]: makeElement(),
            'filter-processo': makeElement(numero),
            'oficio-forma-cumprimento': makeElement('ccm'),
            'oficio-meio-envio': makeElement()
        };
        const doc = { getElementById: name => fields[name] || null, querySelector: () => null };
        const posts = [], notifications = [];
        let reloads = 0, release;
        const storage = new Map();
        const window = {
            document: doc, location: { reload(){ reloads++; } },
            sessionStorage: { getItem: k => storage.get(k), setItem: (k,v) => storage.set(k,v), removeItem: k => storage.delete(k) },
            showToast: data => notifications.push(data),
            normalizeAndValidateCNJValue: cnj.normalizeAndValidateCNJValue
        };
        const context = vm.createContext({ window, document:doc, fetch: (url, init) => {
            posts.push({url,init});
            return new Promise(resolve => { release = resolve; });
        } });
        for (const asset of ['cartorio-create-helpers','oficios-list']) vm.runInContext(read('public/assets/js/shared/'+asset+'.js'),context);
        const source = view.match(/buildRequest:\s*(function\s*\([\s\S]*?\n\s*\}),\s*buildSuccessPayload/)[1];
        const request = vm.runInContext('('+source+')',context);
        const endpoint = '/api/' + area + '-oficios/create.php';
        window.App.OficiosList.initialize({typeSelectId:ids.type,createButtonId:ids.create,createEndpoint:endpoint,buildRequest:request});
        const button = fields[ids.create];
        assert.equal(button.disabled, false,'não espera por classificação externa');
        button.click();
        button.click();
        assert.equal(posts.length,1,'bloqueia clique repetido durante o POST');
        assert.equal(posts[0].url,endpoint);
        const sent = JSON.parse(posts[0].init.body);
        assert.equal(sent.numero_processo,numero);
        assert.ok(!('classe_codigo' in sent));
        assert.equal(button.getAttribute('aria-busy'),'true');
        release({ok:false,json:async()=>({error:'Validação sintética'})});
        await clearMicrotasks();
        assert.equal(button.disabled,false);
        assert.equal(reloads,0);
        assert.equal(notifications[0].message,'Validação sintética');
        assert.equal(fields['filter-processo'].value,numero,'erro não apaga o formulário');
        button.click();
        release({ok:true,json:async()=>({id:'OF-SINTETICO',numero_oficio:'17/2026'})});
        await clearMicrotasks();
        assert.equal(reloads,1);
        assert.match(storage.get('cartorio.postReloadToast'),/17\/2026/);
    }
    console.log('OK: Cartório e Gabinete enviam sem esperar DataJud; clique repetido, falha e sucesso testados.');
})().catch(e=>{console.error(e);process.exitCode=1;});
