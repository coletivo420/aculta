# Inventário dos consumidores de Contributions

**Base de código:** branch `dev`, commit `bc362a1be` (2026-11-01)
**Revalidado em:** 2026-11-01

Este inventário foi revalidado em `bc362a1be` após a integração parcial de
Organization/Location/Event, filas e revisão Content Admin, inbox de resultados
Messenger, workspace privado, formulários de envio, atualização e ressubmissão
estruturada para Eventos, revalidação de capability no apply e aviso privado de
envio por envelope Messenger mínimo. Foi adicionada cobertura Unit do update
Messenger 10015 e o update 10016 têm cobertura em
`MessengerDefaultConfigUpdateTest`. No Runtime de
desenvolvimento, `updatedb:status` mostra 10015/10016 pendentes e
`config:status` não mostra diferenças. WE SATIVA usa `wiki:/wesativa`; IDs e filas
são contratos existentes e não devem ser renomeados sem migração compatível.

| Owner | Dados e campos relevantes | Rotas/acesso e configuração | Filas, updates e testes |
| --- | --- | --- | --- |
| `aculta_contributions` | Entity revisionable `aculta_contribution`, tabelas base/data/revision/revision-data. Guarda autor, subject, source/owner module, purpose, intent, target type/UUID, fingerprint da revisão-base, payload allowlisted, contexto privado `source_data`, `decision_data`, justificativa, estado, parecer, hash idempotente e metadata de revisão. | Sem rota pública própria: adapters e módulos de domínio controlam entrada, resolução de alvo, leitura e decisão. Access handler delega ao adapter e falha fechado; permissões administrativas/listagem não substituem acesso de origem. `aculta_contributions.settings` configura limites Flood. | Update 10001 instala idempotentemente `source_data`/`decision_data` em instalação existente. Unit de Registry/State Machine: 3/19; Kernels Entity + Lifecycle: 14/161, incluindo lock concorrente por conexão e processo OS separado. F4–F7 permanecem nos owners de domínio. |
| `aculta_wiki` | `aculta_wiki_proposal`, tabelas base/data/revision/revision-data com `uid`, `subject`, alvo Node, VID-base, `intent`, `proposed_text`, `proposed_fields`, `rationale`, fontes, `state` e notas. Entidade privada e revisionável. | `/wiki/verbetes/{node}/contribuir`, `/wiki/propostas/{proposal}`, retirada e decisão em `/wesativa/propostas/{proposal}/decidir`; permissões `submit/view own/view any/moderate wiki proposals`, Entity Access, Domain Wiki, e-mail confirmado. `WikiProposalDecision` ainda usa `content_moderation.state_transition_validation` e `moderation_information` para o workflow oficial. | `aculta_wiki_editorial_events` entrega envelopes versionados ao Messenger. Updates 10001 instala schema de proposals se o módulo já estava habilitado e `proposed_fields`; 10002 prepara Queue API; 10003 reconcilia IDs/UUIDs/revisions para `aculta_contribution`. Novos envios ficam fechados até checkpoint de toda proposta legada. Status, decisão, retirada, workspace, fila e publisher fazem leitura dual-store. Kernels focais no HEAD: corte/status 2/264 e serviço legado 1 teste aprovado junto do corte; workflow Core permanece nesta extração. |
| `aculta_events` | `aculta_event`, `aculta_event_date`, `aculta_event_override`, `aculta_event_offer`, `aculta_event_location`, `aculta_event_organization`, `aculta_event_org_suggestion`. Evento inclui título/slug, descrição, organizador UID, organização, coorganizadores, estado, RSVP, acessibilidade, segurança e apoiadores. Local inclui organizador, título/tipo, endereço, coordenadas, precisão, OSM IDs/version e visibilidade. Organização inclui perfil, publicação, titular, criador, membros delegados e estado de reivindicação. | `/culturas/**` para organizer; inclui `/culturas/propostas`, detalhe privado por UUID e `/culturas/propostas/{uuid}/revisar`; `/culturas/propostas/evento/nova` aceita `event_create`; `/culturas/eventos/{aculta_event}/propor-alteracao` aceita `event_update` com Entity Access `update`. Rotas privadas sem cache compartilhado. Administração de eventos/locais/organizações e propostas em `/painel-administrativo/eventos/**` no MAIN. `EventAccessControlHandler`, `EventLocationAccessControlHandler`, `EventOrganizationAccessControlHandler`, `EventPermissionPolicy` e `OrganizationAccessService` revalidam operação/escopo. `create_event_roles` e `create_location_roles` vivem em `aculta_events.settings`. Formulários de Local e revisão de proposta reutilizam `AccountAddressForm`, CEP central e fixam BR; `EventLocation::preSave()` normaliza BR e delega geocoding a Maps. | Updates 10001–10016; Kernels de acesso, organizações/membros/claims, Local/modal, catálogo, Index, datas e migrações. `OrganizationContributionAdapter` aceita criação e atualização de perfil; a fila mantém sugestões legadas. `LocationContributionAdapter` aceita `location_create`/`location_update`, allowlist de oito campos, BR apenas e sem coordenadas/OSM/titularidade/status; criação aprovada permanece draft, pertence ao proponente e não se vincula automaticamente a Evento. A fila/revisão de Eventos usa `events_event`, a rota `admin_event_contributions` e permissão Events nativa. O workspace permite leitura de estado, payload e retorno; formulários submetem propostas privadas de criação e atualização (esta exige Entity Access `update` e usa fingerprint da versão persistida, sem alterar o Evento antes de decisão) com referências allowlisted; em `needs_changes`, o autor pode revisar campos allowlisted e reenviar por `ContributionLifecycle::revise()`, criando nova revisão e revalidando owner, capacidade, estado e revisão-base. Datas novas são opcionais e append-only. Resultados usam envelope mínimo e inbox privado no Messenger, com revalidação de revisão/autor/acesso e outbox KeyValue recuperável por cron; sem e-mail/digest. Validações registradas no incremento de `event_update`: Kernels combinados 37/1.275; formulário focal 1/35. Os registros anteriores de ressubmissão/endereço e submission/consumer (2/64, 1/33 e 34/875) são execuções históricas e não foram repetidos neste HEAD. Homologação Runtime pendente. Claims continuam fora dos adapters. |
| `aculta_user` | `aculta_org_member` registra UID, organização, papel, status, capacidades de gerir membros/publicar, aprovação, aprovador e notas. `aculta_ownership_claim` registra tipo/ID do alvo, proponente, justificativa, contato, status, aprovador e decisão. | `OrganizationMembershipManager` + `OrganizationAccessService` determinam associação ativa, poderes de agir/publicar/gerir membros; `OwnershipClaimService` valida reivindicabilidade, evita claim pendente duplicado e concede titularidade com suspensão preventiva dos membros aprovados para revisão pelo novo titular. Não mover ownership nem converter reivindicações em propostas. | Updates próprios. Testes de ciclo de organização e acesso em Events/User. Claims continuam workflow independente e não devem ser embutidos em `source_data` de contribuição. |
| `aculta_content_admin` | Não possui entidade editorial nem fila própria. `ModeratableCollectionRegistry` descobre allowlist de rotas controller e Views page no MAIN com content entity como base. | A tela verifica autorização nativa por `AccessManager`; aparição não concede acesso à decisão. A allowlist inclui sugestões de Organizações e propostas de Locais/Eventos, protegidas por `administer aculta events`; decisão e Entity Access permanecem no owner. | Unit da registry: 1 teste / 8 assertions PASS; Kernel `EventOrganizationLifecycleKernelTest` valida fila/revisão de Evento. Não replica nem armazena propostas. |
| `aculta_messenger` | Avisos do Fórum em `aculta_aviso`; notificações pessoais persistentes em bundles da entidade `message`, com adapter paginado e estado de leitura ownerado. | Consumers validam source/evento; lista, leitura e abertura revalidam autor e acesso atual no módulo de domínio. | Novo bundle `aculta_msg_events_contribution_notice`, updates idempotentes 10015–10016 e consumer de envio/resultado de Organização/Local/Evento; persistência no inbox do site, chave idempotente e sem payload privado. Não envia e-mail/digest; Runtime e testes finais pendentes. |
| `aculta_index` | Projeções de conteúdo oficial via providers tagueados; providers Wiki, Events, Organização/Local e categorias usam entidades de origem. Contribution não possui provider. | Admin de estado em `/painel-administrativo/indice`; acesso `administer aculta index`. Consumidores consultam Entity Access do owner. | Queue ID `aculta_index_update`; updates 10001–10018 e suíte Kernel de provider/access/rebuild. Não adicionar provider para `aculta_contribution`; validar que aplicação aprovada invalida/atualiza a projeção oficial sem indexar payload privado. |
| `aculta_ugc` | `aculta_ugc_report` e `aculta_ugc_recipients`; denúncias têm alvo, autor, motivo, purpose, estado e decisão. | `/painel-administrativo/moderacao/denuncias` e decisão própria, escopadas por `ModerationScope`. | Update 10001 e Kernels próprios de fila, decisão e Fórum. Denúncia permanece UGC. |

