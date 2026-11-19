# Diagnóstico — mapa sem marcadores (2026-11-07)

Ambiente: servidor de desenvolvimento, tema `aculta420_v2`, base `origin/dev` 6651054.
Evidência sem endereço, coordenada ou segredo.

## Observado

| Verificação | Resultado |
|---|---|
| `/mapa` no host de eventos | 200; `div#aculta-map[data-aculta-map=true]`, `role=region` |
| `drupalSettings.acultaMaps.aculta-map` | provider `openstreetmap`, zoom 4, **0 markers** |
| Biblioteca `aculta_maps/map` | presente no agregado; `window.L` definido; container recebe `leaflet-container` |
| Tiles | `tile.openstreetmap.org` HTTP 200; atribuição Leaflet/OSM visível |
| Altura do container (390 px) | 192 px: `min-block-size: 12rem` do tema sobrepõe os `20rem` do módulo |
| Console | agregado JS de `locale/translations` responde 400 `Invalid filename` (não afeta o mapa; fora do escopo) e avisos CSP Report-Only de estilo inline (Leaflet) |
| Dados | 1 Local, público, com endereço, **sem coordenadas**; 4 Eventos |
| Geocodificação (teste sem salvar) | Nominatim resolve o endereço do Local hoje |
| Página do Local `/locais/1` | sem consumidor de mapa |
| Rotas OSM | MAIN: 403 para anônimo nas 4 rotas; host de eventos: 404 |

## Causa

O mapa inicializa e carrega tiles; não é falha de JS, biblioteca, provider ou tiles.
Os marcadores não aparecem porque o único Local não tem coordenadas, e
`EventLocation::preSave()` só geocodifica quando a entidade é nova ou o endereço muda.
Um Local salvo sem coordenadas (geocodificação indisponível na criação) nunca é
reprocessado em salvamentos seguintes. A altura reduzida do mapa (12rem no mobile)
agrava a percepção de mapa “vazio”.

## Correções previstas

1. `aculta_events`: geocodificar também na atualização quando não há coordenadas.
2. Tema: altura responsiva do mapa com tokens V2; ordem mapa → lista.
3. Documentação do `MapController` (dizia que o mapa exige credencial; OSM é padrão sem chave).

O preenchimento do Local existente exige salvá-lo; não foi feito (alteração de dado).
