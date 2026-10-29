# ACULTA Portal Sitemap

Submódulo do `aculta_portal` que integra o sitemap multidomínio da plataforma (linha 0.1.x,
ADR-009 em `docs/decisions/`).

## Estado (0.1.2: sitemap multidomínio completo no servidor de testes)

Versão do `info.yml` e do CHANGELOG: `0.1.2`. Verificações recentes: `php tests/run.php` (13 verificações), homologação `url.site` (5 ciclos, 6 hosts, 2 caminhos) e leitura HTTP local do checkout servido (código `662c62d43`, anterior à implementação D-49) do índice e de seus sete filhos. Todos responderam `200`, com `X-Robots-Tag: noindex`; as 29 URLs listadas usam hosts de teste registrados no ACULTA Deployer. Não foi feita validação externa no Search Console nem acesso a hosts de produção.

- Endereço por ambiente: a `base_url` segue o site definido pelo ACULTA Deployer (`var/deployer/environment.json`).

## Detalhes da microfase 0.1.0-G (índice central e promoção de /sitemap.xml)

- Variante `index` habilitada; `default_variant = index`, então `/sitemap.xml` entrega o índice.
- Índice lista as variantes por purpose: main, support, wiki, magazine e courses (URLs `/<variante>/sitemap.xml`,
  alias criado pelo `simple_sitemap` para `/sitemaps/<variante>/sitemap.xml`).
- Variante `default` (sitemap institucional anterior) desabilitada. `/sitemaps/default/sitemap.xml` responde 404.
- Paridade com o sitemap institucional anterior: home do MAIN (`https://aculta.org/`) e `/contato`
  (webform) entram no MAIN. Antes da G, a home do MAIN faltava.
- Config exportada para `config/sync`: variantes, tipo `aculta_purpose` e `default_variant`. O módulo consta em
  `core.extension` com valor `0` (desabilitado): a habilitação é ato de deploy e não está exportada.
- A `base_url` do `simple_sitemap` é `https://aculta.org` também no servidor de testes. O índice de testes
  aponta para produção; o servidor de testes é noindex.


- Plugin `aculta_purpose_node` (`PurposeNodeUrlGenerator`): gera URLs canônicas de nós por
  purpose de origem (`field_domain_source`), com regras R1 a R5a e R7 da política de indexação.
- Raiz de cada purpose indexável (SUPPORT, MAGAZINE, WIKI, COURSES) incluída como URL própria,
  porque a página inicial é uma rota, não um nó (`ROOT_PURPOSES`).
- Página inicial de domínio definida em `system.site:page.front` da coleção do domínio é
  excluída do conjunto de nós, para não gerar URL que redireciona para a raiz.
- Verbetes da WIKI entram quando o acesso anônimo é permitido no contexto do purpose de origem (R4),
  avaliado com `DomainPurposeManager::runInPurpose()`.
- SHOP fica fora (sem conteúdo público e sem página inicial).
- Tipo de sitemap `aculta_purpose`; variantes `main` e `support`, com o purpose nas
  configurações de terceiros de cada variante.
- Endpoints pilotos: `/sitemaps/{main,support,magazine,wiki,courses}/sitemap.xml`.
- O sitemap institucional `default` (`/sitemap.xml`) não foi alterado.
- **Não promovido:** o índice central e a troca de `/sitemap.xml` pertencem à fase G.
- **Habilitação:** o módulo está habilitado no runtime de desenvolvimento para o piloto. A
  habilitação e as variantes não foram exportadas para `config/sync` (ato de deploy, a decidir).

## Dependências

`aculta_portal` (serviço `aculta_portal.domain_purpose`), `simple_sitemap` 4.x e `domain`.

## Homologação (0.1.0-J)

- `tests/homologacao-url-site.sh [CICLOS] [IP]`: regressão de cache por `url.site` no servidor de testes (PASS).

## Próximas etapas

- Próxima etapa técnica: testar `isPurposeFrontNode()` com configurações de front-page por domínio em Kernel e concluir a revisão pré-RC. DEP-0002 está resolvida no servidor de testes; a sonda de produção e o Search Console são verificações Beta, conforme [ciclo vigente](../../../../../../docs/operations/RELEASE-LIFECYCLE.md).

## Verificação de desenvolvimento — 2026-10-29

- `php tests/run.php`: 13 verificações PASS.
- `tests/homologacao-url-site.sh 5 127.0.0.1`: PASS, 5 ciclos, 6 hosts e 2 caminhos.
- GET somente em `127.0.0.1`, com Host dos testes: `/sitemap.xml` e sete sitemaps filhos retornaram HTTP 200 e `X-Robots-Tag: noindex, nofollow, noarchive`; os filhos continham 29 URLs ao todo, todas em hosts de teste declarados pelo Deployer.
- A checagem comparou os hosts com `domain_transition.entries` e `robots_policy.test.hosts`; o segundo inventário isolado não contém todos os purposes, como `cursos`.
- D-49 (2026-10-29) decidiu incluir `/wiki/verbetes` na variante `wiki`; a rota foi adicionada à allowlist pública e coberta pela suíte. DEP-0002 está resolvida no servidor de testes; a sonda de produção e o Search Console ficam para Beta. Falta um teste Kernel de `isPurposeFrontNode()` por domínio. O módulo continua em Dev.
