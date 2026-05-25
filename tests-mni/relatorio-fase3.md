# Relatório Fase 3 — Comparativo MNI x DataJud

> Preencher após rodar `php tests-mni/03-comparar-datajud.php "CNJ1,CNJ2,CNJ3"`.

## 1. CNJs comparados

| # | CNJ | Tipo de processo | Observação |
|---|-----|-------------------|------------|
| 1 |     |                   |            |
| 2 |     |                   |            |
| 3 |     |                   |            |

## 2. Resumo bruto (extraído do `comparativo-*.json`)

| CNJ | classe (DJ) | classe (MNI) | classe_igual | assuntos DJ x MNI | polo_passivo DJ x MNI | movimentos DJ x MNI | MNI elapsed_ms |
|-----|-------------|--------------|--------------|--------------------|------------------------|----------------------|------------------|
|     |             |              |              |                    |                        |                      |                  |
|     |             |              |              |                    |                        |                      |                  |
|     |             |              |              |                    |                        |                      |                  |

## 3. Campos exclusivos do MNI observados

(marcar X conforme aparecerem)

- [ ] `mni.partes.ativo`
- [ ] `mni.partes.passivo` (detalhado: tipoPessoa, numeroDocumento)
- [ ] `mni.partes.outros`
- [ ] `mni.orgaoJulgador` (com `codigo` e `nome`)
- [ ] `mni.dataAjuizamento`
- [ ] `mni.classeProcessual` (com codigoNacional + descricao)
- [ ] `mni.assuntosHierarquicos`
- [ ] `mni.documentos` (metadados — id, tipo, descricao, mimetype)
- [ ] `mni.totalMovimentos` (qtd absoluta)

## 4. Decisão: o que persistir em BD após retorno do MNI

| Campo MNI | Persistir? | Tabela alvo / observação |
|-----------|-----------|--------------------------|
| classe (codigoNacional + descricao) | Sim/Não | já coberto por `ProcessoClasseSyncService` (DataJud); MNI vira fallback |
| assuntos (codigoNacional + descricao) | Sim/Não | já coberto por `ProcessoAssuntoSyncService`; MNI pode trazer mais |
| polo ativo/passivo detalhado | Sim/Não | nova tabela? — definir |
| órgão julgador | Sim/Não | atualizar `processos.orgao_julgador_*`? |
| movimentos | Sim/Não | dependente de novas tabelas; **provavelmente fora do MVP** |
| documentos (apenas metadados) | Sim/Não | só listar na UI, sem baixar conteúdo |

## 5. Decisão: o que expor no front (via /api/mni)

| Bloco | Mostrar na UI? | Onde |
|-------|----------------|------|
| classe MNI | Como segunda fonte (refresh do DataJud) | input `filter-classe` (mesmo lugar) |
| assuntos MNI | Substituir lista do BD quando vier mais completa | `#filter-assuntos` da create |
| polo passivo detalhado | Bloco novo? | a definir |
| órgão julgador | Bloco novo (read-only) | a definir |
| movimentos | Sim, painel colapsável | nova área na tela |
| documentos | Lista de metadados | nova área na tela |

## 6. Atenções / próximos passos

- (preencher com aprendizados — latência média, erros de sigilo, divergências surpreendentes etc.)

## 7. Gating para Fase 4

A Fase 4 (endpoint HTTP) só deve avançar depois desta seção estar respondida:

- [ ] Latência média do MNI mensurada (`mni.elapsed_ms`).
- [ ] Decisão tomada sobre persistência (item 4) — mesmo que seja "só log por enquanto".
- [ ] Decisão tomada sobre exposição no front (item 5).
- [ ] Casos de sigilo/inexistente confirmaram tradução de erro coerente.
