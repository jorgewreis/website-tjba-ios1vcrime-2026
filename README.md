# Sistema de Gestão do Cartório ⚖️
**Primeira Vara Crime da Comarca de Ilhéus**

Sistema web interno para centralizar rotinas do **Cartório**, **Gabinete** e **Audiências**, além de um módulo dedicado a **Estudantes**, com controle de acesso por perfil e foco em organização, produtividade e rastreabilidade.

- **Desenvolvimento:** Jorge Reis (Desenvolvedor Fullstack)
- **Stack:** HTML, CSS, JavaScript, PHP e SQL (MySQL)
- **Perfis:** `ADMIN` · `SERVIDOR` · `ESTUDANTE`

---

## Sumário
- [Visão geral](#visão-geral)
- [Área externa (pública)](#área-externa-pública)
- [Áreas internas (módulos)](#áreas-internas-módulos)
  - [Cartório](#cartório)
  - [Gabinete](#gabinete)
  - [Audiências](#audiências)
  - [Estudantes](#estudantes)
  - [Settings](#settings)
- [Perfis de acesso (resumo)](#perfis-de-acesso-resumo)
- [Fluxo de navegação](#fluxo-de-navegação)
- [Padrões de dados e validações](#padrões-de-dados-e-validações)
- [UI: paleta de cores](#ui-paleta-de-cores)
- [UI: padrão de componentes](#ui-padrão-de-componentes)
- [Segurança (boas práticas)](#segurança-boas-práticas)
- [Logs e auditoria](#logs-e-auditoria)
- [Estrutura do projeto (referência)](#estrutura-do-projeto-referência)
- [Licença e uso](#licença-e-uso)
- [Créditos](#créditos)

---

## Visão geral
O **Sistema de Gestão do Cartório** foi desenvolvido para apoiar atividades internas da **Primeira Vara Crime da Comarca de Ilhéus**, oferecendo um ambiente web organizado por módulos, com funcionalidades de cadastro, consulta, acompanhamento e documentação de rotinas.

---

## Área externa (pública)
Área acessível antes do login, voltada ao acesso e manutenção de conta:

- **Login** (entrada principal: `index.php`)
- **Recuperação de senha**
- **Cadastro de novo usuário**

---

## Áreas internas (módulos)

### Cartório
Rotinas cartorárias e acompanhamento de expedientes.

**Subáreas:**
- **Cartas Precatórias**
  - **Recebidas**
  - **Enviadas**
- **Editais**
- **Ofícios**
- **Guias**
- **Medidas Protetivas**
- **Produtividade**

---

### Gabinete
Apoio ao gabinete e controles específicos.

**Subáreas:**
- **Réus presos**
- **Ofícios**
- **Monitoramento eletrônico**

---

### Audiências
Organização e gestão das audiências.

**Subáreas:**
- **Pauta**
- **Atas**
- **Painel**
- **Partes**
- **Processos**

---

### Estudantes 🎓
Área dedicada ao perfil **ESTUDANTE**, com acesso restrito e orientado ao acompanhamento do fluxo de audiências.

**Subáreas:**
- **Pauta**
  - **do dia**
  - **do mês**
- **Atas**
  - **download de atas** das audiências realizadas
- **Inscrição**
  - opção para **assistir audiências do dia**
- **Painel**
  - informações **em tempo real** sobre audiências em andamento

---

### Settings
Configurações do sistema e do usuário.

**Subáreas:**
- **Segurança**
- **Perfil**

---

## Perfis de acesso (resumo)
A autorização é baseada em perfil, garantindo que cada usuário visualize e execute apenas o que for permitido.

| Perfil | Acesso principal |
|------:|------------------|
| **ADMIN** | Acesso total aos módulos e ações administrativas (conforme regras internas). |
| **SERVIDOR** | Rotinas operacionais de Cartório, Gabinete e Audiências conforme permissões definidas. |
| **ESTUDANTE** | Acesso restrito ao módulo **Estudantes** (e demais consultas permitidas). |

> Recomendação: manter uma matriz de permissões por **módulo × ação** (`visualizar`, `criar`, `editar`, `excluir`, `download`).

---

## Fluxo de navegação
Padrão de navegação do sistema:

- **Login:** `index.php`
- **Após login:** redireciona para `admin/home.php`
- **Páginas internas:** exibidas dentro de um **iframe** em `admin/home.php` (menu carrega no mesmo iframe)
- **Exposição de URLs internas:** preferir páginas internas acessadas por **arquivos públicos** que fazem `include` dos scripts da área `admin`, evitando expor diretamente URLs sensíveis
- **Logout:** após logout bem-sucedido, redirecionar para `index.php`

---

## Padrões de dados e validações

### CPF
- **Armazenamento:** CPF salvo **com máscara** em `VARCHAR` (ex.: `000.000.000-00`)
- **Exibição:** sempre com máscara
- **Validação:** validar usando **apenas dígitos** (remover `.` e `-` antes de validar)

### Usuários
- **Chave primária:** `id_usuario`

---

## UI: paleta de cores 🎨
Diretrizes práticas para manter consistência visual.

### Neutras e base
| Uso | Cor |
|---|---|
| Branco (superfícies / cards) | <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#ffffff;border:1px solid #00000020;"></span> |
| Branco translúcido (níveis) | <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:rgba(255,255,255,0.56);border:1px solid #00000020;"></span> <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:rgba(255,255,255,0.38);border:1px solid #00000020;margin-left:4px;"></span> <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:rgba(255,255,255,0.19);border:1px solid #00000020;margin-left:4px;"></span> <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:rgba(255,255,255,0.06);border:1px solid #00000020;margin-left:4px;"></span> |
| Fundo da aplicação | <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#f5f5f9;border:1px solid #00000020;"></span> |
| Texto (body) | <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#646e78;border:1px solid #00000020;"></span> |
| Preto / cinza escuro (contraste) | <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#000000;border:1px solid #00000020;"></span> <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#202020;border:1px solid #00000020;margin-left:4px;"></span> |

### Azuis (interface)
| Grupo | Cores |
|---|---|
| Tons claros (fundos/realces) | <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#f5f5f9;border:1px solid #00000020;"></span> <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#e2edf1;border:1px solid #00000020;margin-left:4px;"></span> <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#cce2eb;border:1px solid #00000020;margin-left:4px;"></span> |
| Tons médios (bordas/apoio) | <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#aaccda;border:1px solid #00000020;"></span> <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#aac6d1;border:1px solid #00000020;margin-left:4px;"></span> |
| Tons fortes (ações/destaques) | <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#2a8ab1;border:1px solid #00000020;"></span> <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#196481;border:1px solid #00000020;margin-left:4px;"></span> <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#0e455a;border:1px solid #00000020;margin-left:4px;"></span> <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#05202b;border:1px solid #00000020;margin-left:4px;"></span> |

### Semânticas (feedback)
| Tipo | Cor (texto/ícone) | Cor (fundo) |
|---|---|---|
| Sucesso | <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#71dd37;border:1px solid #00000020;"></span> | <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#e8fadf;border:1px solid #00000020;"></span> |
| Perigo | <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#FF3E1D;border:1px solid #00000020;"></span> | <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#ffe0db;border:1px solid #00000020;"></span> |
| Atenção | <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#ffab00;border:1px solid #00000020;"></span> | <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#fff2d6;border:1px solid #00000020;"></span> |
| Info | <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#03c3ec;border:1px solid #00000020;"></span> | <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#d7f5fc;border:1px solid #00000020;"></span> |
| Primária (framework) | <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#696cff;border:1px solid #00000020;"></span> | <span style="display:inline-block;width:16px;height:16px;border-radius:3px;background:#e7e7ff;border:1px solid #00000020;"></span> |

---

## UI: padrão de componentes
Diretrizes práticas para manter consistência visual (com base na paleta acima).

### Layout e superfícies
- **Fundo geral:** usar o **azul bem claro** (grupo “Tons claros”).
- **Cards/containers:** usar **branco**.
- **Bordas/divisórias:** usar **tons médios** (azuis médios), evitando contraste pesado.
- **Texto:** padrão em **cinza do body**; títulos podem usar **azul bem escuro** (tons fortes).

### Navegação e estado ativo
- **Item ativo / destaque:** usar **azul forte** (grupo “Tons fortes”).
- **Hover:** usar o próximo tom mais escuro (ainda no grupo “Tons fortes”).
- **Navbar/Sidebar escura (quando houver):** usar **azul bem escuro** com texto **branco**.

### Botões
- **Primário:** azul forte (tons fortes) + texto branco.
- **Secundário:** fundo em tons claros + borda em tom médio + texto em azul escuro.
- **Perigo (ação destrutiva):** usar a cor **Perigo** + texto branco.

### Alerts / mensagens
- Use sempre o par **(cor do tipo) + (fundo do tipo)**:
  - **Sucesso**, **Perigo**, **Atenção**, **Info**.

### Inputs (formulários)
- Fundo branco, borda em tom médio.
- No foco, borda em azul forte e (se usar) sombra bem discreta.

---

---

## Segurança (boas práticas)
- Controle de acesso por perfil (autorização por módulo/ação)
- Validação e sanitização **server-side** em formulários e endpoints
- Uso de **prepared statements** para reduzir risco de SQL Injection
- Proteções contra XSS/CSRF (conforme arquitetura adotada)
- Restringir acesso direto a scripts sensíveis e arquivos de configuração
- Recuperação de senha com fluxo seguro (token/expiração), quando aplicável

---

## Logs e auditoria
Quando aplicável, registrar eventos como:
- login/logout
- criação/edição/exclusão de registros
- downloads (ex.: atas)
- alterações em configurações/segurança

Campos sugeridos:
- `id_usuario` (autor)
- ação
- data/hora
- recurso afetado (módulo/tabela/id do registro)
- IP (opcional)

---

## Licença e uso
Sistema de uso institucional da **Primeira Vara Crime da Comarca de Ilhéus**.  
Reprodução, redistribuição ou reutilização dependem de autorização institucional.

---

## Créditos
Desenvolvido integralmente por **Jorge Reis** (Desenvolvedor Fullstack).
