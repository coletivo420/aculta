# Roadmap — ACULTA Eventos (`aculta_events` + `aculta_calendar`)

Roadmap único desta frente. Detalhes de cada fase: `PLANO-IMPLANTACAO.md`.
Versões seguem `docs/operations/RELEASE-LIFECYCLE.md`; módulos novos começam em `0.1.0-dev.1`.

Regra de implantação: nada vai para produção antes de todos os módulos, temas e subtemas desta
frente estarem em RC. Merge em `main` não é deploy.

## Fase F0 — Decisões e pré-requisitos

Entrada: plano aprovado.

- [x] Decisões D1–D11 registradas (`PLANO-IMPLANTACAO.md`, seção 3). D11: commits na PR atual.
- [x] ADR do provedor de mapa: `docs/decisions/ADR-014-provedor-mapa-google-maps.md` (Google Maps como padrão, D7).
- [x] ADR do purpose `eventos`: `docs/decisions/ADR-015-purpose-eventos.md`.
- [ ] Verificar compatibilidade com Core ^11.4 de `smart_date`, `geofield` e `address`.
- [ ] Revisar termos e cobrança da Google Maps Platform (JavaScript API e Geocoding API) e o aviso de privacidade.
- [x] Auditoria do OpenCulturas (`AUDITORIA-OPENCULTURAS.md`): nenhum arquivo copiado diretamente; achados S1 a S8 e T1 a T14 viram tarefas de F2–F6.
- [ ] Revisar licenças: código GPL-2.0-or-later copiado e ícones Phosphor (MIT).

Saída: decisões registradas; nenhuma dependência nova instalada ainda.

## Fase F1 — Purpose `eventos` e infraestrutura

Entrada: F0 concluída.

- [x] Domain `eventos_aculta_org` e `DomainPurposeManager` com o purpose `eventos` (commit `33cde2adb`).
- [x] Rotas-esqueleto com `_aculta_domain_purpose`; `DomainRoutePolicy` ativo. Rota `aculta_portal.eventos_home` (`/eventos`, página provisória), com a raiz do host apontando para ela.
- [x] `deploy.json`: `domain_transition`, `robots_policy` (teste `noindex`, produção indexável), `sitemap` e `environment_bound`. `validate` e `validateProfiles` sem erros.
- [x] `config/sync`: `domain.record`, ação de domínio e alias de teste. Importados no servidor de teste com `config:import --partial` (só esses 3 arquivos).
- [x] `ROUTES-SITEMAP.md` atualizado (identificador `eventos.aculta`).
- [x] **Teste:** DNS `eventos.aculta.toca.net.br` ativo; certificado Let's Encrypt próprio para o host; vhost HTTPS em `sites-available/eventos-aculta-ssl.conf`. HTTP 200 com `noindex`.
- [x] Gates: `validate-environment-addresses.php` (PASS), `validate-no-temporary-route-aliases.php` (PASS), `validate-domain-presentation-contract.php` (PASS, 169 checks). `validate-public-slugs.php` tem 34 falhas de rotas de contrib, pré-existentes e fora do baseline DT-P20; nenhuma em `eventos`.

Saída: `https://eventos.aculta.toca.net.br/` responde no ambiente de teste (página provisória), com `noindex`.

## Fase F2 — Modelo de dados

Entrada: F1 concluída.

- [ ] Tipos `aculta_event`, `aculta_event_date`, `aculta_event_location` (`MODELO-DE-DADOS.md`).
- [ ] Vocabulários `aculta_event_type` e `aculta_location_type`.
- [ ] Permissões e formulários; `ContentPurposeResolver` cobre os tipos novos.
- [ ] Testes Kernel de acesso por purpose e de datas.

Saída: cadastro funcional em teste, sem view pública.

## Fase F3 — Catálogo de eventos

Entrada: F2 concluída.

- [x] Catálogo `/eventos` com filtros por tipo e período (dd/mm/aaaa). **Desvio:** controller com formulário de filtro, não View. A View exigiria config e tema, e fica para a etapa visual (F5).
- [ ] Cartão de evento como SDC em `aculta420` (`VISUAL.md`). Pendente: depende do tema (frente de visual).
- [x] Página `/eventos/{slug}` com datas, local, tipo e organizador. Rascunho e inexistente: 404.
- [x] Slugs em português, sem alias temporário (`EventSlugGenerator`: transliteração, hífen, sufixo -2 para repetido, estável depois de criado).

