# Roadmap — ACULTA Wiki

**Ciclo vigente:** seguir [Dev → RC → deploy/validação Alpha → produção](../../../../../../../docs/operations/RELEASE-LIFECYCLE.md).
Fechar escopo, testes, gates e revisão visual aplicável antes de RC. Deploy em
produção só quando todos os módulos, temas e subtemas estiverem em RC.

Linha do projeto `aculta_wiki`: **0.1.0-dev.10**. Código e testes do
submódulo existem, mas a Wiki permanece desabilitada até o gate de habilitação.
O plano aprovado e o inventário de referências ficam em
[`docs/modules/WIKI-SUBMODULO.md`](../../../../../../../docs/modules/WIKI-SUBMODULO.md).

## Fase 1 — Casca do submódulo

### Limpeza de verbetes e anexos — dívida coordenada com o Portal

O Portal já oferece `aculta_portal.unused_file_cleaner` para verificar e remover
Files sem uso conhecido, delegando imagens a `aculta_image`. Adicionar ao
contrato do provider Wiki a descoberta de referências necessárias à exclusão
segura de verbetes e submeter candidatos Media ao Portal ao limpar anexos. A
Wiki mantém a descoberta de embeds e relações da própria entidade; o Portal
revalida referências globais e remove os Media órfãos por Entity API.
As relações `wiki_category` e `wiki_image` do Content Index foram implementadas
com revalidação de acesso dos alvos, mas não executam nem substituem o adapter
de limpeza. A exclusão segura de anexos continua sendo um fluxo separado. Ver
[`CONTENT-CLEANUP-POLICY.md`](../../../../../../../docs/portal/CONTENT-CLEANUP-POLICY.md).

- Manifesto com dependências `aculta_portal`, `domain`, `views`, `node` e
  `content_moderation`.
- Documentação de entrada e regras locais.
- Saída: validadores do repositório passam; módulo continua desabilitado.

## Fase 2 — Mover o código inteiro

- `WikiController`, `WikiSearchFormBuilder` e as três rotas
  `aculta_portal.wiki_*` passam para o submódulo, com nomes e caminhos
  idênticos.
- Os pontos de domínio e purpose (`DomainPurposeManager`,
  `ContentPurposeResolver`, `PurposeFrontPathProcessor`, `DomainRouteSubscriber`).
  A Wiki não redefine purpose nem host.
- Saída: Unit e kernel do controlador e da busca; `drush cr` sem erro; Config
  Sync sem diferenças; HTTP 200 em `/wiki`, `/wiki/verbetes`, `/wiki/busca` e
  `/wiki/busca/previa` no domínio `wiki420`; backup privado antes de qualquer
  `updb`.

## Fase 3 — Documentação obrigatória da mudança de escopo

- `AGENT-CONTENT.md` e `AGENTS.md` do submódulo com o estado real de habilitação.
- Inventário LGPD com `wiki.entries.published` e `wiki.entries.updated` antes de
  criar as métricas.
- Política de privacidade: conferir “Bases legais por uso do site” sem inventar
  base legal.

## Fase 4 — `aculta_statistics` pronto para receber a Wiki

- Catálogo e retenção de 90 dias para as duas métricas.
- `WikiProvider` em `aculta_statistics`, sem importar classes da Wiki.
- Primeira publicação, revisão publicada e conteúdo importado cobertos por
  testes unitários.

## Fase 5 — Habilitação

- Só depois das fases anteriores, com decisão do responsável, backup privado e
  validação no servidor de testes.

## Integração ACULTA Content Index — IDX 0.1

- Provider `node:wiki_entry` registrado pelo próprio módulo sob a tag
  `aculta_index.provider`, sem dependência obrigatória do submódulo Index.
- Projeta metadados mínimos e declara o purpose canônico `wiki` para revalidar
  Entity Access em consultas originadas por outros domínios, com o usuário e
  grants atuais. A falha em resolver o domínio deve negar exposição.
- Relações outgoing explícitas: `field_wiki_related` → `related_entry`
  (`node:wiki_entry`); `field_wiki_categories` → `wiki_category`
  (`taxonomy_term:wiki_category`); `field_wiki_image` → `wiki_image`
  (`media:image`). Provider mínimo de categoria pertence a `aculta_wiki`; o
  provider de imagem pertence a `aculta_image`. O Index não copia corpo,
  descrição de termo, URL ou arquivo e revalida acesso de cada destino.
