# Plano de implantação — ACULTA Calendário (`aculta_calendar`)

Submódulo do projeto de eventos. O modelo de dados, o purpose `eventos`, o deployer e as fases
gerais estão em `../../aculta_events/docs/PLANO-IMPLANTACAO.md`. O roadmap único da frente é
`../../aculta_events/docs/ROADMAP.md` (fases F5 e F6 tratam deste submódulo).

Base de portabilidade no OpenCulturas 3.1.4:

| Origem | Uso no OpenCulturas | Destino |
| --- | --- | --- |
| `views.view.related_dates_archive.yml` | Arquivo de datas (passadas e futuras) | Views de agenda e arquivo |
| `views.view.related_dates_export.yml` | Exportação de datas | Rota `.ics` |
| `views.view.related_date.yml` | Datas relacionadas a uma página | Bloco de datas no evento |
| `modules/custom/openculturas_calendar_widget/` | Incorporação por iframe, com formulário de código e página de embed | Rota de incorporação |
| `themes/opcult/templates/views/view-related-dates.scss`, `views-view--related-date.html.twig` | Visual da lista | SDC `calendar/agenda-list` |
| `themes/opcult/templates/contrib/addtocal_augment/` | Botões “adicionar ao calendário” | Componente próprio (sem `addtocal_augment`, decisão D8) |

## Escopo da v1

1. **Agenda (lista)** por dia, em todos os tamanhos de tela. É a visão de referência.
2. **Mês (grade)** a partir de `md`, com dias que têm eventos como links para a agenda do dia.
3. **Filtros** iguais aos do catálogo (tipo de evento, tipo de local, período).
4. **Exportação `.ics`:** uma data, um dia e o período filtrado. Gerada pelo próprio módulo
   (sem biblioteca de terceiros), com fuso `America/Sao_Paulo` e UID estável por data.
5. **Incorporação por iframe** para sites de terceiros: rota pública com a agenda, código para copiar
   e `frame-ancestors` decidido na fase F5. Incorporação não exibe dados pessoais.

Fora da v1: recorrência (pós-v1), calendário pessoal na conta, sincronização bidirecional com
Google ou Outlook, lembretes e notificações.

## Rotas (caminhos públicos em português)

Declaradas nos purposes `eventos` e `main` (`_aculta_domain_purpose`, lista de purposes), e
registradas em `docs/architecture/ROUTES-SITEMAP.md` com os identificadores `eventos.aculta` e
`main.aculta`.

Decisão D6 (calendário também no `main`): o `main` serve as mesmas rotas de agenda, com os mesmos
dados. A URL canônica de produção é a do purpose `eventos` (`eventos.aculta.org/calendario`), para
evitar conteúdo duplicado; no `main` a página mantém o link para essa canonical. Confirmar essa
regra no `ROUTES-SITEMAP.md` antes de F5 (a regra de canonical do ACULTA é pela produção do purpose).

| Caminho | Função | Observação |
| --- | --- | --- |
| `/calendario` | Agenda (lista) e grade do mês | Visão padrão: agenda |
| `/calendario/mes/{ano}/{mes}` | Grade de um mês | Parâmetros numéricos validados |
| `/calendario/dia/{ano}/{mes}/{dia}` | Eventos de um dia | Fonte do link da grade |
| `/calendario/exportar.ics` | Exportação do período filtrado | Cabeçalho `text/calendar` |
| `/calendario/incorporar` | Agenda para iframe | Sem menu do site; `noindex` |
| `/eventos/{slug}/data/{id}.ics` | `.ics` de uma data | Usa o slug do evento |

Não criar aliases temporários nem redirecionamentos (`AGENTS.md`). Caminho antigo responde 404.

## Dados e regras

- Fonte: `aculta_event_date` (campo `field_quando`, `daterange` do core, com `field_dia_inteiro`). Ver `MODELO-DE-DADOS.md`. Recorrência própria, sem `smart_date`, conforme a decisão D-13.
- Só datas publicadas e não canceladas entram na agenda; canceladas aparecem com aviso no evento.
- Datas passadas: saem da agenda principal e ficam no arquivo (`/calendario/arquivo`, a incluir na
  fase F5 se aprovado).
- Cache: as visões mudam quando uma data muda. Usar tags de cache de entidade e de configuração, com
  contexto de `url.query_args` para filtros, sem cache por hostname.

## Visual

Componentes SDC em `aculta420` (`../../aculta_events/docs/VISUAL.md`, seção 3):
`calendar/month-grid` (≥ md), `calendar/agenda-list` (todos os tamanhos), `filters/event-filters`.

Critérios específicos:

- A grade é tabela com `caption`, `th scope="col"` e `th scope="row"` quando houver linhas de semana.
- Em tela pequena, só a agenda; a grade não é escondida com `display: none` sem alternativa.
- Dias com eventos têm texto (quantidade e nome do primeiro evento), não só ponto colorido.
- Botões `.ics` com rótulo que cita o escopo (“Exportar o dia 14 de outubro”).

## Incorporação (iframe)

- Rota sem cabeçalho e rodapé do site; mesmo visual de tokens.
- `X-Robots-Tag: noindex` na rota de incorporação (ambiente de teste e produção).
- `Content-Security-Policy: frame-ancestors` conforme a decisão da fase F5; padrão recomendado:
  `frame-ancestors *` apenas para a rota de incorporação, nunca no restante do site.
- O código copiado usa `iframe` com `title`, altura responsiva e `loading="lazy"`.

## Testes

- Kernel: rotas por purpose (bloqueio em outros hosts), datas com fuso, exportação `.ics`
  (campos obrigatórios, escape de texto, UID estável).
- Unit: gerador `.ics` (quebra de linha, escape de vírgula e ponto e vírgula).
- Navegador (Firefox ESR, `DISPLAY=:0`): agenda e grade em 320 px e 1280 px, teclado na grade,
  download do `.ics`, iframe carregando. Scripts em `tests/browser/aculta-calendar/` com `README.md`.
- Temas: ACULTA420 e Olivero.

## Riscos específicos

| Risco | Mitigação |
| --- | --- |
| Datas erradas por fuso ao exportar `.ics` | Fuso fixo do site; teste com datas de virada de dia |
| Incorporação em sites que expõem dados | Só dados públicos; `noindex`; sem cookies de sessão na rota |
| Grade inacessível | Tabela semântica e agenda como alternativa |
| Cache servir filtro errado | Contexto `url.query_args`; teste de cache por filtro |

## Ordem sugerida dentro de F5

1. Agenda (lista) com filtros e página de dia.
2. Exportação `.ics` (dia e período).
3. Grade do mês (≥ md).
4. Incorporação por iframe.
5. Arquivo de datas passadas, se aprovado.
