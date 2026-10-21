# Changelog — aculta_lms_messenger

## 0.1.0-dev.2 — conclusão no aviso e resumo diário — 2026-10-20

- Publica avisos no site quando a conclusão aprovada é registrada, revalidando
  status, curso e matrícula atual.
- Fornece as conclusões ao resumo diário opt-in já existente no Messenger;
  e-mail confirmado, janela diária e envio único combinam eventos LMS e Fórum.
- Opt-out descarta somente a pendência de e-mail. O aviso no site permanece.
- Unit cobre evento site-only. Revisão visual do drawer/Runtime DEFERRED.

## 0.1.0-dev.1 — avisos de matrícula e certificado — 2026-10-20

- Adiciona adapter opcional com eventos LMS, persistência idempotente em Message,
  listagem no drawer e contagem do `aculta_messenger`.
- E-mails transacionais usam Message Notify, fila, lock e revalidação do
  destinatário e da origem antes do envio; links revalidam access e purpose.
- Testes Unit cobrem a tradução dos eventos e o isolamento de falha no adapter.
  Testes de integração e visual/Runtime: DEFERRED.
