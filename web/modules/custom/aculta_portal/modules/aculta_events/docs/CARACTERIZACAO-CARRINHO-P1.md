# Caracterização — carrinho integrado, checkouts e rotas globais (P1)

Fase P1 de [`PLANO-INGRESSOS-PAGOS.md`](PLANO-INGRESSOS-PAGOS.md), seção 7. **Caracterização, sem mudança de
comportamento.** Nenhum arquivo de produção foi alterado.

- Data: 2026-10-28.
- Branch: `refactor/opencultures-codigo-visual`.
- Ambiente: servidor de teste local (esta máquina), Drupal 11.4.10, banco de Kernel `aculta_simpletest`.
- Base da decisão: `docs/decisions/ADR-013-portal-multi-cart-checkout.md` com o aditivo de 2026-10-28, decisões D-38 a
  D-42 em `docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md` e fases P-CART da mesma ADR.

## Como foi medido

1. Leitura do código de produção: `aculta_portal/src/Cart/*`, `src/EventSubscriber/CommerceCheckoutRouteSubscriber.php`,
   `src/EventSubscriber/DomainRouteSubscriber.php`, `src/Controller/CartCheckoutController.php`,
   `src/Checkout/*` e os três providers (`aculta_commerce`, `aculta_lms`, `aculta_donation`).
2. Leitura da configuração versionada em `config/sync` (arquivos `commerce_order.commerce_order_type.*`,
   `commerce_checkout.commerce_checkout_flow.*`, `aculta_donation.settings`).
3. Leitura do **runtime local**, somente leitura, com `drush cget` e `drush php:eval` (entidades `commerce_order_type`,
   `commerce_checkout_flow`, política `aculta_portal.checkout_policy_manager` e `router.route_provider`). Nenhuma
   configuração foi salva, nenhum pedido foi criado.
4. Testes novos em Kernel e Unit, rodados com
   `php vendor/bin/phpunit -c web/core/phpunit.xml.dist` e `SIMPLETEST_DB=mysql://…/aculta_simpletest` de
   `secrets/simpletest-db.env`. Nenhum teste tocou `aculta_runtime`.

Não foi usado `drush cr`, `composer`, `config:import`, `updatedb` nem deploy.

---

## 1. Carrinho integrado

### 1.1 Agrupamento por separador — `CartSite` e `CartUtility`

`CartSite::paraTipo()` (`aculta_portal/src/Cart/CartSite.php:28-45`) usa um mapa fixo:

| Tipo de pedido | Separador | Rótulo |
| --- | --- | --- |
| `default` | `loja` | Loja |
| `curso` | `cursos` | Cursos |
| `doacao` | `apoio` | Apoio |
| `assinatura` | `assinatura` | Assinatura |
| qualquer outro (inclusive vazio) | `loja` | Loja |

`CartSite::ORDEM` (`:25`) é `[loja, cursos, apoio, assinatura]`, mas **não é usada para ordenar o painel**.
`CartUtility::build()` (`src/Cart/CartUtility.php:41-112`) monta os grupos na ordem dos carrinhos do
`cart_provider`, guarda o maior ID de item de cada grupo (`ultimo`, `:67`) e reordena os separadores por esse ID em
ordem decrescente (`uasort`, `:80`). Resultado medido: o separador do **item adicionado por último aparece primeiro**,
o inverso de `CartSite::ORDEM`. Os itens de cada separador também são listados do mais recente para o mais antigo
(`:83`).

Um detalhe que o mapa fixo esconde: `CartUtility` sobrepõe o mapa quando o tipo do carrinho está em
`CheckoutSettings::lmsOrderTypes()` (`:50-54`). Assim, se o LMS passar a usar um tipo diferente de `curso`, esse tipo
vai para `cursos` sem precisar mudar `CartSite`. O tipo histórico `curso` continua indo para `cursos` pelo mapa.

Totais: `count` soma as quantidades de **todos** os carrinhos, inclusive `assinatura` (`:59`); `total` soma os preços
dos itens (`:60-63`). O separador de assinatura aparece na contagem e no total do painel, embora tenha checkout
próprio e fique fora da fusão.

Links:

