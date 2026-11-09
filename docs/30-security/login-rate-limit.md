# Sessões e limite de tentativas de login

O login usa a tabela compartilhada `login_rate_limits`. Se ela não estiver
disponível, o login é recusado e o erro é registrado no log do PHP. A publicação
de produção verifica a leitura da tabela antes de sincronizar arquivos.

O endereço remoto é usado como IP do cliente por padrão. Quando houver proxy
reverso, configure `TRUSTED_PROXIES` no ambiente do servidor com os endereços
IP exatos dos proxies confiáveis, separados por vírgulas. O proxy de borda deve
remover os cabeçalhos encaminhados fornecidos pelo cliente e construir uma
cadeia `X-Forwarded-For` confiável. Endereços fora da lista não são aceitos como
proxies; intervalos CIDR não são suportados.

Sem essa configuração, um proxy compartilhado pode fazer vários usuários
dividirem o mesmo limite por IP. Verifique a topologia antes da publicação.

As sessões autenticadas são validadas no banco em cada requisição protegida;
contas inativas, tokens revogados e tokens expirados não são aceitos.
