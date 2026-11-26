# REI-92 · Fronteira da consulta pública ao DataJud

Revisão do código da Vara 2026, base `c96eb38`. Não houve consulta, teste de abuso, leitura de configuração efetiva nem alteração de permissão em produção.

## Consumidores e contrato

- O cliente versionado `public/assets/js/modules/datajud/datajud-api.js` envia POST de mesma origem para `/api/datajud/search-processo.php`, via parcial `app/views/internal/_datajud_scripts.php`. O parcial aparece em dez views internas de Cartório, Gabinete e Audiências. Não encontrei uma página pública versionada que o carregue. Isso não permite descartar consumidores externos não versionados.
- O endpoint de pesquisa não exige sessão nem limita chamadas no próprio código. A credencial permanece no servidor. A API pública do CNJ ser consultável não significa que a aplicação da Vara precise oferecer um intermediário anônimo.
- As rotas de sincronização e a fila no servidor não dependem dessa rota do navegador. A REI-108 trata de falhas da origem e gravações; a REI-92 trata de quem pode chamar a pesquisa e com que frequência.
- O cliente do navegador admite até três tentativas em timeout. Acesso legítimo, limites no Cloudflare/Nginx e causa exata de HTTP 429 observado na REI-108 não foram verificados. Não inferir abuso ou extrapolação de uma quota com base em 429 ou na ausência de um controle no PHP.

## Decisão proposta

Confirmar se algum consumidor legítimo usa o endpoint sem login. Se não, restringir a sessão interna autorizada e a POST de mesma origem, preservando a consulta aos metadados públicos do CNJ em todas as telas autorizadas. Definir limite após medir volume e gargalo, com resposta estável e sem retentativas em massa. Se houver consumidor externo, inventariá-lo antes de restringir acesso e separar seu contrato, sem ampliar o acesso interno ou repassar a chave.

Validar no ambiente sintético recusas e consultas permitidas, visibilidade após mudança de perfil, resposta de limite e recuperação; simular a origem em todos os cenários. Não habilitar, desabilitar ou presumir limites em produção nesta etapa.

## Decisão confirmada em 26/11/2026

Jorge confirmou que a consulta pertence ao sistema interno e é acessível a qualquer usuário interno autenticado. A rota de pesquisa usa a mesma fronteira de perfis do dashboard interno, inclusive perfis internos legados e contas mistas, com POST de origem confiável e sem CORS amplo. Não muda a API oficial do CNJ, as permissões por registro para gravação nem a política de consultas a processos sob sigilo. Não foi configurada quota em produção: medir volume, erros e capacidade antes de definir um valor.
