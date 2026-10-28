# Auditoria do OpenCulturas 3.1.4 — o que pode ser copiado

Escopo: código e configuração do OpenCulturas que seriam copiados, ou usados como base, para
`aculta_events` e `aculta_calendar`. Referência: `/home/piradopirata/projetos/aculta-openculturas/`
(somente leitura; SHA-256 em `ORIGEM-OPENCULTURAS.md`).

Esta auditoria não copia código. Ela decide, arquivo a arquivo, o que entra no ACULTA e em que
condições. Achados com identificador (S = segurança e privacidade, T = técnica, V = visual,
C = conteúdo e configuração) são referenciados nas decisões.

## 1. Resumo

| Grupo | Arquivos | Linhas | Decisão geral |
| --- | --- | --- | --- |
| Mapa (`openculturas_map`) | 20 | 8.211 | **Não copiar** o código. Reescrever com Google Maps (D7). Usar só como referência de filtros e views |
| Widget de calendário (`openculturas_calendar_widget`) | 13 | 984 | **Reescrever** a partir do conceito. O controller tem falha (S3) |
| Hooks de evento e data (`openculturas_custom`, trechos) | 5 | 1.145 | **Reescrever** só o que for necessário. Um hook tem falha de acesso (S1) |
| Visual (`opcult`, templates e SCSS) | 8 | 851 | **Não copiar** o CSS nem o Twig. Usar como referência de layout em SDC do `aculta420` |

Conclusão: **nenhum arquivo deve ser copiado diretamente.** Todo código que entrar no ACULTA
será reescrito com as APIs do Aculta, com testes, sem os achados abaixo, e com atribuição
quando houver trecho derivado.

Os pontos de maior risco:

- S1 (alta): acesso sem verificação em `nodePrepareForm`.
- T1 e T2 (altas): dependências não declaradas, que impedem copiar os módulos inteiros.
- S2 (média-alta): montagem de iCal sem escape, que permite injetar propriedades.
- S4 e S5 (médias): endpoints públicos e exportações que expõem mais dados que o necessário.

## 2. Achados de segurança e privacidade

