# Ofícios: browser sintético de REI-73 / REI-90

Artefatos candidatos a versionamento: `render.php`, `run.cjs` e este README.
Saídas são escritas fora do checkout; não há `.gitignore` genérico. Não há alteração de
produto nem integração automática com CI.

## Pré-requisitos e execução

- Node.js, PHP com suporte às classes do projeto, checkout Git.
- Playwright instalado e Chromium já disponível. O teste não baixa pacotes ou
  navegadores e não instala dependências.
- Resolução normal de `require('playwright')`, ou variável explícita
  `REI73_PLAYWRIGHT_MODULE` apontando ao pacote local aprovado.
- `REI73_PHP` opcional seleciona o executável PHP; padrão `php` no PATH.

Na raiz do checkout:

```sh
node tests/ui/rei73-oficios-browser/run.cjs
```

Ausência do Playwright: saída 2, mensagem `PREREQUISITE FAILED`. Ausência de
Chromium ou falha funcional: saída não zero. Não há skip silencioso.
Cada execução cria diretório único `rei73-oficios-ui-evidence-*` no TEMP do
sistema e imprime o caminho. Preserva JSON, screenshots e dados de procedência.

## Contrato e isolamento

O renderizador extrai a linha de listagem, labels, partials e scripts inline
da view real. As bordas são verificadas e falham se deixarem de ser únicas.
Somente `__DIR__` do fragmento extraído é substituído pelo diretório original
para resolver seus includes. Não executa bootstrap, frame ou consultas da view.
Uma classe sentinela impede uso de `Conection::getInstance()`.
O renderizador usa `eval` exclusivamente sobre os fragmentos PHP versionados
do checkout local. Execute apenas em checkout confiável e previamente revisado;
esta fixture não é um sandbox para executar código PHP não confiável.

Dados sintéticos representam envio, juntada e finalização. O navegador usa
`oficios-list.js` e CSS reais. Todas as requisições são interceptadas antes da
rede; fontes Google recebem CSS vazio, requests inesperados falham. A notificação
é capturada por adapter `showToast`; não valida o componente visual de toast.

Oito cenários: HTTP 401/403/400/500, rede abortada, JSON inválido com HTTP 200, sucesso de envio
e de juntada. Verifica recuperação do botão, ausência de avanço/reload no erro,
POST com ID esperado, sucesso/reload e manutenção do estado sintético no reload.
Só aceita os cinco erros de console previstos das recusas HTTP/rede, vinculados
ao endpoint interceptado. Evidências incluem estado dos arquivos rastreados,
hashes dos assets servidos e URLs/hashes das substituições locais de fontes.
O diretório é impresso antes do render, e falha do render preserva diagnóstico.

Não demonstra sessão/autenticação real, servidor, persistência, autorização,
concorrência nem proibição de avanço fora de ordem no backend. REI-90 não pode
ser encerrada com esta prova. Capturas mostram fragmento, não a página completa;
não constituem avaliação completa de layout, responsividade ou acessibilidade.
