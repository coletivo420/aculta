# Matriz do porte — `commerce_shipping` 3.0.5 → `aculta_commerce`

> Estado em 2026-10-26: esta matriz registra a análise/atribuição do porte concluído. O provider Melhor Envio já foi
> integrado em `aculta_commerce_melhorenvio`; veja os resultados e pendências atuais no [roadmap Commerce](ROADMAP.md)
> e no [roadmap do adaptador](../modules/aculta_commerce_melhorenvio/docs/ROADMAP.md).

**Fonte:** `web/modules/contrib/commerce_shipping/` da instalação fixada em
`composer.lock` (`drupal/commerce_shipping` 3.0.5). **Licença:** GPL-2.0-or-later.
Cada arquivo portado mantém um cabeçalho de atribuição com a adaptação realizada.
Nenhum arquivo em `web/modules/contrib/` foi modificado.

## Portado e adaptado

| Origem (3.0.5) | Destino em `aculta_commerce` | Adaptação |
| --- | --- | --- |
| `src/Entity/Shipment.php` | `src/Entity/Shipment.php` | Entidade `aculta_shipment`, tabela própria, itens em JSON, sem translations/revisões |
| `src/Entity/ShipmentType.php` | `src/Entity/ShipmentType.php` | Entidade `aculta_shipment_type`, sem formulários administrativos |
| `src/Entity/ShippingMethod.php` | `src/Entity/ShippingMethod.php` | Entidade `aculta_shipping_method`, plugin type ACULTA, sem translation handler |
| `src/ShippingMethodStorage.php` | `src/ShippingMethodStorage.php` | Consulta sem access check (métodos são provedores internos), sem evento de filtro |
| `src/ShippingMethodManager.php` | `src/ShippingMethodManager.php` | Plugin type `aculta_shipping_method` |
| `src/Attribute/CommerceShippingMethod.php` | `src/Attribute/AcultaShippingMethod.php` | Atributo e workflow padrão próprios |
| `src/Plugin/Commerce/ShippingMethod/ShippingMethodBase.php` | `src/Plugin/Commerce/ShippingMethod/ShippingMethodBase.php` | Package types via manager simples, sem workflow manager |
| `src/Plugin/Commerce/ShippingMethod/FlatRate.php` | `src/Plugin/Commerce/ShippingMethod/FlatRate.php` | ID `aculta_flat_rate`, falha fechada sem valor |
| `src/Plugin/Commerce/ShippingMethod/FlatRatePerItem.php` | `src/Plugin/Commerce/ShippingMethod/FlatRatePerItem.php` | ID `aculta_flat_rate_per_item` |
| `src/ShippingService.php`, `src/ShippingRate.php` | `src/Shipping/ShippingService.php`, `src/Shipping/ShippingRate.php` | Namespaces ACULTA; valores em `Price` |
| `src/ShipmentItem.php`, `src/ProposedShipment.php` | `src/Shipping/ShipmentItem.php`, `src/Shipping/ProposedShipment.php` | Namespaces ACULTA |
| `src/ShipmentManager.php` | `src/Shipping/ShipmentManager.php` | Log sem mensagem do provedor (PII) |
| `src/ShippingOrderManager.php` | `src/Shipping/ShippingOrderManager.php` | Entidade própria |
| `src/PackerManager.php`, `src/Packer/DefaultPacker.php` | `src/Shipping/PackerManager.php`, `src/Shipping/DefaultPacker.php` | Entidade própria; shipment type em `aculta_commerce` |
| `src/EarlyOrderProcessor.php`, `src/LateOrderProcessor.php` | `src/Shipping/EarlyOrderProcessor.php`, `src/Shipping/LateOrderProcessor.php` | Mantém tarifa selecionada; ajuste `shipping` próprio |
| `src/Plugin/Field/FieldType/ShipmentItem.php` | `src/Plugin/Field/FieldType/ShipmentItem.php` | Armazenamento JSON em coluna de texto, sem typed data plugin |
| `src/Plugin/Commerce/EntityTrait/PurchasableEntityShippable.php` | `src/Plugin/Commerce/EntityTrait/PurchasableEntityShippable.php` | ID `aculta_purchasable_entity_shippable`; campo `weight` preservado |
| `src/Plugin/Commerce/EntityTrait/PurchasableEntityDimensions.php` | `src/Plugin/Commerce/EntityTrait/PurchasableEntityDimensions.php` | ID `aculta_purchasable_entity_dimensions` |
| `src/EventSubscriber/ReferenceablePluginTypesSubscriber.php` | `src/EventSubscriber/ShippingPluginTypesSubscriber.php` | Registra `aculta_shipping_method` |
| `src/EventSubscriber/ProfileSubscriber.php` | `src/EventSubscriber/ShippingProfileSubscriber.php` | Expõe o perfil da remessa ao pedido |
| `src/Plugin/Commerce/CheckoutPane/ShippingInformation.php` | `src/Plugin/Commerce/CheckoutPane/AcultaShippingInformation.php` | Pane próprio, endereço único do Portal, recálculo/revalidação no servidor |
| `commerce_shipping.workflows.yml` | `aculta_commerce.workflows.yml` | Workflow `aculta_shipment_default` |
| `commerce_shipping.workflow_groups.yml` | `aculta_commerce.workflow_groups.yml` | Grupo `aculta_shipment` |
| `config/install/commerce_shipping.commerce_shipment_type.default.yml` | `config/install/aculta_commerce.aculta_shipment_type.default.yml` | Tipo `default` |
| `config/install/field.storage.commerce_order.shipments.yml` | `config/install/field.storage.commerce_order.shipments.yml` | Target `aculta_shipment` |
| `commerce_shipping.permissions.yml` | `aculta_commerce.permissions.yml` | Permissões ACULTA |
| `commerce_shipping.commerce_adjustment_types.yml` | `aculta_commerce.commerce_adjustment_types.yml` | Tipo `shipping` |
| `commerce_shipping.schema.yml` (parte) | `config/schema/aculta_commerce.schema.yml` | Config do tipo de remessa, plugin e pane |

