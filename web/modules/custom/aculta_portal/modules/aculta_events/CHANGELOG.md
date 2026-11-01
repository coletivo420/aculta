## Revalidação 2026-11-01 — ciclo de vida e propostas

- `EventOrganizationLifecycleKernelTest`: **27 testes / 888 assertions PASS**;
  `testEventUpdateContribution*`: 6 / 171. Ambos usam `aculta_simpletest`;
  24 depreciações Core/contrib. Runtime sem alterações.

## Revalidação 2026-11-01 — conflitos de `event_update`

- Reexecutados os casos Kernel de submissão privada, aplicação seletiva, perda
  de Entity Access, membership revogado, Local tornado privado e origem alterada:
  **6 testes / 171 assertions PASS**, 24 depreciações Core/contrib. Runtime não
  alterado.

## Revalidação 2026-11-02 — ACL da agenda contextual

- Novo cenário Kernel com dois owners de Local e anônimo confirma que a agenda contextual não divulga títulos/eventos de Locais privados alheios. UIDs de fixture são 101/102 para evitar o bypass administrativo reservado ao UID 1.
- `EventCalendarItemProviderKernelTest`: 6 testes / 116 assertions PASS; 13 depreciações Core/contrib. Sem mudança funcional de produção ou escrita no Runtime.

# Changelog — aculta_events

## Proposta de atualização de Evento (2026-11-02)

- Dashboard oferece `/culturas/eventos/{aculta_event}/propor-alteracao` somente
  para quem passa Entity Access `update`. O formulário envia `event_update` com
  revisão-base, payload allowlisted e uma Data opcional append-only; a entidade
  oficial permanece intacta na submissão.
- A fingerprint agora é calculada a partir da versão persistida recarregada,
  evitando falso conflito por bool/int versus string entre objeto em memória e
  valor hidratado do banco.
- `EventOrganizationLifecycleKernelTest` e `EventDomainBoundaryKernelTest`:
  37 testes / 1.275 assertions PASS, 24 depreciações upstream. Runtime não
  alterado.

## Aviso de envio de proposta de Evento (2026-11-02)

- `event_create` enfileira aviso privado mínimo após persistir a Contribution.
  O evento inclui somente source, UUID/revisão, destinatário e `submitted`;
  payload e justificativa permanecem fora do Messenger.
- Teste Kernel verifica submissão privada e envelope sem dados do payload;
  validação focal total: 34 testes / 875 assertions. Inbox Runtime depende do
  update Messenger 10016 e não foi homologado.

# Proposta inicial de Evento (2026-11-02)

- Adicionada `/culturas/propostas/evento/nova` para enviar Evento e Datas como
  Contribution privada. A rota exige login/purpose `eventos`; o formulário
  exige a capability atual de criação, usa a allowlist `events_event`, UUIDs
  das referências e normaliza horários para UTC. Nenhuma entidade Event ou
  EventDate é salva antes da aprovação. O dashboard expõe a ação para quem pode
  criar Eventos; o CRUD nativo existente permanece disponível.
- Kernel confirma submissão privada, negação sem capability, horário UTC e
  ausência de Event oficial: 1 teste / 32 assertions. Kernels combinados de
  lifecycle Events e fronteira: 36 testes / 1.229 assertions PASS, com 24
  depreciações upstream. PHPCS, PHP lint, YAML e diff check PASS. Runtime não
  foi alterado.

## Correção — links de avisos de Organização e Local (2026-11-02)

- O manager Messenger já enviava decisões das fontes `events_organization`,
  `events_location` e `events_event` para o detalhe do workspace. O controller
  aceitava somente `events_event`, então notificações válidas de Organização e
  Local terminavam em 404.
- O workspace agora lista e abre as três fontes permitidas, mantendo filtro por
  UID proprietário, Entity Access e rotas privadas de `eventos`. O quadro exibe
  o tipo de contribuição correspondente.
- Suítes completas: `EventOrganizationLifecycleKernelTest` 23/753 (24
  depreciações upstream) e `EventDomainBoundaryKernelTest` 10/369 (18
  depreciações upstream) PASS. PHPCS Drupal Core, lint PHP, YAML e diff check
  PASS. Runtime não foi alterado.

## Complemento local — workspace privado de propostas de Evento (2026-11-02)

- Adicionadas as rotas `/culturas/propostas` e detalhe por UUID para a conta
  autora consultar situação, conteúdo enviado e retorno editorial.
- O controller filtra por proprietário, source Events e Entity Access; outra
  conta recebe 404. O dashboard Culturas liga ao workspace e os avisos de
  decisão Messenger apontam ao detalhe da proposta.
