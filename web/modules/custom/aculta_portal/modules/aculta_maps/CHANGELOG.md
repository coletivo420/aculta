## Revalidação 2026-11-02 — acesso e configuração

- Unit Maps: 7 testes / 33 assertions PASS; Kernel: 3 testes / 6 assertions PASS, duas depreciações Core.
- Catálogo de Locais A/B permanece testado em Events (6/129). HTTP/purpose, consumers restantes, cabeçalho Runtime e revisão jurídica continuam pendentes; sem escrita Runtime.

# Changelog — aculta_maps

## 0.1.0-dev.7 — marcadores SDC inline (2026-11-04)

- O renderer fornece dados neutros e templates inertes para os ícones de
  marcadores; o tema ativo decide a apresentação e o JS clona SVG inline em
  `L.divIcon`, mantendo o Leaflet local e o agrupamento próprio.
- O behavior usa `core/once`; variantes de cor são allowlist e conteúdo SVG não
  é transferido por `drupalSettings`.
- Validação: Unit Maps 4/26 PASS, integração renderizada no Runtime e cache
  rebuild PASS; `updatedb:status` e `config:status` limpos. Revisão visual
  continua pendente.

## 0.1.0-dev.6 — popup com apresentação selecionada pelo tema (2026-11-04)

- Cada marcador válido recebe um identificador local para seu template de popup.
- Renderiza conteúdo neutro via hooks de tema; ACULTA420 V2 seleciona o SDC
  `map-popup`. Leaflet e Google usam o mesmo template inerte clonado no clique.
- URLs de popup aceitam apenas caminhos relativos same-site ou HTTPS antes da
  renderização; conteúdo textual permanece sujeito ao autoescape Twig.
- Composer não mudou: Geofield já é dependência do projeto e de Events; Maps
  usa Leaflet 1.9.4 local, não o módulo Drupal contrib Leaflet.
- Validação automática, Runtime e revisão visual ainda pendentes.

## 2026-10-31 — conformidade com tiles e transparência

- Leaflet passou a requisitar tiles pelo endpoint HTTPS canônico
  `tile.openstreetmap.org`, conforme a política atual da OSMF; removidos os
  subdomínios `{s}`.
- README e revisão técnica distinguem a minuta de privacidade 0.6 da política
  ainda não publicada no Runtime. D-52 segue sem aviso inline; ADR-030 requer
  detalhamento na política.
- Smoke Firefox de Maps agora registra e valida o host dos tiles e salva artefatos
  fora do repositório, em `aculta-suite-out` por padrão.
- Execução do Firefox/X11 contra o candidato ainda pendente; Runtime não foi
  alterado.

## 0.1.0-dev.5 — fronteira de apresentação via tokens (2026-10-29)

- Renderer, aviso de provedor e atribuição usam tokens `data-portal-ui`; o
  ACULTA420 mapeia os tokens para classes de apresentação.
- Maps: 10 testes / 39 assertions PASS, 2 depreciações Core; gate módulo–tema
  PASS. A revisão editorial de privacidade segue pendente.

## 2026-10-29 — F0: decisão D-46 e validação

- A decisão D-46 está implementada: Nominatim geocodifica para todos os engines; resultados Google Geocoding não são usados nem persistidos. Quando coordenadas Nominatim aparecem no mapa Google, há atribuição OSM/ODbL.
- Revalidação da suíte Maps: 10 testes, 39 assertions PASS; 2 depreciações do Core. A política de privacidade e o conflito editorial com ADR-030 continuam pendentes.

## 2026-10-28 — MP3, padrão Leaflet/OpenStreetMap

- Leaflet 1.9.4 é servido localmente sob BSD-2-Clause, sem CDN. O controle de atribuição do mapa aponta para OpenStreetMap.
- Adicionado agrupamento local de marcadores, com controles de teclado e conteúdo de popup criado por DOM seguro.
- Geocodificação usa Nominatim somente ao salvar local; lock persistente limita chamadas globais a uma por segundo e o User-Agent identifica ACULTA Maps.
- Lista textual e aviso com link para a política acompanham o mapa. Ativação no Runtime aguarda atualização editorial da política para registrar o envio do IP ao OSM.
- Suítes Kernel/Unit: Maps, 6 testes/20 assertions/3 depreciações; Events, 57/668/26. PHP lint, PHPCS Drupal/DrupalPractice, Node check, `drush cr` e `git diff --check` passam.
