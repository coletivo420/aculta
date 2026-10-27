# Decisões sobre o drift de configuração (2026-10-27)

Registro das decisões do responsável sobre os 26 itens que o validador de segurança reporta
após a publicação de 4cda28b4d. Este documento não altera configuração nem baseline.

## Itens mantidos

| Item | Decisão | Motivo |
|---|---|---|
| field.field.profile.email_confirmed.address | Manter | Guarda dados de clientes em uso (checkout de doação e Melhor Envio). |
| field.storage.profile.address | Manter | Mesmo motivo. |
| aculta_commerce_melhorenvio.settings | Manter | Updates 8001 e 8002 dependem da configuração. |
| key.key.melhorenvio_production_client_id | Manter | Updates 8001 e 8002 dependem da configuração. |
| key.key.melhorenvio_production_client_secret | Manter | Mesmo motivo. |
| key.key.melhorenvio_sandbox_client_id | Manter | Mesmo motivo. |
| key.key.melhorenvio_sandbox_client_secret | Manter | Mesmo motivo. |
| key.key.aculta_melhorenvio_token_encryption | Manter | Mesmo motivo. |
| aculta_commerce.aculta_shipment_type.default | Manter até a janela de cutover | Faz parte da troca de cutover. |
| commerce_checkout.commerce_checkout_flow.default | Manter | Diferença de rótulo e de dependências; revisada. |
| commerce_checkout.commerce_checkout_flow.shipping | Manter até a janela de cutover | Painel aculta_shipping_information substitui shipping_information; revisada. |
| flag.flag.seguir_publicacao | Manter | Até decidir o destino dos registros de "seguir". |

## Itens que podem sair, somente por ordem separada

| Item | Situação |
|---|---|
| core.entity_view_mode.node.pdf | Menor risco. Depende de ordem separada. |
| entity_print.settings | Menor risco. Depende de ordem separada. |
| system.action.entity_print_pdf_download_action | Menor risco. Depende de ordem separada. |

## Itens do cutover, removidos na janela de desinstalação

Os 13 objetos de commerce_shipping e views.view.order_shipments saem do banco quando o
pm:uninstall commerce_shipping rodar, em ordem separada.
