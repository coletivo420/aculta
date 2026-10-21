# Aceite único de Termos de Uso e Política de Privacidade

Decisão do responsável (2026-10-21). Vale para todo o projeto.

## Regra

1. **Existe um único formulário de aceite:** `AcultaUserAgreementAcceptForm` (`aculta_portal`), com o tipo de
   aceite `aculta_user_agreement_terms`.
2. **Todas as funções do portal** que dependem de dado pessoal ou de consentimento precisam estar descritas na
   **Política de Privacidade** e nos **Termos de Uso** publicados. O aceite único cobre esse texto.
3. **Não se criam novos formulários de aceite**, nem checkbox de aceite fora do formulário único, nem tipo novo de
   aceite no ledger (`aculta_user_agreement`).
4. **Não há aceite opcional por função** em formulário próprio. Uma função que exige escolha separada não entra no
   portal até o responsável decidir a base legal (ver a seção de consentimento abaixo).
5. O ledger (`AcultaUserAgreementLedger`) grava **somente** `aculta_user_agreement_terms`. Qualquer outro tipo é
   recusado na gravação e não é reconhecido na leitura.

## Por que

- Um único texto aceito é auditável: a evidência (versão, hash e revisão dos documentos) aponta para um aceite só.
- Aceites espalhados por função criam consentimentos que não estão na política e não são revogáveis de forma
  coerente. Isso já aconteceu com o rastreamento de e-mail, removido nesta decisão.
- Consentimento que o titular precisa dar de forma separada (art. 8º, §4º da LGPD) não cabe num aceite único
  genérico. Por isso uma função desse tipo só entra com decisão explícita do responsável, sem formulário improvisado.

## Pendências de decisão

- **Rastreamento de e-mail (aberturas e cliques):** desligado (falha fechada) em `aculta_portal`
  (`AcultaUserAgreementTrackingConsent`). Para ligar, a função precisa estar na política e o responsável decide a base
  legal. Não há aceite próprio.
- **Série de progresso por aluno (LMS):** não criada. Depende da mesma decisão de base legal.
- **Banner de cookies de visitante anônimo (`aculta_statistics`):** decisão de cookie fora da conta. Não há aceite
  no ledger porque não há conta. Decisão pendente do responsável.

## Guardrail

`php scripts/validate-single-acceptance.php` falha quando:

- aparece um identificador `aculta_user_agreement_*` que não está na lista fechada do gate;
- existe classe de formulário com nome de aceite, consentimento ou acordo fora de `AcultaUserAgreementAcceptForm`;
- há checkbox de aceite em formulário fora da lista autorizada;
- há gravação no ledger fora de `AcultaUserAgreementLedger`, `AcultaUserAgreementPolicy` e `AcultaUserAgreementRegistration`.

Para adicionar um identificador ou um arquivo à lista, é preciso alterar o gate, e isso só é feito com decisão do
responsável registrada neste documento.
