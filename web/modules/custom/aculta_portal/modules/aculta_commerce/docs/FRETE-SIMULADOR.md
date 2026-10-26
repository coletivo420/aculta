# Simulador de frete por CEP — estudo E0 (porte do commerce_cart_estimate)

Data: 2026-10-26. Decisão do responsável (18:43): simulador de frete por CEP na página do
produto, no carrinho lateral e no carrinho completo, **portando** o contrib e sem depender
dele.

- Base: PR #298 @ `3b964f8b7a56d16d05c55cf6e398a257b6eeafb5`.
- Contrib estudado: `commerce_cart_estimate` **2.0.5** (GPL-2.0-or-later), baixado de
  `https://ftp.drupal.org/files/projects/commerce_cart_estimate-2.0.5.tar.gz`, sha256
  `8ba62c3d05fdeb4cd66369291de6820bbbd5ec3c233cdf714e453d285a20248e`, extraído em
  `/home/piradopirata/worktrees/estudo-cart-estimate/` (fora do repositório, fora do
  Composer). Nada de código foi alterado nesta fase.

## 1. Inventário do contrib e destino no aculta_commerce

O contrib depende de `commerce_shipping:commerce_shipping`. Ele **não** usa o nosso motor;
o porte precisa trocar essas peças pelas equivalentes do `aculta_commerce`.

| Arquivo / classe (2.0.5) | Papel | Dependências | Destino proposto |
|---|---|---|---|
| `src/EstimatorInterface.php` | Contrato `buildShippingProfile()` e `estimate()` | — | `Drupal\aculta_commerce\Shipping\Estimate\ShippingEstimateInterface` |
| `src/Estimator.php` | Cria pedido duplicado, empacota, calcula rates, aplica rate, atualiza totais em memória | `event_dispatcher`, `OrderRefreshInterface`, `commerce_shipping.order_manager`, `commerce_shipping.shipment_manager`, `address.subdivision_repository` | `ShippingEstimateService` + `ShippingEstimateCalculator` no `aculta_commerce` |
| `src/CartEstimateResult.php` | Value object com pedido estimado e rates | `commerce_order` | `ShippingEstimateResult` com `ShippingQuote[]`, totais e `expires_at` |
| `src/OrderRefresh.php` | Refresh sem salvar; coleta preprocessors/processors | `commerce_price.chain_price_resolver`, tags `commerce_order.order_preprocessor`/`order_processor` | Reutilizar `EarlyOrderProcessor`/`LateOrderProcessor` do nosso motor com o sinalizador de estimativa; ou um refresh dedicado que **não** salva |
| `src/LateOrderProcessor.php` | Impede salvar Shipment quando o pedido é estimativa | `commerce_shipping.LateOrderProcessor` | Guarda equivalente no `aculta_commerce.shipping_late_order_processor` |
| `src/EventSubscriber/OrderSubscriber.php` | Bloqueia `Order::save()` durante estimativa | evento de pedido do Commerce | Hook/guarda no nosso motor (sem depender de `commerce_shipping`) |
| `src/EventSubscriber/ShipmentSubscriber.php` | Bloqueia `Shipment::save()` durante estimativa | evento de Shipment do Commerce | Guarda no `ShipmentManager`/`LateOrderProcessor` do nosso motor |
| `src/CommerceCartEstimateServiceProvider.php` | Troca a classe do late processor do `commerce_shipping` no container | `ServiceProviderBase` | **Não portar**: o nosso motor já é dono do late processor |
| `src/Plugin/views/area/CartEstimate.php` | Área de Views com formulário país/CEP, AJAX e resultado | `views`, `address_country`, Order/Shipping managers do contrib | Substituir por render element + SDC no `aculta420`; não portar a área de Views |
| `src/Event/CartEstimateEvents.php`, `SelectShippingRateEvent.php` | Permite escolher o rate aplicado | `event_dispatcher` | `ShippingEstimateEvents::SELECT_QUOTE`, disparado pelo nosso selector |
| `src/Exception/CartEstimateException.php` | Falha ao empacotar | — | `ShippingEstimateException` |
| `src/Exception/NoApplicableShippingRatesException.php` | Sem rates | — | Mesma hierarquia; a UI traduz para “frete indisponível” |
| `src/Exception/OrderSaveException.php`, `ShipmentSaveException.php` | Garantias de não-salvamento | — | Não portar: o nosso serviço não tenta salvar |
| `commerce_cart_estimate.module` | `views_data_alter`, `hook_theme`, preprocess do resumo | Views | Sem equivalente: o resumo é calculado no serviço e renderizado pelo SDC |
| `templates/commerce-cart-estimate-summary.html.twig` | Resumo de totais do contrib | `commerce_order/total_summary` | SDC `shipping-estimate` no `aculta420` |
| `css/commerce_cart_estimate.form.css`, `libraries.yml` | Estilo e biblioteca | — | CSS do SDC; sem biblioteca do contrib |
| `config/schema/...schema.yml` | Opções da área de Views (rótulos, container) | Views area | Config schema do render element/SDC, se configurável |
| `tests/src/Kernel/EstimatorTest.php` | Prova empacotamento/rates | Kernel do contrib | Novo Kernel test do serviço no `aculta_commerce` |
| `tests/modules/commerce_cart_estimate_test` | Subscriber de seleção de rate | evento | Fixture do nosso teste de evento |

