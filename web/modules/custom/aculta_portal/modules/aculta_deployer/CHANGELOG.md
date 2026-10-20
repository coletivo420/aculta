## 0.1.27 — provisionamento de segredos e webhook do Brevo, idempotente

- `aculta-deployer provision --env=production|test --secrets=CAMINHO`: gera `BREVO_WEBHOOK_TOKEN` e `MAILER_PAYLOAD_KEY` se faltarem (nunca sobrescreve), cadastra o webhook transacional ou troca o token do existente, e remove o cadastro de teste quando pedido (`--remove-test=ID`). Dry-run por padrão; produção exige `--confirm-production`.
- Chamadas ao Brevo: `GET /v3/webhooks`, `POST /v3/webhooks`, `PUT /v3/webhooks/{id}` (atualização do token), `DELETE /v3/webhooks/{id}` (204). Confirmadas na referência oficial.
- Validado: dry-run do ambiente de teste com o arquivo real de segredos: "webhook já cadastrado; nada a fazer". Recusas de segurança verificadas.
- `external_webhooks.production.action` descreve o comando do deploy. Testes unitários da lógica (faltantes, plano, gravação sem sobrescrever) incluídos.

## 0.1.26 — webhooks por ambiente e ferramentas Drush — 2026-10-20

- `external_webhooks` em `config/deploy.json`: webhook do Brevo declarado por ambiente. A URL sai de `environments` + `path`; o segredo é `BREVO_WEBHOOK_TOKEN`. Produção fica `pendente` até o cadastro no deploy do RC (passos em `docs/USO.md`).
- `aculta-deployer webhooks`: leitura que confere a declaração contra o contrato de segredos. Exige o segredo obrigatório em produção antes do cadastro.
- Ferramentas Drush em `drush/Commands/AculaDeployerCommands.php` (`aculta-deployer:check`, `:boundaries`, `:webhooks`, `:cookies`, `:domains`, `:robots`, `:sitemap`). Chamam a CLI standalone e repassam o código de saída; não exigem o módulo habilitado no Runtime.
- Não altera o Runtime nem chama o provedor: o cadastro de produção segue no procedimento de deploy.
- Correções dos testes do deployer, sem exceções: a validação de perfis não exige regra para alias que a produção remove (`drop`); regras explícitas de remetente, sitemap e `@id` de organização nos perfis de teste e de produção; host de teste nas regras de `links` do perfil de teste (antes escrevia o host de produção); arquivos declarados sem host removidos de `environment_bound`; expectativas dos domínios de produção (oito) e dos caminhos privados (oito) alinhadas à política declarada.

## 0.1.25 — substituição e remoção da raiz pública anterior — 2026-10-15

- `hostinger-layout --apply` substitui diretórios ou symlinks `public_html` por `public_html -> web` relativo e exclui o conteúdo anterior após a ativação.
- A remoção não segue symlinks para fora de `public_html`.
- Remove o caminho de backup, a mensagem de preservação e a rotina que gerava nomes de backup.
- Revisa fixture para confirmar remoção do conteúdo e preservação do destino externo de symlink.
- Validação focada da troca de diretório e segurança do destino externo passou; gates Drupal 11+ (768 verificações) e endereços de ambiente (1702 arquivos) passaram no checkout conectado ao Runtime de testes.

## 0.1.24 — symlink público relativo — 2026-10-15

- `hostinger-layout` ativa `public_html -> web` com destino relativo e o reconhece de forma idempotente.
- Atualiza fixture e versão da CLI para 0.1.24.

## 0.1.23 — raiz pública Hostinger por symlink — 2026-10-15

- Adiciona `hostinger-layout --domain-root=DIR [--apply]`.
- A instalação Composer fica diretamente na raiz da pasta do domínio; `public_html` aponta para `<pasta-do-domínio>/web`.
- A execução é dry-run por padrão. Com `--apply`, uma pasta/symlink `public_html` existente é renomeada para backup antes da ativação; nada é apagado e falha na ativação tenta restaurá-la.
- Recusa instalação incompleta e destinos que sejam arquivo comum; repetição quando o symlink já está correto é idempotente.
- Testes determinísticos da simulação, preservação, ativação, idempotência e recusa de instalação incompleta.
- Nenhuma alteração foi aplicada na Hostinger ou no Runtime do Homelab.

