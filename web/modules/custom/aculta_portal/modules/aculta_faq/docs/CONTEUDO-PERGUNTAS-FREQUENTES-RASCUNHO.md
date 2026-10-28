# Rascunho de conteúdo — Perguntas frequentes

> Minuta editorial para revisão. Não está cadastrada nem publicada no Runtime.
> O conteúdo abaixo foi redigido a partir dos fluxos atuais do ACULTA; não
> reproduz perguntas ou respostas da FAQ do OpenCulturas.

## Onde encontro os eventos e suas datas?

Consulte a página [Eventos](/eventos) para ver os eventos publicados. A página
[Calendário](/calendario) organiza as datas em agenda e permite consultar um dia
ou período.

## O que encontro na página de um evento?

A página apresenta a descrição do evento, seu tipo, a pessoa organizadora e as
próximas datas conhecidas, com horário e local quando informado. Cada data é uma
ocorrência do evento e pode ter situação própria.

## O que significa a indicação “cancelado”?

Ela indica que o evento ou aquela ocorrência foi cancelado. Um evento pode ter
mais de uma data; confira a indicação junto à data para saber qual ocorrência
foi afetada.

## Posso adicionar as datas ao meu calendário?

Na página Calendário, use os links de exportação `.ics` do dia ou do período.
Depois, importe o arquivo no aplicativo de calendário que você utiliza.

## O ACULTA recebe o pagamento de ingressos?

Não. Na versão atual, o ACULTA não processa pagamento de ingressos. Consulte a
organização do evento para confirmar valores, disponibilidade e formas de
compra ou reserva.

## Base de revisão

- Rotas públicas: `/eventos`, `/eventos/{slug}`, `/locais` e `/calendario` em
  `aculta_events.routing.yml`.
- Conteúdo da página de evento: `EventPageController`, o tema
  `aculta_event_page()` e `templates/aculta-event-page.html.twig`.
- Exportação diária e por período: `AgendaController` e `IcsController` de
  `aculta_calendar`.
- Pagamentos de ingresso fora da v1: decisão D-30 e
  `aculta_events/docs/MODELO-DE-DADOS.md`.

Antes do cadastro, revisão editorial deve confirmar a voz institucional e se
estas respostas cobrem as dúvidas prioritárias do público do ACULTA.