### Substituição das peças do `commerce_shipping`

| Peça do contrib (`commerce_shipping`) | Equivalente no nosso motor |
|---|---|
| `commerce_shipping.order_manager` (`createProfile`, `isShippable`, `pack`) | `aculta_commerce.shipping_order_manager` (`ShippingOrderManager::createProfile`, `isShippable`, `pack`) |
| `commerce_shipping.shipment_manager` (`calculateRates`, `applyRate`) | `aculta_commerce.shipment_manager` (`ShipmentManager::calculateRates`, `applyRate`) |
| `Shipment` / `ShipmentInterface` | `ProposedShipment`/`Shipment` internos + `ShippingQuote` |
| `plugin.manager.commerce_shipping_method` | `plugin.manager.aculta_shipping_method` (`FlatRate`, `FlatRatePerItem`, `MelhorEnvio`) |
| `commerce_shipping.late_order_processor` | `aculta_commerce.shipping_late_order_processor` |
| `OrderRefreshInterface` do contrib | Processors do `aculta_commerce` com o sinalizador de estimativa |

## 2. Serviço de estimativa sem efeito colateral

Contrato proposto:

```text
ShippingEstimateService::estimate(ShippingEstimateInput $input): ShippingEstimateResult
```

Entrada:

- `country_code` e `postal_code` (normalizados; só o CEP e o país entram no cálculo);
- itens: `type:id` do purchased entity + quantidade, **ou** variação + quantidade na página do produto;
- loja, moeda e a lista de métodos habilitados;
- opcional: serviço selecionado pelo cliente (para o “selecionar” sem aplicar no pedido).

Fluxo, todo em memória:

1. `ShippingOrderManager::createProfile()` monta o perfil temporário com o endereço parcial;
   **o perfil não é salvo**.
2. Um duplicado do pedido (sem `order_id` novo) é empacotado com `ShippingOrderManager::pack()`.
3. `ShipmentManager::calculateRates()` consulta os plugins de `plugin.manager.aculta_shipping_method`.
4. `ShippingQuoteSelector::available()` filtra moeda, validade, IDs duplicados; `select()`
   resolve a escolha apenas a partir da lista confiável.
5. Os totais (subtotal, frete, total) são calculados em memória a partir das cotações e dos
   preços já resolvidos; nada é aplicado ao pedido real.
6. Retorna `ShippingEstimateResult` com `quotes`, `selected`, `subtotal`, `shipping_total`,
   `total`, `currency`, `expires_at` e `cache_hit`.

Invariantes obrigatórias:

- nenhum `$order->save()`, nenhum `Shipment::save()`, nenhuma remessa, nenhum ajuste
  persistido, nenhum e-mail, fila ou token;
