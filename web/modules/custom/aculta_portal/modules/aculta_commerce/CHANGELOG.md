## Retificação de caminho ACULTA STUDIO GREEN STASH — 2026-10-30

- O caminho canônico confirmado pelo responsável é `/studio/green-stash` no purpose `shop`; rotas de produto e remessa usam o mesmo prefixo.
- `/greenstash` não fica como alias ou redirect. A atualização de rota ainda aguarda homologação no Runtime.

## ACULTA STUDIO GREEN STASH — nome e caminho canônicos (2026-10-30)

- Nome oficial: **ACULTA STUDIO GREEN STASH**, área comercial integrada ao ACULTA Studio.
- Entrada movida para `/studio/green-stash`; produtos, envios e ações de remessa usam esse prefixo. IDs de rota e contratos internos foram preservados.
- `/estudio/loja` e seus caminhos filhos foram removidos sem alias ou redirect; devem responder 404 conforme a política global de URLs.
- A etapa só entra no Runtime após os gates de rotas, acesso, funcionalidade e regressão por purpose.

## 0.1.0-dev.15 — Não validado — simulador de frete no submódulo Melhor Envio — 2026-10-27

- Submódulo `aculta_commerce_melhorenvio` 0.1.0-dev.6: simulador de frete por CEP com estados explícitos, carrinho guardado na sessão e recálculo ao mudar o carrinho (ver `modules/aculta_commerce_melhorenvio/CHANGELOG.md`).
- Nenhuma mudança de schema, de configuração do motor ou de atualização de banco nesta versão.
- Não validado no navegador nem com cotação real. RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.11 — cutover do frete para o motor próprio (código, sem Runtime) — 2026-10-26

- Post-update `remove_orphan_shipment_displays`: remove displays de formulário e visualização de `commerce_shipment` que usam plugin inexistente (ex.: widget `aculta_shipping_rate`), antes da desinstalação do `commerce_shipping`. Lista só IDs de configuração.
- Melhor Envio legado (`aculta_melhorenvio`) migra para o mesmo id no motor, com `default_package_type` e `services`.
- `aculta_commerce.install`: `update_8001` instala as entidades do motor (`aculta_shipping_method`, `aculta_shipment`, `aculta_shipment_type`) de forma idempotente.
- Post-update `migrate_shipping_methods`: copia `commerce_shipping_method` com plugin `flat_rate` para `aculta_flat_rate` (e `flat_rate_per_item` para `aculta_flat_rate_per_item`), preservando `rate_label`, `rate_description`, `rate_amount`, lojas, status, peso e condições. O original não é apagado. `services` do contrib não tem equivalente e não é copiado.
- Post-update `retarget_shipments`: remove do pedido só a referência a remessas do contrib, reaponta `commerce_order.shipments` para `aculta_shipment` e apaga remessas legadas. Pedidos não são apagados.
- A desinstalação do `commerce_shipping` **não é post-update**. É passo explícito da janela de update: `blockersForUninstall()` vazio, e então `drush pm:uninstall commerce_shipping` (ver RUNTIME-UPDATE-WINDOW.md). Motivo: é destrutiva e precisa de conferência humana.
- Serviço `aculta_commerce.legacy_shipping_cutover` (`src/Shipping/Cutover/LegacyShippingCutover.php`) concentra a lógica, com DI.
- Kernel `ShippingCutoverKernelTest` (4 testes / 112 asserções PASS): migração FlatRate com valores preservados, idempotência, pedidos preservados e bloqueio do uninstall antes da cópia e liberação depois do reapontamento.
- Não provado no Kernel: a desinstalação do contrib e o reapontamento de remessas com campo instalado. Ficam para o ensaio no banco restaurado do snapshot.
- PHPCS: 0 erros; restam avisos de comprimento de linha nos arquivos novos.
- Não provado no Kernel: reapontamento com o campo instalado e com dados. O harness não comporta salvar pedido com referência a remessa legada (falha de transação dentro do `save()` do pedido). A prova fica para o banco restaurado do snapshot (Local 3).

## 0.1.0-dev.10 — motor de envios próprio no `aculta_commerce` (2026-10-26)

