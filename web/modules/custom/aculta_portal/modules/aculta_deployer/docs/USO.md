# Uso

Todos os comandos rodam a partir da raiz do repositório, com a CLI do submódulo.

```
CLI=web/modules/custom/aculta_portal/modules/aculta_deployer/bin/aculta-deployer
```

## Comandos

- `$CLI check [--strict]`: valida o registro, as fronteiras e o escopo, e lista as
  entradas abertas. Com `--strict`, sai com código 2 se houver entrada bloqueante aberta.
- `$CLI boundaries`: só a verificação de fronteiras.
- `$CLI list`: lista as entradas do registro.
- `$CLI register --kind=K --page=P --current=C --expected=E --reason=R --owner=O [--blocking]`:
  acrescenta uma entrada. Os campos são validados antes de gravar.
- `$CLI build --target=production --out=DIR [--allow-open-blocking]`: gera a árvore de produção em `DIR`, com a troca de hosts e o perfil `production`.
- `$CLI build --target=test --out=DIR`: gera a árvore de teste com o perfil `test` (hosts de teste preservados).
  - `DIR` não pode ficar dentro do repositório.
  - Com entradas bloqueantes abertas, o build é recusado. `--allow-open-blocking` serve
    só para ensaio, e não para publicar.
  - Grava `DIR/deploy-report.json` com os arquivos, as remoções e as substituições.
- `$CLI hostinger-layout --domain-root=DIR [--project-name=aculta-site]`: valida a instalação e simula a mudança da raiz pública. Só altera o servidor com `--apply`.
- `$CLI verify`: GET somente leitura nas entradas com `probe` e `expect`. Só aceita HTTPS,
  sem credenciais na URL, sem seguir redirecionamentos. Não altera nenhum arquivo nem o registro.
- `$CLI version`.

## Procedimento de deploy

1. `$CLI check --strict`. Precisa passar; se não, resolva ou registre as entradas.
2. `$CLI build --out=/caminho/de/producao`.
3. Confira `deploy-report.json`: nenhum `toca.net.br` deve restar nos arquivos gerados.
4. Aplique a árvore gerada no ambiente de produção, pelo processo do responsável.
5. Após o deploy, rode `$CLI verify`. Entradas que passam podem ser marcadas `status: resolved`
   no registro, com a decisão registrada.
6. Rollback: cada build fica em seu próprio diretório. Para voltar, aponte o ambiente de
   produção para o diretório do build anterior, pelo processo do responsável. Builds nunca
   são sobrescritos.

## Estrutura Hostinger Cloud Startup (0.1.23)

Na Hostinger, mantenha a instalação Composer numa pasta própria, filha da pasta do domínio,
e faça `public_html` apontar somente para `web/`:

```text
<pasta-do-dominio>/
├── aculta-site/                 # composer.json, composer.lock, vendor/, config/ e web/
│   └── web/                     # raiz servida; index.php e .htaccess do Drupal
└── public_html -> aculta-site/web
```

O caminho absoluto da pasta do domínio varia por conta. Descubra-o em hPanel → FTP Accounts;
confirme que SSH está habilitado e que a função `symlink` foi removida de `disableFunctions`
no PHP Configuration do site. Hostinger Web/Cloud desabilita `symlink` por padrão. Use a conta
SSH da hospedagem, não `root`.

Transfira primeiro a instalação completa para `<pasta-do-dominio>/aculta-site`, instale as
dependências do `composer.lock` e provisione os settings/secrets do ambiente. O comando abaixo
somente troca a raiz pública; ele não transfere o projeto, não executa Composer e não altera o
banco:

```sh
cd "<pasta-do-dominio>/aculta-site"
CLI=web/modules/custom/aculta_portal/modules/aculta_deployer/bin/aculta-deployer
php "$CLI" hostinger-layout --domain-root="<caminho-absoluto-da-pasta-do-dominio>"
php "$CLI" hostinger-layout --domain-root="<caminho-absoluto-da-pasta-do-dominio>" --apply
```

