# Estrutura das tabelas Partes, Telefones e Vínculos (para Consulta Partes)

Este documento descreve **exatamente** o que o código da aplicação espera que exista no banco para que a lista de Consulta Partes exiba **telefones** e **instituições vinculadas**.

---

## 1. Tabela `partes` (pessoas)

Onde a listagem de Consulta Partes busca os nomes.

| Coluna           | Uso na listagem |
|------------------|------------------|
| `id`             | **Obrigatório.** É o identificador da parte. Usado para buscar telefones e vínculos (coluna `parte_id` nas outras tabelas). |
| `cpf`            | Exibido na lista (formatado). |
| `nome_completo`  | Exibido na lista. |

**Consulta para listar partes (exemplo):**
```sql
SELECT id, cpf, nome_completo FROM partes ORDER BY nome_completo LIMIT 10;
```

---

## 2. Tabela `partes_telefones` (telefones da parte)

Cada linha é um telefone vinculado a uma parte. A ligação é pelo **`parte_id`** = `partes.id`.

| Coluna       | Obrigatório | Uso na listagem |
|--------------|-------------|------------------|
| `id`         | Sim (PK)    | Identificador do telefone. |
| `parte_id`   | Sim         | **Deve ser igual ao `id` da tabela `partes`** para o telefone aparecer na lista daquela pessoa. |
| `tipo`       | Não         | Ex.: "Celular", "Comercial". Exibido como "tipo: numero". |
| `numero`     | Sim         | Número exibido na lista. |
| `whatsapp`   | Não         | Não usado na listagem. |
| `principal`  | Não         | Ordenação. |
| `ativo`      | Não         | Não filtrado na listagem. |
| `created_at` | -           | Ordenação. |
| `updated_at` | -           | - |

**Consultas para conferir no BD:**

- Listar todos os telefones com o nome da parte (para ver se `parte_id` bate com `partes.id`):
```sql
SELECT pt.id, pt.parte_id, pt.tipo, pt.numero, p.nome_completo
FROM partes_telefones pt
LEFT JOIN partes p ON p.id = pt.parte_id
ORDER BY p.nome_completo, pt.numero;
```

- Ver se há telefones para uma parte específica (troque `PARTE-ID` pelo `id` de uma parte que aparece na Consulta Partes):
```sql
SELECT * FROM partes_telefones WHERE parte_id = 'PARTE-ID';
```

- Ver quais partes têm telefone cadastrado:
```sql
SELECT p.id, p.nome_completo, COUNT(pt.id) AS qtd_telefones
FROM partes p
LEFT JOIN partes_telefones pt ON pt.parte_id = p.id
GROUP BY p.id, p.nome_completo
HAVING qtd_telefones > 0;
```

---

## 3. Tabela `partes_instituicoes` (vínculo parte–instituição)

Cada linha é um vínculo de uma parte com uma instituição. A ligação é pelo **`parte_id`** = `partes.id`. O nome da instituição vem do JOIN com `instituicoes_publicas`.

| Coluna           | Obrigatório | Uso na listagem |
|------------------|-------------|------------------|
| `id`             | Sim (PK)    | Identificador do vínculo. |
| `parte_id`       | Sim         | **Deve ser igual ao `id` da tabela `partes`** para o vínculo aparecer na lista daquela pessoa. |
| `instituicao_id` | Sim         | FK para `instituicoes_publicas.id`. |
| `setor_id`       | Não         | Não exibido na lista. |
| `profissao`      | Não         | Não exibido na lista. |
| `cargo_funcao`   | Não         | **Exibido** na lista (ex.: "Investigador"). |
| `data_inclusao`  | -           | Ordenação. |
| `data_inicio`    | -           | - |
| `data_fim`       | -           | - |
| `ativo`          | Não         | **Filtro:** a listagem usa só vínculos com `ativo = 1`. |
| `observacoes`    | Não         | Não exibido na lista. |
| `created_at`     | -           | - |
| `updated_at`     | -           | - |

A listagem faz **LEFT JOIN** com `instituicoes_publicas` para obter:
- `instituicao_nome` → coluna `nome` de `instituicoes_publicas`
- `instituicao_sigla` → coluna `nome_fantasia` de `instituicoes_publicas`

**Consultas para conferir no BD:**

- Listar vínculos com nome da parte e da instituição (para ver se `parte_id` e `instituicao_id` batem):
```sql
SELECT pi.id, pi.parte_id, pi.instituicao_id, pi.ativo, pi.cargo_funcao,
       p.nome_completo AS parte_nome,
       i.nome AS instituicao_nome, i.nome_fantasia AS instituicao_sigla
FROM partes_instituicoes pi
LEFT JOIN partes p ON p.id = pi.parte_id
LEFT JOIN instituicoes_publicas i ON i.id = pi.instituicao_id
ORDER BY p.nome_completo, i.nome;
```

- Ver vínculos ativos de uma parte específica (troque `PARTE-ID` pelo `id` da parte):
```sql
SELECT pi.*, i.nome AS instituicao_nome, i.nome_fantasia AS instituicao_sigla
FROM partes_instituicoes pi
LEFT JOIN instituicoes_publicas i ON i.id = pi.instituicao_id
WHERE pi.parte_id = 'PARTE-ID' AND pi.ativo = 1;
```

- Ver quais partes têm pelo menos um vínculo ativo:
```sql
SELECT p.id, p.nome_completo, COUNT(pi.id) AS qtd_vinculos
FROM partes p
LEFT JOIN partes_instituicoes pi ON pi.parte_id = p.id AND pi.ativo = 1
GROUP BY p.id, p.nome_completo
HAVING qtd_vinculos > 0;
```

---

## 4. O que conferir se a lista não mostrar telefones/instituições

1. **Nome da tabela**  
   No BD devem existir exatamente: `partes`, `partes_telefones`, `partes_instituicoes`.  
   Se no seu BD os nomes forem outros (ex.: `parte_telefones`, `pessoas_instituicoes`), o código dos repositórios não vai achar as tabelas.

2. **Nome da coluna de vínculo**  
   Nas tabelas de telefones e vínculos a coluna que liga à parte deve se chamar **`parte_id`** (e conter o valor de `partes.id`).  
   Se no seu BD for `pessoa_id` ou `partes_id`, as consultas dos repositórios não vão retornar dados.

3. **Valores em `parte_id`**  
   Em `partes_telefones` e `partes_instituicoes`, cada valor de `parte_id` deve ser **idêntico** ao `id` de uma linha em `partes` (mesmo tipo e formato, ex.: `PRT-xxxxx`).  
   Use as consultas da seção 2 e 3 para comparar `partes.id` com `partes_telefones.parte_id` e `partes_instituicoes.parte_id`.

4. **Vínculos ativos**  
   A listagem de instituições usa apenas vínculos com **`ativo = 1`**.  
   Confira com a última query da seção 3.

Depois de conferir isso no BD, se ainda não aparecer nada na tela, o próximo passo é garantir que a listagem está usando o mesmo `id` retornado por `partes` (por exemplo, imprimindo temporariamente o `id` e o resultado das consultas de telefones e instituições no PHP).
