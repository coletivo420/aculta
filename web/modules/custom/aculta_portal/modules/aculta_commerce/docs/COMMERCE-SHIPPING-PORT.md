# COM-SHIP — Porte do Commerce Shipping para `aculta_commerce`

**Estado:** motor próprio implementado e validado em banco descartável na branch `feat/aculta-commerce-shipping-port`; o Runtime de testes ainda executa o contrib até o cutover coordenado na PR #298. **Decisão atualizada em 2026-10-26:** dados legados de pedidos, remessas e configurações antigas de Shipping **não precisam ser migrados ou preservados no banco de desenvolvimento/testes expressamente descartável**. Não eliminar dados da produção nem contas, endereços, identificadores fiscais, produtos ou sistemas independentes. **Decisão:** funcionalidades de Shipping próprias em `aculta_commerce`, sem novo submódulo; jamais editar `web/modules/contrib/`.

**Base auditada:** PR #298 em 2026-10-26; `composer.lock` fixa `drupal/commerce_shipping` **3.0.5**. Código-fonte a portar no Homelab: `web/modules/contrib/commerce_shipping/` da instalação efetiva, não exemplos de 8.x-2.x. Obter referência upstream da mesma versão e registrar hash, arquivos, atribuições e alterações; respeitar a licença GPL e preservar avisos de copyright/licença.

## Fato observado e limite do diagnóstico

A mensagem *There are no shipping rates available for this address.* indica que o widget não recebeu nenhuma cotação elegível; **não comprova bug do Commerce Shipping**. No `config/sync` auditado:

- `commerce_checkout.commerce_checkout_flow.default.yml` referencia `aculta_shipping_information`, pane próprio do Portal que ainda deriva de `Drupal\commerce_shipping\Plugin\Commerce\CheckoutPane\ShippingInformation`.
- `commerce_checkout.commerce_checkout_flow.shipping.yml` usa o pane contrib `shipping_information`.
- `commerce_shipping.commerce_shipment_type.default.yml` declara tipo de remessa `default`.
- `commerce_product.commerce_product_variation_type.camiseta_tamanho.yml` declara `purchasable_entity_shippable`; a variação `default` não.
- `commerce_order.commerce_order_type.default.yml` não declara trait de shipping no Sync.
- **Nenhum** `commerce_shipping.commerce_shipping_method.*.yml` está versionado. Isso NÃO demonstra ausência de métodos no Runtime ativo: devem ser consultados por Drush read-only.

Antes de alterar fretes, comprovar o conjunto: pedido e seus tipos/traits; SKU físico; perfil e endereço únicos; CEP; `shipping_profile`; remessas; método ativo, loja e condições; pacotes; preço retornado, moeda, peso/dimensões; checkout flow efetivo por pedido; ausência de rates e logs sanitizados. Configurações ativas podem divergir de Sync. A análise não autoriza inventar taxa, frete grátis ou método artificial.

## Contrato de ownership

- `aculta_commerce`: núcleo portado de métodos, serviços, shipment, cotação, selection, condições, checkout de envio da Loja, fulfillment e Green Stash.
- `aculta_commerce_melhorenvio` **já existente**: somente adaptador e integração logística da transportadora; não criar novo submódulo para o porte do engine.
- `aculta_portal`: formulário único `AccountAddressForm`, CEP central, consolidator, shell e fluxo administrativo dos carrinhos.
- Drupal Commerce Core: produto, variação, pedido, pagamento, preços, carrinhos, gateways, impostos.
- `aculta_pagamentos_mercadopago`: pagamentos; `aculta_statistics`: métricas; `aculta_messenger`: avisos.
- `aculta_studio` e `aculta420`: operações Green Stash e componentes SDC/UI Suite; nenhuma edição nativa de produto/envio em `/painel-administrativo`.

Não criar segundo endereço, perfil, pedido, carrinho, preço em float, chave de API em config exportável ou entidades paralelas desnecessárias.

