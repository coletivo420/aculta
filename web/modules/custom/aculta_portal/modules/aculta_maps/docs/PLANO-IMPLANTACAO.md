# Plano de implantação — ACULTA Maps (`aculta_maps`)

## 1. Por que um submódulo próprio

O mapa passou a ser usado por mais de uma área do site. Se ficar dentro de `aculta_events`, cada área teria que
copiar a mesma lógica de provedor, chave, privacidade e geocodificação. O submódulo centraliza isso e os outros
módulos só fornecem dados (coordenadas, rótulo, link).

## 2. Escopo

Dentro:
- renderização de mapa com Leaflet, usando **OpenStreetMap público como padrão e fallback** (D-18);
- opção de **Google Maps API**, com a chave lida do `aculta_secrets` (`GOOGLE_MAPS_API_KEY`), sem exibir o valor;
- **link de rota do Google sem API**, com o modelo de URL configurável no painel (D-18);
- geocodificação de endereço, com provedor Nominatim (padrão) ou Google Geocoding (opção), guardada no objeto de origem;
- agrupamento de marcadores e lista alternativa textual (acessibilidade);
- painel administrativo de configuração em `/painel-administrativo/mapas` (ADM7 passa a viver aqui);
- privacidade: aviso visível, coordenadas arredondadas para locais de precisão aproximada, atribuição do OSM.

Fora:
- dados de locais e eventos (ficam em `aculta_events`);
- mapa de tiles próprios (PMTiles): é outra decisão, prevista como caminho de crescimento (ADR-030);
- importação ou edição de dados no OpenStreetMap (E10, fora da v1).

## 3. API pública (contrato para outros módulos)

Um módulo que precisa de mapa monta um array com marcadores e chama o serviço de renderização. Não conhece o provedor.

| Item | Descrição |
| --- | --- |
| `MapRenderer::build(array $options)` | Retorna render array com o mapa, a lista alternativa e o aviso. Opções: marcadores, centro, zoom, id |
| Marcador | `lat`, `lng`, `label`, `url` (opcional), `precision` (`exact` ou `approximate`) |
| `GeocodingService::geocode(string $address): ?array` | Retorna `lat`, `lng` ou nulo. Respeita limite e cache |
| `RouteLinkBuilder::build(array $destination): Url` | Link de rota com o modelo configurado, sem API |
| `MapSettings` | Configuração lida do config do submódulo (engine, centro, zoom, modelo de URL) |

Regras do contrato:
- Quem chama não recebe nada específico do provedor. Trocar de engine não exige mudança no chamador.
- Coordenadas de precisão aproximada são arredondadas antes de sair do serviço.
- O tema recebe render arrays neutros; não consulta `Domain` nem o purpose (ADR-015 e a regra do shell).

## 4. Provedores e fallback

| Engine | Mapa | Geocodificação | Chave |
| --- | --- | --- | --- |
| `openstreetmap` (padrão e fallback) | Leaflet com tiles públicos do OSM, atribuição visível | Nominatim público: 1 req/s, User-Agent do ACULTA, cache, sem autocompletar | Não precisa |
| `google` (opção) | Maps JavaScript API | Geocoding API, no servidor | `GOOGLE_MAPS_API_KEY` do `aculta_secrets` |

- Se `google` estiver escolhido e a chave faltar ou a API falhar: usar `openstreetmap` e mostrar aviso discreto ao
  administrador. O visitante não vê erro.
- Link de rota: disponível em qualquer engine, abre o Google Maps em nova aba só por clique.
- Chave do Google: fica visível no navegador (exigência da Maps JavaScript API). Por isso precisa de restrição de
  referenciador por host e de restrição de API. Registrado em ADR-030.

## 5. Privacidade

- Carregamento do mapa junto com a página (D-08, mantido pelo D-18), com aviso visível e link para a política.
- Com OSM, o IP do visitante vai aos servidores do OpenStreetMap ao carregar os tiles. Está no aviso e na política.
- Com Google, o IP vai ao Google. Só na engine `google`.
- Local de precisão aproximada: coordenadas arredondadas (ex.: duas casas decimais) antes do mapa e da lista.
- Nenhum script de terceiro carregado no modo de link de rota.

## 6. Migração do código existente (microfase MP1)

Hoje o código de mapa está em `aculta_events`:
- `src/Controller/MapController.php`
- `src/Geocoding/GeocodingService.php`
- `js/aculta-map.js`
- testes `MapControllerKernelTest` e `GeocodingServiceTest`

Ordem: mover para `aculta_maps` sem mudar comportamento; `aculta_events` passa a chamar `aculta_maps`; os testes
movem junto. Só depois a engine OSM entra, e por último o Google e o painel.

## 7. Dependências

- Nenhum pacote Composer novo para o OSM (Leaflet entra como arquivo local, com licença MIT/BSD no próprio pacote).
- Coordenadas vêm de campo geofield nos consumidores (`geofield` já está instalado, D-16).
- Google: só a chave, sem pacote.

## 8. Riscos

| Risco | Mitigação |
| --- | --- |
| Tráfego alto ao servidor público do OSM | Cache no navegador, sem recarregar tiles sem interação; caminho de tiles próprios (ADR-030) |
| Nominatim além do limite | Geocodificação só ao salvar ou por ação do administrador; cache no objeto de origem |
| Chave do Google exposta no navegador | Restrição de referenciador e de API; registrado |
| Consumidor fazer chamada direta ao provedor | Regra de uso no README e teste de regressão (grep no código dos outros módulos) |
| Mudança de engine quebrar mapas existentes | Teste de contrato: mesma entrada, mesma saída estrutural nas duas engines |