- O bundle/provider mínimo de Media `remote_video` pertence ao Portal e está
  disponível para os módulos consumidores. Hoje `filter.format.wiki` permite
  apenas Media `document`; uso de vídeo na Wiki não está habilitado. Decisão do
  responsável: adiar relações de vídeo até fechar os formatos editoriais e os
  contratos de acesso. Não inferir links de texto livre nem projetar URL
  oEmbed/corpo.
- Referência: [arquitetura e roadmap do ACULTA Content Index](../../../../../../../docs/portal/ACULTA-CONTENT-INDEX.md).

### Estado da integração e gates restantes

- Unit do provider Wiki cobre a allowlist, revisão padrão, três relações
  nomeadas e deduplicação; `WikiCategoryContentIndexProviderTest` cobre a
  projeção de `taxonomy_term:wiki_category`, purpose `wiki` e revisão padrão.
  A suíte Unit passou em 2026-10-23 (61 testes / 277 assertions; 3
  depreciações PHPUnit).
- Unit focal do filtro registra o provider Wiki real e confirma purpose,
  preservação do usuário, restauração de Domain e falha fechada sem Domain
  (9/80). O Kernel `ContentIndexWikiAccessKernelTest` usa `EntityHooks`, provider
  e Domains persistidos reais: nega em `main`, autoriza pelo Index em `wiki` e
  restaura o Domain anterior (1/16). O teste usa bridge de fixture; não habilita
  a Wiki nem prova discovery do YAML owner no container Drupal.
- `ContentIndexWikiAccessKernelTest` consulta `related_entry`, `wiki_category`
  e `wiki_image` pelo Index usando os YAMLs reais de `aculta_wiki` e
  `aculta_image`. Entidades Node, Taxonomy Term e Media são persistidas; Entity
  Access omite alvos não publicados, e o Domain `main` é restaurado. MariaDB:
  3 testes / 84 assertions, 5 depreciações.
- O mapa de services e o boot combinado do Index carregam as tags owner. Isso
  não instala toda a configuração da Wiki nem substitui grants adicionais ou
  homologação Runtime. O módulo continua desabilitado; não habilitar ou importar
  sua configuração como parte desta fase.

Atualização IDX 0.1-dev.161 (2026-10-23): o teste transversal
`ContentIndexOwnerIntegrationKernelTest` carregou a closure completa de
dependências da Wiki, além de Portal/LMS/Fórum. O container real descobriu o
provider `wiki` pelo YAML owner; o gate inicial passou com 1 teste / 8
assertions (83 depreciações Core/contrib). Isso comprova boot/DI e registry,
sem instalar configuração/schema nem homologar Runtime. Categorias e imagens
foram concluídas em dev.10, registrada abaixo.

Atualização IDX 0.1-dev.10 (2026-10-23): essa decisão foi encerrada após a
definição dos contratos. O provider agora projeta `wiki_category` e
`wiki_image`, e o owner da categoria foi implementado na Wiki. Kernel MariaDB
valida consulta das relações e revalida acesso a Term/Media reais (3/84; 5
depreciações). O harness é do Index/Portal; a Wiki continua desabilitada e
Runtime continua pendente. Essa integração do Index é separada do adapter para
limpeza segura de verbetes e anexos, que permanece como dívida do projeto.

### Entregue em código (2026-10-22)

- **Listagem e imagem de destaque:** contagem e paginação da view `wiki_entries`,
  recorte 16:9 no card e aba "Imagem de destaque" consumindo o consumidor `wiki`
  da política central de upload. Rotas inalteradas.
- **Busca no site:** `wiki_entry` em `bundles.selected` de `aculta_conteudo`. A
  busca própria da Wiki e as três rotas `/wiki`, `/wiki/busca` e
  `/wiki/busca/previa` não mudaram. Verbetes já publicados entram no índice com
  `drush search-api:index` no ambiente (passo de deploy, não desta sessão).
- **Aviso de revisão:** `WikiReviewHooks` (`entity_update`, via `getOriginal()`),
  `WikiReviewMailType` (`aculta_wiki_review`) e `WikiReviewNotifier` na fila do
  `aculta_mailer`, como serviços opcionais. Tela
  `/painel-administrativo/aculta/wiki` (`administer aculta_wiki`). Desligado por
  padrão, sem destinatários, sem o nome de quem editou no contexto.
- **Pacote LGPD no mesmo commit:** inventário, cláusulas de aceite, política de
  privacidade, `AGENT-CONTENT.md` e `AGENTS.md` do submódulo.
- **Continua pendente:** importação de configuração com backup privado
  verificado, reindexação no ambiente e revisão visual em ACULTA420 e Olivero.


