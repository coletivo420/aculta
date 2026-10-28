# Changelog — aculta_maps

## 2026-10-28 — MP3, padrão Leaflet/OpenStreetMap

- Leaflet 1.9.4 é servido localmente sob BSD-2-Clause, sem CDN. O controle de atribuição do mapa aponta para OpenStreetMap.
- Adicionado agrupamento local de marcadores, com controles de teclado e conteúdo de popup criado por DOM seguro.
- Geocodificação usa Nominatim somente ao salvar local; lock persistente limita chamadas globais a uma por segundo e o User-Agent identifica ACULTA Maps.
- Lista textual e aviso com link para a política acompanham o mapa. Ativação no Runtime aguarda atualização editorial da política para registrar o envio do IP ao OSM.
- Suítes Kernel/Unit: Maps, 6 testes/20 assertions/3 depreciações; Events, 57/668/26. PHP lint, PHPCS Drupal/DrupalPractice, Node check, `drush cr` e `git diff --check` passam.
