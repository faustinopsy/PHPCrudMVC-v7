# FastBackPHP

FastBackPHP é uma automação simplificada para conectar-se a um banco de dados existente e abstrair todas as tabelas em modelos (models), controladores (controllers) e repositórios (repositories). Inspirado em ferramentas como **PHPMaker**, **Scriptcase**, e **Laravel Nova**, este micro-framework foi projetado para facilitar a criação de APIs REST com suporte a um sistema de rotas integrado.

## Objetivo

FastBackPHP foi criado para desenvolvedores que desejam automatizar a construção de APIs REST para aplicações que já possuem um banco de dados estruturado. A ferramenta abstrai a lógica básica e permite que você se concentre em customizar a lógica de negócios ou nos endpoints necessários.

## Casos de Uso

- **Automação de APIs REST:** Ideal para gerar rapidamente endpoints básicos para CRUD.
- **Prototipagem Rápida:** Geração inicial de código para novos projetos baseados em bancos de dados existentes.
- **Integração com Sistemas Legados:** Perfeito para integrar APIs a sistemas que já possuem um banco de dados definido.
- **Educação:** Excelente para aprendizado e demonstração de como APIs REST funcionam com abstração de camadas (Model, Controller e Repository).

## Funcionalidades

1. **Configuração de Banco de Dados**
    - Suporte a bancos MySQL, SQLite, PostgreSQL, SQL Server e MongoDB.
    - Configuração via terminal para gerar automaticamente os parâmetros no arquivo `Config`.

2. **Geração de Código**
    - **Models:** Representações das tabelas do banco de dados.
    - **Repositories:** Classes para encapsular operações de banco de dados (CRUD).
    - **Controllers:** Camada para lidar com rotas e integração entre Models e Repositories.

3. **Sistema de Rotas**
    - Inspirado em frameworks como Laravel e Symfony, suporta anotações para simplificar o roteamento.

4. **Flexibilidade**
    - Não restringe a ordem de uso dos comandos.
    - Você pode gerar apenas Models ou Repositories separadamente.

---

## Instalação

### Pré-requisitos
- **PHP 8.0+**
- **Composer** instalado
- Servidor Web (ex.: Apache, Nginx, ou CLI do PHP)

### Passos de Instalação

1. Clone o repositório ou baixe os arquivos:
    ```bash
    git clone https://github.com/faustinopsy/fastbackphp.git
    cd fastbackphp
    ```

2. Instale as dependências via Composer:
    ```bash
    composer install
    ```

3. Execute o CLI (Terminal/Promp):
    ```bash
    php Fast.php
    ```

4. Siga as instruções no terminal:
    - **Opção 1:** Configure o banco de dados. Esse passo é obrigatório para outros comandos.
    - **Opção 2:** Gere as Models para abstrair tabelas do banco.
    - **Opção 3:** Gere os Repositories para operações CRUD.
    - **Opção 4:** Gere os Controllers para criar endpoints REST (esse passo deve vir depois dos dois anteriores), pois o controller ler os métodos do repository e gera as rotas para cada método.
     **Opção 5:** SObre o servidor interno do CLI.


## Como Usar o Terminal

Ao executar `php Fast.php`, você verá as seguintes opções no terminal:

1. **Configurar Banco de Dados:**
   - Defina as credenciais do banco no arquivo `Config` para conexão e geração de código.

2. **Gerar Models:**
   - Gera classes que representam as tabelas do banco de dados.

3. **Gerar Repositories:**
   - Cria classes para manipulação de dados no banco com métodos CRUD.

4. **Gerar Controllers:**
   - Cria classes que conectam Models e Repositories, prontos para uso como APIs REST.

5. **Subir o servidor embutido:**
   - Servidor do PHP.

6. **Sair:**
   - Finaliza o CLI.

---

## Regras e Recomendações

- **Passo 1:** Configuração do banco de dados é obrigatória antes de executar qualquer outro comando.
(Pois esse framework é para possiveis migrações, ode já existem um banco de dados com tabelas)
- **Uso flexível:** Pode-se gerar apenas Models ou Repositories, conforme necessário.
- **Controllers dependem de Models e Repositories:** Não é possível gerar Controllers sem que Models e Repositories existam.

---

## Contribuições
Contribuições são bem-vindas! Caso tenha sugestões ou encontre bugs, abra uma issue ou envie um pull request no repositório oficial.

Se por algum motivo esse pacote é útil, deixe uma doação para eu melhorar isso colocando outra camada que leverá muito temp ode desenvolvimento, mas a outra camada é um frontend para cada controller, com autenticação e autorização.

## Licença
FastBackPHP é distribuído sob a licença MIT. Veja o arquivo LICENSE para mais informações.