- `url`: `commerce_cart.page` no domínio `main` (`:96`). No runtime o caminho é `/carrinho`, trocado pelo
  `CartFriendlyRouteSubscriber`; o `/cart` do Commerce responde 404.
- `checkout_url` global: `aculta_portal.cart_checkout`, caminho `/carrinho/finalizar` no domínio `main`
  (`:98-101`).
- `checkout_url` por grupo: preenchido **só para o separador `assinatura`** (`:55`, `:66`), com
  `commerce_checkout.form` do próprio pedido e a etapa salva corrigida pelo `CheckoutStepResolver` (`:117-127`).
  Os demais separadores têm `checkout_url` vazio.

### 1.2 Fusão — `CartConsolidator`

`CartConsolidator::consolidar()` (`src/Cart/CartConsolidator.php:50-95`):

1. Ignora os carrinhos do tipo `assinatura` (`TIPO_ASSINATURA`, `:23`, `:51-54`). Sem carrinhos sobra `NULL`.
2. Calcula o tipo final por `tipoFinal()` (`:59`, `:98-106`): percorre `prioridade()` e devolve o primeiro tipo
   presente entre os carrinhos.
3. Se já existe um carrinho desse tipo, ele é o destino; senão, cria um pedido `draft`, `cart = TRUE`, do tipo final,
   no usuário e na loja atuais (`:61-76`).
4. Para cada outro carrinho, remove cada item, adiciona ao destino, grava `order_id` do item apontando para o destino,
   salva o carrinho de origem e **o apaga** (`:78-90`).
5. Salva o destino e o marca como carrinho **ativo** na sessão (`:92-93`).

### 1.3 Precedência

`prioridade()` (`:39-45`) devolve, nesta ordem:

1. `CheckoutSettings::lmsOrderTypes()` — o tipo de LMS configurado seguido do histórico `curso`;
2. `CheckoutSettings::shopOrderType()` — padrão `default`;
3. `doacao` (literal no código).

Portanto a precedência real é **LMS (tipo configurado) > `curso` histórico > Loja > Doações**. As decisões D-39 e o
plano descrevem "LMS > Loja > Doações" sem citar o desdobramento do LMS em dois tipos.

Se nenhum tipo da lista estiver presente, `tipoFinal()` devolve o tipo da Loja (`:105`). Consequência medida: um
carrinho de tipo **fora da lista** não sobrevive à consolidação. Sozinho, ele é fundido em um pedido novo do tipo da
Loja e o carrinho original é apagado. É exatamente o que aconteceria com um futuro tipo `ingresso` antes de a P4
alterar `prioridade()`.

### 1.4 Fluxo escolhido em `/carrinho/finalizar`

`CartCheckoutController::finalizar()` (`src/Controller/CartCheckoutController.php:33-52`) consolida e redireciona para
`commerce_checkout.form` do pedido destino, no domínio `main`, com a etapa resolvida pelo `CheckoutStepResolver`
(etapa salva se visível; senão, a primeira visível). Sem carrinho consolidável, volta para `commerce_cart.page`
(`/carrinho`).

O checkout flow **não é escolhido pelo controller**: vem do tipo de pedido final, pelo vínculo nativo do
`commerce_checkout` (ou pelo fallback `default` quando o tipo não declara flow). No runtime, por consequência:

| Carrinhos presentes | Tipo do pedido final | Flow efetivo |
| --- | --- | --- |
| curso (ou tipo LMS configurado) + outros | LMS | `curso` (runtime; no sync, `default`) |
| Loja + Doações | `default` | `default` (o tipo não declara flow) |
| só Doações | `doacao` | `doacao` |

Ou seja: **um carrinho integrado formado só por doações abre o checkout pela rota global `commerce_checkout.form`
(`/finalizar-compra/...`), não pela rota dedicada `/apoiar/...`**.

---

## 2. Os três checkouts atuais

### 2.1 Loja

| Item | Valor medido |
| --- | --- |
| Tipo de pedido | `default` (`CheckoutSettings::shopOrderType()`, padrão) |
| Checkout flow | o tipo `default` **não declara** `checkout_flow`; o Commerce usa `default` (`OrderTypeCheckoutFlows::COMMERCE_DEFAULT_FLOW`) |
| Painéis de envio | `aculta_shipping_information` ativo na etapa `order_information`, com `require_shipping_profile: true`; `aculta_billing_address` na mesma etapa |
| Identidade fiscal | `aculta_fiscal_identity` na etapa `review` |
| Rotas | carrinho em `/carrinho`; checkout em `/checkout/{pedido}/{etapa}` (público `/finalizar-compra/{pedido}/{etapa}`) |