A primeira execução é somente leitura. Antes de `--apply`, revise o caminho e o status. O
comando exige `composer.json`, `composer.lock`, `vendor/autoload.php`, `web/index.php` e
`web/sites/default/settings.php`. Se `public_html` já existir como pasta ou symlink, ele é
renomeado para `public_html.backup.<UTC>`; nenhum conteúdo é apagado. A ativação usa symlink
temporário e renomeação, é idempotente e tenta restaurar o backup se a criação falhar.

Depois da ativação, valide o domínio HTTPS, rotas Drupal, arquivos estáticos, acesso de escrita
em `web/sites/default/files`, proteção de arquivos privados e o mecanismo de renovação TLS. Se
o `public_html` anterior continha `.well-known` ou validações de domínio, esses arquivos ficam
no backup e precisam ser avaliados antes da troca; não copie outros conteúdos antigos para a
raiz pública sem revisão.

Rollback manual: pelo SSH, mova o symlink atual para um nome de falha e restaure exatamente o
`public_html.backup.<UTC>` exibido pelo comando. `mv public_html public_html.failed` move o
symlink, não o destino. Nunca use `rm -rf` sobre o symlink ou sobre o backup. Teste o rollback
antes da janela de produção.

Referências: [Drupal — relocated web root em hospedagem compartilhada](https://www.drupal.org/docs/getting-started/system-requirements/relocated-web-root-on-shared-hosting),
[Hostinger — criar symlink](https://www.hostinger.com/support/1583694-how-to-create-a-symlink-at-hostinger/),
[Hostinger — habilitar funções PHP](https://www.hostinger.com/support/3212034-how-to-enable-disabled-php-functions-in-hostinger/).

## Testes

```
php web/modules/custom/aculta_portal/modules/aculta_deployer/tests/run.php
```

## Indexação (política por ambiente)

- `$CLI robots --env=production`: GET em todos os hosts de produção; espera **ausência** de
  `X-Robots-Tag` com noindex.
- `$CLI robots --env=test`: espera noindex nos hosts de teste.
- `$CLI robots --env=production` também confere os **caminhos privados** (`private_probes`):
  conta, login (`/entrar`), painel, carrinho e checkout. Cada um deve ter noindex no
  cabeçalho ou `<meta name="robots">` no HTML, ou status 401, 403, 404 ou 410 (não indexável).
  Resposta 200 sem noindex falha.
- A verificação é GET somente leitura, sem seguir redirecionamentos, em HTTPS.
- A lista de hosts, a política e os caminhos privados ficam em `config/deploy.json`
  (`robots_policy`).

### Por que o deployer confere o meta robots

O noindex das páginas privadas é emitido pelo Portal por rota (`meta robots`).
Uma checagem só de `X-Robots-Tag` deixaria de ver essas páginas; por isso a
verificação lê o HTML e aceita o noindex no cabeçalho ou no documento, sem
depender do servidor web. O Homelab usa Apache e a produção Hostinger Cloud
Startup usa LiteSpeed; o deployer não configura nenhum dos dois.

### Por que 404/410 contam como não indexável

Um caminho que responde 404 para o anônimo não tem conteúdo indexável. A checagem aceita
esses status para que a lista de caminhos não precise espelhar rotas que não existem em
produção. Um 200 sem noindex é sempre falha.

## Ambiente do site (0.1.8)

- `$CLI environment` mostra o ambiente atual. `$CLI environment set --to=production` ou `--to=test` grava o ambiente e o
  endereço do site em `var/deployer/environment.json`. Sem o arquivo, as funções usam `test`.
- O Portal lê esse arquivo para escolher o conjunto de credenciais obrigatórias.

### Comportamento por função e ambiente

| Função | Teste | Produção | Observação |
| --- | --- | --- | --- |
| `environment` | define `test` | define `production` | grava o arquivo neutro |
| `robots` | confere noindex nos hosts de teste | confere ausência de noindex e `Sitemap:` do índice | `--env` ou arquivo |
| `sitemap` | confere o índice na base de teste | confere o índice na base de produção | `--env` ou arquivo |
| `report` | ambiente e endereço de teste | ambiente e endereço de produção | sem rede, sem valores |
| `build` | `--target=test` gera a saída de teste | `--target=production` (padrão) gera a saída de produção | recusa saída dentro do repositório |
| `verify` | probes do registro convertidas para os hosts de teste | probes do registro de produção | somente leitura |
| `check` | valida o registro e o escopo | valida o registro e o escopo | não depende do ambiente |
| `boundaries` | independe do ambiente | independe do ambiente | |

`build` aceita `--target`; `--env`, se informado, deve ser igual ao alvo. `verify` respeita o ambiente.

## Perfis por ambiente (guardrail)

Configuração que muda entre teste e produção não é editada à mão: está em `config/deploy.json`.

- `environment_bound` lista os arquivos do `config/sync` que dependem de ambiente (SMTP, backend de e-mail, e-mail do site e do formulário, gateway Mercado Pago, nível de erro).
- `profiles.production` e `profiles.test` têm uma regra por linha alterada (`file`, `pattern`, `with`).
- O `build` aplica o perfil do alvo. Cada regra precisa casar exatamente uma linha; caso contrário, o build é recusado.
- O `check` é o guardrail: falha se um arquivo de `environment_bound` não tiver regra em todos os perfis, se um endereço fixo de domínio do projeto aparecer em arquivo que não é dependente de ambiente, ou se alguma regra deixar de casar.
- Para incluir uma nova configuração dependente de ambiente: declare o arquivo em `environment_bound` e crie a regra nos dois perfis no mesmo PR.

## Painel do Portal (0.1.6)

- `$CLI report` grava `var/deployer/status.json` (ignorado pelo Git). A página `/admin/config/aculta/deployer`
  (permissão `administer aculta deployer`) lê esse arquivo e mostra fronteiras, correções abertas e credenciais
  por nome e estado, com o mesmo sistema de status do diagnóstico (✔, ⚠, ✖).
- Gere o relatório antes de consultar o painel. Sem o arquivo, a página informa que o relatório está indisponível.
- O Portal não executa a ferramenta. Verificações de rede (sitemap e robots) continuam como comandos próprios.

## Credenciais (0.1.7)

- Não há comandos de importação ou exportação de credenciais na ferramenta. O cadastro é feito no painel
  "Credenciais do ambiente" do Portal (ver `docs/operations/SECRETS.md`).
- O relatório não inclui credenciais. A situação das credenciais aparece só na página "Credenciais do ambiente" do Portal.
- Em produção, a camada criptografada no banco é provisionada pela ferramenta após o deploy (fase 9, pendente).

## Descoberta e sitemaps (0.1.3)

- `$CLI sitemap --env=production`: GET no índice (`sitemap.production.index_url`), nos filhos
  e nos hosts de conteúdo. Confere (1) que cada filho do índice está na base do ambiente
  (`index_base`) e (2) que as URLs de conteúdo pertencem aos hosts da política de produção e
  que esses hosts respondem (cross-host).
- `$CLI sitemap --env=test`: o mesmo, com a base do servidor de testes. Os filhos continuam
  com URLs canônicas de produção, por desenho do Portal; só o índice segue a base do ambiente.
- `$CLI robots --env=production` também confere o `robots.txt` de cada host: diretiva
  `Sitemap:` com o índice de produção e nenhum `Disallow: /`.

### Provisionamento por ambiente (problema 1)

- Produção: `simple_sitemap.settings:base_url` é `https://aculta.org` em `config/sync`.
- Servidor de testes: o runtime define `base_url` como `https://aculta.toca.net.br`, por
  `drush config:set` no runtime (não versionado, operação de runtime). Sem isso,
  `sitemap --env=test` falha com "índice aponta para aculta.org".
- Qualquer nova divergência de host, base, `robots.txt` ou sitemap entre ambientes deve ser
  implementada e verificada aqui (regra nas instruções de IA do projeto).