- perfil temporário não salvo;
- falha de empacotamento/cotação → resultado vazio + estado `unavailable`, nunca frete zero;
- o valor exibido é **o valor exato a ser cobrado** enquanto a cotação estiver válida:
  mesmo caminho de preço do checkout (mesmos plugins, mesma moeda, mesma data de cálculo
  e os mesmos ajustes), sem arredondamento próprio nem faixa aproximada;
- a cotação carrega `valid_until`; uma cotação expirada não é exibida como válida — é
  recalculada antes de aparecer;
- o checkout revalida a mesma cotação (ID do método/serviço, valor e validade). Se ela
  expirou ou mudou, recalcula e mostra o novo valor **antes do pagamento**; o valor do
  checkout é o autoritativo.

## 3. Três pontos de entrada e contrato neutro Portal → tema

Contrato: o Portal devolve um array neutro (strings, inteiros, listas), sem entidades nem
classes de shipping. O tema só consome props.

```php
[
  '#type' => 'aculta_frete_simulador',
  '#postal_code' => '01310-100',
  '#country_code' => 'BR',
  '#status' => 'idle|loading|ok|unavailable|rate_limited',
  '#message' => '...',
  '#quotes' => [[
    'id' => 'metodo:servico',
    'label' => 'PAC',
    'amount' => 'R$ 23,40',
    'selected' => FALSE,
  ]],
  '#totals' => ['subtotal' => '...', 'shipping' => '...', 'total' => '...'],
  '#action' => '/…/frete/estimar', // same-origin, rota interna
  '#cache' => ['max-age' => 600, 'contexts' => ['session'], 'tags' => [...]],
]
```

- **Carrinho completo:** SDC `shipping-estimate` renderizado no topo do painel do carrinho,
  substituindo a área de Views do contrib. O Portal entrega props neutros; o tema aplica
  UI Styles/variantes. Mantém os rótulos configuráveis do contrib (título, descrição,
  rótulo do botão, botão limpar) como props.
- **Carrinho lateral (`cart-offcanvas`):** novo slot/prop `shipping_estimate` no SDC
  existente. O Portal renderiza o mesmo render element; o JS do componente só troca o
  conteúdo do slot e os totais, sem re-renderizar a gaveta inteira.
- **Página do produto (`product-buy`):** novo slot `shipping_estimate` abaixo do formulário
  de compra. A variação/quantidade vêm do formulário do Commerce; o CEP é digitado no
  simulador.

A seleção do cliente (método/serviço, valor exato e validade) acompanha o pedido até o
checkout, que revalida a mesma cotação no servidor. Não é um ajuste persistido no carrinho:
enquanto a cotação vale, o valor cobrado é o mesmo do simulador; se expirar, o checkout
recalcula e mostra o novo valor antes do pagamento.

SDC novo no `aculta420`: `components/patterns/shipping-estimate/` com props acima, CSS
mobile-first, sem dependência de jQuery. O JS é mínimo e usa `#ajax` do Form API
(mesma origem, token/CSRF do Drupal), não `fetch` artesanal. Sem JS, o submit recarrega a
página com o resultado. Estados de carregamento com `aria-live` e sem deslocamento de
layout.

O componente expõe variantes — `full` no carrinho completo, `compact` na gaveta e
`inline` no produto — e o tema pode registrar estilos **UI Styles** (`ui_styles`,
`ui_styles_ui_patterns`, já no `core.extension.yml`) para espaçamento e tipografia, sem
CSS por instância. A estrutura de props é a mesma nas três variantes.

## 4. Proteção

- **Cache obrigatório** por chave `hash(país + CEP normalizado + itens + métodos + moeda)`.
  TTL sugerido: 10 minutos para cotação positiva, 60 segundos para “indisponível”
  (cache negativo curto). O TTL efetivo é o **menor** entre 10 minutos e o `valid_until`
  da cotação: nunca servir cotação vencida. Backend de cache do Drupal, com tags dos
  métodos/config. O CEP não aparece em claro na chave, em log ou em HTML de debug.
