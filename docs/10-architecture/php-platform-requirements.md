# Requisitos da plataforma PHP

Este inventário separa dependências comprovadas no código e no lock de requisitos do runtime que ainda precisam ser medidos no ambiente de destino.

## Extensões declaradas

| Requisito | Fluxo/evidência | Condição |
| --- | --- | --- |
| `ext-pdo` e `ext-pdo_mysql` | Conexão persistente com MySQL em `app/helpers/Conection.php` | Obrigatórias para as APIs e páginas que consultam o banco |
| `ext-curl` | DataJud (`app/services/DataJudService.php`) e cliente MNI (`app/Mni/MniClient.php`) | Obrigatória para integrações HTTP que usam esses clientes |
| `ext-mbstring` | Cadastro de estudantes e serviços de Cejusc, Cartas e Guias; também requerida por pacotes Composer | Obrigatória nos fluxos que chamam `mb_*` e no Dompdf |
| `ext-iconv` | `app/services/Guias.php` | Obrigatória para os fluxos de Guias que usam conversão de charset |
| `ext-simplexml` | Parser MNI (`app/Mni/MniResponseParser.php`) | Obrigatória para processar respostas XML MNI |
| `ext-dom` | Requisito transitivo de `dompdf/dompdf` no `composer.lock` | Exigida pelo fluxo de geração de PDF com Dompdf |

As seis extensões de aplicação diretamente observadas estão em `composer.json` e `composer.lock`. `ext-dom` permanece exigida pela dependência Dompdf no lock. GD, fileinfo e outras extensões vistas em dependências opcionais ou fluxos condicionais não foram promovidas a requisito global sem comprovar uso obrigatório em produção.

## Versão PHP e ambientes

O workflow `.github/workflows/php-syntax-check.yml` usa PHP 8.2 para lint. Isso comprova somente o baseline do CI; não demonstra a menor versão aceita pelo código nem as versões/módulos ativos no CLI e no PHP-FPM do ambiente. Por isso, este inventário não declara ainda uma restrição de versão PHP.

Antes de fixar a restrição, validar compatibilidade do código e dependências com a versão mínima e medir separadamente CLI e PHP-FPM em DEV e PROD. Registrar somente versão e lista de extensões, sem `phpinfo()`, variáveis de ambiente ou configuração que possa revelar segredos.

Na inspeção read-only de 16/11/2026, o alias DEV do Gateway apontou para o host `srv1053508`, com PHP CLI 8.4.22 e as extensões `curl`, `dom`, `iconv`, `mbstring`, `PDO`, `pdo_mysql` e `SimpleXML` disponíveis. Há serviços PHP-FPM 8.0 a 8.5 ativos no mesmo host; a configuração do pool/vhost que atende este sistema não pôde ser identificada com a permissão disponível. Portanto, ainda não está comprovado qual versão ou conjunto de extensões o PHP-FPM efetivamente usa para este site. A ligação entre o segredo `DEPLOY_HOST` do DEV workflow e esse alias também permanece sem comprovação direta.

A origem DNS do domínio de produção coincide com o IP público do alias Gateway; isso é indício de que ambos chegam ao mesmo servidor, mas não prova qual pool PHP-FPM atende o site nem qual host o segredo `DEPLOY_HOST` do workflow de produção usa. A versão e as extensões do PHP CLI e PHP-FPM de produção seguem pendentes. Não definir a versão mínima do PHP até concluir essas verificações e validar a compatibilidade do código e das dependências.