## WE SATIVA — fundação aprovada (WES-UX 0.2, 2026-10-22)

A experiência editorial independente da Wiki420 é definida em [WE-SATIVA-FOUNDATION.md](WE-SATIVA-FOUNDATION.md) e no [plano de contribuição contextual](../../../../../../../docs/portal/WE-SATIVA-CONTRIBUTION-FIRST-RESEARCH.md). **Não reutilizar GanjaLab como interface do WE SATIVA.** Implementação iniciará por auditoria de Entity Access/Form API/workflow e resolução de purpose, depois abertura contextual por verbete e microtarefas mobile-first. Não habilitar sugestões sem backend real. **Autosave Form adiado para futuro**, sem Composer/configurações neste ciclo. Preservar Institution Bar + Domain Header e usar exclusivamente SDC/UI Suite no tema.


## WE SATIVA — arquitetura interna WES-UX 0.3 (2026-10-22)

O planejamento aprovado de todas as estruturas internas está em [WE-SATIVA-INTERNAL-ARCHITECTURE.md](../../../../../../../docs/portal/WE-SATIVA-INTERNAL-ARCHITECTURE.md). Fases 0.3-A1 e A2 deverão auditar permissões/workflows e decidir a persistência segura de propostas antes de rotas. Seguem depois wireframes, reuso SDC/UI Suite e integrações incrementais. **Nenhuma implementação funcional liberada por este documento; Autosave Form permanece futuro.**


## WE SATIVA C01 — primeira composição visual (2026-10-22)

[Porta contextual de contribuição](../../../../../../../docs/portal/WE-SATIVA-COMPONENT-01-CONTRIBUTION-ENTRY.md): um CTA autorizado a partir do verbete e seletor de intenções sem ações fictícias. Primeiro auditar `context-header.actions` versus `wiki-entry.actions`; manter um só CTA. Dependência de AccessResult, purpose/domain e URL canônica; proposta sem `update` continua bloqueada até decisão de backend. Projeto mobile-first, SDC/UI Suite, shell global preservado.


## Repriorização visual e de acesso — C01-A (2026-10-22)

A [auditoria C01-A](../../../../../../../docs/portal/WE-SATIVA-C01-A-ACCESS-AND-CTA-AUDIT.md) constatou: o SDC `context-header.actions` pode receber o convite único, enquanto `wiki-entry.actions` já exibe `content.links`; evitar CTA duplicado. O workflow `wiki_editorial` já tem rascunho, revisão e publicação, mas o papel autenticado comum não possui permissões de edição; o papel `content_editor` é privilegiado. **Antes de qualquer rota ou UI WE SATIVA:** (1) desenhar contribuição de mínimo privilégio e mecanismo de propostas sem `update`; (2) mapear todas as permissões e revisão; (3) verificar CKEditor/formatos; (4) selecionar a rota/purpose; (5) integração SDC mobile-first e teste de DOM. Próximo marco **C01-B**. Nenhuma concessão de permissão é autorizada por este roadmap.


## C01-B0 — participação de e-mail confirmado e moderação (2026-10-22)

**Decisão aprovada:** todo usuário com e-mail confirmado poderá contribuir na Wiki; somente `content_editor` modera/publica sugestões. [Contrato C01-B](../../../../../../../docs/portal/WE-SATIVA-C01-B-CONTRIBUTOR-MODERATION-CONTRACT.md) verifica que `user.role.email_confirmed.yml` já possui criação/edição de conteúdo próprio e envio ao estado `review`, sem publicar. Não confundir conta meramente `authenticated` com `email_confirmed`. Corrige-se a inferência incompleta da C01-A; não duplicar papel nem ampliar `edit any`. **Próximos:** C01-B1 conferir atribuição efetiva da confirmação de e-mail e Entity Access; C01-B2 registrar ADR de propostas em verbetes alheios; depois C01-C1 editor de próprios verbetes e C01-C2 microtarefas. C01-R no homelab. Autosave permanece somente futuro; nenhuma permissão, rota ou endpoint foi mudado.


## Prioridade: primeira publicação e moderação Wiki (WES-MOD 0.1)

[Plano de moderação da Wiki e WE SATIVA](WE-SATIVA-MODERATION-PLAN.md): qualquer conta com e-mail confirmado pode **criar** verbete; o **primeiro verbete** exige revisão antes de publicação, tal como a primeira participação no Fórum. A transição de publicação pertence aos `content_editor`; novos verbetes e sugestões continuam moderados no workflow vigente. Fila Wiki própria, com contratos neutros compartilhados pelo `aculta_user`. Antes de novas inspeções visuais: auditar consumidores, registrar ADR do kernel, evitar duplicações e testar a regra de primeira publicação. Não reutilizar contagem de comentários do Fórum para verbetes.

