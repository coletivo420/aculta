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
- [x] D-13: campo de data com `daterange` do core, sem `smart_date` (incompatível com `doctrine/collections` 3.x via `simshaun/recurr`).
- [x] Compatibilidade com Core ^11.4 de `geofield` (1.67.0) e `address`: verificada no Core 11.4.10 — `EventLocation` usa os dois tipos de campo e as suítes Kernel do módulo passam (36 testes).
- [x] D-14: recorrência própria na v1, com `rlanvin/php-rrule` 3.0 (commit `dcea19c4a`, `7ae9e54e4`). **Autorizada pelo responsável em 2026-10-28** (D-16), junto com `drupal/geofield`; nenhuma das duas precisa ser revertida.
- [ ] Revisar termos e cobrança da Google Maps Platform (JavaScript API e Geocoding API) e o aviso de privacidade.
- [x] Auditoria do OpenCulturas (`AUDITORIA-OPENCULTURAS.md`): nenhum arquivo copiado diretamente; achados S1 a S8 e T1 a T14 viram tarefas de F2–F6.
- [ ] Revisar licenças: código GPL-2.0-or-later adaptado (ORIGEM-OPENCULTURAS.md) e ícones Phosphor (MIT), se usados.

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

- [x] Entidades `Evento` e `Local`, e entidade de data com exceção e expansão de ocorrências (commits `0e6d81397`, `bb0a907c8`).
- [x] Vocabulário `aculta_event_type`, com migração de `event_type` (update 10001, commit `8668fc957`).
- [x] Vocabulário `aculta_location_type`, com os 17 tipos do OpenCulturas em português (commit `fd70ddd8a`).
- [x] Permissões, acesso ao evento e purpose das rotas de evento (commit `5226494e2`).
- [ ] `ContentPurposeResolver` cobre os tipos novos (confirmar).
- [ ] Testes Kernel de acesso por purpose e de datas (confirmar cobertura).

Saída: cadastro funcional em teste, sem view pública.

## Fase F3 — Catálogo de eventos

Entrada: F2 concluída.

- [x] Catálogo `/eventos` com filtros por tipo e período (dd/mm/aaaa). **Desvio:** controller com formulário de filtro, não View. A View exigiria config e tema, e fica para a etapa visual (F5).
- [ ] Cartão de evento como SDC em `aculta420` (`VISUAL.md`). Pendente: depende do tema (frente de visual).
- [x] Página `/eventos/{slug}` com datas, local, tipo e organizador. Rascunho e inexistente: 404.
- [x] Slugs em português, sem alias temporário (`EventSlugGenerator`: transliteração, hífen, sufixo -2 para repetido, estável depois de criado).

Saída: catálogo e página do evento navegáveis. Cartão em SDC e validação com ACULTA420 e Olivero ficam para a etapa visual.

## Fase F4 — Locais com mapa

Entrada: F3 concluída. Configuração do mapa conforme D-18 e ADR-030: Leaflet com OpenStreetMap público como padrão e fallback; Google Maps API opcional, com a chave em `aculta_secrets`; link de rota do Google sem API; URL de rota configurável.

- [x] Campo de coordenadas e endereço; regra de precisão do endereço (commit `fd70ddd8a`).
- [x] Geocodificação no servidor (Geocoding API), uma vez por local, com a chave fora do repositório (commit `f761d8593`).
- [ ] Bloco de mapa (Maps JavaScript API + agrupamento de marcadores) e lista sincronizada. **Parcial** (`f761d8593`): mapa e lista; falta a biblioteca de agrupamento de marcadores.
- [ ] Mapa carregado na exibição da página, com aviso de privacidade e link para a política. **Parcial** (`f761d8593`): mapa e aviso; falta o link, porque o site ainda não tem página pública da política.
- [ ] **Painel administrativo de opções do mapa** (ADM7), em `/painel-administrativo/eventos/mapa`:
  - engine: `openstreetmap` (padrão) ou `google`, com o fallback automático para o OpenStreetMap quando a chave faltar ou a API falhar;
  - estado da chave `GOOGLE_MAPS_API_KEY` no `aculta_secrets` (mostra "configurada" ou "ausente", nunca o valor);
  - centro inicial e zoom (inicial e ao mostrar um local);
  - modelo da URL de rota do Google, sem API (padrão: `https://www.google.com/maps/dir/?api=1&destination={destino}`), editável;
  - permissão própria e teste Kernel de acesso negado para usuário comum;
  - teste Kernel: salvar a configuração e confirmar que nenhum valor de chave aparece no config export.
- [ ] Link de rota do Google sem API, aberto em nova aba só por clique (D-18). Teste Kernel de que a página não carrega script do Google nesse modo.
- [x] Filtro de tipo de local (commit `fd70ddd8a`).
- [x] Alternativa textual completa ao mapa (commit `f761d8593`).

