# Política de Logs, Auditoria e Retenção de Dados

Este documento estabelece as diretrizes técnicas e operacionais para a geração, armazenamento, proteção e descarte de registros de eventos (logs) dentro do ecossistema da aplicação. A política visa garantir a rastreabilidade de ações críticas, auxiliar na depuração de erros técnicos e assegurar a conformidade com normas de segurança e privacidade de dados.

A implementação eficaz de logs serve como a principal ferramenta de diagnóstico e auditoria forense. Ao registrar sistematicamente quem acessou o sistema, quando a ação ocorreu e qual operação foi realizada, cria-se uma camada de transparência que permite identificar comportamentos anômalos, tentativas de intrusão ou falhas na integridade dos dados, protegendo tanto a instituição quanto os usuários finais.

## 1. Classificação e Granularidade dos Logs

Para evitar a saturação do armazenamento e garantir que apenas informações relevantes sejam processadas, os logs são categorizados por níveis de severidade e origem. É fundamental distinguir entre logs de infraestrutura (gerados pelo servidor web Nginx e banco de dados MySQL) e logs de aplicação (gerados pelos entrypoints PHP, serviços internos e regras de negócio). A granularidade deve ser ajustada para capturar detalhes suficientes para a reconstrução de um evento, sem, no entanto, registrar dados sensíveis como senhas ou tokens de autenticação em texto plano.

Os logs de aplicação devem seguir uma estrutura padronizada, preferencialmente em formato JSON, facilitando a ingestão por ferramentas de monitoramento. Cada entrada de log deve conter, minimamente, o *timestamp* (carimbo de tempo UTC), o nível de severidade, o identificador do usuário (se autenticado), o endereço IP de origem e a mensagem descritiva do evento. A separação clara entre eventos de rotina e eventos críticos permite a configuração de alertas automatizados para situações que exigem intervenção imediata.

- **Níveis de Severidade:**
    - **DEBUG:** Informações detalhadas para desenvolvimento (desativado em produção).
    - **INFO:** Eventos de rotina, como login bem-sucedido ou carregamento de página.
    - **WARNING:** Eventos inesperados que não interrompem o serviço (ex: tentativa de acesso a rota inexistente).
    - **ERROR:** Falhas que impedem a execução de uma função (ex: falha de conexão com o banco).
    - **CRITICAL:** Falhas graves que derrubam o sistema ou indicam violação de segurança.

## 2. Estratégia de Retenção e Ciclo de Vida

A retenção de dados define por quanto tempo os registros devem ser mantidos antes de serem arquivados ou excluídos permanentemente. Esta estratégia busca equilibrar a necessidade de histórico para auditorias de longo prazo com as limitações físicas de armazenamento e os requisitos legais de privacidade. O ciclo de vida dos logs é dividido em duas fases principais: armazenamento "quente" (acesso imediato para análise recente) e armazenamento "frio" (arquivamento compactado para conformidade).

Logs de acesso web e erros de aplicação, que possuem alto volume e menor relevância histórica, têm um ciclo de vida mais curto. Já os logs de auditoria, que registram alterações em dados sensíveis (criação de usuários, exclusão de registros, alterações de permissão), devem ser preservados por um período estendido. A rotação automática de logs (*log rotation*) deve ser configurada no servidor para comprimir arquivos antigos diariamente e excluir aqueles que ultrapassarem o período de retenção definido.

- **Prazos de Retenção Definidos:**
    - **Logs de Acesso (Nginx/Web):** 30 dias em disco (rotação diária).
    - **Logs de Erro (Application/System):** 90 dias (auxílio em diagnósticos de bugs recorrentes).
    - **Logs de Auditoria (Ações de Usuário):** 5 anos (armazenamento frio/backup para fins legais).
    - **Backups de Banco de Dados:** Retenção progressiva (diários por 7 dias, semanais por 1 mês, mensais por 1 ano).

## 3. Controle de Acesso e Privacidade

