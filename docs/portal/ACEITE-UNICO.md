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

## Funções cobertas pelo aceite único

Decisão do responsável (2026-10-21): as funções são descritas na política e nos termos publicados, e o aceite
único cobre esse texto. Rascunho das cláusulas em `docs/compliance/CLAUSULAS-POLITICA-ACEITE-UNICO.md`.

- **Progresso por aluno (LMS):** implementado. Situação por curso e dia, 90 dias, apagada na exclusão da conta.
  Liga com o aceite único vigente (`LearningDashboardManager::countClassStatuses(..., TRUE)`).
- **Rastreamento de e-mails:** implementado, mas desligado por `AcultaUserAgreementTrackingConsent::FUNCTION_PUBLISHED`
  (falso). Liga só depois da publicação da política.

## Exceção: cookies de visitante sem conta

O banner de `aculta_statistics` guarda a escolha do visitante em cookie. Visitante sem conta não tem registro no
ledger. Decisão do responsável (2026-10-21): manter como exceção de cookie, desligado por padrão.

## Pendências de decisão

- **Publicação da política** com as cláusulas (responsável). Minutas em [`docs/compliance/minutas/`](../compliance/minutas/README.md). Só então `FUNCTION_PUBLISHED` pode virar verdadeiro.
- Nenhuma revisão jurídica adicional é necessária sobre o consentimento embutido no aceite único. Decisão do responsável (2026-10-21): esse consentimento atende o propósito jurídico do projeto.

## Guardrail

`php scripts/validate-single-acceptance.php` falha quando:

- aparece um identificador `aculta_user_agreement_*` que não está na lista fechada do gate;
- existe classe de formulário com nome de aceite, consentimento ou acordo fora de `AcultaUserAgreementAcceptForm`;
- há checkbox de aceite em formulário fora da lista autorizada;
- há gravação no ledger fora de `AcultaUserAgreementLedger`, `AcultaUserAgreementPolicy` e `AcultaUserAgreementRegistration`.

Para adicionar um identificador ou um arquivo à lista, é preciso alterar o gate, e isso só é feito com decisão do
responsável registrada neste documento.
