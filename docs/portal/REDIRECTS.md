# Redirecionamentos e raízes dos subdomínios

Referência única do comportamento de raiz (`/`), de redirecionamento e de 404 em cada host do Portal.
Medida no servidor de testes em 2026-10-11. Atualize esta tabela ao mudar qualquer regra.

## Regras vigentes

1. **Caminho antigo responde 404; não há redirecionamento de slug.** É o padrão do projeto, antes e depois da versão estável (`AGENTS.md`, seção de slugs). Por isso `/apoie` responde 404.
2. **Redirecionamento entre purposes** usa `DomainPurposeManager` + `TrustedRedirectResponse`, nunca `RedirectResponse` cru para outro host (`DomainPurposeRequestSubscriber::retargetRedirect`).
3. **Conta:** após logout, o destino é o login do host de conta; a raiz do host de conta é o ponto de entrada da área logada (`DomainPurposeRequestSubscriber`, bloco de `user.logout`).
4. **Login com destino salvo** volta ao purpose de origem, validado por `isSafeLoginDestinationPath` (`LOGIN_DESTINATION_SESSION_KEY`).
5. **Usuário legado:** `/user/{id}` antigo pode ser redirecionado por `PortalController::legacyUserPageRedirect` (rota `user.page`, para o painel da conta).
6. **Wrong-purpose público continua 404** e não vira redirect genérico (`ADMIN-DOMAIN-POLICY.md`).
7. **Carrinho, checkout e pagamento:** purpose MAIN, com o host de compra apontando para o MAIN (`PAYMENT-DOMAIN-POLICY.md`).
8. **Página do produto da loja:** purpose SHOP (`DomainRouteSubscriber`, rota `entity.commerce_product.canonical`). Fora do host SHOP responde 404.
9. **Raiz de cada host:** a raiz `/` de um subdomínio é a página inicial daquele purpose. Hoje isso acontece por dois mecanismos:
   - MAIN: página inicial do site (`system.site:page.front` = `/node/1`).
   - Apoio, revista, wiki e cursos: nó com `field_domain_source` apontando para o domínio, que o módulo `domain_source` usa como página inicial desse host.

## Matriz de comportamento (2026-10-11)

| Host | `/` | `/entrar` | `/apoie` | `/apoio` | `/user/1` | Observação |
| --- | --- | --- | --- | --- | --- | --- |
| aculta.toca.net.br (MAIN) | 200 | 404 | 404 | 404 | 404 | Início = nó 1 |
| conta.aculta.toca.net.br (ACCOUNT) | 403 | 200 | 404 | 404 | 404 | Raiz exige login; entrada em `/entrar` |
| apoio.aculta.toca.net.br (SUPPORT) | 200 | 404 | 404 | 404 | 404 | Raiz = nó com `domain_source` do apoio |
| loja.aculta.toca.net.br (SHOP) | 404 | 404 | 404 | 404 | 404 | **Sem página inicial da loja** |
| revista420.aculta.toca.net.br (MAGAZINE) | 200 | 404 | 404 | 404 | 404 | Raiz = nó com `domain_source` da revista |
| wiki420.aculta.toca.net.br (WIKI) | 200 | 404 | 404 | 404 | 404 | Raiz = nó com `domain_source` da wiki |
| cursos.aculta.toca.net.br (COURSES) | 200 | 404 | 404 | 404 | 404 | Raiz = nó com `domain_source` dos cursos |

Nenhuma regra de redirecionamento do módulo `redirect` está cadastrada (zero registros no Runtime de teste).

## Pendências para deixar os hosts iguais

- **Loja (SHOP):** a raiz não tem página inicial. Caminho: criar um nó com `field_domain_source = loja_aculta_org` e publicado, como apoio, revista, wiki e cursos. Depende do conteúdo que o responsável aprovar.
- **Conta (ACCOUNT):** a raiz responde 403 para visitante. Decidir se a raiz deve redirecionar para `/entrar` (visitante) ou mostrar a visão geral (logado).

## Onde está o código

- `src/EventSubscriber/DomainPurposeRequestSubscriber.php`: redirecionamentos entre purposes, login, logout e wrong-host.
- `src/EventSubscriber/DomainRouteSubscriber.php`: donos das rotas por purpose (`_aculta_domain_purpose`).
- `src/Domain/DomainRoutePolicy.php`: rotas centrais (carrinho, checkout, pagamento).
- `src/Controller/PortalController.php`: `legacyUserPageRedirect`.
- Documentação relacionada: `ADMIN-DOMAIN-POLICY.md`, `CROSS-DOMAIN-REQUEST-POLICY.md`, `PAYMENT-DOMAIN-POLICY.md`, `docs/architecture/multidomain.md`.
