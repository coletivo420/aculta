# Pesquisa e Análise Comparativa de Provedores de Busca por Logradouro para ACULTA Events e Maps

> **Status:** Pesquisa técnica e recomendação arquitetural. **Nenhuma busca por endereço foi implementada nesta fase.** Conta, checkout e cadastro de locais mantêm o comportamento estrito de CEP central (`aculta_portal_cep`) com país exclusivamente Brasil (`BR`).

---

## 1. Contexto e Guardrails do Projeto

O ecossistema ACULTA possui regras arquiteturais rígidas estabelecidas em ADR-033, ADR-034 e `docs/portal/ACCOUNT-ADDRESS.md`:
1. **Endereço Centralizado e Brasil Exclusivo:** Qualquer formulário de endereço no ACULTA consome a integração central de CEP via `aculta_portal_cep` (`cep-address.js` e `AccountAddressForm`). Não há seletor de país (fixo em `BR`).
2. **Conta e Checkout Intocados:** A busca por logradouro livre/autocomplete é terminantemente proibida em Conta, Meus Dados e no Checkout do Commerce, preservando integridade cadastral e performance.
3. **Escopo Restrito a Events e Maps:** A busca por logradouro livre ou geocodificação direta/reversa destina-se exclusivamente a interfaces especializadas de catálogo de eventos e mapas interativos (`aculta_events` e `aculta_maps`).
4. **Proteção de Privacidade e LGPD:** Termos digitados pelo usuário, coordenadas geográficas precisas e endereços residenciais não podem vazar para provedores externos sem consentimento e base legal adequada.

---

## 2. Matriz Comparativa de Provedores

| Provedor / Serviço | Cobertura no Brasil | Termos e Licença | Atribuição Obrigatória | Custo / Quotas | Privacidade (LGPD) | Cache e Persistência |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Nominatim (OSM Público)** | Média a Alta em capitais; irregular no interior. | ODbL / Aceitação de Política de Uso de APIs da OSMF. | Obrigatório: *"© OpenStreetMap contributors"*. | Máx 1 req/segundo. Proibido tráfego em lote ou comercial pesado. | IP e termos de busca enviados aos servidores da OSMF. Sem contrato de DPA. | **Permitido** armazenar resultados em cache local permanente respeitando ODbL. |
| **Nominatim Próprio / Self-Hosted (Docker/PostGIS)** | Idem dados OSM Brasil (extrato Geofabrik). | ODbL total sobre os dados. | Obrigatório: *"© OpenStreetMap contributors"*. | Custo apenas de infraestrutura (CPU/RAM/SSD local). Sem limites de quota. | **Excelente:** Nenhuma requisição sai do servidor do ACULTA. Zero vazamento de IPs ou termos. | **Total:** Cache irrestrito e otimizado no banco local. |
| **BrasilAPI / ViaCEP (Base Postal)** | **Excepcional** em todo o território nacional por CEP/Logradouro. | Dados públicos abertos dos Correios / DNE. | Cortesia / citação recomendada. | Gratuito; taxa moderada (ViaCEP requer rate limit brando; BrasilAPI CDN). | Boa: servidores nacionais ou CDN com baixa retenção. | **Permitido** em cache local (o ACULTA já possui cache próprio de CEP). |
| **Photon (Komoot)** | Baseada em OSM com motor Elasticsearch. Excelente para busca aproximada/typo-tolerant. | Apache 2.0 (software) / ODbL (dados). | Obrigatório: *"© OpenStreetMap contributors"*. | Servidor público Komoot com limites moderados; software livre para self-hosting. | No servidor público há tráfego para a Alemanha; em self-hosting, 100% privado. | **Permitido** cache dos dados OSM. |
| **LocationIQ** | Alta (OSM enriquecido com bases abertas e comerciais). | Comercial (SaaS). | Obrigatório no plano Free; opcional nos planos pagos. | Gratuito: 5.000 req/dia (1 req/segundo). Planos pagos a partir de US$ 49/mês. | Servidores nos EUA/Europa. Oferecem DPA para conformidade RGPD/LGPD. | Proibido cache permanente longo sem plano Enterprise. |
| **Mapbox Geocoding** | Alta em áreas urbanas; média no interior. | Termos de Serviço Mapbox (Comercial). | Atribuição de logo Mapbox e OSM obrigatória na UI. | 100.000 requisições gratuitas/mês; US$ 0,75 por 1.000 adicionais. | Processamento em nuvem global (EUA/AWS). Rastreamento de telemetria por padrão (requer opt-out). | **Restrito:** Cache permanente é proibido ("Temporary Geocoding"); "Permanent Geocoding" custa o dobro. |
| **Google Places / Geocoding** | Altíssima em quase 100% dos municípios brasileiros. | Google Cloud Platform Terms of Service (Proprietário restritivo). | Logo Google obrigatório em qualquer autocomplete ou mapa. | US$ 200 crédito mensal. Places Autocomplete: ~US$ 17-30 por 1.000 sessões. | Baixa para soberania: telemetria extensiva, perfilamento de contas e exigência de vinculação a cartão/faturamento. | **Proibido expressamente:** É vedado por contrato armazenar qualquer dado do Google Places por mais de 30 dias em cache. |

