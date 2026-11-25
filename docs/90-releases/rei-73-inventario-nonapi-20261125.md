# REI-73 — inventário reproduzível de superfícies complementares

## Base e alcance

Snapshot de `33baf0995ca3fb3ab22331c8c56bc1abc714accf`, commit `origin/main` observado em 25/11/2026. O checkout usado para gerar o relatório tinha outro HEAD e arquivos locais preservados: o script lê os blobs do commit pelo Git, não o conteúdo da árvore de trabalho. A existência no Git não demonstra publicação nem acessibilidade HTTP.

O [CSV](rei-73-inventario-nonapi-20261125.csv) enumera **77 blobs**: 19 PHP em `public/` fora de `public/api/` (inclui fragmentos e `public/admin/api/`), cinco PHP na `api/` legada da raiz, todos os 44 arquivos de `scripts/`, e nove arquivos PHP/Python/shell/PowerShell em `tools/`. Um arquivo não é necessariamente um endpoint: views, bibliotecas e ferramentas compartilham o inventário. `public/api/` tem inventário próprio na pasta `rei-73-cobertura-20261125/`.

As colunas registram commit, categoria, caminho, identificador Git do blob e linhas com padrões textuais diretos. O rótulo `auth`, por exemplo, indica uma ocorrência dos nomes pesquisados, inclusive em comentários ou strings; não prova chamada efetiva, ordem, suficiência nem aplicação por operação. `sem marcador pesquisado` significa somente que nenhum dos padrões limitados ocorreu no próprio arquivo. Controles podem estar em includes, bootstrap, proxy ou SO. O catálogo de padrões está no script; os números de linha remetem ao blob indicado.

## Leituras dirigidas e pendências

- `public/frame.php` e `public/home.php`: a análise direcionada em [frame/home](rei-73-frame-home-20261125.md) registra autenticação e avaliação de acesso no fluxo examinado. O CSV aponta ocorrências em ambos, sem provar todos os aliases, perfis compostos, URLs diretas ou regras das views incluídas.
- Login, logout e recuperação: `public/login.php` e `public/logout.php` contêm marcadores de método e CSRF; formulários e wrappers têm marcadores diferentes. A [cobertura complementar](rei-73-non-api-coverage-20261125.md) e [evidências consolidadas](rei-73-evidencias-consolidadas-20261125.md) distinguem cobertura histórica e testes sintéticos. Não extrapolar o login de ADMIN para recuperação, outras identidades ou controles efetivos.
- `api/*.php`: cinco arquivos legados apresentam marcador `auth`; sua inclusão preserva a distinção de `public/api/`. Chamadores, política por operação e exposição pelo Document Root não foram estabelecidos aqui.
- `scripts/purge-inactive-external-users.php` e os instaladores cron, inclusive o envio de resumo: a [análise CLI/cron](rei-73-cli-cron-20261125.md) descreve guardas e efeitos específicos, com ressalvas sobre concorrência, exclusão, entrega e scheduler. O inventário inclui também migrações e scripts `drop`/`patch`: nenhum foi executado ou auditado integralmente nesta etapa. Tratar seu acesso e efeitos como não verificados até revisão individual.
- `tools/ai/` e ferramentas de migração/alteração de arquivos são inventariadas por extensão; não foram revisadas funcionalmente nem chamadas. Seus controles, dados usados e dependências externas estão pendentes.

Ficam fora desta enumeração `app/` e `services/` (incluindo views internas e serviços transitivos), `config/`, `.github/workflows/`, binários/dependências, arquivos não rastreados, outros tipos de arquivo, configuração implantada de servidor/cron e integrações externas. Os scripts de deploy e workflows precisam de inventário próprio. Não houve descoberta dinâmica de rotas, leitura de banco ou inspeção de produção. O conjunto não comprova que todos os pontos de entrada do sistema estejam cobertos e não satisfaz por si só o aceite da REI-73.

## Reprodução e verificação

Em um checkout que contenha o script e o commit acima:

```powershell
python tests/audit/rei73-nonapi-inventory.py 33baf0995ca3fb3ab22331c8c56bc1abc714accf > inventario.csv
```

O script invoca somente `git rev-parse`, `git ls-tree` e `git cat-file`, lê blobs e escreve CSV na saída padrão; não executa o conteúdo. A comparação deve considerar diferenças de BOM e finais de linha produzidas pelo shell. Para outra base, trocar o SHA, comparar inclusão/exclusão e blobs e reavaliar manualmente controles alterados. Isso não substitui revisão de chamadas indiretas ou testes executáveis.

Foram consultados a REI-73 no Linear e a página de acompanhamento no Notion, além das matrizes vinculadas. A revisão desta etapa é inventário textual e leitura das fontes acima: nenhum teste funcional, banco, serviço externo, agendador ou deploy foi executado.
