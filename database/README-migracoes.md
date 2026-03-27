# Ordem de execução dos scripts SQL (módulo Pessoas e Instituições)

1. **partes-processos-partes.sql** – Cria `partes`, `processos_partes`, `processo_prioridades`. Requer tabela `processos`.
2. **alter-partes-campos-opcionais.sql** – Torna CPF, sexo e data_nascimento opcionais em `partes`.
3. **pessoas-instituicoes-vinculos.sql** – Cria `instituicoes_publicas`, `instituicoes_enderecos`, `instituicoes_telefones`, `instituicoes_setores`, `partes_telefones`, `partes_enderecos`, `partes_instituicoes`.
4. **alter-processos-partes-observacoes-ativo.sql** – Adiciona `observacoes` e `ativo` em `processos_partes`.
5. **alter-cejusc-guias-partes-drop-reus.sql** – Substitui `reu_id` por `parte_id` em cejusc_tarefas e guias; remove tabelas `reus` e `processoReus`. Executar apenas após migração de dados legados, se houver.
6. **alter-usuarios-cadastro-ativo.sql** – Adiciona `cadastro_ativo` em `usuarios` (0=pendente, 1=ativo). Obrigatório para regras de acesso: somente cadastro ativo acessa o site; estudantes ativos ao cadastrar; servidores pendentes até validação por admin/diretora.
7. **migrations/20260220_usuarios_avatar_slug.sql** – Adiciona `avatar_slug` em `usuarios` (preset de avatar sem upload; ver `docs/40-operations/avatars-presets-usuarios.md`).
8. **migrations/20260221_avatar_slug_legacy_to_initials.sql** – (Opcional) Normaliza `default`/vazio para `initials` (ver mesmo documento).
9. **migrations/20260327_ceapa_tarefas.sql** – Cria `ceapa_tarefas` (vínculo com `processos` e `partes`) para o módulo CEAPA.
10. **migrations/20260327_ceapa_tarefas_usuario_cadastro.sql** – Para bases que já criaram `ceapa_tarefas` sem usuário criador: adiciona `usuario_cadastro_id` + FK para `usuarios`.
