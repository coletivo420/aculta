# Changelog — aculta_lms_messenger

## 0.1.0-dev.1 — avisos de matrícula e certificado — 2026-10-20

- Adiciona adapter opcional com eventos LMS, persistência idempotente em Message,
  listagem no drawer e contagem do `aculta_messenger`.
- E-mails transacionais usam Message Notify, fila, lock e revalidação do
  destinatário e da origem antes do envio; links revalidam access e purpose.
- Testes Unit cobrem a tradução dos eventos e o isolamento de falha no adapter.
  Testes de integração e visual/Runtime: DEFERRED.