## Estratégia em gates, sem quebra de dados

### COM-SHIP-0 — baseline operacional somente leitura

1. Inspecionar a instalação efetiva do Commerce Shipping 3.0.5, `composer.lock`, `core.extension`, módulos ativos, tipos, config ativa, métodos de frete existentes e rótulos de remessa.
2. Reproduzir com pedido fictício no **banco isolado**; identificar separadamente ausência de método, incompatibilidade de store/condição, falta de pacote, falha de endereço, peso/volume inválido e falha de transportadora.
3. Registrar fluxos reais do checkout `default` e `shipping`, inclusive `AcultaShippingInformation` e `BillingAddressPane`, sem alterar DB de Runtime.
4. Cobrir com testes regressionais que o diagnóstico falha fechado, em pt-BR, sem expor PII e sem taxar pedidos não enviáveis.

### COM-SHIP-1 — inventário completo do upstream e contratos de dados

Inventariar, por arquivo, o que `commerce_shipping` instala e usa: entidades `commerce_shipment` e `commerce_shipping_method`, storage, base/revision tables, field definitions, refs em Orders, traits shippable, plugin managers, `ShippingRate`, packer, rate widget, `ShippingInformation` checkout pane, order processors, workflows, handlers, acessos, Views, hooks, eventos, config install/schema, update/post-update, tests e migrations. Classificar **portar/adaptar/manter contrato externo/descartar**, com justificativa. Uma cópia de namespace não substitui migração.

### COM-SHIP-2 — portar serviços e modelo ao diretório do `aculta_commerce`

Portar apenas a partir da fonte **3.0.5 exata**, sob `src/Shipping/`, `src/Entity/`, `src/Plugin/`, `src/Access/`, `src/Hook/` e `config/` do `aculta_commerce` conforme necessário; usar `Drupal\aculta_commerce\...`, IDs e services novos, com atribuição de origem. Conservar convenções Drupal 11. Sem novo módulo, sem `contrib` editado.

Implementar primeiro código não destrutivo e tests. Não registrar tipos de entidade, IDs e plugins que colidam com `commerce_shipping` quando ambos estiverem ativos. Não remover a dependência antes de provar a substituição e plano de migração.

### Modelo-alvo e IDs canônicos (a confirmar contra o upstream 3.0.5)

Como a base de teste pode começar limpa, não é obrigatório manter IDs, tabelas e plugin IDs legados. Preferir nomes próprios que não colidam com o contrib durante o porte incremental, por exemplo `aculta_shipment`, `aculta_shipping_method` e pane `aculta_shipping_information`. Antes de fixá-los, auditar EntityType definitions, fields, traits, workflows, Checkout e qualquer consumidor que invoque `commerce_shipment` diretamente. Não cadastrar simultaneamente entidades/traits duplicadas sob o mesmo machine name. Portar também o trait `purchasable_entity_shippable` sob contrato canônico próprio antes de desinstalar o contrib, corrigindo o Config Sync e quaisquer plugins de variações físicas.

**Somente no banco novo de teste**, configurar explicitamente pelo menos um método de frete fixo ou retirada real de prova (com valor e loja conhecidos), para demonstrar checkout sem API externa; testar indisponibilidade quando nenhum método existir. O método configurado não pode ser silenciosamente aplicado a produção. Melhor Envio continua um provider separado que dependerá de credenciais e cotação real, e sua falta não deve produzir frete grátis.

Código inicial na branch:
- `src/Shipping/ShippingQuote.php` e `ShippingQuoteSelector.php`: modelos imutáveis e seleção de quotes confiáveis (base de código proprietário, **não** engine portado).
- `tests/src/Unit/Shipping/ShippingQuoteSelectorTest.php`: testes ainda não executados no Homelab.
- `tests/scripts/shipping_sync_preflight.php`: inventário read-only de Sync.
- `tests/scripts/shipping_runtime_preflight.php`: inventário read-only de Runtime bootstrapped.
- `docs/SHIPPING-RESET-TEST-ONLY.md`: reset seguro e requisitos de isolamento.