- `EventOrganizationLifecycleKernelTest`: 23 testes / 747 assertions PASS;
  `EventDomainBoundaryKernelTest`: 10 testes / 368 assertions PASS. Ambos com
  depreciações upstream (24 e 18). Teste focado confirmou link do dashboard:
  1 teste / 25 assertions PASS.
- Ressubmissão estruturada após pedido de ajustes e notice no envio continuam
  pendentes.

## Complemento local — revalidação de relações de Evento (2026-11-02)

- Ao aplicar `event_update`, Locais das Datas existentes/propostas são
  recarregados e o access handler é limpo antes de revalidar `view` para o
  autor. Membership revogado em Organização reivindicada também impede apply.
- Kernel cobre ambos os conflitos e confirma que a proposta permanece pendente
  e o Evento inalterado: 23 testes / 744 assertions; 24 depreciações.

## Complemento local — fila e revisão de propostas de Evento (2026-11-02)

- A fila administrativa lista propostas `event_create`/`event_update` e abre a
  revisão seletiva pelo lifecycle de Contributions.
- Rota permanece no MAIN com `administer aculta events`; Content Admin só a
  descobre quando o acesso nativo autoriza. Sem payload privado na listagem.
- `ModeratableCollectionRegistryTest`: 1 teste / 8 assertions; suíte
  `EventOrganizationLifecycleKernelTest`: 21 testes / 691 assertions PASS,
  com 24 depreciações Core/contrib. Validador MAIN-only e aliases, lint PHP,
  YAML e PHPCS dos arquivos alterados passaram. Runtime não homologado.

## Complemento local — aviso de decisão de proposta de Evento (2026-11-02)

- `events_event` agora registra decisões no envelope mínimo já usado por
  Organizações e Locais; o worker e o consumer aceitam o source de Evento.
- Sem payload, endereço ou parecer no envelope. Unit: 11 testes / 32
  assertions; `EventOrganizationLifecycleKernelTest`: 21 / 687, com 24
  depreciações Core/contrib. Inbox Runtime depende do update Messenger 10015,
  que não foi executado.

## Complemento local — propostas de Evento (F6 parcial)

- Registrado adapter `events_event` para criar e atualizar Eventos via ciclo
  privado de Contributions. Campos internos e status de publicação não são
  aceitos; criação aprovada fica em rascunho e atribuída ao proponente.
- Datas propostas são adicionadas sem apagar/reescrever Datas existentes;
  Organização, Local, autor, intervalos e fingerprint são revalidados na
  aplicação. A interface de submissão/workspace do autor e avisos Messenger
  específicos de Evento continuam pendentes.
- Aplicação revalida capability configurável de criação e Entity Access atualizado do autor; o cache do handler é invalidado antes de checar atualização. Testes de regressão cobrem revogação entre envio e decisão.
- `EventOrganizationLifecycleKernelTest`: 21 testes / 681 assertions PASS (24 depreciações Core/contrib); PHPCS e lint PHP PASS. Runtime não alterado/não homologado.

## Complemento local — inbox e recuperação de resultados Contributions (2026-11-02)

- Outcomes de Organizações e Locais levam o source estável no envelope. O
  enfileiramento ocorre após commit da decisão; falhas de Queue API ficam no
  outbox KeyValue para tentativa posterior por `hook_cron()` (até 25 por ciclo).
- Messenger persiste aviso privado e idempotente no inbox, sem payload/endereço/
  parecer; envio por e-mail/digest e workspace do autor seguem pendentes.
- Unit Events/Messenger: 9 testes / 27 assertions PASS. Kernel focal de fila e
  decisão: 1 teste / 46 assertions PASS; a suíte Kernel ampla teve uma
  expectativa antiga de envelope, corrigida e revalidada no teste focal.
- Runtime sem migrações/importação de configuração.

## Complemento local — propostas de criação e atualização de Local (2026-11-02)

- Não administradores podem propor criação/atualização de Local; alterações
  permanecem privadas até decisão. Endereço usa o formulário central, país BR
  obrigatório; coordenadas, OSM, status, titularidade e vínculo com Evento são
  protegidos. Criação aprovada permanece draft e atribuída ao proponente.
- Adicionada fila/revisão no MAIN, descoberta por `aculta_content_admin`, sem
  substituir autorização do owner Events. Duplicidade provável bloqueia a
  aplicação e não faz merge automático.
- Os três testes Kernel focais passaram com 100 assertions. São pendências a
  integração de avisos Messenger para Local, execução de suítes amplas e
  homologação Runtime; o Runtime não foi alterado.

## Complemento local — propostas de criação de Organização (2026-11-01)

- Criação por não administrador, tanto no formulário próprio quanto no modal do
  Evento, passa a gerar proposta privada `organization_create`; o perfil só é
  criado após aprovação administrativa. A proposta não é vinculada ao Evento.