- Implementado o núcleo de Shipping dentro do `aculta_commerce`, sem novo módulo e sem editar `contrib`: entidades `aculta_shipment`, `aculta_shipping_method` e `aculta_shipment_type`; campo `shipments` do pedido apontando para `aculta_shipment`; trait `aculta_purchasable_entity_shippable` (campo `weight`); workflow `aculta_shipment_default`; métodos com ID legado `shipment_default` recorrem ao workflow próprio quando o legado não está disponível.
- Plugin manager `plugin.manager.aculta_shipping_method`, atributo `AcultaShippingMethod`, base `ShippingMethodBase` e plugins `aculta_flat_rate` e `aculta_flat_rate_per_item`. Método sem valor configurado falha fechado (nunca retorna tarifa zero). Configurações legadas com workflow `shipment_default` caem para `aculta_shipment_default` quando o workflow não existe.
- Serviços próprios: `shipment_manager`, `shipping_order_manager`, `packer_manager`, `DefaultPacker`, `package_type_manager`, processors de pedido (`EarlyOrderProcessor`/`LateOrderProcessor`) que transferem a tarifa selecionada para o total do pedido.
- Checkout pane próprio `aculta_shipping_information` em `aculta_commerce`, substituindo o pane do Portal que herdava de `commerce_shipping`. Mantém o endereço único (cópia do perfil de cobrança do `BillingAddressPane` ou o perfil de cobrança do pedido), recalcula tarifas a cada montagem, invalida a tarifa quando o endereço muda e revalida a escolha no servidor antes de aplicar.
- Config Sync: removido `commerce_shipping` de `core.extension`; flows `default` e `shipping` usam o pane próprio; tipo de pedido `default` declara `third_party_settings.aculta_commerce.shipment_type`; variação física usa o trait ACULTA; `field.storage.commerce_order.shipments` aponta para `aculta_shipment`; removidas as configs de exibição/view do contrib e o view `order_shipments`; papel anônimo sem `view commerce_shipping_method`.
- Mantida uma área de leitura em `/estudio/loja/envios` no Green Stash, com estado, serviço, tarifa e rastreamento, sem links para formulários nativos. A mesma área oferece as transições válidas do workflow (`finalizar`, `enviar`, `cancelar`) por formulário de confirmação com CSRF; a rota exige `access aculta green stash` **e** `administer aculta shipments`.
- Unit: 55 testes / 272 asserções PASS no `aculta_commerce` (17 depreciações PHPUnit), incluindo seletor de quotes, `FlatRatePluginTest`, `ShippingRateTest`, `PackageTypeManagerTest` e `ShippingConfigSyncTest`. **Kernel:** `ShippingEngineKernelTest` passou com 7 testes / 237 asserções, instalando o fechamento completo de 104 módulos exigido pelos serviços do Portal, com schema estrito, transições draft→ready→shipped, provider de teste com falha fechada, leitor do Green Stash com controle de permissão e o pane de checkout aceitando a tarifa válida, rejeitando uma tarifa adulterada e persistindo o valor correto. 182 depreciações vieram de Core/contrib. PHP lint e PHPCS Drupal/DrupalPractice PASS nos arquivos novos.
- **Composer e instalação limpa reexecutada nesta PR:** `drupal/commerce_shipping` ausente de `composer.json`/`composer.lock`; `drupal/physical` exigido explicitamente. Um site Standard descartável habilitou `aculta_commerce` e `aculta_commerce_melhorenvio` sem o contrib; o plugin Melhor Envio foi descoberto. O Runtime compartilhado continua legado e não foi alterado.
- **Rehearsal anterior reportado pelo worktree de porte, não reexecutado nesta rodada:** pedido físico com 0,6 kg recebeu tarifa própria de 19,90 BRL e pedido digital não recebeu remessa nem ajuste; o rehearsal também registrou a ordem necessária para repontar o field storage antes de desinstalar o contrib. Nesta rodada, a instalação limpa própria foi reexecutada em `aculta_simpletest` com prefixo `shipport_`; 299 tabelas do prefixo foram removidas via Drupal Schema API ao final, e o banco ficou sem tabelas.
- Correções descobertas na instalação limpa: subscriber de `ReferenceablePluginTypesEvent` para o tipo `commerce_plugin_item:aculta_shipping_method`; subscriber de `commerce_order.profiles` para expor o perfil da remessa; tipo de ajuste `shipping` em `aculta_commerce.commerce_adjustment_types.yml`; `CurrencyFormatterInterface` do pacote `commerceguys/intl` no pane; removido o campo base `shipping_countries` (não consumido e sem esquema após retirar o contrib).
- Diagnóstico de Runtime no servidor de testes: 2 métodos ativos (`flat_rate` e Melhor Envio), ambos da loja 1; o pedido `default` em rascunho tem remessa com item `camiseta_tamanho`, perfil válido e nenhuma tarifa selecionada. O cálculo real retornou 4 tarifas (flat 0,00 e Melhor Envio 18,19/23,04/51,97), portanto a mensagem “no shipping rates” **não se reproduz** no estado atual. Causa original não comprovada; fatores registrados em `docs/COMMERCE-SHIPPING-PORT.md`.
- Pendências: cutover no Runtime compartilhado (não executado nesta rodada), validar checkout em Firefox ESR e homologar a experiência após aplicação autorizada. Kernel Melhor Envio reexecutado na PR: 11 testes / 57 assertions PASS; Unit Melhor Envio: 85 / 204 PASS. Nenhuma decisão RC foi tomada.

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
## 0.1.0-dev.13 — identidade no checkout e compra sem CAPTCHA (2026-10-27)

