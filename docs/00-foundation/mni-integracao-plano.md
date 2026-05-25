# MNI (CNJ) — Plano de Integração + Achados v3.0.0

> Plano vivo da integração com o **Modelo Nacional de Interoperabilidade (MNI)** do CNJ.
> Última revisão: 2026-05-25 — Bloco C (token cache + consultarAlteracao) entregue.

---

## 1. Decisões de produto

| Item | Decisão |
|---|---|
| Estratégia | **MNI complementa o DataJud, com orquestração**. DataJud é a 1ª fonte (público, rápido). MNI é enriquecimento autenticado, executado em sequência no mesmo request. |
| Credenciais | TJBA produção (id/senha de consultante) via `.env` — nunca versionar segredos. |
| Falhas do MNI | Nunca derrubam a resposta principal: viram `enrichment_error` (`kind` + `message`). |
| Persistência | **Não persistir** dados extras do MNI no banco até a Fase 3 (relatório de campos) ser assinada. |
| Frontend | Módulo `public/assets/js/modules/mni/` é **opt-in** (não bloqueia DataJud, nunca interfere no fluxo). |

---

## 2. Arquitetura (estado atual)

```
public/api/mni/consultar-processo.php         ← endpoint HTTP (sessão exigida)
        │
        ▼
app/services/MniService.php                    ← cache in-process + retry simples
        │
        ▼
app/services/ProcessoLookupService.php         ← facade: DataJud → MNI → enrichment
        │
        ▼
app/Mni/                                       ← componentes puros (sem sessão/banco)
  ├ MniClient.php          ← cURL + envelope, orquestração
  ├ MniRequestBuilder.php  ← envelope v2.2.x e v3.0.0
  ├ MniResponseParser.php  ← XML → array (lê <recibo> v3 também)
  ├ MniProcessoMapper.php  ← array → shape comum (DataJud-compatible + chave 'mni')
  ├ MniCredentials.php     ← leitura .env, máscara em logs
  ├ MniTribunalResolver.php← roteamento por dígitos CNJ
  ├ MniErrorTranslator.php ← cURL/HTTP/Fault → kind+message
  └ MniEnums.php           ← prioridade, sigilo, polo, comunicação (NOVO)

tests-mni/                                     ← CLI tests (não rodam por HTTP)
  ├ _bootstrap.php
  ├ 01-conectividade.php
  ├ 02-consultar-processo-real.php
  ├ 03-comparar-datajud.php
  ├ 04-edge-cases.php
  └ fixtures/                  ← XML/WSDL sanitizados

api/mni3/xsd_wsdl/                             ← XSDs/WSDL oficiais v300 (referência)
```

---

## 3. Achados dos XSDs/WSDL oficiais (`api/mni3/xsd_wsdl/`)

### 3.1 Namespaces v3.0.0 corretos

Versão | tipos-servico | intercomunicacao
---|---|---
2.2.2 | `http://www.cnj.jus.br/tipos-servico-intercomunicacao-2.2.2` | `http://www.cnj.jus.br/servico-intercomunicacao-2.2.2/`
2.2.3 | `http://www.cnj.jus.br/tipos-servico-intercomunicacao-2.2.3` | `http://www.cnj.jus.br/servico-intercomunicacao-2.2.3/`
**3.0.0** | **`http://www.cnj.jus.br/mni/v300/tipos-servico-intercomunicacao`** | **`http://www.cnj.jus.br/mni/v300/intercomunicacao`**

> Atenção: minha primeira versão usava `servico-intercomunicacao-3.0/` (errado). Corrigido em `MniRequestBuilder::NS_BY_VERSION`.

### 3.2 Envelope `consultarProcesso` — diferenças por versão

**v2.2.x** (legado, plano):

```xml
<int:consultarProcesso>
  <tip:idConsultante>...</tip:idConsultante>
  <tip:senhaConsultante>...</tip:senhaConsultante>
  <tip:numeroProcesso>...</tip:numeroProcesso>
  <tip:incluirCabecalho>true</tip:incluirCabecalho>
  <tip:movimentos>true</tip:movimentos>
  <tip:incluirDocumentos>false</tip:incluirDocumentos>
</int:consultarProcesso>
```

**v3.0.0** (wrapper + credencial estruturada):