- Após aprovação, a Organização é publicada sem titular e sem membership, com
  autoria atribuída ao proponente. A reivindicação de titularidade continua no
  fluxo próprio e só fica disponível depois da criação aprovada.
- Suítes Kernel completas de lifecycle e modal/admin passaram (13 testes / 437
  assertions e 8 / 266); PHPCS, validadores de rotas/aliases, YAML e índice de
  status passaram.

## 0.2.0-dev.9 — propostas de perfil via Contributions (2026-11-01)

- Novas propostas para perfis de Organizações não reivindicadas usam o ciclo
  compartilhado, com adapter Events, allowlist, versão-base completa e lock da
  Organização. A aprovação aplica só os campos escolhidos; claims, membros,
  publicação e edição direta de Organizações reivindicadas preservam seus owners.
- A fila administrativa lista Contributions novas e sugestões legadas; os
  registros e rotas antigos continuam revisáveis. O Runtime não migrou nem
  removeu registros legados.
- Kernel focal de submissão, privacidade, rejeição de campos de titularidade,
  aplicação seletiva e conflito de versão-base: 2 testes / 75 assertions PASS.
- Dependência ausente do Runtime causava 500 em `/wesativa` e
  `/wesativa/verbetes/novo`; após snapshot, `aculta_contributions` foi habilitado
  e os dois paths autenticados responderam HTTP 200.
- Content Admin agora descobre a fila por sua rota nativa; Events persiste
  resultados mínimos e os publica ao Messenger após revalidar autor/acesso.
  Inbox/digest/email, workspace de ajustes do autor e demais adapters de Events
  permanecem pendentes.

## 0.2.0-dev.8 — propostas moderadas para organizações sem titular (2026-11-01)

- Edição por contas autorizadas a criar Eventos agora gera proposta privada;
  a entidade pública permanece inalterada até aprovação administrativa.
- Adicionada a fila em `/painel-administrativo/eventos/organizacoes/sugestoes`
  e revisão com comparação atual/proposto, aprovação ou rejeição e autoria da
  decisão. Só campos de perfil público são permitidos.
- Aprovação exige que a organização continue sem titular e que sua impressão
  digital pública corresponda ao estado no envio. Locks por proposta e
  organização, transação e revalidação impedem reaplicação e sobrescrita de
  atualização posterior. Sugestões privadas a `administer aculta events`.
- `EventOrganizationLifecycleKernelTest`: cobertura para envio sem mutação,
  proteção dos campos de titularidade, aprovação, rejeição, proposta obsoleta e
  acesso negado; resultado a registrar após o gate desta versão. Update hook
  `10016` instala a entidade de sugestões. Runtime não foi alterado.

## 0.2.0-dev.7 — lint completo do módulo e desacoplamento do formulário de Local (2026-11-01)

- O formulário de Local recebe `EventPermissionPolicy` por injeção de
  dependência, removendo chamadas globais ao container sem alterar as regras
  de criação e publicação.
- Corrigidos os apontamentos de estilo do módulo inteiro. PHPCS Drupal e
  DrupalPractice passou sem erros ou avisos; `git diff --check` passou.
- A suíte Kernel completa passou: 131 testes / 2.765 assertions; permanecem
  51 depreciações Core/contrib preexistentes. Unit: 10 testes / 23 assertions
  PASS. Runtime não foi alterado.

## 0.2.0-dev.6 — resolução fail-closed da política RSVP (2026-11-02)

- `RsvpPolicyResolver` produz uma política interna somente quando RSVP está
  habilitado, os parâmetros obrigatórios estão completos e o contexto de
  capacidade Evento/Data/Lote pertence ao Evento.
- Contexto por Lote exige oferta gratuita ativa; ofertas pagas/inativas,
  capacidades inválidas e datas/ofertas de outro Evento são recusadas.
- O resolver não registra participantes, coleta dados, reserva capacidade nem
  oferece rota pública. RSVP continua fechado por padrão; revisão LGPD segue
  obrigatória antes de qualquer coleta pública.
- `EventOfferKernelTest`: 4 testes / 91 assertions PASS; 12 depreciações
  Core/contrib. A suíte Kernel Events (130 testes / 2.741 assertions) e Unit
  (10 testes / 23 assertions) passaram antes da otimização final da consulta de
  recorrência; após a otimização, o Kernel focal passou novamente. PHPCS,
  validadores de rotas/status e `git diff --check` PASS. Runtime não foi alterado.

## 0.2.0-dev.5 — campos estruturais de política RSVP (2026-11-02)

- Update `10015` instala em Evento/Data a política estrutural D-60: RSVP
  desligado por padrão, escopo Evento/Data/Lote, capacidades nominais,
  limite por participante, duplicidade, duração de reserva, espera,
  cancelamento e inscrição por ocorrência/série.
