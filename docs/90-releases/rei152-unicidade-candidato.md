# REI-152: pré-condições da unicidade de documentos

O candidato de transação por pessoa não serializa duas pessoas distintas que
recebem o mesmo documento. A verificação de existência antes do `INSERT` é
insuficiente sob concorrência. O arquivo `database/partes-documentos.sql`
referenciado por `scripts/migrate-partes-documentos.php` não está nesta cópia
versionada; o esquema e os índices efetivos precisam ser conferidos antes de
escolher uma migration.

Antes de impor unicidade, verificar o motor e a collation reais, todos os
escritores (inclusive versões antigas e rotinas de migração), formato de tipo e
número, duplicatas já existentes e divergência com `partes.cpf`. A rotina pura
`rei152AuditarDocumentos` conta algumas dessas anomalias em arrays fornecidos
pelo chamador e não imprime documentos; ela também conta CPF legado inválido
ou não canônico e colisões normalizadas mesmo em documentos inválidos, inclusive
colisões de números não nulos que normalizam para vazio. O tratamento de NULL,
strings vazias e collation em índices depende do esquema real. Não consulta o banco e não verifica
collation, órfãos, concorrência, outros escritores ou isolamento. CPF é
normalizado para dígitos; outros documentos têm espaços removidos pela regra
atual de aplicação. A equivalência da collation do banco pode ser diferente.

O desenho candidato é reconciliação auditada dos legados, normalização
compatível para todos os escritores e restrições de unicidade no banco para
tipo/número e pessoa/tipo, desde que a regra de negócio e os dados existentes
confirmem essas invariantes. É necessária implantação expand-first que impeça
gravações antigas incompatíveis durante validação e criação das constraints.
Não existe migration executável aprovada neste candidato: não é seguro inventar
um esquema ou aplicar DDL em dados potencialmente divergentes.

`PartesRepository::garantirDocumentoCpf` captura exceções depois da inserção de
uma parte. Propagar essa falha sem antes envolver a operação inteira em
transação pode deixar gravação parcial. A correção deve incluir todos os
chamadores e falhas, sem descartar a coluna legada ou apagar dados duplicados.

Antes de produção, são necessários inventário dos índices e engines reais,
contagens seguras sem expor documentos, plano para conflitos e backup com
restauração comprovada, teste de DDL/rollback e corrida em MySQL descartável,
e validação de coexistência/rollback das versões. O DDL MySQL pode efetuar
commit implícito; não assumir reversão transacional. Nenhuma dessas provas foi
obtida por este candidato.
