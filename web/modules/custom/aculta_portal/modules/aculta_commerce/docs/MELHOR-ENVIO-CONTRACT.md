# Contrato do provider Melhor Envio no motor próprio

**Estado:** contrato definido; o provider da linha atual do site ainda não foi
transportado para esta branch. A cotação externa **não está homologada**.

## Papel do submódulo

`aculta_commerce_melhorenvio` é somente o adaptador logístico. O motor de
remessas, métodos, tarifas, elegibilidade, seleção e persistência pertence ao
`aculta_commerce`. O provider não pode criar:

- segundo carrinho, pedido, produto ou pagamento;
- segundo formulário ou perfil de endereço;
- tarifa de fallback quando a API falhar;
- tabelas próprias de remessa.

## Implementação obrigatória na integração

O plugin do provider deve implementar o contrato ACULTA:

- atributo `#[AcultaShippingMethod(id: 'aculta_melhorenvio', ...)]`;
- interface `Drupal\aculta_commerce\Plugin\Commerce\ShippingMethod\ShippingMethodInterface`;
- base `Drupal\aculta_commerce\Plugin\Commerce\ShippingMethod\ShippingMethodBase`;
- `calculateRates(ShipmentInterface $shipment): array` retornando
  `Drupal\aculta_commerce\Shipping\ShippingRate[]`;
- serviços em `Drupal\aculta_commerce\Shipping\ShippingService`;
- workflow `aculta_shipment_default`;
- nenhuma referência a `Drupal\commerce_shipping\*`.

O endpoint de cotação recebe do shipment: perfil de envio, itens, peso,
dimensões conhecidas, valor declarado e moeda. Falha HTTP, credencial ausente,
CEP inválido ou resposta vazia resultam em **lista vazia** e log sanitizado; o
checkout mostra indisponibilidade e não permite concluir uma remessa tarifada.

## Estado nesta branch

O contrato foi comprovado com o provider de teste `aculta_test_mock_carrier`
(`tests/modules/aculta_commerce_shipping_test`): uma transportadora que lança
exceção é ignorada e não vira tarifa zero, e uma tarifa válida é mesclada às
demais. O plugin real do Melhor Envio ainda não está nesta branch (vive na
linha principal do site) e a adaptação continua pendente.

A branch do porte não contém o plugin de cotação atual do Melhor Envio (ele vive
na linha principal do site). A adaptação do arquivo atual é pequena e deve ser
feita na conciliação da PR #298:

1. trocar os imports de `commerce_shipping` pelos namespaces ACULTA acima;
2. trocar `CommerceShippingMethod` por `AcultaShippingMethod`;
3. trocar a criação de tarifas por `QuoteRateFactory` adaptado a
   `aculta_commerce\Shipping\ShippingRate`/`ShippingService`;
4. manter `MelhorEnvioQuoter`, cliente OAuth, settings e mapeamento de endereço
   sem alterações funcionais.

Até essa conciliação, não declarar cotação externa homologada nem executar
compra de etiqueta, rastreamento ou contratação real.
