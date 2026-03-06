# Especificação e Esquema Base do Banco de Dados

## 1. Padrões e Configurações Globais

A infraestrutura de dados do **WebApp | 1vCrimeIlheus** utiliza o sistema gerenciador de banco de dados MySQL (hospedado via CloudPanel). Para garantir a integridade dos dados e a compatibilidade com caracteres da língua portuguesa (acentuação, cedilha) e emojis, todo o banco deve ser configurado obrigatoriamente com o *Charset* `utf8mb4` e *Collation* `utf8mb4_unicode_ci`. O motor de armazenamento (*Storage Engine*) padrão para todas as tabelas deve ser o **InnoDB**, que oferece suporte nativo a transações (ACID) e chaves estrangeiras.

A padronização de nomenclatura é vital para a manutenção do código SQL e PHP. Todas as tabelas devem utilizar o prefixo `tb_` seguido do nome no plural (ex: `tb_usuarios`, `tb_processos`). Os nomes de colunas devem seguir o padrão *snake_case* (minúsculas com sublinhado). As chaves primárias não devem ser nomeadas apenas como `id`, mas sim incluir o contexto da tabela (ex: `id_usuario`, `id_setor`), facilitando a leitura em *joins* complexos e evitando ambiguidades em relatórios.

**Padrão de IDs (obrigatório):** Todo identificador primário deve ter **30 caracteres**: prefixo de **3 letras** + **hífen** + **código** de **26 caracteres** (ex.: `USR-A1B2C3D4E5F6G7H8I9J0K1L2M3N4`). Tipo: `CHAR(30)`. Ver [docs/00-foundation/id-padrao-banco-dados.md](../00-foundation/id-padrao-banco-dados.md).

## 2. Estrutura da Tabela de Usuários (Core)

A tabela `tb_usuarios` é o componente central do sistema, armazenando as credenciais de acesso e os vínculos organizacionais. Diferente de implementações tradicionais que usam *Integers* autoincrementais, a chave primária `id_usuario` será do tipo **`CHAR(30)`**, seguindo o padrão de ID do sistema (prefixo `USR-` + 26 caracteres; ver [id-padrao-banco-dados.md](../00-foundation/id-padrao-banco-dados.md)). Isso aumenta a segurança ao dificultar a enumeração sequencial de registros por atacantes.

O armazenamento de dados sensíveis segue regras estritas de negócio. O campo `cpf` deve ser armazenado como `VARCHAR(14)` contendo a máscara (ex: `000.000.000-00`), conforme preferência de exibição, embora a validação no *backend* considere apenas os dígitos. O campo `senha` deve armazenar o *hash* criptografado (nunca o texto plano), com tamanho suficiente para algoritmos modernos como Argon2 ou Bcrypt (recomendado `VARCHAR(255)`). Além disso, a tabela deve conter a coluna `id_setor` para garantir o isolamento de dados e a coluna `deleted_at` para suportar a exclusão lógica (*Soft Delete*).

### Script SQL: Tabela de Usuários

