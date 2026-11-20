# Draft - Release Notes (Minor)

Este arquivo acumula mudanças incrementais para a próxima release MINOR.

## Entradas Pendentes
- **fix(REI-88): make carta creation atomic** `[cc3b6220]`
- **test(REI-88): verify nested transaction rollback** `[3b01d3e9]`
- **test: cover pauta rollback on isolated MySQL REI-88** `[ae959e86]`
- **fix(REI-78): block duplicate carta submissions** `[c7de2cb2]`
- **fix(REI-78): make carta creation idempotent** `[2a30b969]`
- **test(REI-78): cover concurrent idempotent retries** `[10beeba0]`
- **merge: sync Carta atomicity with current main** `[aea3e451]`
- **fix(dashboard): reconcile guide queue contract (REI-167)** `[4dbda414]`
- **test(REI-167): execute guide dashboard queue on isolated MySQL**

- **ci(deploy): add temporary Hostinger SSH allowlist** `[16c5f76d]`
- **ci(deploy): permitir runner temporário no UFW** `[bec23f45]`
- **ci(deploy): sincronizar firewall temporário Hostinger** `[c85a9638]`
- **ci(deploy): preservar acesso WireGuard no firewall temporário** `[f0a15d4d]`
- **fix(footer): show last successful deploy timestamp** `[7dd86e73]`
- **fix(footer): force credit metadata onto separate lines** `[a082afda]`
- **fix(ci): normalize WireGuard deployment key** `[9a74caa2]`
- **chore(sync): merge latest main into deployment key fix** `[c35478b0]`
- **fix(ci): reveal sanitized WireGuard validation error** `[de7e73d2]`
- **fix(ci): run WireGuard validation with explicit sudo** `[9654ab41]`
- **fix(ci): validate installed WireGuard config only** `[ae8c7d43]`
