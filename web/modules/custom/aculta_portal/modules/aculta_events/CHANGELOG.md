# Changelog — aculta_events

## 2026-10-30 — Fase 2: Migração e Rollback de Endereços Legados de Activity para Locais

- Implementado o serviço central de migração `ActivityLocationMigrator` (`aculta_events.activity_location_migrator`):
  - `getLegacyCandidates()` e `hasLegacyAddressData()`: identifica de forma segura nós `activity` que contêm campos de endereço legados e ainda não possuem referência preenchida em `field_event_location`.
  - `inspect()`: auditoria e diagnóstico em tempo real, informando total de atividades, já vinculadas, candidatas elegíveis, registros incompletos e estimativa de locais únicos após deduplicação.
  - `migrate(bool $dryRun)`: migração idempotente. Em modo `$dryRun = TRUE`, simula o processo sem gravar no banco; em modo persistente (`FALSE`), cria instâncias de `EventLocation` respeitando as regras de endereço (país fixo `BR`, precisão `exato`, status conforme publicação do nó pai, proprietário herdado), vincula `field_event_location` no nó e reutiliza locais idênticos através de chave normalizada (nome + CEP).
  - `rollback()`: reversão atômica e segura que desvincula as Activities e deleta exatamente as entidades `EventLocation` criadas pela migração, utilizando proveniência persistida no Drupal State (`aculta_events.migrated_activity_locations`).
- Implementado o update hook `aculta_events_update_10010()` em `aculta_events.install`.
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