- **Limite por sessão/IP** para anônimos: usar `flood` com evento neutro (ex.:
  `aculta_frete_estimativa`), 10 tentativas/minuto por IP e 30/minuto para autenticados.
  Ao estourar, responder `429` e “frete indisponível”; não revelar limite exato.
- **CAPTCHA não** por padrão. Justificativa: o custo é uma consulta de cotação já cacheada
  e limitada; CAPTCHA prejudica conversão no produto. Reavaliar só se houver abuso
  sustentado após o flood e o cache.
- **Sem dados pessoais em log:** registrar apenas contagem, duração, cache hit/miss e
  categoria de erro. Nunca CEP completo, endereço, nome, e-mail ou token.
- **CSRF/origem:** formulários do Form API com `#ajax`; rota interna, mesma origem; nada
  de endpoint público sem token de formulário. Nenhum CORS cross-site.

## 5. Melhor Envio: como a cotação real entra

- A cotação real usa `MelhorEnvioQuoter::quote()` (cliente com `connect_timeout` 5 s,
  timeout total 20 s, até 3 tentativas apenas em operações idempotentes, retry em
  429/500/502/503/504).
- O simulador **adiciona cache obrigatório** em cima do quoter: chave com destino
  (país/CEP/UF calculados), pacote (peso/dimensões/valor declarado) e serviços. O TTL é
  de 10 min, limitado ao `valid_until` da cotação — cotação vencida não é servida.
  Sem cache, cada tecla ou submit consumiria cota da API.
- Falha, timeout ou lista vazia → `unavailable` (“frete indisponível”). **Nunca** inventar
  frete zero e **nunca** contratar etiqueta, criar remessa, gerar pagamento ou escrever
  token. A estimativa não chama endpoints de carrinho/etiqueta.
- O limite por sessão/IP protege a cota do Melhor Envio; o cache negativo de 60 s evita
  martelar a API durante indisponibilidade.
- `live_mode`/ambiente continuam decididos pelo modo configurado, não pelo prefixo do token.

## 6. Licenciamento

O contrib é **GPL-2.0-or-later**. No porte:

- manter `LICENSE.txt` do contrib no submódulo/diretório do porte;
- criar `README.original-commerce_cart_estimate.md` com versão `2.0.5`, datestamp
  `1771847899`, URL de origem e sha256 do tarball;
- docblock em cada classe portada: `Adapted from drupal/commerce_cart_estimate 2.0.5
  (GPL-2.0-or-later)`;
- **não** adicionar `commerce_cart_estimate` ao `composer.json` nem à instalação; o código
  passa a ser do `aculta_commerce` (mesmo padrão de `aculta_pagamentos_mercadopago`).

## 7. Fases E1–E3, critérios de aceite e dependência do cutover

| Fase | Entrega | Critérios de aceite e testes |
|---|---|---|
| **E1** | Serviço de estimativa sem efeito colateral + adaptador para o motor atual; feature flag desligada | Unit: entrada inválida, perfil não salvo, sem `save()` de pedido/remessa, seleção só da lista confiável. Kernel: empacotamento com 1–3 itens e cotação de `FlatRate`; falha → `unavailable`. Nenhum write em `commerce_order`/shipment. |
| **E2** | Render element + SDC `shipping-estimate`; três pontos de entrada; AJAX same-origin; cache e flood | Kernel/render: props neutros, estados `ok`/`unavailable`/`rate_limited`. Browser (Firefox ESR, `DISPLAY=:0`) no servidor de testes: produto, gaveta e carrinho completo em mobile e desktop; sem JS o submit funciona; nenhum CEP em log. Teste de flood: 11ª chamada anônima no minuto → 429. **O frete exibido deve ser idêntico ao total do checkout para o mesmo CEP/ itens enquanto a cotação for válida**; divergência só é aceitável após expiração/revalidação, e o checkout deve mostrar o novo valor antes do pagamento. |
| **E3** | Cotação real do Melhor Envio com cache/timeout; medição de latência e taxa de cache | Teste com sandbox: cache hit não chama a API; timeout → `unavailable` em ≤20 s; nenhuma remessa/etiqueta criada; cotação nunca persiste token. Medir p50/p95 com cache frio/quente e documentar. |