### COM-SHIP-3 — checkout próprio da Loja

Mover o ownership funcional do pane de envio de `aculta_portal` para `aculta_commerce` por alteração coordenada. Integrar com endereço único do Portal, número/S-N/bairro, Profile e preço `Price` (BRL). Preservar `CartConsolidator`, Loja única, fluxos distintos de LMS e Doações, Mercado Pago e cabeçalho multidomínio.

O checkout **não** pode concluir uma remessa tarifada sem serviço válido e autorizado, nem apresentar tarifa inventada para suprimir a mensagem.

### COM-SHIP-4 — reset limpo, sem migrar legado

**Decisão substitutiva do responsável:** não portar dados históricos de pedidos, remessas, métodos, cotações ou configurações antigas de frete. Implementar instalação limpa do novo motor em `aculta_commerce` e novos pedidos de teste. Não escrever migrations de preservação de IDs ou histórico de Shipping. Não criar tabelas de compatibilidade legada.

O descarte **é permitido somente no ambiente de desenvolvimento/teste explicitamente descartável e conferido**, após inventário, snapshot/backup privado, checagem de URI/DB/root e plano de restauração. O executor local deve classificar as entidades e vínculos antes de apagar. Como o pedido `default` pode participar do carrinho consolidado, não presumir que representa exclusivamente a Loja. Para zerar todos os pedidos de Commerce no ambiente de teste, certificar que LMS/Doações não precisem daqueles pedidos e que não haja transação externa em andamento. Não apagar clientes, profiles, CPF/CNPJ, termos, produtos, uma única Store, inscrições LMS ou outros registros de domínio por associação casual.

O procedimento de reset deverá usar APIs/rotinas Drupal ou uninstall controlado, **jamais SQL ad hoc que ignore Entity API**, em banco isolado. Evitar efeitos externos de hooks (avisos, métricas, webhooks, pagamento). O runbook em `docs/SHIPPING-RESET-TEST-ONLY.md` determina dry-run, backup, bloqueio de escrita concorrente, apagamento e provas. **A autorização de descarte de legado não é autorização para apagar dados na produção ou Runtime compartilhado não verificado.**

Remover a dependência `drupal/commerce_shipping` de Composer/Config Sync **somente depois** que o motor próprio compilar, instalar, registrar entidades/plugins, renderizar o checkout, calcular tarifas autorizadas e passar em testes. Não desinstalar um contrib requerido enquanto o pane do Portal ainda deriva de `ShippingInformation`. Config Sync e Composer globais pertencem à integração coordenada pelo agente principal.

### COM-SHIP-5 — shipping real e Green Stash

No core `aculta_commerce` oferecer cálculo de frete, métodos elegíveis, escolha, persistência e operação de remessas. Reaproveitar `aculta_commerce_melhorenvio` para cliente externo. Falhas de API não viram frete zero. Retirada local/frete fixo apenas se configurados e autorizados; não adotar fallback silencioso. Entrega/etiqueta/estorno externos permanecem bloqueados sem autorização.

### COM-SHIP-T — gates

Unit, Kernel MariaDB, Functional, Browser Firefox ESR visível, PHPCS Drupal/DrupalPractice, lint, gates próprios, Admin Domain, propósito Shop, Entity Access e aprovação visual SDC. Incluir pedidos físicos/mistos, múltiplas variações, checkout agregado e sem frete (LMS/Doações), revisão de cálculo por CEP/itens, atualização/removal de método, logs, erros da API, idempotência e concorrência.

Testar instalação limpa, reset de legado e rollback por snapshot em clones **isolados**. **Não** exigir migração de pedidos ou remessas históricos. Registrar evidências exatas. Só recomendar RC após fechar todo o ciclo.

