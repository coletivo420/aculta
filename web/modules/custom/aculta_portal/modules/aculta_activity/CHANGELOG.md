# Changelog — ACULTA Activity

## 0.1.0-dev.2 — provider verificado no Kernel do Content Index

- Kernel verifica descoberta da tag `aculta_index.provider`, projeção de um Node `activity` e fila incremental no save (1 teste / 18 assertions, SQLite temporário).
- Unit do provider permanece cobrindo bundles/revisões e purpose `main`. Runtime continua sem ativação; ownership editorial legado não foi migrado.

## 0.1.0-dev.1 — fundação e adapter Content Index

- Criado o limite de ownership `aculta_activity` para o bundle Node `activity`.
- Adapter tagged projeta somente metadados mínimos e usa o purpose canônico `main` para revalidar Entity Access.
- Módulo inicial depende do `aculta_index`; o Portal e o Index não dependem do Activity.
- Apresentação, workflow, autoria, migração e comportamento editorial seguem fora deste primeiro recorte.