| ID | Severidade | Onde | Achado | Evidência | Ação no ACULTA |
| --- | --- | --- | --- | --- | --- |
| S1 | **Alta** | `openculturas_custom/src/Hook/OpenculturasCustomDateEventHooks.php`, `nodePrepareForm()` | Lê `event_id` e `member_id` da query string e carrega os nós sem `->access('view')`. Depois clona os parágrafos `field_people_reference` do evento de origem para o novo nó de data. Um usuário pode reaproveitar pessoas de evento não publicado. | Linhas 61–127: `entityTypeManager->getStorage('node')->load($event_id)` sem checagem de acesso; `clone $paragraph` | **Não copiar.** Reescrever com verificação de acesso e sem clonagem automática. Teste Kernel de acesso negado |
| S2 | **Média-alta** | `openculturas_custom/src/Plugin/DateAugmenter/AddToCal.php` | Monta `SUMMARY:` e `LOCATION:` com `parseFieldValue()` sem `strip_markup`, então quebras de linha (CR/LF) no título ou no local não são removidas. Não há escape de `;` e `\` (RFC 5545 §3.3.11). Uma quebra de linha cria uma linha iCal nova, que pode ser uma propriedade inventada. Exige permissão de editor para ter efeito. | Linhas 172, 197 (`'SUMMARY:' . $label`, `'LOCATION:' . $location`); `parseFieldValue` só normaliza quebras com `$strip_markup = TRUE` | Reescrever o gerador de `.ics` (D8). Remover CR/LF e escapar `\`, `;`, `,` e quebras antes de montar. Teste com título contendo CR/LF e `;` |
| S3 | Média | `openculturas_calendar_widget/src/Controller/OpenculturasCalendarWidgetController.php` | O parâmetro `source_uri` da query vira `Url::fromUri()` do link "More dates", sem validação de destino. Permite apontar o link para qualquer URI. | Linhas 92–96: `Url::fromUri((string) $this->request->query->get('source_uri'))` | Não aceitar URI livre. Aceitar só caminhos internos ou hosts da lista do purpose |
| S4 | Média | `openculturas_map/config/install/views.view.oc_map_dates.yml`, `views.view.oc_map_locations.yml` (displays `rest_export`) | Os endpoints `rest/oc_map_dates` e `rest/oc_map_locations` herdam acesso `access content` (público) e expõem `node.rendered_entity` (HTML completo do nó) e `field_address_data`. | Campos das views; display `rest_export` sem override de `access` | Endpoint próprio com campos mínimos, sem HTML renderizado. Endereço de local só com decisão de LGPD (ver T9) |
| S5 | Média | `openculturas_map/config/install/views.view.related_dates_export.yml` (`admin/content/date-export`) | A exportação CSV, JSON e XML inclui `field_email` e `field_phone` (parágrafos de contato) e endereço. O acesso é por papel, com autenticação por cookie. Exportação de dado pessoal sem minimização. | Campos `paragraph__field_email`, `paragraph__field_phone`; displays `data_export_*` com `auth: cookie` | Não copiar. Se houver exportação, mover para `/painel-administrativo/...` (regra de admin do Aculta), com campos mínimos e registro de uso |
| S6 | Média | `openculturas_map/js/openculturas_map.js` (linhas 1619–1651) | Tiles do OpenStreetMap e controle de geocodificação (Nominatim) são chamados pelo navegador do visitante, com IP e referrer. | `L.tileLayer('https://tile.openstreetmap.org/…')`, `L.Control.geocoder` | Substituído por Google Maps (D7). Mapa só após ação do usuário, com aviso de privacidade |
| S7 | Baixa | `openculturas_calendar_widget/src/Controller/…`, `<base target="_blank">` | Todos os links dentro do iframe abrem em nova aba, sem aviso ao usuário. | `'#attributes' => ['target' => '_blank']` | Não usar `target` global. Aviso visível se houver nova aba (WCAG 3.2.5) |
| S8 | Baixa | `openculturas_calendar_widget/js/openculturas_calendar_widget.js` | `navigator.clipboard.writeText()` sem tratamento de erro (permissão negada gera exceção não tratada). | Chamada sem `.catch` | Tratar a falha com mensagem e o campo selecionado como alternativa |

Positivo: `EmbedCodeWidget` coloca o código de incorporação em `textarea`, não em HTML renderizado,
então o risco de XSS ali é baixo.

## 3. Achados técnicos

| ID | Severidade | Onde | Achado | Ação |
| --- | --- | --- | --- | --- |
| T1 | **Alta** | `openculturas_map.info.yml` e `OpenculturasMapHooks.php` | O módulo usa `Drupal\openculturas_custom\GetConfiguredMapMaker`, mas não declara `openculturas_custom` como dependência. Declara `openculturas:openculturas_slimselect_bef` (módulo fora desta cópia). Usa `smart_date`, `media`, `image` e `file` sem declará-los. | Não copiar o módulo com essa estrutura. Toda dependência vai para o `info.yml` do submódulo ACULTA |
| T2 | **Alta** | `openculturas_custom/openculturas_custom.info.yml` | O módulo usa ao menos 12 módulos não declarados: `address`, `addtocal_augment`, `geofield`, `paragraphs`, `extra_field`, `field_group`, `leaflet_views`, `shariff`, `layout_builder`, `update_helper_checklist`, `default_content`, `config_devel`. Não é viável copiar o módulo inteiro. | Copiar só os hooks listados em §5, reescritos. Não copiar `openculturas_custom` como módulo |
| T3 | Média | `OpenculturasCustomDateEventHooks.php` | Acoplamento a nomes fixos: bundles `event`, `date`, `profile`; campos `field_people_reference`, `field_event_description`; view `related_date` com display `related_date_alternative`; flags `bookmark_node` e `recommendation_node`. | Mapear para os nomes do ACULTA (D4) antes de qualquer reuso |
| T4 | Média | `openculturas_map/js/openculturas_map.js`, linhas 764–766 | XHR síncrono (`request.open("GET", uri, false)`). Bloqueia a thread principal e está descontinuado no navegador. | Não copiar. Usar `fetch` assíncrono com `AbortController` |
| T5 | Média | `SmartDateUpcomingFormatter.php`; `OpenculturasMapHooks::cron()` | O formatador remove datas passadas com `time()`, mas não declara `max-age` nem contexto de tempo. Por isso o OpenCulturas invalida `config:views.view.oc_map_dates` por cron diário. Entre a passagem da data e a invalidação, o cache serve dados antigos. | Declarar `max-age` até o próximo início de data, ou filtrar na consulta com contexto de cache adequado. Sem cron de invalidação |
| T6 | Baixa | `SmartDateUpcomingFormatter.php`, linha `$element['#value'] < time()` | Assume que `#value` é um timestamp. Não é possível confirmar sem o `smart_date` instalado. | Verificar com o `smart_date` do ACULTA antes de reusar |
| T7 | Baixa | `openculturas_map.js` | 1.897 linhas, 9 chamadas `console.debug` e a flag `development_mode` (padrão `false`) que muda as credenciais do `fetch`. | Não copiar. Se o padrão for mantido, sem flag de desenvolvimento no código de produção |
| T8 | Baixa | `openculturas_map.js`, `_htmlToNode` e `innerHTML` | Insere HTML vindo do servidor (`rendered_entity`). Seguro só se o PHP escapar o conteúdo. | Eliminar com o endpoint mínimo (S4) |
| T9 | Média | `OpenCulturasCustomLocationPrecisionHooks.php` e `field_location_precision` | **Correção ao plano anterior.** O campo tem valores `auto` e `manual`. `manual` significa área desenhada à mão no mapa, para locais que cobrem vários municípios. Não é regra de privacidade de endereço. Depende de `geocoder_field` e `geofield`. | Não portar. Se o ACULTA precisar de precisão de endereço (residência), é campo novo, com regra definida pelo responsável |
| T10 | Média | `AddToCal.php` (herda de `addtocal_augment`) | Classe herdada de contrib, com sobrescrita de métodos internos. Qualquer atualização do contrib pode quebrar a sobrescrita. | Não copiar. ICS próprio (D8), sem `addtocal_augment` |
| T11 | Média | Todos os candidatos | Nenhum arquivo tem teste automatizado nem cabeçalho de licença por arquivo (`@license`). A atribuição fica só no `LICENSE.txt` do pacote. | Teste Kernel/Unit para cada hook reescrito. Atribuição por arquivo derivado (ver `ORIGEM-OPENCULTURAS.md`) |
| T12 | Baixa | `GetConfiguredMapMaker.php` | Fallback para o tema `openculturas_base` (`images/map_marker.svg`). Acopla o helper a um tema que o ACULTA não usa. | Não copiar |
| T13 | Baixa | `OpenculturasCustomLeafletHooks.php` | Depende de `leaflet_views` e de `GeofieldProximityFilter`. Ligado ao mapa Leaflet, que não entra (D7). | Não copiar |
| T14 | Baixa | Strings de interface | Textos em inglês fixos no PHP: "Visit the location's profile", "See all about", "Save and add date", "Upcoming dates", "Copied text" (no JS). | Traduzir com `t()` e revisar em português (regra do Aculta) |

