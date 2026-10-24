# Caminhos de raiz dos subdomínios (comportamento padrão)

Regra uniforme, implementada em `src/Domain/FrontPathPolicy.php` e aplicada por `DomainPurposeRequestSubscriber`:

1. A raiz de cada subdomínio serve o conteúdo da sua finalidade (`PurposeFrontPathProcessor::FRONT_PATHS`).
2. O caminho interno da raiz (ex.: `/apoio`, `/wiki`, `/cursos`, `/loja`, `/discussoes`, `/redes-sociais`) **não é público**. Requisição direta responde **404**, em qualquer método, sem redirecionamento 301. O caminho antigo `/forum` permanece fechado durante a retirada do módulo contribuído.
3. Caminho de outra finalidade responde 404 (wrong-purpose público, ver `ADMIN-DOMAIN-POLICY.md`).
4. Redirecionamentos 301 ficam só para caminhos legados listados abaixo. Não se cria 301 para slug novo.

## Matriz esperada (Runtime de testes)

| Host | `/` | Caminho interno (`/apoio`, `/wiki`, ...) | Observação |
| --- | --- | --- | --- |
| `apoio.aculta.toca.net.br` | 200 (formulário de apoio) | 404 | Escolha de valor no apoio; finalização no domínio principal |
| `cursos.aculta.toca.net.br` | 200 (cursos) | 404 | |
| `growroom420.aculta.toca.net.br` | 200 (View `aculta_forum_public_topics`) | 404 (`/discussoes` e `/forum`) | HTTP validado no Runtime de teste em 2026-10-19; produção não confirmada |
| `wiki420.aculta.toca.net.br` | 200 (wiki) | 404 | |
| `revista420.aculta.toca.net.br` | 200 | 404 | |
| `loja.aculta.toca.net.br` | **404: pendência de conteúdo** | 404 | A página inicial da loja é a rota `/loja` (`aculta_portal.shop_home`), que mostra o nó 372 "Loja da Associação". O nó 372 não é público direto (404). Decisão do responsável: criar o conteúdo |
| `revista420.aculta.toca.net.br` | não resolve no servidor de testes | — | Pendência de ambiente |
| `aculta.toca.net.br` (principal) | 200 | 404 para `/apoio` (não é finalidade do principal) | Carrinho e checkout pertencem ao principal |
| `conta.aculta.toca.net.br` | 302 para `/entrar` (anônimo) ou hub do ACULTA Studio (logado) | `/studio` direto: 404 | Caminho interno do hub: `/studio`. A Minha Conta original fica em `/conta-legada`, que não é a raiz |

## Redirecionamentos 301 permitidos (legado)
- `/minha-conta` e subcaminhos → conta, sem prefixo (`DomainPurposeRequestSubscriber`).
- Caminhos antigos do checkout `/checkout/{pedido}/{etapa}` → `/finalizar-compra/...` (`DomainPurposeRequestSubscriber`).
- `/pagamentos` → `/pedidos` (`DomainRouteSubscriber`).

Qualquer outro 301 novo é regressão, a menos que esta lista seja atualizada com justificativa.

## Gate anti-regressão
`php scripts/validate-domain-front-paths.php` (estático, sem rede). Com `--live`, também consulta os hosts do Runtime e compara com a matriz acima.

## Metadados da raiz
- `canonical_url` e `og_url` das raízes usam `[site:url]` (ou a raiz do host, para página de finalidade). Endereço de produção não aparece em sobreposição por domínio: o gate `validate-domain-front-paths` confere.
- JSON-LD de organização e site: o endereço principal vem do ambiente (`aculta_deployer`); no teste, `settings.local.php` define os valores.