- Campos permanecem ocultos no formulário do organizador até existir o fluxo
  completo; capacidade não é consumida e nenhuma inscrição/dado de participante
  é armazenado.
- Kernel focal valida defaults, persistência por Data, constraints positivas e
  idempotência do update: `EventRsvpSettingsKernelTest` passou com 2 testes / 37
  assertions e 12 depreciações Core/contrib. Kernel completo Events passou com
  129 testes / 2.709 assertions e 51 depreciações Core/contrib. Runtime não foi
  alterado.

## 0.2.0-dev.4 — modelo estrutural de ofertas/lotes (2026-11-02)

- Adicionada a entidade `aculta_event_offer`, pertencente a Eventos, com Evento
  obrigatório, Data opcional, modalidade gratuita/paga, preço descritivo,
  capacidade nominal, limites e janela de disponibilidade.
- Oferta começa inativa, exige `administer aculta events` e não declara rotas
  públicas ou de edição organizadora. Não implementa RSVP, pedido, pagamento,
  emissão ou check-in.
- Update hook `10014` instala o tipo de entidade em instalações existentes.
- Kernel focal cobre persistência, referências sem duplicar Evento/Data, estado
  fechado por padrão, acesso exclusivo de administradores, constraints de faixa,
  ausência de rotas públicas e idempotência do update hook. `EventOfferKernelTest`
  passou: 3 testes / 57 assertions; 12 depreciações Core/contrib.
- Revisão LGPD e parecer fiscal/jurídico continuam bloqueando coleta pública e
  venda, respectivamente; Runtime não foi alterado.

## 0.2.0-dev.3 — ocultar Local sem acesso no dashboard de organizadores (2026-11-01)

- O dashboard valida `view` para cada Local associado antes de renderizar título
  ou município/UF e agrega os metadados de cache das decisões permitidas e
  negadas.
- Kernel confirma que um organizador pode gerenciar seu Evento sem receber
  dados de um Local privado de outra conta.
- Suíte de Kernel: 123 testes / 2.597 assertions PASS; Unit: 10 testes / 23
  assertions PASS. Foram registradas 51 depreciações de Core/contrib.
- PHP lint, PHPCS Drupal/DrupalPractice focal, validadores de status/fronteira e
  `git diff --check` PASS.
- Runtime não foi alterado; homologação HTTP continua pendente.

## 0.2.0-dev.2 — feed ICS contextual por Local (2026-11-01)

- Adicionada a rota `/locais/{id}/agenda.ics` no purpose `eventos` e link contextual na página do Local.
- O feed usa `CalendarQuery`/`CalendarIcsExporter`, filtra somente o provider `aculta_events`, restringe pelo ID do Local e usa a janela de 30 dias da agenda. Entity Access é revalidado; Local privado de outra conta retorna 404. Respostas `private, no-store` e `noindex`.
- Kernel focal `EventIcsControllerKernelTest`: 3 testes / 54 assertions PASS, incluindo conteúdo de outro Local excluído e Local privado visível ao owner, negado a outra conta.
- Suíte completa Events em `aculta_simpletest`: 132 testes / 2.599 assertions PASS; 51 depreciações Core/contrib. PHP lint, PHPCS focal, parse YAML e verificadores de aliases/purpose PASS.
- Ainda pendem testes HTTP da matriz de purpose/host e homologação Runtime; nenhum Runtime foi alterado.

## 2026-10-31 — reconciliar as decisões D-60/D-61 do ticketing

- ADR-036, plano de ingressos, roadmap e README agora distinguem as regras aprovadas (RSVP opcional, capacidade configurável, inscrição por ocorrência/série, transferência auditável e ownership) das pendências legais e técnicas.
- Ticketing continua sem implementação: a página mantém apenas preço/gratuidade informativos e link externo. Nenhum dado de participante foi coletado; venda continua bloqueada por parecer fiscal/jurídico e revisão LGPD.
- Suíte Events previamente executada em `aculta_simpletest`: 129 testes / 2.557 assertions PASS; nenhum teste ou gate Runtime foi executado nesta atualização documental.

## 2026-11-01 — agenda de Local pelo provider compartilhado

- O detalhe do Local consulta a agenda pelo `CalendarQuery`/`CalendarAgenda` de `aculta_calendar`, limita a origem ao provider Events e filtra Data > Local.
- O calendário expande recorrência e reaplica cancelamentos/reagendamentos; a página mostra os próximos 30 dias e liga cada item à página canônica do Evento.
- Entity Access controla Locais privados por usuário, incluindo isolamento de cache. O gate completo em purpose/Runtime e um feed ICS contextual por Local não foram executados nesta microfase.
- Suíte completa Events: **128 testes / 2.528 assertions PASS** após corrigir o fixture administrativo; suíte focal após revalidar o acesso do Evento: **19 testes / 380 assertions PASS**. PHP lint, PHPCS focal e `git diff --check` PASS; depreciações upstream registradas.