**Dependência do cutover do frete:** o porte assume o nosso motor (`ShippingOrderManager`,
`ShipmentManager`, `plugin.manager.aculta_shipping_method`) e **não** pode importar
`commerce_shipping`. Enquanto o checkout ainda usar `commerce_shipping`, o simulador fica
atrás de feature flag e a cotação do checkout continua sendo a fonte de verdade. A E3 só
entra em produção depois do cutover do frete para o `aculta_commerce` e da remoção da
dependência do `commerce_shipping` no checkout.

## Decisões confirmadas (2026-10-26)

1. **Carrinho completo:** usar **SDCs e UI Styles** no `aculta420`; o Portal entrega props
   neutros. Não portar a área de Views do contrib, não criar bloco legado.
2. **Frete preciso:** o valor exibido é o **exato a ser cobrado** enquanto a cotação for
   válida, calculado pelo mesmo caminho do checkout. O checkout revalida a mesma cotação;
   se ela expirou ou mudou, recalcula e mostra o novo valor antes do pagamento. A E2
   compara **igualdade exata** entre o frete exibido e o total do checkout.
3. **TTL e limites:** confirmados — 10 min positivo, 60 s negativo; 10/min anônimo e
   30/min autenticado por IP/sessão, via `flood`.
4. **CAPTCHA:** sem CAPTCHA. Flood e cache são a primeira barreira; reavaliar só com
   abuso comprovado.
5. **Anônimos:** permitidos, com o limite de flood acima.
6. **Nomes:** confirmados — `aculta_commerce.shipping_estimate*` e namespace
   `Drupal\aculta_commerce\Shipping\Estimate\*`.
7. **E3:** continua condicionada ao cutover do frete para o `aculta_commerce`; o porte não
   importa `commerce_shipping`.

## 8. Ajustes após a revisão do E0 (2026-10-26)

### 8.1 Validade da cotação (`valid_until`) — E1

**Estado atual, verificado no código:** `ShippingQuote` (`src/Shipping/ShippingQuote.php`) já tem o campo
`validUntil` (inteiro Unix, ou `NULL`) e o método `canUse()` que o confere. Porém **nada atribui o campo**: não há
nenhuma construção de `ShippingQuote` no motor que o preencha, e o `QuoteResponseParser` do Melhor Envio não lê
validade da resposta da API. Consequência: toda cotação positiva hoje tem `validUntil = NULL` e é tratada como
válida sem prazo. Isso é o defeito a corrigir em E1.

**Especificação:**

- Campo: `ShippingQuote::$validUntil`, inteiro Unix em segundos. Continua opcional no construtor, mas **a fábrica de
  cotações sempre o preenche** para cotação positiva.
- **Default quando ausente ou não informado pelo provedor:** `emitted_at + 600` (10 minutos), onde `emitted_at` é o
  instante em que a cotação foi obtida (início da chamada bem-sucedida, não o instante da resposta).
- **Origem por provedor:**
  - Melhor Envio: a API não informa validade. `valid_until = emitted_at + 600`. O parser não deve inventar outro prazo.
  - Motor próprio (flat rate, flat rate por item): o preço depende só da configuração do método. `valid_until =
    emitted_at + 600`, e a alteração de configuração de qualquer método invalida o cache por tag, sem esperar o prazo.
- `canUse()` passa a exigir `validUntil` preenchido em cotação positiva. Cotação com `NULL` é rejeitada como inválida,
  nunca aceita como eterna.
- A entrada no cache e no tempstore expira em `min(10 min, valid_until)`. Cotação vencida nunca é servida nem
  revalidada sem recálculo.

### 8.2 Seleção até o checkout — armazenamento sem escrita no pedido

- **Armazenamento:** `tempstore.private`, coleção `aculta_commerce.shipping_estimate`. A chave é
  `hash(store_id + cart_id + session_id)`. Para anônimo, o owner do tempstore é o uid 0 (compartilhado), então o
  `session_id` na chave é obrigatório. Para autenticado, o owner já é o uid, e o `session_id` continua na chave para
  isolar sessões.
