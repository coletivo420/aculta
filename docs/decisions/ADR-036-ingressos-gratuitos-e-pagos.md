# ADR-036 - Ingressos gratuitos e pagos no ACULTA Events

- **Status:** Proposta técnica em revisão. As regras de produto D-60/D-61 estão aprovadas; a ADR não autoriza coleta pública nem venda.
- **Data:** 2026-10-30.
- **Contexto:** Events armazena gratuidade/preço informativos e link externo, mas não implementa RSVP, emissão, venda, lotes ou check-in. O Portal possui Commerce e gateway Mercado Pago, porém os tipos atuais não ligam pedidos a ingressos de Eventos.
- **Proposta:** `aculta_events` é dono de ofertas/lotes, participantes, inscrição, emissão e check-in; `aculta_commerce` mantém pedidos e itens pagos; `aculta_pagamentos_mercadopago` mantém o gateway; `aculta_portal` coordena o carrinho existente. Sem gateway, carrinho ou storage financeiro paralelo.
- **Gratuito:** RSVP sem cobrança, nome/e-mail (D-33/D-40), sem CPF, Commerce order, carrinho ou gateway; valida capacidade e emite credencial única. Não coletar em produção antes de base legal, aviso, retenção e revisão LGPD.
- **Pago:** lote associado ao Evento/Data, order type `ingresso` e item identificável, checkout Mercado Pago; emitir somente após pagamento confirmado/idempotente. Sem frete. Só pedido pago de Eventos entra no carrinho integrado.
- **Credencial:** trilha gratuita e paga converge na emissão e check-in móvel, mas mantém estados financeiros separados. QR contém token opaco, validado no servidor e sem PII; uma entrada confirmada não pode ser reutilizada.
- **Domínios:** escrita de inscrição/compra no purpose `eventos`; nunca fazer POST por `account`. Administração técnica/global no MAIN. Gestão do organizador em ACULTA Studio Culturas (`/culturas`), cuja implementação está parcial e ainda requer homologação.
- **Financeiro:** taxa, estorno e repasse seguem D-31 a D-34; repasse manual, sem split/marketplace inicialmente. Venda paga bloqueada por parecer fiscal/jurídico (D-35), LGPD e homologação sandbox.
- **Compatibilidade:** preservar `field_gratuito`, `field_pricing`, `field_pricing_info` e `field_ticketing`; definir fonte/precedência e migração antes de ativar lotes internos.
- **Interpretação proposta à ADR-032/D-39:** somente pedidos pagos integram o carrinho. RSVP gratuito não é pedido e fica fora do carrinho. Requer revisão antes de codificar.
- **Pontos resolvidos por D-60/D-61:** RSVP opcional por Evento; escopo da capacidade configurável entre Evento/Data/Lote; limites, duplicidade, reserva/expiração, espera e cancelamento configuráveis; inscrição por ocorrência ou série; transferência sempre permitida, auditável e com invalidação da credencial anterior; `aculta_events` é dono das ofertas/lotes e RSVP gratuito não usa Commerce.
- **Pontos ainda abertos:** base legal, aviso e revisão LGPD antes de coletar dados publicamente; parecer fiscal/jurídico antes de venda paga; decisão técnica da forma do item Commerce (variação ou entidade própria); detalhes acessíveis de emissão/entrega da credencial e operação do check-in. Os demais parâmetros de taxa e estorno são configurações administrativas, não decisões de produto pendentes.
- **Plano:** [integração de ingressos](../../web/modules/custom/aculta_portal/modules/aculta_events/docs/PLANO-INGRESSOS-PAGOS.md).

## Consequências

- Campos informativos não indicam ticketing ou pagamento integrado.
- Implementação faseada: modelo, RSVP gratuito, venda paga sandbox, carrinho apenas para pedidos pagos, emissão/check-in, operação financeira e homologação.
- Aprovar esta proposta não coloca inscrições ou compras em operação.
