## Contributions — estado local em 2026-11-01

- Revalidação do checkout de desenvolvimento: `EventOrganizationLifecycleKernelTest`
  passou 29/941, incluindo rejeição de título e Data duplicados; os cenários
  `testEventUpdateContribution*` passaram 6/171 (24 depreciações Core/contrib).
  Sem alteração Runtime.

- Organização sem titular: propostas de perfil e criação sob revisão; claims,
  titularidade, membros e edições de perfis reivindicados continuam nos owners
  existentes.
- Local: proposta privada de criação/atualização, endereço central BR, allowlist,
  conflito por versão e bloqueio de duplicidade provável. Criação aprovada é
  draft e não associa Evento automaticamente.
- Resultado: evento mínimo e inbox Messenger privado; enqueue após commit com
  outbox KeyValue recuperável pelo cron. Sem payload privado e sem email/digest.
- Kernel Contributions/Events + fronteira **37 testes / 1.275 assertions PASS** (24
  depreciações Core/contrib); fila/outbox e consumer Messenger **9 testes / 27
  assertions PASS**; update idempotente do campo de aviso **1 teste / 13 assertions PASS**.
  Updates Messenger 10015–10016 estão aplicados; jornada autenticada Events
  continua pendente.
- Eventos têm adapter `events_event` parcial: formulário em
  `/culturas/propostas/evento/nova` submete `event_create` como Contribution
  privada com Datas e referências allowlisted; a aprovação gera rascunho
  atribuído ao autor. `event_update` aplica seleção explícita e adiciona Datas
  sem substituir as existentes. Revalidação de referências, datas e fingerprint
  ocorre na aprovação.
- Submissão e decisões agora usam o evento mínimo e o consumer Messenger
  compartilhado; não carregam payload ou parecer. A interface de autor para
  proposta `event_update` está em `/culturas/eventos/{aculta_event}/propor-alteracao`;
  conserva o CRUD oficial e grava só Contribution privada. Permanecem pendentes
  revisão ampla da matriz de duplicidade, cobertura adicional de conflitos e
  homologação autenticada no Runtime; updates 10015–10016 estão aplicados.
  O workspace privado e o aviso para
  Organização, Local e Evento agora usam a mesma rota privada do workspace.
  Regressões focais de workspace passaram em `EventOrganizationLifecycleKernelTest`
  (3/111), e purpose da rota em `EventDomainBoundaryKernelTest` (1/100).
- `EventOrganizationLifecycleKernelTest`: 23 testes / 747 assertions PASS,
  24 depreciações Core/contrib após revalidação de capability e Entity Access
  do autor no adapter de Evento. Após ampliar o workspace para Organização e
  Local, a classe passou com 23 testes / 753 assertions; `EventDomainBoundaryKernelTest`
  passou com 10/369. Após ressubmissão estruturada e proposta inicial, os Kernels
  combinados das duas classes passaram com 36/1.229.

## LOC-00 a LOC-08 - status revalidado no HEAD da PR #299 (2026-10-30)

[Plano LOC](../../../../../../../docs/architecture/PLANO-LOCAIS-ACULTA-EVENTS-INDEX.md) e [análise comparativa OpenCulturas](../../../../../../../docs/architecture/ANALISE-COMPARATIVA-OPENCULTURAS-ACULTA-STUDIO-CULTURAS.md). A documentação anterior descreve o baseline pré-implementação; o código da PR agora contém o fluxo integrado. Esta revisão não executou a suíte nem acessou o Runtime: classifica código presente separadamente de teste executado e homologação.

| Fase | Estado no código do HEAD auditado | Evidência no repositório | Falta para concluir |
| --- | --- | --- | --- |
| LOC-00 | Inventário de código e ownership refeito; Runtime/sync ainda não revalidado nesta revisão | Entidades, rotas, formulários e testes do `aculta_events` | Confrontar config ativa e conteúdo Runtime com segurança e autorização |
| LOC-01 | ACL, estado editorial e precisão de endereço implementados | `EventLocationAccessControlHandler`, `EventPermissionPolicy`, testes de acesso/local/Maps | Reexecutar gates e validar todas as saídas/cache no Runtime |
| LOC-02 | CRUD e páginas/diretório de Local presentes | `EventLocationForm`, rotas `organizer_location_*`, `LocationPageController`, `LocationListController` | Homologar formulários, acessos e agenda pública integrada |
| LOC-03 | Fluxo de seleção/cadastro em popup Drupal/AJAX presente; Data fica subordinada ao Evento | `EventLocationModalWidget`, `EventLocationModalForm`, `EventForm`, `EventDateCreateForm`, testes Kernel | Revalidar estado do formulário pai, teclado/foco e bloqueio por purpose no navegador |
| LOC-04 / IDX-LOC | Providers, relações derivadas, manutenção incremental e URLs canônicas presentes | `EventContentIndexProvider`, `LocationContentIndexProvider`, hooks e testes Kernel | Validar geração/consulta no ambiente de teste sem escrita não autorizada no Runtime |
| LOC-05 | Detalhe e catálogo de Local; agenda de 30 dias integrada ao `aculta_calendar`, filtrada por Data > Local | `LocationPageController`, `CalendarQuery`, provider Events, `CalendarAgenda`; Kernel cobre recorrência, cancelamento, reagendamento, Local privado e cache por usuário | Homologar A/B, propósito/host, histórico, links/ICS contextuais e navegação |
| LOC-06 | Implementação parcial presente: dashboard `/culturas`, criação/edição de Eventos, Datas/Locais e papéis configuráveis | `EventOrganizerDashboardController`, rotas `organizer_*`, `EventForm`, `EventSettingsForm` | Melhorar dashboard, validar autorização/domínio, visual, mobile, acessibilidade e Runtime |
| LOC-07 | Migrador e testes de migração existem; execução Runtime não faz parte desta revisão | `ActivityLocationMigrator`, update/CLI e `ActivityLocationMigrationKernelTest` | Ensaio autorizado, snapshot/hash, contagem, rollback e decisão de corte dos legados |
| LOC-08 | Não concluída | Testes focalizados existem; a análise comparativa não executou testes | Executar gates integrados e homologação visual/runtime no HEAD consolidado |

### Revalidação LOC-05 — 2026-10-31