Positivo: o código usa APIs atuais do Drupal 11 (uso moderado de `\Drupal::`, com 7 ocorrências nos
candidatos; `once()` nos JS; sem jQuery, sem `drupal_set_message` nem `entity_load()`). Isso reduz o trabalho de migração.

## 4. Achados de visual

| ID | Severidade | Onde | Achado | Ação |
| --- | --- | --- | --- | --- |
| V1 | Média | Templates e SCSS (`url(../sprite/oc-sprite.svg#map-pin-light)` etc.) | Ícones do sprite Phosphor (MIT) e sprite do Gin para o admin. O ACULTA usa Bootstrap Icons. | Remapear os ícones. Se o ACULTA adotar Phosphor, incluir o aviso MIT |
| V2 | Baixa | `themes/opcult/fonts`, `sass/fonts.scss` | Montserrat e OpenSans. O ACULTA usa Oswald e Inter. | Não copiar |
| V3 | Baixa | Classes `openculturas-map--*`, `view-related-date`, `view-locations` | Prefixo do OpenCulturas. | Renomear para o padrão do `aculta420` |
| V4 | Média | Templates `node--date--teaser*.html.twig` | Estrutura de cartão acoplada ao tema `opcult` (classes do tema e regras de `field/date.scss`). | Recriar como SDC (`content/event-card`, `content/event-date-chip`, conforme `VISUAL.md`) |

