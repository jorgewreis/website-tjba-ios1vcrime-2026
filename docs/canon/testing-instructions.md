---
id: testing-instructions
title: "Instruções de Testes e QA"
version: "1.0"
date: "2026-02-21"
---

# Testes e QA (instruções rápidas)

Passos para testes manuais (smoke tests) no ambiente dev:

1. Verificar deploy recente em dev.ios1vcrime.com.br.  
2. Login com usuário de teste (admin) e validar fluxo de autenticação.  
3. Navegar para módulos principais: Guias, Usuários, Audiências, Cartório e Gabinete.  
4. Validar sessão autenticada via endpoint auxiliar legado ainda ativo: `GET /admin/api/checkToken.php`.  
5. Testar pelo menos um endpoint oficial em `public/api/` consumido pela interface real do módulo alterado. Exemplos: `/api/cartorio-guias/list.php`, `/api/sala-audiencias-pauta/occupied-slots.php`, `/api/processos/classe-read.php?numero=...`.  
6. Quando houver mudança administrativa, validar também endpoints oficiais de `public/api/admin/`, como `/api/admin/feriados/list.php`.  
7. Não usar a árvore `api/` da raiz (`/api/guia.php`, `/api/notifications.php`, `/api/users.php`, `/api/documents.php`) como referência de smoke test canônico, pois ela não representa a superfície oficial atual.  

Testes automatizados (sugestão):

- PHP Unit: escrever testes para serviços em `app/services/` (PHPUnit).  
- PHPStan: análise estática para `app/` (nível 7 recomendado).  
- lint CSS/JS: usar stylelint / ESLint conforme necessidade.

## Sandbox isolado de autenticação

**Execução suspensa:** a REI-93 mantém barreiras provisórias que recusam a
inicialização, a execução e a limpeza dos testes de autenticação. Não remova ou
contorne essas barreiras: o isolamento do sandbox descartável ainda não foi
comprovado. Cópias antigas do repositório podem não conter a mesma proteção.

Antes de habilitar um novo executor, é necessário comprovar para cada execução:

- a identidade e a procedência do daemon e das imagens utilizadas;
- uma rede sem acesso indevido a serviços externos e sem relay SMTP para fora;
- cópia e montagem de arquivos confinadas, sem `.env` ou dados reais;
- identidade do banco descartável e da captura SMTP, vinculadas aos recursos
  inspecionados e a desafios novos, com destinatários sintéticos;
- limpeza restrita aos recursos criados pela execução, com confirmação do
  resultado e respeito às proteções existentes.

Endereço local, nome de banco, variáveis de ambiente, uma rede bridge ou um
marcador isolado não demonstram, por si só, essas propriedades. Os comandos
históricos não constituem um procedimento validado de execução ou limpeza.
Registre na REI-93 as evidências, lacunas e resultado antes de alterar a barreira.

Mesmo após validação futura do fluxo HTTP local, evidência de cookies `Secure`
em HTTPS, comportamento em iframe e paridade com produção exige testes próprios.

Relatar falhas em issue com passos para reproduzir, logs e URL do ambiente dev.

