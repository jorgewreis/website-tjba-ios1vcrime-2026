# REI-168 — candidata de autorização HTTP sintética

## Escopo

A fixture `rei168-permissao-http-sintetica.test.py` prepara cópia temporária apenas dos endpoints de listagem e conclusão, `Security`, `ApiAccessGuard` e avaliadores de perfil. Substitui a conexão por PDO SQLite `:memory:` e o serviço de cartas por stub que registra chamadas sintéticas. As sessões e os perfis são gerados pela fixture; não há login real.

O teste proposto cobre leitura e conclusão para sessões autorizadas e indevidas, anônimo, token inválido, método e origem. Asserções JSON e rastreamento do stub avaliam se recusas chegam ao serviço. O stub não executa consulta de cartas, não valida o significado de filtros e não prova integridade ou ausência de escrita em banco de negócio. Não substitui ensaios MySQL, login, proxy, produção ou navegador.

## Isolamento e limites

A cópia usa allowlist e não copia configuração do projeto, `.env`, dados ou runners de autenticação. O PHP é verificado e a conexão substituída cria somente SQLite em memória. O servidor proposto escuta em `127.0.0.1` e pede porta efêmera ao kernel; um marcador confirma a resposta de readiness, e a verificação do listener por PID precede cada requisição. O cliente desativa proxies e redirecionamentos. Limpeza exige término confirmado do processo e revalidação do diretório próprio; estado incerto preserva o diretório.

Em Linux, a identificação do socket depende de `/proc/<pid>/fd` e `/proc/<pid>/net/tcp`; em Windows, depende de `Get-NetTCPConnection`. Conforme registro da revisão independente, a fixture foi executada em Windows com o caminho real do PHP e terminou com código 0. Isso não comprova o comportamento em Linux. Ali, o suporte a `-S 127.0.0.1:0`, o formato da mensagem e o acesso a `/proc` continuam por confirmar; em caso de falha, a fixture recusa antes da primeira requisição. Links simbólicos ou reparse points no caminho do PHP ou do temporário podem causar recusa, inclusive em Linux. O host, kernel e executáveis são assumidos confiáveis; existe janela TOCTOU entre validação de PID/socket ou caminho e seu uso.

A revisão independente cobriu a candidata; o resultado observado não elimina as limitações acima.