- `EventLocationManagementKernelTest` agora inspeciona as datas efetivamente
  renderizadas na página do Local: recorrência, cancelamento, reagendamento e
  exclusão de um Evento associado a outro Local. O novo caso passou com 22
  assertions; PHPCS e PHP lint do fixture passaram.
- Suíte completa Unit/Kernel de Events: **129 testes / 2.557 assertions PASS**
  em `aculta_simpletest`, com 51 depreciações Core/contrib. A bateria focada de
  Calendar/Events (agenda, ICS, provider e gestão de Local) passou com 22 testes
  / 296 assertions; a execução isolada do novo caso também passou.
- Sem escrita ou importação no Runtime. A/B, propósito/host, histórico,
  navegação e eventual ICS contextual continuam pendentes em LOC-08.

### Revalidação funcional de privacidade — 2026-11-01

- Página pública do Evento omite Local privado para quem não tem view access,
  inclusive endereço no JSON-LD; o owner autorizado vê o Local com cache
  variando por usuário.
- Agenda e itens ICS públicos omitem nomes de Locais privados; alterações de
  Local invalidam a cache do calendário. A agenda filtrada por Local (LOC-05)
  agora usa `CalendarQuery`/`CalendarAgenda`, cobre até 30 dias e exige acesso ao
  Local. Em Local privado, owner autorizado vê a agenda com cache por usuário;
  outra conta recebe 404. O feed ICS contextual por Local foi implementado na
  microfase seguinte; homologação de purpose/runtime e navegação integrada
  continuam pendentes.
- O teste revelou e corrigiu dois erros 500 na página de Evento:
  `getCoOrganizers()` não existe no contrato de Event, e a organização não tem
  route ID `canonical`. A composição usa as referências reais e
  `aculta_events.organization_page`.
- Kernel focal em `aculta_simpletest`: 15 testes / 321 assertions PASS; 15
  depreciações upstream. Runtime não foi alterado.

### Exportação ICS contextual por Local — 2026-11-01

- A rota canônica `/locais/{aculta_event_location}/agenda.ics` foi adicionada
  no purpose `eventos`, com parâmetro de entidade owner e link na página do
  Local. Consulta o `CalendarIcsExporter` compartilhado, restringe a janela aos
  próximos 30 dias e seleciona apenas o provider `aculta_events` filtrado pelo
  ID do Local; séries, cancelamentos e reagendamentos continuam no motor
  `aculta_calendar`.
- O controller repete o Entity Access de `view` antes de serializar. Owner pode
  exportar Local privado; outra conta recebe 404. Resposta `private, no-store`
  e `X-Robots-Tag`; não inclui eventos de outro Local nem itens LMS.
- `EventIcsControllerKernelTest`: 3 testes / 54 assertions PASS. Suíte completa
  Events: 132 testes / 2.599 assertions PASS em `aculta_simpletest`, com 51
  depreciações Core/contrib. Matriz HTTP de purpose/host ainda pendente.

Versões e status de release permanecem Dev até fechar os gates exigidos. A presença de rota, classe ou teste no repositório não prova execução bem-sucedida, ativação no Runtime ou homologação visual.

### Agenda de Local pelo provider compartilhado — 2026-11-01

- `LocationPageController` não consulta mais `EventDate` diretamente para montar sua lista: chama `CalendarAgenda` com uma consulta que seleciona somente `aculta_events` e filtra o ID de Local na relação da Data.
- A janela padrão exibe hoje até 30 dias. O motor compartilhado expande séries e aplica cancelamentos/reagendamentos; itens preservam link para o Evento.
- A consulta contextual usa Entity Access na conta corrente. Local privado aparece apenas ao owner/editor autorizado, com contexto de cache `user`; Local privado negado não retorna itens. Locais de outras páginas não entram na agenda.
- Ajustado o fixture existente de `EventAdminOverviewController`: instala o schema de Organização e verifica as quatro métricas/nove ações que o controller já renderiza.
- Suíte completa Events: **128 testes / 2.528 assertions PASS** após corrigir o fixture administrativo; suíte focal após revalidar o acesso: **19 testes / 380 assertions PASS**. PHPCS focal e `git diff --check` PASS; Runtime não foi alterado.

## Integração arquitetural OpenCulturas - ARQ-01 a ARQ-04 (2026-10-29)

Fonte: [plano normativo](../../../../../../../docs/architecture/PLANO-ARQUITETURAL-OPENCULTURAS-ACULTA-ARQ-01-09.md).
- [x] **ARQ-01 (IMPLEMENTADO):** formalizado `EventLocationAccessControlHandler` em `aculta_event_location`, cobrindo leitura pública, mutações restritas a administradores e respeito a hooks de acesso. `LocationCatalogue::busca()` corrigido para filtrar por `access('view')` do usuário atual. Validação de privacidade preservada (aproximado vs exato sem vazamento de rua/CEP). Testes Kernel cobrindo anônimo, usuário autenticado e admin (`LocationCatalogueKernelTest`, `LocationPurposeAccessKernelTest`, `MapControllerKernelTest` PASS).
- [x] **ARQ-02 (IMPLEMENTADO):** `EventCatalogue::busca()` otimizado com eliminação de consultas N+1 via carregamento em lote de datas (`IN` por lote de IDs de eventos), introdução de suporte a paginação (`$limite`, `$offset`) sem quebra de retrocompatibilidade, e preservação estrita da recorrência ACULTA, timezones, cancelamentos e ACL. Teste Kernel `EventCatalogueKernelTest` cobrindo paginação e ordenação PASS (6 testes, 97 asserções).
- [x] **ARQ-03 (IMPLEMENTADO):** integrados providers `EventContentIndexProvider` (`aculta_event`) e `LocationContentIndexProvider` (`aculta_event_location`) na infraestrutura transversal `aculta_index` com tag `aculta_index.provider`. Purpose `'eventos'` preservado, rascunhos confinados (não indexáveis), e relações estruturadas com tipo de evento, organizador e tipo de local. Teste Kernel `EventContentIndexProviderKernelTest` PASS (2 testes, 50 asserções).
- [x] **ARQ-04 (IMPLEMENTADO):** modelo de dados estruturados aditivos implementado para eventos e datas (`field_classificacao_etaria`, `field_acessibilidade`, `field_seguranca` em `aculta_event`; `field_modo`, `field_link_virtual`, `field_abertura_portas` em `aculta_event_date`). Update idempotente `aculta_events_update_10005` criado para instalação segura e reversível sem rebaixar bibliotecas nem forçar Smart Date. Teste Kernel `EventStructuredDataKernelTest` PASS (2 testes, 34 asserções).

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
- [x] Revisão técnica de termos/cobrança concluída em `aculta_maps/docs/REVISAO-TERMOS-MAPAS-2026-10-28.md`: D-46 resolve o geocodificador — Nominatim para qualquer mapa, sem persistir resultados Google, com atribuição OSM/ODbL sobre mapa Google. A chave Google Geocoding permanece sem uso pela aplicação. D-52 veda aviso inline sobre IP; a minuta da política 0.6 descreve tiles e geocodificação, aguardando revisão e publicação autorizada no Runtime.
- [x] Auditoria do OpenCulturas (`AUDITORIA-OPENCULTURAS.md`): nenhum arquivo copiado diretamente; achados S1 a S8 e T1 a T14 viram tarefas de F2–F6.
- [x] Revisão de licenças: OpenCulturas GPL-2.0-or-later e trechos derivados registrados em `ORIGEM-OPENCULTURAS.md`; Phosphor não é usado; Bootstrap Icons tem licença MIT.

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
- [x] `ContentPurposeResolver` reconhece as rotas canônicas de evento, data e local como `eventos`; Unit usa data provider para os três tipos (`ContentPurposeResolverEventsUnitTest`). As rotas funcionais do submódulo declaram `_aculta_domain_purpose: eventos`.
- [x] Cobertura Kernel de purpose, acesso e datas: `LocationPurposeAccessKernelTest` confirma 404 fora do purpose; `EventAccessKernelTest` cobre rascunho/publicação e permissão; `EventDateHooksKernelTest` cobre filtragem e cache das datas.

