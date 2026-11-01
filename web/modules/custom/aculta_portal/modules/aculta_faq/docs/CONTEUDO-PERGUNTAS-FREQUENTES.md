# Conteúdo editorial — Perguntas frequentes

> Aprovado pelo responsável em 2026-10-29 e cadastrado no Runtime de teste.
> Não publicado em produção.
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

## Como encontro a localização de um espaço?

Consulte a página [Locais](/locais) para ver os espaços cadastrados ou abra o
[Mapa de locais](/mapa). Quando houver endereço, a lista e o mapa oferecem um
link para abrir a rota no Google Maps.

## Base de revisão

- Rotas públicas: `/eventos`, `/eventos/{slug}`, `/locais`, `/mapa` e
  `/calendario` em `aculta_events.routing.yml`.
- Conteúdo da página de evento: `EventPageController`, o tema
  `aculta_event_page()` e `templates/aculta-event-page.html.twig`.
- Exportação diária e por período: `AgendaController` e `IcsController` de
  `aculta_calendar`.
- Localização e rotas: `LocationListController`, `MapController` e o builder de
  links do `aculta_maps`.
- Pagamentos de ingresso fora da v1: decisão D-30 e
  `aculta_events/docs/MODELO-DE-DADOS.md`.

Revisão técnica em 2026-10-28 confirmou rotas, exportação `.ics`, texto de
cancelamento, dados exibidos e ausência de processamento de ingressos. O
responsável aprovou o texto em 2026-10-29. No Runtime de teste, as seis
perguntas foram cadastradas como Nodes publicados `567`–`572`; Firefox ESR
confirmou o conteúdo, links, teclado e reflow. Nenhum cadastro foi feito em
produção.