Saída: `/locais` e `/mapa` funcionam sem JavaScript na lista; o mapa é progressivo.

## Fase F5 — Calendário (`aculta_calendar`)

Entrada: F3 concluída.

- [x] Visão de agenda pública por dia em `/calendario` (commit `7eb8deec1`). **Visão de mês não existe** no código.
- [ ] Filtros compartilhados com o catálogo.
- [x] Gerador `.ics` próprio (E6): escape RFC 5545, CR/LF como espaço, horário de Brasília (commits `a26bff741`, `e8e8d0151`). Exportação de período com séries (`RRULE`, `EXDATE`, `RECURRENCE-ID`) em `224c3925e`; exportação de uma data só ainda não existe.
- [x] Allowlist do `source_uri` do embed (E5, commit `7985bc36f`). Rota `/calendario/incorporar` entregue em `224c3925e`, com o purpose `eventos` ativo no servidor de teste.
- [x] Validador (R1) e expansão de série na janela (R2) da recorrência (commits `5f3b61ee7`, `1caa5fc7d`).
- [x] Interface da recorrência (M4, commit `b9144b142`): regra da série em `/eventos/datas/{data}/recorrencia` e edição de uma ocorrência em `/eventos/datas/{data}/ocorrencia/{inicio}` (cancelar, reagendar, restaurar), só para `administer aculta events`. Falta o link para os formulários na página do evento (F6).
- [x] Incorporação por iframe em `/calendario/incorporar`, com `Content-Security-Policy: frame-ancestors` explícito (lista dos hosts `*.aculta.org` e `*.aculta.toca.net.br`) e `noindex` no ambiente de teste (commit `224c3925e`).
- [x] Smoke visual em `1280`, `390` e `320` px reais na agenda e na incorporação: `tests/browser/eventos/agenda-embed.mjs`, 33/33 verificações (commit `e81c841f0`). O Runtime de teste está sem eventos, então cobre carregamento, layout e estado vazio, não a renderização de ocorrências.
- [ ] Calendário também no `main` (D6): mesmas rotas de agenda, canonical e sitemap conforme a regra de produção.

Saída: calendário e exportação em teste, nos dois purposes (`main` e `eventos`).

## Itens de incorporação do OpenCulturas ainda não concluídos

- [x] Formatter de datas futuras (T5, commit `56725c71d`), sem smart_date e sem cron de invalidação.
- [x] E3: datas passadas fora das listagens, com cache até a próxima data (commit `8bd6d832b`). `viewsPostExecute` virou `#[Hook('views_post_execute')]` em `EventDateHooks`; o `nodeViewAlter` não tem equivalente em entity view no ACULTA e a regra vive no builder da página e do catálogo (`UpcomingDates`). Sem cron. A tag `aculta_event_list`, antes declarada e nunca invalidada, agora é invalidada por hook ao salvar ou excluir evento e data.
- [ ] E4: pré-preenchimento de data com checagem de acesso, **se** for necessário. O `nodePrepareForm` original não é copiado (S1).
- [ ] E2: views de locais e de datas relacionadas, sem export com telefone e e-mail (S5).
- [ ] E9 (opcional, I-15): FAQ. Só se o responsável pedir.

## Integração com LMS (D-15)

- [x] `aculta_lms_agenda`: prazos e eventos de turma para matriculados (commit `ac43e7d94`).
- [x] Bloco de próximos prazos e eventos no painel do aluno (commit `9766bb731`).
- [ ] Campo `field_turma` em `aculta_event` e acesso restrito a matriculados, conforme `aculta_lms_agenda/docs/INTEGRACAO.md`. Confirmar a cobertura.

## Fase F6 — Visual e acessibilidade

Entrada: F3 e F5 concluídas.

- [ ] Componentes SDC em `aculta420` com stories (`VISUAL.md`).
- [ ] Modo claro e escuro por tokens.
- [ ] Teste em 320 px, zoom de 200% e teclado.
- [ ] Revisão visual no Firefox ESR (`DISPLAY=:0`), com capturas fora do repositório. Sem revisão registrada até aqui (a frente B revisou só a View de álbuns).

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

- Importação de local a partir do OpenStreetMap (E10, assistente administrativo do OpenCulturas). Fora da v1; bloqueada por parecer de licença ODbL e por decisão de integração. Ver `docs/architecture/PLANO-INCORPORACAO-OPENCULTURAS.md`.

- Recorrência de eventos.
- Sincronização com OpenStreetMap (`openculturas_openstreetmap`), via integração do ACULTA.
- Venda de ingressos e inscrições (plano próprio, com Mercado Pago e `aculta_commerce`).
- Calendário pessoal na conta do usuário.
- Integração com a Revista e o Studio (referência a eventos).