## Retificação de ownership (2026-10-22)

**A política compartilhada de moderação e primeira participação reside em `aculta_user`**, estendendo `ParticipationRules`, sem migração para `aculta_portal`. Wiki e Fórum permanecem donos de filas/workflows e entidades próprias. Esta decisão substitui indicações anteriores de centralizar essa política no Portal. [Contrato revisado](../../../../../../../docs/architecture/ACULTA-SHARED-MODERATION-AND-KERNEL-POLICY.md).


## USER-PART 0.1-A — auditoria Fórum + LMS + Wiki

O [inventário transversal](../../aculta_user/docs/USER-PART-0.1-A-CROSS-KERNEL-INVENTORY.md) estabelece `aculta_user` como proprietário da política compartilhada; Fórum e LMS mantêm entidades, queries, fila e Group; Wiki mantém workflow e revisões. Prioridade USER-PART 0.1-B (contrato de evidência de publicação) → testes de caracterização → adapter de verbetes Wiki → integração do primeiro verbete moderado. **Não reaproveitar o contador de comentários para calcular experiência Wiki**, nem conceder publicação a `email_confirmed`. Inspeção visual fica após o fechamento dos contratos funcionais.

## Auditoria transversal XK 0.1 — 2026-10-22

XK-01/03/05/06/08: Wiki publica eventos de revisão/ajustes via adapter Messenger com ACL e deduplicação; não cria caixa de avisos. Reaproveita aculta_user para primeira participação, aculta_forum_wiki para discussões, aculta_image e viewers para mídia, aculta_statistics para métricas; mantém moderação e propostas Wiki próprias.

Plano de dependências e gates: [ACULTA-XK 0.1](../../../../../../../docs/architecture/ACULTA-CROSS-MODULE-CENTRALIZATION-AUDIT-2026-10.md). **Planejado; sem alterações funcionais ou validação Runtime.**

## ACULTA-XK 0.1-B — contrato arquitetural (2026-10-22)

XK 0.1-B / ADR-XK-01: primeira participação de Wiki passa por contrato de User com lookup próprio de verbetes publicados; toda publicação exige content_editor. Para avisos de edição/revisão, a Wiki produz eventos mínimos para o Messenger sem segunda central. O próximo gate é caracterizar o workflow e não liberar edição de terceiros diretamente.

Fonte de verdade: [ADRs XK-01 a XK-03](../../../../../../../docs/architecture/ACULTA-XK-0.1-B-SHARED-CONTRACTS.md). **Somente planejamento, sem alteração funcional ou testes Runtime.**

## XK 0.1-C1 — caracterização concluída (2026-10-22)

As políticas compartilhadas de participação no `aculta_user` estão fixadas por testes Unit e Kernel, sem mudança de comportamento. `PublishedParticipationLookupInterface` foi documentada, **não implementada**: o adapter de verbetes Wiki e a regra do primeiro verbete continuam fora deste marco. Nenhuma alteração neste módulo. [Evidência](../../aculta_user/docs/USER-PART-0.1-C1-CHARACTERIZATION.md).

## WES-UX 0.4 — projeto visual por telas (2026-10-22)

[Inventário de 12 estruturas WE SATIVA](../../../../../../../docs/portal/WE-SATIVA-VISUAL-SCREENS-0.4.md), incluindo entrada pelo verbete, início, Meu trabalho, microtarefas, novo verbete, CKEditor, prévia, revisão, feedback, ajuda, fontes e mídia. Sequência V-0.4-A–R, mobile-first em toda a Wiki, componentes Core SDC/UI Suite exclusivamente, Institution Bar + Domain Header preservados. Cada tela depende de providers, estados e permissões reais; não implementar links fictícios. Autosave apenas futuro.

## WES-UX V-0.4-B — entrada, início e Meu trabalho

[Especificação detalhada das telas V01–V04](../../../../../../../docs/portal/WE-SATIVA-V-0.4-B-ENTRY-DASHBOARD-WORK.md): convite contextual único, seletor de intenções com ações realmente disponíveis, início com criação/continuação autorizadas e lista própria de trabalhos com filtros HTTP. Histórias WES-B-01 a WES-B-10, cenários vazios/erro/acesso, mobile-first 320px e claro/escuro. Somente Core SDC/UI Suite, sem rotas fictícias ou novas permissões. Próximo marco V-0.4-C (microtarefas e editor).