## Substituído por código próprio mais simples

| Origem | Decisão |
| --- | --- |
| `PackageType`, `PackageTypeManager`, `Plugin/Commerce/PackageType/*`, `PackageTypeDeriver` | `Shipping/PackageType` + `PackageTypeManager` com `custom_box`/`letter` |
| `Plugin/Field/FieldWidget/ShippingRateWidget`, `ShippingProfileWidget`, `ShipmentItemDefaultWidget` | Radios no pane próprio; sem widget de campo |
| `Plugin/Field/FieldFormatter/*` | Sem formatação administrativa nesta fase |
| `src/Form/*`, `src/Controller/ShipmentController.php`, rotas administrativas | ACULTA STUDIO GREEN STASH `/studio/green-stash/envios` (leitura) |
| Entity displays, form modes, views | Removidos do Config Sync; a operação fica no Studio |
| `OrderShipmentSummary`, `ShippingMethodListBuilder`, `ShipmentListBuilder`, `ShipmentPermissionProvider`, `ShipmentRouteProvider`, `ShipmentViewsData` | Não necessários para o checkout/Green Stash inicial |

## Não portado nesta entrega (documentado)

- `Plugin/Commerce/PromotionOffer/*` (promoções de frete).
- `Plugin/Commerce/TaxType/Shipping.php` (imposto de envio).
- `Mail/ShipmentConfirmationMail`, `Mail/ShipmentConfirmationMailInterface`.
- `Normalizer/ShipmentItemNormalizer`.
- Condições específicas de remessa (`ShipmentAddress`, `ShipmentQuantity`, `ShipmentWeight`, `OrderShippingMethod`) e `FilterConditionsEventSubscriber`.
- `Access/ShipmentCollectionAccessCheck`, `FieldAccess`, `ProfileFieldCopy`.
- Formulários/modais administrativos, envio de e-mail, etiquetas e rastreamento externo.
- Migração de dados históricos (decisão do responsável: não migrar).

Esses itens não são referenciados pelo Config Sync nem pelo checkout atual.
Quando forem necessários, devem ser portados para o mesmo motor, sem novo
submódulo e sem editar o contrib.

## Integração externa

- **Melhor Envio:** provider integrado ao motor ACULTA e testes Unit/Kernel aprovados na PR; cotação externa e checkout ainda não homologados. Contrato em [`MELHOR-ENVIO-CONTRACT.md`](MELHOR-ENVIO-CONTRACT.md).
- **Mercado Pago:** inalterado; continua gateway transversal.
- **LMS/Doações:** tipos de pedido e variações sem o trait de envio; validado que não recebem remessa nem ajuste de frete.
