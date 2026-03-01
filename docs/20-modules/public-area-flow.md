# Fluxo de Navegação e Funcionalidades — Área Pública

## Referências e alinhamento
- docs/00-foundation/estrutura-repositorio.md — estrutura física e separação de responsabilidades.
- docs/00-foundation/escopo-produto.md e docs/00-foundation/escopo-projeto.md — visão de produto, módulos previstos e foco da fase 2026.01.
- docs/00-foundation/funcionalidades areas publica e restrita.md — divisão funcional entre área pública e restrita.

## 1. Objetivos, público e limites
A Área Pública é o portal institucional da Primeira Vara Crime. Atende cidadãos, estudantes e comunidade jurídica com conteúdos institucionais, consultas públicas permitidas e fluxos acadêmicos, sem expor dados sensíveis (processos em segredo de justiça são mascarados ou bloqueados). Esse ambiente reduz a demanda presencial e provê uma camada de transparência controlada ao cidadão.

## 2. Acesso e roteamento inteligente
index.php é o ponto de entrada único e detecta o perfil antes do redirecionamento: servidores/chefias são encaminhados à Área Restrita (iframe em dmin/home.php), enquanto estudantes e público externo permanecem no portal público. A autenticação usa tokens JWT residindo em sessionStorage (conforme 	echnical-assumptions-constraints.md) e inclui um fluxo seguro de recuperação de senha por token.

## 3. Serviços públicos estratégicos
1. **Portal do Estudante:** auto-cadastro, seleção de audiências públicas do dia e download automático das atas validadas.
2. **Consultas permitidas:** pauta do dia, estatísticas agregadas e marcos institucionais.
3. **Painel de transparência:** indicadores de produtividade, reconhecimentos institucionais e status das audiências em tempo real.
4. **Documentos públicos e FAQ:** manuais, normas, portarias e respostas a dúvidas recorrentes disponíveis para download.

## 4. Restrições e governança
- O portal **não é** um buscador processual livre (padrão definido pelo documento de funcionalidades). Consultas detalhadas requerem os sistemas oficiais do tribunal ou atendimento presencial.
- Dados sensíveis e pessoais permanecem mascarados; apenas eventos públicos e dados agregados são exibidos.
- Todas as regras seguem o escopo de produto e garantem que a Área Pública não permita manipulação ou expedição de atos internos.