Saída: cadastro funcional em teste, sem view pública.

## Fase F3 — Catálogo de eventos

Entrada: F2 concluída.

- [x] Catálogo `/eventos` com filtros por tipo e período (dd/mm/aaaa). **Desvio:** controller com formulário de filtro, não View. A View exigiria config e tema, e fica para a etapa visual (F5).
- [x] Cartão de evento como SDC em `aculta420` (`VISUAL.md`), consumido via `PortalUiHooks`; títulos H2 abaixo do H1 do catálogo. O restante da homologação visual continua com a frente do tema.
- [x] Página `/eventos/{slug}` com datas, local, tipo e organizador. Rascunho e inexistente: 404.
- [x] Slugs em português, sem alias temporário (`EventSlugGenerator`: transliteração, hífen, sufixo -2 para repetido, estável depois de criado).

Saída: catálogo e página do evento navegáveis. Cartão em SDC e validação no tema ACULTA ativo ficam para a etapa visual.

## Fase F4 — Locais com mapa

> **D-19:** o mapa e a geocodificação pertencem ao submódulo `aculta_maps`. MP0–MP7 estão concluídas; `MapController` permanece em `aculta_events` por montar os dados do catálogo e a rota `/mapa`. O Runtime de teste está ativado com Leaflet/OpenStreetMap como padrão. D-52 veda aviso inline de IP; a minuta da política 0.6 agora descreve tiles e geocodificação. Publicação no Runtime, revisão jurídica e aprovação institucional permanecem pendentes. Evidências detalhadas em `web/modules/custom/aculta_portal/modules/aculta_maps/docs/`.

Entrada: F3 concluída. Configuração do mapa conforme D-18 e ADR-030: Leaflet com OpenStreetMap público como padrão e fallback; Google Maps API opcional, com a chave em `aculta_secrets`; link de rota do Google sem API; URL de rota configurável.

- [x] Campo de coordenadas e endereço; regra de precisão do endereço (commit `fd70ddd8a`).
- [x] Geocodificação no servidor (Geocoding API), uma vez por local, com a chave fora do repositório (commit `f761d8593`).
- [x] Bloco de mapa Leaflet/OSM, agrupamento próprio de marcadores e lista sincronizada (`58359728f`, `fd5c96526`).
- [x] Mapa na página, com aviso e link para `/sobre/politica-de-privacidade` (`58359728f`). A minuta 0.6 cobre tiles/provedores; ainda não foi publicada no Runtime.
- [x] **Painel administrativo de opções do mapa** (ADM7), em `/painel-administrativo/mapas` (`cac0efbba`):
  - engine: `openstreetmap` (padrão) ou `google`, com o fallback automático para o OpenStreetMap quando a chave faltar ou a API falhar;
  - estado da chave `GOOGLE_MAPS_API_KEY` no `aculta_secrets` (mostra "configurada" ou "ausente", nunca o valor);
  - centro inicial e zoom (inicial e ao mostrar um local);
  - modelo da URL de rota do Google, sem API (padrão: `https://www.google.com/maps/dir/?api=1&destination={destino}`), editável;
  - permissão própria e teste Kernel de acesso negado para usuário comum;
  - teste Kernel: salvar a configuração e confirmar que nenhum valor de chave aparece no config export.
- [x] Link de rota do Google sem API, aberto em nova aba só por clique, sem carregar script do Google (`33b638ccf`; teste Kernel).
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
- [x] Horário em texto livre (`EventLocation::field_horario`, `string_long`), **não estruturado**.
- [ ] Horários estruturados equivalentes a `field_office_hours` (dia/intervalo/exceção); avaliar Office Hours versus solução própria antes de decidir dependência.
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
- [x] E4 (D-28): formulário administrativo de nova data em `/painel-administrativo/eventos/datas/adicionar`; preenche somente referência a evento com acesso de atualização autorizado. Kernel cobre editor, usuário comum, ID inexistente e rascunho não editável. **Aposentado em 2026-10-30:** a rota global foi removida e a criação passou para `/painel-administrativo/eventos/{aculta_event}/datas/adicionar`, sempre no contexto do Evento.
- [x] Incorporação por iframe em `/calendario/incorporar`, com `Content-Security-Policy: frame-ancestors` explícito (lista dos hosts `*.aculta.org` e `*.aculta.toca.net.br`) e `noindex` no ambiente de teste (commit `224c3925e`).
- [x] Smoke visual em `1280`, `390` e `320` px reais na lista de eventos, na página de evento, na agenda, na grade do mês, no dia e na incorporação, com modo escuro: `tests/browser/eventos/agenda-embed.mjs`, 126 ok, 3 ignoradas e 0 falhas (EV-M4, commit `e33636144`). O mapa depende de local com coordenadas (ignorado sem uma).
- [x] Calendário também no `main` (D6): agenda, mês, dia, `.ics`, embed e visão interativa; canonical do purpose `eventos` pelo ACULTA Deployer no ambiente ativo. Runtime de teste: seis rotas HTTP 200 nos dois hosts, canonical único, sitemap lista apenas a URL canônica de `eventos`. Código: `9800e0d62`.

