# ACULTA Maps

Submódulo compartilhado para mapas e geocodificação. Consumidores devem usar
os serviços e render arrays públicos de `aculta_maps`; não devem integrar
diretamente com Google Maps, OpenStreetMap ou Nominatim.

Consumidores montam `markers` com `lat`, `lng`, `nome` e `endereco` e chamam
`aculta_maps.renderer` (`MapRenderer::build()`). Geocodificação de endereços usa
`aculta_maps.geocoding` (`GeocodingService::geocode()`). As chaves externas
ficam no `aculta_secrets` e nunca são armazenadas na configuração exportável.
O mecanismo Google atual é transitório; MP3 estabelece Leaflet/OSM como padrão.
O contrato e o estado por microfase estão em `docs/`.
