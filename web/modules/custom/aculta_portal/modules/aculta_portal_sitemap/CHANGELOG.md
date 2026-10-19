# Changelog — ACULTA Portal Sitemap

## 0.1.1 — endereço do sitemap por ambiente (acabamento) — 2026-10-09

- A `base_url` do sitemap segue o endereço do site que o ACULTA Deployer define (`var/deployer/environment.json`),
  por override em `settings.local.php`. A configuração exportada permanece com produção (`https://aculta.org`); o
  ajuste manual no runtime de teste foi removido, e não há mais drift em `simple_sitemap.settings`.
- Verificado no servidor de testes: índice com 5 filhos (`https://aculta.toca.net.br/<variante>/sitemap.xml`);
  `aculta-deployer sitemap --env=test` PASS; `robots --env=test` PASS; homologação `url.site` PASS; testes do submódulo PASS.
- Pendências que dependem de decisão ou de produção: indexar `/wiki/verbetes` (decisão do responsável);  DEP-0002 (apoio cross-host) e verificação no Search Console
  (produção, RC).

## 0.1.0-I/J — documentação consolidada e homologação url.site — 2026-10-09

- I: ADR-009 com a regra de deploy só no RC; `README.md` e `docs/operations` alinhados (DEPLOYMENT, RELEASES, AGENTS, deployer).
- J: `tests/homologacao-url-site.sh` alterna seis hosts por cinco ciclos (raiz e `/entrar`) e compara título, canonical e meta robots com a linha de base. Resultado no servidor de testes: PASS (sem vazamento de cache entre hosts). Expectativas de robots conferidas: WIKI, CURSOS e APOIO sem noindex; `/entrar` com noindex.
- Limite da J: no servidor de testes, o canonical de `aculta.toca.net.br` e de `apoio` aponta para o host de teste, e o de WIKI, CURSOS e MAGAZINE para produção. É estável entre ciclos (não é cache), e em produção o host da requisição é o canônico. Recomenda-se revisar na homologação do RC.
- Deploy: somente no RC de todos os módulos, temas e subtemas.

## 0.1.0-H — descoberta: robots.txt e índice no ambiente — 2026-10-09

- `web/robots.txt` anuncia `Sitemap: https://aculta.org/sitemap.xml` (índice central). Não há Disallow de purpose públicos.
- Verificação da descoberta no `aculta_deployer` 0.1.3: `robots --env=production` (diretiva Sitemap e ausência de `Disallow: /`) e `sitemap --env=production|test`.
- Pendências: deploy no RC (todos os módulos, temas e subtemas), não antes; cross-host (apoio, wiki420, coletivo420, cursos) só responde após DEP-0001/0002/0003; verificação no Search Console fora do código.

## 0.1.0-G — índice central e promoção de /sitemap.xml — 2026-10-09

- `PurposeNodeUrlGenerator`: MAIN passa a ter raiz própria (`ROOT_PURPOSES`) e caminho público
  `/contato` (`PUBLIC_PATHS`). Com isso o MAIN tem a mesma cobertura do sitemap institucional anterior.
- Variante `index` habilitada; `simple_sitemap.settings:default_variant` = `index`; `/sitemap.xml` entrega o índice.
- Variante `default` desabilitada (`/sitemaps/default/sitemap.xml` retorna 404 no servidor de testes).
- Variantes `main`, `support`, `wiki`, `magazine`, `courses` e tipo `aculta_purpose` exportados para `config/sync`,
  junto com `aculta_portal_sitemap` em `core.extension`.
- Verificado no servidor de testes: `/sitemap.xml` com os cinco filhos; MAIN 12, SUPPORT 1, WIKI 5, MAGAZINE 1, COURSES 1.
- Pendências: decisão sobre `base_url` por ambiente; cross-host (apoio.aculta.org) depende de verificação no Search Console; deploy em produção não feito.

## 0.1.0-F — expansão para WIKI, MAGAZINE e COURSES — 2026-10-09

- `INDEXABLE_PURPOSES` passa a incluir `magazine`, `wiki` e `courses`; SHOP continua fora.
- `ROOT_PURPOSES` passa a incluir a raiz de cada purpose indexável (URL própria, rota).
- `isPurposeFrontNode()`: a página inicial do domínio (`system.site:page.front` da coleção
  `domain.<id>`) é excluída do conjunto de nós. Corrige `/noticias` do MAGAZINE, que redirecionava
  para a raiz.
- Dependência `domain_config` declarada em `aculta_portal_sitemap.info.yml`.
- Variantes de piloto no runtime: `wiki`, `magazine` e `courses`.
- Verificado no servidor de testes: MAIN 11 URLs; SUPPORT 1; WIKI 1 (`https://wiki420.aculta.org/`);
  MAGAZINE 1 (`https://revista420.aculta.org/`); COURSES 1 (`https://cursos.aculta.org/`).
  Total final: 18 URLs (MAIN 10, SUPPORT 1, WIKI 5, MAGAZINE 1, COURSES 1), sem duplicatas.
- Verbetes da WIKI incluídos (4): o acesso é avaliado no contexto do purpose de origem, via
  `DomainPurposeManager::runInPurpose()` (Portal 0.2.0-dev.8). Antes, o gerador rodava no contexto do MAIN
  e o hook `entity_access` negava `wiki_entry`. As concessões de `node_access` foram reconstruídas (autorizado).
- `/inicio` removido do MAIN: a página inicial global (`system.site:page.front` = `/node/1`) agora é excluída.
- Não promovido, não exportado para `config/sync`. Pendência: fase G (índice).

## 0.1.0-E — sitemaps piloto MAIN e SUPPORT — 2026-10-09

- Plugin `aculta_purpose_node` com injeção de dependências e regras R1 a R5a e R7.
- Tipo `aculta_purpose`, variantes `main` e `support`, e raiz do SUPPORT como URL própria.
- Verificado no servidor de testes: MAIN com 11 URLs canônicas de produção; SUPPORT com
  `https://apoio.aculta.org/`; sitemap institucional `default` inalterado (13 URLs).
- `DomainPurposeManager::canonicalPathUrl()` adicionado ao Portal (0.2.0-dev.7).
- Não promovido, não exportado para `config/sync`. Pendências: fase F (expansão) e G (índice).