### 2.2 LMS

| Item | Valor medido no runtime | Valor em `config/sync` |
| --- | --- | --- |
| Tipo de pedido | `curso` (`CheckoutSettings::lmsOrderType()`, padrão) | idem |
| Checkout flow | **`curso`** | `default` |
| Painéis de envio | `aculta_shipping_information` com `step: _disabled`; `aculta_billing_address` com `step: _disabled` | o flow `default` tem envio **ativo** |
| Identidade fiscal | `aculta_fiscal_identity` na etapa `review` | idem no `default` |
| Pane extra | `aculta_personal_name` na etapa `order_information`; `login` com `allow_registration: true` | o `default` do sync tem `allow_registration: false` |
| Rotas | as mesmas rotas globais do Commerce; não há rota própria de LMS | idem |

Divergência material: o runtime usa o flow `curso` (sem envio) e o `config/sync` aponta o tipo `curso` para o `default`
(com envio). Importar o sync mudaria o LMS de flow, painéis e regra de cadastro. Nenhum dos dois arquivos foi alterado
nesta fase.

### 2.3 Doações

| Item | Valor medido |
| --- | --- |
| Tipo de pedido | `doacao` (`aculta_donation.settings:donate_order_type`) |
| Checkout flow | `doacao` (plugin `fluxo_doacao`, `DonationCheckoutFlow`) |
| Painéis | `commerce_donation_pane` (etapa `donation`), `commerce_memorial_pane` (`dedication`), `contact_information` (`billing`), `review`, `payment_information` (`billing`), `payment_process` (`payment`), `donation_thank_you` (`complete`) |
| Envio | **nenhum** painel de envio; `aculta_billing_address`, `aculta_shipping_information` e `shipping_information` não existem no flow |
| Identidade fiscal | **sem** `aculta_fiscal_identity` |
| Login | pane `login` com `step: _disabled` |
| Rotas | dedicada `/apoiar/{pedido}/{etapa}` (o YAML declara `/donate/...`; o `DonationFriendlyRouteSubscriber` troca para `/apoiar`); rotas globais quando `donation_route` permite |

Assinatura recorrente (`assinatura_flow`, mesmo plugin, sem envio e sem identidade fiscal) fica fora do carrinho
unificado.

O modelo de doação serve para D-42 no que diz respeito a **não ter envio**. Não serve inteiro para Eventos: o flow de
doação não tem `aculta_fiscal_identity`, exigido por D-40 em evento pago. O checkout de Eventos precisará do pane fiscal
que o `default` usa.

---

## 3. Rotas globais e alcance (B-01)

### 3.1 Rotas medidas no runtime

| Rota | Caminho | Requirements |
| --- | --- | --- |
| `commerce_cart.page` | `/carrinho` | `_access: TRUE` |
| `commerce_checkout.checkout` | `/checkout` | `_permission: access checkout` + `_custom_access: aculta_portal.access.protected_route:checkoutStart` |
| `commerce_checkout.form` | `/checkout/{commerce_order}/{step}` | `_custom_access: aculta_portal.access.protected_route:checkout` |
| `aculta_portal.cart_checkout` | `/carrinho/finalizar` | `_access: TRUE` (com `no_cache: true`) |
| `aculta_donation.donation_controller_formPage` | `/apoiar/{commerce_order}/{step}` | `_custom_access: DonationController::checkAccess` |

O `_custom_access` das rotas do Commerce é aplicado pelo `DomainRouteSubscriber`
(`src/EventSubscriber/DomainRouteSubscriber.php:28-47`), não pelo subscriber de política. São dois mecanismos
independentes: um protege a rota, o outro pode fechá-la.

### 3.2 O que o `CommerceCheckoutRouteSubscriber` faz

`src/EventSubscriber/CommerceCheckoutRouteSubscriber.php:28-46`:

- consulta `CheckoutPolicyManager::commonCheckoutRoutesAvailable()`;
- se as rotas estão disponíveis, **retorna sem alterar nada**;
- se estão fechadas, põe `_access: FALSE` em `commerce_cart.page`, e em `commerce_checkout.checkout` e
  `commerce_checkout.form` remove `_permission` e acrescenta `_access: FALSE`.

Observações de alcance:

- O subscriber **não** toca `aculta_portal.cart_checkout` nem `aculta_donation.donation_controller_formPage`. O
  checkout dedicado de Doações continua acessível com as rotas globais fechadas.
- Em `commerce_checkout.checkout` e `commerce_checkout.form`, o `_custom_access` do `DomainRouteSubscriber`
  **permanece**; a negação vem do `_access: FALSE`. O efeito é negação por AND, mas a rota fica com dois mecanismos ao
  mesmo tempo.
- O nome do arquivo citado em B-01 (`CartCheckoutRouteSubscriber`) é o do subscriber **removido** do
  `aculta_donation`. O equivalente atual é `CommerceCheckoutRouteSubscriber`, no Portal.

### 3.3 `commonCheckoutRoutesAvailable()`

`src/Checkout/CheckoutPolicyManager.php:37-47`:

1. se `common_checkout_enabled` for falso, **fecha** — independentemente de qualquer provider;
2. se algum contexto registrado com `gatesCommonCheckout = TRUE` não admitir checkout comum, **fecha**;
3. caso contrário, **abre**.

Hoje só Doações tem `gatesCommonCheckout = TRUE` (`DonationCheckoutProvider::configuration()`); Loja e LMS declaram
`CheckoutMode::Common` sem essa flag. Sem Donation registrado (módulo desabilitado) e com a chave ligada, as rotas ficam
como o Commerce as define.

### 3.4 Alcance de cada valor de `donation_route`

`DonationCheckoutProvider::configuration()` traduz `aculta_donation.settings:donation_route`:

| `donation_route` | Modo | `allowsCommon('donation')` | Efeito nas rotas globais (com `common_checkout_enabled` ligado) |
| --- | --- | --- | --- |
| `donate` | `Dedicated` | não | **fechadas** |
| `cart` | `Common` | sim | mantidas como o Commerce define |
| `both` | `Both` | sim | mantidas como o Commerce define |
| ausente | `Dedicated` | não | **fechadas** |
| valor desconhecido | `Dedicated` | não | **fechadas** |

Com `donation_route` em `donate` (ou ausente), o fechamento é **global**: atinge o carrinho e o checkout da Loja e do
LMS, não só o fluxo de doação. É o risco B-01, agora provado por teste.

### 3.5 Alcance de `common_checkout_enabled`

| Valor | Rotas globais |
| --- | --- |
| `TRUE` | dependem de `donation_route` (item 3.4) |
| `FALSE` | **sempre fechadas**, para qualquer `donation_route` |
| chave ausente | `TRUE` (`CheckoutSettings::commonCheckoutEnabled()`), então valem as regras de 3.4 |

Estado do runtime local: a config `aculta_portal.checkout_settings` **não existe** — nem no runtime, nem em
`config/sync`. Portanto valem os padrões do código: checkout comum habilitado, tipo da Loja `default`, tipo do LMS
`curso`. A medição da política confirma `commonCheckoutRoutesAvailable() === true`.

Modelo de disponibilidade medido no runtime:

| Contexto | `allowsCommon` | `allowsDedicated` |
| --- | --- | --- |
| `shop` | sim | não (Loja não tem checkout dedicado próprio) |
| `lms` | sim | não |
| `donation` | sim (`both`) | sim |
| `eventos` | não (não registrado) | não |

`orderTypeConflicts()` está vazio: nenhum tipo de pedido é reivindicado por mais de um contexto.

---

## 4. Testes de caracterização

Todos os arquivos abaixo são novos. Nenhum código de produção foi alterado. Onde o teste depende da rota pública do
carrinho, foi criado o módulo de suporte `tests/modules/aculta_portal_cart_test`, que registra a rota real
`aculta_portal.cart_checkout` e reaplica o `CartFriendlyRouteSubscriber`; o caminho e o controller da rota real são
conferidos pelo teste Unit de configuração.