## 0.1.22 — plataforma LiteSpeed da Hostinger — 2026-10-15

- Registra que produção usa Hostinger Cloud Startup com LiteSpeed, confirmado em cabeçalho HTTP público; Homelab permanece Apache.
- Atualiza DEP-0003 para evitar pressupor configuração Apache no servidor gerenciado; as verificações HTTP do deployer não dependem do servidor.
- Versão sincronizada em `VERSION`, `Cli::VERSION` e `aculta_deployer.info.yml`.
- Nenhuma alteração foi aplicada no servidor de produção; ele já responde com cabeçalho LiteSpeed. Build/testes do deployer não executados nesta etapa.

## 0.1.21 — Política de cookies de sessão por ambiente — 2026-10-14

- `config/deploy.json`: seção `session_cookie` com o `cookie_domain` de cada ambiente (teste `.aculta.toca.net.br`, produção `.aculta.org`) e a transição teste → produção.
- Comando `cookies` (somente leitura): confere o domínio declarado contra `services.homelab.yml` (teste) e o modelo `services.hostinger.yml.example` (produção). Aponta dívidas em `services*.yml` versionados.
- `docs/COOKIES-AMBIENTE.md`: política, transição e dívida (`services.local.yml` com `.aculta.test`).
- `VERSION` e `Cli::VERSION`: 0.1.21.

## 0.1.20 — Purpose LINKS no servidor de testes — 2026-10-12

- `config/deploy.json`: declara `links.aculta.toca.net.br`, transição do purpose e política noindex de teste; produção permanece sem provisionamento DNS.
- `config/sync`: registra Domain, alias de teste, front `/redes-sociais` e sitemap LINKS.
- `VERSION` e `Cli::VERSION`: 0.1.20.

## 0.1.19 — Indexação no relatório de status — 2026-10-11

- `robots` grava o resultado neutro em `var/deployer/indexing.json` (URLs públicas, estados e o valor público de X-Robots-Tag; nunca valores de credencial).
- `status` inclui o resumo `indexing` (ambiente, data da verificação, aprovados, falhas e URLs com falha). Sem arquivo, o resumo é `null`.
- Classe `Indexing` (`src/Indexing.php`), testada em `tests/run.php` sem rede.

## 0.1.18 — Remoção das regras de SMTP e system.mail por ambiente — 2026-10-11

- `config/deploy.json`: removidas as entradas `environment_bound` e as regras de perfil de `smtp.settings` e de `system.mail` (o transporte e o backend agora são iguais nos dois ambientes; ver `EMAIL-TRANSPORT.md`). Regras de `system.site` e demais mantidas.
- `tests/run.php`: build de teste espera `system.mail` no `symfony_mailer` e não gera `smtp.settings`; guardrail de perfil usa `system.site.yml` como arquivo dependente de ambiente.

## 0.1.17 — Fórum: host de teste e de produção no deployer — 2026-10-11

- `config/deploy.json`: `forum_aculta_org` em `domain_transition` (forum.aculta.toca.net.br → forum.aculta.org), na lista de robots de teste (noindex) e nas regras de hostname dos perfis de produção e de teste.
- `config/sync/domain.record.forum_aculta_org.yml` (produção) e `domain_alias.alias.forum_aculta_toca_net_br.yml` (teste, ambiente homelab).
- Script de servidor: `/home/piradopirata/scripts/forum-setup.sh` (fora do Git).
- Testes, `check` e `domains` (7 alinhados, 1 pendente de RC) passam.

## 0.1.16 — Loja: host SHOP do produto e teste de robots — 2026-10-11

- `config/deploy.json`: bloco `domain_transition.shop` (purpose SHOP, rota `entity.commerce_product.canonical`). Loja de teste `loja.aculta.toca.net.br`; produção `loja.aculta.org`.
- `robots_policy.test.hosts`: inclui `loja.aculta.toca.net.br` (noindex verificado pelo `robots --env=test`).
- Versão: `VERSION` e a constante da CLI em 0.1.16.
- Testes: PASS; `check`: PASS; `domains`: 6 alinhados, 1 pendente de RC.

## 0.1.15 — migração de domínios (tabela e comando de leitura) — 2026-10-11