## WES-UX V-0.4-C — microtarefas, novo verbete, editor e prévia

[Especificação visual e funcional V05–V08](../../../../../../../docs/portal/WE-SATIVA-V-0.4-C-EDITOR-FLOWS.md): fluxos progressivos, 12 stories de aceite, Form API com CKEditor 5 **já configurado para o formato wiki**; toolbar exportada, Media integrado e upload direto de imagem desativado. Contas email_confirmed criam e enviam à moderação, content_editor publica. Sugestões de alteração de terceiros dependem de backend seguro. SDC/UI Suite, mobile-first, sem autosave nesta fase. Próximo V-0.4-D.


## WES-UX V-0.4-D — moderação visual no GanjaLab (2026-10-22)

[Contrato V09–V12 e janela GanjaLab](../../../../../../../docs/portal/WE-SATIVA-V-0.4-D-GANJALAB-MODERATION.md): `aculta_wiki` é fonte dos workflows, revisões, access e fila; `aculta_studio` oferece aba Wiki/Moderação e janela de análise, enquanto WE SATIVA mostra confirmação, ajustes recebidos, ajuda, fontes e mídia ao colaborador. Fases D-A contratos/StudioTab → D-B stories SDC → D-C fila e URLs → D-D transições → D-E Messenger e feedback → D-R homologação. Proibido duplicar painel de moderação no WE SATIVA. Mobile-first, shell multidomínio, SDC/UI Suite.

## V-0.4-R / GanjaLab D-A — auditoria do código existente

[Inventário e contrato da janela Wiki](../../../../../../../docs/portal/GANJALAB-WIKI-V-0.4-R-D-A-AUDIT.md). **A área Wiki em `WikiTab` já existe**: não criar outra aba/plugin. Acrescentar futuramente seção `moderacao` com gate próprio, pois permissão `create wiki_entry content` já abre a área para colaboradores e não equivale a permissão de moderar. StudioPresenter e URL/AJAX existentes serão preservados; `aculta_wiki` é o dono de fila, grants, revisões e POST. Ainda faltam exame dirigido do listing builder, adapter de acesso e testes Runtime. Design com SDC/UI Suite, mobile-first e shell multidomínio preservado.

## GanjaLab Wiki D-A.2 / D-B — listagens seguras e composição visual

[Auditoria de StudioListingBuilder e especificação dos componentes](../../../../../../../docs/portal/GANJALAB-WIKI-D-A2-D-B-AUDIT-VISUAL-CONTRACT.md). Achados prioritários: cache de listagens por usuário/grants, consulta auxiliar de moderation_state com accessCheck(FALSE), necessidade de autorização explícita por revisão, acesso separado para aba Moderação e paginação. **Bloquear integração da fila até testes de caracterização e provider Wiki seguro**. D-B prevê cards mobile 320px, comparador empilhado no celular e paralelo no desktop, SDC/UI Suite, shell multidomínio e fallback HTTP. Não declarar Runtime PASS.

## D-A.2-T — progresso e próximos gates

D-A.2-T: hardening inicial do GanjaLab no StudioListingBuilder (cache por usuário, checagem explícita de view revision) e teste Unit adicionados. Não equivale a autorização de fila Wiki. Antes de D-C é indispensável provider de revisão Wiki, validação por entidade e revisão, query de moderation_state correta e Kernel com dois usuários/grants.

## Decisão normativa: ACULTA Studio (2026-10-22)

WE SATIVA is an ACULTA Studio user experience hosted within the Wiki purpose, driven by aculta_wiki Form API/CKEditor and editorial access; it is not an independent interface kernel. GanjaLab moderation is another Studio experience at account purpose. Maintain different UIs and common interfaces/SDC base; V-0.4-R and D-A.2-T guards still apply.

[Contrato e roadmap transversal](../../../../../../../docs/architecture/ACULTA-STUDIO-EXPERIENCE-PLATFORM.md). Esta decisão prevalece sobre terminologia anterior que equiparava o submódulo exclusivamente a GanjaLab. **Não declarar consumidores planejados como rotas implementadas.**

## Atualização STUDIO 0.1-B / D-A.2-T2

D-A.2-T2: aculta_studio não enumera mais todas as entidades content_moderation_state; o estado usa a revisão mais recente visível por item. Isso não fornece fila confiável/paginada: aculta_wiki ainda deve implementar provider próprio de revisões com grants e workflow. STUDIO 0.1-B separa providers de experiência WE SATIVA (purpose Wiki) da navegação GanjaLab (account).