## 2026-11-01 — Privacidade de Locais em Eventos, agenda e ICS

- Página pública do Evento só apresenta nome, endereço, link e Schema.org do
  Local quando a decisão de view permite. Os metadados de cache dos resultados
  permitidos e negados são propagados; o organizador autorizado mantém acesso.
- Agenda e itens usados pelo ICS deixam o Local privado em branco e invalidam
  cache quando um Local muda.
- Corrigidos dois erros 500 da página do Evento: chamada ao método inexistente
  `getCoOrganizers()` e geração de URL canônica de Organização por uma rota que
  não existe. Consumidores usam referências e rota pública reais.
- Kernel de Evento estruturado, CalendarItemProvider e hooks de Data: **15
  testes / 321 assertions, PASS**, com 15 depreciações Core/contrib. PHPCS e
  PHP lint focal também passaram.

## 2026-11-01 — Paginação do catálogo de Locais em lotes

- `LocationCatalogue` carrega no máximo 100 entidades por lote e conserva apenas
  os resultados da página solicitada, sem `loadMultiple()` global seguido de
  `array_slice()`. A contagem continua considerando somente Locais autorizados.
- A ordenação usa título e ID para estabilizar páginas com títulos repetidos;
  índices negativos são normalizados para a primeira página.
- Suítes Kernel de catálogo, mapa e gestão mais Unit do MapRenderer: **20 testes
  / 369 assertions, PASS**; 22 depreciações upstream. PHP lint e PHPCS PASS.

## 2026-11-01 — Propagar cacheabilidade do acesso a Locais

- `LocationCatalogue` agrega os metadados de cache das decisões de view
  permitidas e negadas, incluindo conteúdo privado acessível ao organizador.
- A lista de Locais e o mapa agora incorporam esses contextos e tags; Kernel
  confirma que conteúdo restrito não aparece para anônimos e que a resposta
  varia por usuário.
- Suítes focais de catálogo/mapa: 9 testes / 179 assertions PASS; PHP lint e
  PHPCS Drupal/DrupalPractice PASS. Permanecem 14 depreciações de Core/contrib.

## 2026-11-01 — Preservar coordenadas numéricas zero no mapa

- O mapa agora inclui locais com latitude ou longitude `0` (Equador e meridiano
  de Greenwich); `empty()` tratava esses valores válidos como ausentes.
- A validação numérica e de faixa do `MapRenderer` permanece responsável por
  rejeitar coordenadas inválidas antes de expô-las no navegador.
- Kernel cobre o marcador `0/0`; junto com o Unit do renderer, são 5 testes e
  57 asserções PASS. PHP lint e PHPCS focal também passaram.

## 2026-10-31 — Hierarquia de títulos no catálogo

- Os títulos dos cartões em `/eventos` usam H2 imediatamente abaixo do H1 da
  página; o SDC `content/event_card` mantém a mesma semântica.
- Kernel cobre o contrato da tag de título. Não altera dados, filtro, acesso,
  rotas ou cache.

## 2026-10-30 — Fase 2: Migração e Rollback de Endereços Legados de Activity para Locais

- Implementado o serviço central de migração `ActivityLocationMigrator` (`aculta_events.activity_location_migrator`):
  - `getLegacyCandidates()` e `hasLegacyAddressData()`: identifica de forma segura nós `activity` que contêm campos de endereço legados e ainda não possuem referência preenchida em `field_event_location`.
  - `inspect()`: auditoria e diagnóstico em tempo real, informando total de atividades, já vinculadas, candidatas elegíveis, registros incompletos e estimativa de locais únicos após deduplicação.
  - `migrate(bool $dryRun)`: migração idempotente. Em modo `$dryRun = TRUE`, simula o processo sem gravar no banco; em modo persistente (`FALSE`), cria instâncias de `EventLocation` respeitando as regras de endereço (país fixo `BR`, precisão `exato`, status conforme publicação do nó pai, proprietário herdado), vincula `field_event_location` no nó e reutiliza locais idênticos através de chave normalizada (nome + CEP).
  - `rollback()`: reversão atômica e segura que desvincula as Activities e deleta exatamente as entidades `EventLocation` criadas pela migração, utilizando proveniência persistida no Drupal State (`aculta_events.migrated_activity_locations`).