- `config/deploy.json`: bloco `domain_transition` com os 7 domínios do Portal (host de teste → host de produção) e a decisão pendente da revista.
- `domains`: comando somente leitura que confere a tabela contra `config/sync/domain.record.*.yml`. Hoje: 6 alinhados, 1 pendente de RC.
- `docs/MIGRACAO-DOMINIOS.md`: guia da transição, com passos do RC e rollback.
- Versão: `VERSION` e a constante da CLI agora coincidem (a constante estava em 0.1.10).
- Testes: PASS (inclui `domains`); `check`: PASS.
- Não altera produção nem Runtime. A troca de host de produção acontece no deploy do RC.

# Changelog — ACULTA Deployer

## 0.1.14 — transição de ambiente (plano) e sitemap de teste — 2026-10-10

- `transition --target=production|test --out=DIR`: gera o build do alvo e grava `transition-plan.json` com os arquivos de `config/sync` que mudam, os passos de importação parcial, verificação e rollback. Não altera nenhum Runtime.
- `sitemap --env=test`: a lista de hosts permitidos passa a usar os equivalentes de teste (antes comparava com os hosts de produção e reprovava os hosts de teste).
- DEP-0002 resolvida pelo responsável em 2026-10-10: registros de domínio por ambiente importados no Runtime de teste; `sitemap --env=test` PASS. A sonda de produção segue registrada para a verificação pós-deploy.
- DEP-0001 resolvida pelo responsável em 2026-10-10: canonical da página de apoio no teste = `https://apoio.aculta.toca.net.br/` (hostname do registro de domínio por ambiente). A sonda de produção segue para a verificação pós-deploy.
- Correção de fronteira: o plano de transição não cita mais a ferramenta do Drupal no código standalone.
- Testes: PASS; `check`: PASS.

## 0.1.13 — hostnames dos domínios por ambiente (perfis) — 2026-10-10

- Os sete registros `domain.record.*` passam a `environment_bound`, com regra nos perfis `production` (host de produção) e `test` (host de teste). O simple_sitemap usa esse hostname nas URLs dos filhos, então o sitemap de teste passa a listar os hosts de teste depois da aplicação do perfil no Runtime.
- DEP-0002 atualizada: motivo e decisão refletem que o host de produção no sitemap de teste vinha do registro de domínio e é resolvível pelo perfil. Continua aberta até aplicar o perfil de teste no Runtime e verificar a produção.
- Separação de domínios feita pelo deployer: os aliases de teste (`*.aculta.toca.net.br` e `*_test_8080`) continuam no `config/sync` como fonte única. O build de produção os remove (regra `drop`) e não sobra host de teste; o build de teste os mantém. Verificado: 14 aliases no build de teste, 0 no de produção.
- Nota do sitemap de teste em `deploy.json` corrigida (antes dizia que os filhos mantinham URLs de produção de propósito).
- Verificado: `build --target=test` gera `hostname: apoio.aculta.toca.net.br`; `build --target=production` mantém `apoio.aculta.org`; `check` e testes PASS.

## 0.1.12 — correção da sonda de sitemap de SUPPORT (DEP-0002) — 2026-10-10

- DEP-0002: a sonda apontava para `apoio.aculta.org/sitemap.xml`, caminho fora do desenho. O sitemap de SUPPORT é o filho `/support/sitemap.xml` do índice central. A sonda passa a `https://aculta.org/support/sitemap.xml`, e o motivo e a decisão do registro foram atualizados.
- Verificado no servidor de testes com `sitemap --env=test` (PASS): o filho de SUPPORT lista `https://apoio.aculta.org/`, e o host responde 200.
- A entrada continua aberta e bloqueante até a verificação em produção depois do deploy (REGISTRO.md). Nenhuma mudança de código.

## 0.1.11 — perfis por ambiente e guardrail de transição teste → produção — 2026-10-10

