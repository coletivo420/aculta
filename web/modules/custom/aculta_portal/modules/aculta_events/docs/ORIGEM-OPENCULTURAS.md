# Origem e licenciamento — OpenCulturas

Fonte: OpenCulturas 3.1.4, distribuição Drupal (`openculturas/openculturas-distribution`).
Referência local: `/home/piradopirata/projetos/aculta-openculturas/` (somente leitura).

| Item | Valor |
| --- | --- |
| Arquivo recebido | `openculturas-3.1.4.tar.gz` |
| SHA-256 | `d2e1c5ecdf7c3aa104b55fea9687d901b14730f7ded0a0dbfb51477722de051d` |
| Licença do projeto | GPL-2.0-or-later (`LICENSE.txt`; `composer.json`) |
| Ícones | Phosphor Icons (MIT), `profile/themes/opcult/sprite/svg/LICENSE_phosphor-icons.md` |
| Fontes | Montserrat e OpenSans no pacote; não serão usadas (o ACULTA usa Oswald e Inter) |

## Regras de cópia

1. **Copiar e adaptar.** Nunca copiar um arquivo inteiro do tema `opcult` para o `aculta420`.
2. **Preservar avisos.** Se o arquivo de origem tiver cabeçalho de copyright ou licença, ele
   permanece. Arquivos novos derivados recebem a linha: `Adaptado de OpenCulturas 3.1.4
   (GPL-2.0-or-later), profile/<caminho>`.
3. **Registrar cada cópia** na tabela abaixo: caminho de origem, destino, alterações e data.
4. **Não copiar ativos de terceiros** sem a licença do próprio ativo (fontes, imagens de exemplo).
5. **Compatibilidade:** GPL-2.0-or-later é compatível com o `LICENSE` (GPLv3) do repositório. A
   classificação não substitui parecer jurídico; a revisão está prevista na fase F9.

## Mapa de cópia (preenchido à medida que as fases avançam)

