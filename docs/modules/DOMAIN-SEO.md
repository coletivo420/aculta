# Módulos — Domains, URLs e SEO

Data da revisão: 2026-10-07.

## Domain

| Módulo | Papel |
| --- | --- |
| domain | entidades Domain e contexto |
| domain_alias | aliases por ambiente |
| domain_config | configuração variada por Domain |
| domain_source | domínio de origem do conteúdo |

`aculta_portal` aplica a política de purposes sobre as APIs Domain. Não criar tabela própria de hosts/purposes.

Purposes ativos: MAIN, ACCOUNT, SUPPORT, MAGAZINE, WIKI, SHOP e COURSES. FORUM permanece planejado.

## URLs

| Módulo | Papel |
| --- | --- |
| pathauto | aliases amigáveis |
| redirect | redirects persistentes |
| rename_admin_paths | hardening de paths administrativos |

Canonical e purpose pertencem à camada Portal/Domain, não ao tema.

## SEO

| Módulo | Papel |
| --- | --- |
| metatag | metatags, canonical e metadata social |
| metatag_open_graph | Open Graph |
| schema_metatag | framework Schema.org |
| schema_article/event/image_object/organization/web_page/web_site | tipos Schema usados |
| simple_sitemap | sitemap XML |

### Anti-regressão

- não hardcodar canonical em Twig;
- não duplicar sitemap;
- aliases Homelab não alteram canonical público;
- mudanças de Domain exigem matriz de hosts.

## Indexação por domínio

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
