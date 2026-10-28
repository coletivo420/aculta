# Roadmap — ACULTA Maps (`aculta_maps`)

Roadmap único do submódulo. Plano e contrato: `PLANO-IMPLANTACAO.md`.
Status: MP0 e MP1 concluídas; MP2–MP7 a fazer.

## MP0 — Criação do submódulo

- [x] `aculta_maps.info.yml` sem dependências de terceiros, `aculta_maps.services.yml` vazio, README do módulo.
- [x] Busca estática: chamadas de mapa Google agora ficam em `aculta_maps`; `aculta_events_openstreetmap` mantém apenas sua integração separada de edição/importação OSM (E10).

## MP1 — Migração do código de `aculta_events`

- [x] Extrair `GeocodingService` e `aculta-map.js`; manter `MapController` em `aculta_events` porque monta o catálogo de locais e a rota `/mapa`.
- [x] `aculta_events` consome `MapRenderer` e `GeocodingService` de `aculta_maps`.
- [x] Mover os testes unitários de geocodificação e adaptar os Kernel tests do consumidor. Suíte Events: 57 testes, 667 assertions, 27 depreciações; Maps: 5 testes, 5 assertions.

Evidência de MP0: `9ff609c1e`. Código MP1: hash será registrado após o commit de implementação.

## MP2 — Configuração e painel (ADM7)

- [ ] Formulário em `/painel-administrativo/mapas`: engine, centro, zoom inicial, zoom ao mostrar um local, modelo da URL de rota.
- [ ] Estado da chave `GOOGLE_MAPS_API_KEY` (configurada ou ausente), nunca o valor.
- [ ] Permissão própria. Teste de acesso negado para usuário comum.
- [ ] Teste: salvar a configuração e confirmar que nenhum valor de chave aparece no config export.

## MP3 — Engine OpenStreetMap (padrão e fallback)

- [ ] Leaflet e agrupamento de marcadores como arquivos locais, com LICENSE no pacote. Sem CDN.
- [ ] Atribuição "© OpenStreetMap contributors" visível em todo mapa.
- [ ] Geocodificação com Nominatim: 1 req/s, User-Agent, cache no objeto de origem, sem autocompletar.
- [ ] Lista alternativa textual funciona sem JavaScript.

## MP4 — Engine Google (opção) e fallback

- [ ] Maps JavaScript API com a chave lida do `aculta_secrets`.
- [ ] Geocoding API no servidor, mesma interface do serviço.
- [ ] Fallback para OSM quando a chave falta ou a API falha; aviso só para o administrador.
- [ ] Documentar as restrições de referenciador e de API da chave.

## MP5 — Link de rota sem API

- [ ] `RouteLinkBuilder` com modelo configurável (padrão: `https://www.google.com/maps/dir/?api=1&destination={destino}`).
- [ ] Abre em nova aba só por clique. Teste: a página não carrega script do Google nesse modo.

## MP6 — Privacidade

- [ ] Arredondamento de coordenadas de precisão aproximada, antes de sair do serviço. Teste Unit.
- [ ] Aviso de privacidade visível na página do mapa, com link para a política.

## MP7 — Verificação e documentação para agentes

- [ ] Verificação no Firefox ESR (`DISPLAY=:0`): desktop 1280 px, 320 px e modo escuro, com capturas em `$SUITE_OUT`.
- [ ] README do módulo com a API, o que cada módulo pode e não pode fazer, e onde ficam as chaves.
- [ ] Atualizar ADR-030 com o estado de implementação.
