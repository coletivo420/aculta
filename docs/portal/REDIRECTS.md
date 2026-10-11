# Redirecionamentos e raízes dos subdomínios

Referência única do comportamento de raiz (`/`), de redirecionamento e de 404 em cada host do Portal.
Medida no servidor de testes em 2026-10-11. Atualize esta tabela ao mudar qualquer regra.

## Regras vigentes

1. **Caminho antigo responde 404; não há redirecionamento de slug.** É o padrão do projeto, antes e depois da versão estável (`AGENTS.md`, seção de slugs). Por isso `/apoie` responde 404.
2. **Redirecionamento entre purposes** usa `DomainPurposeManager` + `TrustedRedirectResponse`, nunca `RedirectResponse` cru para outro host (`DomainPurposeRequestSubscriber::retargetRedirect`).
3. **Conta:** a raiz `/` do host de conta é o ponto de entrada da área logada. Visitante não logado é redirecionado (302) para `/entrar` do próprio host de conta; a visão geral (`/minha-conta` internamente) não é acessível diretamente. Após logout, o destino também é o login (`DomainPurposeRequestSubscriber`, `onRequestBeforeRouter` e bloco de `user.logout`).
4. **Login com destino salvo** volta ao purpose de origem, validado por `isSafeLoginDestinationPath` (`LOGIN_DESTINATION_SESSION_KEY`).
5. **Usuário legado:** `/user/{id}` antigo pode ser redirecionado por `PortalController::legacyUserPageRedirect` (rota `user.page`, para o painel da conta).
6. **Wrong-purpose público continua 404** e não vira redirect genérico (`ADMIN-DOMAIN-POLICY.md`).
7. **Carrinho, checkout e pagamento:** purpose MAIN, com o host de compra apontando para o MAIN (`PAYMENT-DOMAIN-POLICY.md`).
8. **Página do produto da loja:** purpose SHOP (`DomainRouteSubscriber`, rota `entity.commerce_product.canonical`). Fora do host SHOP responde 404.
9. **Decisão de raiz (2026-10-11): a raiz `/` de cada host serve conteúdo, sem redirecionar.** Mecanismo padrão do Core para o caso da loja: serviço com a tag `path_processor_inbound` (`InboundPathProcessorInterface`), que define `_disable_route_normalizer` para o alias interno não virar 301 (módulo `redirect`). Vale para MAIN, SUPPORT, WIKI, SHOP, MAGAZINE e COURSES. Cada subdomínio tem página inicial própria.
10. **Exceção única:** visitante não logado na raiz do host de conta vai (302) para `/entrar` (`DomainPurposeRequestSubscriber::onRequestBeforeRouter`). A raiz `/` de cada host é a página inicial daquele purpose, por dois mecanismos:
   - MAIN: página inicial do site (`system.site:page.front` = `/node/1`).
   - Apoio, revista, wiki e cursos: nó com `field_domain_source` apontando para o domínio, que o módulo `domain_source` usa como página inicial desse host.
   - Conta: `system.site:page.front` na coleção de configuração `domain.conta_aculta_org` = `/minha-conta` (exportada em `config/sync/domain/conta_aculta_org/system.site.yml`). `/minha-conta` é a rota interna da visão geral: requisição direta responde 404; o público é a raiz `/`.

## Matriz de comportamento (2026-10-11)

| Host | `/` | `/entrar` | `/apoie` | `/apoio` | `/user/1` | Observação |
| --- | --- | --- | --- | --- | --- | --- |
| aculta.toca.net.br (MAIN) | 200 (`<front>`) | 404 | 404 | 404 | 404 | Início = nó 1 (sem reescrita) |
| conta.aculta.toca.net.br (ACCOUNT) | 302 → /entrar | 200 | 404 | 404 | 404 | Exceção: visitante na raiz vai ao login |
| apoio.aculta.toca.net.br (SUPPORT) | 200 (`/apoio` por `FRONT_PATHS`) | 404 | 404 | 404 | 404 | Formulário de apoio |
| loja.aculta.toca.net.br (SHOP) | 200 (`/loja-da-aculta` por `FRONT_PATHS`) | 404 | 404 | 404 | 404 | Página inicial da loja (nó 69) |
| revista420.aculta.toca.net.br (MAGAZINE) | 200 (`<front>`, com a marca da revista) | 404 | 404 | 404 | 404 | Sem reescrita: página inicial do site com o cabeçalho do host |
| wiki420.aculta.toca.net.br (WIKI) | 200 (`/wiki` por `FRONT_PATHS`) | 404 | 404 | 404 | 404 | Página inicial da wiki |
| cursos.aculta.toca.net.br (COURSES) | 200 (`/cursos` por `FRONT_PATHS`) | 404 | 404 | 404 | 404 | Página inicial dos cursos |
| forum.aculta.toca.net.br (FORUM) | 200 (`/forum` por `FRONT_PATHS`) | 404 | 404 | 404 | 404 | Índice do fórum (módulo forum) |

Nenhuma regra de redirecionamento do módulo `redirect` está cadastrada (zero registros no Runtime de teste).

## Pendências para deixar os hosts iguais

