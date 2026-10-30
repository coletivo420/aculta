# Caminhos de raiz dos subdomínios (comportamento padrão)

Regra uniforme, implementada em `src/Domain/FrontPathPolicy.php` e aplicada por `DomainPurposeRequestSubscriber`:

1. A raiz de cada subdomínio serve o conteúdo da sua finalidade (`PurposeFrontPathProcessor::FRONT_PATHS`).
2. O caminho interno da raiz (ex.: `/apoio`, `/wiki`, `/cursos`, `/loja`, `/discussoes`, `/redes-sociais`, `/eventos`) **não é público**. Requisição direta responde **404**, em qualquer método, sem redirecionamento 301. O caminho antigo `/forum` permanece fechado durante a retirada do módulo contribuído.
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
| `conta.aculta.toca.net.br` | 302 para `/entrar` (anônimo) ou hub do ACULTA Studio (logado) | `/studio` direto: 404 | Caminho interno do hub: `/studio`. A Minha Conta original fica em `/conta-legada`, que não é a raiz |

## Redirecionamentos 301 permitidos (legado)
- Caminhos antigos do checkout `/checkout/{pedido}/{etapa}` → `/finalizar-compra/...` (`DomainPurposeRequestSubscriber`).
- `/pagamentos` → `/pedidos` (`DomainRouteSubscriber`).

Qualquer outro 301 novo é regressão, a menos que esta lista seja atualizada com justificativa.

## Gate anti-regressão
`php scripts/validate-domain-front-paths.php` (estático, sem rede). Com `--live`, também consulta os hosts do Runtime e compara com a matriz acima.

## Metadados da raiz
- `canonical_url` e `og_url` das raízes usam `[site:url]` (ou a raiz do host, para página de finalidade). Endereço de produção não aparece em sobreposição por domínio: o gate `validate-domain-front-paths` confere.
- JSON-LD de organização e site: o endereço principal vem do ambiente (`aculta_deployer`); no teste, `settings.local.php` define os valores.

## Suites do ACULTA Studio por domínio - destino ADR-038 (planejado)

A regra atual da raiz continua descrevendo o Runtime existente até o corte. As rotas abaixo passam a ser válidas somente no purpose correto; não são redirects da raiz. Atualizar `FrontPathPolicy` e o gate para validar estas exceções.

| Host de teste | Purpose | Suite/path permitido | Purpose errado |
| --- | --- | --- | --- |
| `conta.aculta.toca.net.br` | `account` | Marley `/marley` e filhas | 404 |
| `cursos.aculta.toca.net.br` | `courses` | Ganjalab `/ganjalab` e filhas | 404 |
| `wiki.aculta.toca.net.br` | `wiki` | WE SATIVA `/wesativa` e filhas | 404 |
| `loja.aculta.toca.net.br` | `shop` | Green Stash `/greenstash` e filhas | 404 |
| `eventos.aculta.toca.net.br` | `eventos` | Culturas `/culturas` e filhas | 404 |

A raiz `conta` passa a ser entrada da Conta, sem reescrita para `/studio`; `/studio` e `/studio/*` retornam 404, sem alias/redirect. Conta e Perfil migram para `/conta/*` e `/perfil/*`; `/conta-legada` permanece. A Wiki ainda usa `wiki420.aculta.toca.net.br`; não trocar até provisionar Domain/Deployer, DNS, TLS/vhost e homologar `wiki.aculta.toca.net.br`.

A tabela de runtime acima continua sendo estado presente até o corte. Esta seção documenta o destino aprovado, não prova mudança de host/rota.