| Arquivo | Tipo | Casos | Resultado |
| --- | --- | --- | --- |
| `tests/src/Kernel/Cart/CartConsolidatorCaracterizacaoKernelTest.php` | Kernel | 10 | **PASS** |
| `tests/src/Kernel/Cart/CartUtilityCaracterizacaoKernelTest.php` | Kernel | 8 | **PASS** |
| `tests/src/Kernel/Cart/CartCheckoutControllerCaracterizacaoKernelTest.php` | Kernel | 3 | **PASS** |
| `tests/src/Unit/Checkout/RotasGlobaisCaracterizacaoTest.php` | Unit | 13 | **PASS** |
| `tests/src/Unit/Checkout/CheckoutsConfiguracaoCaracterizacaoTest.php` | Unit | 11 | **PASS** |

Execução conjunta dos cinco arquivos novos: `Tests: 45, Assertions: 757` — **0 falhas, 0 erros**; as 79 ocorrências
restantes são deprecations do core e dos módulos contrib, não falhas dos testes. Com os dois testes de caracterização
anteriores, a rodada final deu `Tests: 64, Assertions: 798`, também **PASS**.

Os testes de caracterização anteriores continuam PASS (19 testes, 41 asserções):
`aculta_donation/tests/src/Unit/CartCheckoutRoutesCharacterizationTest.php` e
`aculta_portal/tests/src/Unit/Cart/CartSiteTest.php`.

### 4.1 O que cada arquivo fixa

**`CartConsolidatorCaracterizacaoKernelTest`**

| Teste | Comportamento fixado | Resultado |
| --- | --- | --- |
| `testSemCarrinhosRetornaNull` | sem carrinhos, `consolidar()` devolve `NULL` | PASS |
| `testCarrinhoUnicoNaoEAparece` | carrinho único volta como está e é marcado ativo | PASS |
| `testPrecedenciaCursoSobreLojaEDoacao` | LMS vence Loja e Doações; carrinhos de origem apagados | PASS |
| `testPrecedenciaLojaSobreDoacao` | Loja vence Doações | PASS |
| `testApenasDoacaoMantemOTipoDeDoacao` | só doação mantém `doacao` | PASS |
| `testAssinaturaFicaForaDaFusao` | assinatura não é fundida nem apagada | PASS |
| `testTipoForaDaPrecedenciaCaiNaLoja` | tipo desconhecido é fundido no tipo da Loja e o carrinho é apagado | PASS |
| `testLmsConfiguradoVemAntesDoCursoHistorico` | tipo LMS configurado vence o `curso` histórico | PASS |
| `testItensMovidosApontamParaODestino` | `order_id` do item passa a ser o do destino | PASS |
| `testConsolidarDuasVezesNaoCriaOutroPedido` | consolidação é idempotente | PASS |

**`CartUtilityCaracterizacaoKernelTest`**

| Teste | Comportamento fixado | Resultado |
| --- | --- | --- |
| `testCarrinhoVazio` | painel vazio, `count` 0, `sites` vazio | PASS |
| `testAgrupamentoPorSeparador` | mapa por tipo, ordem do mais recente, assinatura no total | PASS |
| `testTipoDesconhecidoCaiNaLoja` | tipo sem separador cai em `loja` | PASS |
| `testTipoDeLmsConfiguradoVaiParaCursos` | `lms_order_type` move o tipo para `cursos` | PASS |
| `testContagemETotalSomamTodosOsSeparadores` | `count` e `total` somam os separadores | PASS |
| `testApenasAssinaturaTemCheckoutPorGrupo` | só assinatura tem `checkout_url` de grupo | PASS |
| `testLinksGlobais` | `/carrinho` e `/carrinho/finalizar` no domínio principal | PASS |
| `testItensDoSeparadorDoMaisRecenteParaOMaisAntigo` | itens ordenados do mais recente ao mais antigo | PASS |

**`CartCheckoutControllerCaracterizacaoKernelTest`**

| Teste | Comportamento fixado | Resultado |
| --- | --- | --- |
| `testSemCarrinhoRedirecionaParaOCarrinho` | sem carrinho, 302 para `/carrinho` | PASS |
| `testComCarrinhoRedirecionaParaOCheckoutConsolidado` | 302 para `/checkout/{pedido do tipo final}/…`; carrinho fundido apagado | PASS |
| `testRedirecionamentoUsaODominioPrincipal` | destino no domínio `main` | PASS |

