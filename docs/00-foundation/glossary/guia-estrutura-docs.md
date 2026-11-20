# Guia de Organização de Documentação (`/docs`) — SGC (IOS1VCRIME)

## 0. Finalidade e regras gerais deste guia

Este guia estabelece o **padrão oficial** de organização de documentação dentro da pasta `docs/` do repositório do SGC (IOS1VCRIME). Ele existe para reduzir ambiguidade, evitar duplicação de fontes e tornar a documentação **executável**: qualquer desenvolvedor (ou você no futuro) deve conseguir localizar rapidamente o que precisa para implementar, testar, operar e auditar o sistema.

A organização proposta assume que o SGC é um sistema com separação rígida entre **Área Pública** e **Área Restrita**, exigindo **rastreabilidade**, **auditoria** e **governança de regras** (parâmetros versionados, histórico e responsável). Portanto, a documentação não é apenas descritiva; ela é parte do controle de qualidade, segurança e conformidade do produto.

O guia separa documentos em dois grandes tipos, seguindo prática comum da comunidade: **(1) documentação viva** (normalmente em Markdown, revisada e atualizada continuamente) e **(2) artefatos “congelados”** (normalmente PDFs) que representam fontes originais, anexos oficiais ou versões publicadas/snapshot. Essa separação evita que haja “duas verdades” competindo dentro do mesmo diretório.

Por padrão, **Markdown é a fonte de trabalho** e PDF é **evidência/fonte externa/publicação**. Exceções são permitidas, mas devem ser explícitas (por exemplo: “este PDF é o documento oficial e o MD é apenas uma transcrição”). Sempre que existir par `PDF + MD` para o mesmo conteúdo, o MD deve declarar a origem do PDF no topo do arquivo, e o PDF deve estar isolado em `sources/` ou `published/`.

---

## 0.1 Convenções obrigatórias (comuns na comunidade)

- **Nomenclatura:** inglês, minúsculas, sem espaços. Use `kebab-case` em arquivos e pastas (ex.: `public-area.md`, `access-control.md`).
- **Um assunto → um arquivo:** evite arquivos “enciclopédia”. Prefira documentos menores e linkados entre si.
- **Fonte vs. publicação:**  
  - `*.md` = conteúdo vivo (especificação, runbook, padrões, ADRs).  
  - `sources/*.pdf` = anexos originais, fontes externas e documentos base imutáveis.  
  - `published/*.pdf` = PDFs gerados/fechados a partir dos MDs (opcional).
- **Cabeçalho padrão do MD (recomendado):** objetivo, escopo, dependências, impacto (público/restrito), logs/auditoria exigidos, referências internas.
- **Evolução controlada:** mudanças relevantes em regras, segurança, visibilidade e produtividade devem refletir em `docs/` e em notas de versão (releases).

---

# 1. `docs/foundation` — Base institucional e de produto

A pasta `docs/foundation` guarda os documentos que definem **o que o sistema é**, **para quem existe** e **quais limites governam o produto**. É o ponto de entrada para qualquer pessoa entender rapidamente o contexto do SGC sem precisar ler código. Em projetos com governança, essa pasta funciona como “constituição”: ela reduz discussões repetitivas e evita deriva de escopo.

Em termos práticos, `foundation` registra a identidade do sistema (missão institucional e utilidade operacional), o público-alvo (externo e interno), e as premissas que devem ser respeitadas por qualquer módulo. No SGC, isso inclui a regra estrutural de separação público/restrito e o princípio de que ações internas relevantes devem ser rastreáveis e auditáveis.

Como o projeto evolui por ciclos semanais, `foundation` também serve como âncora para decisões de roadmap e priorização. Quando surgir uma demanda “nova”, você avalia contra `foundation` para decidir se é escopo real, melhoria coerente ou expansão indevida. Isso evita retrabalho e reduz risco de inconsistências arquiteturais.

Por fim, `foundation` é a origem dos modelos de escrita e do vocabulário oficial do projeto. O objetivo é que documentos derivados (modules, security, operations) não reinventem estrutura e termos; eles devem herdar padrões claros para manter consistência técnica e institucional.

## 1.1 `docs/foundation/overview`
Esta subpasta concentra a apresentação técnica e institucional do sistema: visão geral, objetivos estratégicos, público-alvo, e o papel do SGC como canal oficial digital. É comum que ela contenha um `overview.md` e documentos que descrevem claramente a separação entre o que é informativo (público) e o que é operacional (restrito).

