## Dev 0.1.0-dev.10 — motor de envios próprio no `aculta_commerce` (2026-10-26)

- Implementado o núcleo de Shipping dentro do `aculta_commerce`, sem novo módulo e sem editar `contrib`: entidades `aculta_shipment`, `aculta_shipping_method` e `aculta_shipment_type`; campo `shipments` do pedido apontando para `aculta_shipment`; trait `aculta_purchasable_entity_shippable` (campo `weight`); workflow `aculta_shipment_default`.
- Plugin manager `plugin.manager.aculta_shipping_method`, atributo `AcultaShippingMethod`, base `ShippingMethodBase` e plugins `aculta_flat_rate` e `aculta_flat_rate_per_item`. Método sem valor configurado falha fechado (nunca retorna tarifa zero).
- Serviços próprios: `shipment_manager`, `shipping_order_manager`, `packer_manager`, `DefaultPacker`, `package_type_manager`, processors de pedido (`EarlyOrderProcessor`/`LateOrderProcessor`) que transferem a tarifa selecionada para o total do pedido.
- Checkout pane próprio `aculta_shipping_information` em `aculta_commerce`, substituindo o pane do Portal que herdava de `commerce_shipping`. Mantém o endereço único (cópia do perfil de cobrança do `BillingAddressPane`), recalcula tarifas a cada montagem, invalida a tarifa quando o endereço muda e revalida a escolha no servidor antes de aplicar.
- Config Sync: removido `commerce_shipping` de `core.extension`; flows `default` e `shipping` usam o pane próprio; tipo de pedido `default` declara `third_party_settings.aculta_commerce.shipment_type`; variação física usa o trait ACULTA; `field.storage.commerce_order.shipments` aponta para `aculta_shipment`; removidas as configs de exibição/view do contrib e o view `order_shipments`; papel anônimo sem `view commerce_shipping_method`.
- Mantida uma área de leitura em `/estudio/loja/envios` no Green Stash, com estado, serviço, tarifa e rastreamento, sem links para formulários nativos.
- Unit: 49 testes / 230 asserções PASS no `aculta_commerce` (15 depreciações PHPUnit), incluindo seletor de quotes, `FlatRatePluginTest` e `ShippingRateTest`. PHP lint e PHPCS Drupal/DrupalPractice PASS nos arquivos novos. Kernel do motor permanece **skipped** pelo mesmo bloqueio de harness de 212 módulos registrado em `DonationPortKernelTest`.
- **Validação em banco descartável (2026-10-26):** site `standard` instalado em `sites/shipport` sobre o banco `aculta_simpletest` com prefixo `shipport_`, depois removido e com as tabelas apagadas. Resultados: entidades e plugin types instalados; trait `aculta_purchasable_entity_shippable` cria o campo `weight`; pedido físico empacotado com peso `0,6 kg`; tarifa própria `19,90 BRL` calculada e aplicada; total do pedido `139,90 BRL` com ajuste `shipping:19,90`; pedido digital não enviável, sem remessa e sem ajuste de frete. Rehearsal de migração: `commerce_shipping` instalado primeiro, `aculta_commerce` habilitado, `field.storage.commerce_order.shipments` reapontado para `aculta_shipment` em modo sync e contrib desinstalado; o motor continuou calculando tarifas e o checkout descobriu o pane próprio. Instalar o contrib com o field storage ACULTA já existente falha com `PreExistingConfigException`; o cutover deve seguir a ordem de atualização da config e desinstalação, não uma instalação conjunta.
- Correções descobertas na instalação limpa: subscriber de `ReferenceablePluginTypesEvent` para o tipo `commerce_plugin_item:aculta_shipping_method`; subscriber de `commerce_order.profiles` para expor o perfil da remessa; tipo de ajuste `shipping` em `aculta_commerce.commerce_adjustment_types.yml`; `CurrencyFormatterInterface` do pacote `commerceguys/intl` no pane; removido o campo base `shipping_countries` (não consumido e sem esquema após retirar o contrib).
- Diagnóstico de Runtime no servidor de testes: 2 métodos ativos (`flat_rate` e Melhor Envio), ambos da loja 1; o pedido `default` em rascunho tem remessa com item `camiseta_tamanho`, perfil válido e nenhuma tarifa selecionada. O cálculo real retornou 4 tarifas (flat 0,00 e Melhor Envio 18,19/23,04/51,97), portanto a mensagem “no shipping rates” **não se reproduz** no estado atual. Causa original não comprovada; fatores registrados em `docs/COMMERCE-SHIPPING-PORT.md`.
- Pendências: conciliar com a linha atual do site (plugin Melhor Envio, Config Sync mais novo, `FreshShippingRateWidget` não versionado), remover `drupal/commerce_shipping` do Composer na integração, instalar e validar em banco descartável, e homologar checkout com Firefox ESR.

## Dev — preparação COM-SHIP (branch isolada, 2026-10-26)

- Diretriz do responsável: substituir Commerce Shipping 3.0.5 por código próprio **dentro de `aculta_commerce`**, sem editar contrib ou criar submódulo de Shipping; legado de pedidos/remessas/métodos pode ser descartado apenas em ambiente de desenvolvimento/teste descartável com inventário e backup.
- Criados `ShippingQuote` e `ShippingQuoteSelector` (valores como strings decimais, sem float, sem tarifa fictícia, recusa de quotes expiradas, moedas divergentes e duplicatas); testes Unit preparados, ainda não executados no Homelab.
- Adicionados diagnósticos somente leitura de Config Sync e Runtime e runbook de reset limpo.
- O porte do upstream, alteração de entidades/checkout, alteração de Config Sync, exclusão de dados e testes integrados ainda **não foram executados**; continuar Dev.

