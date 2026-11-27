'use strict';
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const vm = require('node:vm');
const field = () => ({value:'', disabled:false, hidden:true, options:[{}], handlers:{},
    addEventListener(type, fn) { this.handlers[type] = fn; },
    append(option) { this.options.push(option); }});
const inputs = new Map();
for (const id of ['vara-local','vara-status','catalogo-filtro','catalogo-comarca','catalogo-vara','catalogo-dados']) {
    inputs.set('#processo-' + id, field());
}
const form = {...field(), querySelector: (key) => inputs.get(key)};
const campo = form.querySelector('#processo-vara-local');
const comarca = form.querySelector('#processo-catalogo-comarca');
const vara = form.querySelector('#processo-catalogo-vara');
campo.value = 'Vara cadastrada';
comarca.value = '2146';
form.querySelector('#processo-catalogo-dados').textContent = JSON.stringify({
    2146:['2ª Vara Criminal','1ª Vara Criminal'], 100:['Vara única']
});
const window = {document:{getElementById: id => id === 'processo-vara-form' ? form : null, createElement: field}};
vm.runInNewContext(readFileSync('public/assets/js/shared/processos-edit.js', 'utf8'), {window});
window.App.ProcessosEdit.initialize();
assert.equal(form.querySelector('#processo-catalogo-filtro').hidden, false);
assert.deepEqual(vara.options.slice(1).map(o => o.value), ['1ª Vara Criminal','2ª Vara Criminal']);
assert.equal(campo.value, 'Vara cadastrada');
comarca.value = '100';
comarca.handlers.change();
assert.deepEqual(vara.options.slice(1).map(o => o.value), ['Vara única']);
assert.equal(campo.value, 'Vara cadastrada');
vara.value = 'Vara única';
vara.handlers.change();
assert.equal(campo.value, 'Vara única');
comarca.value = '';
comarca.handlers.change();
assert.equal(vara.disabled, true);
assert.equal(campo.value, 'Vara única');
console.log('PASS: lista filtrada, sem preenchimento automático e sem apagar o campo ao trocar comarca.');
