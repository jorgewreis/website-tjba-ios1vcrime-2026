'use strict';
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

class Element {
    constructor(tag) { this.tagName = tag; this.children = []; this.listeners = {}; this.value = ''; this.textContent = ''; this.hidden = false; }
    append(...items) { items.forEach(item => { this.children.push(item); item.parentElement = this; }); }
    replaceChildren(...items) { this.children = []; this.append(...items); }
    add(option) { this.append(option); }
    addEventListener(name, listener) { this.listeners[name] = listener; }
    dispatch(name) { if (this.listeners[name]) this.listeners[name]({ preventDefault() { throw new Error('Envio foi bloqueado'); } }); }
}
class Option extends Element {
    constructor(text, value) { super('option'); this.textContent = text; this.value = value; }
}
const ids = {};
for (const id of ['tarefas-regras-dados', 'regra-form', 'regra-grupos', 'regra-acoes', 'adicionar-grupo', 'adicionar-acao', 'regra-evento', 'regra-evento-status', 'regra-nome', 'regra-prioridade', 'regra-ativa', 'regra-json']) ids[id] = new Element('div');
ids['regra-evento-status'].parentElement = new Element('label');
ids['regra-evento'].value = 'criacao';
ids['regra-nome'].value = 'Regra';
ids['regra-prioridade'].value = '0';
ids['regra-ativa'].checked = true;
ids['tarefas-regras-dados'].textContent = JSON.stringify({
    regra: { condicoes: [[]], acoes: [{ id: 'a', tipo: 'definir_prazo', inicio: 'criacao', dias: 3, alerta_dias: 1 }] },
    tipos: { expedicao: 'Expedir' }, documentos: { 'Ofício': 'Ofício' }, especificacoes: {}, locais: { 'Cartório': 'Cartório' }, usuarios: {}, status: { a_fazer: 'A fazer' }
});
const document = { getElementById: id => ids[id], createElement: tag => new Element(tag) };
vm.runInNewContext(fs.readFileSync('public/assets/js/cartorio-tarefas-regras.js', 'utf8'), { document, Option, URLSearchParams, alert: message => { throw new Error(message); } });
const primeiroSelect = ids['regra-grupos'].children[0].children[2].children[0].children[1];
primeiroSelect.value = 'expedicao';
primeiroSelect.dispatch('change');
ids['regra-form'].dispatch('submit');
const resultado = JSON.parse(ids['regra-json'].value);
assert.deepStrictEqual(resultado.condicoes, [{ tipo: 'expedicao' }], 'Edição de um grupo vazio deve preservar a condição adicionada');
assert.strictEqual(resultado.acoes[0].dias, 3);
console.log('Editor de regras verificado.');
