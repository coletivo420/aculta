# Roadmap — ACULTA Maps (`aculta_maps`)

Roadmap único do submódulo. Plano e contrato: `PLANO-IMPLANTACAO.md`.
Status: MP0–MP2 concluídas; MP3 técnica implementada, aguardando atualização da política e ativação no Runtime; MP4–MP7 a fazer.

## MP0 — Criação do submódulo

- [x] `aculta_maps.info.yml`, service definitions e README do módulo. Dependências de runtime limitadas ao Portal e Key (sem nova biblioteca externa).
- [x] Busca estática: chamadas de mapa Google agora ficam em `aculta_maps`; `aculta_events_openstreetmap` mantém apenas sua integração separada de edição/importação OSM (E10).

## MP1 — Migração do código de `aculta_events`

- [x] Extrair `GeocodingService` e `aculta-map.js`; manter `MapController` em `aculta_events` porque monta o catálogo de locais e a rota `/mapa`.
- [x] `aculta_events` consome `MapRenderer` e `GeocodingService` de `aculta_maps`.
- [x] Mover os testes unitários de geocodificação e adaptar os Kernel tests do consumidor. Suíte Events: 57 testes, 667 assertions, 27 depreciações; Maps: 5 testes, 5 assertions.

Evidência de MP0: `9ff609c1e`. MP1: `917cce3f1`.

## MP2 — Configuração e painel (ADM7)

- [x] Formulário em `/painel-administrativo/mapas`: engine, centro, zoom inicial, zoom ao mostrar um local, modelo da URL de rota.
- [x] Estado da chave `GOOGLE_MAPS_API_KEY` (configurada ou ausente), nunca o valor.
- [x] Permissão própria. Kernel nega acesso ao usuário comum.
- [x] Kernel salva as opções e confirma que nenhum valor de chave aparece na configuração exportada; URL rejeita `javascript:` e hosts não aprovados.

Evidência de MP2: `cac0efbba`.

## MP3 — Engine OpenStreetMap (padrão e fallback)

- [x] Leaflet 1.9.4 e assets locais sob BSD-2-Clause; agrupamento próprio, sem CDN.
- [x] Atribuição "© OpenStreetMap contributors" visível em todo mapa.
- [x] Geocodificação com Nominatim: 1 req/s global, User-Agent, coordenadas armazenadas no local, sem autocompletar.
- [x] Lista alternativa textual funciona sem JavaScript.

Pendente para fechar a ativação de MP3 no Runtime: adicionar à política pública a informação sobre tiles OSM e envio do IP. A página de teste atual ainda não menciona os provedores; a decisão sobre a alteração de conteúdo está aguardando resposta.

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