| Origem (`profile/…`) | Destino ACULTA | Tipo | Alterações | Data |
| --- | --- | --- | --- | --- |
| — | — | — | Nenhuma cópia feita até a fase F0 | — |
| `profile/modules/custom/openculturas_faq/config/install/node.type.faq.yml`, `config/optional/field.field.node.faq.body.yml`, `config/optional/views.view.faq.yml` | `aculta_portal/modules/aculta_faq` (novo submódulo) | Reescrita como FAQ em português e bloco editorial no Drupal 11; texto limitado por formato com filtro HTML; sem Twig/CSS da origem | 2026-10-28 |
| `profile/modules/custom/openculturas_custom/src/Plugin/DateAugmenter/AddToCal.php` (só regra, nenhum trecho copiado) | `aculta_portal/modules/aculta_calendar/src/Ics/IcsEventBuilder.php` | Reescrita do zero (E6, C1) | Gerador `.ics` próprio: escape RFC 5545, CR/LF viram espaço em SUMMARY e LOCATION (S2), UID estável, dobra em 75 octetos, DTSTART/DTEND no horário de Brasília (TZID com VTIMEZONE), DTSTAMP em UTC. Sem `addtocal_augment` (T10). Sem atribuição, pois nenhum trecho foi copiado | 2026-10-28 |
| `profile/modules/custom/openculturas_calendar_widget/src/Controller/OpenculturasCalendarWidgetController.php` (só regra do S3, nenhum trecho copiado) | `aculta_portal/modules/aculta_calendar/src/Embed/EmbedSourceAllowlist.php` | Reescrita do zero (E5, C2) | Lógica de allowlist do `source_uri`: caminho interno ou https de host da lista; recusa javascript:, data:, http, protocolo relativo, credencial e porta fora do padrão (S3). Sem rota, controller ou bloco nesta fase. Sem atribuição, pois nenhum trecho foi copiado | 2026-10-28 |
| `profile/modules/custom/openculturas_map/src/Plugin/Field/FieldFormatter/SmartDateUpcomingFormatter.php` | `aculta_portal/modules/aculta_events/src/Plugin/Field/FieldFormatter/DateRangeUpcomingFormatter.php` | Adaptado (GPL-2.0-or-later; cabeçalho no arquivo) | Formatter de datas futuras sobre `daterange` do core (sem smart_date). Sem cron de invalidação: o cache expira no fim da próxima data (T5). Formato dd/mm/aaaa HH:mm em Brasília. Teste Kernel: DateRangeUpcomingFormatterKernelTest, 3 ok. | 2026-10-28 |
| `profile/modules/custom/openculturas_custom/src/Hook/OpenculturasCustomDateEventHooks.php` (`nodeViewAlter` e `viewsPostExecute`, só comportamento) | `aculta_portal/modules/aculta_events/src/Hook/EventDateHooks.php` e `src/Occurrence/UpcomingDates.php` | Reescrita do zero (E3) | Data vencida sai da listagem; cache expira no fim da próxima data, sem cron (T5). `viewsPostExecute` virou `#[Hook('views_post_execute')]`; a regra de `nodeViewAlter` vive nos controllers, porque a página do ACULTA não é entity view. Listagem administrativa não é filtrada. `nodePrepareForm` (S1) não foi copiado, nem a clonagem de parágrafos. Sem atribuição, pois nenhum trecho foi copiado | 2026-10-28 |
| `profile/modules/custom/openculturas_map/` (só conceito de lista e filtro de locais; nenhum trecho de código copiado) | `aculta_portal/modules/aculta_maps/src/MapRenderer.php`, `src/Geocoding/GeocodingService.php`, `src/RouteLinkBuilder.php`; `aculta_events/src/Controller/MapController.php` | Reescrita do zero (E7/MP0–MP7) | Implementação própria com Leaflet/OSM padrão, Google opcional (ADR-030), atribuição OSM e coordenadas aproximadas arredondadas. Sem atribuição em código, pois não houve cópia de trecho | 2026-10-28 |
| `profile/config/install/taxonomy.vocabulary.location_type.yml` (dados do vocabulário, sem código) | `aculta_portal/modules/aculta_events/aculta_events.install` (update 10003) e vocabulário `aculta_location_type` | Dados portados (E1) | Os 17 tipos de local do OpenCulturas, traduzidos para português; nomes de máquina novos. Nenhum código copiado | 2026-10-28 |
| `profile/config/install/views.view.files.yml` (conceito de uso de arquivo) | `aculta_portal/modules/aculta_image/src/Controller/ImageFileUsageController.php` | Reescrita somente leitura (ADM3) | File Usage API do Drupal; exibe apenas entidades acessíveis e não altera upload, limpeza ou quarentena | 2026-10-28 |
| `profile/config/install/views.view.locations.yml` (conceito de listagem de locais) | `aculta_portal/modules/aculta_events/config/install/views.view.eventos_locais.yml` | View nova, reimplementada com acesso por papel | Página administrativa `/painel-administrativo/eventos/locais`; somente nome do local; acesso restrito ao papel administrator. Adaptado sem filtro `pre_render` | 2026-10-28 |
| `profile/config/install/views.view.related_dates_archive.yml` (conceito de datas relacionadas) | `aculta_portal/modules/aculta_events/config/install/views.view.eventos_datas_relacionadas.yml` | View nova, reimplementada com acesso por papel | Página administrativa `/painel-administrativo/eventos/datas-relacionadas`; título e intervalo da data; sem contato/endereço e sem filtro `pre_render`. Exportação de datas depende do provedor ADR-031 e não foi duplicada | 2026-10-28 |
| `profile/config/install/field.storage.node.field_pricing.yml`, `field_pricing_info.yml` e `field_ticketing.yml` | `aculta_portal/modules/aculta_events/src/Entity/EventDate.php`, `aculta_events.install`, `docs/MODELO-DE-DADOS.md` | Campos reescritos/adaptados (E1, D-29) | Preço e detalhes como texto simples, compra/reserva por link externo e indicador gratuito; sem pagamentos, scripts ou código do OpenCulturas | 2026-10-28 |
| `profile/modules/custom/openculturas_openstreetmap/src/OpenStreetMap/ApiClient.php` (conceito de OAuth e API) | `aculta_portal/modules/aculta_events_openstreetmap/src/OAuth/OpenStreetMapOAuthClient.php` | Reescrito (E10-A); cabeçalho de atribuição no arquivo | Cliente OAuth2 ACULTA com endpoint fixo por ambiente, `state` de uso único, timeouts, sem redirecionamento HTTP e token cifrado em State; sem credenciais versionadas | 2026-10-28 |
| `profile/modules/custom/openculturas_openstreetmap/src/OpenStreetMap/ApiClient.php` (conceito de API XML) | `aculta_portal/modules/aculta_events_openstreetmap/src/Osm/OpenStreetMapApiClient.php` | Reescrito (E10-B); cabeçalho de atribuição no arquivo | API de leitura/escrita por endpoint fixo do ambiente ativo; XML processado sem rede, coordenadas validadas, tags em allowlist sem telefone/e-mail, comentário de changeset obrigatório, revisão de versão e encerramento do changeset | 2026-10-28 |
| `profile/modules/custom/openculturas_openstreetmap/src/Wizard/OSMFormWizard.php` | `aculta_portal/modules/aculta_events_openstreetmap/src/Form/OpenStreetMapImportWizard.php` | Reescrito (E10-B); cabeçalho de atribuição no arquivo | Fluxo Find → Select → Review → Save em Form API, sem wizard/JS contrib; endpoint ativo e ID digitado, revisão editável e confirmação explícita de envio | 2026-10-28 |
| `profile/modules/custom/openculturas_openstreetmap/src/Form/OSM/FindLocation.php` | `aculta_portal/modules/aculta_events_openstreetmap/src/Form/OpenStreetMapImportWizard.php` | Reescrito (E10-B); cabeçalho de atribuição no arquivo | Busca direta por tipo e ID na API do ambiente ativo; sem consulta Photon/Nominatim, evitando cruzar IDs de produção e desenvolvimento | 2026-10-28 |
| `profile/modules/custom/openculturas_openstreetmap/src/Form/OSM/SelectDataToCopy.php` | `aculta_portal/modules/aculta_events_openstreetmap/src/Form/OpenStreetMapImportWizard.php` | Reescrito (E10-B); cabeçalho de atribuição no arquivo | Seleção de nome, endereço, horário e coordenadas; allowlist exclui dados de contato | 2026-10-28 |
| `profile/modules/custom/openculturas_openstreetmap/src/Form/OSM/ReviewData.php` | `aculta_portal/modules/aculta_events_openstreetmap/src/Form/OpenStreetMapImportWizard.php` | Reescrito (E10-B); cabeçalho de atribuição no arquivo | Campos revisáveis, comentário obrigatório e checkbox de confirmação antes de qualquer escrita; link de copyright/licença OSM exibido | 2026-10-28 |
| `profile/modules/custom/openculturas_openstreetmap/src/Form/OSM/PushData.php` | `aculta_portal/modules/aculta_events_openstreetmap/src/Form/OpenStreetMapImportWizard.php` | Reescrito (E10-B); cabeçalho de atribuição no arquivo | Envia somente diferenças de tags permitidas ao endpoint ativo; sem contato, credencial ou URL livre; nenhum envio real nesta execução | 2026-10-28 |

## Diferença de licença com o AGPL

O `aculta_links` decidiu não copiar código do LinkStack por ser AGPL-3.0. O OpenCulturas não é AGPL:
o código dele é GPL-2.0-or-later, e a regra do `aculta_links` não se aplica a ele. Ainda assim,
cada cópia precisa do registro acima.

## Documentação copiada

`upstream-openculturas/` contém cópia de `README.md`, `DEVELOPMENT.md`, `CONTRIBUTING.md`,
`SECURITY.md`, `CHANGELOG.md` e `LICENSE.txt`, sem alteração de conteúdo.
