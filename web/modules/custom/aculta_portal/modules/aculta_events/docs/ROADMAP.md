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
- [x] Vocabulário `aculta_location_type`, com os 17 tipos do OpenCulturas em português; Kernel confirma criação/listagem e idempotência do update 10003 (`fd70ddd8a`, `82efe4456`).
- [x] Campos informativos `field_gratuito`, `field_pricing`, `field_pricing_info` e `field_ticketing` em `aculta_event_date` (D-29); update 10004, sem fluxo de pagamento. Kernel: persistência dos campos e idempotência do update.
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

> **D-19:** o mapa e a geocodificação passam a ser do submódulo `aculta_maps`. Os itens de mapa desta fase são entregues por ele (MP1 a MP6). `aculta_events` só consome a API pública. Plano: `web/modules/custom/aculta_portal/modules/aculta_maps/docs/`.

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

## Campos do local — paridade com o OpenCulturas

Mapeamento dos campos do tipo `location` do OpenCulturas (`profile/config/install/field.field.node.location.*.yml`).
Um item por campo, com o nome em português e o tipo de dado no ACULTA. Os campos que não existem no ACULTA entram
com o mesmo papel do OpenCulturas. `[x]` = já existe; `[ ]` = a fazer.

- [x] Nome do local (`title`).
- [x] Tipo de local: `aculta_location_type` (vocabulário próprio).
- [x] Endereço, coordenadas e precisão do endereço (`aculta_events`, F4).
- [x] Horário de funcionamento (campo estruturado, equivalente a `field_office_hours`).
- [ ] Nome alternativo (`field_alternative_title`, string).
- [ ] Subtítulo ou chamada (`field_subtitle`, texto curto).
- [ ] Descrição (`body`, texto longo com formatação).
- [ ] Foco ou categorias (`field_category`, referência a taxonomia).
- [ ] Data de início da atividade, "ativo desde" (`field_started`, data).
- [ ] Informações de horário em texto livre (`field_opening_hours`, texto longo), para casos que o campo estruturado não cobre.
- [ ] Informações adicionais (`field_additional_information`, texto longo).
- [ ] Informações de segurança (`field_safety_info`, texto longo). Visível ao público quando preenchido.
- [ ] Acessibilidade (`field_accessibility`, lista de itens com rótulo textual). Alimenta os selos do I-28.
- [ ] Contato (`field_contact_data`, bloco com telefone, e-mail e site). Não aparece em exportação administrativa (S5).
- [ ] Imagem principal (`field_mood_image`, imagem).
- [ ] Imagem de perfil (`field_portrait`, imagem).
- [ ] Logotipo (`field_logo`, imagem).
- [ ] Galeria (`field_gallery`, galeria de imagens, com `aculta_gallery`).
- [ ] Apoiadores (`field_supporters`, referência a apoiadores). Exige autorização registrada (I-27).
- [ ] Menções na imprensa (`field_press_quotes`, bloco com veículo, data e link externo). Ver I-26.
- [ ] Pessoas regulares (`field_people_reference`, bloco com referência a perfil, do `aculta_user`).
- [ ] Permitir reivindicação (`field_allow_claiming`, booleano). **Padrão fechado** (C-02): só o dono altera.
- [ ] Recomendações (`field_recommendations`). Depende de um flag de recomendação que o ACULTA ainda não tem; entra depois.

Campos do OpenCulturas que **não** entram, e por quê:
- `field_reference_title` (título personalizado de seção): é apresentação, e o ACULTA controla isso pelo layout.
- Visões de datas passadas, datas relacionadas, menções e recomendações (`field_past_dates_view_ref` e outros): são views de listagem, não campos. Ficam como blocos de conteúdo relacionado (I-02), não como campos do local.

Regras transversais:
- Todo campo de texto é traduzível e tem `t()` no código (regra do projeto).
- Campo de contato, de segurança e de galeria seguem a política de imagem e de upload (`USER-UPLOADS.md`).
- A reivindicação de posse (`field_allow_claiming`) é decidida pelo dono do local, e não pela moderação, enquanto o padrão for fechado.

> **D-20:** o calendário é o submódulo `aculta_calendar`. Esta fase entrega o **provedor de itens de evento** e as páginas de evento; agenda, grade, `.ics` e embed são do submódulo (CA1 a CA5). Arquitetura: ADR-031.

