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

## Importação revisável de iCalendar

A importação é uma funcionalidade opcional do próprio `aculta_calendar`. O fluxo é deliberadamente separado em
origem autorizada, busca protegida, leitura, normalização, reconciliação e revisão editorial:

1. Crie uma entidade de configuração `CalendarImportSource` (`aculta_calendar_import_source`) para identificar a
   origem, URL e `allowedHosts`. A allowlist contém hosts exatos (sem curingas), por fonte; mantenha-a curta e
   administrada por quem responde pela integração.
2. Para uma fonte configurada, `aculta_calendar.remote_import` (`import($source)`) busca o arquivo pela URL e roda
   todo o fluxo abaixo. `aculta_calendar.import_remote_fetcher` também pode ser usado diretamente para obter o
   conteúdo. Só HTTPS na porta 443 é aceito; o host deve corresponder exatamente à allowlist. A aplicação resolve
   DNS, recusa respostas privadas, reservadas ou não globais e fixa o IP validado na conexão cURL, mantendo o nome
   do host para SNI e validação do certificado. Use somente nomes DNS completos, sem wildcard ou IP literal.
   TLS é obrigatório, redirecionamentos e proxies são desativados, `connect_timeout` é 5 s e `timeout` é 15 s.
   O corpo é limitado durante a transferência. Sem cURL com suporte a pinagem, a chamada falha fechada.
3. `aculta_calendar.import_reader` (`CalendarFileReader::read($path)`) lê o arquivo local temporário. O leitor recusa
   URLs, arquivos acima de 2 MiB e calendários com mais de 2.000 eventos. Ele lê os campos UID, DTSTART, DTEND,
   SUMMARY, DESCRIPTION, LOCATION e STATUS; não interpreta recorrência ou todos os componentes RFC 5545.
4. Passe cada `ParsedCalendarEvent` por `aculta_calendar.import_normalizer` (`normalize()`). O normalizador limpa e
   limita os textos, valida TZID, assume `America/Sao_Paulo` quando não há fuso e marca `timezoneAssumed`.
5. Passe a lista de `NormalizedCalendarEvent` para `aculta_calendar.import_processor`
   (`process($source, $events)`). O retorno contém contagens `created`, `updated`, `unchanged` e `absent`.
   UID repetido no mesmo arquivo rejeita a importação. UID igual não cria revisão; conteúdo alterado gera revisão
   para revisão humana; UID ausente é marcado, sem exclusão nem despublicação automática.
6. Usuários com a permissão restrita `administer aculta calendar imports` revisam os itens em
   `/painel-administrativo/calendario/importacao`. Publicar promove o conteúdo revisado; manter como rascunho não
   o publica. A importação nunca publica automaticamente.

O provedor `aculta_calendar.imported_item_provider` integra itens publicados ao contrato geral de calendário.
Rascunhos e cancelamentos não são expostos. Um item publicado que desapareça da fonte continua público até uma
decisão editorial, conforme a regra de ausência; o campo `source_presence` informa a divergência.

I5 foi autorizada pela D-21. Isso não adiciona suporte a componentes ou campos além do leitor mínimo, nem autoriza
outras formas de acesso de rede ou download de imagens externas.

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

A resposta HTML do iframe usa `private, no-store`, pois um provedor pode devolver itens vinculados à conta atual.
O render array da agenda mantém os contexts e tags dos provedores.

## O que cada área pode e não pode

- **Pode:** registrar um provedor, consumir `aculta_calendar.item_source`, `aculta_calendar.agenda`,
  `aculta_calendar.ics_exporter` e publicar as rotas com `_aculta_calendar_rotas`.
- **Não pode:** importar classes de `aculta_events` ou de `aculta_lms_agenda` dentro deste módulo.
  O teste `tests/src/Unit/IsolamentoTest.php` falha se isso acontecer.

## Provedores registrados

| Provedor | Módulo | Itens |
| --- | --- | --- |
| `aculta_events` | `aculta_events` | Datas de evento publicadas e canceladas, com regra e exceções |
| `aculta_calendar_import` | `aculta_calendar` | Itens importados publicados após revisão editorial |
