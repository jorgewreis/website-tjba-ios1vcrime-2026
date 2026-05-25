# tests-mni — Testes CLI do cliente MNI (CNJ)

Pasta isolada para validar o cliente MNI (`app/Mni/*`) **antes** de qualquer integração nas páginas.

> Estes scripts **só rodam via CLI** (`PHP_SAPI === 'cli'`). Não são acessíveis por HTTP.

## Pré-requisitos

1. Variáveis no `.env` (ver `config/mni.example.php` e `.env.example`):
   ```env
   MNI_TJBA_ENDPOINT=https://pje.tjba.jus.br/pje-1g/intercomunicacao
   MNI_TJBA_VERSION=2.2.2
   MNI_TJBA_ID_CONSULTANTE=...
   MNI_TJBA_SENHA_CONSULTANTE=...
   MNI_HTTP_TIMEOUT_MS=30000
   MNI_HTTP_CONNECT_TIMEOUT_MS=10000
   ```
2. PHP CLI ≥ 8.0 com extensões `curl` e `simplexml` ativas.

### Versões MNI suportadas

| Versão | Status | Observação |
|---|---|---|
| `2.2.2` / `2.2.3` | suportado | Envelope plano (`<int:consultarProcesso>`). SOAPAction é só o nome da operação. Usado pela maioria dos PJe atuais (inclusive TJBA). |
| `3.0.0` | suportado | Envelope wrapper `<tic:requisicaoConsultarProcesso>` com `<consultante><pji:autenticacaoSimples>`. SOAPAction precisa ser URL completa (`http://www.cnj.jus.br/mni/v300/consultarProcesso`). Suporta flags extras `incluirPartes` / `incluirEnderecos` e devolve `<recibo>` com `reciboToken` opcional. |

Os XSDs e o WSDL oficiais v300 ficam em `api/mni3/xsd_wsdl/` (referência local — evita depender de download do CNJ). Fonte das mudanças v3 na implementação:

- `tipoAutenticacaoSimples` (`<usuario>` / `<senha>`, **não** `idConsultante`/`senhaConsultante`)
- `tipoCabecalhoProcesso` (campos novos lidos pelo mapper: `prioridade`, `nivelSigilo`, `valorCausa`, `competencia`, `magistradoAtuante`, `pedidoLiminarPendente`, `processoFisico`, `intervencaoMP`, `tamanhoProcesso`, `historicoDeslocamento`, `processoVinculado`)
- `tipoOrgaoJulgador` (`<codigo>` / `<nome>` / `<instancia>` / `<codigoLocalidade>`)
- `tipoPoloProcessual` (polos completos: `AT`, `PA`, `TC`, `FL`, `TJ`, `AD`, `VI`)
- `tipoDocumento` (mimetype/hash ficam dentro de `<conteudo>`; flag `criptografado` por heurística quando há binário sem mimetype)

## Ordem de execução

| # | Script | O que valida | Critério de avanço |
|---|--------|--------------|---------------------|
| 1 | `01-conectividade.php` | Vars no .env, DNS, TCP/TLS, WSDL, autenticação contra CNJ dummy | exit 0 = OK; salva WSDL em `fixtures/` |
| 2 | `02-consultar-processo-real.php <CNJ>` | Consulta processos reais do TJBA, gera shape comum, salva XML sanitizado | exit 0 = todos CNJs resolvidos (ok / sigilo / not_found) |
| 3 | `04-edge-cases.php` | DV inválido, fora TJBA, credencial errada, timeout, endpoint inexistente | exit 0 = todos retornam `kind` esperado |
| 4 | `05-consultar-alteracao.php <CNJ>` | Polling barato (`consultarAlteracao` v3) — devolve `hashCabecalho`/`hashMovimentacoes`/`hashDocumentos` e novo `reciboToken` quando o servidor entrega | exit 0 = todos CNJs resolvidos (ok / sigilo / not_found); XML sanitizado em `fixtures/raw/` |

`03-comparar-datajud.php` (Fase 3) entra depois — ele consome o relatório acumulado dos anteriores.

## Como rodar (Windows PowerShell)

```powershell
# 1. Conectividade
php tests-mni/01-conectividade.php

# 2. Consulta real (passe um CNJ válido do TJBA)
php tests-mni/02-consultar-processo-real.php 0000000-00.0000.8.05.0103

# Múltiplos CNJs
php tests-mni/02-consultar-processo-real.php "CNJ1,CNJ2,CNJ3"
# ou via env:
$env:MNI_TEST_CNJS = "CNJ1,CNJ2"; php tests-mni/02-consultar-processo-real.php

# 3. Edge cases
$env:MNI_TEST_CNJ_VALIDO = "CNJ-real-do-TJBA"
$env:MNI_TEST_CNJ_TJSP  = "CNJ-valido-de-outro-tribunal"
php tests-mni/04-edge-cases.php

# 4. consultarAlteracao (polling barato — Bloco C.2)
php tests-mni/05-consultar-alteracao.php 0000000-00.0000.8.05.0103
```

## Como interpretar a saída

- `[OK]` — etapa esperada confirmada.
- `[WARN]` — comportamento aceitável, mas merece olhar (ex.: WSDL não devolvido com 200, sigilo).
- `[FAIL]` — bloqueador real (configuração, autenticação, falha de rede).
- Cada script termina com `exit 0` (sucesso) ou `exit 1` (algo falhou).

## Política de fixtures

- WSDLs e respostas XML caem em `tests-mni/fixtures/`.
- A senha do consultante é mascarada antes de salvar.
- **Não comitar** XMLs que contenham nomes/CPFs reais — limpar manualmente antes de versionar exemplos.

## Próximos passos após Fase 2 OK

Quando os três scripts saírem `exit 0` em ambiente local:
1. Escrever `tests-mni/relatorio-fase2.md` (resumo dos casos + tempos).
2. Avançar para Fase 3 (`03-comparar-datajud.php`) e gerar `relatorio-fase3.md` com a decisão de quais campos do MNI vamos persistir.