- **Conteúdo gravado** (nunca o CEP em claro):
  `method_id`, `service_id`, `amount` (decimal como string), `currency`, `valid_until`, `emitted_at`,
  `items_hash` (purchased entity + quantidade + variação), `postal_code_hash` e `country_code`.
- **Expiração:** `set()` com expiração igual a `valid_until`. A entrada é apagada na troca de carrinho, no
  esvaziamento do carrinho e ao concluir o pedido.
- **Sem escrita no pedido durante a simulação.** A gravação do método escolhido no pedido continua acontecendo só no
  passo de envio do checkout, como hoje.
- **Pane `aculta_shipping_information`:**
  1. Lê a entrada do tempstore.
  2. Se `items_hash`, `postal_code_hash` e `country_code` batem com o carrinho atual e `valid_until > agora`, pré-seleciona
     o método e exibe o valor gravado.
  3. Se algo não bate ou expirou, recalcula com `ShippingEstimateService` e exibe o **novo** valor.
  4. Se o valor mudou em relação ao exibido na gaveta ou ao já selecionado, o pane mostra a mudança e **bloqueia o
     avanço para o pagamento** até o cliente confirmar. O valor do checkout é o autoritativo.

### 8.3 Chave do cache e o que altera o preço

A chave do cache de cotação inclui, além de país, CEP normalizado, itens, métodos e moeda:

- **Loja** (`store_id`).
- **Contexto de preço do cliente:** o conjunto de papéis/grupos do usuário que o resolvedor de preço considera. Para
  anônimo, o conjunto vazio.
- **Promoções aplicadas ao carrinho:** o conjunto ordenado de IDs de promoção aplicadas. **Hoje o conjunto é sempre
  vazio**: nenhum módulo de promoção ou cupom está habilitado (verificado; `commerce_promotion` não está ativo e não há
  referência a `PromotionOffer` ou cupom no código do ACULTA).
- **Declaração:** promoções **não atuam sobre o frete** nesta versão. Se um módulo de promoção com oferta de frete
  for habilitado, esta especificação precisa ser revisada antes, porque a chave deve passar a incluir o hash das
  promoções aplicadas. Sem essa revisão, a garantia de valor exato não vale para esse caso.

### 8.4 Limite de flood por IP — antes de ligar para anônimos

- **Servidor de testes:** verificado nesta revisão. O Apache responde direto, sem header `cf-ray` (Cloudflare) na
  resposta, e `settings.php` mantém `reverse_proxy` desligado. Nesse desenho, `REMOTE_ADDR` é o IP do cliente. **Ainda
  assim, antes de ligar o limite, confirmar com duas origens de rede diferentes que o IP observado muda por cliente.**
- **Produção:** **não verificado.** Antes de ligar o limite para anônimos, confirmar a topologia (proxy, balanceador,
  CDN). Se houver proxy: definir `$settings['reverse_proxy'] = TRUE` e `$settings['reverse_proxy_addresses']` com
  **somente** os IPs dos proxies confiáveis; sem isso, todos os anônimos compartilham um IP e o limite global bloqueia
  todo mundo, ou `X-Forwarded-For` pode ser forjado por cliente.
- Critério de aceite de E2: duas origens geram contadores independentes de flood.

### 8.5 Pré-preenchimento do CEP (opcional)

- Opcional, fora do escopo de E1. Quando o endereço de entrega ainda não tem CEP, o pane pode pré-preencher o campo
  com o CEP do endereço de cobrança ou do último endereço do próprio cliente, usando `aculta_portal_cep` para
  normalização. Nunca preencher a partir de dado de outro cliente nem de cookie não autenticado.

### 8.6 Decisão pendente do responsável

- **"Exibido + revalidado" (8.2) ou "persistido no carrinho":** **pendente**. Registrar aqui a decisão quando ela vier.
  Até lá, a especificação acima assume "exibido + revalidado", sem persistir a cotação no carrinho.
