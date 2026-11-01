# ADR-021 — Um único aceite de Termos e Política de Privacidade

**Status:** vigente desde 2026-10-21, por decisão do responsável.  
**Escopo:** coleta de aceite e ledger de consentimento em todo o projeto.  
**Origem:** `0.4.0-dev.243`, `0.4.0-dev.245` e `0.4.0-dev.247`, [histórico arquivado do Portal](../history/changelog/aculta_portal.md), e a decisão de banner de cookie sem conta em `0.2.0-dev.2`, [histórico arquivado de Statistics](../history/changelog/aculta_statistics.md).

## Contexto

Formulários e identificadores de aceite por função criariam registros
desconectados dos Termos e da Política de Privacidade publicados. O histórico
registra a transferência do aceite para `aculta_user` e a remoção do formulário
separado de rastreamento de e-mail.

## Decisão

1. Há um único formulário para aceitar os Termos de Uso e a Política de
   Privacidade, e um único tipo persistido: `aculta_user_agreement_terms`.
2. Funções que dependem de dados pessoais ou consentimento devem estar descritas
   nos documentos publicados e são cobertas por esse aceite quando a lei não
   exige escolha separada.
3. Não se criam checkboxes, formulários ou tipos de ledger paralelos. Uma função
   que exija consentimento separado depende de decisão explícita antes de ser
   disponibilizada.
4. Cookies de visitantes sem conta permanecem fora do ledger de contas e seguem
   a política própria descrita em [ACEITE-UNICO.md](../portal/ACEITE-UNICO.md).

## Evidência vigente

`AcultaUserAgreementLedger` aceita apenas `aculta_user_agreement_terms`;
`validate-single-acceptance.php` protege formulário, identificadores e gravação.
O contrato operacional e as exceções estão em
[ACEITE-UNICO.md](../portal/ACEITE-UNICO.md).
