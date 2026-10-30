# Caminhos de raiz dos subdomínios (comportamento padrão)

Regra uniforme, implementada em `src/Domain/FrontPathPolicy.php` e aplicada por `DomainPurposeRequestSubscriber`:

1. A raiz de cada subdomínio serve o conteúdo da sua finalidade (`PurposeFrontPathProcessor::FRONT_PATHS`).
2. O caminho interno da raiz (ex.: `/apoio`, `/wiki`, `/cursos`, `/loja`, `/discussoes`, `/redes-sociais`, `/eventos`) **não é público**. Requisição direta responde **404**, em qualquer método, sem redirecionamento 301. Exceções canônicas: `/conta` abre a entrada da Conta; as Suites têm seus caminhos próprios somente no purpose declarado pelo ADR-038. `/studio` e todas as rotas `/studio/*` são 404 para todos os métodos.
3. Caminho de outra finalidade responde 404 (wrong-purpose público, ver `ADMIN-DOMAIN-POLICY.md`).
4. Redirecionamentos 301 ficam só para caminhos legados listados abaixo. Não se cria 301 para slug novo.

## Matriz esperada (Runtime de testes)

| Host | `/` | Caminho interno (`/apoio`, `/wiki`, `/eventos`, ...) | Observação |
| --- | --- | --- | --- |
| `apoio.aculta.toca.net.br` | 200 (formulário de apoio) | 404 | Escolha de valor no apoio; finalização no domínio principal |
| `cursos.aculta.toca.net.br` | 200 (cursos) | 404 | |
| `growroom420.aculta.toca.net.br` | 200 (View `aculta_forum_public_topics`) | 404 (`/discussoes` e `/forum`) | HTTP validado no Runtime de teste em 2026-10-19; produção não confirmada |
| `wiki420.aculta.toca.net.br` | 200 (wiki) | 404 | |
| `revista.aculta.toca.net.br` | 200 | 404 | HTTPS válido; `X-Robots-Tag: noindex, nofollow, noarchive`, validado em 2026-10-28 |
| `loja.aculta.toca.net.br` | **404: pendência de conteúdo** | 404 | A página inicial da loja é a rota `/loja` (`aculta_portal.shop_home`), que mostra o nó 372 "Loja da Associação". O nó 372 não é público direto (404). Decisão do responsável: criar o conteúdo |
| `eventos.aculta.toca.net.br` | 200 (catálogo de eventos) | 404 | Catálogo de eventos na raiz; caminho interno `/eventos` fechado |
| `aculta.toca.net.br` (principal) | 200 | 404 para `/apoio` (não é finalidade do principal) | Carrinho e checkout pertencem ao principal |
| `conta.aculta.toca.net.br` | 302 para `/entrar` (anônimo) ou entrada da Conta (logado) | `/conta` | A raiz resolve à mesma entrada de Conta; `/studio` e todo `/studio/*` retornam 404. A Conta legada permanece em `/conta-legada` |

## Redirecionamentos 301 permitidos (legado)
- Caminhos antigos do checkout `/checkout/{pedido}/{etapa}` → `/finalizar-compra/...` (`DomainPurposeRequestSubscriber`).
- `/pagamentos` → `/pedidos` (`DomainRouteSubscriber`).

Qualquer outro 301 novo é regressão, a menos que esta lista seja atualizada com justificativa.

## Gate anti-regressão
`php scripts/validate-domain-front-paths.php` (estático, sem rede). Com `--live`, também consulta os hosts do Runtime e compara com a matriz acima.

## Metadados da raiz
- `canonical_url` e `og_url` das raízes usam `[site:url]` (ou a raiz do host, para página de finalidade). Endereço de produção não aparece em sobreposição por domínio: o gate `validate-domain-front-paths` confere.
- JSON-LD de organização e site: o endereço principal vem do ambiente (`aculta_deployer`); no teste, `settings.local.php` define os valores.

## Suites do ACULTA Studio por domínio - ADR-038 (implementação em andamento)

As rotas abaixo estão declaradas no código e respondem somente no purpose correto; não são redirects da raiz. A validação automatizada de métodos/purposes continua pendente para todos os fluxos protegidos.

| Host de teste | Purpose | Suite/path permitido | Purpose errado |
| --- | --- | --- | --- |
| `conta.aculta.toca.net.br` | `account` | Marley `/marley` e filhas | 404 |
| `cursos.aculta.toca.net.br` | `courses` | Ganjalab `/ganjalab` e filhas | 404 |
| `wiki420.aculta.toca.net.br` (Domain ativo) | `wiki` | WE SATIVA `/wesativa` e filhas | 404 |
| `loja.aculta.toca.net.br` | `shop` | Green Stash `/greenstash` e filhas | 404 |
| `eventos.aculta.toca.net.br` | `eventos` | Culturas `/culturas` e filhas | 404 |

A raiz `conta` apresenta entrada da Conta, resolvida pela rota `/conta`; esse caminho também é público. Não há reescrita para `/studio`; `/studio` e `/studio/*` retornam 404, sem alias/redirect. Conta e Perfil usam `/conta/*` e `/perfil/*`; `/salvos`, planos de Mercado Pago e `/conta-legada` permanecem separados. O Domain `wiki` ainda usa `wiki420.aculta.toca.net.br`: `wiki.aculta.toca.net.br` não resolve no DNS e não pode ser declarado ativo até provisionar Domain/Deployer, DNS, TLS/vhost e homologação.

O cache de rotas no Runtime foi reconstruído em 2026-10-30 e o site respondeu HTTP 200 na raiz MAIN e na entrada de Conta; `/studio` respondeu 404. Isso não homologa o novo host Wiki.
