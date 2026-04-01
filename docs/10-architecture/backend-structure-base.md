# Estrutura de Pastas e Arquivos Base do Backend

## 1. Visao Geral da Arquitetura

O backend do projeto e uma base **PHP customizada**, organizada para separar arquivos publicos, regras de negocio, acesso a dados, configuracao e scripts de banco.
Esta documentacao descreve a estrutura **real** do repositorio e substitui modelos genericos baseados em frameworks que nao fazem parte da arquitetura oficial.

## 2. Estrutura de Diretorios Atual

Abaixo esta a estrutura principal do backend e das camadas relacionadas:

- **root/**
  - **app/**
    - **access/**: politicas, registro de paginas e avaliacao de acesso
    - **controllers/**: controladores da aplicacao
    - **helpers/**: bootstrap, conexao, utilitarios e apoio tecnico
    - **middlewares/**: middlewares e validacoes intermediarias
    - **models/**: modelos centrais do dominio
    - **repositories/**: consultas e persistencia orientadas ao banco
    - **rules/**: regras especificas do dominio
    - **services/**: casos de uso e orquestracao
    - **views/**
      - **internal/**: paginas internas carregadas pelo shell autenticado
      - **public/**: views publicas quando aplicavel
  - **config/**
    - **config.php**
    - **security.php**
  - **database/**
    - **schema.sql**
    - **migrations/**
    - **seeds/**
    - scripts SQL auxiliares e evolutivos
  - **public/**
    - **index.php**: entrada publica
    - **login.php**: processamento de autenticacao
    - **home.php**: shell autenticado
    - **frame.php**: carregamento das paginas internas
    - **api/**: endpoints internos principais
    - **admin/api/**: endpoints auxiliares administrativos
    - **assets/**: CSS, JavaScript, imagens, templates e recursos estaticos
  - **storage/**
    - **logs/**
    - **uploads/**
    - **backups/**
  - **docs/**
  - **scripts/**

Observacao: existe tambem uma pasta **`api/`** na raiz do repositorio, mas ela nao representa a superficie principal atual da aplicacao e deve ser tratada separadamente em auditoria propria.

## 3. Camada de Conexao

A conexao com o banco e centralizada em helper proprio, com destaque para:

- `app/helpers/Conection.php`
- `config/config.php`

Essa camada encapsula a criacao da conexao PDO e os parametros necessarios para acesso ao MySQL, mantendo o consumo do banco desacoplado dos entrypoints publicos.

## 4. Camada de Seguranca e Acesso

O controle da area restrita e feito no backend, com apoio de:

- `config/security.php`
- `app/helpers/bootstrap.php`
- `app/access/`
- `app/middlewares/`

O shell autenticado e servido por `public/home.php`, enquanto `public/frame.php` resolve as paginas internas permitidas.
Regras de permissao e acesso por perfil/pagina devem permanecer centralizadas fora da camada de apresentacao.

## 5. Camada de Aplicacao e Persistencia

O fluxo padrao do backend ocorre assim:

1. o navegador acessa um entrypoint em `public/` ou um endpoint em `public/api/`;
2. o backend carrega configuracao, seguranca e bootstrap;
3. services, controllers, models e repositories executam o fluxo da funcionalidade;
4. a resposta retorna como HTML renderizado ou JSON, conforme o caso.

Diretorios-chave nessa camada:

- `app/controllers/`
- `app/services/`
- `app/models/`
- `app/repositories/`
- `app/rules/`

## 6. Frontend Acoplado ao Backend

Mesmo este documento sendo focado no backend, a estrutura real do projeto exige considerar os ativos acoplados ao runtime:

- `public/assets/css/shared/` contem a base de estilos compartilhados;
- `public/assets/css/modules/` contem estilos especificos de modulos;
- `public/assets/js/` contem scripts e modulos JavaScript do sistema;
- `app/views/internal/` contem as telas internas carregadas no iframe.

O projeto usa CSS e JavaScript proprios, sem Bootstrap como dependencia estrutural.

## 7. Endpoints Internos

A superficie principal de endpoints internos do sistema fica em:

- `public/api/`

Ela contem grupos de endpoints por modulo, como cartorio, gabinete, audiencias, consultas e entidades auxiliares.
Os endpoints de apoio administrativo mais antigos ficam em:

- `public/admin/api/`

Novas rotas e novos contratos devem ser documentados a partir da estrutura oficial do projeto, evitando dispersao entre diretorios paralelos.

## 8. Diretrizes de Manutencao

- manter regra de negocio fora de `public/`
- manter SQL e evolucoes estruturais em `database/`
- manter configuracoes de runtime em `config/`
- manter documentacao alinhada a estrutura real do repositorio
- nao introduzir documentacao baseada em Laravel, Next.js ou Bootstrap para descrever a arquitetura atual
