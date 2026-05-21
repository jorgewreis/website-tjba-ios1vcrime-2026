![Badge](https://img.shields.io/badge/MINOR-blue?style=for-the-badge)

**Data de Lançamento:** 21 de maio de 2026

# V. 1.45

> refactor: streamline subtask management and enhance active order handling

## Nível de Impacto

🟡 **MEDIO**

## Alterações

- **refactor: streamline subtask management and enhance active order handling** `[edf6df4e]`
- **remove(consulta-cep):** página dedicada, API `/api/cep/`, busca ViaCEP em formulários, menu, regras de acesso `consultas_cep`, toasts e regra de produtividade `PRG-E4AED8332C544E3AE27FF8535E` (migration `database/migrations/20260521_remove_consulta_cep.sql`)
- **remove(cejusc-ceapa):** submódulos CEJUSC e CEAPA (UI, APIs, serviços, integração em Medidas Protetivas, produtividade, dashboard e tabelas; migration `database/migrations/20260521_drop_cejusc_ceapa.sql`)
- **remove(produtividade-automatica):** exclusão de registro automático (cartório, sala-mídias, consultas/pauta), helpers e configs; listagem só cadastro manual + lançamentos + export (sem ranking, resumo nem projeção); migration `database/migrations/20260521_disable_produtividade_automatica_regras.sql`

---

*Documento gerado automaticamente pelo sistema de versionamento.*
