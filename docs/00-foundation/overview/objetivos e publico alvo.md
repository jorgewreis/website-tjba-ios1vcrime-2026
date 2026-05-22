# SGC - IOS1VCRIME  

## DOCUMENTAÇÃO INICIAL  

### OBJETIVOS E PÚBLICO-ALVO

**Projeto:** Dev WebApp – Sistema de Gestão do Cartório da Primeira Vara Crime de Ilhéus  
**Documento desenvolvido por:** Reis Developer  
**Direitos:** Todos os direitos reservados a Jorge Wanderley Reis de Menezes  

---

## 1. Contextualização

O presente documento tem por finalidade estabelecer, de forma estruturada, detalhada e tecnicamente fundamentada, os objetivos gerais e específicos do webapp da Primeira Vara Crime do Fórum de Ilhéus, bem como definir com precisão o público-alvo interno e externo atendido pelo sistema.

Trata-se de um documento conceitual e estratégico, cujo conteúdo orienta diretamente todas as decisões subsequentes do projeto.

Este material deve ser compreendido como documento-base, servindo de referência obrigatória para a elaboração do gerenciamento de escopo, definição de requisitos funcionais e não funcionais, modelagem de perfis e permissões, arquitetura do sistema, políticas de segurança da informação e critérios de qualidade. Qualquer funcionalidade futura, ajuste de escopo ou evolução do sistema deverá estar alinhada aos princípios, objetivos e limites aqui descritos.

Como se trata de um sistema inserido no contexto do Poder Judiciário, o documento adota linguagem híbrida — técnica e institucional — e observa rigorosamente os princípios da Administração Pública, a Lei Geral de Proteção de Dados (LGPD) e as normas ISO 25010, ISO 12207, ISO 9001 e MPS.BR.

---

## 2. Objetivo Geral do WebApp

O objetivo geral do webapp é desenvolver e implantar uma plataforma institucional e operacional integrada, destinada a centralizar tanto a divulgação de informações públicas quanto o gerenciamento das rotinas administrativas e judiciais internas da Primeira Vara Crime do Fórum de Ilhéus.

A proposta do sistema não se limita à informatização de tarefas, mas à reorganização lógica e segura dos fluxos de trabalho já existentes.

O webapp busca atuar como um ambiente único e oficial, eliminando a fragmentação de informações, a dependência excessiva de controles manuais e o uso de ferramentas paralelas não integradas. Ao centralizar dados, fluxos e registros, o sistema contribui diretamente para a eficiência administrativa, para a segurança da informação e para a melhoria da prestação jurisdicional.

De forma prática, o objetivo geral se materializa em ações como:

- disponibilizar informações públicas de forma clara, acessível e atualizada;  
- oferecer aos servidores um ambiente único para execução e acompanhamento de suas atividades;  
- permitir à chefia uma visão consolidada e gerencial das rotinas da Vara.  

---

## 3. Objetivos Específicos

### 3.1 Objetivos Específicos Institucionais

No aspecto institucional, o webapp tem como objetivo consolidar um canal digital oficial exclusivo da Primeira Vara Crime, garantindo que todas as informações públicas divulgadas tenham origem única, legitimidade institucional e padronização visual e textual.

Isso reduz ruídos de comunicação, divergências de informação e insegurança para o cidadão.

Além disso, o sistema fortalece o princípio da publicidade administrativa, permitindo que atos processuais públicos sejam divulgados de forma organizada, sem prejuízo ao sigilo legal. O controle centralizado evita tanto a exposição indevida de dados quanto a omissão de informações que deveriam ser públicas.

Exemplos práticos desses objetivos incluem:

- exibição padronizada das audiências públicas do dia;  
- disponibilização temporária de atas públicas em formato digital;  
- apresentação clara de informações institucionais e canais oficiais de contato.  

---

### 3.2 Objetivos Específicos Operacionais

Sob a perspectiva operacional, o webapp tem como objetivo centralizar e padronizar os fluxos internos de trabalho, reduzindo retrabalho, inconsistências e dependência de controles manuais dispersos.

A ausência de um sistema único tende a gerar duplicidade de informações, falhas de comunicação e dificuldade de rastreamento das atividades executadas.

O sistema busca organizar de forma lógica e segura atividades como cadastro de audiências, controle de atas, tarefas administrativas, ofícios, cartas, editais e guias. Ao estruturar esses fluxos em módulos integrados, o webapp permite maior previsibilidade, controle e eficiência operacional.

Como reflexo prático desses objetivos, destacam-se:

- acompanhamento do status das audiências em tempo real;  
- controle claro de responsabilidades por tarefa ou fluxo;  
- geração de indicadores de produtividade por setor ou perfil funcional.  

---

### 3.3 Objetivos Específicos Tecnológicos e de Qualidade

