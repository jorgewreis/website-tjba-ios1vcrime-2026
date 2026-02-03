# Especificação Funcional: Área Restrita

## 1. Visão Geral do Módulo

A Área Restrita constitui o núcleo administrativo e operacional do sistema **WebApp | 1vCrimeIlheus**, projetada exclusivamente para o gerenciamento interno de dados, usuários e processos. Este ambiente é isolado do acesso público e estudantil, garantindo que apenas perfis autorizados (Chefias, Servidores, Estagiários e Consultores) possam interagir com as informações sensíveis da organização. A segurança é reforçada por uma arquitetura que impede o acesso direto via URL, exigindo autenticação prévia para qualquer interação.

A arquitetura de navegação foi concebida para operar como uma *Single Page Application* (SPA) simulada. A interface principal é carregada através do arquivo `admin/home.php`, e todo o conteúdo interno é renderizado dentro de um **Iframe** central. Isso permite que o menu lateral e o cabeçalho permaneçam estáticos durante a navegação, otimizando a experiência do usuário e reduzindo o consumo de dados. As URLs internas são mascaradas através de arquivos públicos que incluem os scripts administrativos, ocultando a estrutura real de diretórios do servidor.

## 2. Controle de Acesso e Autenticação

O acesso ao módulo é protegido por um mecanismo de autenticação localizado em `index.php`, que atua como o único ponto de entrada para a área administrativa. Diferente de sistemas tradicionais que utilizam sessões de servidor PHP (cookies `PHPSESSID`), este projeto adota uma abordagem *stateless* no *backend*, delegando o armazenamento da sessão ao navegador do cliente. Isso reduz a carga no servidor e elimina problemas comuns de expiração de sessão por inatividade do lado do servidor.

Os dados de autenticação, incluindo o token JWT (JSON Web Token) e as informações do usuário, são armazenados exclusivamente no **sessionStorage** do navegador. Esta escolha de design é estratégica para a segurança em ambientes compartilhados: ao fechar a aba ou o navegador, os dados são automaticamente limpos, prevenindo acessos indevidos subsequentes. O token JWT possui uma validade configurada de **6 horas**, garantindo que o usuário precise se reautenticar periodicamente para renovar suas credenciais de acesso.

### 2.1. Políticas de Senha e Bloqueio

Para garantir a integridade das credenciais e dificultar ataques de força bruta, o sistema impõe regras estritas na definição de senhas. É exigido um mínimo de 8 caracteres, que devem incluir obrigatoriamente uma combinação de letras maiúsculas, minúsculas, números e caracteres especiais. Além disso, o sistema não permite a reutilização da senha anterior durante o processo de redefinição.

O mecanismo de defesa ativa monitora as tentativas de login e bloqueia a conta automaticamente após **3 tentativas falhas** consecutivas. Uma vez bloqueada, a conta não pode ser desbloqueada pelo próprio usuário via e-mail; é necessária a intervenção manual de um administrador para restaurar o acesso. Isso adiciona uma camada extra de verificação humana em casos de suspeita de comprometimento de conta.

## 3. Matriz de Perfis e Permissões

A Área Restrita implementa um controle de acesso baseado em funções (RBAC - *Role-Based Access Control*), onde cada perfil possui um escopo de atuação delimitado. O sistema foi desenhado para que as permissões sejam granulares, evitando que usuários com funções operacionais tenham acesso a comandos destrutivos ou configurações globais.

Uma regra transversal fundamental é o **Isolamento por Setor**. Nenhum usuário, independentemente do nível hierárquico (exceto auditoria global, se aplicável), possui permissão para visualizar ou manipular dados de setores aos quais não esteja explicitamente vinculado. Isso garante que um servidor do departamento "A" não tenha visibilidade sobre os processos do departamento "B", mantendo a confidencialidade e a organização dos dados.

### 3.1. Chefias e Administradores

Este perfil detém o nível mais elevado de privilégios dentro do seu respectivo setor. As Chefias possuem autonomia total para gerenciar o ciclo de vida dos dados, podendo **Incluir, Editar e Excluir** registros conforme a necessidade. Além disso, possuem acesso exclusivo aos logs de auditoria e relatórios de segurança.

