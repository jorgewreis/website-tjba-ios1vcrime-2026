# Fluxo de Navegação e Funcionalidades: Área Pública

## 1. Visão Geral e Propósito

A Área Pública do **WebApp | 1vCrimeIlheus** atua como o portal de transparência e serviços digitais da unidade judiciária. Diferente da Área Restrita, que foca na gestão interna, este ambiente foi desenhado para atender ao cidadão, ao estudante de direito e à comunidade em geral, oferecendo acesso rápido a informações institucionais e ferramentas de autoatendimento sem a necessidade de intervenção de um servidor.

O design e a arquitetura da informação priorizam a clareza e a acessibilidade. O objetivo é reduzir a demanda de atendimento presencial e telefônico no balcão do cartório, permitindo que usuários externos resolvam demandas simples — como consultar a pauta de audiências ou obter comprovantes de participação — de forma autônoma e remota. Todas as funcionalidades respeitam rigorosamente as regras de sigilo processual, não expondo dados sensíveis de partes ou processos em segredo de justiça.

## 2. Acesso e Autenticação

A tela de login (`index.php`) serve como o ponto de convergência tanto para os usuários internos (servidores e chefias) quanto para os estudantes cadastrados. Embora a interface seja unificada, o roteamento pós-login é inteligente: servidores são direcionados para a Área Restrita (Iframe administrativo), enquanto estudantes permanecem no ambiente do Portal do Estudante, com acesso limitado às suas próprias vinculações.

Para garantir a continuidade do acesso, o sistema disponibiliza um fluxo de **Recuperação de Senha** automatizado. Caso o usuário esqueça suas credenciais, ele pode solicitar a redefinição informando o e-mail cadastrado. O sistema valida a existência da conta e envia um link seguro ou token para a criação de uma nova senha, garantindo que o usuário recupere sua autonomia sem precisar contatar o suporte técnico da vara.

## 3. Serviços ao Estudante

O Portal do Estudante é um módulo dedicado ao público acadêmico que necessita comprovar horas de atividade complementar através da assistência de audiências. O fluxo inicia-se com o **Auto-cadastro**, onde o estudante preenche seus dados pessoais e acadêmicos. Após o login, ele tem acesso à funcionalidade de **Vinculação à Audiência** (Check-in), que permite selecionar audiências da pauta do dia para registrar sua presença virtual ou física. É importante ressaltar que o sistema filtra automaticamente as audiências, exibindo para vinculação apenas os processos que **não possuem segredo de justiça**.

Após a conclusão da audiência e a devida validação do sistema (ou do servidor responsável), o estudante ganha acesso à tela de **Download de Atas**. Nesta seção, ele pode visualizar e baixar os arquivos PDF das atas das audiências às quais se vinculou efetivamente. Este recurso elimina a necessidade de o estudante solicitar cópias físicas ou enviadas por e-mail, fechando o ciclo de sua participação acadêmica de forma totalmente digital.

## 4. Transparência e Tempo Real

Um dos pilares da Área Pública é o **Painel de Audiências em Tempo Real**. Esta funcionalidade exibe, de forma dinâmica e atualizada, o status das audiências agendadas para o dia (ex: "Em andamento", "Aguardando", "Encerrada"). O painel serve tanto para orientar as partes e advogados que aguardam no saguão (ou remotamente) quanto para dar publicidade aos atos da vara, sempre ocultando nomes completos ou detalhes de processos sigilosos.

Além da pauta diária, a seção de transparência apresenta **Estatísticas Gerais de Produtividade**. Gráficos e indicadores numéricos demonstram o volume de sentenças, audiências realizadas e processos arquivados, prestando contas à sociedade sobre a eficiência da unidade. Complementarmente, uma área dedicada exibe as **Premiações e Reconhecimentos** recebidos pela vara, reforçando a credibilidade e o compromisso da equipe com a excelência jurisdicional.

## 5. Informações Institucionais e Suporte

Para facilitar a comunicação e o acesso à informação, o portal disponibiliza uma seção completa com **Dados de Contato**. Nela, o cidadão encontra endereços, telefones atualizados, e-mails oficiais e o horário de atendimento ao público. Junto a isso, é apresentada a **Composição da Equipe**, listando os nomes dos servidores e estagiários ativos, o que humaniza o atendimento e oferece transparência sobre a força de trabalho da unidade.

Visando reduzir dúvidas recorrentes, foi implementada uma seção de **FAQ (Perguntas Frequentes)** e um repositório de **Documentos Públicos**. O FAQ responde a questões simples como vestimenta adequada, documentos necessários para audiência e procedimentos de balcão. Já o repositório permite o download de Manuais, Normas de Conduta e Portarias, garantindo que advogados e partes tenham acesso fácil às regras locais.

## 6. Restrições de Consulta

É fundamental delimitar o escopo da Área Pública para alinhar as expectativas dos usuários. Este portal **não funciona** como um sistema de consulta processual completo (como o PJe ou Projudi). Por questões de segurança e competência, não é disponibilizada nenhuma ferramenta de busca livre de processos por número ou nome da parte.

Da mesma forma, não existe consulta pública de dados pessoais de partes envolvidas em litígios. A publicidade dos atos restringe-se à pauta de audiências do dia (sem segredo de justiça) e aos dados estatísticos agregados. Qualquer necessidade de consulta detalhada aos autos deve ser realizada através dos sistemas oficiais do tribunal ou presencialmente, mediante identificação adequada.
