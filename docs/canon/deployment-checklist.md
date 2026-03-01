---
id: deployment-checklist
title: "Checklist de Acesso e Deploy"
version: "1.0"
date: "2026-02-21"
---

# Checklist de Acesso e Deploy

Use este checklist para guardar passos e confirmar que os acessos necessários estão disponíveis antes de iniciar desenvolvimento e deploys automáticos.

1. Credenciais e acessos
   - [ ] Acesso Git (SSH) com chave registrada no usuário de deploy.
   - [ ] Acesso ao servidor dev (SSH) — usuário e chave/credentials.
   - [ ] Acesso ao banco de dados (host, db, user) — credencial armazenada em cofre.
   - [ ] Variáveis de ambiente definidas em `.env` (não commitar em repositório).

2. Secrets e armazenamento seguro
   - [ ] Armazenar segredos no cofre (Vault / GitHub Secrets / Azure Key Vault).
   - [ ] Confirmar rotação e leitores autorizados.

3. Deploy e CI
   - [ ] Verificar workflow referente a deploy em `develop` (GitHub Actions / CI).
   - [ ] Validar que deploy automático aponta para `dev.ios1vcrime.com.br`.
   - [ ] Confirmar rollback procedure e backups.

4. Acesso de emergência
   - [ ] Gerar branch `reserve` em caso de conflito crítico.
   - [ ] Documentar contatos de operações para acesso de emergência.

Instruções:
- Nunca colocar credenciais em arquivos versionados. Use `.env` localmente e cofre remoto para CI.
- Antes de mover/alterar configurações sensíveis no servidor, fazer backup completo.