Saída: calendário e exportação em teste, nos dois purposes (`main` e `eventos`).

## Itens de incorporação do OpenCulturas ainda não concluídos

- [x] Formatter de datas futuras (T5, commit `56725c71d`), sem smart_date e sem cron de invalidação.
- [x] E3: datas passadas fora das listagens, com cache até a próxima data (commit `8bd6d832b`). `viewsPostExecute` virou `#[Hook('views_post_execute')]` em `EventDateHooks`; o `nodeViewAlter` não tem equivalente em entity view no ACULTA e a regra vive no builder da página e do catálogo (`UpcomingDates`). Sem cron. A tag `aculta_event_list`, antes declarada e nunca invalidada, agora é invalidada por hook ao salvar ou excluir evento e data.
- [x] E4 (D-28, commit registrado no plano de incorporação): pré-preenchimento de nova data a partir de um evento. Regras: copiar **só a referência ao evento** (`event_id`), **só de eventos que o usuário pode editar**, sem copiar pessoas nem clonar parágrafos. Kernel confirma acesso permitido, acesso negado e ID inexistente. Implementação original da regra; `nodePrepareForm` não foi copiado (S1).
- [x] E2 (views e exportação): locais e datas relacionadas em português, acesso administrativo e campos mínimos; Kernel nega acesso comum e fixa a allowlist de campos. CSV de datas em `/painel-administrativo/eventos/exportar` usa `CalendarItemSource` e `CalendarOccurrences`, restrito aos itens `aculta_events`, janela máxima de 366 dias, sem telefone, e-mail ou endereço; neutraliza fórmulas de planilha. Suíte Events verificada em 2026-10-28: 57 testes, 671 assertions e 26 depreciações. Views ativas no Runtime de teste; ambas negam acesso anônimo e o status focal config/sync não apresenta diferenças. Commit de sincronização `0f1f384d8`.
- [x] E9 (I-15): código do FAQ reescrito em `aculta_faq`, com atribuição em `ORIGEM-OPENCULTURAS.md` (D-25). A origem não tinha conteúdo FAQ editorial versionado; as seis respostas ACULTA foram revisadas, aprovadas e publicadas como Nodes 567–572 no Runtime de teste (sem publicação em produção).
- [ ] E10 (D-45): E10-A/B implementam OAuth2 no popup, token cifrado e assistente Find → Select → Review → Save. O checkout de teste usa a API de desenvolvimento por padrão; a API ativa está selecionada explicitamente no teste. Credenciais e chave de cifra estão provisionadas apenas em `secrets/`; OAuth ainda não foi concluído e nenhuma edição live foi feita. Cada alteração exige revisão e confirmação explícita. Kernel: 4 testes / 53 assertions, 12 depreciações; D-26 foi revogada por D-45. Seleção explícita `edb7e338f`; ativação/configuração prévia `0f1f384d8`.

## Integração com LMS (D-15)

- [x] `aculta_lms_agenda`: prazos e eventos de turma para matriculados (commit `ac43e7d94`).
- [x] Bloco de próximos prazos e eventos no painel do aluno (commit `9766bb731`).
- [x] Campo `field_turma` em `aculta_event` e acesso restrito a matriculados, conforme `aculta_lms_agenda/docs/INTEGRACAO.md`; Kernel cobre matrícula correta, outra turma e ausência de matrícula (4 testes, 176 assertions; commits `df76938ae`, `86f247d4e`).
- [x] Fechar a exposição em superfícies públicas. `hook_entity_access` revalida membership e permissão do curso; o subscriber protege página e `.ics` individual, o catálogo usa access e cache por usuário, e `hook_simple_sitemap_links_alter()` remove eventos de turma do sitemap. Kernel cobre acesso correto, outra turma, ausência de matrícula, rotas diretas e sitemap; suíte Agenda LMS: 12 testes, 227 assertions e 92 depreciações. Código: `1b932d29c`, `dbf2d94e8`, `3be40be71`.

## Fase F6 — Visual e acessibilidade

Entrada: F3 e F5 concluídas.

- [ ] Componentes SDC em `aculta420` com stories (`VISUAL.md`).
- [ ] Modo claro e escuro por tokens.
- [ ] Teste em 320 px, zoom de 200% e teclado.
- [x] Smoke visual Firefox ESR (`DISPLAY=:0`) em 1280/390/320 px e modo escuro: 129/129 verificações, sem overflow, erros visíveis ou falhas; ocorrências reais estão presentes no Runtime. Evidência em `/home/piradopirata/aculta-suite-out/f6-events`.
- [ ] A captura da página de evento mostra o título repetido duas vezes. O render envolve `EventPageController.php`, arquivo da frente paralela; este smoke não alterou o código. Corrigir com a frente dona.
- [ ] Completar teclado e zoom de 200%; não foram cobertos pelo smoke existente.

Saída: revisão visual aprovada pelo responsável.

## Fase F7 — SEO e indexação

Entrada: F3 e F4 concluídas.

- [x] Sitemap do purpose responde no Runtime de teste; index e filho do purpose retornam HTTP 200 com `X-Robots-Tag: noindex, nofollow, noarchive`.
- [x] Página de evento emite JSON-LD `schema.org/Event`, com datas e estado de cancelamento.
- [x] D-44: URLs da página e do sitemap vêm do mapeamento `domain_transition` do ACULTA Deployer. No Runtime de teste, canonical e sitemap filho usam `eventos.aculta.toca.net.br`; o ambiente segue `noindex`. Código e testes publicados em `721e9453e`.
- [x] `validate-final-sitemap.php` PASS no Runtime de teste; index e sitemap filho respondem HTTP 200, com todos os hosts no conjunto previsto pelo Deployer.

