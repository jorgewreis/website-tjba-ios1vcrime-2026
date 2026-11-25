# Entrega de cobertura documental REI-73

## Bases e resultado

Fonte dos154 arquivos API:6b47f8249fd6653585dd025cd6d3427e2baa141d. Fonte das16 matrizes versionadas:abc3e0834692a923bcbe4afc5fd527b0bc375651. Não confundir base de código auditado com commit posterior que integra documentação.

CSV rei73-coverage.csv contém154 caminhos únicos:150 rotas,2 helpers e2 configurações; zero sem referência operacional. Cada linha registra blob Git do arquivo, matriz versionada, linha, SHA256 e ambos commits. Quinze matrizes usam docs/90-releases/rei-73-matriz-{familia}-20261125.md;12 instituições mantêm docs/90-releases/rei-73-matriz-bloqueios-2026-11-16.md. Quatro rotas processos-partes também aparecem nessa matriz antiga; referências secundárias registradas sem duplicar contagem.

Helpers:admin/_auth_admin_diretora_json.php e internal-list-export/_common.php. Configs:datajud/config.php e mni/config.php. Toast é rota de catálogo; email_hint é tombstone e consultar-processo MNI é rota suspensa.

## Diferenças da versão temporária anterior

-142 linhas tiveram referência primária substituída por caminho versionado;12 instituições conservaram caminho versionado.
-Nenhuma linha de referência mudou;154 referências conferidas nos blobs efetivamente versionados.
-Seis hashes mudaram apenas por whitespace de final de arquivo: avulsas-exports, cartas-recebidas, cejusc-oficios, editais, datajud-mni-consulta e processos. Os temporários atuais ainda correspondem aos hashes do snapshot anterior e os conteúdos são iguais após rstrip. Lista exata de hashes anteriores/novos em hash-changes.json.
-Hash atual usa bytes UTF-8 exatos de git show COMMIT:PATH, sem remover BOM, normalizar EOL ou aparar EOF. Nenhum hash depende da cópia física Windows. O antigo hash textual normalizava leitura de newline.
-CSV não referencia matriz temporária nem script externo. O método reproduzível segue nesta entrega como verify.py; os arquivos podem ser movidos juntos.

## Reprodução somente leitura

Pré-requisito:Python3 e Git; repositório local contendo os dois commits. Não executa código PHP, aplicações ou banco. A partir deste diretório:

```powershell
python verify.py D:/Projects/Clients/website-tjba-ios1vcrime-2026
```

Ou fornecer qualquer checkout com os commits e o caminho do CSV como segundo argumento. Não faz fetch nem exige rede. O verificador compara o conjunto exato de caminhos de git ls-tree, blobs de origem, hashes das matrizes, nomes nas154 linhas e as4 referências secundárias; também confere contagens por tipo. Seleção semântica de seção/família foi feita na consolidação original e revista nas matrizes; o script não substitui revisão do conteúdo.

Execução local nesta entrega: DOCUMENTARY PASS,154 caminhos,16 matrizes,150/2/2,0pendentes. Essa é conferência documental, não teste funcional. Inventário histórico rei-73-inventario-a506e451.csv e índices agregados não foram somados como nova cobertura operacional.

## Limites

Nenhuma alteração de repositório/Linear/PR, endpoint, PHP, SQL, Docker ou serviço externo. Zero testes funcionais nesta entrega. Cobertura por arquivo significa existência de descrição operacional, não aprovação dos controles, aceitação das issues, validade jurídica de prazos, comportamento MySQL/UI ou implantação. Evidências históricas nas matrizes conservam seus limites e bases. A alteração de documentos em commits futuros requer recalcular hashes/linhas.
