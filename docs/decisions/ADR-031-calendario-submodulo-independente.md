# ADR-031 — Calendário como submódulo independente (`aculta_calendar`)

**Status:** aceito em 2026-10-28, pelo responsável (D-20 de `docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md`).
**Escopo:** motor de recorrência, exportação `.ics`, allowlist do embed e visões de agenda e de mês do ACULTA.

## Contexto

O mapa passou a ser um submódulo próprio (ADR-030, D-19) porque mais de uma área do site o usa. O calendário segue o
mesmo caminho: `aculta_events` e `aculta_lms_agenda` precisam de agenda, e outras áreas podem precisar.

## Decisão

1. `aculta_calendar` **não conhece eventos, turmas ou qualquer outro dono de dados.** Não importa classes desses módulos.
2. Ele define uma **API de itens de calendário** (`CalendarItem`: id, título, início, fim, dia inteiro, fuso, local,
   link, origem). Cada módulo que tem datas registra um **provedor** de itens por serviço com tag
   (`aculta_calendar.item_provider`).
3. O submódulo entrega, a partir dos provedores: a agenda por dia, a grade do mês, a exportação `.ics` (de uma data
   e de um período) e o embed por iframe, com a allowlist de hosts.
4. O motor de recorrência (regra RRULE e expansão na janela) fica no calendário. Quem guarda a regra é o dono do dado;
   o calendário expande.
5. **Rotas e purposes** são definidos pelo módulo que publica a página. O calendário fornece o conteúdo; o purpose
   (ex.: `eventos`, `main`) vem de quem registra a rota (ADR-015).
6. **Fuso:** `America/Sao_Paulo` do site, como no restante do projeto (D9).
7. Visual: componentes do calendário seguem o roteiro visual do tema (frente B). Nada de biblioteca de calendário pesada.

## Consequências

- `aculta_events` (F5) e `aculta_lms_agenda` (D-15) viram **provedores**. Nenhum dos dois implementa exportação ou embed próprios.
- Trocar a fonte de dados de um provedor não exige mudança no calendário.
- O calendário pode ser habilitado sem eventos ativos. Hoje a `info.yml` diz "aguarda o purpose eventos", o que deve
  ser ajustado na implementação.

## Alternativas consideradas

| Alternativa | Por que não |
| --- | --- |
| Calendário dentro de `aculta_events` | Acopla a agenda do LMS e de outras áreas a eventos |
| Biblioteca de calendário de terceiros (FullCalendar etc.) | Dependência de JavaScript pesada, contra a regra de dependências do projeto |

## Aditivo: gestão editorial de Datas no Evento (2026-10-30)

A experiência administrativa de Datas foi centralizada em Eventos (ADR-033), mas esta decisão não muda o contrato do calendário:

- `aculta_event_date` continua sendo a fonte de datas, o provedor `aculta_events.item_provider` continua registrado e `EventDateOccurrences`/RRULE permanecem no fluxo.
- Agenda, grade, `.ics`, embed e páginas públicas seguem consumindo a Data; a mudança é apenas editorial e de navegação administrativa.
- Nenhuma rota ou entidade do calendário foi removida. As rotas administrativas de recorrência e ocorrência permanecem técnicas, restritas a quem administra eventos.
- A rota global de criação de Data (`aculta_events.date_add`) saiu; a criação passou a exigir o Evento de origem no caminho.

## Aditivo: regra de domínio (decisão do responsável, 2026-10-30)

- **Todo `/painel-administrativo/` responde somente no MAIN** — inclusive Eventos, Datas, Locais e OSM. Em subdomínios, as rotas administrativas retornam **404** (nunca 302), o toolbar do Navigation não é renderizado e não há ações editoriais. O purpose `eventos` serve as páginas públicas e o calendário.
- A página pública de Eventos não expõe ação de edição; o admin é acessado no MAIN.
- Outros domínios apenas exibem o bloco somente leitura `aculta_events_upcoming`, que consulta Eventos publicados e acessíveis e aponta para as URLs de `eventos` no ambiente.
- O contrato do calendário não muda: agenda, grade, `.ics`, embed e recorrência continuam consumindo `aculta_event_date` como fonte de dados.