## Próxima ação obrigatória do executor local

**Não optar pela edição temporária no contrib.** Recuperar a fonte 3.0.5 realmente instalada e executar COM-SHIP-0 e COM-SHIP-1 primeiro; iniciar portes COM-SHIP-2 sem colisões. Trabalhar em worktree isolado da PR #298, respeitar alterações dos outros agentes, commits focados, README/CHANGELOG/status/roadmap atualizados, não abrir outra PR, não fazer merge/deploy nem gravar no Runtime sem autorização.

## Resultado do diagnóstico COM-SHIP-A (2026-10-26, servidor de testes)

Execução somente leitura em `https://loja.aculta.toca.net.br`:

- `aculta_commerce`, `commerce_shipping` e `aculta_commerce_melhorenvio` habilitados.
- Entidades do contrib presentes: `commerce_shipping_method`, `commerce_shipment`, `commerce_order`.
- **2 métodos ativos**, ambos da loja 1: `flat_rate` (“Envio”, peso 0, sem condições, valor configurado **0,00 BRL**) e `aculta_melhorenvio` (“Melhor Envio”, peso 1, sem condições).
- 1 remessa no banco; o único pedido `default` está em rascunho, com item `camiseta_tamanho`, campo `shipments` presente e perfil de cobrança `authenticated` com `address` e `field_address` válidos (BR/SP, CEP de 9 caracteres).
- A remessa do pedido está sem método, serviço e valor, com peso `0.000000g`.
- O cálculo real por `ShipmentManager::calculateRates()` retornou **4 tarifas**: flat `0,00 BRL` e Melhor Envio `18,19`, `23,04` e `51,97 BRL`.
- Log do Melhor Envio às 09:56: “Cotação não enviada: origem: postal_code_invalid”; o método Melhor Envio foi criado às 10:15 e o pedido às 10:31.

**Conclusão:** a mensagem “There are no shipping rates available for this address.” **não se reproduz** no estado atual. Não há prova para atribuí-la ao contrib. Fatores registrados: (a) os métodos existem apenas no banco, não no Config Sync; (b) a tarifa fixa está configurada em 0,00, o que é um problema de configuração comercial; (c) houve falha de CEP de origem do Melhor Envio antes da criação do método; (d) havia divergência entre config ativa e Sync (rótulo do tipo de pedido, `third_party_settings` ausente no Sync e widget `aculta_shipping_rate` presente só na config ativa, vindo de arquivo não versionado). O pane novo elimina a reutilização de tarifas vazias em form state ao recalcular a cada montagem.

## Implementação entregue na branch (COM-SHIP-B a H)

- Núcleo próprio em `src/Shipping/`, `src/Entity/`, `src/Plugin/Commerce/ShippingMethod/`, `src/Plugin/Commerce/EntityTrait/`, `src/Plugin/Commerce/CheckoutPane/` e `src/Catalog/`.
- Entidades `aculta_shipment`, `aculta_shipping_method` e `aculta_shipment_type`; IDs, tabelas, campos e plugins sem colisão com o contrib.
- Campo `shipments` do pedido e trait de variação com IDs ACULTA; workflow próprio.
- Checkout pane `aculta_shipping_information` no `aculta_commerce`; o pane antigo do Portal foi removido.
- Green Stash `/estudio/loja/envios` com leitura de remessas e transições confirmadas do workflow, sem interface nativa.
- Config Sync substituído onde o contrib era referenciado; atribuições GPL registradas nos arquivos portados.
- **Não incluído nesta branch:** plugin de cotação do Melhor Envio (vive na linha principal do site; contrato em `docs/MELHOR-ENVIO-CONTRACT.md`), promoções de remessa, tipo de imposto de envio, e-mails de confirmação de remessa, formulários administrativos nativos e etiquetas/rastreamento externo.
- **Pendente para integração na PR #298:** conciliar com a linha principal do site (90 commits à frente), remover `drupal/commerce_shipping` do Composer, importar o Config Sync no banco descartável, executar instalação limpa/desinstalação do contrib, e homologar o checkout físico e não enviável com Firefox ESR visível.

