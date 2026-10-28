# ADR-022 — Separação entre domínio de apoio e transação Commerce

**Status:** vigente desde 2026-10-17, por decisão do responsável.  
**Escopo:** propósitos de domínio para Loja, Cursos, Apoio, checkout e pagamentos.  
**Origem:** `0.4.0-dev.113`, [histórico arquivado do Portal](../history/changelog/aculta_portal.md).

## Contexto

A escolha do valor de apoio e a transação Commerce têm donos e ciclos distintos.
Manter todo o fluxo no mesmo domínio ou criar checkout por subdomínio duplicaria
regras de carrinho e pagamento.

## Decisão

1. A entrada de doação e assinatura pertence ao purpose `SUPPORT`.
2. Carrinho, checkout e pagamento Drupal Commerce pertencem ao purpose `MAIN`,
   inclusive quando iniciados em Loja, Cursos ou Apoio.
3. Retorno e notificação do gateway usam `MAIN`; o Portal gera os destinos
   canônicos pelo `DomainPurposeManager`, sem host hardcoded.
4. Não manter checkouts nem processadores de pagamento próprios por subdomínio.

## Evidência vigente

`DomainRoutePolicy` classifica rotas Commerce e rotas de doação por propósito;
`DomainRouteSubscriber` e `PaymentDomainPathProcessor` aplicam a separação.
O contrato e suas verificações estão em
[PAYMENT-DOMAIN-POLICY.md](../portal/PAYMENT-DOMAIN-POLICY.md).
