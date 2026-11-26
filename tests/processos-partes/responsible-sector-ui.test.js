const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../../public/assets/js/shared/processo-setor-responsavel.js'), 'utf8');

function createForm(fetchImpl) {
  const handlers = {};
  const form = {
    dataset: { processId: 'PRC-SINTETICO' },
    addEventListener(name, callback) { handlers[name] = callback; },
  };
  const elements = {
    'process-responsible-sector-form': form,
    'process-responsible-sector-select': { value: 'SET-2', selectedIndex: 1, options: [{}, { textContent: 'Gabinete' }] },
    'process-responsible-sector-reason': { value: 'Atribuição sintética', focus() { this.focused = true; } },
    'process-responsible-sector-submit': { disabled: false },
    'process-responsible-sector-status': { textContent: '' },
    'process-responsible-sector-current': { textContent: '' },
  };
  const context = {
    document: { getElementById(id) { return elements[id] || null; } },
    fetch: fetchImpl,
  };
  vm.runInNewContext(source, context, { filename: 'processo-setor-responsavel.js' });
  return { handlers, elements };
}

async function main() {
  let request;
  const success = createForm(async (url, options) => {
    request = { url, options };
    return { ok: true, json: async () => ({ success: true, changed: true, sector_id: 'SET-2' }) };
  });
  await success.handlers.submit({ preventDefault() {} });
  assert.equal(request.url, '/api/processos/setor-responsavel.php');
  assert.equal(request.options.method, 'POST');
  assert.equal(request.options.credentials, 'same-origin');
  assert.deepEqual(JSON.parse(request.options.body), {
    processo_id: 'PRC-SINTETICO', setor_id: 'SET-2', justificativa: 'Atribuição sintética',
  });
  assert.equal(success.elements['process-responsible-sector-current'].textContent, 'Atribuição atual: Gabinete');
  assert.equal(success.elements['process-responsible-sector-status'].textContent, 'Atribuição auditada com sucesso.');
  assert.equal(success.elements['process-responsible-sector-reason'].value, '');
  assert.equal(success.elements['process-responsible-sector-submit'].disabled, false);

  const optional = createForm(async (url, options) => {
    assert.equal(JSON.parse(options.body).justificativa, '');
    return { ok: true, json: async () => ({ success: true, changed: true, sector_id: 'SET-1', is_default: true }) };
  });
  optional.elements['process-responsible-sector-reason'].value = '  ';
  await optional.handlers.submit({ preventDefault() {} });
  assert.equal(optional.elements['process-responsible-sector-current'].textContent, 'Unidade padrão: Cartório');
  const failure = createForm(async () => { throw new Error('synthetic network failure'); });
  await failure.handlers.submit({ preventDefault() {} });
  assert.equal(failure.elements['process-responsible-sector-status'].textContent, 'Falha de comunicação. A justificativa foi preservada; tente novamente.');
  assert.equal(failure.elements['process-responsible-sector-reason'].value, 'Atribuição sintética');
  assert.equal(failure.elements['process-responsible-sector-submit'].disabled, false);

  console.log('PASS: envio same-origin, retorno de sucesso e preservação da justificativa em falha de rede.');
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