**Conteúdos recomendados:**
- `overview.md` (visão geral do SGC, propósito e limites)
- `audience.md` (público-alvo, perfis e cenários de uso)
- `high-level-scope.md` (escopo macro e boundaries)

## 1.2 `docs/foundation/scope`
Esta subpasta define o escopo controlado do sistema, premissas, restrições e critérios de aceitação de alto nível. Na prática comum, é aqui que se “congela” o que está dentro e fora do sistema, evitando que novas funcionalidades entrem sem governança. Em contexto judicial, isso é essencial para manter previsibilidade e reduzir risco.

**Conteúdos recomendados:**
- `in-scope.md` / `out-of-scope.md`
- `assumptions.md` (premissas)
- `constraints.md` (restrições técnicas e operacionais)

## 1.3 `docs/foundation/glossary`
Esta subpasta centraliza o vocabulário oficial: siglas, termos jurídicos/operacionais (cartório, gabinete, atos), e termos técnicos (módulos, logs, auditoria). A prática comum é manter um único arquivo `glossary.md` com seções e links internos. Isso previne divergências de nomenclatura entre telas, banco e documentos.

**Conteúdos recomendados:**
- `glossary.md`
- `entities.md` (lista de entidades do domínio e definições curtas)

## 1.4 `docs/foundation/stakeholders`
Esta subpasta descreve papéis e responsabilidades de alto nível (sem entrar nos detalhes do RBAC, que vai em `security/rbac`). A comunidade costuma separar stakeholder (papel organizacional) de permissionamento técnico (perfil). Aqui você explica “quem decide”, “quem opera” e “quem consome”.

**Conteúdos recomendados:**
- `roles-and-actors.md`
- `responsibility-matrix-highlevel.md`

## 1.5 `docs/foundation/templates`
Esta subpasta guarda modelos reutilizáveis de documentação (ex.: template de módulo, template de ADR, template de runbook). É prática comum para reduzir atrito e manter padrão de qualidade. Para o SGC, templates são particularmente úteis porque há muitos documentos derivados (módulos, regras, operações).

**Conteúdos recomendados:**
- `template-module.md`
- `template-adr.md`
- `template-runbook.md`

## 1.6 `docs/foundation/sources`
Esta subpasta armazena PDFs originais e anexos base que fundamentam `foundation`. A regra é: **não editar** esses PDFs; eles são referência imutável. Quando houver conversão para Markdown, o MD fica na pasta adequada e o PDF permanece aqui como fonte.

---

# 2. `docs/architecture` — Estrutura técnica, padrões e decisões

A pasta `docs/architecture` registra como o sistema é construído por dentro: arquitetura (ex.: MVC), padrões de organização, roteamento, modularidade e limites técnicos. Em projetos reais, a maior causa de dívida técnica é a ausência de uma arquitetura documentada e rastreável; por isso, esse diretório deve ser tratado como “manual do motor do sistema”.

Como o SGC exige separação público/restrito e segurança ativa desde o início, `architecture` também descreve onde e como essas regras são aplicadas: entry point, middlewares, controllers, models e views. A documentação aqui tem valor direto na implementação, porque ela define estruturas que são replicadas consistentemente em módulos e fluxos.

Outro papel essencial dessa pasta é registrar decisões, não apenas descrever o estado atual. A comunidade usa ADRs justamente para evitar “memória tribal”: quando alguém quiser mudar uma parte do sistema, deve entender o porquê do desenho atual. Isso reduz mudanças impulsivas que quebram segurança, auditoria ou coesão modular.

Finalmente, `architecture` deve manter diagramas e padrões de interface entre componentes (ex.: como controllers chamam models, como logs são gerados, como configurações administráveis influenciam comportamento). Esses documentos devem ser enxutos e vinculados ao código, evitando abstrações genéricas sem utilidade prática.

## 2.1 `docs/architecture/adr`
ADRs (Architecture Decision Records) são registros curtos e numerados de decisões arquiteturais. A prática comum é escrever em Markdown com data, contexto, decisão, alternativas consideradas e consequências. No SGC, ADRs são ideais para decisões como: “public web root”, “modelo de logs e retenção”, “RBAC por perfis”, “parametrização de regras em banco sem recálculo histórico”.

**Formato recomendado:**
- `ADR-0001-public-web-root.md`
- `ADR-0002-session-policy.md`

