# REI-73 — complemento de evidências em 25/11/2026

## Escopo

Preparado sobre main `a868a6a7aeec55fefb190b566ed8d6da65b31ae9`, conferida por `git ls-remote origin refs/heads/main`. Complementa a [consolidação histórica](rei-73-evidencias-consolidadas-20261125.md), publicada pela [PR #501](https://github.com/jorgewreis/website-tjba-ios1vcrime-2026/pull/501). Não substitui matrizes, CSV, resultados anteriores ou suas bases. Consulta documental não é reexecução nem aprovação integral de artefatos.

Foram consultados 20 comentários recentes da REI-73 e artefatos locais. Não foi encontrada reivindicação explícita deste novo documento; isso não comprova ausência de sessão externa. Preservar candidatas e coordenar qualquer implementação com seus responsáveis. Nenhum teste, Docker, banco ou endpoint foi executado para esta consolidação.

## Evidência incremental por operação

### Pauta — transições de status / REI-131

[Registro e limites](https://linear.app/reis-developer/issue/REI-73#comment-f0772ab2-c23e-4ee9-a7bb-0173a7497431): fonte `a868a6a7aeec55fefb190b566ed8d6da65b31ae9`, run `86316fc372d74119b89cc289ae89f500`, handlers `public/api/sala-audiencias-pauta/update.php` e `set-status-painel.php`. Diagnóstico HTTP/MySQL sintético: 14 casos, dez controles aprovados e quatro divergências persistidas. Update geral aceitou início em dia incorreto, início com outra audiência em andamento e reativação com outra ativa no mesmo processo; o painel também aceitou esta reativação. Painel recusou dia incorreto e outra audiência em andamento; controles de sessão/perfil recusaram.

Sessões e schema sintéticos; snapshots de 13 tabelas. Os positivos de encerramento verificaram presença dos horários, mas usaram início futuro: não comprovam coerência cronológica. Não há prova concorrente, login ou produção. A REI-131 mantém candidatas experimentais de concorrência; este diagnóstico sequencial não as substitui.

### Pauta — propriedade dos vínculos / REI-69

[Reprodução](https://linear.app/reis-developer/issue/REI-73#comment-7a18e2f6-8412-4d0a-826d-0c0ba5b346ce) e [revisão independente](https://linear.app/reis-developer/issue/REI-73#comment-8605fab3-e761-495d-a5cd-dffbe10ed5b7): fonte `0a2f21acda267b58a72dd1a472a4cc75f99b33e6`, run `1dda198b61184ab78c4ef2af86088b63`, `partes-create.php`/`partes-update.php` da Pauta. Dois controles positivos e seis negativos aprovados; seis pedidos com vínculo de outro processo e/ou pessoa retornaram 201/200 e persistiram referência incompatível. Resultado FAIL confirma achados, não correção.

Snapshots de 13 tabelas; sessões sintéticas e schema sem FKs. Não comprova login, sigilo, concorrência ou produção. Preservar candidata #438. O achado trata propriedade de IDs referenciados e não revoga a política documentada de leitura interna entre setores.

### Atas — download, autenticação e efeitos do GET / REI-87 e REI-99

[Registro](https://linear.app/reis-developer/issue/REI-73#comment-f3fab04d-9963-499b-ade6-fa69ee8c41d3): fonte `0a2f21acda267b58a72dd1a472a4cc75f99b33e6`, run `edaabf9ba64b4de2b23543f86be3afd3`, 14 casos HTTP/MySQL, resultado FAIL. Download interno entregou PDF sintético a sessões revogada/expirada/inativa; sete consultas internas alteraram ata vencida de outra pauta. Estudante elegível recebeu 403, sem contorno da ACL.

Snapshots de duas tabelas e dois arquivos. Sessões pré-fabricadas, sem login, concorrência ou produção. Não cobre política de expiração NULL, falhas de I/O da manutenção ou integralidade da elegibilidade. Reconciliar com REI-87/99 e candidatas #439/#448 antes de alterar produto; esta prova não demonstra que essas candidatas passaram ou foram integradas.

### Login externo — ESTUDANTE e CONSULTOR

[Registro](https://linear.app/reis-developer/issue/REI-73#comment-2e398321-47b1-4769-83a6-95f87b18f157): fonte `9e7ea7c51aff65b89ee2781edf59f3e7842ac0a8`, laboratório `rei73-external-login-browser-20261125`, tentativa 03. Login real pelo iframe de `/index.php`, perfis Chromium separados, sete checks por papel e runner exit 0. Senha inválida mostrou erro e permitiu recuperação; login válido comprovou identidade/home, menus, dashboard com conteúdo por papel e duas recusas diretas 403 por perfil. Estudante recebeu 403 na busca institucional; consultor recebeu somente instituição ativa sintética.

Sessão/API/backend reais no laboratório, preenchimento programático e acesso direto por fetch. Sem capturas, teclado, navegação visual das páginas estudantis, PDF ou produção. A linha de erro SMTP pertence a cenário de falha deliberada conforme registro do executor, não prova falha operacional de entrega. Tentativa 01 falhou no pool Docker e 02 em browser-start; ambas preservadas. Não transferir PASS automaticamente ao SHA do merge ou à main posterior.

### Polling estudantil e achado visual REI-328

[Polling](https://linear.app/reis-developer/issue/REI-73#comment-4a8f053a-68b0-4e8d-9812-36549873f6ed), fonte `63eb1da4a7227e94cece467ef2d57fbd8020e416`, laboratório `rei73-estudante-polling-run03-20261125`: Chromium 159, HTML/JS reais e respostas interceptadas. Dois cards removeram links/marcadores sintéticos ao receber indisponibilidade; foco foi ao título; HTTP500 manteve a ocultação dos dados; recuperação provocou uma recarga e restaurou ambos. Revisão independente conferiu ausência de quatro marcadores nos DOMs observados. Tentativas 01/02 de preparação/seletor preservadas.

Não prova login, backend, ACL, persistência, sigilo real ou memória. Partial de processo simplificada e fontes/ícones bloqueados limitam avaliação visual. A funcionalidade de polling aprovada não significa aprovação visual.

A [REI-328](https://linear.app/reis-developer/issue/REI-328) registra prova adicional distinta, em `rei73-estudante-polling-realpartial-20261125`, mesma fonte, com partial de processo e CSS reais. Viewport 390 px: bloco de processo 450 px, scrollWidth 420 e sobreposição do link de audiência com inscrição. Revisores conferiram retângulos e captura `evidence/05-mobile.png`; desktop não apresentou a mesma sobreposição aparente nessa rodada. Não confundir métricas dessa prova com o laboratório anterior de partial simplificada. Fontes externas bloqueadas; reprodução autenticada completa, correção, teclado e validação de larguras/estados continuam pendentes na issue.

## Artefatos e comandos

Raiz local dos laboratórios: `C:/Users/jorge/AppData/Local/Temp/`. Caminhos locais não são anexos públicos nem garantem retenção permanente. READMEs históricos de alguns laboratórios ainda dizem PREPARED; resultados acima são atribuídos a JSON/transcripts e comentários posteriores, não a esses títulos.

| Laboratório | Artefato principal | SHA-256 registrado |
|---|---|---|
| rei131-status-http-lab-20261125 | evidence-86316fc372d74119b89cc289ae89f500.json; reviewed-execution.txt | JSON: cdf68f9053e4ba6b6a72a8456278a90d4559f87f4155c88a662b50358a9bc9e0; transcript: 504088bc10bd8fbe3856a191e80ddfd59546596e01ee5dabb8480a7e9eaee164 |
| rei73-pauta-links-http-lab-20261125 | evidence-1dda198b61184ab78c4ef2af86088b63.json | 84031152fbcce2eb6de4f57e35d0d0039dfe157259f632ee0c20e3eb81471580 |
| rei73-ata-http-lab-20261125 | evidence-edaabf9ba64b4de2b23543f86be3afd3.json | 3898e95d0b58900d1bbff09efb39d1af217d9655a7b92ffbfe071b6ed03b1cf8 |
| rei73-external-login-browser-20261125 | laboratory/reviewed-execution-03.txt | 8e97f71728746ac47d64dd442e6bfa7fc4ecf9c92f7c4451b397a6b526e8fb48 |
| rei73-estudante-polling-run03-20261125 | evidence/evidence.json | Não transcrito neste complemento |
| rei73-estudante-polling-realpartial-20261125 | evidence/evidence.json; evidence/05-mobile.png | Não transcrito neste complemento |

Comandos efetivos extraídos dos transcripts, reduzidos à invocação (captura Start-Transcript/Stop-Transcript omitida):

```powershell
& "$env:TEMP/rei131-status-http-lab-20261125/runner.ps1"
& "$env:TEMP/rei73-pauta-links-http-lab-20261125/runner.ps1"
& "$env:TEMP/rei73-external-login-browser-20261125/tests/auth/run-isolated-auth-e2e.ps1" -Browser
```

Para atas não foi localizado transcript da invocação nesta leitura; `runner.ps1` é o entrypoint do laboratório, sem alegação de reconstrução integral do comando histórico. O README do polling documenta `python -I ./prepare.py`, configuração local de `REI73_PLAYWRIGHT_MODULE` e `node ./run.cjs`; não foi localizado transcript para certificar a invocação histórica exata. Esses comandos são referências documentais, não autorização de reexecução; requerem revisão dos gates, dependências e exclusividade antes de qualquer nova rodada.

## Isolamento, lacunas e continuidade

Consultas posteriores por label/nome não encontraram recursos dos runs HTTP/login, conforme fontes citadas. IDs e inspeções completas não foram preservados nos respectivos artefatos; ausência posterior não reconstrói a topologia histórica nem comprova ausência em todo instante. No login, o horário exato da consulta independente pós-run03 não foi registrado. Controller PASS sozinho não demonstra limpeza.

Os recortes reduzem lacunas específicas de status, vínculos, atas e perfis externos; não encerram REI-73. Continuam necessários mapeamento proporcional das demais operações/perfis, concorrência e recuperação não cobertas, UI completa, configurações efetivas/infraestrutura e limites do ambiente implantado. Infraestrutura indisponível pode ser registrada como limite explícito de evidência, com impacto e justificativa; este complemento não exige acesso produtivo nem uso de dados reais para fechar essas lacunas. Defeitos reproduzidos devem permanecer associados às issues acionáveis; concluir auditoria e corrigir cada defeito são etapas distintas. A [reconciliação de estado](https://linear.app/reis-developer/issue/REI-73#comment-c98a133a-b3a1-41e3-a948-9aa9d12f2b01) mantém a auditoria em andamento após publicações parciais.