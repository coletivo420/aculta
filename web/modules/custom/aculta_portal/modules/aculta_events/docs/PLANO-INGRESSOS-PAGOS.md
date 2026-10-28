# Plano — ingressos pagos (venda dentro do ACULTA)

Status: **rascunho, sem código.** Fora da v1 de eventos (D-30). A v1 só tem campos informativos de preço e link
externo (D-29, E1). Decisões que servem de base: D-31 a D-37 em `docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md`.

> **Atenção:** a emissão de documento fiscal (P-D, D-35) está **sem decisão**. Nenhuma venda pode abrir antes do parecer
> fiscal e jurídico. Este plano não substitui esse parecer.

## 1. O que o OpenCulturas tem

- **Não tem venda de ingressos.** O `openculturas.info.yml` não depende de módulo de comércio ou pagamento.
- Campos informativos em `node.date`: `field_pricing`, `field_pricing_info` e `field_ticketing` (link externo).
  Os dois primeiros entram na v1 do ACULTA (D-29).
- Não há código a copiar. Venda de ingressos é funcionalidade nova no ACULTA.

## 2. Base que será reutilizada (sem dependência nova)

Regra do responsável: usar o `aculta_commerce` e o `aculta_pagamentos_mercadopago` atuais, sem nenhum módulo ou
pacote novo.

| Peça | Uso no plano |
| --- | --- |
| `aculta_commerce` | Pedido, linhas de pedido e ajustes. Novo tipo de pedido `ingresso`, sem novo módulo |
| `aculta_pagamentos_mercadopago` | Checkout Pro, webhooks, estorno com lock e reconciliação |
| Conta única do ACULTA | Recebe todas as vendas. Repasse é manual (D-32) |

Limite conhecido: o gateway não faz repasse automático nem emite documento fiscal. Isso não é implementado nesta fase.

## 3. Regras de negócio decididas

| Tema | Regra | Decisão |
| --- | --- | --- |
| Repasse | Manual, por evento, com total de vendas e estornos fechado | D-32 |
| Taxa | Duas modalidades, percentual e fixa. Administrador define mínimos e quais modalidades ficam ativas no site. Organizador escolhe valor acima do mínimo e a modalidade | D-33 |
| Quem paga a taxa | Padrão: percentual é pago pelo comprador; fixa pelo organizador. O organizador pode escolher | D-33 |
| Evento gratuito | Permitido, sem cobrança | D-33 |
| Estorno | Opções administrativas: ativar ou desativar, e definir o prazo do estorno integral. Sem valor padrão fixado | D-34 |
| Documento fiscal | Pendente. Nenhuma venda antes da decisão | D-35 |
| Dados do comprador | Nome e e-mail. Sem CPF. Retenção padrão de 1 ano após o evento, com controle administrativo | D-36 |
| Check-in | Organizador, administrador e pessoas cadastradas com acesso, pelo celular, por categoria de operador. O organizador convida e revoga o acesso por evento. Entrada registrada e ingresso não reutilizável | D-37 |

## 4. Escopo proposto

1. **Tipo de pedido `ingresso`** em `aculta_commerce`, aceito pelo `aculta_pagamentos_mercadopago`.
2. **Lotes de ingresso** por data: nome, preço, quantidade, período de venda, vinculados a `aculta_event_date`.
3. **Taxa** com modalidade e mínimos, conforme D-33. Taxa incide no pedido e aparece para o comprador quando for o caso.
4. **Compra** pelo fluxo de pedido existente, com limite de quantidade por pedido.
5. **Ingresso emitido**: código único, com QR para check-in.
6. **Check-in** pelo celular, com registro de entrada e bloqueio de reuso.
7. **Estorno** pelo fluxo existente, com as opções que o administrador configura (D-34).
8. **Repasse manual** por evento, com registro (D-32).

## 5. Pontos ainda abertos

| # | Ponto | Depende de |
| --- | --- | --- |
| A | Documento fiscal da venda: quem emite e em que regime | Parecer fiscal (D-35) |
| B | Base legal do tratamento dos dados do comprador (a retenção de 1 ano já está decidida, D-36) | Parecer de LGPD |
| C | Valores dos mínimos de taxa e prazo de estorno: ficam sem valor inicial, para o administrador configurar | Definição do administrador na implementação |
| D | Detalhe técnico da categoria de operador (nome, tela de convite e revogação) | Implementação, dentro de `aculta_events` |
| E | Incidência de taxa sobre o valor do ingresso e a contabilidade do repasse | Parecer fiscal |

## 6. Fases propostas

| Fase | Entrega | Pré-requisito |
| --- | --- | --- |
| P0 | Parecer fiscal e de LGPD (pontos A, B e E) | Decisão do responsável |
| P1 | Lotes e tipo de pedido `ingresso`, com taxa e estorno configuráveis, sem venda aberta | P0 |
| P2 | Compra com gateway de teste (credenciais em `secrets/`) | P1 |
| P3 | Ingresso com QR e check-in | P2 |
| P4 | Repasse manual por evento, com registro | P0 e P2 |

## 7. Restrições de execução

- Testes de pagamento só com credenciais de teste em `secrets/`. Nunca produção sem RC.
- Credenciais de produção só no banco, criptografadas, provisionadas pelo `aculta_deployer`.
- O prefixo do token não identifica o ambiente (`APP_USR-` pode ser de teste). Decidir pelo modo configurado e pelo
  `live_mode` do pagamento.
- Produção só recebe deploy quando todos os módulos estiverem em RC.
- Testes de navegador com Firefox ESR em `DISPLAY=:0`.
- Commits na branch atual. Sem criação de branch pelo agente.
