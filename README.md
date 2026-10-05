# CRUD ICP-Brasil (ITI)

Sistema web para gerenciar a estrutura de Autoridades Certificadoras da ICP-Brasil
(AC, AC de 2º nível e Autoridades de Registro), simulando
[estrutura.iti.gov.br](https://estrutura.iti.gov.br/) e populável pelo upload do
`structure.json` publicado pelo ITI.

Desafio técnico — Desenvolvedor PHP.

## Stack

| Item | Versão |
|---|---|
| PHP | 8.3 |
| Framework | Laminas MVC 3 |
| ORM | Doctrine ORM 2.20 (DoctrineORMModule 6) com mapeamento por atributos |
| Banco | MySQL 8.0 |
| QR Code | [chillerlan/php-qrcode](https://github.com/chillerlan/php-qrcode) 5 (SVG, não depende da extensão GD) |
| Autenticação | laminas-authentication + laminas-session |
| Interface | Views `.phtml` + Bootstrap 5 (sem build de front-end) |
| Testes | PHPUnit 10 + laminas-test |

## Requisitos do desafio

| Requisito | Onde está |
|---|---|
| CRUD de AC, AC N2 e AR (listar, criar, editar, excluir) | `module/Icp/src/Controller/{Ac,AcN2,Ar}Controller.php` |
| AC N2 vinculada a uma AC; AR vinculada a AC N2 | Entidades em `module/Icp/src/Entity/` (FKs `RESTRICT`) |
| Botão "Gerar QRCode" com modal | `BotaoQrCode` (view helper), `QrCodeController`, `public/js/app.js` |
| Login por e-mail e senha; só autenticados acessam os CRUDs | Módulo `module/Auth` (`AuthGuard` protege todas as rotas, exceto `/login`) |
| MySQL + ORM + Composer | `config/autoload/local.php`, Doctrine, `composer.json` |
| Importar `structure.json` via upload | `/importar` — `LeitorEstrutura` + `ImportadorEstrutura` |
| Simular a estrutura do site do ITI | `/estrutura` — árvore AC → AC N2 → AR com filtro |

## Decisões de modelagem

A análise do `structure.json` real (05/10/2026) mostrou pontos que o enunciado
não cobre. Ver também os avisos exibidos ao final de cada importação.

- **Níveis do arquivo:** `ac-root` (AC RAIZ, nó único) → `ac-1` (**AC**, 20) →
  `ac-2` (**AC N2**, 106) → `ar` (**AR**). A AC RAIZ é só o ponto de partida e não vira registro.
- **AR ↔ AC N2 é N:N.** As 2.021 ARs do arquivo aparecem 6.585 vezes: a mesma AR é
  credenciada por várias AC N2. Cada AR é gravada uma única vez e ligada às suas
  AC N2 pela tabela `ar_ac_n2`. O formulário exige ao menos uma AC N2.
- **Vínculos AR → AC 1º nível** (222 no arquivo) não existem no modelo exigido e são
  ignorados. As 3 ARs que só têm esse tipo de vínculo não são importadas.
- **`situacao`:** 4002 = Credenciado, 4001 = Em credenciamento (códigos do próprio ITI).
- **`iti_id`:** guarda o `id` do ITI, com índice único. A importação casa os registros por ele,
  então reenviar o arquivo **atualiza sem duplicar**. Registros cadastrados à mão ficam com `iti_id` nulo.
- **Exclusão:** bloqueada com mensagem quando há dependentes (AC com AC N2, AC N2 com AR).
  As FKs `ON DELETE RESTRICT` garantem a regra também no banco.
- **QR Code:** codifica a URL absoluta da página de detalhes do registro
  (ex.: `http://localhost:8080/ac/view/2`). O PDF do desafio termina em "com o link:"
  sem informar o destino; trocar o alvo é uma alteração em `LinkQrCode`.

> **Atenção à URL do JSON:** `https://estrutura.iti.gov.br/assets/structure.json` devolve a
> página HTML do site (aplicação Angular). O arquivo de dados está em
> **`https://estrutura.iti.gov.br/assets/jsons/structure.json`**. O importador detecta
> o HTML e mostra essa orientação. Uma cópia do arquivo real está em `data/exemplo/structure.json`.

## Como executar (Ubuntu / WSL)

Pré-requisitos: PHP 8.3 com `pdo_mysql`, `mbstring`, `intl`, `fileinfo`; Composer; MySQL 8.

```bash
composer install
```

Banco e usuário da aplicação (ajuste a senha):

```sql
CREATE DATABASE crud_iti CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'crud_iti_user'@'localhost' IDENTIFIED BY 'sua-senha';
GRANT ALL PRIVILEGES ON crud_iti.* TO 'crud_iti_user'@'localhost';
```

Configuração local e schema:

```bash
cp config/autoload/local.php.dist config/autoload/local.php   # preencha a senha do banco
./vendor/bin/doctrine-module orm:schema-tool:create             # ou: mysql crud_iti < data/sql/schema.sql
```

Usuário de acesso (a senha é pedida no terminal, sem eco):

```bash
composer criar-usuario -- voce@exemplo.com "Seu Nome"
```

Dados do ITI (opcional; também pode ser feito pela tela `/importar`):

```bash
composer importar-estrutura -- data/exemplo/structure.json
```

Subir a aplicação:

```bash
composer serve        # http://localhost:8080
```

### Usuário de demonstração

Para avaliação local, crie o usuário de demonstração com a senha `Demo@ITI2026`:

```bash
CRUD_ITI_SENHA='Demo@ITI2026' composer criar-usuario -- demo@crud-iti.test "Usuário Demonstração"
```

### Desempenho no WSL

Se o projeto estiver em um disco do Windows (`/mnt/c`, `/mnt/d`…), cada requisição leva
de 1 a 3 s por causa do acesso a arquivos entre Windows e WSL2. Medido aqui: o bootstrap
leva 3,5 s em `/mnt/d` e 0,03 s no disco do Linux. Para desenvolver com velocidade normal,
mantenha o projeto no sistema de arquivos do Linux (ex.: `~/crud-iti`), acessível pelo
Windows em `\\wsl$\Ubuntu-24.04\home\<usuario>\crud-iti`.

## Testes

```bash
composer test
```

- `LeitorEstruturaTest`: leitura do `structure.json` (níveis, AR repetida, vínculos diretos ignorados,
  situação, truncamento, HTML no lugar do JSON, JSON inválido, BOM).
- `ArFormTest`: validação da seleção múltipla de AC N2 (regressão).
- `ArTest`: vínculos N:N sem duplicar.
- `CredenciaisAdapterTest`: login válido, senha errada, usuário inexistente, hash de senha.
- `GeradorQrCodeTest`: saída SVG.
- `ProtecaoDeRotasTest`: todas as rotas de CRUD, importação e QR redirecionam para o login sem sessão.

Os testes não dependem do MySQL. Teste de ponta a ponta do upload, com o servidor
rodando e o usuário de demonstração criado:

```bash
CRUD_ITI_SENHA='Demo@ITI2026' bash bin/teste-upload.sh data/exemplo/structure.json
```

Capturas de tela da aplicação em funcionamento: `docs/evidencias/`.

## Estrutura

```text
module/
├── Application/   layout, painel inicial, CsrfForm e partial de campo de formulário
├── Auth/          entidade Usuario, login/logout, AuthGuard (proteção de rotas)
└── Icp/           domínio: entidades, repositórios, formulários, CRUDs,
                   importação do structure.json, QR Code e árvore da estrutura
bin/               criar-usuario.php, importar-estrutura.php, teste-upload.sh
data/exemplo/      structure.json real do ITI (05/10/2026)
data/sql/          schema.sql gerado pelo Doctrine
```

## Segurança

- Senhas com `password_hash()` / `password_verify()`; mensagem única para e-mail ou senha errados.
- Novo id de sessão no login e no logout; cookie `HttpOnly` e `SameSite=Lax`.
- Token CSRF em todos os POSTs (formulários, exclusão, importação, logout).
- Exclusão somente por POST; redirecionamento pós-login aceita apenas caminhos locais.
- Saída escapada nas views (`escapeHtml` / `escapeHtmlAttr`), inclusive no `<title>`.
- Upload: extensão `.json`, até 10 MB, conteúdo validado antes de qualquer gravação, importação em transação.
- Credenciais do banco somente em `config/autoload/local.php` (fora do Git).