Saída: sitemap e robots conferidos pelo `aculta_deployer`.

## Fase F8 — Piloto de conteúdo

Entrada: F6 concluída.

- [ ] Cadastro piloto no ambiente de teste (dados fictícios ou aprovados).
- [ ] Teste com usuários (organizadores e público).

Saída: lista de ajustes aprovada.

## Fase F9 — Fechamento e RC

Entrada: F0–F8 concluídas.

- [ ] Gates verdes; testes Kernel, Unit e navegador no tema ACULTA ativo.
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
- Imagens de eventos e locais podem informar licença/crédito **opcionais** (D-10); não exigir licença nem filtrar uploads por ela.

## Segunda leva OpenCulturas aplicada a eventos (2026-10-28)

- **I-21** página de evento em cinco regiões (V-9), via UI Patterns Layouts, na F3.
- **I-28** selos de acessibilidade do local e do evento (`content/accessibility-badges`), na F4 e F6.
- **I-30** "próximos eventos" na vitrine e na home de eventos, na F3.

## Revalidação ACL da agenda contextual por Local (2026-11-02)

`EventCalendarItemProvider` foi exercitado com duas contas comuns e uma conta anônima. A conta A consulta o próprio Local privado, não consulta o Local da conta B, e anônimo não recebe dados de nenhuma agenda privada. A fixture usa UIDs 101/102 porque UID 1 tem bypass de acesso no Drupal. `EventCalendarItemProviderKernelTest`: 6 testes / 116 assertions PASS; 13 depreciações Core/contrib. Não houve mudança funcional de produção nem escrita no Runtime.

## Pós-v1 (sem data)

- Ingressos e inscrições: campos informativos atuais não são ticketing. RSVP gratuito e venda paga dentro do ACULTA são frentes futuras distintas; pedidos pagos usam Commerce/Mercado Pago e entram no carrinho integrado conforme D-39, enquanto RSVP não usa checkout na proposta ADR-036. Venda paga bloqueada por parecer fiscal/jurídico e LGPD. Plano atualizado em `docs/PLANO-INGRESSOS-PAGOS.md`.
- Calendário pessoal na conta do usuário.
- Integração com a Revista e o Studio (referência a eventos).

## Fronteira de apresentação (2026-10-29, dev.2)

- O catálogo não chama mais `aculta420:event_card`; emite render arrays Drupal
  e tokens `data-portal-ui`. `MapController` usa os tokens de aviso e link.
- ACULTA420 mapeia os tokens para classes do cartão e do mapa. O módulo não
  contém nome do tema nem classe de design.
- Gate módulo–tema PASS (3397 verificações); Events 55/669 PASS, 26
  depreciações upstream. Confirmar a apresentação no Firefox ESR após servir o
  candidato.

## Incremento OC315 aprovado - matriz de eventos e locais (2026-10-29)

[Auditoria OpenCulturas 3.1.5](../../../../../../../docs/architecture/AUDITORIA-OPENCULTURAS-3.1.5-2026-10-29.md). **Backlog; nenhum campo alterado nesta revisão.** As implementações E0–E10/F0–F9 acima preservam seu estado.

| Ordem | Incremento | Evidência no código | Gate |
| --- | --- | --- | --- |
| OC-EV1 / P0 | ACL de locais | Implementado: a consulta seleciona candidatos e revalida cada entidade por `access('view', $account, TRUE)`; decisões permitidas e negadas integram a cacheabilidade antes de lista/mapa/paginação. | Kernel em 2026-10-31: 6 testes/129 assertions PASS, com owner A, owner B e anônimo. Purpose/Runtime e auditoria dos demais consumidores ainda pendentes. |
| OC-EV2 / P0 | desempenho do catálogo | `EventCatalogue::busca()` carrega eventos + datas e expande em PHP | paginação efetiva, benchmark de recorrência extensa, limites e cache |
| OC-EV3 / P1 | eventos híbridos e informação | OpenCulturas: `field_attendance_mode`, `field_location_virtual`, `field_min_age`, `field_safety_info`, `field_door_time_relative`, `field_event_status` | modelo/tipos acordados, update idempotente, Form API, ACL e tests |
| OC-EV4 / P1 | horário estruturado e acessibilidade | hoje `field_horario` é texto; `accessibility-badges` não recebe dados reais | tipagem, exceções, safe markup, consumer evento/local e busca |
| OC-EV5 / P1 | página real com layout SDC | `EventPageController` usa `#theme=aculta_event_page` e o Twig próprio contém H1; `event-page-five-regions` é candidato | consumidor real, eliminar H1 duplicado sem perder título acessível, desktop/320/zoom/teclado |
| OC-EV6 / P2 | imprensa, apoiadores e pessoas | campos/consumidores ausentes ou parciais | vínculos verificados, autorização de marca e pipeline de imagem |
| OC-EV7 / P2 | geodados | mapa atual é de pontos; `MapController` valida coordenadas numericamente e aceita `0`/`0.0`. | Kernel `MapControllerKernelTest`: 3 testes/57 assertions PASS em 2026-11-01. Polígonos seguem ideia exploratória, não paridade comprovada 3.1.5. |

**Dependências:** indexação cruzada via `aculta_index`/Search API; `aculta_maps` continua dono do renderer; não introduzir novo papel `organizador`, ECA ou `smart_date`. Revisar code owner e autorização antes de qualquer alteração de esquema ou Runtime.

## Backlog complementar OC-A/OC-B - OpenCulturas 3.1.5 (2026-10-29)

Fonte: `docs/architecture/AUDITORIA-OPENCULTURAS-3.1.5-2026-10-29.md`. Preservar E0-E10 e fases F, sem reabrir entregas anteriores.