**`RotasGlobaisCaracterizacaoTest`**

| Teste | Comportamento fixado | Resultado |
| --- | --- | --- |
| `testDonationRouteSemCarrinhoFechaAsRotasGlobais` (3 casos) | `donate`, ausente e desconhecido fecham as três rotas | PASS |
| `testCartOuBothMantemAsRotasGlobais` (2 casos) | `cart` e `both` mantêm as rotas do Commerce | PASS |
| `testChaveGlobalDesligadaFechaTodasAsRotas` (3 casos) | `common_checkout_enabled = FALSE` fecha tudo | PASS |
| `testRotasDedicadasNaoSaoTocadas` (3 casos × 2 chaves) | `/carrinho/finalizar` e `/apoiar/...` não mudam | PASS |
| `testDisponibilidadePorContexto` | `allowsCommon`/`allowsDedicated` por contexto | PASS |
| `testSomenteDonationControlaAsRotasGlobais` | sem Donation registrado, rotas como o Commerce | PASS |

**`CheckoutsConfiguracaoCaracterizacaoTest`**

| Teste | Comportamento fixado | Resultado |
| --- | --- | --- |
| `testTipoDePedidoApontaParaOCheckoutFlow` (4 casos) | `default` sem flow; `curso`→`default`; `doacao`→`doacao`; `assinatura`→`assinatura_flow` (config versionada) | PASS |
| `testFluxoDaLojaTemPaineisDeEnvio` | `aculta_billing_address` e `aculta_shipping_information` em `order_information` | PASS |
| `testFluxoDeDoacaoNaoTemPaineisDeEnvio` | plugin `fluxo_doacao`, sem painel de envio | PASS |
| `testFluxoDeAssinaturaNaoTemPaineisDeEnvio` | plugin `fluxo_doacao`, sem painel de envio | PASS |
| `testFluxoDeEnvioNaoEstaLigadoATipoDePedido` | o flow `shipping` não é usado por nenhum tipo | PASS |
| `testConfiguracaoDaDoacao` | `both`, `doacao`, `doacao`, cart vazio | PASS |
| `testRotasPublicasDoCarrinhoEDoCheckoutDeDoacao` | `/carrinho/finalizar` e `/apoiar/{pedido}/{etapa}` | PASS |
| `testRotasGlobaisEDedicadasNaoSeSobrepõem` | caminhos distintos | PASS |

### 4.2 O que ficou DEFERRED

- **Functional / BrowserTestBase:** o ambiente não fornece `SIMPLETEST_BASE_URL` (limitação já registrada em
  `docs/modules/ACULTA-LMS-ROADMAP.md`). A rota `/carrinho/finalizar` foi caracterizada em Kernel, chamando o
  controller real com a rota pública registrada. Nenhum navegador foi usado.
- **Teste Olivero:** não se aplica nesta fase — não há mudança de tema nem de renderização; os testes são Kernel/Unit,
  sem template. A regra segue valendo para as fases com interface.

---

## 5. Pontos que contradizem a documentação ou as decisões

1. **D-38 / I-4 — "`CheckoutSettings` recebe o contexto de Eventos".** Hoje `CheckoutSettings` só expõe
   `common_checkout_enabled`, `shop_order_type` e `lms_order_type`; não há campo para Eventos. O registro de contextos
   vive no `CheckoutContextRegistry` com providers. E a própria config `aculta_portal.checkout_settings` **não existe**
   no runtime local nem em `config/sync`, então valem os padrões do código. A frase "Registro no Portal:
   `CheckoutSettings` recebe o contexto de Eventos" descreve algo que ainda não existe.
2. **ADR-013, adendo de 2026-10-23 — "Loja e LMS passam a usar um único flow, o `default`; o flow `curso` sai da
   configuração".** No `config/sync` o tipo `curso` aponta para `default`, mas o **runtime local ainda tem o flow
   `curso`** e o tipo `curso` aponta para ele. A unificação não foi importada. Como o flow `curso` tem envio desabilitado
   e o `default` tem envio ativo, importar o sync mudaria o checkout do LMS (painel de envio, `aculta_personal_name`,
   `allow_registration`). É uma mudança de comportamento que o aditivo de Eventos não pede.
