# Deployment

Data da revisão: 2026-10-07.

Runbook canônico de deployment da plataforma ACULTA.

## Baseline

- Homelab: Debian + Apache + PHP-FPM + MariaDB.
- Produção: Hostinger Cloud Startup + LiteSpeed + PHP + MariaDB.
- o deploy transfere código, dependências travadas e configuração aprovada;
- Estados (backups do banco) são artefatos de desenvolvimento e nunca são enviados para produção;
- produção não é ambiente de experimento.

## Pré-condições

Antes da janela:

- SHA/tag aprovado;
- backup de banco e arquivos;
- rollback conhecido;
- Composer/security status conhecido;
- config drift classificado;
- `updatedb:status` conhecido;
- segredos provisionados pelo mecanismo de ambiente/Key;
- nenhum Critical/High aberto do escopo.

Ver [RELEASES.md](RELEASES.md), [HARDENING.md](HARDENING.md) e
[SECRETS.md](SECRETS.md).

## Checklist de ambiente (2026-10-09)

Passos que cada ambiente precisa, além do código e da configuração:

1. **Serviço do site (CEP).** Em `settings.php` ou no arquivo de settings do ambiente, incluir:
   `$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.aculta.yml';`
   Sem a linha, um site novo montado a partir de `config/sync` não instala (o `cep_autocomplete` precisa do canal definido em `services.aculta.yml`). Verificação: `drush php:eval 'print (int) \Drupal::hasService("logger.channel.cep_autocomplete");'` deve imprimir `1`.
2. **Turnstile.** Cada ambiente usa a própria chave: produção `TURNSTILE_KEYS_JSON`; teste `TURNSTILE_TEST_KEYS_JSON`. Só há dois ambientes (teste e produção). A escolha é automática pelo `aculta-deployer environment set`. Nunca copiar a chave de teste para produção.
3. **Busca (F3).** Depois do `config:import`, reindexar: `drush search-api:index aculta_conteudo`. A consulta usa o banco; não há serviço externo.
3. **Conteúdo da home.** Dry-run: `php ../vendor/drush/drush/drush.php php:script load-home-content --script-path=../scripts/content/institution`. Aplicar com `ACULTA_APPLY=1` no mesmo comando. Uma segunda execução não deve alterar nada.
4. **Dependências.** `composer install` com scripts. O scaffold preserva `web/robots.txt` (a linha de Sitemap do projeto) desde o PR da F3.

## Configuration Sync

`config/sync` é a configuração desejada aprovada do projeto.

Regras:

- não usar export em massa apenas para “limpar” drift;
- não usar Config Split/Ignore como atalho sem decisão arquitetural;
- secrets nunca entram em Configuration Sync;
- comparar paths inesperados antes de importar/exportar;
- conteúdo Drupal não é substituído por configuração.

## Transição teste → produção (perfis do deployer)

Configuração que muda entre ambientes (SMTP e backend de e-mail, remetente, gateway Mercado Pago, nível de erro)
é de responsabilidade do `aculta_deployer`, não de edição manual. O `build --target=production` aplica o perfil
de produção no deploy, e `build --target=test` aplica o de teste. O `check` falha se algum arquivo dependente de
ambiente não estiver coberto nos dois perfis. Detalhes e regra para novas configurações: `aculta_deployer/docs/USO.md`.

## Multidomínio

Todos os purposes ativos apontam para a mesma aplicação Drupal, banco e sessão:

| Purpose | Produção |
| --- | --- |
| MAIN | `aculta.org` |
| ACCOUNT | `conta.aculta.org` |
| SUPPORT | `apoio.aculta.org` |
| MAGAZINE | `revista420.aculta.org` |
| WIKI | `wiki420.aculta.org` |
| SHOP | `loja.aculta.org` |
| COURSES | `cursos.aculta.org` |

FORUM só entra quando efetivamente ativado.

Antes de habilitar integrações externas, confirmar DNS/TLS dos hosts envolvidos.
A política de cookie compartilhado só se aplica a hosts ACULTA sob a mesma
aplicação e trust boundary.

## Servidor web por ambiente

No Homelab, validar o document root `web/`, `web/.htaccess`, `mod_rewrite`,
`mod_headers`, PHP-FPM via `proxy_fcgi`, proteção de arquivos e VirtualHosts.

