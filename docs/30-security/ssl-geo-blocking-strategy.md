# Estratégia de Segurança: Configuração SSL/TLS e Restrições Geográficas

Este documento estabelece as diretrizes técnicas e decisões estratégicas referentes à segurança de dados em trânsito e ao controle de acesso baseado em localização geográfica. O objetivo primordial é garantir a confidencialidade, integridade e autenticidade das informações trocadas entre as interfaces clientes e a infraestrutura do servidor, utilizando protocolos de criptografia robustos e mecanismos de filtragem proativa.

A implementação destas estratégias visa mitigar riscos associados a ataques de interceptação, como *Man-in-the-Middle* (MitM), e reduzir drasticamente a superfície de ataque contra botnets automatizados oriundos de regiões fora do escopo operacional da aplicação. Estas definições aplicam-se a todo o ecossistema web, abrangendo o servidor web Nginx gerenciado via CloudPanel, o runtime PHP sob PHP-FPM e os entrypoints públicos da aplicação.

## 1. Estratégia de Implementação SSL/TLS

A implementação do protocolo *Transport Layer Security* (TLS) é o pilar fundamental para a comunicação segura na web moderna. No contexto deste sistema, a estratégia envolve não apenas a ativação de certificados de criptografia, mas também a configuração rigorosa de *cipher suites* e versões de protocolo. Utilizamos certificados Let's Encrypt, que oferecem um processo de validação e renovação automatizado, garantindo que a cadeia de confiança permaneça válida sem intervenção manual, reduzindo assim o risco de expiração e indisponibilidade do serviço.

Além disso, a arquitetura deve impor uma política estrita de "HTTPS Only". Isso significa que o servidor deve ser configurado para rejeitar qualquer tentativa de comunicação não criptografada ou, preferencialmente, redirecionar automaticamente todo o tráfego da porta 80 (HTTP) para a porta 443 (HTTPS) utilizando um código de status 301 (*Permanent Redirect*). Este comportamento assegura que dados sensíveis, como credenciais de autenticação e tokens de sessão, nunca sejam transmitidos em texto plano, protegendo o usuário contra monitoramento passivo em redes públicas ou comprometidas.

### 1.1. HTTP Strict Transport Security (HSTS)

Para elevar o nível de segurança provido pelo SSL padrão, implementamos o *HTTP Strict Transport Security* (HSTS). Este mecanismo funciona como uma diretiva que o servidor envia ao navegador do usuário (*User Agent*), instruindo-o a interagir com o domínio exclusivamente via conexões seguras por um período definido. Isso impede ataques de *downgrade*, onde um atacante tenta forçar o navegador a abandonar a conexão criptografada em favor de uma versão insegura, e também protege contra sequestro de cookies.

A configuração do HSTS deve ser aplicada diretamente nas diretivas do bloco de servidor (*server block*) do Nginx dentro do ambiente CloudPanel. É crucial que o parâmetro `max-age` seja definido para uma longa duração (tipicamente um ano ou 31.536.000 segundos) e que a diretiva `includeSubDomains` esteja presente para proteger todo o ecossistema da aplicação. Adicionalmente, a diretiva `preload` deve ser considerada para permitir que o domínio seja incluído nas listas de HSTS *hardcoded* dos principais navegadores.

- **Implementação do Cabeçalho:** `Strict-Transport-Security: max-age=31536000; includeSubDomains; preload`
- **Versões de Protocolo:** Suporte exclusivo para TLS 1.2 e TLS 1.3, desabilitando explicitamente SSLv3, TLS 1.0 e TLS 1.1.
- **Cipher Suites:** Priorização de algoritmos que ofereçam *Forward Secrecy* (FS), garantindo que o comprometimento de uma chave privada não comprometa sessões passadas.

## 2. Estratégia de Bloqueio Geográfico (Geo-blocking)

