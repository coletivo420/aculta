# Inventário da microfase 0.1.0-A (sitemap multidomínio)

Registro do estado observado em 2026-10-09, antes de qualquer alteração. Leitura
somente: nenhum módulo, configuração ou dado foi alterado.

## Módulos

| Módulo | Versão observada | Situação |
| --- | --- | --- |
| `aculta_portal` | 0.2.0-dev.5 (`info.yml`) | Habilitado. |
| `aculta_deployer` (submódulo) | 0.1.0 | Descoberto, não habilitado. |
| `simple_sitemap` | 4.2.3 (instalado) | Habilitado. Submódulos `simple_sitemap_engines` e `simple_sitemap_views` presentes; nenhum de domínio. |
| `domain` | presente em `web/modules/contrib/domain` | Habilitado. |
| `domain_simple_sitemap` | não instalado | Candidato da microfase C. |

## Configuração do sitemap (runtime)

- `simple_sitemap.sitemap.default`: ativo (`status: true`).
- `simple_sitemap.sitemap.index`: **desativado** (`status: false`).
- `simple_sitemap.custom_links.default`: presente; contém a raiz `/`.
- Bundles com sitemap: `activity`, `article`, `document`, `page`, `project`.
- `simple_sitemap.settings`: `max_links` 2000; `cron_generate` habilitado.

## Endpoints observados

- `https://aculta.toca.net.br/sitemap.xml` (host de teste): 200, 13 URLs.
- `https://apoio.aculta.toca.net.br/sitemap.xml`: 200, **o mesmo XML** (13 URLs, todas do domínio principal).
- Conclusão: o sitemap não é separado por host. Esse é o problema central da linha 0.1.x.

## Código e testes existentes

- O Portal não contém código de sitemap em `src/`.
- `scripts/validate-institution-browser.mjs` exige que todas as URLs comecem com
  `https://aculta.org/` (regra a ser substituída na microfase E).
- `scripts/validate-final-sitemap.php` gera o sitemap e exige URLs `https://aculta.org/`.
  Em `localhost` ele falha por design do host.
- Não há teste automatizado de sitemap em `web/modules/custom/aculta_portal/tests`.

## Dívidas e registros relacionados

- DT-P23 (`docs/operations/DEBT-REGISTER.md`): sitemap sem o host de apoio. Aberta.
- DEP-0002 (`aculta_deployer/registry/deploy-registry.json`): bloqueante, aberta.
- DEP-0001 (canonical da página de apoio): bloqueante, aberta.
