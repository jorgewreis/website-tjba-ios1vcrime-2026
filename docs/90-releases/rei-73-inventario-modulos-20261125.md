# REI-73 — inventário textual de módulos e views

## Base e método

O [CSV](rei-73-inventario-modulos-20261125.csv) enumera blobs rastreados de `app/` e `services/` no commit `63eb1da4a7227e94cece467ef2d57fbd8020e416`, observado como `origin/main` em 25/11/2026. É complementar ao [inventário de 77 arquivos](rei-73-inventario-nonapi-20261125.md), com outra base histórica, e ao inventário próprio de APIs. O script lê a árvore e os blobs pelo Git; não usa conteúdo alterado no checkout nem executa os arquivos.

São **270 arquivos**: 265 em `app/` e cinco em `services/`; 264 PHP, três Python, um HTML, um Markdown e um TXT. As famílias são classificadas por caminho: `app/DataJud` 2, `app/Mni` 9, `app/access` 5, `app/config` 1, `app/data` 1, `app/helpers` 61, `app/middlewares` 1, `app/models` 1, `app/repositories` 48, `app/rules` 1, `app/services` 35, `app/views/internal` 100 e `services/pje_public_search_api` 5. A classificação pelo diretório é indicativa e não identifica o responsável real, a operação ou um ponto de entrada.

Cada linha inclui commit, família, extensão, caminho, hash Git do blob e números de linhas com os padrões textuais definidos em `tests/audit/rei73-modulos-inventory.py`: includes/requires, imports, nomes de acesso, sessão, CSRF, método, persistência, rede e erros. São buscas textuais limitadas, sujeitas a comentários, strings, aliases e outros falsos positivos ou negativos; até `file_get_contents` pode apontar para arquivo local. Os **38 arquivos sem marcador pesquisado** não foram classificados como desprotegidos. A presença de um marcador não comprova execução, ordem, controle equivalente, autorização, transação ou segurança. Os números são do blob indicado.

## Limites e próximo passo

Não foi construído grafo de chamadas ou resolvidos includes dinâmicos; imports indicam somente ocorrências textuais. Não foram identificados todos os chamadores, responsáveis, operações e perfis por módulo, tampouco inspecionada individualmente a lógica dos 270 arquivos. As 100 views não são necessariamente rotas: aliases do frame, acesso direto e controles herdados requerem revisão. A família `services/pje_public_search_api` inclui documentação e suporte, sem inferir que o serviço esteja acessível ou configurado.

Permanecem fora desta enumeração `config/` da raiz, `.github/workflows/`, `lib/`, `bin/`, banco e migrações, recursos estáticos, outras árvores, arquivos não rastreados, links simbólicos e configuração efetiva de servidor, scheduler e integrações. O inventário anterior também contém exclusões; reunir listas não prova descoberta completa de entrypoints. Não houve execução funcional, consulta ao banco ou acesso a produção. Reavaliar árvore e blobs na base vigente, seguir cada entrada até seus serviços, controles e persistência e validar hipóteses em ambiente sintético antes de concluir os critérios da REI-73.

Foram consultados o aceite e relações da REI-73 no Linear, o início da página de acompanhamento do Notion e a consolidação e o inventário anteriores do repositório. Estas fontes não substituem revisão completa de comentários, issues relacionadas ou configuração implantada.

## Reprodução

```powershell
python tests/audit/rei73-modulos-verify.py 63eb1da4a7227e94cece467ef2d57fbd8020e416 docs/90-releases/rei-73-inventario-modulos-20261125.csv
```

O inventariador emite UTF-8 com finais de linha LF. O verificador regenera o CSV em memória e compara seus bytes após normalizar eventuais CRLF do checkout para LF; CR isolado é rejeitado. Confere também unicidade, caminhos, blobs e commit com `git ls-tree`. Ambos os scripts executam somente comandos Git de leitura e não executam conteúdo dos blobs. Para outra base, gere um novo CSV pelo inventariador, atualize os totais e compare alterações antes de reutilizar evidências. A verificação prova reprodução e correspondência com essa árvore, não cobertura funcional ou validade semântica dos marcadores.