```xml
<tic:requisicaoConsultarProcesso>
  <tic:consultante>
    <pji:autenticacaoSimples>
      <pji:usuario>...</pji:usuario>
      <pji:senha>...</pji:senha>
    </pji:autenticacaoSimples>
  </tic:consultante>
  <tic:numeroProcesso>...</tic:numeroProcesso>
  <tic:incluirCabecalho>true</tic:incluirCabecalho>
  <tic:incluirPartes>true</tic:incluirPartes>       <!-- NOVO -->
  <tic:incluirEnderecos>false</tic:incluirEnderecos><!-- NOVO -->
  <tic:incluirMovimentos>true</tic:incluirMovimentos>
  <tic:incluirDocumentos>false</tic:incluirDocumentos>
</tic:requisicaoConsultarProcesso>
```

A credencial v3 também aceita (via `xsd:choice`):
- `<pji:token>...</pji:token>` — token devolvido pelo recibo (sessão sem reautenticar)
- `<pji:autenticacaoCertificada>` — assinatura digital (ICP-Br)

### 3.3 Resposta v3 — `<recibo>` em vez de `sucesso`/`mensagem` raiz

```xml
<tic:respostaConsultarProcesso>
  <tic:recibo>
    <pji:sucesso>true|false</pji:sucesso>
    <pji:mensagens>texto 1</pji:mensagens>
    <pji:mensagens>texto N</pji:mensagens>
    <pji:reciboToken>           <!-- OPCIONAL -->
      <pji:token>...</pji:token>
      <pji:dataExpiracao>...</pji:dataExpiracao>
    </pji:reciboToken>
  </tic:recibo>
  <tic:processo>...</tic:processo>
</tic:respostaConsultarProcesso>
```

O parser foi atualizado para extrair tanto `<consultarProcessoResponse>` (v2) quanto `<respostaConsultarProcesso>` (v3), e devolve `mensagens` (lista) + `recibo_token` quando presente.

### 3.4 SOAPAction precisa ser URL completa na v3

| Versão | SOAPAction header |
|---|---|
| 2.2.x | `"consultarProcesso"` (nome simples) |
| **3.0.0** | `"http://www.cnj.jus.br/mni/v300/consultarProcesso"` |

`MniRequestBuilder::soapActionFor($operation, $version)` faz a distinção.

### 3.5 Campos novos do cabeçalho v3 que o mapper agora extrai

Todos vão para `shape['mni']['cabecalho']`, e os que têm equivalente no shape DataJud também são populados no topo:

| Campo MNI | Posição no shape | Observação |
|---|---|---|
| `prioridade` (modalidadePrioridade) | `shape['prioridades']` (codigo+descricao) e `mni.cabecalho.prioridade` | Enum: `IDOSO`, `REU_PRESO`, `PERECIMENTO`, `MENOR`. **DataJud não traz** — enriquecimento real. |
| `nivelSigilo` (0–5) | `shape['nivel_sigilo']` e `mni.cabecalho.nivel_sigilo*` | 0=público, 1=segredo, 2-5=sigilo crescente. |
| `valorCausa` | `mni.cabecalho.valor_causa` | float |
| `competencia` | `mni.cabecalho.competencia` | int (catálogo CNJ) |
| `magistradoAtuante` | `mni.cabecalho.magistrados_atuantes` | lista de CPF/CNPJ |
| `pedidoLiminarPendente` | `mni.cabecalho.pedido_liminar_pendente` | bool |
| `processoFisico` | `mni.cabecalho.processo_fisico` | bool |
| `intervencaoMP` | `mni.cabecalho.intervencao_mp` | bool |
| `tamanhoProcesso` | `mni.cabecalho.tamanho_processo_bytes` | int |
| `outrosNumeros` | `mni.cabecalho.outros_numeros` | lista de strings |
| `historicoDeslocamento` | `mni.cabecalho.historico_deslocamento` | lista (origem/destino/dataHora) |
| `processoVinculado` | `mni.cabecalho.processo_vinculado` | lista (numero/tipo) |

### 3.6 Polos completos (eram só 4, agora são 8)

A v3 reconhece: `AT` (ativo), `PA` (passivo), `TC` (terceiro), `FL` (fiscal da lei), `TJ` (testemunha do juízo), `AD` (amicus curiae), `VI` (vítima), e `OT` (fallback). `MniEnums` e `MniProcessoMapper::extractPartes()` cobrem todos.

