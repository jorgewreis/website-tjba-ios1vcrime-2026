# Especificação de Requisitos de Desempenho e Tempo de Resposta

## 1. Introdução

Este documento define os indicadores de desempenho (KPIs) e os limites aceitáveis de tempo de resposta para o WebApp | 1vCrimeIlheus. O objetivo é assegurar uma experiência de usuário fluida e a eficiência operacional, mesmo em momentos de pico de utilização.

## 2. Métricas de Tempo de Resposta (Latência)

Os tempos abaixo consideram condições normais de rede (banda larga estável) e processamento no servidor.

### 2.1. Interações de Interface (Frontend)

- **Carregamento Inicial (LCP):** O conteúdo principal da página de login ou dashboard deve ser visível em até **2,5 segundos** (Core Web Vitals).
- **Navegação entre páginas internas:** A transição entre conteúdos da área autenticada (via `public/home.php` + `public/frame.php?page=...`) deve ocorrer em menos de **500ms** para páginas leves, desconsideradas consultas pesadas ao banco.
- **Feedback Visual:** Qualquer ação do usuário (clique em botão) deve apresentar um feedback visual (spinner, mudança de cor) em menos de **100ms**.

### 2.2. Requisições de API (Backend)

- **Consultas Simples (Leitura):** Endpoints de listagem (ex: Listar Processos, Buscar Usuário) devem retornar em no máximo **500ms**.
- **Transações Complexas (Escrita):** Operações de gravação que envolvem múltiplas tabelas (ex: Criar Processo, Salvar Ata) devem ser concluídas em até **2 segundos**.
- **Relatórios e Buscas Pesadas:** Consultas complexas (ex: Filtros avançados por data e parte) têm um limite tolerável de **5 segundos**. Caso exceda, deve ser processado em background ou exibir barra de progresso.

## 3. Capacidade e Concorrência

### 3.1. Usuários Simultâneos

- O sistema deve suportar, sem degradação perceptível de performance, o acesso simultâneo de **100% da força de trabalho prevista** (Servidores, Estagiários e Chefia) operando no mesmo horário (pico das 14:00 às 16:00).
- Capacidade estimada inicial: Suporte a **50-100 requisições por segundo (RPS)** na API.

## 4. Otimização de Banco de Dados

### 4.1. Indexação e Consultas

- **Índices Obrigatórios:** Todas as colunas utilizadas em cláusulas `WHERE`, `JOIN` e `ORDER BY` (especialmente `cpf`, `nome_parte`, `data_audiencia`) devem possuir índices otimizados no MySQL.
- **Paginação:** Nenhuma listagem de dados pode retornar "todos os registros" de uma vez. É obrigatória a implementação de paginação (LIMIT/OFFSET) com máximo de **20 a 50 itens por página**.

## 5. Otimização de Frontend e Rede

### 5.1. Assets e Caching

- **Minificação:** Todo o código CSS e JavaScript exposto em `public/assets/` deve ser entregue em versão otimizada para produção, sem depender de build obrigatório de framework frontend.
- **Imagens:** Imagens estáticas devem ser otimizadas (formato WebP) e servidas com cache de longo prazo.
- **Lazy Loading:** Componentes pesados ou listas longas devem ser carregados sob demanda (Lazy Load) para não bloquear a renderização inicial.

### 5.2. Impacto do Iframe

- Dado o requisito arquitetural de uso de `iframe` para o admin, o sistema deve garantir que o "Cold Start" do iframe (primeiro carregamento) não exceda **1,5 segundos** adicionais ao tempo de carga da página pai.