- **Loja (SHOP):** a raiz serve a página inicial (nó 69) pelo `PurposeFrontPathProcessor`. Frete, prazo e trocas seguem pendentes do responsável.
- **Mecanismo dos demais hosts:** a raiz de apoio, revista, wiki e cursos responde 200 por um mecanismo que ainda não foi rastreado. A pendência é padronizá-lo com o `PurposeFrontPathProcessor`, que é o mapa único (`FRONT_PATHS`).
- **Por que não `domain_config`:** o override `system.site:page.front` por domínio não é aplicado nesta instalação (testado com nome e página inicial), e há relatos públicos de bugs do `DomainConfigFactoryOverride` (issues drupal.org #3154402 e #1650498). Exceção confirmada em 2026-10-11: a raiz da Conta é lida da coleção `domain.conta_aculta_org` e responde a visão geral na raiz; o mecanismo exato de leitura por domínio segue não rastreado.
- **Mecanismo dos demais hosts:** a raiz de apoio, revista, wiki e cursos responde 200, mas o mecanismo exato não foi rastreado; `domain_source` sozinho não o explica. Rastrear e padronizar com a regra da loja é pendência.

## Onde está o código

- `src/EventSubscriber/DomainPurposeRequestSubscriber.php`: redirecionamentos entre purposes, login, logout e wrong-host.
- `src/EventSubscriber/DomainRouteSubscriber.php`: donos das rotas por purpose (`_aculta_domain_purpose`).
- `src/Domain/DomainRoutePolicy.php`: rotas centrais (carrinho, checkout, pagamento).
- `src/Controller/PortalController.php`: `legacyUserPageRedirect`.
- Documentação relacionada: `ADMIN-DOMAIN-POLICY.md`, `CROSS-DOMAIN-REQUEST-POLICY.md`, `PAYMENT-DOMAIN-POLICY.md`, `docs/architecture/multidomain.md`.

## Mecanismo único da raiz (decisão de 2026-10-11)

Toda raiz de subdomínio que serve conteúdo passa pelo `PurposeFrontPathProcessor` (`src/PathProcessor/PurposeFrontPathProcessor.php`), um `InboundPathProcessorInterface` com a tag `path_processor_inbound`, mapa único `FRONT_PATHS`. Não há redirecionamento. Sem entrada no mapa, o host usa o `<front>` do Core. Hoje, o `FRONT_PATHS` tem apoio, wiki, cursos, loja e fórum. Revista e principal usam o `<front>`. Para mudar a raiz de um host, altere só esse mapa e rode `php scripts/validate-root-policy.php`.

## Inventário de redirecionamentos e remoções (2026-10-11)

Regra: slug publicado é estável. Quando um caminho público muda, o caminho antigo responde **404**; não há 301 de slug. Redirecionamentos existem só por segurança, login ou fluxo de conta, e estão listados abaixo com o arquivo que os emite.

### Redirecionamentos vigentes

| Origem | Destino | Código | Mecanismo (arquivo) | Motivo |
| --- | --- | --- | --- | --- |
| `/` no host de Conta, visitante | `/entrar` | 302 | `DomainPurposeRequestSubscriber` (`TrustedRedirectResponse`) | Raiz da conta exige login |
| `/aceite-termos` (Conta), após aceitar | `/` (Conta) | 302 | Agreement `aculta_account_terms`, `settings.destination` (`config/sync/agreement.agreement.aculta_account_terms.yml`) | Retorno à raiz da conta; antes apontava para `/minha-conta` (404), corrigido em 0.3.0-dev.12 |
| `/user/{uid}/edit`, próprio usuário | `/seguranca` (Conta) | 302 | `AccountRouteSubscriber` | Edição de conta acontece no painel |
| `/user/{uid}/edit`, outro usuário | — | 403 | `AccountRouteSubscriber` | Sem acesso a conta alheia |
| `/user/{uid}`, próprio usuário | `/` (Conta) | 302 | `AccountRouteSubscriber` | Perfil público não existe; a conta é a visão geral |
| `/user/{uid}`, outro usuário | — | 403 | `AccountRouteSubscriber` | Sem acesso a conta alheia |
| Rotas de troca de e-mail (`change_mail_page.*`) | `/seguranca` (Conta) | 302 | `AccountRouteSubscriber` | Troca de e-mail fica na segurança |
| `/user` (página legada do Core) | raiz da Conta | 302 | `PortalController::legacyUserPageRedirect` | Página do Core sem interface própria |
| `/carrinho` no host da loja (SHOP) | `/carrinho` no host principal (MAIN) | 302 | `DomainPurposeRequestSubscriber` e política de pagamento | Carrinho e checkout pertencem ao MAIN |
| `/busca` enviada pelo formulário | `/busca?q=<termo>` | 302 | `SearchPageForm::submitForm` | Busca em GET com o termo na URL |
| Destinos após formulários de conta (cadastro, segurança, dados, painel) | `aculta_portal.security`, `aculta_portal.dashboard`, `aculta_portal.my_data_address`, `<front>` | 302 | `PortalFormCallbacks` (`setRedirect`) | Retorno ao painel depois de salvar |
| `/product/{id}` | `/loja/produto/...` (alias) | 301 | Módulo Redirect e alias do Pathauto | Caminho canônico do produto |

### Caminhos removidos (404, sem redirecionamento)

| Caminho antigo | Caminho atual | Quando | Observação |
| --- | --- | --- | --- |
| `/cart` | `/carrinho` | 0.3.0-dev.4 | Mesma rota do Commerce, novo caminho |
| `/donate`, `/donate/{valor}/now`, `/donate/{pedido}/{etapa}` | `/apoiar`, `/apoiar/{valor}/agora`, `/apoiar/{pedido}/{etapa}` | 0.3.0-dev.5 | Mesmas rotas do Commerce, novos caminhos |
| `/user/{uid}/orders` | `/pedidos` | 0.3.0-dev.7 | Lista de pedidos do Commerce, sem ID na URL |
| `/user/{uid}/payment-methods` | `/pagamentos` | 0.3.0-dev.7 | Lista de pagamentos do Commerce, sem ID na URL |
| `/conta-interna` (Conta) | `/` (raiz da Conta) | 0.3.0-dev.13 | Sem rota. Visitante recebe 404. Logado recebe 302 para `/` pela regra geral de caminho inexistente da Conta (não alterada, decisão pendente) |

### Caminhos de conta com ID removidos (0.3.0-dev.8)

| Caminho antigo (ID na URL) | Caminho atual (sessão) | Responde | Observação |
| --- | --- | --- | --- |
| `/user/{uid}/address-book`, `/enderecos` | `/dados/endereco` (Meus Dados) | 302 para o caminho novo; 404 para o caminho com ID | Lista de endereços do Commerce substituída pelo formulário de Meus Dados (busca por CEP). Decisão do responsável (2026-10-11). |
| `/user/{uid}/payment-methods/add` | `/pagamentos/novo` | 404 para o antigo | `PortalController::paymentMethodAdd`; 403 quando a loja não tem tipo de pagamento que aceite cadastro (comportamento do Commerce) |
| `/user/{uid}/payment-methods/{id}/edit` | `/pagamentos/{id}/editar` | 404 para o antigo | Dono verificado pelo método |
| `/user/{uid}/payment-methods/{id}/delete` | `/pagamentos/{id}/excluir` | 404 para o antigo | Dono verificado pelo método |
| `/user/{uid}/payment-methods/{id}/set-default` | `/pagamentos/{id}/padrao` | 404 para o antigo | Dono verificado pelo método |
| `/user/{uid}/orders/{pedido}` | `/pedidos/{pedido}` | 404 para o antigo | Dono verificado pelo pedido (`_entity_access commerce_order.view`) |

### Mecanismo

- **Caminhos e controllers:** `DomainRouteSubscriber` troca o caminho e o controller das rotas do Commerce e remove o parâmetro `user`.
- **Usuário da sessão:** `PortalController` entrega ao controller do Commerce o usuário da sessão como entidade (`sessionUser()`).
- **Links sem ID:** `AccountListPathProcessor` remove `user` da query gerada para `/pedidos` e `/pagamentos`.
- **Verificações:** exigências que pediam o `$user` da URL foram trocadas por `_user_is_logged_in`; a propriedade do registro continua verificada pela entidade (perfil, método de pagamento ou pedido).

### Pendências de conta (não são redirecionamentos)

- **Permissões de endereço (corrigido em 2026-10-11):** o papel "autenticado" tem `create customer profile`, `view own customer profile` e `update own customer profile` (`config/sync/user.role.authenticated.yml`): cliente cria e edita o próprio endereço. `delete any customer profile` e `delete own customer profile` ficam só com o administrador. O endereço de outra conta continua negado. Verificado com duas contas temporárias, já removidas.
- **Teste de dono com cliente comum:** verificado por API em 2026-10-11 (usuário `teste.comum`, não dono de `/checkout/4`, negado pela regra do Commerce). O teste de navegador com o cliente comum segue com o responsável do teste.
- **Pedido com itens:** `/pedidos/{pedido}` não foi testado com pedido real (não há pedidos finalizados no ambiente de teste).


### Endereços: substituição por Meus Dados (0.3.0-dev.9)

- **Fonte única:** o endereço é cadastrado e alterado só em `/dados/endereco` (Meus Dados), que busca o endereço pelo CEP.
- **Redirecionamentos (302):** `/enderecos`, `/enderecos/novo/{tipo}`, `/enderecos/{perfil}/editar`, `/enderecos/{perfil}/excluir`, `/enderecos/{perfil}/padrao` redirecionam para `/dados/endereco`. Implementados por `PortalController::addressRedirect`, nas rotas do Commerce (`DomainRouteSubscriber`).
- **Exceção à regra de slug:** a regra geral não usa redirecionamento de slug. Aqui o redirecionamento foi pedido pelo responsável para a tela substituída.
- **Caminho com ID:** `/user/{uid}/address-book*` responde 404.
- **Perfil `customer`:** o formulário de Meus Dados grava no mesmo tipo de perfil usado pelo Commerce.
