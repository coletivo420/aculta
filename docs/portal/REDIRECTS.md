# Redirecionamentos e raízes dos subdomínios

Referência única do comportamento de raiz (`/`), de redirecionamento e de 404 em cada host do Portal.
Medida no servidor de testes em 2026-10-11. Atualize esta tabela ao mudar qualquer regra.

## Regras vigentes

1. **Caminho antigo responde 404; não há redirecionamento de slug.** É o padrão do projeto, antes e depois da versão estável (`AGENTS.md`, seção de slugs). Por isso `/apoie` responde 404.
2. **Redirecionamento entre purposes** usa `DomainPurposeManager` + `TrustedRedirectResponse`, nunca `RedirectResponse` cru para outro host (`DomainPurposeRequestSubscriber::retargetRedirect`).
3. **Conta:** a raiz `/` do host de conta é o ponto de entrada da área logada. Visitante não logado é redirecionado (302) para `/entrar` do próprio host de conta; a visão geral (`/conta-interna` internamente) não é acessível diretamente. Após logout, o destino também é o login (`DomainPurposeRequestSubscriber`, `onRequestBeforeRouter` e bloco de `user.logout`).
4. **Login com destino salvo** volta ao purpose de origem, validado por `isSafeLoginDestinationPath` (`LOGIN_DESTINATION_SESSION_KEY`).
5. **Usuário legado:** `/user/{id}` antigo pode ser redirecionado por `PortalController::legacyUserPageRedirect` (rota `user.page`, para o painel da conta).
6. **Wrong-purpose público continua 404** e não vira redirect genérico (`ADMIN-DOMAIN-POLICY.md`).
7. **Carrinho, checkout e pagamento:** purpose MAIN, com o host de compra apontando para o MAIN (`PAYMENT-DOMAIN-POLICY.md`).
8. **Página do produto da loja:** purpose SHOP (`DomainRouteSubscriber`, rota `entity.commerce_product.canonical`). Fora do host SHOP responde 404.
9. **Decisão de raiz (2026-10-11): a raiz `/` de cada host serve conteúdo, sem redirecionar.** Mecanismo padrão do Core para o caso da loja: serviço com a tag `path_processor_inbound` (`InboundPathProcessorInterface`), que define `_disable_route_normalizer` para o alias interno não virar 301 (módulo `redirect`). Vale para MAIN, SUPPORT, WIKI, SHOP, MAGAZINE e COURSES. Cada subdomínio tem página inicial própria.
10. **Exceção única:** visitante não logado na raiz do host de conta vai (302) para `/entrar` (`DomainPurposeRequestSubscriber::onRequestBeforeRouter`). A raiz `/` de cada host é a página inicial daquele purpose, por dois mecanismos:
   - MAIN: página inicial do site (`system.site:page.front` = `/node/1`).
   - Apoio, revista, wiki e cursos: nó com `field_domain_source` apontando para o domínio, que o módulo `domain_source` usa como página inicial desse host.

## Matriz de comportamento (2026-10-11)

| Host | `/` | `/entrar` | `/apoie` | `/apoio` | `/user/1` | Observação |
| --- | --- | --- | --- | --- | --- | --- |
| aculta.toca.net.br (MAIN) | 200 | 404 | 404 | 404 | 404 | Início = nó 1 |
| conta.aculta.toca.net.br (ACCOUNT) | 302 → /entrar | 200 | 404 | 404 | 404 | Exceção: visitante na raiz vai ao login |
| apoio.aculta.toca.net.br (SUPPORT) | 200 | 404 | 404 | 404 | 404 | Raiz = nó com `domain_source` do apoio |
| loja.aculta.toca.net.br (SHOP) | 200 (serve conteúdo) | 404 | 404 | 404 | 404 | Página inicial da loja (nó 69) servida pelo processador de entrada `PurposeFrontPathProcessor` |
| revista420.aculta.toca.net.br (MAGAZINE) | 200 | 404 | 404 | 404 | 404 | Raiz = nó com `domain_source` da revista |
| wiki420.aculta.toca.net.br (WIKI) | 200 | 404 | 404 | 404 | 404 | Raiz = nó com `domain_source` da wiki |
| cursos.aculta.toca.net.br (COURSES) | 200 | 404 | 404 | 404 | 404 | Raiz = nó com `domain_source` dos cursos |

Nenhuma regra de redirecionamento do módulo `redirect` está cadastrada (zero registros no Runtime de teste).

## Pendências para deixar os hosts iguais

- **Loja (SHOP):** a raiz serve a página inicial (nó 69) pelo `PurposeFrontPathProcessor`. Frete, prazo e trocas seguem pendentes do responsável.
- **Mecanismo dos demais hosts:** a raiz de apoio, revista, wiki e cursos responde 200 por um mecanismo que ainda não foi rastreado. A pendência é padronizá-lo com o `PurposeFrontPathProcessor`, que é o mapa único (`FRONT_PATHS`).
- **Por que não `domain_config`:** o override `system.site:page.front` por domínio não é aplicado nesta instalação (testado com nome e página inicial), e há relatos públicos de bugs do `DomainConfigFactoryOverride` (issues drupal.org #3154402 e #1650498).
- **Mecanismo dos demais hosts:** a raiz de apoio, revista, wiki e cursos responde 200, mas o mecanismo exato não foi rastreado; `domain_source` sozinho não o explica. Rastrear e padronizar com a regra da loja é pendência.

## Onde está o código

- `src/EventSubscriber/DomainPurposeRequestSubscriber.php`: redirecionamentos entre purposes, login, logout e wrong-host.
- `src/EventSubscriber/DomainRouteSubscriber.php`: donos das rotas por purpose (`_aculta_domain_purpose`).
- `src/Domain/DomainRoutePolicy.php`: rotas centrais (carrinho, checkout, pagamento).
- `src/Controller/PortalController.php`: `legacyUserPageRedirect`.
- Documentação relacionada: `ADMIN-DOMAIN-POLICY.md`, `CROSS-DOMAIN-REQUEST-POLICY.md`, `PAYMENT-DOMAIN-POLICY.md`, `docs/architecture/multidomain.md`.