Saída: catálogo e página do evento navegáveis. Cartão em SDC e validação com ACULTA420 e Olivero ficam para a etapa visual.

## Fase F4 — Locais com mapa

Entrada: F3 concluída; chave de API do Google provisionada para teste (`secrets/`) e restrição por referenciador definida.

- [ ] Campo de coordenadas e endereço; regra de precisão do endereço.
- [ ] Geocodificação no servidor (Geocoding API), uma vez por local, com a chave fora do repositório.
- [ ] Bloco de mapa (Maps JavaScript API + agrupamento de marcadores) e lista sincronizada.
- [ ] Mapa carregado na exibição da página, com aviso de privacidade e link para a política.
- [ ] Filtro de tipo de local.
- [ ] Alternativa textual completa ao mapa.

Saída: `/locais` e `/mapa` funcionam sem JavaScript na lista; o mapa é progressivo.

## Fase F5 — Calendário (`aculta_calendar`)

Entrada: F3 concluída.

- [ ] Visão de agenda (lista) e visão de mês (a partir de `md`).
- [ ] Filtros compartilhados com o catálogo.
- [ ] Exportação `.ics` de uma data e do calendário inteiro (ICS próprio).
- [ ] Incorporação por iframe em `/calendario/incorporar`, com política de `frame-ancestors` decidida.
- [ ] Calendário também no `main` (D6): mesmas rotas de agenda, canonical e sitemap conforme a regra de produção.

Saída: calendário e exportação em teste, nos dois purposes (`main` e `eventos`).

## Fase F6 — Visual e acessibilidade

Entrada: F3 e F5 concluídas.

- [ ] Componentes SDC em `aculta420` com stories (`VISUAL.md`).
- [ ] Modo claro e escuro por tokens.
- [ ] Teste em 320 px, zoom de 200% e teclado.
- [ ] Revisão visual no Firefox ESR (`DISPLAY=:0`), com capturas fora do repositório.

Saída: revisão visual aprovada pelo responsável.

## Fase F7 — SEO e indexação

Entrada: F3 e F4 concluídas.

- [ ] Sitemap do purpose (produção indexável; teste `noindex`).
- [ ] Metadados de página e dados estruturados de evento (schema.org `Event`), se o `json_ld_schema` do ACULTA for usado.
- [ ] `validate-final-sitemap.php` verde no ambiente de teste.

Saída: sitemap e robots conferidos pelo `aculta_deployer`.

## Fase F8 — Piloto de conteúdo

Entrada: F6 concluída.

- [ ] Cadastro piloto no ambiente de teste (dados fictícios ou aprovados).
- [ ] Teste com usuários (organizadores e público).

Saída: lista de ajustes aprovada.

## Fase F9 — Fechamento e RC

Entrada: F0–F8 concluídas.

- [ ] Gates verdes; testes Kernel, Unit e navegador com ACULTA420 e Olivero.
- [ ] Changelogs e `docs/operations/status/` atualizados pelo fluxo do projeto.
- [ ] `ORIGEM-OPENCULTURAS.md` revisado (avisos de licença).
- [ ] DNS e certificado de `eventos.aculta.org` planejados (fora do repositório).

Saída: módulos em RC. Deploy só quando todos os módulos, temas e subtemas estiverem em RC.

## Padrões do Portal reutilizados (2026-10-28)

Origem: `docs/architecture/OPENCULTURAS-COMPARATIVO.md`.

- F3 usa a listagem filtrável padrão (I-01, V-1) no catálogo.
- F3 e F4 usam o bloco de conteúdo relacionado (I-02) para eventos e locais relacionados.
- F4 inclui "como chegar" (I-11): links para provedores de rota, abertos só por clique do usuário.
- F7 inclui JSON-LD de Event e Place (I-12).
- Imagens de eventos e locais declaram licença (I-13).

## Segunda leva OpenCulturas aplicada a eventos (2026-10-28)

- **I-21** página de evento em cinco regiões (V-9), via UI Patterns Layouts, na F3.
- **I-28** selos de acessibilidade do local e do evento (`content/accessibility-badges`), na F4 e F6.
- **I-30** "próximos eventos" na vitrine e na home de eventos, na F3.

## Pós-v1 (sem data)

- Recorrência de eventos.
- Sincronização com OpenStreetMap (`openculturas_openstreetmap`), via integração do ACULTA.
- Venda de ingressos e inscrições (plano próprio, com Mercado Pago e `aculta_commerce`).
- Calendário pessoal na conta do usuário.
- Integração com a Revista e o Studio (referência a eventos).