## 2.2 `docs/architecture/specs`
Esta subpasta contém especificações vivas da arquitetura: padrão MVC, roteamento, estrutura de diretórios do app, convenções de controllers/models/views, padrões de resposta e erro, e regras transversais (sigilo, logs, auditoria). É o lugar onde você descreve “como fazer do jeito certo” antes de codar.

**Conteúdos recomendados:**
- `mvc-structure.md`
- `routing-and-entrypoint.md`
- `directory-structure.md`
- `error-handling.md`

## 2.3 `docs/architecture/standards`
Aqui ficam padrões de engenharia: nomenclatura, estilo, organização de commits (se documentar), padrões de mensagens de log, e critérios mínimos de qualidade. A comunidade costuma separar “specs” (o que o sistema é) de “standards” (como o time trabalha) para reduzir mistura de assuntos.

**Conteúdos recomendados:**
- `naming-conventions.md`
- `logging-standard.md`
- `coding-guidelines.md`

## 2.4 `docs/architecture/diagrams`
Esta subpasta armazena diagramas (UML, fluxos, C4), preferencialmente em formatos versionáveis (`.puml`, `.svg`) e, quando necessário, exportações (`.png`). A prática comum é manter a fonte do diagrama junto do export, para reduzir divergência entre o desenho e o que é mostrado.

**Estrutura recomendada:**
- `puml/`
- `svg/`
- `png/`

## 2.5 `docs/architecture/sources`
PDFs e anexos técnicos originais relacionados à arquitetura ficam aqui. Se você gerar versões Markdown a partir desses PDFs, o MD deve ficar em `specs/` ou em outra pasta adequada, com referência explícita ao PDF fonte.

## 2.6 `docs/architecture/published` (opcional)
Use esta pasta se você quiser “fechar” versões oficiais em PDF (para distribuição ou arquivamento) geradas a partir de MDs. É prática comum em ambientes institucionais, porque separa a documentação viva do artefato publicado.

---

# 3. `docs/modules` — Especificação por módulo/submódulo

A pasta `docs/modules` organiza a documentação funcional e técnica por módulo. A regra é simples e comum: **um módulo = uma pasta**, e dentro dela ficam os documentos que descrevem regras, fluxos, telas, dados e permissões daquele módulo. Isso evita que “tudo” vá para um único documento e melhora a ligação entre documentação, banco e implementação.

No SGC, a abordagem por módulos é essencial porque o sistema possui Área Pública com limites rígidos e Área Restrita com módulos operacionais (Cartório, Gabinete, Audiências, Logs, Configurações, Produtividade, etc.). Documentar por módulo reduz o risco de expor dados indevidos e facilita garantir que cada módulo implementa logs, auditoria e regras de sigilo conforme necessário.

Além disso, essa pasta é a principal fonte para derivar backlog técnico e critérios de teste. Quando você implementa um submódulo, você deveria conseguir apontar para `docs/modules/<modulo>/spec/` e verificar: escopo, regras, bordas, dados persistidos, e impacto em perfis. Essa disciplina reduz retrabalho e acelera o desenvolvimento incremental.

Por fim, a organização interna do módulo deve ser estável: sempre os mesmos nomes de subpastas. Isso é prática comum em times maduros porque torna a documentação previsível. Se você abrir qualquer módulo, você deve encontrar rapidamente “spec”, “flows”, “data”, “permissions” e “ui”, sem precisar adivinhar.

## 3.1 Padrão de pastas por módulo (obrigatório)
Dentro de cada módulo, use o conjunto mínimo abaixo. Você pode adicionar subpastas extras apenas se houver necessidade real.

- `spec/` — especificação funcional/técnica do módulo.
- `flows/` — fluxos operacionais e estados (passo a passo, exceções).
- `ui/` — telas, componentes e comportamento do front.
- `data/` — entidades/tabelas, validações e migrations relacionadas.
- `permissions/` — impactos por perfil e regras de acesso (por rota/ação).
- `integrations/` — integrações e dependências externas (se houver).
- `examples/` — cenários práticos e casos de borda.
- `sources/` — anexos e PDFs específicos do módulo (se existirem).

## 3.2 Nomeação de módulos (dois padrões aceitos)
A comunidade usa dois padrões principais. Você pode escolher um (e deve manter consistente):

