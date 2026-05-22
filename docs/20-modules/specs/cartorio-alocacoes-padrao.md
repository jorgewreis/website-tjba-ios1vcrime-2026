# Especificação: Padrão de Alocações (Cartório)

## 1. Objetivo

Definir um padrão único para alocação de usuários nas telas de cartório (lista, create e edit), cobrindo:

- tipo de alocação;
- texto exibido em tooltip/acessibilidade;
- ordem de exibição dos chips;
- cores por tipo;
- comportamento para tipo inválido;
- regra de permissão e limites.

## 2. Escopo

Aplica-se aos módulos de cartório:

- Guias
- Cartas Expedidas
- Cartas Recebidas
- Editais
- Ofícios (cartório)

Fora de escopo: Sala de Audiências.

## 3. Tipos de alocação

### 3.1 Mapeamento de tipos

| Tipo (DB) | Significado | Origem |
|---|---|---|
| `all` | Responsável pelo cadastro | Autoalocação do usuário que cadastrou a tarefa |
| `rule` | Alocação por regra do cartório | Usuário associado à regra `ALLOCATE-*` do módulo |
| `specific` | Alocação direta | Usuário indicado manualmente por perfil autorizado |

### 3.2 Exceção de módulo

- Editais trabalha com `rule` e `specific` no fluxo padrão.
- O padrão visual/textual continua o mesmo para compatibilidade caso `all` exista em dados legados.

## 4. Texto padrão de chip (title/aria-label)

`title` e `aria-label` devem ser idênticos.

Formato "médio" obrigatório:

`<prefixo>: <Nome> - <micro-frase>`

Separador obrigatório entre nome e micro-frase: ` - ` (hífen simples com espaços).

### 4.1 Prefixos por tipo

| Tipo | Prefixo |
|---|---|
| `all` | `Responsável pelo cadastro:` |
| `rule` | `Alocação por regra do cartório:` |
| `specific` | `Alocação direta:` |

### 4.2 Micro-frases por tipo

| Tipo | Micro-frase |
|---|---|
| `all` | `entrou automaticamente ao criar a tarefa` |
| `rule` | `definido pela regra ativa do módulo` |
| `specific` | `incluído manualmente por alocação direcionada` |

### 4.3 Exemplo

`Alocação direta: Maria Silva - incluído manualmente por alocação direcionada`

## 5. Ordem de exibição de chips

Ordem global por tipo:

1. `all`
2. `rule`
3. `specific`
4. qualquer outro tipo (fallback)

Desempate dentro do mesmo tipo: iniciais em ordem A-Z.

## 6. Cores e classes CSS

Classes CSS padrão:

- `.user-allocate.all` -> neutra
- `.user-allocate.rule` -> warning
- `.user-allocate.specific` -> primária

Objetivo visual:

- `all`: informar titularidade base sem destaque forte;
- `rule`: indicar vínculo sistêmico/alerta contextual;
- `specific`: destacar ação manual de gestão/chefia.

## 7. Permissão e limite

### 7.1 Quem pode alocar manualmente (`specific`)

Regras centralizadas em `AlocacaoTarefasPerfisHelper`:

- Perfis autorizados para alocação direcionada no cadastro/edição: ADMIN, CHEFIA, DIRETORA, MAGISTRADA.

### 7.2 Autoalocação (`all`)

- Perfis de chefia sem autoalocação (regra do helper): CHEFIA, DIRETORA, MAGISTRADA.

### 7.3 Limite

- Limite máximo por tarefa: 5 alocados.

## 8. Tipo inválido

Quando tipo não for `all`, `rule` ou `specific`:

- não renderizar chip na interface;
- registrar log em uma linha JSON contendo:
  - `tipo`
  - `entity_id`
  - `usuario_id`
  - `origem` (módulo/tela curto)

## 9. Implementação de referência

- Centralizar literals em `CartorioAlocacaoTipo`.
- Centralizar rótulos, acessibilidade e ordenação em `CartorioAlocacaoChipHelper`.
- Aplicar helper em listas, partial de edição e `cartorio-oficios-edit.php`.
- No create, montar preview de chip com a mesma estrutura das listas:
  - `user-allocate-wrap`
  - `user-allocate specific`

## 10. Checklist de validação

- `title` e `aria-label` iguais em todos os chips.
- Separador ` - ` aplicado no texto final.
- Ordenação: all -> rule -> specific -> outros; desempate por iniciais.
- Cores corretas por tipo.
- Tipo inválido não aparece na tela e gera log JSON.
- Limite de 5 respeitado.

