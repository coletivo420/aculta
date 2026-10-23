# ACULTA Commerce Melhor Envio

Integração logística com o Melhor Envio. Submódulo do `aculta_commerce`, versão `0.1.0-dev.1`.

Status: **fundação** (ME-0.1-B) e **adaptador de CEP** (ME-CEP 0.1-C). Ainda não há cotação de frete, contratação,
etiqueta, rastreamento nem integração com a API do Melhor Envio. Ver [ROADMAP](docs/ROADMAP.md).

## Responsabilidades

- Adaptar o endereço de envio do pedido para o Melhor Envio (CEP de destino validado).
- Usar a consulta de CEP compartilhada do Portal (`aculta_portal_cep`). Não existe segundo sistema de consulta.
- Futuro: método de envio do Drupal Commerce Shipping, cotação, contratação e etiquetas (fases ME-0.2 em diante).

Fora de escopo, por regra de arquitetura:

- Carrinho, pedido, produto e pagamento: continuam no Drupal Commerce, no Portal e no Mercado Pago.
- Endereço: fica no Profile/Order do Commerce. O submódulo não guarda cópia.
- Checkout: nenhum campo de CEP novo. Preservados `CartConsolidator`, `CartUtility`, `CartSite`,
  `AcultaShippingInformation` e `BillingAddressPane`.

## Componentes

| Serviço | Classe | Papel |
|---|---|---|
| `aculta_commerce_melhorenvio.address_adapter` | `Address\ShippingAddressAdapter` | Valida o CEP do perfil, pede sugestão só quando faltam campos e devolve um resultado com `status`. |

### Contrato do adaptador

`resolve(array $address): array` recebe os campos do Drupal Address e devolve:

- `status`: `complete`, `suggested`, `not_found`, `invalid_postal_code` ou `unavailable`.
- `postal_code`: CEP com 8 dígitos (ou `NULL` quando inválido).
- `missing`: campos vazios que o CEP poderia preencher.
- `suggestion`: só os campos de `missing` encontrados na consulta.

Regras:

1. Endereço completo não consulta a base de CEP.
2. CEP com máscara é normalizado antes de qualquer uso.
3. Nunca sobrescreve campo já preenchido, nem número ou complemento.
4. CEP não encontrado não gera endereço inventado.
5. Falha da consulta resulta em `unavailable` e não interrompe o checkout.
6. O adaptador não grava nada. Quem decide salvar é o formulário do Commerce, pelo fluxo normal.
7. Disponibilidade de frete não é decidida aqui. Ela vem da cotação real do Melhor Envio (fase ME-0.2-A).

## Dependências

- `aculta_portal_cep` (serviço `aculta_portal_cep.lookup`, interface `CepLookupInterface`).
- Previsto para ME-0.2-A: `commerce_shipping` e `aculta_commerce`.

## Testes

- Unit: `tests/src/Unit/ShippingAddressAdapterTest.php` (7 testes).
- Kernel e Functional: pendentes. Ver a matriz em `docs/ROADMAP.md` e as dívidas no registro central.

## Segurança e LGPD

- Nenhum token, CPF, CNPJ ou endereço vai para log.
- O CEP é o único dado do endereço enviado à consulta externa, como já acontecia com o endpoint AJAX.