### 3.7 Documentos podem vir criptografados

`<tipoDocumento>` tem `<conteudo>` opcional com `mimetype` + `hash` + `conteudo` (base64). Em casos de intimação pendente, o conteúdo binário vem cifrado — a chave só vem via `consultarTeorComunicacao`. O mapper marca `criptografado=true` por heurística (binário presente sem mimetype).

### 3.8 Operações úteis do WSDL que **ainda não usamos**

| Operação | Uso potencial |
|---|---|
| `consultarLocalidades` | alimentar lista de comarcas/cidades-destino na tela de cartas expedidas |
| `consultarClasses` | sincronizar catálogo CNJ de classes (hoje vem só do BD local) |
| `consultarAssuntos` | sincronizar catálogo CNJ de assuntos |
| `consultarCompetencias` | catálogo de competências |
| `consultarAlteracao` | polling barato — detecta se processo mudou sem baixar tudo |
| `consultarDocumentosProcesso` | buscar só documentos (separado do cabeçalho) |
| `consultarAvisosPendentes` + `consultarTeorComunicacao` | receber intimações no sistema (fluxo futuro grande) |
| `entregarPeticaoInicial` / `entregarPeticao` / `responderComunicacao` | peticionamento via MNI (fluxo futuro) |

---

## 4. Fases — status

| Fase | Escopo | Status |
|---|---|---|
| 0 | Provisionamento (`.env.example`, `config/mni.example.php`, .gitignore) | ✅ |
| 1 | Cliente puro em `app/Mni/` (incluindo `MniEnums.php`) | ✅ + v3 corrigida |
| 2 | Testes CLI em `tests-mni/` (01, 02, 04) | ✅ |
| 3 | Comparativo MNI × DataJud (`03-comparar-datajud.php` + `relatorio-fase3.md`) | ✅ template |
| 4 | Endpoint HTTP `public/api/mni/` | ✅ |
| 5 | `MniService` + `ProcessoLookupService` facade | ✅ + prioridade/sigilo no enrichment |
| 6 | Frontend opt-in `public/assets/js/modules/mni/` | ✅ |
| 7 | Hardening (rate-limit, auditoria, observabilidade) | pendente |
| **C.1** | **Cache de `reciboToken` em arquivo (`storage/mni-tokens/`)** | ✅ |
| **C.2** | **Cliente `consultarAlteracao` (polling barato)** | ✅ |

---

## 5. Follow-ups identificados (após análise dos XSDs)

### 5.1 Bloco C — otimizações

- [x] Cache de `reciboToken` em arquivo + envelope `<pji:token>` com fallback automático para `<pji:autenticacaoSimples>`. Detalhes em §8.1.
- [x] Cliente `consultarAlteracao()` (v3 e v2.x), parser dedicado e CLI `tests-mni/05-consultar-alteracao.php`. Detalhes em §8.2.

### 5.2 Bloco D — novas fases (opcionais, futuro)

- [ ] **Fase 8 (opcional)** — Sincronização de catálogos via MNI: `consultarLocalidades`, `consultarClasses`, `consultarAssuntos`, `consultarCompetencias` rodando como CLI agendado. Aplicação direta: alimentar dropdown de cidades-destino na tela de cartas expedidas.
- [ ] **Fase 9 (futuro)** — Recebimento de intimações: `consultarAvisosPendentes` + `consultarTeorComunicacao`.
- [ ] **Fase 10 (futuro)** — Peticionamento: `entregarPeticao` / `entregarPeticaoInicial` / `responderComunicacao`.

### 5.3 Persistência de campos enriquecidos

Após a Fase 3 (relatório comparativo MNI × DataJud) ser assinada, decidir quais campos do MNI v3 entram no banco:
- Prioridade nativa do MNI (`modalidadePrioridade`) — candidato a substituir/complementar `processo_prioridades` local.
- Nível de sigilo — pode influenciar regras de visibilidade do sistema.
- `processoFisico` — útil em telas que diferenciam físico/eletrônico.
- `pedidoLiminarPendente` — flag de UX.
- `magistradoAtuante` — útil em fluxos de gabinete.

---

## 6. Como rodar testes (resumo)

Detalhes em `tests-mni/README.md`. CLI obrigatório.