No âmbito da gestão de pessoas, as Chefias são as únicas autorizadas a realizar a movimentação de pessoal. Elas podem realizar a **Alocação** inicial de um usuário no setor e, crucialmente, a **Realocação** de servidores para outros departamentos ou funções. Também cabe a este perfil a responsabilidade de desbloquear contas de usuários que excederam o limite de tentativas de login.

### 3.2. Servidores

O perfil de Servidor é focado na operação diária e na manutenção dos registros do sistema. Eles possuem permissão para **Incluir e Editar** dados dentro do seu setor de atuação, garantindo a fluidez dos processos administrativos. No entanto, suas permissões de exclusão são restritas ou inexistentes, dependendo da configuração específica da tabela.

Em relação à gestão de usuários, os Servidores possuem uma permissão parcial. Eles podem realizar a **Alocação** inicial de novos usuários (como o cadastro de um cidadão ou estagiário), mas não possuem privilégios para alterar uma alocação existente (Realocação). Isso impede que um servidor mova um colega ou a si mesmo para outro setor sem a aprovação de uma chefia.

### 3.3. Estagiários e Consultores

Estes perfis possuem acesso restrito e focado em tarefas específicas de menor criticidade. Os **Estagiários** podem atuar na edição de processos internos sob supervisão, mas possuem limitações severas de navegação, com acesso bloqueado a links sensíveis, configurações do sistema e relatórios gerenciais.

Os **Consultores Externos**, por sua vez, possuem um perfil voltado majoritariamente para consulta e análise. Eles podem visualizar dados para fins de auditoria ou pareceres, mas não possuem permissões de escrita (Insert/Update) em dados estruturais, garantindo que a base de dados permaneça inalterada durante suas análises.

## 4. Funcionalidades de Relatórios

O módulo de relatórios foi segmentado para garantir que informações estratégicas e de auditoria sejam acessadas apenas pelos níveis hierárquicos adequados. A geração de relatórios é processada em tempo real, realizando consultas diretas ao banco de dados e respeitando sempre o filtro de setor do usuário logado para garantir a consistência da informação.

A interface de relatórios é separada em duas categorias principais para facilitar a navegação e o controle de acesso. Enquanto os relatórios operacionais focam na produtividade e nos números do dia a dia, os relatórios de segurança focam na rastreabilidade e na conformidade das ações realizadas dentro do sistema.

### 4.1. Relatórios de Uso e Operação

Estes relatórios são destinados ao acompanhamento da produtividade e do volume de dados processados pelo setor. Eles fornecem métricas quantitativas sobre cadastros realizados, atendimentos efetuados e movimentações operacionais. O acesso é permitido para **Chefias, Administradores e Servidores**, servindo como ferramenta de gestão diária. Estagiários não visualizam esta opção.

### 4.2. Relatórios de Segurança e Auditoria

Trata-se de uma funcionalidade crítica que exibe logs de acesso, tentativas de invasão, histórico de alterações de registros e ações administrativas sensíveis. Devido à natureza confidencial destas informações, o acesso é estritamente restrito a **Chefias e Administradores**. Servidores, Estagiários e Consultores não possuem permissão para visualizar estes dados.

## 5. Gestão de Dados e Integridade

A integridade dos dados é mantida através de regras de validação estritas no *backend* e padronização de identificadores. O sistema utiliza prefixos específicos nas chaves primárias (ex: `USR-` para usuários, `PER-` para perfis) antes do código gerado, facilitando a identificação visual e a depuração de registros em logs e relatórios.

O tratamento de dados sensíveis, como o CPF, segue normas específicas de armazenamento e apresentação. O dado é armazenado em colunas do tipo `VARCHAR` já com a máscara aplicada (ex: 000.000.000-00) para padronização visual. No entanto, todas as operações de busca, validação e verificação de duplicidade são processadas removendo-se a máscara e considerando apenas os dígitos numéricos, garantindo a precisão das consultas.