## Fase F5 — Calendário (`aculta_calendar`)

Entrada: F3 concluída.

- [x] Visão de agenda pública por dia em `/calendario` (commit `7eb8deec1`), agora servida pelo `aculta_calendar`.
- [x] Visão de mês em `/calendario/mes/{ano}/{mes}` e agenda de um dia em `/calendario/dia/{ano}/{mes}/{dia}`, com grade em tabela semântica (`caption`, `th scope="col"`) e link por dia.
- [x] Filtros compartilhados com o catálogo: `tipo` (aculta_event_type) e `tipo_local` (aculta_location_type), definidos pelo provedor de eventos.
- [x] Gerador `.ics` próprio (E6): escape RFC 5545, CR/LF como espaço, horário de Brasília (commits `a26bff741`, `e8e8d0151`). Exportação de período com séries (`RRULE`, `EXDATE`, `RECURRENCE-ID`) em `224c3925e` e de uma data em `/eventos/{slug}/data/{id}/calendario.ics`.
- [x] EV-M3 (D-20, ADR-031, commit `c9b6b8e1f`): agenda, grade, exportação e embed movidos para `aculta_calendar`, com a API `CalendarItem`/`CalendarItemProviderInterface` (tag `aculta_calendar.item_provider`). `aculta_events` registra `aculta_events.item_provider`; não tem mais controller de agenda.
- [x] Allowlist do `source_uri` do embed (E5, commit `7985bc36f`). Rota `/calendario/incorporar` entregue em `224c3925e`, com o purpose `eventos` ativo no servidor de teste.
- [x] Validador (R1) e expansão de série na janela (R2) da recorrência (commits `5f3b61ee7`, `1caa5fc7d`).
- [x] Interface da recorrência (M4, commit `b9144b142`): regra da série em `/eventos/datas/{data}/recorrencia` e edição de uma ocorrência em `/eventos/datas/{data}/ocorrencia/{inicio}` (cancelar, reagendar, restaurar), só para `administer aculta events`. A página do evento liga as duas ações só para administradores e varia o cache por permissões (commit `c4919bbb3`).
- [x] E4 (D-28): formulário administrativo de nova data em `/painel-administrativo/eventos/datas/adicionar`; preenche somente referência a evento com acesso de atualização autorizado. Kernel cobre editor, usuário comum, ID inexistente e rascunho não editável.
- [x] Incorporação por iframe em `/calendario/incorporar`, com `Content-Security-Policy: frame-ancestors` explícito (lista dos hosts `*.aculta.org` e `*.aculta.toca.net.br`) e `noindex` no ambiente de teste (commit `224c3925e`).
- [x] Smoke visual em `1280`, `390` e `320` px reais na lista de eventos, na página de evento, na agenda, na grade do mês, no dia e na incorporação, com modo escuro: `tests/browser/eventos/agenda-embed.mjs`, 126 ok, 3 ignoradas e 0 falhas (EV-M4, commit `e33636144`). O mapa depende de local com coordenadas (ignorado sem uma).
- [ ] Calendário também no `main` (D6): mesmas rotas de agenda, canonical e sitemap conforme a regra de produção.

Saída: calendário e exportação em teste, nos dois purposes (`main` e `eventos`).

## Itens de incorporação do OpenCulturas ainda não concluídos