- [ ] **OC-A/P0:** benchmark e limites/paginação reais de `EventCatalogue::busca()` (expansão em PHP). O contrato de ACL de `EventLocation`, isolamento A/B no `LocationCatalogue` e validação numérica de coordenadas (inclusive zero) estão implementados e cobertos pelos testes acima; homologar purpose/Runtime e auditar os demais consumidores.
- [ ] **OC-A/P0:** tornar eventos/locais descobríveis na busca cultural apenas com grants e purpose corretos; jamais expor evento de turma LMS privada.
- [ ] **OC-B/P1:** modalidade virtual/híbrida, referência online, idade mínima, segurança, horário de portas, status da data, horário estruturado e acessibilidade como dados; instalar novas dependências só após aprovação própria. Updates idempotentes e testes Kernel.
- [ ] **OC-B/P1:** ligar SDCs existentes `accessibility-badges` e `event-page-five-regions` a consumidor real; apurar duplicidade de H1, teclado, zoom 200% e 320 px.
- [ ] **OC-C/D:** organizações verificadas, relações/apoios autorizados e imprensa, respeitando separação de conta/perfil/organização. Sem alterar regras de ingressos, frete ou pagamento nesta onda.

## RC — evidência do candidato (2026-10-29, 0.1.0-rc.1)

- Candidato `89efeb5b7` servido no checkout de teste com `drush cr`.
- Smoke do purpose `eventos`: 129/129 verificações em 1280/390/320 px reais,
  com modo escuro; o mapa não exibe o texto inline de IP e mantém os links da
  Política de Privacidade (D-52).
- Suíte 55 testes/670 assertions PASS (26 depreciações); PHPCS 0 erros e lint
  do `MapController` PASS.
- OAuth do OSM validado e ensaio de escrita fictícia com limpeza: nó
  `14331309082`, changesets `191197897`, `191197899` e `191197900`.
- Próxima etapa: validação Alpha após autorização de release.


## Experiência editorial de Eventos e Locais (Atividades editoriais legadas descontinuadas)

- [x] **Descontinuação de Atividades em favor de Eventos (2026-10-30):**
  - Atividades editoriais legadas (`node:activity` e `/node/add/activity`) foram totalmente descontinuadas em favor exclusivo das entidades nativas de Eventos (`aculta_event`, `aculta_event_date` e `aculta_event_location`).
  - Não confundir com atividades educacionais do LMS nem com o módulo `aculta_activity`.
  - A rota `/node/add/activity` foi aposentada com HTTP 404 e a criação do bundle bloqueada via hook `entity_create_access`.
  - A criação de eventos e locais ocorre exclusivamente dentro de Eventos em `/painel-administrativo/eventos/adicionar`.
- [x] Cadastro e seleção de Local em modal Drupal/AJAX (`EventLocationModalWidget` e `EventLocationModalForm`), reutilizando o adaptador central de endereço e CEP (`aculta_portal_cep`), com Brasil exclusivo sem seletor de país e CEP primeiro.
- [x] Área administrativa própria de Events criada em `/painel-administrativo/eventos` (`EventAdminOverviewController`), exibindo exclusivamente Eventos, Datas e Locais.

Contrato normativo: [ADR-033](../../../../../../../docs/decisions/ADR-033-endereco-busca-e-locais.md).

### Requisito visual do fluxo de Local: popup Drupal

O seletor de Local e o cadastro devem ser popups modais do Drupal sobre o formulário de Activity, com AJAX. Não implementar como campos expandidos inline ou página separada. A entidade/cadastro pertence a Events; Maps consome os mesmos Locais autorizados e não mantém cadastro paralelo. O popup de cadastro tem o CEP como primeiro campo. O popup deve preservar o estado do pai, retornar a referência após confirmação e atender foco, teclado, Escape, mensagens acessíveis e estados de carregamento/erro/vazio.


## Centralização administrativa de Eventos e Datas (decisão 2026-10-30)

Evento é a unidade editorial e administrativa. aculta_event_date permanece uma entidade interna do modelo Evento > Data > Local, mas não deve aparecer como conteúdo independente nem como fluxo separado de “Adicionar data de evento”.

- [x] Na criação de Evento, permitir configurar uma ou mais Datas e associar um Local a cada uma. (`EventForm::buildDateCreationSection()`, com “Adicionar outra data” por AJAX; coberto por `EventFormDatesKernelTest`.)
- [x] Na edição do Evento, administrar suas Datas, horários e Locais dentro do próprio contexto do Evento. (`EventForm::buildEventDatesSection()` lista as Datas do Evento com editar/remover em popup.)
- [x] Remover da navegação e do painel ações globais para adicionar Data sem partir de um Evento. (Link `aculta_events.admin_date_add` e ação da visão geral removidos.)
- [x] Eliminar os links que abrem um formulário de Data como tela editorial independente. Se uma rota técnica for necessária, ela só pode ser usada dentro do fluxo do Evento, com Evento de origem explícito, autorização no servidor e acesso direto sem contexto negado. (Rota `aculta_events.date_add` removida; `event_date_add/edit/delete` exigem Evento e `EventDateRouteAccess`.)
- [x] Abrir o popup Drupal/AJAX de Local no contexto da Data do Evento e preservar os valores não salvos de Evento e Data. (`EventLocationModalWidget` com `wrapper_id` por linha e `EventLocationModalForm` revalidando acesso.)
- [x] Configurar no painel de Eventos os papéis autorizados a criar Eventos e Locais, mantendo content_editor como padrão inicial e aplicando a regra também em submissões diretas. (`EventPermissionPolicy`, `EventSettingsForm` e config `aculta_events.settings`.)

A conclusão técnica de E4 não fecha esta experiência editorial. O gate de conclusão exige ausência de criação/listagem de Datas como tarefas independentes e administração integral a partir do Evento.

**Evidência de implementação (2026-10-30):** `EventDateManagementKernelTest` 6/88 PASS, `EventFormDatesKernelTest` 3/48 PASS, suíte Kernel de Events 76/1216 PASS, `aculta_calendar` 127/431 PASS e Unit de Events 8/19 PASS. A entidade `aculta_event_date` permanece como fonte de calendário, `.ics`, recorrência, embed e página pública. Nenhuma migração de dados foi executada; o Runtime não foi alterado.


## Regra de domínio: admin no MAIN, público em Eventos (decisão 2026-10-30)

**Decisão do responsável:** todo `/painel-administrativo/` responde somente no MAIN. O purpose `eventos` serve as páginas públicas e o calendário; os demais domínios exibem o bloco somente leitura.

