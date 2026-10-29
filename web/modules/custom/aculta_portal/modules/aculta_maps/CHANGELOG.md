# Changelog — aculta_maps

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
