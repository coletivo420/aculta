# Changelog — ACULTA Activity

## 0.1.0-dev.5 — cobrir ciclo CRUD do provider Index

- Kernel ativa `aculta_activity` e verifica backfill, atualização e exclusão
  através do worker real do Index: 2 testes / 29 assertions PASS em MariaDB.
- A fixture instala `node_access`, necessário ao delete do Node no Core.

## 0.1.0-dev.4 — hooks e validação sob ownership do Activity

- Movidos para `aculta_activity` os hooks de formulário e Metatag exclusivos do
  bundle `activity`, além da validação condicional de modalidade, endereço e
  horário. Portal permanece responsável por canonical e metadados transversais.
- O módulo declara `schema_metatag` como dependência direta dos hooks que
  serializam localizações estruturadas.


## 0.1.0-dev.3 — versão declarada do provider Index

- Provider declara `projection_version=1` no fingerprint do rebuild; aumentar
  a versão quando mudar os campos, relações ou semântica indexáveis.

## 0.1.0-dev.2 — provider verificado no Kernel do Content Index

- Kernel verifica descoberta da tag `aculta_index.provider`, projeção de um Node `activity` e fila incremental no save (1 teste / 18 assertions, SQLite temporário).
- Unit do provider permanece cobrindo bundles/revisões e purpose `main`. Runtime continua sem ativação; ownership editorial legado não foi migrado.

## 0.1.0-dev.1 — fundação e adapter Content Index

- Criado o limite de ownership `aculta_activity` para o bundle Node `activity`.
- Adapter tagged projeta somente metadados mínimos e usa o purpose canônico `main` para revalidar Entity Access.
- Módulo inicial depende do `aculta_index`; o Portal e o Index não dependem do Activity.
- Apresentação, workflow, autoria, migração e comportamento editorial seguem fora deste primeiro recorte.
