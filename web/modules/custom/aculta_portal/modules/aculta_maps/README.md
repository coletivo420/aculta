# ACULTA Maps

`aculta_maps` é o serviço compartilhado de mapas, geocodificação e links de rota.
Consumidores passam dados e render arrays por seus serviços; não chamam Google,
OpenStreetMap ou Nominatim diretamente.

## Serviços públicos

- `aculta_maps.renderer` (`MapRenderer::build()`): recebe marcadores com `lat`,
  `lng`, `label`, `description`, `url`, `routeUrl` e `precision`. Coordenadas
  inválidas são descartadas; precisão `approximate` é arredondada a duas casas.
- `aculta_maps.geocoding` (`GeocodingService::geocode()`): retorna `lat`/`lng`
  ou `NULL`. Nominatim respeita limite global de uma chamada por segundo; a
  opção Google usa uma chamada de servidor e cai para Nominatim em falha.
- `aculta_maps.route_link_builder` (`RouteLinkBuilder::build()`): gera URL de
  rota com destino codificado e hosts permitidos. Links abrem apenas por clique,
  em nova aba com `noopener noreferrer`.

## Provedores e credenciais

OpenStreetMap com Leaflet é o padrão. A atribuição aparece no mapa e a página
consumidora precisa manter uma lista textual de conteúdo equivalente para quem
não usa JavaScript. O aviso de privacidade deve apontar para
`/sobre/politica-de-privacidade`.

Google é opcional e escolhido no painel ADM7. `GOOGLE_MAPS_API_KEY` é usada no
navegador e deve ser restrita aos hosts por referenciador e à Maps JavaScript
API. `GOOGLE_GEOCODING_API_KEY` é separada, só fica no servidor e deve ser
restrita ao IP do servidor e à Geocoding API. Ambas são lidas por
`aculta_portal.secrets_manager`; não grave valores em config, código, logs ou
documentação. O formulário mostra somente se estão configuradas.

## Responsabilidades do consumidor

Consumidores podem compor marcadores a partir dos próprios dados e chamar os
serviços acima. Não devem definir engine, expor segredos, modificar dados OSM,
geocodificar a cada visualização nem incluir endereço de contato em links ou
exportações sem autorização. `aculta_events` mantém o catálogo e a rota `/mapa`;
`aculta_maps` mantém renderização, geocodificação e link de rota.

Leaflet 1.9.4 é distribuído em `assets/vendor/leaflet/` sob BSD-2-Clause. O
mapa público não foi ativado no Runtime: o smoke visual aguarda a revisão de
transparência da política do ambiente, conforme ADR-030. Plano e fases estão em
`docs/`.
