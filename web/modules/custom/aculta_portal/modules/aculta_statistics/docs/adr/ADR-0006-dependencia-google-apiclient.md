# ADR-0006 — Dependência google/apiclient para GA4 e Search Console (proposta)

Status: **aceita** (2026-10-21). Instalada em `google/apiclient` 2.20.1, com `composer require --minimal-changes --no-scripts`.
A remoção de `drupal/change_mail_page` (já substituída pelo `aculta_user`) foi commitada antes, em commit próprio.

## Necessidade

- **GA4 Data API (v1beta), `runReport`**: consulta agregada das métricas do site (fase 0.6.0). Usa conta de serviço, com
  autenticação OAuth2 por JWT, e chamadas REST autenticadas.
- **Search Console, Search Analytics**: consulta de cliques e impressões por consulta e página (mesma fase). Usa a mesma
  autenticação.

Sem a biblioteca, seria preciso implementar JWT, troca de token, paginação e tratamento de erro do Google. Isso é
risco de segurança e de manutenção. A biblioteca oficial mantém esses pontos.

## Alternativas consideradas

- **google/analytics-data** (cliente oficial só do GA4 Data API): dependência menor e focada, mas não cobre o Search
  Console. Seriam duas bibliotecas se o Search Console também for implementado.
- **Cliente HTTP do Drupal com JWT próprio**: sem dependência nova, mas mais código de autenticação para manter.

## Decisão

O responsável escolheu `google/apiclient` (2026-10-21). Esta ADR registra a justificativa exigida pelo AGENTS.md (item
14, dependência Composer). Pré-requisitos para ligar o cliente real:

1. credencial da conta de serviço, lida pelo `aculta_portal.secrets_manager` (AGENTS.md, segredos);
3. ID da propriedade GA4 e domínio verificado no Search Console (preferência por DNS, `docs/integrations/GOOGLE.md`).

## Efeitos

- Sem credencial, o cliente continua o nulo (`NullGa4ReportClient`) e nenhum pedido sai do servidor.
- A biblioteca só é carregada pelo cliente real, que fica desligado por padrão.