- Implementado o update hook `aculta_events_update_10012()` em `aculta_events.install`.
- Disponibilizado o script CLI `scripts/migrate-activity-locations.php` com suporte aos modos `--inspect`, `--dry-run`, `--execute` e `--rollback`.
- Cobertura de testes:
  - `ActivityLocationMigrationKernelTest`: 3 testes e 79 asserções cobrindo auditoria diagnóstica, garantia de isolamento do dry-run, criação idempotente, deduplicação de múltiplos nós para a mesma entidade de local e reversão íntegra via rollback.


## 2026-10-30 — Regra de domínio: admin no MAIN, público em Eventos

- **Decisão do responsável:** todo `/painel-administrativo/` responde somente no MAIN. Isso inclui a área de Eventos, as rotas de Datas e Locais e a integração OpenStreetMap. A regra é validada no servidor e prevalece sobre o aditivo anterior que restringia o editorial ao host `eventos`.
- O purpose `eventos` serve as páginas públicas (catálogo, página do Evento, calendário, `.ics`, embed) sem toolbar, sem links administrativos e sem ação de edição.
- Todas as rotas `/painel-administrativo/eventos/*` declaram `_aculta_domain_purpose: main`; em subdomínios elas retornam **404** (nunca 302), inclusive POST. `AppearanceHooks` remove o toolbar do Navigation fora do MAIN.
- A integração OpenStreetMap voltou para o MAIN, junto da área administrativa que a utiliza; o redirect URI do app OAuth deve usar o host MAIN.
- Bloco somente leitura `aculta_events_upcoming` colocado na sidebar do MAIN (`block.block.aculta420_aculta_events_upcoming`), visível apenas no purpose `main`, com URLs de Eventos no ambiente e sem controles editoriais.
- Salvar, editar, remover ou reordenar uma Data atualiza a lista do Evento por AJAX (`ReplaceCommand('#event-dates-list', ...)`), preservando os campos não salvos do Evento.
- Ordem editorial das Datas por `field_peso` (update `10011`), com a tela "Ordenar datas" em popup.
- Testes: suíte Kernel de Events 84/1438 PASS; `aculta_calendar` 127/431 PASS; Unit de `aculta_portal` PASS; homologação Firefox ESR no servidor de desenvolvimento.

## 2026-10-30 — Datas e Locais geridos dentro do Evento (centralização editorial)

- A rota avulsa `aculta_events.date_add` (`/painel-administrativo/eventos/datas/adicionar`) foi **removida**; a URL responde 404. Não há alias nem redirect temporário.
- A criação e a edição de Datas passam a viver no contexto do Evento:
  - `aculta_events.event_date_add` — `/painel-administrativo/eventos/{aculta_event}/datas/adicionar`;
  - `aculta_events.event_date_edit` — `/painel-administrativo/eventos/{aculta_event}/datas/{aculta_event_date}/editar`;
  - `aculta_events.event_date_delete` — `/painel-administrativo/eventos/{aculta_event}/datas/{aculta_event_date}/excluir`.
- As rotas exigem `aculta_event.update` e `EventDateRouteAccess` confirma no servidor que a Data pertence ao Evento da URL. Acesso direto sem Evento autorizado é negado.
- `EventForm` na criação aceita uma ou mais Datas (com “Adicionar outra data” por AJAX) e um Local por Data; na edição, lista as Datas do Evento com ações de editar/remover abertas em popup Drupal (AJAX). Os formulários de Data retornam ao Evento de origem.
- O menu administrativo e a visão geral deixam de expor “Adicionar data de evento” como ação independente; o link da página pública passa a abrir a edição do Evento.
- A entidade técnica `aculta_event_date` permanece no modelo Evento > Data > Local e continua sendo a fonte do calendário, `.ics`, recorrência, embed e página pública.
- Política configurável de papéis para criar Eventos/Locais (`aculta_events.permission_policy`) aplicada nos access handlers, no modal de Local e no servidor; padrão `content_editor`.
- Testes: `EventDateManagementKernelTest` (6/88), `EventFormDatesKernelTest` (3/48), suíte Kernel de Events 76/1216, Calendar 127/431 e Unit 8/19 PASS.

## 0.2.0-dev.1 — centralização exclusiva em Eventos, modal AJAX de Locais e aposentadoria de Atividades (2026-10-30)

- Rebaixamento de versão para a linha de desenvolvimento 0.2.0-dev conforme diretriz do responsável.
- Descontinuação total de Atividades editoriais (`node:activity`) em favor exclusivo de Eventos:
  - Aposentadoria de `/node/add/activity` retornando HTTP 404 via `ActivityRetiredRouteSubscriber`.
  - Bloqueio de criação de entidades do bundle `node:activity` via `ActivityRetirementHooks::entityCreateAccess`.
  - Remoção de links e entradas de atividades do menu administrativo (`aculta_events.links.menu.yml`).
  - Purificação do painel administrativo (`/painel-administrativo/eventos`) com `EventAdminOverviewController` exibindo exclusivamente Eventos, Datas e Locais. A opção de adição fica restrita aos fluxos de Eventos (`/painel-administrativo/eventos/adicionar`).
