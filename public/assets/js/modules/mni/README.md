# Modulo MNI (frontend opt-in)

Pasta isolada do frontend para complementar o DataJud com dados retornados pelo MNI/TJBA.

## Status

- **Opt-in por tela**: nenhum HTML inclui automaticamente o `mni-api.js`. Cada tela que quiser usar precisa incluir o script.
- **Não substitui** o fluxo do DataJud. O `datajud-api.js` continua sendo a fonte primária (rápida e pública); o MNI roda em segundo plano e dispara eventos novos que a tela pode (ou não) usar.

## Como incluir em uma página

```html
<!-- DataJud continua sendo incluído normalmente (faz parte do bundle de _datajud_scripts.php). -->
<?php include __DIR__ . '/_datajud_scripts.php'; ?>

<!-- MNI opt-in: incluir DEPOIS do DataJud para garantir ordem dos listeners. -->
<script src="/assets/js/modules/mni/mni-api.js?v=<?php echo (int) @filemtime(__DIR__ . '/../../public/assets/js/modules/mni/mni-api.js'); ?>"></script>

<script>
  // Opcional: configurar
  window.__MNI_CLIENT_CONFIG = {
    endpoint: '/api/mni/consultar-processo.php',
    debounceMs: 200,
    requestOptions: { incluir_movimentos: true, incluir_documentos: false }
  };

  // Usar:
  window.addEventListener('mniProcessoResolved', function (event) {
    var detail = event.detail || {};
    // detail.shape = shape comum (mesmo do DataJud, com chave extra "mni")
    // detail.shape.mni.partes.passivo  -> lista detalhada
    // detail.shape.movimentos          -> lista normalizada
    // detail.shape.mni.documentos      -> metadados de documentos
    // detail.meta.elapsed_ms           -> tempo da consulta MNI
  });
  window.addEventListener('mniProcessoError', function (event) {
    // detail.kind = 'auth' | 'sigilo' | 'not_found' | 'timeout' | 'network' | 'http' | ...
    // detail.message = mensagem amigável
  });
</script>
```

## Eventos disparados

| Evento | Quando | Detail |
|--------|--------|--------|
| `mniProcessoResolved` | Sucesso na consulta MNI | `{ processoInputId, shape, meta: { elapsed_ms, http_code, version, endpoint_host }, source: 'mni' }` |
| `mniProcessoError`    | Falha em qualquer etapa | `{ processoInputId, kind, message, meta }` |

`kind` segue `MniErrorTranslator`: `auth`, `sigilo`, `not_found`, `timeout`, `network`, `http`, `fault`, `invalid_response`, `config`, `unknown`.

## Não interfere em

- `datajud-api.js` — continua disparando `cnjValidated`/`datajudClasseResolved` normalmente.
- Botão Cadastrar — o gate (`initCreateButtonGate`) só reage a eventos do DataJud; o MNI nunca trava o submit.
- Lock de botões (`DataJudCycle.snapshotPageButtons`) — não somos chamados a partir desse ciclo.

## Política de uso

- Primeira tela piloto a habilitar: `app/views/internal/cartorio-cartas-expedidas-create.php`.
- Demais telas só ganham o módulo após validação visual na piloto.
- Não use `mniProcessoResolved` para tomar decisões críticas (ex.: bloquear submit) — trate como informação extra.