1) **Por código**: `m01-audiencias`, `m04-cartorio`, `m11-logs` (bom para rastrear com documentos e roadmap).
2) **Por nome**: `hearings`, `cartorio`, `logs` (bom para leitura rápida e onboarding).

**Recomendação para o SGC:** se os documentos oficiais e o escopo já usam “m1, m4, m11…”, o padrão por código tende a reduzir ambiguidade ao longo do roadmap.

## 3.3 Exemplo prático (estrutura de um módulo)
```
docs/modules/m04-cartorio/
  spec/overview.md
  flows/cartorio-standard-subtasks.md
  data/entities.md
  permissions/rbac-impact.md
  ui/screens.md
  examples/common-cases.md
  sources/original.pdf
```

## 3.4 Índice de specs em `docs/20-modules/specs/` (repositório)

No repositório SGC, as especificações por módulo costumam ficar em **`docs/20-modules/specs/`** (estrutura numérica alinhada a `docs/10-architecture`, `docs/00-foundation`, etc.). Use esta pasta como ponto de partida para escopo funcional, dados e roadmap antes de alterar código.

| Documento | Assunto |
|-----------|---------|
| [modulo-produtividade-interna.md](../../20-modules/specs/modulo-produtividade-interna.md) | Produtividade interna: o que já está implementado, legado, Fases 3 e 4 (ranking/metas/export vs. automação/badges/dashboard) |
| [cartorio-guias.md](../../20-modules/specs/cartorio-guias.md) | Cartório — guias |
| [cartorio-cartas-recebidas.md](../../20-modules/specs/cartorio-cartas-recebidas.md) | Cartório — cartas recebidas |
| [cartorio-cartas-expedidas.md](../../20-modules/specs/cartorio-cartas-expedidas.md) | Cartório — cartas expedidas |

---

# 4. `docs/security` — Segurança, LGPD e auditoria

A pasta `docs/security` reúne políticas, mecanismos e verificações para proteger o sistema e seus dados. Em um contexto judicial, esse diretório deve ser considerado tão crítico quanto o código: ele define padrões obrigatórios de autenticação, autorização, sigilo e auditoria. A regra prática é que toda mudança que impacta visibilidade de dados ou acesso deve refletir aqui.

Como o SGC separa área pública e restrita, `security` deve explicitar a **classificação de dados** (público, restrito, sigiloso) e como isso é aplicado em rotas, queries e templates. A comunidade costuma falhar nesse ponto quando mistura “regras” em várias pastas; aqui você centraliza a política e distribui referências para módulos.

Outro ponto essencial é auditoria: ações internas relevantes devem gerar trilhas rastreáveis por usuário, horário e tipo de ação. A documentação de auditoria define quais eventos são obrigatórios, como armazenar, como reter e quem pode visualizar. Isso sustenta governança e, no caso do SGC, também sustenta produtividade/pontuação como modelo rastreável.

Por fim, `security` deve conter checklists e resposta a incidentes. Isso é prática comum em sistemas operacionais reais: quando algo dá errado (vazamento potencial, falha de acesso, bug de sessão), você precisa de um procedimento curto e objetivo, sem improviso e com registro de ações tomadas.

## 4.1 `docs/security/auth`
Esta subpasta documenta autenticação e sessão: políticas de login, expiração, proteção contra acesso indevido e padrões de recuperação. Mesmo que a implementação mude, a política deve permanecer clara aqui. É recomendável incluir requisitos mínimos de headers e boas práticas para reduzir superfície de ataque.

## 4.2 `docs/security/rbac`
Aqui ficam regras de RBAC (Role-Based Access Control): perfis, permissões, mapa de rotas/actions, e regras de escalonamento (admin/chefia/servidor/estagiário). A comunidade costuma documentar RBAC com matrizes e texto descritivo; para o SGC, o mais importante é deixar “o que pode” e “o que não pode” inquestionável.

## 4.3 `docs/security/data-classification`
Esta subpasta define classificação de dados e regras de exposição, especialmente para a área pública. Inclua aqui os padrões de mascaramento, critérios de bloqueio para sigilo e orientações de como tratar campos sensíveis (minimização). Isso deve ser referenciado por módulos que exibem qualquer dado externo.

## 4.4 `docs/security/audit-logging`
Aqui ficam padrões de logs e auditoria: eventos obrigatórios, formato recomendado, retenção, exportação e controle de acesso ao log. Como boa prática, separe logs técnicos (erro) de logs de segurança (tentativas, bloqueios) e logs administrativos (mudanças de parâmetros).

