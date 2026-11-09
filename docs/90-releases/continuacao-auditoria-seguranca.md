# Continuação da auditoria de segurança

## Contexto

Este documento reúne o estado das correções do sistema da 1ª Vara Crime de
Ilhéus para retomada em outro computador. Referência verificada em 9/11/2026:
branch `main`, commit `876915c356d16fbdc34df64a2b3bac4a59dd3d1a`.

No computador de casa, atualize uma cópia existente sem sobrescrever alterações
locais. Primeiro verifique `git status`; se estiver limpa, use `git fetch origin`
e `git pull --ff-only origin main`. Se houver alterações locais ou divergência,
examine-as antes de atualizar. Para uma cópia nova, clone o repositório:

```bash
git clone https://github.com/jorgewreis/website-tjba-ios1vcrime-2026.git
```

As alterações publicadas em `main` acionam o deploy de produção. Confira o
resultado em GitHub Actions; um push concluído não prova que o deploy passou.

## Resumo das seis implementações

### 1. Autorização das APIs — parcial

Foi criado `app/helpers/ApiAccessGuard.php` e aplicado a 34 rotas nos grupos de
pessoas, consulta de processos, editais e réus presos. Foram reduzidos detalhes
de exceções inesperadas nas rotas trabalhadas. O usuário relatou cadastro
funcionando.

**Falta:** inventariar as demais APIs, distinguir endpoints públicos legítimos
de rotas restritas, revisar autenticação, permissões por operação e por registro
e tratamento de erros. Confirmar também a proteção CSRF em cada escrita e os
fluxos de usuários com perfis diferentes. Não supor que ocultar um link protege
uma API. A revisão de todas as rotas não foi concluída.

### 2. Sessões e login — implementada, validação parcial

A validação das sessões protegidas passou a consultar token, validade e estado
da conta no banco. O limitador usa armazenamento compartilhado; se ele estiver
indisponível, o login é recusado. O usuário confirmou **login funcionando**.

**Falta:** em ambiente isolado, validar revogação de token, conta inativa,
expiração e acesso às APIs com sessão revogada. Confirmar a topologia do proxy
e configurar `TRUSTED_PROXIES` somente com endereços confiáveis, se necessário.
Sem configuração correta, várias pessoas atrás de um proxy podem compartilhar
o limite por IP. Ver `../30-security/login-rate-limit.md`.

### 3. Usuários e retenção — implementada, validação pendente

A listagem e o login não executam limpeza. Há filtro **Sem perfil** para
localizar contas e regularizá-las pela edição. O usuário confirmou o cadastro,
mas a regularização de uma conta sem perfil ainda precisa ser testada.

Regra solicitada: excluir após **30 dias de inatividade** contas exclusivamente
externas com perfil `ESTUDANTE` ou `CONSULTOR`; preservar contas que também
possuam perfil interno. Para quem nunca acessou, conta-se da criação. O script
`scripts/purge-inactive-external-users.php` é agendado diariamente pelo deploy;
a limpeza e exclusão efetivas ainda não foram verificadas. O prazo é verificado
na execução diária, não necessariamente no exato instante em que se completam
30 dias.

**Falta:** verificar o agendamento e seus logs no servidor e homologar com
dados sintéticos em ambiente isolado: estudante, consultor, múltiplos perfis,
usuário ativo e transação com erro. Não apagar registros reais como teste.
Contas com outros perfis e cadastros inconsistentes devem ser protegidos.

### 4. Chave e identidade SSH — parcial, urgente

A chave privada antiga foi retirada da versão atual do Git, mantida localmente
e excluída das próximas sincronizações. Isso **não remove a chave do histórico,
não apaga eventuais cópias no servidor e não a revoga**.

O workflow aceita `DEPLOY_HOST_KEY_SHA256` e confere a identidade SSH quando
esse secret está configurado. Na ausência dele, continua usando o mecanismo
anterior e emite aviso: **a identidade ainda não está fixada**.

**Falta:** obter por canal administrativo confiável a impressão digital do
servidor; configurar o secret; preparar uma nova chave de acesso com o menor
privilégio viável; autorizar a chave nova no servidor; atualizar o secret
`DEPLOY_KEY_B64`; confirmar o acesso e o deploy; depois revogar a chave antiga.
Verificar eventuais cópias e avaliar tratamento do histórico após a rotação.
Não cole senha ou chave privada em chat ou no repositório. A documentação de
apoio está em `../../.github/DEPLOY-SETUP.md`.

### 5. PDF e Dompdf — atualização publicada, teste pendente

O Dompdf foi atualizado de 3.1.5 para 3.1.6. Após relato de que o gerador não
era encontrado, o deploy foi corrigido para permitir leitura das dependências
pelo servidor web e validar o autoload; o deploy concluiu com sucesso.

**Falta:** testar novamente a geração de PDF no navegador e conferir o conteúdo,
acentuação e paginação. O resultado desse teste ainda não foi informado.

### 6. Publicação e recuperação — parcial

O workflow verifica sintaxe PHP e dependências, usa controle de concorrência e
prepara as dependências em diretório separado antes de trocá-las. A instalação
falha preservando as dependências atuais; há tentativa de restauração se a troca
falhar. O código e as migrações ainda são aplicados no ambiente ativo.

**Falta:** com acesso ao servidor, identificar diretório publicado, arquivos
persistentes, permissões e agendamentos; implementar e homologar releases
completas preparadas separadamente, troca controlada e retorno. Verificar backup
restaurável e compatibilidade das migrações: reverter código não reverte banco.
Ver `deploy-producao.md`.

## Ordem recomendada ao retomar

1. Confirmar no navegador a geração de PDF e conferir a lista de usuários sem
   perfil. Registrar resultados e mensagens de erro.
2. Homologar sessão revogada, permissões das APIs e retenção usando dados
   sintéticos em ambiente isolado.
3. Concluir a rotação da chave SSH e a fixação da identidade do servidor.
4. Revisar as APIs restantes e concluir a proteção por operação e objeto.
5. Planejar e homologar releases atômicas e recuperação do banco.

O usuário deseja deploy após alterações. Acompanhar o workflow até o resultado
final e informar claramente se a publicação e a validação foram concluídas.