- [x] Formatter de datas futuras (T5, commit `56725c71d`), sem smart_date e sem cron de invalidação.
- [x] E3: datas passadas fora das listagens, com cache até a próxima data (commit `8bd6d832b`). `viewsPostExecute` virou `#[Hook('views_post_execute')]` em `EventDateHooks`; o `nodeViewAlter` não tem equivalente em entity view no ACULTA e a regra vive no builder da página e do catálogo (`UpcomingDates`). Sem cron. A tag `aculta_event_list`, antes declarada e nunca invalidada, agora é invalidada por hook ao salvar ou excluir evento e data.
- [x] E4 (D-28, commit registrado no plano de incorporação): pré-preenchimento de nova data a partir de um evento. Regras: copiar **só a referência ao evento** (`event_id`), **só de eventos que o usuário pode editar**, sem copiar pessoas nem clonar parágrafos. Kernel confirma acesso permitido, acesso negado e ID inexistente. Implementação original da regra; `nodePrepareForm` não foi copiado (S1).
- [x] E2 (views e exportação): locais e datas relacionadas em português, acesso administrativo e campos mínimos; Kernel nega acesso comum e fixa a allowlist de campos. CSV de datas em `/painel-administrativo/eventos/exportar` usa `CalendarItemSource` e `CalendarOccurrences`, restrito aos itens `aculta_events`, janela máxima de 366 dias, sem telefone, e-mail ou endereço; neutraliza fórmulas de planilha. Suíte Events: 62 testes, 672 assertions. Views ativas no Runtime de teste; ambas negam acesso anônimo e o status focal config/sync não apresenta diferenças. Commit de sincronização `0f1f384d8`.
- [x] E9 (I-15): código do FAQ reescrito em `aculta_faq`, com atribuição em `ORIGEM-OPENCULTURAS.md` (D-25). A origem não tinha conteúdo FAQ editorial versionado; revisão e cadastro do conteúdo ACULTA continuam pendentes.
- [ ] E10 (D-45): E10-A/B implementam OAuth2 no popup, token cifrado e assistente Find → Select → Review → Save. O checkout de teste usa a API de desenvolvimento por padrão; a API ativa está selecionada explicitamente no teste. Credenciais e chave de cifra estão provisionadas apenas em `secrets/`; OAuth ainda não foi concluído e nenhuma edição live foi feita. Cada alteração exige revisão e confirmação explícita. Kernel: 4 testes / 53 assertions, 12 depreciações; D-26 foi revogada por D-45. Seleção explícita `edb7e338f`; ativação/configuração prévia `0f1f384d8`.

## Integração com LMS (D-15)

- [x] `aculta_lms_agenda`: prazos e eventos de turma para matriculados (commit `ac43e7d94`).
- [x] Bloco de próximos prazos e eventos no painel do aluno (commit `9766bb731`).
- [x] Campo `field_turma` em `aculta_event` e acesso restrito a matriculados, conforme `aculta_lms_agenda/docs/INTEGRACAO.md`; Kernel cobre matrícula correta, outra turma e ausência de matrícula (4 testes, 176 assertions; commits `df76938ae`, `86f247d4e`).

## Fase F6 — Visual e acessibilidade

Entrada: F3 e F5 concluídas.

- [ ] Componentes SDC em `aculta420` com stories (`VISUAL.md`).
- [ ] Modo claro e escuro por tokens.
- [ ] Teste em 320 px, zoom de 200% e teclado.
- [ ] Revisão visual no Firefox ESR (`DISPLAY=:0`), com capturas fora do repositório. Sem revisão registrada até aqui (a frente B revisou só a View de álbuns).

Saída: revisão visual aprovada pelo responsável.

## Fase F7 — SEO e indexação

Entrada: F3 e F4 concluídas.

- [x] Sitemap do purpose responde no Runtime de teste; index e filho do purpose retornam HTTP 200 com `X-Robots-Tag: noindex, nofollow, noarchive`.
- [x] Página de evento emite JSON-LD `schema.org/Event`, com datas e estado de cancelamento.
- [ ] Aplicar D-44: URLs da página e do sitemap devem vir do mapeamento `domain_transition` do ACULTA Deployer. O código agora consulta esse mapeamento; falta regenerar e validar no servidor de teste. A página de evento já aponta para o host de teste e o ambiente continua `noindex`.
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

- Venda de ingressos pagos dentro do ACULTA (D-30 a D-41). Usa só `aculta_commerce` e `aculta_pagamentos_mercadopago`, sem repasse automático e com repasse manual por evento. Checkout próprio de Eventos, com entrada no carrinho integrado (precedência LMS > Loja > Eventos > Doações), conforme o aditivo à ADR-013. Sem frete; pagamento na etapa final do Mercado Pago (D-42). CPF só em evento pago. Fases P0 a P7 em `docs/PLANO-INGRESSOS-PAGOS.md`. Depende de parecer fiscal e de LGPD antes de qualquer venda, e a fase P1 (caracterização do carrinho e das rotas globais) precede qualquer mudança no integrado.
- Calendário pessoal na conta do usuário.
- Integração com a Revista e o Studio (referência a eventos).
