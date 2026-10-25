# ACULTA Commerce — Inventário de rotas e riscos (0.1.0-A1)

Inspeção estática na branch `docs/aculta-forum-0.1.0-dev.1`, em 2026-10-22. Este documento não equivale a validação de runtime.

## Rotas efetivamente identificadas

| Rota | Caminho | Dono atual | Decisão |
|---|---|---|---|
| `aculta_portal.shop_home` | `/loja` | Portal / `ShopController::home` | Preservar URL e purpose `shop` durante refatoração |
| `aculta_portal.relatorio_vendas` | `/painel-administrativo/loja/vendas` | Portal | Caracterizar consulta e filtros; futura interface Green Stash não deverá reimplementar storage |
| `aculta_portal.relatorio_vendas_pagamento_atualizar` | `/painel-administrativo/loja/vendas/pagamento/{commerce_payment}/atualizar` | Portal | **Auditar autorização por entidade e ação**, não só permissão global |
| `aculta_portal.relatorio_vendas_pagamento_cancelar` | `/painel-administrativo/loja/vendas/pagamento/{commerce_payment}/cancelar` | Portal | **Auditar idempotência, estado e autorização do pagamento** |
| `aculta_portal.support_subscription_action` | `/meu-apoio/assinatura/{commerce_order}/{acao}` | Portal / apoio | Exclusivo do fluxo de doações; não migrar como operação de loja |
| `aculta_studio.home` | `/studio/ganjalab` | Studio | Experiência GanjaLab já existe; Green Stash terá rota própria |
| `aculta_studio.old_ganjalab_home` | `/ganjalab` | Studio | Compatibilidade existente: não sobrepor |

Fonte: `web/modules/custom/aculta_portal/aculta_portal.routing.yml` e `modules/aculta_studio/aculta_studio.routing.yml`.

## Problemas de fronteira para investigar no runtime

- A rota do relatório geral de vendas exige `view commerce_order`; verificar se há isolamento efetivo por loja, tipo de pedido e domínio, incluindo doações/LMS. **Não assumir segregação só pela rota ou permissão**.
- Os relatórios de pagamento do Portal estão no namespace de loja, mas o gateway Mercado Pago é transversal. Verificar se dados de doações e LMS aparecem indevidamente, e definir filtros conforme purpose e ACL.
- Checkout shipping usa perfil de cobrança como endereço de entrega; testar mudança de endereço, pedidos guest, perfis incompletos e consentimento.
- Se houver várias lojas, o `ShopController` atual filtra por bundle e publicação, sem seleção explícita de store na consulta encontrada. Confirmar necessidade de filtro por loja/domínio.
- A experiência Green Stash ainda não tem rota nem provider; só registrar depois de confirmar ACL e API de extensão do Studio. As telas de administração não precisam pertencer ao purpose `shop` só porque o catálogo público pertence a ele.
- O `aculta_pagamentos_mercadopago` depende de `commerce_payment` e exporta `CheckoutPro`; preserve o mesmo gateway para loja, LMS e doações.

## Provas a coletar no servidor

1. Relatórios administrativos: usuário com `view commerce_order` limitado, pedido de loja, pedido de doação, pedido LMS; checar vazamento cruzado.
2. Operações `atualizar` e `cancelar`: matriz de acesso com dono, permissão, estado, domínio, gateway, tipo de pedido e CSRF.
3. Filtragem da vitrine por status, variação comprável, store, domínio, moeda e contexto de preço.
4. Navegação Studio: testar `/studio/ganjalab`, `/ganjalab` e futura URL do Green Stash sem conflito.
5. Confirmar API real do Content Index antes de implementar serviços e indexadores.

## Restrição para próximas fases

Alterações funcionais só devem ocorrer após caracterizar em testes os três fluxos independentes: **loja**, **LMS** e **doações**, todos consumindo o **mesmo gateway Mercado Pago**. Nenhum caso de teste de loja pode apagar ou reconfigurar pagamentos de LMS/doação.