## 0.1.0-dev.9 — respeitar AccessResult ao criar produto no Green Stash

- O formulário avaliava os objetos `AccessResultInterface` como booleanos. Como esses objetos são truthy mesmo quando negados, os checks de acesso à experiência Studio e `commerce_product.createAccess()` não interrompiam a criação. Agora ambos exigem `isAllowed()`.
- `GreenStashProductCreateAccessTest` cobre os dois caminhos negados e garante que a negação pare antes de consultar a loja. Unit Commerce: 31 testes / 192 assertions PASS (11 depreciações PHPUnit); PHPCS Drupal/DrupalPractice e PHP lint dos arquivos alterados PASS. Sem operação no Runtime.

## 0.1.0-dev.6 — fechar testes de acesso do Green Stash (2026-10-26)

- `GreenStashProductAccessTest` agora cobre o caso permitido com `ProductInterface`/`StoreInterface` reais do Commerce e bloqueia produto ligado a outra loja, store sem permissão `update` ou configuração ambígua. Entity Access view/update e `store.access('update')` permanecem obrigatórios.
- Unit Commerce: 25 testes / 160 assertions PASS (9 depreciações PHPUnit). PHPCS Drupal/DrupalPractice e PHP lint do teste PASS. Nenhum dado, configuração ativa ou fluxo financeiro foi alterado; Functional/Browser e homologação Runtime continuam pendentes.

## 0.1.0-dev.5 — regressão focal da fundação comercial (2026-10-26)

- Unit Commerce no HEAD da PR: 21 testes / 125 assertions PASS. Nenhum checkout, pagamento ou serviço externo executado.
- Kernel `DonationPortKernelTest` permanece falhando no setup do harness: a cadeia Messenger → Portal → Image e contrib requer serviços/módulos adicionais; 0 assertions funcionais. Não foi criado serviço fictício nem aplicada alteração de produto.

## 0.1.0-dev.4 — publicar evento de pedido no Messenger (2026-10-24)

- `aculta_commerce` publica `store.order_placed`; o consumer e a entrega
  centralizados residem em `aculta_messenger`. O recibo nativo permanece para
  convidados e fallback.
- Unit e Kernel MariaDB integrados passaram; Runtime não homologado.

# Changelog — aculta_commerce

## 0.1.0-dev.8 — confirmar continuação do catálogo (2026-10-26)

- Ao atingir o orçamento de varredura, o catálogo faz uma consulta limitada a
  um ID para confirmar se há continuação. Isso mantém o cursor quando produtos
  mais antigos ainda existem e evita um próximo link vazio quando os resultados
  terminam exatamente no limite.
- Unit Commerce: 29 testes / 183 assertions PASS (10 depreciações PHPUnit).
  PHPCS Drupal/DrupalPractice, PHP lint e `git diff --check` PASS nos arquivos
  alterados. Nenhum Runtime ou dado persistente foi alterado.
- A regressão de paginação foi ajustada para testar 500 candidatos não
  gerenciáveis e um ID mais antigo realmente fora dos lotes. A classe do
  publisher agora também verifica pedido de visitante e fallback do recibo
  Commerce quando o Messenger falha.

## 0.1.0-dev.7 — preservar cursor após varredura seletiva (2026-10-26)

- O catálogo Green Stash agora emite um cursor de continuação quando o limite
  de cinco lotes é atingido após filtrar produtos que o operador não pode
  gerenciar. Produtos mais antigos não ficam ocultos por uma página final sem
  cursor; o acesso continua validado por Entity Access e pela loja.
- Regressão Unit: Commerce 26 testes / 168 assertions PASS (10 depreciações
  PHPUnit). PHPCS Drupal/DrupalPractice e PHP lint nos arquivos alterados PASS.
- Nenhum dado, configuração ativa, pedido ou serviço externo foi alterado.

## 0.1.0-dev.3 — alinhar fixture após conciliação do branch (2026-10-23)

- O teste de negação por Entity Access fornece o resolvedor de loja única
  exigido pelo construtor atual de `GreenStashProductReader`.
- Links da navegação expõem o token semântico `data-portal-ui`, removendo a
  classe visual rejeitada pelo gate de fronteira módulo/tema.
- A conciliação anterior preservou o provider de checkout e os serviços Green
  Stash no mesmo YAML. Nenhuma instalação ou operação de checkout foi executada.
- O submódulo Melhor Envio recebeu seu status documental obrigatório, sem
  alterar sua versão, capacidades ou dependências.

- Regressão Unit: Commerce 20 testes / 116 assertions (9 depreciações PHPUnit), Melhor Envio 29/78 e CEP 9/36 passaram.

## 0.1.0-dev.2 — provider de checkout da Loja habilitado (P-CART C) — 2026-10-22

- `ShopCheckoutProvider` declara ao Portal o contexto Loja (tipo de pedido configurado em `aculta_portal.checkout_settings`, padrão `default`, checkout comum e flow efetivo). Somente leitura.
- O módulo passa a constar em `config/sync/core.extension.yml` e foi habilitado no runtime de testes. Não cria configuração, esquema nem hooks. Rollback: `drush pm:uninstall aculta_commerce` e remover a linha do sync. RUNTIME STATUS: verificado por API no runtime local (diagnóstico lista a Loja, sem conflitos); navegador pendente.
