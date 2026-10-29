# ACULTA Maps

## Contrato obrigatório para campos de endereço

Por decisão D-54/D-55 do responsável, qualquer formulário de captura ou edição
de endereço consumido por Maps deve reutilizar `AccountAddressForm`/adaptador
central de `aculta_portal` e a biblioteca `aculta_portal_cep/cep-address`.
O país é fixo em `BR`, sem seletor de país. Os dados continuam no modelo
próprio de local/mapa; não crie um formulário paralelo nem replique consulta,
endpoint ou cliente de CEP. Busca geográfica Nominatim restrita ao Brasil não
substitui o preenchimento de endereço por CEP.

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
API. Por D-46, a geocodificação usa Nominatim para todos os engines e não chama
Google Geocoding; a chave `GOOGLE_GEOCODING_API_KEY` provisionada permanece sem
uso pela aplicação. Quando coordenadas Nominatim aparecem em mapa Google, o
renderer exibe atribuição a OpenStreetMap contributors e link à licença ODbL.
Não grave valores de credenciais em config, código, logs ou documentação.

## Responsabilidades do consumidor

Consumidores podem compor marcadores a partir dos próprios dados e chamar os
serviços acima. Não devem definir engine, expor segredos, modificar dados OSM,
geocodificar a cada visualização nem incluir endereço de contato em links ou
exportações sem autorização. `aculta_events` mantém o catálogo e a rota `/mapa`;
`aculta_maps` mantém renderização, geocodificação e link de rota.

Map Settings não captura endereço. A geocodificação do serviço restringe a
Nominatim ao Brasil (`countrycodes=br`); os locais de Eventos fornecem seus
endereços pelo `AccountAddressForm` central do `aculta_portal`, com país fixo
BR e preenchimento CEP do `aculta_portal_cep`.

Leaflet 1.9.4 é distribuído em `assets/vendor/leaflet/` sob BSD-2-Clause. O
mapa público não foi ativado no Runtime: o smoke visual aguarda a revisão de
transparência da política do ambiente, conforme ADR-030. Plano e fases estão em
`docs/`.
