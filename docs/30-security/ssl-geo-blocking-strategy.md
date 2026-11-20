# Estratégia de Segurança: Configuração SSL/TLS e Restrições Geográficas

> **Estado:** orientações propostas; a topologia, as configurações e a ativação em produção não foram verificadas por este documento.

Este documento apresenta diretrizes técnicas referentes à segurança de dados em trânsito e ao controle de acesso baseado em localização geográfica. O objetivo primordial é garantir a confidencialidade, integridade e autenticidade das informações trocadas entre as interfaces clientes e a infraestrutura do servidor, utilizando protocolos de criptografia robustos e mecanismos de filtragem proativa.

A implementação destas estratégias visa mitigar riscos associados a ataques de interceptação, como *Man-in-the-Middle* (MitM), e reduzir drasticamente a superfície de ataque contra botnets automatizados oriundos de regiões fora do escopo operacional da aplicação. Estas definições aplicam-se a todo o ecossistema web, abrangendo o servidor web Nginx gerenciado via CloudPanel, o runtime PHP sob PHP-FPM e os entrypoints públicos da aplicação.

## 1. Estratégia de Implementação SSL/TLS

A implementação do protocolo *Transport Layer Security* (TLS) é o pilar fundamental para a comunicação segura na web moderna. No contexto deste sistema, a estratégia envolve não apenas a ativação de certificados de criptografia, mas também a configuração rigorosa de *cipher suites* e versões de protocolo. A autoridade certificadora, a renovação e a validade da cadeia devem ser verificadas no ambiente efetivo; automação não dispensa monitoramento de expiração.

Além disso, a arquitetura deve impor uma política estrita de "HTTPS Only". Isso significa que o servidor deve ser configurado para rejeitar qualquer tentativa de comunicação não criptografada ou, preferencialmente, redirecionar automaticamente todo o tráfego da porta 80 (HTTP) para a porta 443 (HTTPS) utilizando um código de status 301 (*Permanent Redirect*). Este comportamento assegura que dados sensíveis, como credenciais de autenticação e tokens de sessão, nunca sejam transmitidos em texto plano, protegendo o usuário contra monitoramento passivo em redes públicas ou comprometidas.

### 1.1. HTTP Strict Transport Security (HSTS)

Para elevar o nível de segurança provido pelo SSL padrão, pode-se utilizar o *HTTP Strict Transport Security* (HSTS). Este mecanismo funciona como uma diretiva que o servidor envia ao navegador do usuário (*User Agent*), instruindo-o a interagir com o domínio exclusivamente via conexões seguras por um período definido. Isso impede ataques de *downgrade*, onde um atacante tenta forçar o navegador a abandonar a conexão criptografada em favor de uma versão insegura, e também protege contra sequestro de cookies.

A configuração HSTS depende da topologia efetiva e de revisão operacional. Antes de usar `includeSubDomains` ou `preload`, confirme que todos os subdomínios abrangidos suportam HTTPS e avalie as consequências duradouras da política; o uso dessas diretivas não está comprovado neste documento.

- Avaliar suporte a TLS 1.2 e 1.3 e desativação de protocolos obsoletos conforme a compatibilidade do ambiente.
- Priorizar algoritmos com *Forward Secrecy* e acompanhar validade e renovação do certificado.

## 2. Estratégia de Bloqueio Geográfico (Geo-blocking)

O bloqueio por país é uma proposta de defesa adicional, sujeita à decisão operacional e à confirmação da topologia real. Geolocalização por IP é imperfeita e não substitui autenticação, autorização ou proteção contra ataques. Este documento não comprova que CDN, proxy, bloqueio geográfico ou qualquer regra de origem esteja ativo.

### 2.1. Fronteira de confiança

Antes de implementar qualquer regra, inventarie o caminho real entre cliente, CDN ou proxy e origem; identifique quais componentes determinam o país, como o fazem e quais caminhos conseguem alcançar diretamente a origem. Não confie em `CF-IPCountry`, `X-Forwarded-For` ou outro cabeçalho apenas porque está presente: um cliente pode forjá-lo.

Uma alternativa a avaliar em homologação é restringir a origem aos endereços de borda aprovados e atualizados, garantir que a borda descarte ou substitua os cabeçalhos fornecidos pelo cliente e aceitar o país somente após verificar o remetente imediato como proxy confiável. A lista de proxies, o mecanismo de atualização e eventuais caminhos que contornem a borda precisam ser validados para a infraestrutura real. A configuração `TRUSTED_PROXIES` existente em partes da aplicação não comprova, por si só, a autenticidade de um cabeçalho de país nem uma política geográfica ativa.

### 2.2. País desconhecido e cobertura

