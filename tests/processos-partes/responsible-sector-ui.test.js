const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../../public/assets/js/shared/processo-setor-responsavel.js'), 'utf8');

function createForm(fetchImpl, explicitDestination = false) {
  const handlers = {};
  const form = {
    dataset: { processId: 'PRC-SINTETICO', explicitDestination: String(explicitDestination) },
    addEventListener(name, callback) { handlers[name] = callback; },
  };
  const elements = {
    'process-responsible-sector-form': form,
    'process-responsible-sector-select': { value: explicitDestination ? '' : 'SET-2', selectedIndex: explicitDestination ? 0 : 1, options: [{}, { textContent: 'Gabinete' }],
      addEventListener(name, callback) { handlers['select:' + name] = callback; } },
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
    processo_id: 'PRC-SINTETICO', setor_id: 'SET-2',
  });
  assert.equal(success.elements['process-responsible-sector-current'].textContent, 'Atribuição atual: Gabinete');
  assert.equal(success.elements['process-responsible-sector-status'].textContent, 'Atribuição auditada com sucesso.');
  assert.equal(success.elements['process-responsible-sector-submit'].disabled, false);

  const optional = createForm(async (url, options) => {
    assert.equal(Object.hasOwn(JSON.parse(options.body), 'justificativa'), false);
    return { ok: true, json: async () => ({ success: true, changed: true, sector_id: 'SET-1', is_default: true }) };
  });
  await optional.handlers.submit({ preventDefault() {} });
  assert.equal(optional.elements['process-responsible-sector-current'].textContent, 'Unidade padrão: Cartório');
  const failure = createForm(async () => { throw new Error('synthetic network failure'); });
  await failure.handlers.submit({ preventDefault() {} });
  assert.equal(failure.elements['process-responsible-sector-status'].textContent, 'Falha de comunicação. Tente novamente.');
  assert.equal(failure.elements['process-responsible-sector-submit'].disabled, false);

  let explicitRequests = 0;
  const explicit = createForm(async (url, options) => {
    explicitRequests += 1;
    assert.equal(JSON.parse(options.body).setor_id, 'SET-2');
    return { ok: true, json: async () => ({ success: true, changed: true, sector_id: 'SET-2' }) };
  }, true);
  const select = explicit.elements['process-responsible-sector-select'];
  const submit = explicit.elements['process-responsible-sector-submit'];
  assert.equal(submit.disabled, true, 'Cartório implícito não pode ser presumido como destino.');
  await explicit.handlers.submit({ preventDefault() {} });
  assert.equal(explicitRequests, 0);
  select.value = 'SET-2';
  select.selectedIndex = 1;
  explicit.handlers['select:change']();
  assert.equal(submit.disabled, false);
  await explicit.handlers.submit({ preventDefault() {} });
  assert.equal(explicitRequests, 1);
  console.log('PASS: destino explícito, envio same-origin, retorno de sucesso e envio sem justificativa e recuperação de falha de rede.');
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