- `config/deploy.json`: `environment_bound` (arquivos que mudam entre ambientes) e `profiles` (`production` e `test`) com regras de linha (`file`, `pattern`, `with`).
- `build --target=production|test` (padrão: production). O build aplica as regras do perfil de cada arquivo. Cada regra precisa casar exatamente uma linha; zero ou mais de uma recusa o build. Produção mantém a troca de hosts e a remoção de aliases de teste; teste preserva os hosts de teste.
- `system.mail` em `SMTPMailSystem` (`default` e `webform`) nos dois perfis; remetente por ambiente (`aculta.org` ou `toca.net.br`); gateway Mercado Pago ativo; erros ocultos na tela.
- Guardrail no `check`: falha se um arquivo dependente de ambiente não tiver regra em todos os perfis; se houver endereço fixo de `aculta`/`toca` em arquivo fora de `environment_bound`; se alguma regra não casar exatamente uma vez no `config/sync`.
- Testes: cobertura de perfil, guardrail, aplicação estrita de regra e build de teste.

## 0.1.10 — verify por ambiente; build somente de produção (fase 10) — 2026-10-09

- `verify` respeita o ambiente (`--env` ou o arquivo): no teste, as sondas e os valores esperados do registro são
  convertidos para os hosts de teste (`Verify::toTestEnvironment`). Host parecido com produção não é alterado.
- `build --env=test` é recusado: o build gera somente a saída de produção.
- Testes: conversão de hosts (canonical e host parecido) e recusa do build de teste.

## 0.1.9 — remoção da seção de credenciais do relatório — 2026-10-09

- `report` deixa de incluir a situação das credenciais obrigatórias por ambiente, e a opção `--file` sai com ela.
- A página de status do Portal deixa de mostrar a tabela de credenciais. O cadastro continua na página "Credenciais do ambiente".
- Removido `src/Secrets.php` (sem uso). A checagem de nomes do contrato contra `docs/operations/SECRETS.md` lê o JSON diretamente.

## 0.1.8 — ambiente do site (teste/produção) — 2026-10-09

- Comando `environment [show] | environment set --to=production|test`: grava `var/deployer/environment.json` com o
  ambiente e o endereço do site (`config/deploy.json`, bloco `environments`). Valor desconhecido é recusado.
- `robots`, `sitemap` e `report` usam o mesmo ambiente de trabalho: `--env`, senão o arquivo, senão `test`.
- Relatório do painel inclui ambiente e endereço. O Portal lê o mesmo arquivo para escolher o conjunto de credenciais.
- Testes: set de produção e teste, recusa e restauração do arquivo real.

## 0.1.7 — remoção da importação de credenciais; cadastro pelo painel do Portal — 2026-10-09

- Removidos os comandos `secrets check` e `secrets export` e as funções que liam ou gravavam valores de credenciais.
  O cadastro passa a ser feito no painel "Credenciais do ambiente" do `aculta_portal` (0.2.0-dev.11).
- `Secrets.php` mantém só o formato `NAME=value` e o contrato usados pelo relatório (`report`).
- Em produção, a camada criptografada no banco será provisionada pela ferramenta após o deploy (fase 9 do roadmap). Ainda não implementada.
- Testes: removidos os casos dos comandos removidos; mantidos relatório sem valores, publicação atômica, fronteira e contrato.

## 0.1.6 — complemento do Portal e relatório para o painel — 2026-10-09

- Comando `report [--out] [--file]`: grava `var/deployer/status.json` (esquema 1) com fronteiras, correções abertas
  e credenciais obrigatórias por ambiente (só nomes e estados). Sem rede e sem valores. Publicação atômica, 0640.
- Declarado como complemento opcional do `aculta_portal` (`info.yml`). O Portal não depende do módulo.
- Política de fronteira: a pasta de leitura do painel (`aculta_portal/src/Deployer`) pode citar a ferramenta, mas não
  pode executá-la nem usar seu código (`consumer_tool_reference_allowed` e `consumer_allowed_forbidden`).
- Testes: relatório sem valores, sobrescrita atômica e a exceção de fronteira.

## 0.1.5 — regra de permissão do arquivo de credenciais — 2026-10-09

- `Secrets::modeProblem()` passa a recusar só escrita de grupo (0020) e acesso de outros (0007). Leitura de grupo
  (ACL de leitura do processo web, modo 0640) é aceita, alinhada ao loader do Drupal. Aprovada pelo responsável.
- Testes atualizados: 0640 aceito; 0620, 0664, 0604, 0644 e 0666 recusados.

