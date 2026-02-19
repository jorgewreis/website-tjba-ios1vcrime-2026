# Especificação Funcional — Área Restrita

## Referências e alinhamento
- docs/00-foundation/escopo-produto.md — visão de produto completa (módulos m1, m3, m4, m5, m6, m11, m12, Gabinete).
- docs/00-foundation/funcionalidades areas publica e restrita.md — regras de segregação, perfis e sigilo.
- docs/00-foundation/technical-assumptions-constraints.md — autênticação jwt/sessionStorage, políticas de senha e isolamento por setor.

## 1. Visão geral e arquitetura
A Área Restrita é o núcleo administrativo do webapp. Carregada via dmin/home.php, ela renderiza o conteúdo externo (controllers/views) dentro de um iframe para manter o menu lateral e cabeçalho fixos. Essa abordagem (iframe + shell Next.js) preserva o isolamento entre público e interno, protegendo rotas sensíveis e respeitando o canônico de arquitetura.

## 2. Autenticação e controle de sessão
A autenticação acontece em index.php e valida tokens JWT de 6h. Os dados de sessão ficam exclusivamente em sessionStorage; não há cookies PHP nativos. Regras de senha (8 caracteres, letras, números e símbolos) e bloqueio após 3 tentativas são aplicadas em 	echnical-assumptions-constraints.md.

## 3. Perfis e isolamento
1. **Chefias:** visam configurações globais, alocação e realocação de usuários, acesso irrestrito a logs e relatórios críticos.
2. **Servidores:** operam dentro do setor, podendo incluir/editar dados, mas sem excluir nem relocar colegas.
3. **Estagiários e consultores:** acesso reduzido, apenas leitura ou edição limitada, sem permissão para relatórios sensíveis.

Regra transversal: **isolamento por setor** (perfil não vê dados de outro núcleo) e **log de auditoria obrigatório** (AuditLogger).

## 4. Módulos internos principais
- **m1 Audiências:** cadastro, partes, PDFs e controle de presença vinculando estudantes.
- **m3 Tarefas:** catálogo, sub-tarefas parametricas, pontuação e exclusão controlada.
- **m4 Cartório:** regras de sub-tarefas, cartas, editais, guias, ofícios e medidas protetivas.
- **m5 Notificações:** geração automática e prioridades controladas por chefia.
- **m6 Produtividade:** registro de pontos, dashboards e metas.
- **m11 Logs/Auditoria:** reporta acessos, falhas, alterações e eventos sensíveis.
- **m12 Configurações:** prazos, notificações, governança e versionamento de regras.
- **Gabinete:** réus presos, ofícios consultoria e monitoramento eletrônico.

## 5. Relatórios e segurança
Relatórios operacionais (produtividade, cadastros, volumes) são liberados para chefias e servidores; relatórios de segurança (logs de acesso, tentativas falhas, alterações sensíveis) ficam exclusivos para chefias/administradores. A integridade dos dados usa prefixos semânticos (USR-, PER-) e validação server-side.

## 6. Governança e compliance
As regras de auditoria, versionamento e isolamento seguem o canônico global. Todas as ações críticas geram toast (frontend) e log (backend) com o mesmo padrão, enquanto políticas de LGPD garante mascaramento nos relatórios e tabelas, conforme 	echnical-assumptions-constraints.md.
