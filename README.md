# CRUD ICP-Brasil (ITI)

Sistema web para gerenciar a estrutura de Autoridades Certificadoras da ICP-Brasil
(AC, AC de 2º nível e Autoridades de Registro), simulando
[estrutura.iti.gov.br](https://estrutura.iti.gov.br/) e populável pelo upload do
`structure.json` publicado pelo ITI.

Desafio técnico — Desenvolvedor PHP.

> **Demonstração online:** _endereço será publicado aqui após o deploy_ — usuário
> `demo@crud-iti.test`, senha `Demo@ITI2026`. Hospedagem gratuita: o primeiro acesso depois de
> um tempo parado costuma levar de 30 s a 1 min (o serviço "acorda"). Veja
> [Publicação gratuita](#publicação-gratuita-render--aiven).

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
- **Situação por vínculo.** Em 21 ARs a situação muda conforme a AC N2 (ex.: AR CERTISIGN é
  credenciada em 14 AC N2 e está em credenciamento na AC CERTISIGN_OM-BR). Por isso cada vínculo
  guarda a sua situação (entidade `VinculoArAcN2`, coluna `ar_ac_n2.situacao`), exibida na árvore
  e nas páginas de detalhe. A situação da própria AR, usada na listagem e no filtro, é
  **Credenciado se ela for credenciada em ao menos uma AC N2**. Na edição manual, alterar o campo
  situação aplica o novo valor a todos os vínculos; mantê-lo preserva a situação de cada um.
- **`iti_id`:** guarda o `id` do ITI, com índice único. A importação casa os registros por ele,
  então reenviar o arquivo **atualiza sem duplicar**. Registros cadastrados à mão ficam com `iti_id` nulo.
  A reimportação cria e atualiza registros e vínculos, mas **não remove** o que deixou de constar
  no arquivo (nem vínculos feitos à mão); ela não é uma sincronização completa.
- **Exclusão:** bloqueada com mensagem quando há dependentes (AC com AC N2, AC N2 com AR).
  As FKs `ON DELETE RESTRICT` garantem a regra também no banco, inclusive se uma AR for
  vinculada entre a checagem e a exclusão (o Doctrine não apaga os vínculos por conta própria).
- **QR Code:** ao ser lido, leva para a página daquela entidade em específico
  (ex.: `http://localhost:8080/ac/view/2`), **conforme confirmado pelo avaliador** (o PDF
  terminava em "com o link:" sem informar o destino). Quem lê sem estar logado passa pelo login e
  é levado em seguida à página do item. Para ler pelo celular, veja "Ler o QR Code pelo celular".

> **Atenção à URL do JSON:** `https://estrutura.iti.gov.br/assets/structure.json` devolve a
> página HTML do site (aplicação Angular). O arquivo de dados está em
> **`https://estrutura.iti.gov.br/assets/jsons/structure.json`**. O importador detecta
> o HTML e mostra essa orientação. Uma cópia do arquivo real está em `data/exemplo/structure.json`.

## Como executar (Ubuntu / WSL)

Pré-requisitos: PHP 8.3 com `pdo_mysql`, `mbstring`, `intl`, `fileinfo` e as extensões XML
(`dom`, `simplexml`, `xmlwriter`, exigidas pelas dependências de desenvolvimento); Composer; MySQL 8.
No Ubuntu 24.04:

```bash
sudo apt install php8.3-cli php8.3-mysql php8.3-mbstring php8.3-intl php8.3-xml composer mysql-server
```

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

**Banco criado antes da situação por vínculo** (commit `9a56602` ou anterior): aplique
`data/sql/atualizacao-situacao-por-vinculo.sql`, que cria a coluna `ar_ac_n2.situacao` copiando a
situação atual da AR, e depois reimporte o `structure.json` para ajustar os vínculos que diferem:

```bash
mysql -u crud_iti_user -p crud_iti < data/sql/atualizacao-situacao-por-vinculo.sql
composer importar-estrutura -- data/exemplo/structure.json
```

### Usuário de demonstração

Para avaliação local, crie o usuário de demonstração com a senha `Demo@ITI2026`:

```bash
CRUD_ITI_SENHA='Demo@ITI2026' composer criar-usuario -- demo@crud-iti.test "Usuário Demonstração"
```

### Ler o QR Code pelo celular

O QR codifica o endereço pelo qual o sistema foi aberto. Aberto como `http://localhost:8080`, o
link aponta para `localhost`, que o celular não alcança. Para testar com o celular na mesma rede:

1. Suba o servidor escutando na rede (`composer serve` já usa `0.0.0.0:8080`).
2. Abra o sistema no computador pelo IP da máquina na rede (ex.: `http://192.168.0.10:8080`):
   o QR passa a codificar esse endereço. Outra opção é fixar o endereço em
   `config/autoload/local.php`: `'icp' => ['qrcode' => ['base_url' => 'http://192.168.0.10:8080']]`.
3. **No WSL2**, a rede padrão (NAT) não aceita conexões vindas de outros aparelhos. Use o modo
   espelhado (`networkingMode=mirrored` na seção `[wsl2]` do `%UserProfile%\.wslconfig`, seguido de
   `wsl --shutdown`) e libere a porta 8080 no firewall do Windows.

### Desempenho no WSL

O projeto fica no sistema de arquivos do Linux (`~/crud-iti`), acessível pelo Windows em
`\\wsl$\Ubuntu-24.04\home\<usuario>\crud-iti`. Em um disco do Windows (`/mnt/c`, `/mnt/d`…)
as páginas ficam segundos mais lentas por causa do acesso a arquivos entre Windows e WSL2.
Medido aqui com o mesmo script, servidor embutido com OPcache e sessão já logada:

| Página | `/mnt/d` | `~/crud-iti` |
|---|---|---|
| Lista de AC (`/ac`) | 3,63 s | 0,06 s |
| Lista de AR (`/ar`, 2.018 registros) | 2,73 s | 0,07 s |
| Estrutura (`/estrutura`) | 5,24 s | 0,10 s |
| Importação (`/importar`) | 5,34 s | 0,02 s |
| Bootstrap da aplicação (CLI) | 3,49 s | 0,03 s |

## Publicação gratuita (Render + Aiven)

O mesmo código roda num container (`Dockerfile`: PHP 8.3 + Apache, com o Laminas instalado pelo
Composer no build). O app fica no [Render](https://render.com) (plano gratuito) e o MySQL no
[Aiven](https://aiven.io/free-mysql-database) (plano gratuito, sem cartão).

**1. Banco (Aiven):** crie um serviço **MySQL** no plano *Free*. Na página do serviço, anote
*Host*, *Port*, *User* (`avnadmin`), *Password* e *Database* (`defaultdb`) e baixe o *CA certificate*.

**2. App (Render):** *New → Web Service*, conecte este repositório, *Language: Docker*,
plano *Free*. Não há *start command* (o `Dockerfile` define tudo). *Health Check Path*: `/login`.
Em *Environment*:

| Variável | Valor |
|---|---|
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | dados do Aiven |
| `DB_SSL_CA_ARQUIVO` | `/etc/secrets/ca.pem` — antes, em *Secret Files*, crie o arquivo `ca.pem` com o conteúdo do *CA certificate* do Aiven (alternativa: colar o texto do certificado na variável `DB_SSL_CA`) |
| `DEMO_EMAIL`, `DEMO_SENHA` | usuário de demonstração (ex.: `demo@crud-iti.test` / `Demo@ITI2026`) |
| `RESTAURAR_DADOS` | opcional: um valor novo (ex.: a data) apaga AC/AC N2/AR e reimporta o arquivo do ITI no próximo deploy |

`PORT` e `RENDER_EXTERNAL_URL` são definidas pelo próprio Render; a segunda faz o QR Code apontar
para o endereço público (`https://…onrender.com/ar/view/15`), que abre no celular.

**O que acontece ao subir** (`bin/iniciar-container.sh` → `bin/preparar-banco.php`): banco vazio
recebe `data/sql/schema.sql`; banco antigo recebe a atualização da situação por vínculo; o
`structure.json` é importado uma única vez; o usuário demo é criado (trocar `DEMO_SENHA` e fazer
novo deploy troca a senha). Cada etapa fica registrada na tabela `crud_iti_instalacao`, então
reinícios não repetem nada. Sem o certificado da CA, o container se recusa a subir: a conexão com
o banco publicado é sempre por TLS verificado.

**Limites do plano gratuito:** o Render "dorme" após 15 min sem acesso (o próximo acesso leva de
30 s a 1 min e a sessão se perde, basta entrar de novo); o Aiven desliga o banco depois de um longo
período sem uso, com aviso por e-mail, e ele é religado no painel. A página não é indexada por
buscadores (`X-Robots-Tag: noindex`).

**Testar a imagem localmente** (com um MySQL local sem TLS):

```bash
docker build -t crud-iti .
docker run --rm -p 8080:8080 -e DB_HOST=host.docker.internal -e DB_NAME=crud_iti \
  -e DB_USER=crud_iti_user -e DB_PASSWORD=sua-senha -e DB_SSL=desligado crud-iti
```

## Testes

```bash
composer test
```

- `LeitorEstruturaTest`: leitura do `structure.json` (níveis, AR repetida, vínculos diretos ignorados,
  situação por vínculo, truncamento, HTML no lugar do JSON, JSON inválido, BOM).
- `ArFormTest`: validação da seleção múltipla de AC N2 (regressão).
- `ArTest`: vínculos N:N sem duplicar, situação de cada vínculo preservada na edição, situação geral.
- `VinculoArAcN2IntegracaoTest` (grupo `integracao`, usa o MySQL configurado dentro de uma transação
  desfeita ao final; é pulado sem banco): FK bloqueando a exclusão de AC N2 com AR, troca de vínculos
  no mesmo flush, exclusão de AR e reimportação idempotente.
- `LoginFormTest`: campos enviados como lista são recusados sem erro 500.
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
