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
| `MapRenderer::build(array $options)` | Retorna render array neutro do mapa. A lista alternativa e o aviso são responsabilidade do módulo consumidor. Opções: marcadores, centro, zoom, id |
| Marcador | `lat`, `lng`, `label`, `url` (opcional), `precision` (`exact` ou `approximate`) |
| `GeocodingService::geocode(string $address): ?array` | Retorna `lat`, `lng` ou nulo. Nominatim é limitado a uma chamada por segundo; coordenadas encontradas são armazenadas no local |
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
| `google` (opção) | Maps JavaScript API | Geocoding API, no servidor | `GOOGLE_MAPS_API_KEY` (navegador) e `GOOGLE_GEOCODING_API_KEY` (servidor), do `aculta_secrets` |

- Se `google` estiver escolhido e a chave faltar ou a API falhar: usar `openstreetmap` e mostrar aviso discreto ao
  administrador. O visitante não vê erro.
- Link de rota: disponível em qualquer engine, abre o Google Maps em nova aba só por clique.
- Chave do Maps JavaScript fica visível no navegador e deve ter restrições de host e API. A chave da Geocoding API
  é separada, restrita a IP do servidor e à Geocoding API. Nenhuma chave fica em configuração exportável.

## 5. Privacidade

- Carregamento do mapa junto com a página (D-08, mantido pelo D-18), com aviso visível e link para a política.
- Provedores externos recebem os dados técnicos exigidos pelo protocolo quando há requisição. A política pública do
  ambiente deve descrever o fluxo de cada provedor antes da ativação final; a preferência atual do responsável é não
  mencionar IP na política, portanto a ativação permanece pendente de compatibilização editorial.
- Local de precisão aproximada: coordenadas arredondadas (ex.: duas casas decimais) antes do mapa e da lista.
- Nenhum script de terceiro carregado no modo de link de rota.

## 6. Migração do código existente (microfase MP1)

O código de mapa estava em `aculta_events`. MP1 moveu `GeocodingService`, `aculta-map.js` e `GeocodingServiceTest` para `aculta_maps`. `MapController` permanece em `aculta_events`, pois obtém e prepara dados do catálogo de locais e atende a rota pública `/mapa`; MP5 usa o builder compartilhado para links de rota.

`aculta_events` chama `MapRenderer` e `GeocodingService` do `aculta_maps`. MP3 estabelece Leaflet/OSM como padrão; MP4 implementa Google como opção e fallback.

## 7. Dependências

- Nenhum pacote Composer novo para o OSM (Leaflet 1.9.4 entra como arquivo local, sob BSD-2-Clause).
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
