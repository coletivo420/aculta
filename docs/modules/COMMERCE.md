# Módulos — Commerce, apoio e pagamentos

Data da revisão: 2026-10-07.

Drupal Commerce é a fonte de verdade para pedidos, preços, checkout, pagamentos e stores.

| Módulo | Papel |
| --- | --- |
| commerce | fundação Commerce |
| commerce_cart | carrinho |
| commerce_checkout | checkout |
| commerce_order | pedidos |
| commerce_payment | pagamentos |
| commerce_price | valores/moeda |
| commerce_store | lojas |
| commerce_number_pattern | numeração |
| aculta_donation (port de commerce_donation_flow) | apoio/doação |
| commerce_mercado_pago | gateway Mercado Pago |
| state_machine | estados usados por Commerce |
| inline_entity_form | edição de entidades associadas |
| address | estrutura de endereço |
| cep_autocomplete | preenchimento assistido de CEP |

## Regras ACULTA

- não criar ledger/storage paralelo de apoio;
- validar access antes de ler itens, totais e pagamentos privados;
- Mercado Pago permanece sob Commerce Payment;
- credenciais não são versionadas;
- checkout/pagamento não vira AJAX genérico do Portal;
- Address/Profile continuam fontes de verdade de endereço.