## 4.5 `docs/security/lgpd`
Nesta subpasta você documenta princípios e decisões relacionadas à LGPD: finalidade, minimização, retenção, acesso por necessidade funcional, e tratamento de dados sensíveis. Mesmo que o sistema não seja “marketing”, a LGPD importa para governança e para limitar exposição indevida.

## 4.6 `docs/security/checklists`
Checklists são uma ferramenta prática e comum para reduzir falhas em fluxo direto. Inclua aqui listas “pré-release”, “pós-deploy”, “revisão de permissão”, “revisão de visibilidade pública” e “verificação de logs/auditoria”. O objetivo é permitir validação rápida, repetível e sem omissões.

## 4.7 `docs/security/incident-response`
Esta subpasta descreve como responder a incidentes: contenção, investigação mínima, registros, comunicação interna e correção. Mesmo um runbook simples é superior a improviso, e em sistemas críticos ele reduz tempo de indisponibilidade e risco de reincidência.

---

# 5. `docs/operations` — Operação, deploy, backups e runbooks

A pasta `docs/operations` é o “manual de operação” do sistema. Se um deploy falhar, se um backup precisar ser restaurado, se um job travar, ou se houver inconsistência de permissão em produção, é aqui que você procura. Em práticas modernas, operações e engenharia caminham juntas, e a documentação operacional evita dependência de memória e “passos secretos”.

No SGC, operações têm peso alto porque o sistema é usado em contexto judicial e precisa de confiabilidade. Isso inclui: ambientes separados (dev/staging/prod), implantação controlada, políticas de retenção de logs e backups, e rotinas automáticas (jobs) que expiram dados públicos quando necessário. Tudo isso deve estar claramente documentado para garantir estabilidade ao longo do cronograma.

A comunidade de devs normalmente separa operações em: ambientes, deploy, dados (backups), rotinas (jobs), observabilidade (monitoring/logs) e troubleshooting. Esse padrão é eficaz porque permite localizar rapidamente o assunto correto sob pressão (ex.: incidente). No seu caso, ele também serve para padronizar implantação semanal e revisão dominical.

Finalmente, `operations` deve conter procedimentos de rollback e hotfix, especialmente porque você adotou fluxo direto. Isso significa: se algo entrar errado no `main`, você deve ter instruções claras e curtas para reverter e restabelecer estabilidade, preservando logs e integridade do banco.

## 5.1 `docs/operations/environments`
Descreve dev/staging/prod, diferenças de configuração, variáveis sensíveis e pré-requisitos. Inclua regras de permissões de diretório, isolamento de `/public`, e como validar que o ambiente está “pronto para receber código funcional” (conceito típico de marcos iniciais).

## 5.2 `docs/operations/deploy`
Documenta pipeline, etapas, smoke tests, promoção (se houver), e rollback. Mesmo usando fluxo direto, é recomendado manter “deploy para validação” antes de produção quando possível, e registrar o que foi implantado. Inclua também como executar migrations com segurança.

## 5.3 `docs/operations/backups`
Define política de backup, rotação, retenção e, principalmente, **procedimento de restauração**. Boas práticas incluem: registrar sucesso/falha, verificar integridade, e testar restore periodicamente. Este é um ponto onde projetos falham por falta de procedimento claro.

## 5.4 `docs/operations/jobs`
Documenta cron jobs e rotinas automáticas: limpeza de temporários, expiração de documentos públicos, rotação de logs, manutenção e verificações. Inclua o que o job faz, frequência, onde loga, e como validar que executou corretamente.

## 5.5 `docs/operations/monitoring`
Define como observar o sistema: onde ficam logs, como identificar erros, quais sinais indicam problema de segurança ou falha operacional, e quais métricas/indicadores são úteis. Mesmo sem uma stack complexa de observabilidade, ter “onde olhar primeiro” acelera diagnóstico.

## 5.6 `docs/operations/troubleshooting`
Lista problemas comuns e soluções: CSS não carregando, sessão expirando cedo demais, erro de permissão em uploads, falha de migration, inconsistência de rota, etc. A prática comum é organizar por sintoma, não por causa, porque em incidente você enxerga o sintoma primeiro.

## 5.7 `docs/operations/access`
Documenta controles de acesso operacional: chaves, usuários de servidor, permissões de diretórios, e princípios para limitar acesso. Em projetos com governança, isso reduz risco de “acesso amplo demais” e facilita auditoria.