## 0.1.4 — arquivo local de credenciais e exportação pós-deploy — 2026-10-09

- `src/Secrets.php` e `config/secrets-contract.json` (nomes e ambientes; sem valores).
- `secrets check [--env] [--file]`: valida o arquivo (permissões 0600, fora de `web/`, ignorado pelo Git
  quando está no repositório, sintaxe, nomes do contrato e obrigatórios do ambiente). Só imprime nomes.
- `secrets export --env --out`: grava as variáveis do contrato em arquivo NOVO, 0600, fora do repositório,
  para importação manual pós-deploy. Não sobrescreve.
- Testes: parse, permissões, contenção, coerência do contrato com `docs/operations/SECRETS.md`, e a CLI
  (recusa dentro do repositório, recusa de obrigatórios ausentes, export positivo, sem valores na saída).

## 0.1.3 — descoberta e sitemaps por ambiente (0.1.0-H) — 2026-10-09

- Comando `sitemap --env=production|test` (GET somente leitura): confere o índice central,
  a base de cada filho (problema 1: base de teste em produção ou o inverso) e os hosts das URLs
  de conteúdo, que devem pertencer à política de produção e responder (problema 2: cross-host,
  por exemplo `apoio.aculta.org`).
- `robots --env=production` passa a conferir o `robots.txt` de cada host: diretiva `Sitemap:`
  apontando para o índice de produção e ausência de `Disallow: /`.
- `web/robots.txt` anuncia `Sitemap: https://aculta.org/sitemap.xml`.
- `config/deploy.json` ganha o bloco `sitemap` por ambiente (`index_url` e `index_base`).
- Helpers em `Verify`: `xmlLocs()` (sem entidades externas), `sitemapDirectives()`,
  `disallowsRoot()` e `hostOf()`.
- Testes: 15 asserções novas (XML, XXE, diretivas, host e coerência com `web/robots.txt`).

## 0.1.2 — verificação de caminhos privados e meta robots — 2026-10-09

- `robots --env=production` passa a conferir os caminhos privados (`private_probes` em
  `config/deploy.json`): conta, login, painel, carrinho e checkout.
- Caminho privado passa quando tem noindex no cabeçalho ou em `<meta name="robots">`, ou
  responde 401, 403, 404 ou 410. Um 200 sem noindex falha.
- `Verify::metaNoindex()`, `Verify::statusCode()` e `Verify::isRefusedStatus()` adicionados.
- Testes: asserções de meta robots, status e lista de caminhos privados de produção.
- `sitemap --env=test` verifica os hosts de conteúdo pelo equivalente de teste (`apoio.aculta.toca.net.br`), sem depender de produção: PASS no servidor de testes.
- Documentação em `docs/USO.md` e `docs/GUARDRAILS.md` (motivo da checagem de meta e de 404/410).
- Pendência fora deste submódulo: o Portal não emite `noindex` em `/entrar` (rota
  `user.login`); a regra em `PortalHooks` cobre só rotas `aculta_portal.*`.

## 0.1.1 — política de indexação por ambiente — 2026-10-09

- Comando `robots --env=production|test` (GET somente leitura) por host.
- `build` grava `deploy-policy.json` e recusa política de produção com noindex.
- Política: produção indexável nos sete domínios; teste com noindex.
- Testes da leitura de cabeçalho, da detecção de noindex e da cobertura dos domínios.

## 0.1.0 — primeira versão — 2026-10-09

- Submódulo do `aculta_portal` em `modules/aculta_deployer/`, descoberto e não habilitado.
- CLI standalone `bin/aculta-deployer` com `check`, `boundaries`, `list`, `register`, `build` e `version`.
- Build de produção: substitui `*.aculta.toca.net.br` por `*.aculta.org` (preserva o prefixo) e remove aliases de teste.
- Registro de correções de deploy com validação de campos e ids sequenciais.
- Barreiras de separação verificadas por `boundaries`: tema e Portal (fora do submódulo) não referenciam a ferramenta; a ferramenta não depende de Drupal, Drush, vendor, tema ou Portal.
- Testes determinísticos em `tests/run.php`.
- Entradas iniciais: DEP-0001 (canonical da página de apoio) e DEP-0002 (sitemap sem o host de apoio), ambas bloqueantes e abertas.

