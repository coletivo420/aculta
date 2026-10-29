# Roadmap — ACULTA Maps (`aculta_maps`)

Roadmap único do submódulo. Plano e contrato: `PLANO-IMPLANTACAO.md`.
Status: MP0–MP7 verificados no Runtime de teste, com OSM como provedor padrão. A política pública não foi alterada após a decisão de não informar IP.

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
- [x] Geocodificação somente com Nominatim, qualquer que seja o engine do mapa: 1 req/s global, User-Agent, coordenadas armazenadas no local, sem autocompletar (D-46).
- [x] Lista alternativa textual funciona sem JavaScript.

Runtime de teste ativado com OSM após ordem explícita do responsável. A política não foi alterada conforme decisão de não informar IP; esse ponto de transparência continua registrado como pendência editorial.

Evidência técnica MP3: `58359728f`.

## MP4 — Engine Google (opção) e fallback

- [x] Maps JavaScript API opcional com chave lida do `aculta_secrets`, carregada só quando Google está selecionado.
- [x] Geocoding API removida do fluxo da aplicação por D-46; a chave provisionada está sem uso.
- [x] Fallback para OSM sem chave ou quando a API falha; aviso só para o administrador.
- [x] Documentar restrições por referenciador/API no navegador e por IP/API no servidor.

Evidência MP4: `d9ae563d4`.

## MP5 — Link de rota sem API

- [x] `RouteLinkBuilder` usa o modelo configurável, codifica o destino e rejeita hosts não aprovados.
- [x] Link abre em nova aba com `noopener noreferrer`; não injeta script Google para gerar a rota.
- [x] Lista textual e popups do mapa oferecem rota quando há endereço.

Evidência MP5: `33b638ccf`.

## MP6 — Privacidade

- [x] Coordenadas de precisão aproximada arredondadas antes de sair do `MapRenderer`; Unit cobre duas casas decimais.
- [x] Aviso de privacidade visível na página do mapa, com link para `/sobre/politica-de-privacidade`; Kernel cobre a renderização.

Evidência técnica MP6: `58359728f`; suítes verificadas em 2026-10-28: Maps 10 testes/44 assertions/2 depreciações e Events 57/671/26.

## MP7 — Verificação e documentação para agentes

- [x] Firefox ESR visível em `DISPLAY=:0`: desktop 1280 px, viewport real de 320 px e modo escuro; sem overflow horizontal. Capturas e JSON em `/home/piradopirata/aculta-suite-out/mp7-maps`.
- [x] README do módulo documenta API, responsabilidades dos consumidores e chaves.
- [x] ADR-030 registra o estado de implementação e o bloqueio de transparência.

Evidência MP7: smoke no servidor `eventos.aculta.toca.net.br`; configuração ativa `openstreetmap`. O catálogo do Runtime de teste não tem marcador com coordenadas, então a presença do marcador aproximado é validada pela Kernel `MapControllerKernelTest`.

Commit MP7: `fd5c96526`.

## Revisão F0 — Termos e licenças

Revisão técnica em [`REVISAO-TERMOS-MAPAS-2026-10-28.md`](REVISAO-TERMOS-MAPAS-2026-10-28.md). OpenStreetMap permanece
o engine do Runtime de teste. D-46 mantém Nominatim para todos os engines e exige atribuição OSM/ODbL quando seus
resultados aparecem em mapa Google. A chave Google Geocoding provisionada está sem uso. A política pública não foi
alterada e a revisão editorial sobre o provedor de tiles permanece pendente.

Implementação F0 concluída em `dc032d9e0`. Revalidação em 2026-10-29: suíte Maps
10 testes/39 assertions PASS, com 2 depreciações do Core; cobre o uso de Nominatim
e a atribuição OSM/ODbL no engine Google. A revisão da política continua aberta:
a preferência registrada de não mencionar o envio de IP conflita com a exigência
de transparência do ADR-030 antes da ativação final.

## Fronteira módulo–tema (2026-10-29, dev.5)

`MapRenderer` e `MapController` agora enviam tokens semânticos `data-portal-ui`
para mapa, atribuição, avisos e links. PortalUiHooks mapeia as classes no tema.
Gate global módulo–tema PASS (3397 verificações); suíte Maps 10/39 PASS, duas
depreciações Core. A revisão de privacidade e a verificação visual continuam
pendentes.

## Backlog OC315 - Maps e locais (2026-10-29)

Fonte: [auditoria 3.1.5](../../../../../../../docs/architecture/AUDITORIA-OPENCULTURAS-3.1.5-2026-10-29.md). **Nenhuma mudança no provedor OSM/Leaflet padrão** (D-17 a D-20).

- **OC-M1/P0:** testar proteção de locais no catálogo/mapa; revisar `accessCheck(FALSE)` de `LocationCatalogue`; garantir que precise de política explícita para expor dados pessoais/privados.
- **OC-M2/P0:** aceitar coordenadas 0 e 0.0 sem eliminá-las por `!empty()`; testar range lat/lon e precisão aproximada.
- **OC-M3/P1:** integrar dados estruturados de acessibilidade/horários a filtros e popups, quando schema de Eventos existir.
- **OC-M4/P2:** revisar `OfficeHoursToOsmFormatter.php` da origem após adoção de dados estruturados; não importar edição OSM sem revisão humana.
- **Exploratório fora da paridade 3.1.5:** geometrias Polygon/MultiPolygon para circuitos e trajetos de eventos; **não** tratar como característica confirmada da origem nem implementar sem ADR de privacidade/custo.

## Complemento OC-A/OC-E - OpenCulturas 3.1.5 (2026-10-29)

- [ ] Rever a política de acesso dos locais antes de exportar marcadores e listagens; preservar privacidade de precisão aproximada, cache e purpose. Tratar coordenadas zero numericamente.
- [ ] Consumir horários estruturados e selos de acessibilidade somente depois de modelo aprovado em Events.
- [ ] Estudar `OfficeHoursToOsmFormatter` após o novo contrato de horários e revisão OSM; filtros de proximidade são referência de UX. Manter Leaflet/OSM padrão e Google opcional. Polígonos não foram comprovados no pacote 3.1.5; considerar apenas como pesquisa independente.

Nenhuma autorização de escrita OSM, deploy ou substituição de geocodificador.