- [x] Todo `/painel-administrativo/` (Eventos, Datas, Locais) declara purpose `main` e é negado no host de Eventos com **404** (nunca 302), inclusive POST. (`_admin_route` + `_aculta_domain_purpose: main`; `DomainSiteRequestSubscriber`.)
- [x] Subdomínios não renderizam o toolbar do Navigation nem ações editoriais. (`AppearanceHooks` remove `navigation`/`top_bar`; catálogo e página do Evento não expõem criar/editar.)
- [x] A integração OpenStreetMap também pertence ao MAIN, junto da área administrativa. (Rotas OSM com purpose `main`.)
- [x] Datas e Locais seguem o Evento proprietário; `EventDateRouteAccess` valida o vínculo e o acesso ao Evento.
- [x] As páginas públicas em Eventos não expõem ações editoriais; o admin é acessado no MAIN.
- [x] Bloco somente leitura `aculta_events_upcoming` colocado na sidebar do MAIN. (Config `block.block.aculta420_aculta_events_upcoming`, visibilidade por `aculta_domain_purpose: main`.)
- [x] A caixa consulta somente Eventos publicados e acessíveis, com cache e URLs do ambiente. (`EventCatalogue` + `environmentPathUrl`; cache `user`/`url.site` e tags de evento.)
- [x] Eventos privados do LMS não vazam. (`hook_entity_access` + `access('view')`.)
- [x] Sem duplicação de entidade/catálogo e sem alias/redirect.
- [x] Salvar, editar, remover ou reordenar Data atualiza a lista do Evento por AJAX, preservando os campos não salvos. (`ReplaceCommand('#event-dates-list', ...)`.)
- [x] Ordem editorial por peso (`field_peso`, update `10011`), com a tela "Ordenar datas" no popup.

**Evidência:** `EventDomainBoundaryKernelTest`, `EventDateManagementKernelTest` (AJAX e ordem), `EventFormDatesKernelTest`, suíte Kernel de Events 84/1438 PASS, `aculta_calendar` 127/431 PASS, Unit de `aculta_portal` PASS e homologação Firefox ESR. Update `10011` aplicado no Runtime com snapshot `8e7ee699…`.

## Experiência de organizadores — ACULTA Studio Culturas (ADR-035)

## ACULTA Studio Culturas — decisão e implementação parcial (2026-10-30)

O nome aprovado para a experiência de organizadores é **ACULTA Studio Culturas**. A rota inicial `/culturas` e rotas auxiliares estão declaradas no purpose `eventos`; o host é escolhido pelo DomainPurposeManager. A entrada da Conta pode apresentar apenas link para essa experiência, sem escrita cross-domain. `/studio/culturas` foi removida conforme ADR-038.

- A sessão administrativa técnica continua no domínio principal, em `/painel-administrativo/eventos`, restrita a administradores. Configuração global de permissões, auditoria e operações administrativas permanecem ali.
- ACULTA Studio Culturas é a experiência de organizadores: composição de fluxos do owner `aculta_events`, para criar e gerir Eventos e seus componentes (Datas e Locais), usando Form API, Entity Access e moderação existentes. Não cria entidade, formulário de persistência, permissão ou catálogo duplicado.
- A autorização para criar/editar/publicar Eventos e cadastrar Locais deve ser configurável pela administração de Eventos, com `content_editor` como padrão inicial. A configuração não concede acesso sozinha: permissões efetivas e access handlers do servidor continuam obrigatórios; criar, editar e publicar são capacidades distintas.
- Escritas só são aceitas no purpose `eventos` através de rotas canônicas da experiência. O domínio `account` pode linkar para ela, mas não pode fazer POST nem gravar conteúdo de Eventos. Não introduzir proxy de escrita, alias de rota ou redirect de compatibilidade.
- As telas públicas de Eventos continuam no purpose `eventos`. Outros domínios podem compor exibição somente leitura de Eventos publicados e acessíveis, sempre com link canônico de Eventos e sem expor Eventos privados do LMS.
- Esta decisão substitui qualquer texto anterior que diga que todo trabalho editorial de Eventos só pode ocorrer no MAIN ou que o purpose `eventos` é exclusivamente público. O painel de administração permanece no MAIN; a área do organizador é a exceção explícita e separada em ACULTA Studio Culturas.
- Estado: contrato aprovado e código parcial presente no HEAD auditado; autorização/domínio, navegação, execução de testes no HEAD e homologação ainda precisam ser concluídos.

- [x] Rotas de organizador, dashboard inicial e formulários no owner `aculta_events` declarados no código em `/culturas`.
- [x] Configuração administrativa separada e seleção de papéis para criação de Evento e Local presentes em `EventSettingsForm`; `content_editor` permanece padrão inicial.
- [x] Criação inicial de Evento com Datas; operações de Data contextualizadas ao Evento; seleção/cadastro de Local em popup Drupal/AJAX.
- [ ] Completar autorização efetiva por purpose e revalidar acesso no Runtime, incluindo GET/POST/AJAX e dados de Eventos restritos do LMS.
- [x] Melhorar o dashboard com busca, filtros e paginação; mostrar próxima Data/Local sem quebrar privacidade; escopo ampliado para membros aprovados de organizações e coorganizações.
- [ ] Revisar consistência/recuperação quando Evento e Datas iniciais são persistidos em sequência.
- [ ] Homologar fluxos, acessibilidade, teclado, mobile e atualização documental antes de marcar LOC-06 concluída.

## Ingressos gratuitos e pagos - proposta de integração (ADR-036)

`field_gratuito`, `field_pricing` e `field_pricing_info` continuam informativos; `field_ticketing` é link externo. A entidade estrutural `aculta_event_offer` foi adicionada para ofertas/lotes de Evento, com Data opcional, sem rota pública e inativa por padrão. O update `10015` instala políticas RSVP em Evento/Data com RSVP desligado e capacidade apenas nominal. `RsvpPolicyResolver` valida a política gratuita e os contextos Evento/Data/Lote, falhando fechado para regras incompletas, vínculos divergentes e lotes inativos/pagos; não registra inscrição nem reserva capacidade. Ainda não há endpoint, emissão, venda Commerce, capacidade consumida, emissão pós-pagamento ou check-in.