O bloqueio geográfico, ou *Geo-blocking*, é adotado como uma medida de defesa de perímetro para restringir o acesso à aplicação com base na origem do endereço IP da requisição. Considerando que o público-alvo e a operação administrativa do sistema são estritamente regionais (localizados no Brasil), o tráfego oriundo de outros continentes representa, estatisticamente, uma alta probabilidade de ser malicioso. Isso inclui scanners automatizados, tentativas de força bruta e ataques DDoS originados de botnets globais hospedadas em países com alta incidência de cibercrime.

Ao implementar essa restrição, alcançamos uma otimização significativa dos recursos do servidor (CPU, RAM e Largura de Banda). Ao invés de processar milhares de requisições inválidas na camada de aplicação, essas conexões são descartadas na borda da rede ou no servidor web. Isso garante que a infraestrutura permaneça disponível e responsiva para os usuários legítimos, ao mesmo tempo que remove um grande vetor de ataques oportunistas que varrem a internet em busca de vulnerabilidades conhecidas em frameworks web.

### 2.1. Bloqueio em Nível de Infraestrutura (Nginx/CloudPanel)

A camada mais eficiente para aplicar restrições geográficas é o servidor web, neste caso, o Nginx. O processamento de regras neste nível ocorre antes que a requisição seja repassada para o runtime da aplicação PHP, resultando em latência extremamente baixa e alto *throughput* de rejeição. O CloudPanel facilita essa integração através do módulo `ngx_http_geoip_module` ou através de Listas de Controle de Acesso (ACLs) que mapeiam faixas de IP para seus respectivos países (*GeoIP Database*).

A política recomendada é a de *Whitelisting* (Lista de Permissão), que é mais segura e gerenciável do que *Blacklisting*. Neste modelo, negamos explicitamente todo o tráfego por padrão (`deny all`) e permitimos apenas faixas de IP originárias do Brasil (`allow BR`). Essa abordagem elimina a necessidade de atualizar constantemente listas de países bloqueados e garante que novas ameaças de locais inesperados sejam neutralizadas automaticamente.

- **Módulo:** Utilização do `ngx_http_geoip_module` ou integração equivalente no CloudPanel.
- **Política Padrão:** Negar todo tráfego de entrada que não corresponda ao código de país permitido (BR).
- **Tratamento de Exceções:** IPs específicos de serviços de monitoramento externo ou parceiros no exterior devem ser adicionados manualmente à lista de permissão.

### 2.2. Bloqueio em Nível de Aplicação (Bootstrap PHP / Entry Points)

Como uma camada secundária de defesa, a validação geográfica pode ser implementada dentro da lógica da aplicação PHP, por meio do bootstrap compartilhado, guards de autenticação ou validações nos entrypoints sensíveis. Embora menos eficiente que o bloqueio no Nginx, esta camada oferece controle granular e melhor experiência de usuário. Ela permite, por exemplo, que páginas públicas permaneçam acessíveis globalmente, enquanto rotas críticas como `/home.php`, `/frame.php`, `/login.php` ou endpoints de API autenticados sejam estritamente trancados para IPs permitidos.

Esta implementação baseia-se na leitura de cabeçalhos HTTP repassados pelo proxy ou CDN, como `X-Forwarded-For` ou `CF-IPCountry` (caso utilize Cloudflare). O bootstrap ou guard intercepta a requisição antes que ela atinja a renderização da página ou a lógica da API, permitindo que o sistema retorne uma resposta `403 Forbidden` customizada com uma mensagem clara ao usuário, ou até mesmo registre a tentativa para auditoria de segurança futura.

- **Granularidade:** Proteção aplicada especificamente em rotas sensíveis (Admin e API).
- **Feedback:** Entrega de respostas JSON ou HTML explicando o motivo do bloqueio, diferentemente da queda abrupta de conexão do Nginx.
- **Auditoria:** Possibilidade de integrar com serviços de log para analisar padrões de bloqueio.

## 3. Diretrizes de Implementação