- O pane Login do Commerce foi especializado sem substituir sua autenticação: Google aparece antes do e-mail; a consulta AJAX revela senha para conta existente ou cadastro de visitante para e-mail novo. O login continua usando autenticação, bloqueio e flood control do Core; consulta de e-mail tem limite por IP. Quando a configuração Sync for aplicada, o Core criará a conta ao concluir e enviará link para senha.
- Carrinho, adição de itens, produtos e checkout compartilhados por Loja, LMS e Doações ficam sem desafio CAPTCHA/Turnstile. As páginas gerais de login e cadastro preservam a política global. CAPTCHA continua como proteção dos demais formulários; os fluxos de compra usam flood control e validação do pedido.
- Unit: 9 testes / 59 assertions PASS, cobrindo flows, pane e exceção CAPTCHA; PHP lint e PHPCS sem erros. Firefox ESR no Runtime comprovou: produto e carrinho sem CAPTCHA, checkout com Google antes do e-mail, AJAX revela cadastro para novo e-mail, sem senha ou CAPTCHA. A etapa de conta existente, envio do pedido e e-mail de senha ainda não foi homologada.

## 0.1.0-dev.12 — conta de visitante pelo Commerce Core (2026-10-27)

- Flows padrão, frete, doação avulsa e doação recorrente permitem checkout de visitante e habilitam a criação silenciosa de conta nativa do Commerce ao concluir. O aviso do Core entrega link de configuração de senha.
- Desabilitado o registro inline no pane de login; `guest_order_assign` continua falso para impedir associação de pedido por simples coincidência de e-mail. O Core continua dono da criação de conta e entidades Commerce; nenhum módulo externo ou cópia do código foi introduzido.
- Testes Unit de contrato validam flows e associações de pedido. A ativação dessas configurações no Runtime e homologação do e-mail permanecem pendentes.

## 0.1.0-dev.14 — exceção CAPTCHA em todo o fluxo de compra (2026-10-27)

- IDs dinâmicos de adicionar ao carrinho e todos os formulários/etapas `commerce_checkout_flow_*` removem CAPTCHA inclusive quando o fallback global está ativo. O painel Turnstile mantém desligados pontos dinâmicos de compra ao alternar o desafio.
- A continuação de visitante com e-mail novo é sempre renderizada conforme o contrato de compra anônima, mesmo quando a configuração ativa antiga está divergente; autenticação de contas existentes continua no Commerce Core.
- Firefox ESR confirmou ausência de CAPTCHA no produto, carrinho, AJAX de cadastro e pane de endereço/frete. O teste parou antes de enviar endereço; não solicitou cotação externa nem concluiu o pedido. Unit 6/62 e Kernel `TurnstileToggleKernelTest` 3/11 PASS; depreciações do contrib CAPTCHA/Key permanecem.