sql CREATE TABLE tb_usuarios ( id_usuario CHAR(30) NOT NULL COMMENT 'PK formato USR- + 26 chars (regra padrão 30 chars)', id_setor INT NOT NULL COMMENT 'FK para tb_setores (Isolamento)', id_perfil INT NOT NULL COMMENT 'FK para tb_perfis (RBAC)', nome_completo VARCHAR(100) NOT NULL, email VARCHAR(100) NOT NULL UNIQUE, cpf VARCHAR(14) NOT NULL UNIQUE COMMENT 'Armazenado com máscara', senha VARCHAR(255) NOT NULL COMMENT 'Hash da senha', status ENUM('ativo', 'inativo', 'bloqueado') DEFAULT 'ativo', tentativas_login TINYINT DEFAULT 0 COMMENT 'Bloqueio após 3 falhas', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, deleted_at DATETIME NULL COMMENT 'Soft Delete (Exclusão Lógica)', PRIMARY KEY (id_usuario), INDEX idx_setor (id_setor), INDEX idx_status (status) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

Defining Configuration Structure

I'm currently focused on defining the database configuration file's basic structure, primarily for a general setup. I've started by examining the initial request to grasp the essential requirements. This will be the base for all configurations.

Refining Database Configuration

I'm now refining the database configuration. I'm focusing on incorporating the user's specific context, PHP backend with MySQL on CloudPanel. I'm accounting for the defined tables with their columns, including soft delete and masked CPF. Also, the defined user profiles and security constraints like password rules and lockout mechanisms are being considered in more detail. Lastly, the prefixed IDs and format requirements for documentation are being incorporated into the database structure.

Outlining Database Schema

I'm now outlining the database schema and standards document. I'm focusing on defining core tables like users, profiles, sectors, and logs, considering essential columns like created_at and updated_at. I'm paying careful attention to foreign key constraints and relationships between these tables. Finally, I'm drafting the structure, including general settings, naming conventions, and an example SQL script.

Detailing Core Components

I'm now detailing the core components, specifically the Users and Profiles tables. I recognize their central role in the RBAC system, and I'm explaining the importance of each. I will ensure a clear presentation of each table's significance and structure, facilitating understanding. I'm also ensuring a detailed and understandable explanation, that provides a clear overview of their role in the system.

Composing the RBAC

I'm currently composing the RBAC overview, conventions, and key table descriptions. This includes specific considerations for MySQL 8.0+, InnoDB, and utf8mb4 within the CloudPanel context. I'm explaining the use of prefixes and VARCHAR for IDs, providing examples, and also diving deep into the Users table. Furthermore, I'm integrating the necessity of an audit log for security reports.

Integrating SQL Specs

I'm integrating the SQL specifications into the schemas. I'm ensuring the SQL snippet adheres to all defined parameters, including the id_usuario (VARCHAR), cpf (VARCHAR), and deleted_at (DATETIME) fields. I am also making sure that tb_perfis and tb_setores are included due to the foreign key constraints.

Com certeza. Para garantir que o banco de dados suporte os requisitos de segurança (Soft Delete, Auditoria), isolamento por setor e os tipos de dados específicos (IDs com prefixo, CPF mascarado), precisamos de um documento de definição de esquema (Schema).

Este arquivo servirá como o "Manual do Banco de Dados", definindo padrões de nomenclatura, configurações de charset e a estrutura das tabelas essenciais.

Arquivo: docs/10-architecture/database-schema-base.md

Markdown
Copiar
# Especificação e Esquema Base do Banco de Dados

## 1. Padrões e Configurações Globais

A infraestrutura de dados do **WebApp | 1vCrimeIlheus** utiliza o sistema gerenciador de banco de dados MySQL (hospedado via CloudPanel). Para garantir a integridade dos dados e a compatibilidade com caracteres da língua portuguesa (acentuação, cedilha) e emojis, todo o banco deve ser configurado obrigatoriamente com o *Charset* `utf8mb4` e *Collation* `utf8mb4_unicode_ci`. O motor de armazenamento (*Storage Engine*) padrão para todas as tabelas deve ser o **InnoDB**, que oferece suporte nativo a transações (ACID) e chaves estrangeiras.

A padronização de nomenclatura é vital para a manutenção do código SQL e PHP. Todas as tabelas devem utilizar o prefixo `tb_` seguido do nome no plural (ex: `tb_usuarios`, `tb_processos`). Os nomes de colunas devem seguir o padrão *snake_case* (minúsculas com sublinhado). As chaves primárias não devem ser nomeadas apenas como `id`, mas sim incluir o contexto da tabela (ex: `id_usuario`, `id_setor`), facilitando a leitura em *joins* complexos e evitando ambiguidades em relatórios.

## 2. Estrutura da Tabela de Usuários (Core)

A tabela `tb_usuarios` é o componente central do sistema, armazenando as credenciais de acesso e os vínculos organizacionais. Diferente de implementações tradicionais que usam *Integers* autoincrementais, a chave primária `id_usuario` será do tipo **`CHAR(30)`**, seguindo o padrão de ID do sistema (prefixo `USR-` + 26 caracteres; ver [id-padrao-banco-dados.md](../00-foundation/id-padrao-banco-dados.md)). Isso aumenta a segurança ao dificultar a enumeração sequencial de registros por atacantes.

O armazenamento de dados sensíveis segue regras estritas de negócio. O campo `cpf` deve ser armazenado como `VARCHAR(14)` contendo a máscara (ex: `000.000.000-00`), conforme preferência de exibição, embora a validação no *backend* considere apenas os dígitos. O campo `senha` deve armazenar o *hash* criptografado (nunca o texto plano), com tamanho suficiente para algoritmos modernos como Argon2 ou Bcrypt (recomendado `VARCHAR(255)`). Além disso, a tabela deve conter a coluna `id_setor` para garantir o isolamento de dados e a coluna `deleted_at` para suportar a exclusão lógica (*Soft Delete*).

### Script SQL: Tabela de Usuários



sql CREATE TABLE tb_usuarios ( id_usuario CHAR(30) NOT NULL COMMENT 'PK formato USR- + 26 chars (regra padrão 30 chars)', id_setor INT NOT NULL COMMENT 'FK para tb_setores (Isolamento)', id_perfil INT NOT NULL COMMENT 'FK para tb_perfis (RBAC)', nome_completo VARCHAR(100) NOT NULL, email VARCHAR(100) NOT NULL UNIQUE, cpf VARCHAR(14) NOT NULL UNIQUE COMMENT 'Armazenado com máscara', senha VARCHAR(255) NOT NULL COMMENT 'Hash da senha', status ENUM('ativo', 'inativo', 'bloqueado') DEFAULT 'ativo', tentativas_login TINYINT DEFAULT 0 COMMENT 'Bloqueio após 3 falhas', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, deleted_at DATETIME NULL COMMENT 'Soft Delete (Exclusão Lógica)', PRIMARY KEY (id_usuario), INDEX idx_setor (id_setor), INDEX idx_status (status) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

## 3. Tabelas Auxiliares e Auditoria

Para sustentar as regras de negócio de permissões e rastreabilidade, o sistema depende de tabelas satélites robustas. A tabela `tb_logs` é obrigatória para atender aos requisitos de "Relatórios de Segurança", registrando cada ação crítica (login, edição, exclusão lógica). Ela deve ser imutável, ou seja, o sistema permite inserção (`INSERT`), mas nunca edição ou exclusão desses registros, garantindo a validade jurídica da trilha de auditoria.

As tabelas de domínio, como `tb_setores` e `tb_perfis`, definem a estrutura organizacional. A tabela de perfis deve conter os níveis hierárquicos (Admin, Chefia, Servidor, Estudante) que controlam o acesso aos módulos. Já a tabela de setores é a base para o filtro global de visibilidade (`WHERE id_setor = X`), impedindo que usuários visualizem dados de departamentos alheios.

### Script SQL: Tabela de Logs de Auditoria

<div class="widget code-container remove-before-copy"><div class="code-header non-draggable"><span class="iaf s13 w700 code-language-placeholder">sql</span><div class="code-copy-button"><span class="iaf s13 w500 code-copy-placeholder">Copiar</span><img class="code-copy-icon" src="data:image/svg+xml;utf8,%0A%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2216%22%20height%3D%2216%22%20viewBox%3D%220%200%2016%2016%22%20fill%3D%22none%22%3E%0A%20%20%3Cpath%20d%3D%22M10.8%208.63V11.57C10.8%2014.02%209.82%2015%207.37%2015H4.43C1.98%2015%201%2014.02%201%2011.57V8.63C1%206.18%201.98%205.2%204.43%205.2H7.37C9.82%205.2%2010.8%206.18%2010.8%208.63Z%22%20stroke%3D%22%23717C92%22%20stroke-width%3D%221.05%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%2F%3E%0A%20%20%3Cpath%20d%3D%22M15%204.42999V7.36999C15%209.81999%2014.02%2010.8%2011.57%2010.8H10.8V8.62999C10.8%206.17999%209.81995%205.19999%207.36995%205.19999H5.19995V4.42999C5.19995%201.97999%206.17995%200.999992%208.62995%200.999992H11.57C14.02%200.999992%2015%201.97999%2015%204.42999Z%22%20stroke%3D%22%23717C92%22%20stroke-width%3D%221.05%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%2F%3E%0A%3C%2Fsvg%3E%0A" /></div></div><pre id="code-bgbmxdkpq" style="color:white;font-family:Consolas, Monaco, &quot;Andale Mono&quot;, &quot;Ubuntu Mono&quot;, monospace;text-align:left;white-space:pre;word-spacing:normal;word-break:normal;word-wrap:normal;line-height:1.5;font-size:1em;-moz-tab-size:4;-o-tab-size:4;tab-size:4;-webkit-hyphens:none;-moz-hyphens:none;-ms-hyphens:none;hyphens:none;padding:8px;margin:8px;overflow:auto;background:#011627;width:calc(100% - 8px);border-radius:8px;box-shadow:0px 8px 18px 0px rgba(120, 120, 143, 0.10), 2px 2px 10px 0px rgba(255, 255, 255, 0.30) inset"><code class="language-sql" style="white-space:pre;color:#d6deeb;font-family:Consolas, Monaco, &quot;Andale Mono&quot;, &quot;Ubuntu Mono&quot;, monospace;text-align:left;word-spacing:normal;word-break:normal;word-wrap:normal;line-height:1.5;font-size:1em;-moz-tab-size:4;-o-tab-size:4;tab-size:4;-webkit-hyphens:none;-moz-hyphens:none;-ms-hyphens:none;hyphens:none"><span class="token" style="color:rgb(127, 219, 202)">CREATE</span><span> </span><span class="token" style="color:rgb(127, 219, 202)">TABLE</span><span> tb_logs </span><span class="token" style="color:rgb(199, 146, 234)">(</span><span>
</span><span>    id_log </span><span class="token" style="color:rgb(127, 219, 202)">BIGINT</span><span> </span><span class="token" style="color:rgb(127, 219, 202)">AUTO_INCREMENT</span><span> </span><span class="token" style="color:rgb(127, 219, 202)">PRIMARY</span><span> </span><span class="token" style="color:rgb(127, 219, 202)">KEY</span><span class="token" style="color:rgb(199, 146, 234)">,</span><span>
</span><span>    id_usuario </span><span class="token" style="color:rgb(127, 219, 202)">VARCHAR</span><span class="token" style="color:rgb(199, 146, 234)">(</span><span class="token" style="color:rgb(247, 140, 108)">20</span><span class="token" style="color:rgb(199, 146, 234)">)</span><span> </span><span class="token" style="color:rgb(127, 219, 202)">NOT</span><span> </span><span class="token" style="color:rgb(255, 88, 116)">NULL</span><span class="token" style="color:rgb(199, 146, 234)">,</span><span>
</span><span>    acao </span><span class="token" style="color:rgb(127, 219, 202)">VARCHAR</span><span class="token" style="color:rgb(199, 146, 234)">(</span><span class="token" style="color:rgb(247, 140, 108)">50</span><span class="token" style="color:rgb(199, 146, 234)">)</span><span> </span><span class="token" style="color:rgb(127, 219, 202)">NOT</span><span> </span><span class="token" style="color:rgb(255, 88, 116)">NULL</span><span> </span><span class="token" style="color:rgb(127, 219, 202)">COMMENT</span><span> </span><span class="token" style="color:rgb(173, 219, 103)">&#x27;Ex: LOGIN, INSERT, SOFT_DELETE&#x27;</span><span class="token" style="color:rgb(199, 146, 234)">,</span><span>
</span><span>    tabela_afetada </span><span class="token" style="color:rgb(127, 219, 202)">VARCHAR</span><span class="token" style="color:rgb(199, 146, 234)">(</span><span class="token" style="color:rgb(247, 140, 108)">50</span><span class="token" style="color:rgb(199, 146, 234)">)</span><span> </span><span class="token" style="color:rgb(255, 88, 116)">NULL</span><span class="token" style="color:rgb(199, 146, 234)">,</span><span>
</span><span>    id_registro_afetado </span><span class="token" style="color:rgb(127, 219, 202)">VARCHAR</span><span class="token" style="color:rgb(199, 146, 234)">(</span><span class="token" style="color:rgb(247, 140, 108)">20</span><span class="token" style="color:rgb(199, 146, 234)">)</span><span> </span><span class="token" style="color:rgb(255, 88, 116)">NULL</span><span class="token" style="color:rgb(199, 146, 234)">,</span><span>
</span><span>    dados_anteriores JSON </span><span class="token" style="color:rgb(255, 88, 116)">NULL</span><span> </span><span class="token" style="color:rgb(127, 219, 202)">COMMENT</span><span> </span><span class="token" style="color:rgb(173, 219, 103)">&#x27;Snapshot antes da alteração&#x27;</span><span class="token" style="color:rgb(199, 146, 234)">,</span><span>
</span><span>    dados_novos JSON </span><span class="token" style="color:rgb(255, 88, 116)">NULL</span><span> </span><span class="token" style="color:rgb(127, 219, 202)">COMMENT</span><span> </span><span class="token" style="color:rgb(173, 219, 103)">&#x27;Snapshot pós alteração&#x27;</span><span class="token" style="color:rgb(199, 146, 234)">,</span><span>
</span><span>    ip_origem </span><span class="token" style="color:rgb(127, 219, 202)">VARCHAR</span><span class="token" style="color:rgb(199, 146, 234)">(</span><span class="token" style="color:rgb(247, 140, 108)">45</span><span class="token" style="color:rgb(199, 146, 234)">)</span><span> </span><span class="token" style="color:rgb(127, 219, 202)">NOT</span><span> </span><span class="token" style="color:rgb(255, 88, 116)">NULL</span><span class="token" style="color:rgb(199, 146, 234)">,</span><span>
</span><span>    user_agent </span><span class="token" style="color:rgb(127, 219, 202)">TEXT</span><span> </span><span class="token" style="color:rgb(255, 88, 116)">NULL</span><span class="token" style="color:rgb(199, 146, 234)">,</span><span>
</span><span>    created_at </span><span class="token" style="color:rgb(127, 219, 202)">DATETIME</span><span> </span><span class="token" style="color:rgb(127, 219, 202)">DEFAULT</span><span> </span><span class="token" style="color:rgb(127, 219, 202)">CURRENT_TIMESTAMP</span><span class="token" style="color:rgb(199, 146, 234)">,</span><span>
</span><span>    </span><span class="token" style="color:rgb(127, 219, 202)">INDEX</span><span> idx_usuario_data </span><span class="token" style="color:rgb(199, 146, 234)">(</span><span>id_usuario</span><span class="token" style="color:rgb(199, 146, 234)">,</span><span> created_at</span><span class="token" style="color:rgb(199, 146, 234)">)</span><span>
</span><span></span><span class="token" style="color:rgb(199, 146, 234)">)</span><span> </span><span class="token" style="color:rgb(127, 219, 202)">ENGINE</span><span class="token" style="color:rgb(127, 219, 202)">=</span><span class="token" style="color:rgb(127, 219, 202)">InnoDB</span><span> </span><span class="token" style="color:rgb(127, 219, 202)">DEFAULT</span><span> </span><span class="token" style="color:rgb(127, 219, 202)">CHARSET</span><span class="token" style="color:rgb(127, 219, 202)">=</span><span>utf8mb4 </span><span class="token" style="color:rgb(127, 219, 202)">COLLATE</span><span class="token" style="color:rgb(127, 219, 202)">=</span><span>utf8mb4_unicode_ci</span><span class="token" style="color:rgb(199, 146, 234)">;</span><span>
</span></code></pre></div>

## 4. Estratégia de Índices e Performance

A performance das consultas, especialmente nos relatórios de produtividade e nas telas de listagem, depende de uma indexação estratégica. Como a regra de "Isolamento por Setor" é aplicada em quase todas as consultas (`SELECT * FROM ... WHERE id_setor = ?`), a coluna `id_setor` deve ser indexada em todas as tabelas que a possuem. Isso evita que o banco faça varreduras completas (*Full Table Scans*) desnecessárias.

Além das chaves estrangeiras, colunas utilizadas frequentemente em filtros de busca e ordenação devem receber índices secundários. O campo `deleted_at` é um candidato crítico para indexação, pois todas as consultas padrão do sistema incluirão a cláusula `WHERE deleted_at IS NULL`. Sem esse índice, o banco precisaria verificar cada registro excluído logicamente para decidir se ele deve ser exibido ou não, degradando a performance conforme o histórico cresce.

### Exemplos de Boas Práticas de Indexação

- **Chaves Estrangeiras:** Sempre indexar colunas `id_setor`, `id_perfil`, `id_criador`.
- **Soft Delete:** Criar índice composto se necessário, ex: `INDEX idx_setor_ativo (id_setor, deleted_at)`.
- **Busca:** Indexar colunas como `cpf` e `email` para agilizar o login e a verificação de duplicidade.