Os templates de Twig e SCSS não aproveitam o contrato do `aculta420` (SDC, tokens `--aculta-*`,
modo escuro por tokens). Servem de referência de estrutura, nunca de cópia.

## 5. Decisão por arquivo

| Arquivo ou grupo | Decisão | Motivo | Substituto no ACULTA |
| --- | --- | --- | --- |
| `openculturas_map/src/Plugin/Block/OpenCulturasMapBlock.php` | Não copiar | T1, D7 | Bloco de mapa com Google Maps (`aculta_events`) |
| `openculturas_map/js/*.js` (2.132 linhas) | Não copiar | T4, T7, S6, D7 | JavaScript próprio, Maps JavaScript API |
| `openculturas_map/src/Plugin/Form/OpenCulturasMapFilterForm.php` | Referência | Lógica de filtros útil | Filtros de catálogo em `aculta_events` |
| `openculturas_map/src/Form/SettingsForm.php` (410 linhas) | Não copiar | Configuração de tiles e geocodificação do OSM | Configuração do Google (chave por ambiente) |
| `openculturas_map/src/Plugin/Field/FieldFormatter/SmartDateUpcomingFormatter.php` | Reescrever | T5, T6 | Formatador com `max-age` correto |
| `openculturas_map/src/Plugin/Field/FieldFormatter/ImageThumbnailUrlFormatter.php` | Reescrever | Usa `ImageStyle::load('thumbnail')` fixo | Image style do ACULTA (`aculta_image`) |
| `openculturas_map/config/install/views.view.oc_map_*.yml` | Referência | S4 nos displays REST | Views com campos mínimos e endpoint próprio |
| `openculturas_map/openculturas_map.libraries.yml`, `.install`, `.routing.yml` | Não copiar | Dependem de Leaflet e de OSM | — |
| `openculturas_calendar_widget/src/Controller/…Controller.php` | Reescrever | S3, S7 | Rota `/calendario/incorporar` no `aculta_calendar` |
| `openculturas_calendar_widget/src/Form/EmbedCodeWidget.php` | Reescrever | Conceito útil (código copiável) | Componente com textarea e botão de cópia com tratamento de erro (S8) |
| `openculturas_calendar_widget/js/…js` | Reescrever | S8 | JS de cópia do ACULTA |
| `openculturas_calendar_widget/templates/…html.twig` | Reescrever | Visual do tema `opcult` | Layout de embed em `aculta420` |
| `openculturas_calendar_widget/config/schema/…yml` | Reescrever | Schema de `host_list` e `limit_access` | Schema próprio, com validação de host |
| `openculturas_custom/src/Hook/OpenculturasCustomDateEventHooks.php` | Reescrever só `nodeViewAlter` e `viewsPostExecute` (delta de datas), após reescrita de T3 | S1 em `nodePrepareForm` | Lógica de ocorrências no `aculta_events` |
| `OpenculturasCustomDateEventHooks::nodePrepareForm()` | **Não copiar** | S1 | Pré-preenchimento com verificação de acesso, se for necessário |
| `OpenculturasCustomDateEventHooks::formNodeFormAlter()` | Avaliar | Botão "Salvar e adicionar data" (UX de cadastro) | Decidir na F2 |
| `openculturas_custom/src/Hook/OpenculturasCustomLocationPrecisionHooks.php` | **Não copiar** | T9 | — |
| `openculturas_custom/src/Plugin/DateAugmenter/AddToCal.php` | **Não copiar** | S2, T10 | Gerador de `.ics` próprio (`aculta_calendar`) |
| `openculturas_custom/src/Hook/OpenculturasCustomLeafletHooks.php` | **Não copiar** | T13 | — |
| `openculturas_custom/src/GetConfiguredMapMaker.php` | **Não copiar** | T12 | — |
| `themes/opcult/templates/node/node--date*.twig`, `node--date.scss` | Referência | V4 | SDC de cartão de evento |
| `themes/opcult/templates/views/views-view--locations.html.twig`, `views-view--related-date.html.twig`, `view-*.scss` | Referência | V4 | SDC de lista de locais e agenda |
| `themes/opcult/sprite/` (2.910 SVG) | Não copiar | V1 | Bootstrap Icons, se o ACULTA adotar |
| `themes/opcult/fonts/`, `openculturas_base/fonts/` | **Não copiar** | V2 | Oswald e Inter do `aculta420` |
| `profile/config/install/node.type.event|date|location.yml` | Reescrever | C3 | Tipos `aculta_event`, `aculta_event_date`, `aculta_event_location` (D4) |
| `profile/config/install/field.field.node.date.field_pricing*.yml`, `field_ticketing*.yml`, `field.field.node.event.field_is_bookable*.yml`, `paragraphs.paragraphs_type.bookable_event.yml` | **Não copiar** | Venda e ingressos fora da v1 | — |
| `profile/config/install/views.view.*` (catálogo, locais, datas) | Referência | C1 | Views novas em `aculta_events` e `aculta_calendar` |