### Consumidores Wiki na transição dual-store

`aculta_wiki.proposal_view`, `aculta_wiki.proposal_withdraw` e
`aculta_wiki.proposal_decide` preservam paths e route IDs legados. O param
converter resolve primeiro `aculta_contribution` Wiki pelo ID estável e recua
para `aculta_wiki_proposal`. Forms de decisão/retirada, `WikiEditorialQueue`,
`WikiContributorWorkspace` e `WikiEditorialNoticePublisher` suportam ambos
stores; novos envios entram no store compartilhado somente depois da barreira de
checkpoints. Preservar IDs/UUIDs, Entity Access, revisão, paginação, cache e
destino dos avisos continua parte do contrato; a reconciliação Runtime ainda
não ocorreu.

`aculta_contributions` oferece entidade privada revisionável, acesso delegado ao
adapter registrado e ciclo genérico. O adapter `wiki` está registrado em
`aculta_wiki.services.yml`; novos envios passam pelo ciclo compartilhado depois
que cada proposta legada tem checkpoint de migração. Status/param converter,
decisão, retirada, workspace, fila editorial e publisher suportam os dois
stores durante a transição. O contrato genérico valida alvo antes do payload e
mantém contexto privado separado dos campos aplicáveis. O modelo separa
`payload`, `source_data` e `decision_data` por revisão; o adapter Wiki mapeia
intenção editorial, fontes e opções `append/replace` para allowlists próprias.
As tabelas antigas e seus registros não serão removidos sem decisão e migração
de dados compatível.