- Implementado widget modal AJAX nativo Drupal (`EventLocationModalWidget` e `EventLocationModalForm`):
  - Etapa 1: busca por nome de locais existentes acessíveis com ação de seleção e retorno AJAX via `ReplaceCommand` e `InvokeCommand`.
  - Etapa 2: cadastro de novo local reutilizando `AccountAddressForm::apply()` iniciando pelo CEP.
  - Validação estrita no servidor: endereço restrito exclusivamente a Brasil (`BR`), rejeitando submissões adulteradas e normalizando no `preSave` da entidade `EventLocation`.
- Formulários de Evento (`EventForm`) e Datas (`EventDateCreateForm`):
  - Removidos campos próprios e soltos de endereço e CEP (`cadastrar_novo_local`, `novo_local_*`, `new_location_*`). Endereço é gerenciado exclusivamente em `EventLocation`.
- Área administrativa independente para Eventos (`/painel-administrativo/eventos`):
  - Criado `EventAdminOverviewController` e rotas sob `/painel-administrativo/eventos/*` com indicadores de contagem e ações rápidas.
- Cobertura de testes:
  - `EventLocationModalAndAdminKernelTest` cobrindo 5 fluxos com 75 asserções PASS (incluindo interceptação 404 de `/node/add/activity`).
  - `EventLocationManagementKernelTest` cobrindo 7 fluxos com 147 asserções PASS.


- Provedores `EventContentIndexProvider` e `LocationContentIndexProvider` atualizados com DI completa (remoção de chamadas estáticas `\Drupal::`), URLs canônicas geradas via `toUrl('canonical')` e `toCanonicalUrl(?DomainPurposeManager)` apontando para rotas canônicas no propósito `eventos`.
- Allowlist mínima para Locais no índice: títulos e tipo de local; exclusão estrita de logradouro exato, coordenadas e telefones.
- Relação `event_location` (Evento > Local) projetada a partir de datas físicas com validação de status publicado e verificação de acesso de leitura para anônimo.
- `EventDateHooks` atualizado com injeção de dependência e hooks para `aculta_event_location_insert/update/delete` enfileirando eventos afetados para reindexação e invalidando tags de cache.
- Validação imediata de existência do local via Entity API nos formulários `EventForm` e `EventDateCreateForm`.
- Suíte `EventContentIndexProviderKernelTest` expandida para 6 testes cobrindo metadados, URLs, deduplicação de relações, consultas de relações autorizadas, resiliência de fila e seleção imediata (141 asserções PASS).
- Suíte `EventLocationManagementKernelTest` validada com 7 testes / 148 asserções PASS.

## 0.1.0-rc.1 — candidato RC (2026-10-29)

- Candidato `89efeb5b7` servido no checkout de teste com `drush cr`.
- Smoke do purpose `eventos`: 129/129 verificações PASS em 1280/390/320 px
  reais, com modo escuro; o mapa não exibe mais o texto inline de IP e mantém
  dois links para a Política de Privacidade (D-52).
- Suíte Events: 55 testes/670 assertions PASS (26 depreciações); PHPCS 0 erros
  e `php -l` do `MapController` PASS.
- OAuth do OSM validado no Runtime e ensaio de escrita fictícia concluído com
  limpeza (nó `14331309082`, changesets `191197897`/`191197899`/`191197900`).
- Próxima etapa: validação Alpha após autorização de release (RELEASE-LIFECYCLE).

## 0.1.0-dev.5 — aviso do mapa e OAuth do OSM validados (2026-10-29)

- D-52: a página do mapa não exibe mais o texto inline de envio de IP; ficam a
  atribuição do provedor (Leaflet/OpenStreetMap) e o link para a Política de
  Privacidade. O detalhamento continua na política publicada.
- OAuth do OSM validado no Runtime: token conectado, ambiente efetivo em
  `production` (D-45) e leitura real de `node/1` (versão 47) sem escrita.
- Suíte Events: 55 testes/670 assertions PASS (26 depreciações); PHPCS 0 erros
  e `php -l` do `MapController` PASS. O Kernel do mapa agora exige o link da
  política e rejeita o texto inline de IP.
- A revalidação visual do aviso depende de servir este candidato; o checkout
  segue aguardando a outra sessão.

## 0.1.0-dev.4 — validar UNTIL contra o início da série (2026-10-29)

- O formulário de recorrência recusa `UNTIL` anterior ao início da série, com
  mensagem no campo, em vez de gravar uma série que se expandiria para nada.
  O expansor mantém a mesma regra como guarda de dados.
