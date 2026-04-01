# Especificação de Requisitos de Acessibilidade

## 1. Introdução

Este documento define os padrões de acessibilidade e requisitos técnicos para o WebApp | 1vCrimeIlheus. O objetivo é garantir que a aplicação seja utilizável por pessoas com diversas habilidades, incluindo deficiências visuais, auditivas, motoras e cognitivas, cumprindo as normas legais e as melhores práticas de design inclusivo.

## 2. Normas de Conformidade

O projeto deve aderir às seguintes diretrizes nacionais e internacionais:

- **WCAG 2.1 (Web Content Accessibility Guidelines):** A conformidade com o Nível **AA** é obrigatória.
- **eMAG (Modelo de Acessibilidade em Governo Eletrônico):** Aderência aos padrões de acessibilidade digital do governo brasileiro onde aplicável.

## 3. Requisitos Visuais e de Interface (UI)

### 3.1. Cor e Contraste

- **Razão de Contraste:** Textos e elementos interativos devem ter uma razão de contraste de pelo menos **4.5:1** em relação ao fundo (Nível AA). Textos grandes (18pt+) devem ter pelo menos 3:1.
- **Independência de Cor:** A informação não deve ser transmitida apenas pela cor (ex: mensagens de erro devem usar ícones ou rótulos de texto, não apenas bordas vermelhas).
- **Modo Escuro:** A interface deve suportar o modo escuro baseado na preferência do sistema (opcional, mas recomendado para conforto visual).

### 3.2. Tipografia e Layout

- **Texto Redimensionável:** O layout deve permanecer funcional quando o texto for redimensionado em até **200%** sem o uso de tecnologia assistiva.
- **Design Responsivo:** A interface deve se adaptar a diferentes tamanhos de tela (Mobile, Tablet, Desktop) sem rolagem horizontal para leitura de texto (Reflow).

## 4. Navegação e Interação

### 4.1. Navegação por Teclado

- **Foco Visível:** Todos os elementos interativos (links, botões, campos) devem ter um indicador de foco claramente visível (borda/anel) quando selecionados via teclado.
- **Ordem de Tabulação:** A ordem de navegação deve seguir o fluxo visual lógico da página (Esquerda para Direita, Topo para Baixo).
- **Sem Armadilhas de Teclado:** Os usuários devem ser capazes de navegar para dentro e para fora de todos os componentes de UI (modais, iframes) usando apenas o teclado.

### 4.2. Leitores de Tela (Tecnologia Assistiva)

- **HTML Semântico:** Utilize tags HTML5 apropriadas (`<main>`, `<nav>`, `<article>`, `<header>`, `<footer>`) em vez de estruturas genéricas com `<div>`.
- **Rótulos ARIA:** Utilize `aria-label` ou `aria-labelledby` para elementos interativos que não possuem texto visível (ex: botões apenas com ícones).
- **Texto Alternativo em Imagens:** Todas as imagens informativas devem ter atributos `alt` descritivos. Imagens decorativas devem ter `alt=""` vazio.

## 5. Implementação Técnica (HTML, CSS, JavaScript e PHP)

### 5.1. Formulários e Entrada de Dados

- **Rótulos (Labels):** Todos os campos de entrada (especialmente nos formulários de Login e Cadastro) devem ter elementos `<label>` associados via `for`/`id` em HTML.
- **Campos com Máscara:** Para o campo de CPF (mascarado), garanta que o leitor de tela anuncie o formato esperado. Utilize `aria-describedby` para vincular textos de ajuda.
- **Identificação de Erros:** Erros de validação devem ser associados programaticamente ao campo de entrada para que os leitores de tela anunciem o erro imediatamente.

### 5.2. Acessibilidade em Iframes (Módulo Admin)

Dado que a arquitetura utiliza `iframes` para páginas administrativas:

- **Atributo Title:** Todo `<iframe>` deve possuir um atributo `title` descritivo (ex: `title="Conteúdo do Painel Administrativo"`).
- **Gerenciamento de Foco:** Garanta que o foco do teclado possa transitar perfeitamente entre a janela principal (pai) e o conteúdo do iframe.

### 5.3. Ferramentas de Desenvolvimento

- **Checklist de revisão:** Inclua verificação de acessibilidade nas revisões de HTML, formulários, iframes e navegação por teclado.
- **Testes:** Utilize ferramentas como **Lighthouse** ou **Axe DevTools** e validação manual nos principais fluxos antes dos lançamentos.

## 6. Requisitos Específicos de Componentes

| Componente | Requisito |
| :--- | :--- |
| **Modais** | Devem prender o foco dentro do modal enquanto aberto e retornar o foco ao botão de origem ao fechar. |
| **Menu de Navegação** | Deve suportar um link de "Pular para o Conteúdo" como o primeiro elemento focável da página. |
| **Tabelas** | Tabelas de dados (ex: Listas de Processos) devem usar `<th>` com cabeçalhos `scope="col"` ou `scope="row"`. |
| **Indicadores de Status** | Utilize ícones + texto (ex: "Ativo", "Bloqueado") em vez de apenas pontos coloridos. |