## Matriz domínio × operação × policy × applier (F0)

| Domínio/operação | Policy que decide | Applier/owner dos dados | Situação no HEAD auditado `bc362a1be` |
| --- | --- | --- | --- |
| Wiki update | `WikiContributionAdapter`: purpose Wiki, e-mail confirmado, permissão de submissão, alvo publicado/visível, revisão padrão, Entity Access; reviewer `moderate wiki proposals`; apply requer `edit any wiki_entry` e Node update access. | `WikiProposalDecision::applyToWikiEntry()` mantém allowlist e lógica de listas/imagem; `aculta_contributions` mantém ciclo e revisão de proposta. | Novo submit e consumidores migrados com leitura dual-store; update 10003 não executado em Runtime. Workflow oficial de Node continua em Content Moderation. |
| Organização update não reivindicada | `OrganizationContributionAdapter` exige purpose `eventos`, conta autenticada com role/config `create_event_roles` e alvo não reivindicado; revisão/aplicação exigem purpose MAIN e `administer aculta events`. | Adapter Events aplica somente `title`, `body`, `imagem`, `links`, `localidade`, `uf`, `ocultar_localidade`, sob lock compartilhado e fingerprint integral do `EventOrganization`. | Novos envios usam `aculta_contributions`; fila e revisão dual-read mantêm sugestões legadas. Kernel 2/80 PASS. A revisão do autor para `needs_changes` é compartilhada; claims e titularidade permanecem fora do adapter. Não houve migração/drop de registros antigos nem homologação Runtime. |
| Organização create / update reivindicada | Criação usa papéis `create_event_roles`; escolha de claim é fluxo de `OwnershipClaimService`. Organização reivindicada autoriza titular/membros ativos por `OrganizationAccessService`; admin de Events mantém operação administrativa. | `EventOrganizationForm` persiste criação e edições autorizadas; `OrganizationMembershipManager` cria/aprova/suspende vínculos; `OwnershipClaimService` altera titularidade. | Não há applier Contributions. Claim, titular, membro, status, slug e publicação são campos protegidos e não podem ser valores submetidos pelo cliente. Semântica de proposta de criação com claim pendente precisa de definição antes de implementar. |
| Local create / update | `EventLocationAccessControlHandler` + `EventPermissionPolicy` role settings e escopo de organizador; admin de Events nas rotas administrativas. | `EventLocation`/`EventLocationForm`; endereço por `AccountAddressForm` + CEP central BR; geocoding fica em Maps. | Adapter local valida allowlist, BR, alvo/update access, fingerprint e duplicidade provável; atualização aplica campos selecionados, criação aprovada resulta em draft sem ligação automática a Evento. Coordenadas, OSM e controles editoriais ficam protegidos. Messenger de resultados privado usa a fila/outbox compartilhada; não envia payload, endereço ou parecer. Aviso de envio de Evento implementado sem payload; testes amplos, revalidação de Events relacionados e homologação Runtime ainda pendentes. |
| Evento create / update | `EventAccessControlHandler`, `EventPermissionPolicy`, `OrganizationAccessService`; capabilities de criar, editar, publicar separadas. Revisão oficial da publicação continua no owner. | `EventForm`, `EventDate` e `EventDateOverride`; `aculta_events` valida datas, organizer, organização, Local e estado. | Adapter `events_event`: allowlist editorial, `event_create`/`event_update`, lock/fingerprint do Evento e Datas e referências revalidadas; criação aceita gera rascunho atribuído ao proponente e updates aplicam seleção/adicionam Datas. No apply, autor/capability/Entity Access atuais são revalidados; membership revogado e Local de Data tornado privado produzem conflito sem mutação. Fila/revisão Content Admin usa MAIN e `administer aculta events`. No incremento de `event_update`, Kernels combinados 37/1.275 e formulário focal 1/35 foram registrados; são evidência histórica, não reexecução neste HEAD. A interface de autor para `event_update` captura fingerprint persistida; Data nova é opcional e append-only. Permanecem cobertura adicional de conflitos e homologação Runtime. |
| Painel / fila | Access Manager verifica cada rota allowlisted; Entity Access dos módulos de origem continua autoritativo. | `ModeratableCollectionRegistry` descobre as coleções de Organizações, Locais e Eventos; revisão usa `administer aculta events` e o Entity Access de Contributions/owner. | Fila de Evento em `/painel-administrativo/eventos/propostas`; Registry Unit 1/8 e Kernel Events 21/691 PASS. Rotas MAIN-only; Runtime pendente. |
| Messenger / aviso | Owner escolhe destinatário; consumer, listagem e abertura revalidam conta ativa, autor, revisão, estado e Entity Access. | Events enfileira envelope mínimo de resultado para Organização/Local/Evento, sem payload/endereço/parecer; Messenger persiste Message e adiciona adapter à lista/estado lido. | Código e testes locais cobrem enqueue/consumo; inbox Runtime depende dos updates 10015–10016 ainda pendentes. E-mail/digest fora desta entrega. |
| Index | Provider exige conteúdo oficial público e respeita access da origem. | `aculta_index` consome projections versionadas de Wiki/Events/Organization/Location. | Sem provider para Contributions, corretamente. Falta validar invalidation/reindex após apply de Events. |
| UGC / Fórum / claims | `aculta_ugc` `ModerationScope`, política própria de comentário; claim pela policy `aculta_user`. | Denúncias, moderação de comentários e reivindicações permanecem nos owners. | Excluídos do ciclo Contribution; somente links/eventos de encaminhamento em caso justificado. |