Esta seção detalha os procedimentos operacionais necessários para aplicar as estratégias definidas neste documento. Ela serve como um guia técnico para a equipe de DevOps ou Administradores de Sistema durante as fases de configuração e manutenção do ambiente. A aderência estrita a estes passos é vital para garantir que as medidas de segurança não impactem negativamente a disponibilidade legítima do sistema.

Recomenda-se que todas as alterações sejam testadas inicialmente em um ambiente de homologação (*Staging*) antes de serem aplicadas em produção. Além disso, antes de ativar o bloqueio geográfico, é essencial verificar se a base de dados de geolocalização de IPs está atualizada e se o IP atual do administrador está dentro da faixa permitida para evitar bloqueios acidentais (*lockouts*).

### Checklist SSL/TLS

- **Automação de Certificado:** Verificar se a opção "Let's Encrypt" está ativa e sincronizando com sucesso na aba de domínio do CloudPanel.
- **Forçar HTTPS:** Habilitar a opção "Force HTTPS" nas configurações do CloudPanel para criar a regra de redirecionamento 301 automática.
- **Configuração de Cabeçalho:** Inserir manualmente a diretiva HSTS na configuração do Vhost do Nginx: `add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;`.

### Configuração de Geo-blocking (Exemplo em PHP)

Para a camada de aplicação, o trecho de código a seguir ilustra como interceptar requisições e validar o país de origem em um bootstrap ou entrypoint compartilhado do projeto PHP.

```php
<?php
$country = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$protectedPaths = [
    '/home.php',
    '/frame.php',
    '/login.php',
    '/admin',
    '/api/secure',
];

$isProtectedPath = false;
foreach ($protectedPaths as $prefix) {
    if (str_starts_with($path, $prefix)) {
        $isProtectedPath = true;
        break;
    }
}

if ($isProtectedPath && $country !== '' && strtoupper($country) !== 'BR') {
    http_response_code(403);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'message' => 'Acesso negado: sua localização não está autorizada a acessar este recurso.',
    ]);
    exit;
}
```

## 4. FAQ

Esta seção aborda dúvidas recorrentes sobre o impacto e a manutenção das políticas de segurança descritas acima. O objetivo é fornecer respostas rápidas para as equipes de suporte técnico e desenvolvimento, esclarecendo dúvidas comuns que podem surgir durante a operação do sistema ou no diagnóstico de problemas de conectividade.

Estas respostas devem ser utilizadas como referência ao distinguir entre uma falha do sistema e um comportamento esperado das camadas de segurança. Por exemplo, um usuário incapaz de acessar o sistema enquanto está de férias no exterior é um comportamento esperado da política de Geo-blocking, e não um erro do servidor.

- **O Geo-blocking garante 100% de proteção contra ataques estrangeiros?**
    Não. Atacantes podem utilizar VPNs, Proxies ou máquinas comprometidas (zumbis) localizadas dentro do Brasil para contornar essa restrição. No entanto, a medida é eficaz para eliminar o "ruído de fundo" de ataques automatizados e varreduras em massa que se originam globalmente.

- **Qual o procedimento se um gestor precisar de acesso em viagem ao exterior?**
    O protocolo recomendado é o uso de uma VPN corporativa que tunele a conexão através de um IP brasileiro. Alternativamente, pode-se adicionar uma exceção temporária na *whitelist* do Nginx para o IP específico do hotel ou local onde o gestor se encontra.

- **Por que usar HSTS se já temos redirecionamento para HTTPS?**
    O redirecionamento padrão ainda permite que a primeiríssima requisição seja feita via HTTP, o que pode ser interceptado. O HSTS instrui o navegador a nunca sequer tentar a conexão HTTP, eliminando essa pequena janela de vulnerabilidade e protegendo contra ataques de *SSL Stripping*.

- **O Geo-blocking pode afetar a indexação de SEO (Googlebot)?**
    Sim, se aplicado indiscriminadamente. Se a aplicação possuir conteúdo público que precisa ser indexado, o bloqueio deve ser feito na camada de aplicação excluindo os *User-Agents* de bots de busca, ou configurado apenas nas rotas administrativas, deixando a *home page* pública acessível.
