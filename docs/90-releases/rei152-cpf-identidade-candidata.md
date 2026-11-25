# REI-152: candidata de identificacao de CPF

A busca verifica as duas tabelas configuradas, normalizando valores antigos com a mesma regra PHP de entrada. Rejeita mais de um dono, documentos orfaos e divergencia no dono em vez de escolher um resultado arbitrario. A comparacao nao altera dados legados. Ao inserir documento, o repositório usa sua tabela documental configurada e a tabela de pessoas informada; esta ultima por padrao e `partes`.

## Limites para integracao

- As duas varreduras custam O(numero de pessoas + numero de documentos) por busca e nao usam indices para normalizacao; PDO MySQL pode armazenar o resultado em memoria. Nao ha orcamento de latencia nem medicao representativa. Nao ativar em producao sem avaliacao de volume e plano operacional.
- Os caminhos de criacao podem executar a busca mais de uma vez; compartilhar resultado sem comprovar protecao ate o commit criaria risco de estado desatualizado. A validacao repetida do mesmo dono no mesmo insert foi removida.
- GET_LOCK e cooperativo e ligado ao servidor: nao protege escritores SQL antigos/externos nem instancias independentes. Nao substitui restricao no banco. A correcao depende de todos os escritores relevantes e da ordem de locks.
- Em InnoDB REPEATABLE READ, transacao externa pode ter snapshot anterior a obtencao do lock. Nao foi demonstrado que todos os chamadores possuem leitura fresca; exige revisao de contratos e teste com concorrencia real em MySQL. Nao afirmar garantia global.
- Engine, indices, collation, versao e esquema efetivos sao desconhecidos. Nenhuma migracao ou operacao em dados reais foi feita. Unique em cada tabela isolada nao garante unicidade cruzada.
- O gate transacional existente inspeciona tabelas padrao alem das adicionais; a compatibilidade das tabelas alternativas foi exercitada apenas com SQLite, que nao valida esse gate em MySQL.

A candidata portada da main apos PR #521 verifica que o lock do CPF especifico foi adquirido pelo helper na mesma conexao antes de usar lookup para mutacao. Consultas publicas de leitura continuam independentes do lock. O teste do contrato usa PDO SQLite com funcoes de lock simuladas: nao demonstra a semantica nem a concorrencia do MySQL. A prova de snapshot depende das transacoes serem abertas e observadas pelo PDO e de todos os escritores cooperarem.

O setter recusa CPF novo invalido e dados divergentes ou ambiguos. A recusa preserva dados para reconciliacao explicita e auditada; esta candidata nao implementa nem autoriza escolher qual registro legado e correto.
