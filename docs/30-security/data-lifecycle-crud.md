# Fluxos de Gerenciamento de Dados (CRUD) e Ciclo de Vida

## 1. Visão Geral e Integridade

O gerenciamento de dados no **WebApp | 1vCrimeIlheus** segue um protocolo rigoroso de integridade e segurança, garantindo que todas as operações de criação, leitura, atualização e exclusão (CRUD) sejam auditáveis e reversíveis quando necessário. A arquitetura do sistema prioriza a proteção contra perda acidental de informações, implementando camadas de validação no *backend* antes que qualquer alteração seja persistida no banco de dados MySQL.

Além das validações técnicas, o ciclo de vida dos dados obedece a regras de negócio hierárquicas. Enquanto a inserção de dados é uma tarefa operacional distribuída entre diversos perfis, a remoção e a alteração crítica são privilégios restritos. O sistema adota uma política de retenção de dados de longo prazo, onde a exclusão imediata é apenas lógica (*Soft Delete*), preservando o histórico para fins de auditoria e correição por um período determinado antes do expurgo definitivo.

## 2. Fluxo de Cadastro (Create)

O processo de inserção de novos registros é a porta de entrada dos dados no sistema e, portanto, o ponto onde as regras de validação são mais estritas. Antes de qualquer gravação, o *backend* verifica a integridade dos campos obrigatórios, a complexidade das senhas (no caso de usuários) e a unicidade de chaves como CPF ou matrícula. O sistema utiliza identificadores com prefixos semânticos (ex: `USR-` para usuários, `PROC-` para processos) para facilitar a rastreabilidade e a leitura de logs.

Para garantir a consistência, dados sensíveis como o CPF são tratados de forma híbrida: são armazenados com a máscara de formatação (ex: `000.000.000-00`) em colunas `VARCHAR` para padronização visual, mas todas as validações de duplicidade e verificação de dígitos verificadores são realizadas sobre a string numérica pura. O retorno da operação para o *frontend* é sempre estruturado em JSON, informando o sucesso da criação ou detalhando erros de validação específicos.

### Exemplos Práticos de Cadastro

Abaixo estão listados cenários comuns de validação e resposta que devem ser implementados nas APIs de criação. Estes exemplos ilustram o comportamento esperado do sistema diante de dados válidos e inválidos:

- **Validação de CPF:** O sistema deve remover caracteres não numéricos e aplicar o algoritmo de Módulo 11. Se inválido, a API deve retornar erro `400 Bad Request`.
- **Geração de ID:** Ao criar um novo usuário, o sistema deve gerar automaticamente um ID composto, como `USR-2024-X9Y2`, garantindo unicidade e leitura humana.
- **Feedback de Sucesso:** A resposta padrão deve ser um JSON contendo `{ "status": "success", "id": "USR-...", "message": "Registro criado com sucesso." }`.

## 3. Fluxo de Consulta e Visualização (Read)

A recuperação de informações na Área Restrita é regida pelo princípio fundamental do **Isolamento por Setor**. Diferente de sistemas monolíticos onde o administrador possui visão global irrestrita, neste projeto a consulta padrão é sempre filtrada pelo setor de alocação do usuário logado. Isso significa que um servidor lotado na "Secretaria Criminal" não terá acesso, via interface ou API, aos registros pertencentes ao "Gabinete do Juiz", a menos que possua uma permissão transversal explícita.

Além do filtro de escopo, a visualização de dados aplica máscaras de proteção em tempo real para garantir a privacidade. Campos sensíveis são renderizados com formatação amigável, e listas de resultados são paginadas para evitar sobrecarga do servidor. A consulta de processos, especificamente, é restrita ao ambiente interno, não havendo endpoints públicos que exponham detalhes processuais ou dados das partes para a internet aberta.

### Exemplos de Filtros de Consulta

Os filtros abaixo demonstram como as regras de negócio devem ser traduzidas para a lógica de consulta no banco de dados, garantindo a segurança da informação:

- **Filtro de Escopo (SQL):** A query base deve sempre incluir `WHERE setor_id = :usuario_setor_id AND deleted_at IS NULL`.
- **Filtro de Status:** A interface deve permitir que usuários filtrem listagens por estados como "Ativos", "Pendentes" ou "Arquivados".
- **Busca Textual:** A busca por nome de parte ou número de processo deve ser insensível a maiúsculas/minúsculas e ignorar pontuação.

## 4. Fluxo de Edição e Atualização (Update)

A atualização de registros é uma operação sensível que exige rastreabilidade total para fins de auditoria. O sistema diferencia claramente entre a edição de dados cadastrais (correção de nome, atualização de endereço) e a edição de dados estruturais (mudança de setor, alteração de perfil). Enquanto Servidores podem realizar edições operacionais no dia a dia, apenas as Chefias possuem permissão para alterar vínculos estruturais, como a realocação de um usuário.

Todas as operações de edição geram obrigatoriamente um registro de auditoria (*Audit Log*), salvando o estado anterior do dado, o estado novo, o ID do usuário responsável e o *timestamp* da alteração. Campos imutáveis, como a Chave Primária e a Data de Criação, são protegidos contra edição em nível de API, rejeitando qualquer requisição que tente modificá-los.

### Exemplos de Permissões de Edição

As regras de permissão variam conforme o perfil do usuário logado e o tipo de dado que está sendo alterado:

- **Servidor:** Possui permissão para alterar campos não estruturais, como "Endereço" e "Telefone" de uma parte em um processo.
- **Chefia:** Possui permissão elevada para alterar campos estruturais, como o "Setor de Lotação" de um servidor (Realocação).
- **Bloqueio de Imutáveis:** Qualquer tentativa de alterar o campo `created_at` deve retornar imediatamente um erro `403 Forbidden`.

## 5. Fluxo de Exclusão e Retenção (Delete)

A estratégia de exclusão do sistema foi desenhada para evitar a perda irreversível de dados por erro humano ou malicioso. Quando um usuário com permissão de Chefia solicita a remoção de um registro, o sistema executa um **Soft Delete**. Isso significa que o dado não é apagado fisicamente do disco; em vez disso, uma coluna de controle (ex: `deleted_at` ou `status`) é atualizada, tornando o registro invisível para as consultas padrões do sistema.

A exclusão definitiva (**Hard Delete**) ocorre através de um processo automatizado e agendado (Cron Job), garantindo a limpeza da base de dados a longo prazo. O sistema varre periodicamente a base de dados em busca de registros marcados com *Soft Delete* há mais de **1 ano (365 dias)**. Somente após esse período de quarentena é que o comando `DELETE` SQL é executado, liberando espaço em disco e cumprindo as políticas de saneamento.

### Exemplos de Ciclo de Exclusão

O ciclo de vida de um dado excluído segue etapas bem definidas, desde a ação do usuário até a remoção física do banco:

- **Ação Manual (Dia 0):** A Chefia confirma a exclusão. O sistema executa `UPDATE usuarios SET deleted_at = NOW() WHERE id = 'USR-123'`.
- **Tentativa de Acesso:** Se houver tentativa de acesso direto via URL, o sistema retorna `404 Not Found` (o filtro padrão exclui `deleted_at IS NOT NULL`).
- **Expurgo Automático (Dia 365+):** O script de manutenção executa `DELETE FROM usuarios WHERE deleted_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)`.
