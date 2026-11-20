# Draft - Release Notes (Minor)

Este arquivo acumula mudanças incrementais para a próxima release MINOR.

## Entradas Pendentes
- **fix(REI-76): protect DataJud synchronization** `[e7c839ea]`
- **fix(REI-72,REI-76,REI-153): validate DataJud subject sync** `[f0aca0f2]`
- **test(DataJud): align HTTP fixtures with verified source contract** `[989c56b8]`
- **fix(DataJud): allow subject sync without class** `[085e773f]`
- **fix(DataJud): reject malformed class metadata** `[9cc8c877]`
- **fix(REI-153): reconcile verified empty DataJud subjects** `[9636bb9e]`
- **merge: sync DataJud process sync with current main** `[7cf523db]`
- **fix(REI-72): restrict subject mutations by sector** `[7bbcef09]`
- **fix(REI-120): make monitoring transitions atomic** `[49415bce]`
- **fix(REI-69): restrict process-part access by sector** `[79cc9a31]`
- **feat(rei-69): add audited responsible sector assignment** `[46777715]`
- **fix(REI-88): make carta creation atomic** `[cc3b6220]`
- **test(REI-88): verify nested transaction rollback** `[3b01d3e9]`
- **test: cover pauta rollback on isolated MySQL REI-88** `[ae959e86]`
- **fix(REI-78): block duplicate carta submissions** `[c7de2cb2]`
- **fix(REI-78): make carta creation idempotent** `[2a30b969]`
- **test(REI-78): cover concurrent idempotent retries** `[10beeba0]`
- **merge: sync Carta atomicity with current main** `[aea3e451]`
- **docs(REI-69): document sector migration deploy** `[e0b03ee9]`

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