Defina e aprove as áreas protegidas e o comportamento de páginas públicas antes da ativação. Se uma política restritiva for aprovada, país ausente, inválido ou não determinável deve ser tratado como desconhecido e negado nas rotas protegidas, com resposta e procedimento de recuperação definidos; não presumir que ausência significa Brasil. A regra para páginas públicas e integrações deve ser decidida separadamente.

O inventário deve incluir páginas PHP, APIs, rotas AJAX, métodos HTTP, aliases e caminhos legados efetivamente expostos pelo servidor. Examine `public/*.php`, `public/api/**` e, se expostos, os demais entrypoints e diretórios legados; essa lista não é exaustiva. Verifique que uma rota alternativa não contorne o mesmo recurso protegido. Não use prefixos exemplificativos como prova de cobertura.

### 2.3. Exceções verificáveis

Acesso legítimo do exterior, monitoramento e indexação exigem política explícita, aprovada e revisável. Quando autorizada, uma exceção pode usar VPN corporativa ou identidade e origem verificadas, com responsável, finalidade, prazo e revogação; uma lista de IPs somente é adequada quando seu controle e atualização forem confirmados. Um `User-Agent` declarado pelo cliente nunca comprova identidade de robô ou autoriza exceção. Não publique IPs pessoais ou dados sensíveis nos registros de auditoria.

## 3. Verificação antes de qualquer ativação

Confirme certificado, renovação, redirecionamento HTTPS e HSTS no ambiente de destino antes de propor mudanças. A validação geográfica abaixo requer homologação isolada com dados sintéticos, configuração conhecida e uma implementação candidata; não foi executada por esta revisão documental. Não altere produção para realizar estes testes.

| Cenário em homologação | Resultado esperado para rota protegida, se a política restritiva for aprovada |
| --- | --- |
| Acesso direto à origem com cabeçalho forjado `CF-IPCountry: BR` | Negado; o cabeçalho não estabelece confiança. |
| Proxy não confiável envia país permitido ou cabeçalhos de encaminhamento forjados | Negado; a identidade do remetente imediato é obrigatória. |
| Borda confiável substitui entrada do cliente e determina país permitido | A política geográfica permite prosseguir; autenticação e autorização continuam obrigatórias. |
| Borda confiável determina país não permitido | Negado, salvo exceção aprovada e verificável. |
| País ausente, inválido ou desconhecido | Negado, com tratamento operacional previsto. |
| Cada família de rotas protegidas, seus métodos e aliases | Mesma decisão, sem contorno por caminho alternativo. |
| Rotas públicas e integrações identificadas no inventário | Comportamento conforme política específica aprovada, sem presumir liberação global. |
| Exceção válida, expirada e revogada | Apenas a exceção válida satisfaz os critérios aprovados. |
| Cliente usa `User-Agent` de robô sem identidade verificada | Não recebe exceção por esse cabeçalho. |

Registre configuração sintética, versão do candidato, passos, resultados e limites. Inclua testes tanto na borda como na origem quando esses caminhos existirem e um plano de recuperação de acesso antes de propor qualquer mudança operacional.

## 4. FAQ

Esta seção aborda dúvidas recorrentes sobre o impacto e a manutenção das políticas de segurança descritas acima. O objetivo é fornecer respostas rápidas para as equipes de suporte técnico e desenvolvimento, esclarecendo dúvidas comuns que podem surgir durante a operação do sistema ou no diagnóstico de problemas de conectividade.

Estas respostas devem ser utilizadas como referência ao distinguir entre uma falha do sistema e um comportamento esperado das camadas de segurança. O impacto sobre usuários no exterior depende da política que vier a ser aprovada e efetivamente implantada.

- **O Geo-blocking garante 100% de proteção contra ataques estrangeiros?**
    Não. Atacantes podem utilizar VPNs, Proxies ou máquinas comprometidas (zumbis) localizadas dentro do Brasil para contornar essa restrição. No entanto, a medida é eficaz para eliminar o "ruído de fundo" de ataques automatizados e varreduras em massa que se originam globalmente.

- **Qual o procedimento se um gestor precisar de acesso em viagem ao exterior?**
    Avalie uma VPN corporativa ou exceção temporária verificável segundo a política aprovada, com responsável, prazo e revogação. Não presuma que uma exceção já exista.

- **Por que usar HSTS se já temos redirecionamento para HTTPS?**
    O redirecionamento padrão ainda permite que a primeiríssima requisição seja feita via HTTP, o que pode ser interceptado. O HSTS instrui o navegador a nunca sequer tentar a conexão HTTP, eliminando essa pequena janela de vulnerabilidade e protegendo contra ataques de *SSL Stripping*.

- **O Geo-blocking pode afetar a indexação de SEO (Googlebot)?**
    Sim, se aplicado indiscriminadamente. Decida quais páginas públicas precisam de indexação e valide uma política específica; não conceda exceção com base apenas no *User-Agent* alegado.