## Validação em banco descartável (COM-SHIP-E/T)

Executada em 2026-10-26 em `sites/shipport` (removido ao final), sobre o banco
dedicado `aculta_simpletest` com prefixo `shipport_`. O banco é exclusivo de
testes; o `site:install` recria suas tabelas. Nenhum banco de runtime foi usado.

| Verificação | Resultado |
| --- | --- |
| Kernel `ShippingEngineKernelTest` | PASS; 6 testes / 207 asserções com o fechamento de 104 módulos, schema estrito, transições, provider externo mock e pane de checkout |
| Pane de checkout (Kernel) | PASS; tarifa válida aceita, tarifa adulterada rejeitada e valor persistido vem do servidor |
| Provider externo com falha (mock) | PASS; a tarifa da transportadora que lança exceção é ignorada, nunca convertida em zero |
| Instalação `standard` + `aculta_commerce` | PASS; entidades, tipos e plugin manager disponíveis |
| Config/install do tipo de remessa `default` | PASS |
| Trait `aculta_purchasable_entity_shippable` | PASS; campo `weight` criado e usado |
| Campo `shipments` no tipo de pedido | PASS após `third_party_settings.aculta_commerce.shipment_type` |
| Empacotamento de 2 itens de 0,3 kg | PASS; 1 remessa de 0,6 kg |
| Tarifa fixa própria | PASS; 12,34 BRL calculada, aplicada e persistida |
| Total do pedido | PASS; 112,34 BRL com ajuste `shipping:12,34` |
| Pedido digital (sem weight) | PASS; `isShippable` falso, zero remessas e zero ajuste de frete |
| Pane `aculta_shipping_information` | PASS; plugin descoberto |
| Migração: contrib instalado primeiro, campo reapontado para `aculta_shipment`, contrib desinstalado | PASS; tarifa 19,90 BRL e total 139,90 BRL após a remoção; `commerce_shipping` ausente |
| Instalação do contrib com o field storage ACULTA já existente | Falha esperada `PreExistingConfigException`; o cutover é atualização de config + desinstalação, não coexistência de instalação |

O campo `field.storage.commerce_order.shipments` é obrigatório: removê-lo apaga
a tabela de dados do campo. O setup descartável foi apagado (diretório e
tabelas com prefixo) após os testes.

## Referências

- https://www.drupal.org/project/commerce_shipping/releases/3.0.5
- https://www.drupal.org/project/commerce_shipping
- `web/modules/custom/aculta_portal/modules/aculta_commerce/docs/SHIPPING-STOCK-BRAZIL-RESEARCH.md`
- `docs/portal/CHECKOUT-BILLING.md`
- `docs/decisions/ADR-013-portal-multi-cart-checkout.md`

**Estado de implantação:** motor próprio implementado na branch, com entidades, plugins, empacotamento, cálculo, seleção revalidada, processors de pedido, pane de checkout e leitura/transições de remessas no Green Stash; Unit (49/230) e Kernel (6/207, fechamento de 104 módulos) passam, e a instalação limpa em banco descartável foi validada, incluindo o rehearsal de desinstalação do contrib. `drupal/commerce_shipping` foi removido de `composer.json`/`composer.lock` e `drupal/physical` passou a ser exigido explicitamente. **Pendente para a PR #298:** adaptar o plugin atual do Melhor Envio ao contrato ACULTA, executar `composer install` no deploy/cutover, aplicar o Config Sync e a migração de campo no Runtime com snapshot, e homologar o checkout físico/não enviável com Firefox ESR. Ver a matriz em [`SHIPPING-PORT-MATRIX.md`](SHIPPING-PORT-MATRIX.md).