Na produção Hostinger Cloud Startup, validar que LiteSpeed processa o
`web/.htaccess`, as regras de rewrite e headers, o handler PHP e a proteção de
arquivos públicos/privados. A resposta pública de `aculta.org` confirmou
`platform: hostinger` e `x-turbo-charged-by: LiteSpeed` em 2026-10-15.
O cabeçalho `server` indica Cloudflare à frente, portanto não é evidência do
servidor de origem.

Não copiar literalmente paths, usuários, certificados ou configuração de
VirtualHost entre Homelab e Hostinger. A produção não herda o noindex do
Homelab.

## Sequência geral

1. criar restore point/backup;
2. disponibilizar o SHA/tag aprovado;
3. instalar as dependências do lockfile sem update global;
4. executar database updates quando necessários;
5. importar a configuração aprovada;
6. executar provisioners de conteúdo somente quando explicitamente requeridos;
7. reconstruir cache;
8. verificar cron/queues;
9. executar smoke HTTP/Domain;
10. revisar logs;
11. confirmar config/updatedb finais.

Comandos exatos podem variar por ambiente, mas a ordem de responsabilidades
acima deve ser preservada.

## Provisionamento de conteúdo

Conteúdo não deve ser “fabricado” por Configuration Sync.

Scripts atualmente mantidos em `scripts/institution/`:

- `provision-commerce-store.php`: provisionamento idempotente da Store canônica;
- `provision-lms-pilot.php`: provisionamento do curso piloto LMS quando
  explicitamente necessário.

Antes de executar qualquer provisioner:

- ler o cabeçalho do script;
- confirmar que a entidade ainda não existe;
- confirmar que os dados canônicos esperados ainda são válidos;
- não criar duplicata para contornar falha de provisioning.

## Integrações externas

Após código/configuração e somente com credenciais corretas do ambiente:

- SMTP;
- Turnstile;
- Google OAuth;
- Mercado Pago;
- outras integrações ativas.

Cada integração deve ser homologada segundo sua documentação específica.

Não habilitar gateway ou serviço externo apenas porque o código foi deployado.

## Mercado Pago

Commerce continua fonte de verdade financeira.

Antes de habilitar pagamentos:

- credenciais pelo contrato de secrets;
- webhook validado;
- assinatura obrigatória;
- idempotência/retry revisados;
- order/payment access validado;
- logs sem material sensível.

Não editar contrib para “adaptar” produção.

## Pós-deploy

Smoke mínimo dos purposes ativos:

- MAIN;
- ACCOUNT login;
- SUPPORT;
- MAGAZINE;
- WIKI;
- COURSES;
- SHOP conforme escopo efetivamente publicado.

Adicionar aos smokes as integrações alteradas pelo release.

## Rollback

Definir antes da janela:

- rollback de código;
- lockfile/dependências;
- configuração;
- banco;
- arquivos;
- cache.

Se uma migração de banco não for reversível, isso precisa estar explícito antes
do deploy.

## Evidência

Evidência de uma rodada pertence ao PR/release, não a um novo relatório
permanente neste repositório.

## Indexação por ambiente

Política aprovada pelo responsável em 2026-10-09.

- **Produção indexável:** todos os domínios e subdomínios de produção (`aculta.org`,
  `conta.`, `apoio.`, `revista420.`, `wiki420.`, `loja.`, `cursos.`) não enviam `X-Robots-Tag`
  com `noindex`.
- **Servidor de testes não indexável:** `*.aculta.toca.net.br` envia `noindex, nofollow, noarchive`.
- **Páginas privadas da conta** continuam com `noindex` em nível de rota (meta `robots` do
  `aculta_portal`). Essa proteção não é removida pelo deploy.
- **Quem garante:** o `aculta_deployer` (`robots --env=production|test`, `build` com trava de
  política). Verificação pós-deploy por host; resultado FAIL bloqueia a publicação.
- Correção registrada: DEP-0003 (VirtualHost de teste com noindex) permanece bloqueante até o
  `robots --env=production` passar em todos os hosts.

**Produção (decisão do responsável):** a entrega em produção não é requisito de fase, PR, release, RC nem pendência do agente. O responsável decide quando o projeto vai para produção, no fim de todo o projeto. Merge em `main` atualiza o código do repositório e não publica nada no Hostinger. As verificações de produção (`robots --env=production`, `sitemap --env=production`) são ensaios, não ações de deploy.

Procedimento (somente no RC): `aculta-deployer check --strict`, `build --out=DIR`, aplicar o deploy,
depois `aculta-deployer robots --env=production` e `aculta-deployer sitemap --env=production`
(devem passar em todos os hosts) e `aculta-deployer robots --env=test` (hosts de teste com noindex).
