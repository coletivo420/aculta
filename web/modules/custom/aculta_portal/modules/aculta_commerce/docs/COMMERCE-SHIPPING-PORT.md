# COM-SHIP — Porte do Commerce Shipping para `aculta_commerce`

**Estado:** Dev / migração não iniciada em Runtime. **Decisão:** funcionalidades de Shipping próprias em `aculta_commerce`, sem novo submódulo; jamais editar `web/modules/contrib/`.

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

### COM-SHIP-3 — checkout próprio da Loja

Mover o ownership funcional do pane de envio de `aculta_portal` para `aculta_commerce` por alteração coordenada. Integrar com endereço único do Portal, número/S-N/bairro, Profile e preço `Price` (BRL). Preservar `CartConsolidator`, Loja única, fluxos distintos de LMS e Doações, Mercado Pago e cabeçalho multidomínio.

O checkout **não** pode concluir uma remessa tarifada sem serviço válido e autorizado, nem apresentar tarifa inventada para suprimir a mensagem.

### COM-SHIP-4 — transportar dados/configuração e desativar contrib

Projetar a migração real antes de executar: `commerce_shipment` e IDs/revisões, references em pedidos, `commerce_shipping_method`, plugin IDs, field storages, configs/shipping flow/workflow, Views, access, testes de reentrância, `hook_update_N` e rollback testado. Definir decisão sobre manter IDs físicos/dados/entidades versus cópia com reindexação; não escolher sem caracterização de dependências e banco.

**Bloqueio:** não desinstalar `commerce_shipping` no Runtime até migração não destrutiva, com prova dos pedidos e remessas históricos. Desinstalação pode remover dados/configurações. Config Sync global e Composer são integrados pelo agente principal com testes e autorização.

### COM-SHIP-5 — shipping real e Green Stash

No core `aculta_commerce` oferecer cálculo de frete, métodos elegíveis, escolha, persistência e operação de remessas. Reaproveitar `aculta_commerce_melhorenvio` para cliente externo. Falhas de API não viram frete zero. Retirada local/frete fixo apenas se configurados e autorizados; não adotar fallback silencioso. Entrega/etiqueta/estorno externos permanecem bloqueados sem autorização.

### COM-SHIP-T — gates

Unit, Kernel MariaDB, Functional, Browser Firefox ESR visível, PHPCS Drupal/DrupalPractice, lint, gates próprios, Admin Domain, propósito Shop, Entity Access e aprovação visual SDC. Incluir pedidos físicos/mistos, múltiplas variações, checkout agregado e sem frete (LMS/Doações), revisão de cálculo por CEP/itens, atualização/removal de método, logs, erros da API, idempotência e concorrência.

Testar instalação limpa, atualização de instalação já populada e rollback em clones **isolados**. Registrar evidências exatas. Só recomendar RC após fechar todo o ciclo.

## Próxima ação obrigatória do executor local

**Não optar pela edição temporária no contrib.** Recuperar a fonte 3.0.5 realmente instalada e executar COM-SHIP-0 e COM-SHIP-1 primeiro; iniciar portes COM-SHIP-2 sem colisões. Trabalhar em worktree isolado da PR #298, respeitar alterações dos outros agentes, commits focados, README/CHANGELOG/status/roadmap atualizados, não abrir outra PR, não fazer merge/deploy nem gravar no Runtime sem autorização.

## Referências

- https://www.drupal.org/project/commerce_shipping/releases/3.0.5
- https://www.drupal.org/project/commerce_shipping
- `web/modules/custom/aculta_portal/modules/aculta_commerce/docs/SHIPPING-STOCK-BRAZIL-RESEARCH.md`
- `docs/portal/CHECKOUT-BILLING.md`
- `docs/decisions/ADR-013-portal-multi-cart-checkout.md`

**Estado de implantação:** não implementado. Este documento é contrato de execução e inventário preliminar, não substitui o porte real, testes ou correção confirmada do checkout.