- Suíte Events: 55 testes/669 assertions PASS (26 depreciações); PHPCS sem
  erros nos arquivos alterados.

## 0.1.0-dev.3 — smoke visual do purpose eventos (2026-10-29)

- `tests/browser/eventos/agenda-embed.mjs` passou 129/129 verificações no
  candidato atual `a4f878d55` (events dev.3), servido no checkout de teste, no
  purpose `eventos`: catálogo,
  página de evento, agenda, grade do mês, agenda do dia, incorporação e mapa,
  em 1280/390/320 px reais via BiDi, com modo escuro, sem rolagem horizontal e
  sem erro visível.
- Suíte atual no worktree: 55 testes/669 assertions PASS (26 depreciações).
  A revisão visual cobre o dev.3 no candidato `a4f878d55`. Nenhuma escrita no
  OSM e nenhuma atualização do Runtime.

## 0.1.0-dev.2 — fronteira de apresentação via tokens (2026-10-29)

- Catálogo deixou de invocar o componente `aculta420:event_card`; a estrutura
  agora usa render arrays Drupal com tokens semânticos `data-portal-ui`.
- Links do mapa usam os mesmos tokens neutros. As classes visuais equivalentes
  ficam no `PortalUiHooks` do ACULTA420.
- Events: 55 testes / 669 assertions PASS (26 depreciações upstream); gate
  módulo–tema PASS.

## 2026-10-28 — E7 MP3, mapas Leaflet/OSM

- `/mapa` passa a usar o renderer compartilhado `aculta_maps`, tiles OSM e agrupamento de marcadores; a lista continua disponível sem JavaScript.
- O aviso identifica o envio do IP aos tiles OSM e aponta para `/sobre/politica-de-privacidade`; a ativação no Runtime aguarda revisão do conteúdo da política.
- Geocodificação Nominatim acontece ao criar local ou alterar endereço. Sucesso grava coordenadas; falha ao atualizar endereço remove coordenadas antigas para evitar localização desatualizada.

## 2026-10-28 — URLs de página e sitemap por ambiente no F7

- Página de evento e sitemap do purpose usam o host que o ACULTA Deployer mapeia para o ambiente ativo; o ambiente de teste permanece `noindex`.
- Smoke HTTP no teste: sitemap index e filho `eventos` respondem 200; o filho lista URLs no host `eventos` de teste e a página declara canonical para o mesmo host. F7 concluído em `D-44`.

## 2026-10-28 — formulário de nova data com referência segura (E4, D-28)

- Adicionado `/painel-administrativo/eventos/datas/adicionar`; o parâmetro `event_id` só preenche o evento quando o usuário tem acesso de atualização. IDs inválidos, inexistentes ou não editáveis deixam o campo em branco.
- O formulário cria a data com a referência selecionada e campos preenchidos ali; não copia participantes nem clona parágrafos. Implementação reescrita a partir da regra D-28, sem copiar código do OpenCulturas.
- Kernel: editor autorizado recebe a referência; usuário sem acesso não a recebe; ID inexistente ou evento não editável não quebra o formulário.

## 2026-10-28 — campos informativos de preço e compra externa (E1, D-29)

- `aculta_event_date` ganha `field_gratuito`, `field_pricing`, `field_pricing_info` e `field_ticketing`; preço é texto, ticketing aceita link externo e não há cobrança no ACULTA.
- Update hook 10004 instala as definições sem remover dados existentes. Tipos adaptados do OpenCulturas; nenhum código de pagamento foi copiado.

## 2026-10-28 — views administrativas de locais e datas

- Incluídas as views `eventos_locais` e `eventos_datas_relacionadas` em caminhos portugueses, com acesso restrito ao papel administrador e sem telefone, e-mail ou endereço.
- A exportação administrativa de datas foi aprovada para a v1 via provedor do `aculta_calendar` (ADR-031); permanece pendente de uma API de exportação de dados administrativos sobre os provedores de itens, não coberta pela exportação `.ics` CA0/CA2 e não duplicada neste módulo.
- Kernel `EventRelatedViewsAccessKernelTest`: acesso negado para usuário comum e allowlist de campos sem telefone, e-mail ou endereço. O provedor do calendário fornece agenda e `.ics`; exportação administrativa estruturada ainda pendente.

## 2026-10-28 — registrar propósito dos locais nos testes

- Exportado para `config/sync` o vocabulário `aculta_location_type`, já criado pelo update hook 10003, e coberta a negação de `/locais` fora do purpose `eventos` com teste Kernel.
- O update hook já contém os 17 tipos de local traduzidos; este registro fecha a configuração e a evidência de acesso da microfase E1.