```powershell
php tests-mni/01-conectividade.php
php tests-mni/02-consultar-processo-real.php "CNJ1,CNJ2"
php tests-mni/04-edge-cases.php
```

Para validar especificamente a v3 quando TJBA disponibilizar:

```env
MNI_TJBA_VERSION=3.0.0
MNI_TJBA_ENDPOINT=<endpoint do PJe TJBA na v300>
```

Os XSDs locais em `api/mni3/xsd_wsdl/` permitem validar payloads gerados antes mesmo de bater no servidor (via `xmllint --schema` ou similar).

---

## 7. Como rodar `consultarAlteracao` (CLI)

```powershell
# CNJ unico
php tests-mni/05-consultar-alteracao.php 0000000-00.0000.8.05.0103

# Multiplos
php tests-mni/05-consultar-alteracao.php "CNJ1,CNJ2"

# Via env
$env:MNI_TEST_CNJS = "CNJ1,CNJ2"; php tests-mni/05-consultar-alteracao.php
```

Saida esperada (resposta bem-sucedida): `hashCabecalho`, `hashMovimentacoes`, `hashDocumentos`. Erros traduzidos: `sigilo`, `not_found`, `auth`, `network`, `timeout`, `http`.

---

## 8. Otimizações implementadas (Bloco C)

### 8.1 Cache de `reciboToken` (sessão sem reautenticar)

**Por que** — Na v3, a primeira chamada autenticada com `<pji:autenticacaoSimples>` (usuário/senha) devolve, dentro de `<recibo>`, um `<reciboToken>` com `<token>` e `<dataExpiracao>`. Chamadas subsequentes podem trocar o par usuário/senha por esse token, economizando o "custoso procedimento de credenciamento" (palavras do XSD: `tipoReciboTokenAutenticacao`). Esse cache é interno; o endpoint HTTP `public/api/mni/consultar-processo.php` permanece igual.

**Armazenamento** — `app/Mni/MniTokenStore.php`:

- Base padrão: `storage/mni-tokens/` (criada com `0700` quando possível; fallback automático para `sys_get_temp_dir() . '/mni-tokens/'`).
- 1 arquivo JSON por chave. Nome do arquivo = `sha256(version | endpoint | idConsultante)`.
- Conteúdo: `{ "token": "...", "data_expiracao": "...", "criado_em": "...", "version": "..." }`.
- Escrita atômica: `fopen+flock(LOCK_EX)+fwrite+fflush` → `rename(.tmp, .json)` + `chmod 0600`. Evita corrupção sob concorrência.
- Leitura: respeita `dataExpiracao` (ISO 8601, fuso aceito) e descarta o token se restarem menos de `MniTokenStore::SAFETY_SECONDS` (atualmente 30s).
- Métodos: `get()`, `put()`, `invalidate()`, `basePath()`.
- Sem dependências externas. Funciona em CLI e HTTP.

**Política de uso no `MniClient`** — só ativo na v3 e quando há `tokenStore` injetado:

1. Antes da chamada: tenta `MniTokenStore::get()`.
2. Se obtém token válido: monta envelope com `<consultante><pji:token>...</pji:token></consultante>` (via `MniRequestBuilder::buildConsultanteV3()`, que respeita o `xsd:choice` `token | autenticacaoSimples | autenticacaoCertificada` definido em `intercomunicacao.xsd`).
3. Se a chamada falhar com `kind=auth` (HTTP 401, sigilo/permissão em fault ou `sucesso=false` com mensagem contendo "token/credencia/senha"): **invalida** o cache e refaz a chamada **uma vez** com `<pji:autenticacaoSimples>`. Esse retry é interno e ortogonal ao retry de timeout/network do `MniService`.
4. Se a nova resposta trouxer `<reciboToken>`, persiste no store.
5. v2.2.x ignora todo o ciclo silenciosamente (não há token no XSD legado).

**Política de uso no `MniService`** — `use_token_cache` (default `true`) pode ser desligado por chamada:

```php
$svc = new MniService();
$svc->consultar($cnj, ['use_token_cache' => false]); // forca autenticacaoSimples
```

`MniService::__construct` instancia `MniTokenStore` a partir de `<repo>/storage/mni-tokens` automaticamente; é possível injetar um store custom pelo segundo parâmetro.