3. **D-42 / I-12 — "fluxo modelo: checkout de doação".** Confirmado que a doação não tem painéis de envio. Mas o flow
   de doação **também não tem `aculta_fiscal_identity`**, e D-40 exige CPF em evento pago. O modelo de doação terá de
   ser complementado com o pane fiscal que o `default` usa — não pode ser copiado literalmente.
4. **Precedência "LMS > Loja > Doações" (D-39, plano §4).** O código acrescenta o tipo LMS configurado **antes** do
   histórico `curso`, e o fallback de tipo final é o tipo da Loja. Um tipo fora da lista (como um futuro `ingresso`) é
   fundido no tipo da Loja e o carrinho original é apagado. Incluir Eventos na P4 exige mudar `prioridade()`, que é
   código de produção.
5. **`CartSite` não lê configuração.** O mapa é fixo (`default`, `curso`, `doacao`, `assinatura`) e tipo desconhecido cai
   em Loja. Só `CartUtility` corrige o tipo LMS configurado. Um separador "Eventos" exigirá mudança em `CartSite` e/ou
   `CartUtility` (P4).
6. **Assinatura no painel, mas fora da fusão.** `CartUtility` soma a assinatura em `count` e `total`, e o botão geral
   `/carrinho/finalizar` não a consolida. Quem só tem assinatura vê o item no painel e o botão geral leva de volta a
   `/carrinho`, sem checkout comum. O comportamento atual não está descrito no plano.
7. **B-01 cita `CartCheckoutRouteSubscriber`.** Esse subscriber foi removido do `aculta_donation`; o equivalente atual é
   `CommerceCheckoutRouteSubscriber`, do Portal. O alcance continua global e foi provado: `donation_route = donate`
   fecha `commerce_cart.page`, `commerce_checkout.checkout` e `commerce_checkout.form` para Loja e LMS.
8. **`common_checkout_enabled` desligado fecha tudo.** Não é uma chave só de Doações: fecha o carrinho e o checkout
   comum usados por Loja e LMS. No runtime atual a config não existe, então a chave vale `TRUE`; a P2 não deve importar
   a config com valor diferente do padrão sem prova de equivalência.
9. **Rotas com dois mecanismos de acesso.** Ao fechar `commerce_checkout.checkout` e `commerce_checkout.form`, o
   subscriber remove `_permission` mas **mantém** o `_custom_access` do `DomainRouteSubscriber`, somando `_access:
   FALSE`. O resultado é negação, mas quem migrar o ownership precisa preservar os dois.
10. **Doação em `/donate` no YAML, `/apoiar` no runtime.** `aculta_donation.routing.yml` declara `/donate/...`; o
    caminho público real é `/apoiar/...`, aplicado pelo `DonationFriendlyRouteSubscriber`. Documentação que cite
    `/donate` está desatualizada.

---

## 6. O que ficou para a P2 (e adiante)

Fora do escopo desta fase, conforme o plano:

- tipo de pedido `ingresso` e tipo de item de lote (I-1, I-2), resolvendo o ponto F;
- contexto de Eventos no Portal (`CheckoutContextProviderInterface` + campos próprios, se necessários) — não em
  `CheckoutSettings` como está hoje;
- lotes de ingresso por data e vínculo com `aculta_event_date`;
- regras de taxa (D-33) e de CPF apenas em evento pago (D-40);
- entrada de Eventos no carrinho integrado (P4), com mudança em `prioridade()` e em `CartSite`/`CartUtility`, mais a
  regressão da barra e dos três checkouts;
- checkout próprio de Eventos (P3) com o pane fiscal e sem painéis de envio.

Nenhum desses itens foi iniciado.

---

## 7. Restrições observadas

- Nenhum arquivo de produção alterado: `CartConsolidator`, `CartSite`, `CartUtility`, `CommerceCheckoutRouteSubscriber`
  e as rotas globais permanecem como estavam.
- Nenhuma configuração armazenada, ID de pedido ou tipo de pedido existente foi alterado.
- Testes Kernel só no banco `aculta_simpletest`; `aculta_runtime` não foi tocado.
- Sem `composer require`, `drush cr`, `updatedb`, `config:import`, deploy ou push.
- Nenhum acesso a produção (`*.aculta.org`).