---

# 6. `docs/releases` — Versionamento, changelog e notas de versão

A pasta `docs/releases` registra “o que mudou” ao longo do tempo e como isso afeta usuários e operação. Em fluxo direto, isso é ainda mais importante porque não há PR obrigatório como ritual de documentação; então as notas de versão viram o registro humano do estado entregue. A comunidade usa `CHANGELOG` e notas por versão para manter rastreabilidade e facilitar rollback ou análise de regressão.

No SGC, releases devem cobrir não apenas novas funcionalidades, mas também mudanças de regras administráveis, parâmetros e ajustes de produtividade/pontuação. Como há governança e versionamento de regras (sem recálculo histórico), mudanças precisam ser registradas com data e responsável, e refletidas em release notes quando impactarem comportamento do sistema.

Outra prática comum é separar “changelog cumulativo” de “release notes por versão”. O primeiro ajuda a ver histórico rapidamente; o segundo ajuda a comunicar impactos específicos e orientar operação (por exemplo, “rodar migration X”, “reconfigurar parâmetro Y”, “novo comportamento de expiração”). Em sistemas críticos, também é comum manter guias de migração quando mudanças exigem cuidado extra.

Por fim, `releases` deve conter checklists de liberação, mesmo curtos. Isso reduz erros simples (deploy sem build, schema fora de sincronia, regra de permissão omitida) e transforma o release em um processo repetível. Em projetos institucionais, repetibilidade é mais valiosa do que velocidade bruta.

## 6.1 `docs/releases/changelog`
Aqui fica o `CHANGELOG.md` (cumulativo) e regras de escrita. A prática comum é listar por versão, com categorias: Added, Changed, Fixed, Security, Deprecated. Mesmo que você não use esse padrão completo, manter consistência é essencial.

## 6.2 `docs/releases/notes`
Notas por versão (ex.: `v1.1.0.md`). Devem explicar impacto funcional e operacional, incluindo: módulos afetados, mudanças de segurança, alterações de parâmetro e se há passo extra (migration, job, etc.).

## 6.3 `docs/releases/migration-guides`
Guia específico quando uma versão exige atenção extra: mudanças de banco, mudanças de config, alteração de caminhos de storage, mudanças em retenção, etc. A comunidade trata isso como “o que fazer ao atualizar”, e isso reduz falhas em produção.

## 6.4 `docs/releases/release-checklists`
Checklists do release (pré e pós). Em fluxo direto, isso é um “guard rail” essencial. Inclua: smoke tests, validação de login/sessão, verificação de isolamento público/restrito, checagem de logs, e validação de jobs críticos.

## 6.5 `docs/releases/sources` (opcional)
Se você publicar PDFs de release (por exigência institucional), coloque aqui. Mantenha claro que são snapshots, e que o conteúdo vivo permanece em Markdown.

---

## 7. Sumário de subpastas (visão rápida)

- `foundation/`: `overview/`, `scope/`, `glossary/`, `stakeholders/`, `templates/`, `sources/`
- `architecture/`: `adr/`, `specs/`, `standards/`, `diagrams/`, `sources/`, `published/` (opcional)
- `modules/`: por módulo → `spec/`, `flows/`, `ui/`, `data/`, `permissions/`, `integrations/`, `examples/`, `sources/`
- **No repositório:** `20-modules/specs/` — índice em §3.4 (specs por módulo em Markdown)
- `security/`: `auth/`, `rbac/`, `data-classification/`, `audit-logging/`, `lgpd/`, `checklists/`, `incident-response/`, `sources/`
- `operations/`: `environments/`, `deploy/`, `backups/`, `jobs/`, `monitoring/`, `troubleshooting/`, `access/`, `sources/`
- `releases/`: `changelog/`, `notes/`, `migration-guides/`, `release-checklists/`, `sources/`

---

## 8. Próxima ação sugerida (para consolidar rápido)

1. Criar as subpastas vazias com arquivos `README.md` curtos explicando a finalidade de cada uma.  
2. Definir o template de módulo (`foundation/templates/template-module.md`) e usar para pelo menos 1 módulo (ex.: Audiências ou Cartório).
3. Criar 3 ADRs iniciais em `architecture/adr/` (public web root, política de sessão, logs/auditoria).  
4. Padronizar `sources/` vs `published/` e mover PDFs conforme função.

