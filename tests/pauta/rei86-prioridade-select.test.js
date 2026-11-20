const assert = require('assert');
const fs = require('fs');
const vm = require('vm');
const listeners = {};
class Element {
  constructor(tag) { this.tagName = tag; this.children = []; this.style = {}; this.disabled = false; this._value = ''; this.attrs = {}; }
  set innerHTML(value) { this.children = []; this._value = ''; }
  get value() { return this._value; }
  set value(value) { this._value = value; }
  set selectedIndex(value) { if (value === 0) this._value = ''; }
  appendChild(child) { this.children.push(child); return child; }
  setAttribute(key, value) { this.attrs[key] = value; }
  addEventListener(event, fn) { listeners[this.id + ':' + event] = fn; }
}
const ids = {};
for (const id of ['prioridades-processuais-wrap', 'prioridade-catalog-select', 'btn-prioridade-adicionar', 'prioridades-processuais-list']) { ids[id] = new Element('div'); ids[id].id = id; }
const initial = { prioridades: [{codigo: 'a', label: 'A'}], catalogo: [{codigo:'a',label:'A'}, {codigo:'b',label:'B'}, {codigo:'c',label:'C'}] };
let response = null;
let deferred = null;
const context = { window: { PAUTA_PROCESSO_ID: '123', PAUTA_PRIORIDADES_INITIAL: initial }, document: { getElementById: id => ids[id], createElement: tag => new Element(tag) }, fetch: () => deferred ? new Promise(resolve => { deferred.resolve = resolve; }) : Promise.resolve({ok:true,json:()=>Promise.resolve({success:true,state:response})}), console, encodeURIComponent };
vm.runInNewContext(fs.readFileSync('public/assets/js/modules/audiencias/pauta-edit-prioridades.js', 'utf8'), context);
const select = ids['prioridade-catalog-select'], button = ids['btn-prioridade-adicionar'];
assert.strictEqual(button.disabled, true);
select.value = 'b'; listeners['prioridade-catalog-select:change'](); assert.strictEqual(button.disabled, false);
response = {prioridades: [], catalogo: initial.catalogo};
listeners['prioridades-processuais-list:click']({target:{closest:()=>({getAttribute:()=> 'a'})}});
setImmediate(() => {
  assert.strictEqual(select.value, 'b', 'seleção disponível deve ser preservada');
  assert.strictEqual(button.disabled, false);
  response = {prioridades:[{codigo:'b',label:'B'}],catalogo:initial.catalogo};
  listeners['prioridades-processuais-list:click']({target:{closest:()=>({getAttribute:()=> 'a'})}});
  setImmediate(() => {
    assert.strictEqual(select.value, '');
    assert.strictEqual(button.disabled, true, 'botão deve desabilitar quando a seleção desaparece');
    select.value = 'a'; listeners['prioridade-catalog-select:change']();
    deferred = {};
    listeners['btn-prioridade-adicionar:click']();
    select.value = 'c'; listeners['prioridade-catalog-select:change']();
    deferred.resolve({ok:true,json:()=>Promise.resolve({success:true,state:{prioridades:[{codigo:'a',label:'A'},{codigo:'b',label:'B'}],catalogo:initial.catalogo}})});
    setImmediate(() => {
      assert.strictEqual(select.value, 'c', 'seleção posterior deve sobreviver à resposta da adição');
      assert.strictEqual(button.disabled, false);
      console.log('REI-86: seleção preservada e botão sincronizado');
    });
  });
});