Do ponto de vista tecnológico, o webapp tem como objetivo garantir segurança, desempenho, confiabilidade e sustentabilidade técnica.

O sistema deve ser robusto o suficiente para suportar o uso diário contínuo, sem comprometer a integridade dos dados ou a experiência do usuário.

A adoção de boas práticas de desenvolvimento e das normas de qualidade assegura que o sistema seja manutenível, auditável e evolutivo. A arquitetura modular facilita ajustes futuros, correções e ampliações, sem necessidade de reestruturações profundas.

Entre os objetivos tecnológicos, destacam-se:

- autenticação segura e controle de acesso por perfil;  
- proteção de dados pessoais conforme a LGPD;  
- desempenho adequado em diferentes dispositivos (desktop, tablet e mobile).  

---

## 4. Público-Alvo do WebApp

O webapp foi concebido para atender dois grandes grupos de usuários, com necessidades, responsabilidades e níveis de acesso distintos: o público externo e o público interno.

Essa distinção não é apenas conceitual, mas estrutural, influenciando diretamente a arquitetura do sistema e as regras de segurança.

Cada grupo acessa o sistema com finalidades específicas e bem delimitadas, desde a camada de interface até as regras de negócio e permissões, garantindo uso adequado e seguro das informações disponibilizadas.

---

## 5. Público-Alvo Externo

O público externo é composto por usuários sem vínculo funcional direto com a Primeira Vara Crime, que acessam exclusivamente a área pública do sistema.

Esse acesso é controlado e limitado às informações cuja publicidade é autorizada por lei.

### 5.1 Perfis do Público Externo

O público externo abrange diferentes perfis, tais como:

- cidadãos em geral;  
- advogados;  
- defensores públicos;  
- promotores de justiça;  
- estudantes de Direito e pesquisadores.  

O sistema poderá permitir, de forma controlada, que outras Varas consultem dados específicos, como estatísticas agregadas ou consulta de partes, sempre sem acesso a informações sigilosas ou sensíveis.

---

### 5.2 Justificativa de Atendimento ao Público Externo

O atendimento ao público externo fundamenta-se nos princípios da publicidade, transparência e eficiência, assegurando que a sociedade tenha acesso facilitado às informações públicas da atividade jurisdicional.

Essa transparência contribui para a legitimidade institucional e para a confiança da população no Poder Judiciário.

Exemplos práticos desse atendimento incluem:

- consulta às audiências públicas do dia;  
- acompanhamento do status das audiências em painéis públicos;  
- acesso temporário a documentos públicos, quando permitido.  

---

## 6. Público-Alvo Interno

O público interno é formado por usuários vinculados funcionalmente à Primeira Vara Crime, com acesso mediante autenticação e permissões específicas.

Esses usuários utilizam o sistema como ferramenta de trabalho diário.

### 6.1 Perfis Internos e Responsabilidades Funcionais

Os perfis internos refletem a estrutura funcional da Vara, garantindo que cada usuário tenha acesso apenas às funcionalidades compatíveis com suas atribuições.

Os principais perfis internos incluem:

- chefia e administração do sistema;  
- servidores do cartório;  
- servidores do gabinete;  
- estagiários do cartório;  
- estagiários do gabinete.  

Cada perfil possui responsabilidades bem definidas, como cadastro, movimentação, validação ou acompanhamento de informações, sempre com níveis de acesso proporcionais às suas funções.

---

## 7. Delimitação de Acesso e Segurança

A segurança do sistema baseia-se na separação rígida entre área pública e área restrita, aliada a controles de acesso por perfil e auditoria constante.

Usuários externos não possuem acesso a funcionalidades internas, e informações sigilosas não são permitidas a usuários externos em nenhuma hipótese.

No ambiente interno, o acesso às informações também é controlado de forma proporcional, evitando que usuários visualizem ou alterem dados fora de sua responsabilidade funcional.

Todas as ações relevantes são registradas em logs, garantindo rastreabilidade e possibilidade de auditoria.

Exemplos práticos dessa delimitação incluem:

- ocultação automática de processos sigilosos;  
- restrição de exclusões ou alterações a perfis específicos;  
- registro de acessos e operações sensíveis.  

---

## 8. Papel do Documento no Projeto

Este documento constitui fundamento conceitual e normativo do **Projeto 2026.01**, sendo referência obrigatória para todos os demais documentos e decisões técnicas.

A partir deste documento derivam diretamente:

- Documento de Escopo;  
- Requisitos Funcionais e Não Funcionais;  
- Matriz de Perfis e Permissões;  
- Políticas de Segurança e Auditoria.  

Qualquer evolução futura do webapp deverá respeitar integralmente os princípios e diretrizes aqui estabelecidos.

---

**Fim do documento.**
