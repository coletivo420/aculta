# Revisão técnica de termos e licenças de mapas

Data da consulta: 2026-10-28. Escopo: provedores ativos em `aculta_maps`, geocodificação, uso de tiles e ativos de terceiros.

## Google Maps Platform

- A Maps JavaScript API exige faturamento habilitado e cobra por SKU/uso; carregamentos de mapa dinâmico são medidos
  separadamente de outros serviços. A Geocoding API também cobra por requisição. As cotas gratuitas e tarifas são
  variáveis por SKU e devem ser conferidas na tabela atual antes de habilitar o Google em outro ambiente.
- Os termos exigem atribuição e restringem cache de conteúdo Google ao que os termos específicos permitem. O conteúdo
  da Geocoding API não pode ser usado junto a um mapa não Google.
- Coordenadas de latitude/longitude da Geocoding API podem ser guardadas por até 30 dias. A exceção de armazenamento
  sem prazo exige suporte direto à funcionalidade voltada ao usuário, isolamento lógico ao usuário que iniciou a
  consulta e que o cache não substitua novas consultas.

Referências oficiais:

- [Termos da Google Maps Platform](https://cloud.google.com/maps-platform/terms)
- [Termos específicos por serviço](https://cloud.google.com/maps-platform/terms/maps-service-terms)
- [Preços por SKU](https://developers.google.com/maps/billing-and-pricing/pricing)
- [Uso e faturamento da Maps JavaScript API](https://developers.google.com/maps/documentation/javascript/usage-and-billing)
- [Uso e faturamento da Geocoding API](https://developers.google.com/maps/documentation/geocoding/usage-and-billing)

### Verificação do código ACULTA

**Decisão D-46 (2026-10-28):** `GeocodingService` usa Nominatim qualquer que seja o engine do mapa. A aplicação não
consulta Google Geocoding e não persiste resultados dessa API. Coordenadas retornadas por Nominatim continuam sendo
armazenadas no local; quando forem exibidas sobre mapa Google, a interface atribui OpenStreetMap contributors e liga
à página de copyright/licença ODbL. A chave Google Geocoding provisionada permanece sem uso pela aplicação.

## OpenStreetMap

A política do servidor público de tiles declara disponibilidade best-effort, exige atribuição visível, identificador
de aplicação e respeito aos cabeçalhos de cache, e proíbe prefetch, download em massa e cabeçalhos `no-cache`. O mapa
usa o endpoint HTTPS oficial e o navegador faz pedidos somente para o viewport visível. O teste Firefox confirmou a
atribuição. A resposta da página de teste não define `Referrer-Policy`; o navegador conserva seu comportamento padrão.

- [Política oficial de tiles](https://operations.osmfoundation.org/policies/tiles/)
- [Política oficial do Nominatim](https://operations.osmfoundation.org/policies/nominatim/)

A política de privacidade do ACULTA não foi alterada conforme instrução do responsável de não incluir informação
sobre IP. A revisão editorial da informação sobre o provedor permanece pendente.

## Licenças

- OpenCulturas 3.1.4 declara GPL-2.0-or-later. Os trechos efetivamente derivados e os itens reescritos estão
  registrados em `aculta_events/docs/ORIGEM-OPENCULTURAS.md`; a regra do repositório permite GPLv3 porque a licença de
  origem permite versões posteriores da GPL.
- O ACULTA não usa o sprite Phosphor do OpenCulturas. O tema usa Bootstrap Icons sob MIT e mantém a licença no pacote.
- Leaflet 1.9.4 é distribuído com BSD-2-Clause no módulo de mapas. O agrupamento e os componentes ACULTA são código
  próprio.

## Estado

Termos, cobrança, atribuição, cache e licenças foram revisados tecnicamente. D-46 remove o uso de resultados Google
Geocoding; a atribuição OSM/ODbL é mostrada em mapas Google quando há coordenadas de Nominatim. A transparência
editorial geral da política de privacidade continua separada e pendente.