## 6. Dependências

Dependências que o reuso exige, em ordem de substituição:

| Módulo | Origem | Uso | Decisão |
| --- | --- | --- | --- |
| `smart_date` | Contrib | Datas e horários | Avaliar compatibilidade com Core ^11.4 (T6) |
| `geofield` | Contrib | Coordenadas | Avaliar. Usado também no filtro de proximidade |
| `address` | Contrib | Endereço | Verificar se o ACULTA já tem endereço (`aculta_portal_cep`) |
| `paragraphs` | Contrib | Parágrafos de endereço e de pessoa | Avaliar. Hoje o ACULTA usa Entity Reference Revisions? Verificar antes de F2 |
| `media`, `image`, `file` | Core | Imagens do evento | Usar `aculta_image` |
| `addtocal_augment` | Contrib | `.ics` | **Não usar** (T10, D8) |
| `leaflet`, `leaflet_views`, `leaflet_markercluster` | Contrib | Mapa | **Não usar** (D7) |
| `geocoder`, `geocoder_field` | Contrib | Geocodificação | **Não usar** para o mapa. Google Geocoding API no servidor (D7) |
| `openculturas_slimselect_bef`, `openculturas_slimselect` | OpenCulturas | Seleção de filtros | **Não usar** |
| `openculturas_openstreetmap`, `key`, `oauth2_client` | OpenCulturas | OSM | **Não usar** (fora da v1) |
| `flag` | Contrib | Favoritos e recomendações | Fora da v1 (verificar com `aculta_user`) |
| `eca` | Contrib | Automação | Não usar sem decisão |
| `pathauto` | Contrib | URLs | Avaliar. O ACULTA controla slugs em português (`FRIENDLY-PORTUGUESE-SLUGS.md`) |

## 7. Plano de reuso (ordem)

1. Antes de qualquer código: decidir o ADR da exceção de privacidade do Google Maps (D7) e a
   regra de endereço de residência (T9).
2. Corrigir a fundação: dependências declaradas, sem `openculturas_custom` (T1, T2).
3. Modelo de dados em `aculta_events` (C3, D4) sem campos de venda (C2).
4. Hooks reescritos com teste de acesso negado (S1) e datas com `max-age` (T5).
5. Gerador de `.ics` próprio, com escape (S2), em `aculta_calendar`.
6. Endpoints mínimos (S4) e exportação administrativa sob `/painel-administrativo` (S5), se entrar.
7. Visual em SDC (V1–V4), com modo escuro e 320 px.

## 8. Lacunas desta auditoria

- Não foi instalado o `smart_date` nem outros contribs: T6 e a compatibilidade com Core ^11.4 ficam
  para a fase F0.
- A segurança de S1 e S2 foi avaliada pela leitura do código. Ainda não há teste de reprodução. Cada
  um deve virar teste automatizado antes de qualquer reuso.
- A auditoria não avaliou o `openculturas_content` (conteúdo de exemplo) nem o `openculturas_discussions`,
  `openculturas_faq`, `openculturas_section`, `openculturas_teaser`, `openculturas_address_links` e
  `openculturas_media`, porque estão fora do escopo de eventos, calendário e locais.
- Não foi verificada a licença de cada asset binário (imagens de `openculturas_map/assets/`); o sprite
  é Phosphor (MIT), conforme o próprio pacote.
