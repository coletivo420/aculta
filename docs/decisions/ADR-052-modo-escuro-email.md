# ADR-052 — Modo escuro do envelope de e-mail é exceção ao token-only

**Status:** aceita (2026-11-07)
**Escopo:** `web/themes/custom/aculta420_v2/src/Hook/MailEnvelopeHooks.php`,
`web/themes/custom/aculta420_v2/templates/aculta-mailer-wrap.html.twig`

## Contexto

O gate de fundamentos exige que o modo escuro dependa apenas de tokens em
`tokens.css`. O envelope de e-mail precisa de estilo escuro inline, porque clientes
de e-mail não leem variáveis CSS do tema nem respeitam `data-theme`.

## Decisão

1. O envelope de e-mail mantém o estilo escuro inline, como exceção ao modo
   token-only.
2. A exceção vale só para esses dois arquivos. Qualquer outro seletor escuro
   fora de `tokens.css` continua falhando no gate.
3. Os valores do modo escuro do e-mail devem vir dos tokens do tema, copiados
   para o inline, e não definidos à mão.

## Consequências

- O gate de fundamentos registra a exceção, com referência a este ADR.
