# Nova auditoria de incorporação: OpenCulturas 3.1.5 → ACULTA

**Data:** 2026-10-29. **Estado:** auditoria estática concluída e incorporação documental aprovada pelo responsável. **Sem alterações de código, dependências, Config Sync ou Runtime nesta rodada.**

## 1. Fontes, reprodutibilidade e limites

- Origem material: arquivo fornecido `openculturas-3.1.5.tar.gz`, SHA-256 `2cc0a34293e3cd7ccff633d07d61844afc1cec752d506a955c49a319fcbbb406`.
- Base de origem: `openculturas/profile/` (arquivo traz `openculturas/` como raiz); licença declarada em `composer.json`: GPL-2.0-or-later; `profile/openculturas.info.yml` identifica 3.1.5 e `core_version_requirement: ^11`.
- Destino: PR [#299](https://github.com/coletivo420/aculta/pull/299), branch `refactor/opencultures-codigo-visual`, auditada a partir do HEAD `64ae22076a11ce2189ed6efd591d5fe3731b18f1`.
- Método: inventário dos nomes de arquivos YAML/PHP no tarball, inspeção de configurações e classes selecionadas; comparação com `composer.json`, `config/sync`, entidades e controllers, SDCs e roadmaps do ACULTA no GitHub.
- Limitações: **não** foi executado Drupal/Drush, PHPUnit, navegador, banco ou diff linha a linha de todos os arquivos do tarball. Presença de arquivo ≠ módulo habilitado, consumidor ligado ou comportamento homologado. Não confundir estado histórico dos roadmaps com comprovação atual no Runtime.

## 2. Inventário verificado do OpenCulturas 3.1.5

| Tipo | Encontrado em `profile/` | Observação |
| --- | ---: | --- |
| Módulos próprios | 14 | `profile/modules/custom/` |
| Views em `config/install` | 42 | **7 desabilitadas**: `who_s_new`, `who_s_online`, `frontpage`, `archive`, `content_recent`, `taxonomy_term`, `glossary` |
| Tipos de parágrafo | 19 | Todos presentes em `paragraphs.paragraphs_type.*` |
| Tipos de nó | 6 | `date`, `location`, `article`, `event`, `page`, `profile` |
| Tipos de mídia | 7 | `image`, `document`, `audio`, `remote_video`, `sponsor`, `logo_image`, `user_profile_picture` |
| Vocabulários | 9 | Em `taxonomy.vocabulary.*` |
| Facetas | 3 | Acessibilidade, categoria/foco e subtipo |
| Flags | 5 | Denúncia, bookmarks de nó/termo, recomendação e reivindicação |
| Workflows | 3 | `draften_member`, `draften`, `magazine_article` |
| Processos ECA | 4 | 4 `eca.eca.process_*.yml` |

**Retificações obrigatórias:** o levantamento anterior mencionava 48 Views, 11 vocabulários e 4 workflows; **não** são os números deste tarball. `views.view.archive.yml` e `views.view.glossary.yml` existem, mas estão com `status: false`. `views.view.oc_map_dates.yml` **não existe** em `config/install` do arquivo 3.1.5. Não atribuir a este pacote código/fields específicos de declaração de uso de IA nem funcionalidade de edição de polígonos sem prova em arquivos concretos. São ideias exploratórias, não paridade confirmada desta versão.

## 3. Matriz de evidências: Aculta atual x origem

| ID | Evidência concreta OpenCulturas 3.1.5 | Evidência no ACULTA | Estado e próxima ação |
| --- | --- | --- | --- |
| OC315-01 | `search_api.index.content.yml`, `views.view.search.yml` e 3 `facets.facet.*`; índice usa `rendered_item` com view mode `search_index` | `search_api.index.aculta_conteudo.yml` indexa nós `activity`, `revista`, `document`, `page`, `wiki_entry`; Fórum usa índice próprio. `aculta_event` e `aculta_event_location` não são fontes desse índice | **P0, não incorporado:** descoberta cultural interoperável; avaliar um índice/projeção que preserve grants e purpose, facetas só após prova de necessidade |
| OC315-02 | `views.view.event_catalogue.yml` e `views.view.locations.yml` têm filtros/paginadores; a View de locais possui attachment de mapa | `EventCatalogue::busca()` carrega eventos, expande datas e ordena em PHP; `LocationListController` entrega lista textual | **P0, melhorar:** custo/limite/paginação na consulta; benchmark, cache e testes de volume |
| OC315-03 | `node.type.location` e campos estruturados de horário/acessibilidade | `EventLocation::field_horario` é `string_long`, não Office Hours; `content/accessibility-badges` é SDC experimental sem consumer real | **P1, parcial:** campo estruturado, exceções, texto adicional, a11y e dados consumidos por busca/cartão |
| OC315-04 | `field_attendance_mode`, `field_location_virtual`, `field_min_age`, `field_safety_info`, `field_door_time_relative`, `field_event_status` na data de evento | `EventDate` já tem data, gratuidade, preço, link, recorrência; `Event` tem status simples | **P1, campos não equivalentes:** desenhar aditivo de dados sem mudar contratos atuais; migração idempotente |
| OC315-05 | `flag.flag.claim_ownership.yml`, `field_allow_claiming`, `field_main_profile`, perfis/organizações | `OwnershipClaimService`, `OwnershipClaimAdminController` (`/painel-administrativo/pessoas/reivindicacoes`) e formulário de decisão em `aculta_user`; D-01 preservado sem papel genérico `organizador` | **Implementado e coberto:** vinculação por moderação, padrão fechado, escopo `content_editor`; Kernel `OrganizationClaimKernelTest` PASS (6 testes, 120 asserções). |
| OC315-06 | `views.view.my_bookmarks.yml`, `my_recommendations`, `related_*` | Flags e `aculta_index` integrados; lista de salvos e recomendações no Studio (`SavedController`, `MarleyListingBuilder`) respeitando propósito | **Implementado e coberto:** lista privada por UID, relations por índice sem criar armazenamento paralelo; Kernel `SavedAndRecommendationsKernelTest` PASS (8 testes, 153 asserções). |
| OC315-07 | Denúncia via Flag + ECA, lista `flagged_content` | Módulo `aculta_ugc` com painel central `/painel-administrativo/moderacao/denuncias` e decisão individual/em lote (`UgcReportDecisionForm`); destinatários por purpose (D-03) sem ECA | **Implementado e coberto:** fila unificada, sem vazamento de autor (D-03), adapters e moderação por propósito; Kernel `UgcModerationKernelTest` PASS (5 testes, 93 asserções; suíte UGC 38/38 PASS). |
| OC315-08 | `paragraphs.paragraphs_type.media_mention.yml`, `field_supporters`, `views.view.related_sponsor.yml` | `aculta_magazine` único bundle `revista`; diretriz ARQ-08 substitui necessidade de entidades/parágrafos novos | **Definido e coberto:** modelo editorial enxuto sem duplicação de entidades ou parágrafos de imprensa/patrocinadores; Kernel `FaqEditorialSafetyKernelTest` e `RevistaTabTest` PASS. |
| OC315-09 | `openculturas_media/src/Service/ReadableMime.php` e `Plugin/Field/FieldFormatter/DownloadWithMetaFormatter.php` | `ReadableMime` e `DownloadWithMetaFormatter` em `aculta_document_viewer` e `aculta_image` | **Implementado e coberto:** metadados de formato, tamanho em PT-BR e rótulo acessível para leitores de tela nos downloads; Unit `ReadableMimeTest` PASS (3 testes, 15 asserções). |
| OC315-10 | `openculturas_teaser/src/Plugin/paragraphs/Behavior/TeaserBehaviorBase.php` | `patterns/card`, destaques editoriais, UI Patterns 2 | **P2, adaptar conceito:** override editorial de teaser sem copiar framework Paragraphs |
| OC315-11 | `field.storage.node.field_office_hours.yml`; `OfficeHoursToOsmFormatter.php` | `aculta_maps` e integração OSM presentes; horário local em texto livre | **P2, dependente de OC315-03:** estudo de formatação OSM com licença/política e acesso revisados |
| OC315-12 | `views.view.archive.yml`, `glossary.yml`, `content_recent.yml` **desabilitadas na origem** | Revista e Wiki têm Views próprias | **P2, referência opcional:** novos displays apenas quando houver uso; nada de importação direta |
| OC315-13 | `openculturas_custom/src/TermPathBasedBreadcrumbBuilder.php` | `AcultaBreadcrumbBuilder::taxonomyParents()` já existe | **Já há alternativa:** teste comparativo; não duplicar serviço |
| OC315-14 | SDCs `event-page-five-regions`, `accessibility-badges`, `sponsor-logos`, `bookmark-toggle` existem no Aculta | `EventPageController` ainda retorna `#theme => aculta_event_page`; Twig inclui H1 próprio; componentes novos não estão todos conectados | **P1 visual:** consumir SDC real, corrigir título duplicado com checagem de origem do H1, WCAG + teclado + zoom |
| OC315-15 | O OpenCulturas possui biblioteca de mídia e campos `field_licenses` | Aculta tem Image, Gallery, políticas próprias | **P2 opcional:** metadados de crédito/autoria, sem tornar licença obrigatória (D-10) |
| OC315-16 | Consultas de mapa de locais com filtro de proximidade | `LocationCatalogue::busca()` usa `accessCheck(FALSE)`, retorna entidades sem `access('view')` explícito; `MapController` usa `!empty()` em lat/lon | **P0 revisão de segurança:** decidir publicidade integral de locais, teste Kernel de acesso; trocar checagem de coordenadas por validação numérica (0 é válido) |

## 4. Arquivos upstream candidatos (verificar dependências antes de qualquer cópia)

1. `profile/modules/custom/openculturas_media/src/Service/ReadableMime.php` e `src/Plugin/Field/FieldFormatter/DownloadWithMetaFormatter.php`: normalização de MIME e apresentação de download; adaptar a Document Viewer sem enfraquecer o acesso.
2. `profile/modules/custom/openculturas_custom/src/Entity/EcaNotificationRecipient.php`: estrutura de entidade de configuração como **referência**, sem dependência ECA; destinatários de denúncia por purpose.
3. `profile/modules/custom/openculturas_custom/src/Plugin/ExtraField/Display/EventSupporters.php` e `EventPressQuotes.php`: composição de apoiadores/imprensa com dados validados.
4. `profile/modules/custom/openculturas_teaser/src/Plugin/paragraphs/Behavior/TeaserBehaviorBase.php`: conceito de override de teaser editorial; evitar acoplamento com Paragraphs se não for necessário.
5. `profile/modules/custom/openculturas_openstreetmap/src/OpenStreetMap/OfficeHoursToOsmFormatter.php`: conversão após aprovação do horário estruturado.
6. `profile/modules/custom/openculturas_map/src/Plugin/Form/OpenCulturasMapFilterForm.php`: desenho de filtros, não importar o módulo inteiro.
7. `profile/modules/custom/openculturas_custom/src/TermPathBasedBreadcrumbBuilder.php`: comparar antes de portar (alternativa ACULTA existente).

**Regra de cópia:** não copiar arquivos inteiros ou ativos de terceiros cegamente. Primeiro contrato/tipo, rastrear licença, implementar versão Drupal 11+/PHP 8.4 com DI, access/cache tests, registrar origem e revisão em `aculta_events/docs/ORIGEM-OPENCULTURAS.md` **se houver trecho derivado**. Mantidas decisões D-01, D-03, D-10, D-11, D-13/D-14, D-17 a D-20 e D-45.

## 5. Plano aprovado e critérios de aceite

| Onda | Itens | Dono lógico | Gate de saída |
| --- | --- | --- | --- |
| OC-A, P0 | OC315-01, 02, 16 | Index, Events, Portal | matriz acesso 2 contas + purposes; paginação real; benchmark no volume definido; nenhum vazamento no mapa |
| OC-B, P1 | OC315-03, 04, 14 | Events, Maps, ACULTA420 | esquema/migração idempotentes, consumer real por dado, WCAG 2.2 AA, teclado, zoom 200%, 320 px |
| OC-C, P1 | OC315-05, 06, 07 | User, Studio, UGC, Mailer | vínculos aprovados, ownership não transferido automaticamente, fila única, cache A/B |
| OC-D, P2 | OC315-08, 09, 10, 12, 15 | Magazine, Image, Document Viewer, tema | origem/legalidade dos dados, autorização das marcas, cards/Views validados |
| OC-E, P2 | OC315-11, 13 | Maps, Portal | comparação prévia com implementação existente; evitar duplicidade |
| OC-F, encerramento | todas | Donos + Ops | documentação e provenance reconciliadas, Unit/Kernel/Functional, Firefox ESR visível e Olivero onde aplicável |

**Ordem condicionada:** estas ondas são backlog aprovado, **não** autorização para contornar as fases/gates vigentes de cada módulo, nem para realizar `drush cr`, `config:import`, `updatedb`, deploy ou escrita de OpenStreetMap. Atualização de dependências exige aprovação e README no mesmo commit. O uso de Facets/Office Hours é **candidato**, não dependência aprovada automaticamente.

## 6. Conclusões adicionais e ressalvas

- A comparação anterior sugeriu "metadados de IA" e "polígonos" como se fossem comportamento verificado do 3.1.5. A árvore/configuração inspecionada **não fundamenta essa afirmação específica**. Manter como estudo independente, fora da matriz de paridade.
- `LocationCatalogue::busca()` e a geocodificação síncrona em `EventLocation::preSave()` devem ser reavaliados em testes de acesso/desempenho, sem afirmar falha de produção.
- `views.view.archive.yml` não é uma feature pronta: está com `status: false`; somente criar arquivo editorial se houver requisito explícito.
- `aculta_index` já dispõe de providers e consulta paginada com ACL; o problema de descoberta cultural envolve Search API, novas projeções e interfaces de busca, **não** reescrever o índice transversal existente.
- A validação desta rodada foi **estática e documental**. Nenhuma evidência de Runtime foi criada, nem resultado de PHPUnit alegado.

## 7. Links locais

- [Comparativo](OPENCULTURAS-COMPARATIVO.md)
- [Plano de incorporação](PLANO-INCORPORACAO-OPENCULTURAS.md)
- [Referências de código](OPENCULTURAS-INCORPORACAO-CODIGO.md)
- [Decisões vigentes](DECISOES-RESPONSAVEL-2026-10-28.md)
- [Roadmap do Portal](../portal/ROADMAP.md)
- [Roadmap de Eventos](../../web/modules/custom/aculta_portal/modules/aculta_events/docs/ROADMAP.md)
