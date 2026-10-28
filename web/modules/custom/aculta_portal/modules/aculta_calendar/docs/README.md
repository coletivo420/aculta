# ACULTA Calendário (`aculta_calendar`)

Submódulo reutilizável de agenda. Decisão: D-20. Arquitetura: ADR-031.

| Arquivo | Conteúdo |
| --- | --- |
| [PLANO-IMPLANTACAO.md](PLANO-IMPLANTACAO.md) | Escopo, rotas, embed, testes e riscos |
| [RECORRENCIA.md](RECORRENCIA.md) | Motor de recorrência (regra, expansão e limites) |
| [ROADMAP.md](ROADMAP.md) | Roadmap único do submódulo |

Regra de uso: nenhum módulo gera `.ics` ou embed próprios. Quem tem datas registra um provedor de itens.

## API de itens

| Peça | Papel |
| --- | --- |
| `Drupal\aculta_calendar\Item\CalendarItem` | Item imutável: `id`, `titulo`, `inicio`, `fim`, `diaInteiro`, `fuso`, `local`, `link`, `origem`, `rrule`, `excecoes`, `reagendadas`, `cancelado`, `limiteMeses`. Com `rrule`, é uma série. |
| `Drupal\aculta_calendar\Item\CalendarItemProviderInterface` | `id()`, `items(CalendarQuery)`, `filtros()`, `cacheTags()`. |
| `Drupal\aculta_calendar\Item\CalendarQuery` | Janela (`de`, `ate`), referência (`agora`) e filtros do provedor. |
| Tag `aculta_calendar.item_provider` | Registra o provedor. O `aculta_calendar.item_source` agrega todos. |

O calendário expande a regra (`CalendarOccurrences`), monta a agenda (`CalendarAgenda`), a grade
do mês (`MonthGrid`) e o `.ics` (`CalendarIcsExporter`). O provedor só traduz o dono do dado.

## Rotas e purposes

O módulo que publica a página define as rotas e informa os nomes na opção
`_aculta_calendar_rotas` (agenda, month, day, ics, embed). O calendário não escolhe purpose nem
host. Exemplo em `aculta_events.routing.yml`.

| Caminho (publicado por `aculta_events`) | Conteúdo |
| --- | --- |
| `/calendario` | Agenda por dia, com filtros |
| `/calendario/mes/{ano}/{mes}` | Grade do mês |
| `/calendario/dia/{ano}/{mes}/{dia}` | Agenda de um dia |
| `/calendario/exportar.ics` | `.ics` de um período filtrado |
| `/calendario/incorporar` | Agenda para iframe |

## O que cada área pode e não pode

- **Pode:** registrar um provedor, consumir `aculta_calendar.item_source`, `aculta_calendar.agenda`,
  `aculta_calendar.ics_exporter` e publicar as rotas com `_aculta_calendar_rotas`.
- **Não pode:** importar classes de `aculta_events` ou de `aculta_lms_agenda` dentro deste módulo.
  O teste `tests/src/Unit/IsolamentoTest.php` falha se isso acontecer.

## Provedores registrados

| Provedor | Módulo | Itens |
| --- | --- | --- |
| `aculta_events` | `aculta_events` | Datas de evento publicadas e canceladas, com regra e exceções |
