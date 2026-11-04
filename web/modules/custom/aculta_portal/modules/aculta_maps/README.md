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
  Para cada marcador válido, a renderização inclui um template inerte por meio
  dos hooks `aculta_maps_popup_template` e `aculta_maps_popup_content`. O tema
  ativo escolhe a apresentação; Leaflet/Google clonam o template quando o
  marcador é aberto. O módulo não conhece IDs nem classes de SDC.
  Para Leaflet, um hook separado fornece template inerte de ícone; o tema pode
  renderizar Phosphor inline e o cliente o clona para `L.divIcon`. Isso mantém
  SVG fora de `drupalSettings` e não requer sprites externos nem CORS.
- `aculta_maps.geocoding` (`GeocodingService::geocode()`): retorna `lat`/`lng`
  ou `NULL`. Nominatim é usado em todos os engines e respeita limite global de
  uma chamada por segundo. Google Geocoding não é chamado pela aplicação.
- `aculta_maps.route_link_builder` (`RouteLinkBuilder::build()`): gera URL de
  rota com destino codificado e hosts permitidos. Links abrem apenas por clique,
  em nova aba com `noopener noreferrer`.

## Provedores e credenciais

OpenStreetMap com Leaflet é o padrão. O código usa o endpoint HTTPS canônico
`https://tile.openstreetmap.org/{z}/{x}/{y}.png`, com atribuição visível. A
página consumidora precisa manter uma lista textual de conteúdo equivalente
para quem não usa JavaScript. O aviso de privacidade aponta para
`/sobre/politica-de-privacidade`; detalhes de tráfego e provedores pertencem à
política, conforme ADR-030 e D-52.

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
No formulário de endereço do local, a pessoa digita o CEP e o Portal CEP
preenche os campos postais, igual ao formulário geral. Isso já está ativo no
adaptador de `EventLocation` e na revisão de endereço do importador OSM.

A busca atual por CEP já preenche o endereço e continua compartilhada. Busca/autocomplete futura pelo nome da rua/logradouro ainda não está disponível e sua interface ficará restrita a Events e Maps. O serviço pode ser fornecido por `aculta_portal_cep`, mas não será ativado em Conta, checkout ou formulário global. Geocodificação de endereço salvo continua distinta da busca postal. Plano e critérios:
[CEP-ADDRESS-SEARCH-PLAN](../../../../../../docs/portal/CEP-ADDRESS-SEARCH-PLAN.md).

Leaflet 1.9.4 é distribuído em `assets/vendor/leaflet/` sob BSD-2-Clause. A
minuta 0.6 da Política de Privacidade descreve tiles OSM/Google e geocodificação
Nominatim, mas ainda não foi publicada no Runtime. Confirme o provedor e os
cabeçalhos HTTP ativos por ambiente antes da homologação. Plano e fases estão
em `docs/`.

## Cache compartilhado e CDN

O renderer continua entregando render arrays para popups e ícones e preserva
tags/contextos no render tree. A página de mapa acrescenta a tag do catálogo
de locais e seu max-age; consumidores que agreguem conteúdo de entidades devem
adicionar essas dependências/cache tags ao próprio render array. Dados de
marcadores ficam no `drupalSettings` da página cacheável e não incluem sessão
ou perfil pessoal. Configuração de CDN, TTL, headers de surrogate keys e purge
é responsabilidade da integração de cada ambiente; o módulo não fixa host,
headers de cache ou serviço de tiles.


## Revalidação técnica 2026-11-02

Unit Maps: 7 testes / 33 assertions PASS. Kernel: 3 testes / 6 assertions PASS; duas depreciações Core. O catálogo ACL A/B é testado pelo owner `aculta_events` (`LocationCatalogueKernelTest`: 6/129, dois owners e anônimo). HTTP/purpose, consumers restantes, `Referrer-Policy` Runtime e revisão jurídica continuam pendentes; nenhuma escrita Runtime.