O acesso aos arquivos de log e aos relatórios de auditoria é estritamente controlado e restrito a perfis de alto nível hierárquico. Como os logs podem conter metadados que revelam padrões de uso e endereços IP, tratá-los com o mesmo nível de segurança dos dados produtivos é imperativo. O princípio do menor privilégio deve ser aplicado, garantindo que apenas os responsáveis pela segurança e gestão do sistema possam visualizar esses registros.

Conforme as regras de negócio estabelecidas, a visualização de relatórios de segurança e trilhas de auditoria é exclusiva para os perfis **ADMIN** e **CHEFIA**. O perfil **SERVIDOR** pode gerar relatórios de uso operacional, mas não deve ter acesso a logs técnicos ou de segurança. O perfil **ESTUDANTE** não possui qualquer permissão de visualização de logs ou relatórios internos.

- **Matriz de Acesso aos Logs:**
    - **ADMIN:** Acesso total a logs de sistema, aplicação e auditoria (leitura e exportação).
    - **CHEFIA:** Acesso a relatórios de auditoria de ações (quem fez o quê) e logs de segurança.
    - **SERVIDOR:** Sem acesso a logs técnicos; acesso apenas a relatórios operacionais de sua competência.
    - **Desenvolvedores/DevOps:** Acesso temporário e supervisionado apenas em ambientes de homologação ou durante janelas de manutenção.

## 4. Implementação Técnica e Rotação

A implementação técnica da política de logs utiliza ferramentas nativas do sistema operacional Linux e configurações específicas do CloudPanel/Nginx. O utilitário `logrotate` é o mecanismo padrão para gerenciar a rotação, compressão e expurgo automático dos arquivos de log. Isso impede que o crescimento descontrolado dos arquivos de texto consuma todo o espaço em disco disponível, o que poderia levar à interrupção do serviço.

Para a aplicação PHP, recomenda-se o uso de logging estruturado sempre que viável, seja por `error_log`, arquivos próprios em `storage/logs/` ou integração com serviços centralizados como CloudWatch. Em ambiente de produção, o Nginx, o PHP-FPM e a aplicação podem gerar arquivos físicos ou saídas integradas ao coletor do servidor, que por sua vez são gerenciados pelo `logrotate`. É crucial garantir que as permissões dos arquivos de log (`chmod/chown`) impeçam a leitura por usuários não privilegiados do sistema operacional.

- **Configuração do Logrotate:**
    - **Frequência:** Diária (`daily`).
    - **Compressão:** Ativada (`compress`), utilizando gzip para economizar espaço.
    - **Modo de Criação:** Novos arquivos criados com permissão 0640 (`create 0640 root adm`).
    - **Scripts de Pós-Rotação:** Recarregar o Nginx e, quando aplicável, o PHP-FPM ou o coletor de logs suavemente para liberar os descritores de arquivo (`postrotate`).

## 5. FAQ

- **O sistema registra as senhas dos usuários nos logs?**
    - **Não.** Em hipótese alguma senhas, tokens de sessão, tokens de reset, JWT legados ou dados de cartão de crédito devem ser gravados nos logs. Caso ocorra um erro durante o login, registra-se apenas o ID do usuário (se identificado) e o motivo da falha, nunca a credencial enviada.

- **O que acontece se o disco do servidor encher devido aos logs?**
    - **Interrupção de Serviço.** Se o disco atingir 100% de capacidade, o banco de dados e o servidor web podem parar de funcionar. Por isso, a política de rotação e compressão é vital. Monitoramento de espaço em disco deve ser configurado para alertar quando o uso atingir 80%.

- **Um usuário comum pode pedir para ver seus próprios logs de acesso?**
    - **Sim, mediante solicitação.** Embora o usuário não tenha acesso direto via interface, a LGPD garante o direito de saber quais dados são processados. Um Administrador pode extrair um relatório filtrado contendo apenas os registros daquele usuário específico.

- **Por que os logs de auditoria são mantidos por 5 anos?**
    - **Conformidade Legal.** Prazos prescricionais para ações civis e normas de segurança da informação geralmente recomendam ou exigem a guarda de registros de atividades por 5 anos para garantir respaldo jurídico em caso de investigações futuras.
