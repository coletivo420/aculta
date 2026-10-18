# Changelog — aculta_pagamentos_mercadopago

## 0.1.0-dev.1 — porte do commerce_mercado_pago — 2026-10-18

- Porte do `drupal/commerce_mercado_pago` 3.0.0-rc3 para o submódulo: plugin `CheckoutPro` (`mercado_pago_checkout_pro`), tipo de meio de pagamento, formulário de redirecionamento, controlador de retorno de popup, mapa de meios de pagamento, bibliotecas, schema e teste Kernel.
- Namespace `Drupal\aculta_pagamentos_mercadopago`; rotas, bibliotecas e canal de log renomeados. IDs de plugin, chave de dados do pedido e caminho de retorno mantidos.
- Documentação e licença do upstream preservadas (`README.original-commerce_mercado_pago.md`, `LICENSE.txt`).
- Ainda não habilitado nem referenciado pelo `aculta_portal`: a troca de dependência e a remoção do contrib vêm nas entregas seguintes.
- RUNTIME STATUS: DEFERRED (não instalado no Runtime de teste).
