# Instruções do projeto

Sistema PHP da 1ª Vara Crime de Ilhéus. Comunique-se em português e observe a solicitação atual, as decisões anteriores e as instruções do ambiente. Confira o estado do repositório e preserve alterações alheias.

Para selecionar agentes e skills adequados à tarefa, consulte `.agents/skills/jev-router/SKILL.md`; a invocação e a delegação dependem dos recursos e permissões do ambiente.

## Arquitetura

- `public/`: entradas HTTP; `public/api/`: APIs atuais; `public/assets/`: CSS e JS. `public/home.php` e `public/frame.php` compõem o shell e o roteamento.
- `app/views/internal/`, `app/services/`, `app/repositories/`, `app/helpers/` e `app/models/`: apresentação, regras, persistência e componentes compartilhados.
- `config/`: configuração e segurança; `database/`: esquema e migrações; `.github/workflows/`: integração e deploy. A árvore `api/` da raiz é legada; confira consumidores antes de alterá-la.
- Projeto PHP sem framework com PDO/MySQL. Consulte `composer.json`, `composer.lock`, `package.json` e workflows para versões reais; o CI de sintaxe usa PHP 8.2. Não presuma ferramentas instaladas.

## Acesso, dados e segurança

- Use consultas parametrizadas e transações para operações relacionadas. Não exponha credenciais, tokens, dados sensíveis ou detalhes internos de exceções ao cliente. Escape a saída conforme o contexto; não aplique escape HTML indiscriminadamente aos dados persistidos.
- Para páginas, confira `PageAccessRegistry`, `AccessEvaluator`, perfis efetivos e roteamento em `public/frame.php`. Há perfis compostos e externos; não substitua a política por uma hierarquia genérica de três níveis.
- Cada API deve verificar autenticação, autorização por operação e, quando necessário, por registro; ocultar menu não é controle de acesso. Examine `ApiAccessGuard` e o padrão do módulo, incluindo método e CSRF. Não presuma que endpoints antigos já estejam protegidos.
- Não execute limpezas ou alterações no banco por operações de consulta. Antes de migrações e exclusões, confira integridade, retenção e recuperação. Contas exclusivamente externas são elegíveis à limpeza após 30 dias de inatividade; preserve contas com perfil interno e verifique a implementação atual.
- Não reintroduza módulos independentes de FokusLaw, Tarefas ou Expedições aqui: seu desenvolvimento pertence ao FokusCloud. Preserve subtarefas próprias das rotinas existentes, salvo pedido explícito.

## Interface e integrações

- Reutilize componentes e estilos existentes; consulte `docs/create-edit-ui-contract.md`, `.cursor/rules/` e os arquivos do módulo quando aplicáveis. Preserve exceções de negócio documentadas.
- Para processo, classe e partes, confira os helpers DataJud, as regras do módulo e `docs/00-foundation/datajud-classe-processo-bd-sync.md`. Não presuma sucesso da consulta nem amplie sincronizações fora do escopo.
- Erros de validação devem ser úteis e não repetitivos; nos campos de senha e confirmação, respeite o fluxo existente de validação ao sair do campo.

## Trabalho e entrega

- Pergunte quando faltar decisão que realmente impeça o trabalho; aproveite autorização e contexto existentes. Revisão ou auditoria é somente leitura, salvo solicitação de correção.
- Restrinja o diff ao pedido, examine impactos cruzados e relate limites de verificação. Execute testes quando solicitados ou exigidos pelo fluxo de entrega; informe ferramentas e ambientes indisponíveis.
- O usuário solicitou deploy após alterações neste projeto. Confira mudanças e branch, publique apenas trabalho autorizado e acompanhe o workflow; diferencie push de deploy concluído. Não publique uma auditoria somente de leitura. Pendências de chave SSH e releases atômicas estão em `docs/90-releases/continuacao-auditoria-seguranca.md` e `docs/90-releases/deploy-producao.md`.