### Decisões técnicas fechadas por este inventário

- `aculta_contributions` não define campos, status de publicação nem regra de
  ownership para Events; os adapters devem chamar APIs dos owners.
- A sugestão atual de Organização é um consumidor legado a substituir com
  migração/reconciliação preservadora. A antiga entidade/rota não será apagada
  automaticamente e deve continuar resolvível durante a transição.
- Claim de titularidade e associação/gestão de membros seguem os workflows de
  `aculta_user`; não são `source_data`, payload nem outcome de contribuição.
- Criação de Organização com claim pendente exige definir como obter ID oficial
  sem publicar perfil pré-aprovação nem duplicar claim. Até essa definição, o
  adapter deve falhar fechado para esse intent específico.
- Requisições para endereço completo em Local usam CEP central e BR. Antes da
  fase Local, confirmar auditoria do formulário completo e de validação server-
  side, sem tocar no Runtime nem promover componente por mera documentação.

## Lacunas de execução

- O adapter `wiki` valida purpose, elegibilidade, campos e escolhas de lista e
  delega a aplicação ao workflow Wiki existente. Update 10003 e tabelas de
  reconciliação proposta/revisão estão preparados; testes focais de migração e
  corte/status passaram 2/264 e lifecycle 10/109. Runtime não foi atualizado;
  não declarar a migração homologada até validar os dados reais e operar o
  update com autorização.
- A implementação antiga não persistia as escolhas de campos e modos de lista
  aplicadas no aceite; estados, pareceres e revisões permanecem recuperáveis,
  mas essa parte da decisão histórica não pode ser reconstruída.
- A migração é fail-closed para colisões de IDs e revisões traduzidas. A
  quantidade real desses casos não foi verificada no Runtime.
- A sugestão de Organização não é revisionável e não cobre criação, Local ou
  Evento; a integração precisa preservar titulares, membros, claims e estados.
- O painel Content Admin descobre filas nativas por rotas dos owners e mantém a
  autorização da origem; não copia itens nem decide propostas. Events publica
  eventos mínimos de submissão e resultado à API Messenger; updates 10015–10016
  ainda precisam ser instalados e homologados no Runtime. Index continua sem
  provider de Contributions, corretamente.
- Kernel MariaDB do novo módulo usa o banco existente `aculta_simpletest` via
  `SIMPLETEST_DB`; persistência, revisão, idempotência, aplicação, conflito e
  negação/allowlist passaram localmente. Runtime ainda não foi homologado.