**Política de invalidação manual** — apagar arquivos em `storage/mni-tokens/` é seguro (no próximo uso o cliente volta a autenticar simples). O `.gitignore` da pasta garante que nunca seja versionado.

**Meta exposto na resposta do client** — além de `meta.http_code/elapsed_ms/endpoint/version`, agora:

- `meta.recibo_token` (`null` ou `{token, data_expiracao}`).
- `meta.used_token` (`bool`): se `true`, o cliente enviou `<pji:token>`. Útil para logs e debug.

### 8.2 `consultarAlteracao` (polling barato)

**Por que** — Operação do XSD oficial v3 (`tipos-servico-intercomunicacao.xsd`, `requisicaoConsultarAlteracao`/`respostaConsultarAlteracao`) que devolve apenas hashes do processo, sem trazer cabeçalho/movimentos. Permite ao sistema (em fase futura) decidir se vale a pena reconsultar `consultarProcesso`. Não há endpoint HTTP exposto: é feature de plataforma para uso interno.

**Requisição v3** — `MniRequestBuilder::buildConsultarAlteracao()`:

```xml
<tic:requisicaoConsultarAlteracao>
  <tic:consultante>
    <pji:autenticacaoSimples><pji:usuario>.../<pji:senha>...</pji:autenticacaoSimples>
    <!-- ou <pji:token>...</pji:token> quando $options['auth_token'] esta presente -->
  </tic:consultante>
  <tic:numeroProcesso>{20 digitos}</tic:numeroProcesso>
</tic:requisicaoConsultarAlteracao>
```

> O XSD oficial v300 não declara `dataReferencia` em `requisicaoConsultarAlteracao` (apenas `consultante` + `numeroProcesso`). A chave `$options['data_referencia']` é forward-compat: só emite `<tic:dataReferencia>` se explicitamente passada — vai como extensão e fica a critério do tribunal aceitar.

**Requisição v2.2.x** — fallback plano análogo a `consultarProcesso` v2:

```xml
<int:consultarAlteracao>
  <tip:idConsultante>...</tip:idConsultante>
  <tip:senhaConsultante>...</tip:senhaConsultante>
  <tip:numeroProcesso>...</tip:numeroProcesso>
</int:consultarAlteracao>
```

**Resposta** — `MniResponseParser::parseConsultarAlteracaoResponse(xml, version)` devolve:

```php
[
  'ok'           => bool,
  'sucesso'      => bool|null,                  // do <recibo><sucesso>
  'mensagens'    => string[],                   // do <recibo><mensagens>
  'recibo_token' => null | ['token'=>..., 'data_expiracao'=>...],
  'alteracoes'   => [
    'hashCabecalho'     => string?,   // opcional no XSD
    'hashMovimentacoes' => string?,   // opcional no XSD
    'hashDocumentos'    => string?,   // opcional no XSD
    'dataReferencia'    => string?,   // se o servidor incluir
  ],
  'fault'        => null | ['code'=>..., 'string'=>...],
]
```

**Exemplo de uso** (com cache de token automático):

```php
$svc    = new MniService();
$client = new MniClient($svc->credentials(), ['use_token_cache' => true], $svc->tokenStore());
$res    = $client->consultarAlteracao('00000000020268050103');
if ($res['ok']) {
    $hc = $res['alteracoes']['hashCabecalho']     ?? null;
    $hm = $res['alteracoes']['hashMovimentacoes'] ?? null;
    $hd = $res['alteracoes']['hashDocumentos']    ?? null;
    // comparar com hashes locais e decidir se reconsulta consultarProcesso
}
```

**SOAPAction** — `MniRequestBuilder::soapActionFor('consultarAlteracao', '3.0.0')` devolve `http://www.cnj.jus.br/mni/v300/consultarAlteracao` (URL completa, conforme `servico-intercomunicacao.wsdl`).

**Limites conhecidos**

- A v2.x do XSD/WSDL não está no repo; o envelope v2 foi implementado por analogia a `consultarProcesso` v2. Se o servidor recusar, a v3 já cobre o caso de uso.
- Resposta de SOAP Fault é tratada normalmente pelo `MniErrorTranslator`.
- `auth_token` no `consultarAlteracao` segue o mesmo retry de invalidação que `consultarProcesso` (token inválido → invalida → refaz com autenticação simples).