---

## 3. Análise Detalhada dos Critérios

### 3.1. Cobertura no Brasil e Precisão
- Para localização formal de endereços postais (Rua + Bairro + Cidade + UF), o Brasil possui uma estrutura postal indexada por CEP. O serviço central `aculta_portal_cep` cobre mais de 98% dos endereços brasileiros através da base DNE/ViaCEP/BrasilAPI.
- Para nomes de locais culturais (ex.: "Teatro Municipal", "Parque Vaca Brava", "Circo Voador"), OpenStreetMap possui cobertura rica nos centros culturais onde eventos ocorrem.
- Google Maps possui a maior cobertura de pequenos estabelecimentos comerciais, mas impõe termos inaceitáveis de retenção de dados e custo escalonado em moeda estrangeira.

### 3.2. Termos de Licença e Atribuição
- O uso de OpenStreetMap exige a exibição clara da atribuição: `© OpenStreetMap contributors`. Isso se alinha perfeitamente com os princípios de transparência e cultura livre do coletivo ACULTA.
- Google Maps e Mapbox impõem lock-in: os dados geocodificados não podem ser exibidos em mapas que não sejam os de suas próprias bibliotecas sob pena de quebra de contrato.

### 3.3. Cacheabilidade e Soberania de Dados
- Google Places proíbe armazenamento em cache local por mais de 30 dias (Cláusula 3.2.3 dos Termos Adicionais do Google Maps). Se o ACULTA armazenasse o resultado de um local no banco de dados para associar a um evento anual, estaria violando a licença do Google.
- OpenStreetMap e Nominatim/Photon em modelo self-hosted ou com cache respeitando ODbL permitem persistir coordenadas e nomes de locais no modelo de dados do `aculta_event_location` indefinidamente.

### 3.4. Privacidade e Conformidade LGPD
- O envio de termos digitados caractere por caractere (autocomplete) para provedores estrangeiros pode vazar endereços residenciais de organizadores ou dados sensíveis de locais de eventos comunitários.
- A arquitetura ACULTA prioriza consultas iniciadas pelo servidor (backend proxy) com rate-limiting, sem expor tokens no frontend público e sem transferir dados de navegação de associados a terceiros.

---

## 4. Recomendação Técnica para Fases Futuras

1. **Fase Atual (Vigente):**
   - **Nenhuma implementação de busca por logradouro.**
   - Manutenção exclusiva do fluxo via `aculta_portal_cep` (CEP primeiro, país `BR` fixo).
2. **Fase Recomendada para Maps/Events (Pós-Homologação):**
   - **Abordagem Híbrida em Duas Camadas:**
     - **Camada 1 (Endereço Formal):** Continua via `aculta_portal_cep` para resolução de logradouro a partir do CEP.
     - **Camada 2 (Busca de Locais Culturais e Pontos de Interesse):** Implementação de microsserviço local ou consumo controlado de instância dedicada do **Photon / Nominatim OSM** hospedada na infraestrutura do coletivo.
   - **Requisitos Pré-Implementação:**
     - Aprovação expressa do responsável quanto aos termos de uso da infraestrutura e atribuição no tema.
     - Contrato de cache e sanitização de dados no backend Drupal.
     - Isenção absoluta de qualquer interferência em Conta e Checkout.