- **Ownership:** `aculta_events` é dono das ofertas/lotes, inscrições/participantes, emissão e check-in. `aculta_commerce` mantém pedidos e order items pagos; `aculta_pagamentos_mercadopago` mantém o gateway; `aculta_portal` coordena os tipos/contextos no carrinho já existente. Sem gateway, carrinho ou storage financeiro paralelo.
- **Gratuito (proposta):** RSVP sem cobrança, nome e e-mail (D-33/D-40), sem CPF, pedido Commerce, carrinho ou Mercado Pago. Confirmar capacidade atomicamente e emitir credencial consumível uma vez no check-in. Não coletar dados reais em produção antes da revisão LGPD/base legal/aviso/retenção.
- **Pago (proposta):** lote ligado a Evento e Data; pedido Commerce `ingresso`; Checkout Pro; somente confirmação de pagamento validada e idempotente emite o ingresso. Sem frete. Somente pedidos pagos participam do carrinho integrado e seguem a precedência D-39. Em pedidos mistos, o ingresso não exige endereço nem compõe frete.
- **Credencial compartilhada:** RSVP e compra paga convergem em participante/credencial e check-in móvel, preservando estados financeiros distintos. QR leva apenas token opaco assinado; validar no servidor, exigir autorização contextual, gravar uma entrada e bloquear reuso. Não indexar pedido, PII ou token em `aculta_index`.
- **Domínios e interface:** inscrições e compras iniciadas em `eventos`; escrita validada no servidor e nunca intermediada pelo purpose `account`. Administração técnica/global fica no MAIN. Gestão do organizador pertence ao ACULTA Studio Culturas em `/culturas`; o código é parcial e sua homologação continua pendente.
- **Gates financeiros:** venda paga bloqueada até parecer fiscal/jurídico (D-31/D-35), políticas de taxa, estorno e repasse (D-32 a D-34), LGPD e testes em sandbox. Repasse manual por evento; sem marketplace/split na primeira versão.
- **Compatibilidade:** preservar os campos informativos atuais sem migração destrutiva. Quando houver lotes internos, definir precedência de exibição e migração antes de ativar; não inferir uma transação do booleano de gratuidade.

### Decisões ainda abertas

D-60/D-61 resolveram RSVP opcional, escopo configurável de capacidade (Evento/Data/Lote), regras configuráveis de limites, duplicidade, reserva/expiração, espera e cancelamento, inscrição por ocorrência ou série, transferência auditável com invalidação da credencial anterior e ownership das ofertas/lotes/RSVP em `aculta_events`. Não tratar esses itens como decisões pendentes.

Permanecem pendentes o parecer fiscal/jurídico para venda paga, a revisão LGPD/base legal/aviso antes de coleta pública de dados, o contrato de item Commerce e os detalhes acessíveis de emissão/entrega da credencial e categorias de operador do check-in. A entidade de oferta existe, mas não expõe nem executa ticketing.

## Rota da Suite Culturas - ADR-038

LOC-06 passa a acompanhar código implementado e gates de integração pendentes para ACULTA Studio Culturas em `/culturas` no purpose `eventos`. Não criar alias. Rota e dashboard têm implementação inicial; atualizar consumidores e validar permissões por operação, POST incorreto, Dialog/AJAX e navegação antes da homologação. O painel administrativo continua no MAIN.

## Organizações Culturais e Governança de Titularidade (ARQ-05 / D-02 / C-02)

- **Owner:** `aculta_events` é dono da entidade `aculta_event_organization`. `aculta_user` gerencia membros (`OrganizationMember`) e a fila transversal de reivindicações (`OwnershipClaim`).
- **Gestão administrativa:** Restrita ao MAIN em `/painel-administrativo/eventos/organizacoes`, com paginação `pager(20)` e filtros (nome, reivindicação inclusive pendente, revisão de acessos e denúncias UGC). Recuperação emergencial de posse em `/painel-administrativo/eventos/organizacoes/{id}/recuperar`.
- **Modal de seleção e cadastro:** `EventOrganizationModalWidget` e `EventOrganizationModalForm` utilizam o sistema de diálogos nativo do Drupal com AJAX, busca com prevenção de duplicidades, devolução de foco ao gatilho (`data-portal-trigger`), cadastro sem CEP/rua e respeito a `ocultar_localidade`.
- **Governança estrita de reivindicação:** Usuários não-administradores não podem assumir titularidade imediata no cadastro; podem criar organizações como não-reivindicadas ou submeter uma solicitação com justificativa obrigatória (`reivindicada = FALSE`, `reivindicacao_status = 'pendente'`, `titular = NULL`), registrando o pedido em `aculta_ownership_claim` para deliberação da moderação. Nenhum papel de proprietário (`owner`) é concedido antes do deferimento administrativo formal. No deferimento, solicitações concorrentes pendentes para a mesma entidade são automaticamente rejeitadas com nota de auditoria; em caso de indeferimento de pedido único, o status da organização é restaurado automaticamente para `nao_reivindicada`.
- **Protótipo de sugestões de edição para organização sem titular:** código inicial permite a contas que criam Eventos propor campos de perfil, com fila administrativa e revalidação antes da aplicação. Em 2026-11-01, o responsável esclareceu que sugestões em Eventos terão outro caminho. Este fluxo é provisório, não homologado nem para ativar no Runtime; definir o caminho final e então substituir/revalidar o protótipo.
- **Filtro contextual na moderação:** A listagem administrativa de reivindicações (`OwnershipClaimAdminController`) suporta filtros diretos por `target_type` e `target_id`, com contexts de cache em URL, viabilizando link direto da visão geral de organizações para suas reivindicações específicas.
- **Injeção de dependência e desacoplamento:** `EventOrganizationMembersForm` utiliza injeção de dependência via `ContainerInjectionInterface` para gerenciamento de tipos de entidade, membros e reivindicações, eliminando chamadas estáticas `\Drupal::service`.
- **Projeção segura no Content Index:** `OrganizationContentIndexProvider` projeta o perfil da organização preservando a privacidade: o criador técnico nunca é vinculado como titular/owner no índice de entidades não-reivindicadas ou pendentes; o `ownerUid` reflete estritamente o titular oficial deferido. Localidade (UF) é omitida caso `ocultar_localidade` esteja ativo. Endereço postal e CEP nunca são coletados ou projetados para organizações.
