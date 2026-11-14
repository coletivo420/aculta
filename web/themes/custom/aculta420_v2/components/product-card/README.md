# product-card

Cartão de produto da vitrine Commerce, classe `aculta420_v2:product-card`.
O adapter do view mode `catalog` envia o render array padrão do Commerce pelo
slot `content`, preservando fields e formulário sem reconstrução.

## Slot

- `content`: fields, variações, preço e formulário de adicionar ao carrinho.

O view mode Commerce `catalog` entrega o render array completo ao slot `content` via `PagePresentationHooks::preprocessCommerceProduct()`. O template Core mantém o `article` semântico; a story apresenta o conteúdo do SDC isoladamente. Sem JavaScript.
