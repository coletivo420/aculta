# GanjaLab (`aculta_studio`)

Espaço de trabalho por abas na conta, para autores, editores, moderadores e educadores. É uma ferramenta transversal:
agrega serviços e ações dos donos das áreas, e não armazena conteúdo, permissões, discussões nem galerias próprias.

- Rota canônica: `/ganjalab` no domínio de conta (`conta.aculta.*/ganjalab`), com `_aculta_domain_purpose: account` e permissão
  `access aculta portal`. Visitante recebe 403.
- Áreas: plugins `#[StudioTab]` em `Plugin/StudioTab`. Cada área declara se o dono está disponível (`isAvailable()`),
  a permissão de exibição (`access()`) e as seções (`sections()`): cada seção do submenu tem as próprias abas, e cada
  aba lista as ações, cada uma com o acesso da rota do dono.
- Presenter: `StudioPresenter::visibleTabs()` mostra só abas com dono ativo e permissão concedida, em ordem de peso.
- Aba atual: argumento `aba` (id do plugin). Sem argumento ou com id desconhecido, a primeira aba visível.
- Quatro URLs canônicas: `/ganjalab`, `/ganjalab/{área}`, `/ganjalab/{área}/{seção}` e `/ganjalab/{área}/{seção}/{aba}`. Segmento
  inexistente ou fora do alcance da conta responde 404. Cada nível é um link real; sem JavaScript, a navegação segue normal.
- Galerias não aparece para a conta de teste: a rota de criação de galeria do dono fica no painel administrativo do
  domínio principal. A conta recebe a permissão de mídia pelo papel, mas o acesso à rota continua negado.
- Três níveis, cada um com navegação AJAX própria (`js/studio-navigation.js`, biblioteca `aculta_studio/navigation`):
  áreas (`aba`), seções do submenu (`secao`) e abas da seção (`sub`). A raiz `#aculta-studio-root` é substituída pela
  resposta `drupal_ajax`; URL, `aria-current` e voltar/avançar acompanham a troca.
- Página própria: sem o shell, o menu nem o `account-navigation.js` da Minha conta.

## Abas

| Aba | Dono | Permissão | Ações |
| --- | --- | --- | --- |
| Revista (`revista`) | `aculta_magazine` | `create revista content` | Nova publicação |
| Wiki (`wiki`) | `aculta_wiki` | criar/editar verbetes ou `moderate wiki proposals` | Verbetes próprios, revisão autorizada e propostas privadas |

Fórum, Cursos, Projetos, Biblioteca, Métricas e Eventos continuam dependendo das integrações dos respectivos donos.
A aba Wiki existente consome o serviço de fila proprietário de `aculta_wiki`; o Studio não armazena propostas nem
implementa permissões ou workflow paralelos.

## Apresentação e fronteira com o tema

O módulo não emite classes de design. Marca as partes com tokens semânticos `data-portal-ui`:
`studio-shell`, `studio-tabs`, `studio-tab`, `studio-panel`, `studio-heading`, `studio-actions`, `studio-action` e
`studio-empty`. O ACULTA420 traduz esses tokens em `PortalUiHooks::TOKENS` e estiliza a aba ativa em
`css/components/studio.css` (pelo `aria-current="page"`). Ver `docs/portal/FRONTEIRA-MODULO-TEMA.md`.

## Limites

- A integração das filas Wiki foi caracterizada em Kernel combinado; Runtime, Firefox/X11, mobile e WCAG permanecem pendentes.
- A aba Wiki só expõe revisão/propostas mediante as permissões e os serviços autorizados do proprietário.
- Não substitui Views, Form API, Entity Access nem Workflows dos donos: as ações apenas os invocam.
- Estado e próximos passos: `docs/operations/status/aculta_studio.yml` e o roadmap em `docs/portal/STUDIO-MAGAZINE-ROADMAP.md`.

## Listagens

- Aba interna de uma seção pode declarar `listing` (`bundle` e `state`, este último do fluxo editorial). A área
  Revista lista Todos, Rascunhos, Em revisão, Publicados e Arquivados pelo `content_moderation_state` do workflow
  `revista`. `StudioListingBuilder` lê os nós com `accessCheck(TRUE)`, então cada linha passa pelo acesso do nó.
- Sem linhas, a aba mostra "Nenhum item nesta aba".
- Ainda sem listagem: editor de publicação, fila de moderação do Fórum, revisões da Wiki, seleção de turma nos Cursos
  e navegação visual de Galerias. Cada uma entra quando o dono fornecer a visão correspondente.

## Contrato visual 0.2.0-dev.1 (planejamento, ainda não implementado)

- [Conceito visual responsivo — espaço pessoal primeiro](../../../../../../docs/portal/STUDIO-VISUAL-CONCEPT.md): em cada área, “Meus [itens]” é a vista padrão; conteúdo de outros autores aparece apenas em contexto identificado de equipe/moderação e conforme acesso real do provider.
- [Telas de criação, edição, prévia e revisão](../../../../../../docs/portal/STUDIO-EDITOR-FLOWS.md): shell de editor desktop/mobile, formulários e workflows nativos, estados de erro e critérios de aceitação.
- Estes contratos **não representam recursos implementados**. O ACULTA420 detém o visual, este módulo coordena interface e permissões dos providers; não criar armazenamento de conteúdo paralelo.

## Planejamento visual específico — LMS Course Builder

O [contrato CB-UX 0.3.0–0.3.8](../../../../../../docs/portal/STUDIO-LMS-COURSE-BUILDER-VISUAL.md) especifica a construção pedagógica **mobile-first**, com visão inicial “Meus cursos”, árvore/itens no desktop, navegação em uma tela por tarefa no telefone, edição contextual via Form API/CKEditor 5 e reordenação acessível. **É plano, não funcionalidade entregue.** Drupal LMS/Group conservam entidades, relações, notas e permissões; Studio coordena experiência e ACULTA420 implementa o visual.

## Identidade pública

GanjaLab é a interface de criação. Consulte [identidade pública](../../../../../../docs/portal/GANJALAB-BRANDING.md) e [GanjaLab420: experiência de aprendizagem](../../../../../../docs/portal/GANJALAB420-LEARNING-UX.md). Nomes internos e rotas permanecem inalterados.

## URLs GanjaLab

Rotas públicas canônicas: `/ganjalab`, `/ganjalab/{área}`, `/ganjalab/{área}/{seção}`, `/ganjalab/{área}/{seção}/{aba}` (route names técnicos inalterados). As antigas `/studio` continuam como aliases temporários, sem redirecionamento HTTP automático. O GanjaLab420 usa `/ganjalab420` para dashboard do estudante. Rebuild de rotas e testes no Runtime pendentes. Ver [contrato de marca](../../../../../../docs/portal/GANJALAB-BRANDING.md).


## Wiki420 — janela de moderação no GanjaLab (V-0.4-D)

A experiência de editores/moderadores da Wiki será integrada ao GanjaLab por aba `StudioTab` específica/área Wiki com seção Moderação, mantendo a navegação por áreas/seções/abas e AJAX progressivo existentes. [Especificação da janela](../../../../../../docs/portal/WE-SATIVA-V-0.4-D-GANJALAB-MODERATION.md): fila autorizada, primeira publicação, revisão de propostas, comparação, devolução para ajustes, publicação, concorrência e acessibilidade mobile/full-screen. `aculta_wiki` mantém dados, grants, formulários e workflow. WE SATIVA permanece ambiente de autoria/acompanhar resultados, não moderador. Nenhum storage/ACL alternativo no Studio.

## V-0.4-R / GanjaLab D-A — auditoria do código existente

[Inventário e contrato da janela Wiki](../../../../../../docs/portal/GANJALAB-WIKI-V-0.4-R-D-A-AUDIT.md). **A área Wiki em `WikiTab` já existe**: não criar outra aba/plugin. Acrescentar futuramente seção `moderacao` com gate próprio, pois permissão `create wiki_entry content` já abre a área para colaboradores e não equivale a permissão de moderar. StudioPresenter e URL/AJAX existentes serão preservados; `aculta_wiki` é o dono de fila, grants, revisões e POST. Ainda faltam exame dirigido do listing builder, adapter de acesso e testes Runtime. Design com SDC/UI Suite, mobile-first e shell multidomínio preservado.

## GanjaLab Wiki D-A.2 / D-B — listagens seguras e composição visual

[Auditoria de StudioListingBuilder e especificação dos componentes](../../../../../../docs/portal/GANJALAB-WIKI-D-A2-D-B-AUDIT-VISUAL-CONTRACT.md). Achados prioritários: cache de listagens por usuário/grants, consulta auxiliar de moderation_state com accessCheck(FALSE), necessidade de autorização explícita por revisão, acesso separado para aba Moderação e paginação. **Bloquear integração da fila até testes de caracterização e provider Wiki seguro**. D-B prevê cards mobile 320px, comparador empilhado no celular e paralelo no desktop, SDC/UI Suite, shell multidomínio e fallback HTTP. Não declarar Runtime PASS.

## D-A.2-T — progresso e próximos gates

D-A.2-T aplicado: StudioListingBuilder passa a variar listagens por user além de user.permissions e filtra cada revisão por view + view revision. Teste unitário de caracterização incluído, execução pendente. A consulta auxiliar de moderation_state, cache de Domain/grants e paginação ainda bloqueiam fila Wiki real.

## Integração Wiki no GanjaLab — Studio dev.2 / Wiki dev.19

A `WikiTab` existente separa `Verbetes` e `Moderação`. Revisões são consultadas por `WikiEditorialQueue` e propostas pela fila privada da Wiki; ações abrem os formulários nativos Drupal/Content Moderation ou o Form API proprietário de decisão. O Studio verifica o acesso das rotas sob o purpose Wiki antes de emitir links. Testes Kernel combinados cobrem gates da aba e renderização das filas. A homologação visual em Firefox ESR e o Runtime permanecem pendentes; a Wiki continua desabilitada no Runtime.

## Decisão normativa: ACULTA Studio (2026-10-22)

ACULTA Studio is the **existing Drupal submodule**, not just the GanjaLab name. GanjaLab is its existing area-based account workspace; GanjaLab420 and WE SATIVA are distinct user experiences under the Studio interface platform, with their actual availability governed by implementing providers. Shared navigation/layout/orchestration belongs here; Wiki and other kernels own data, forms, workflow and access. Do not force WE SATIVA into the account-purpose StudioTab.

[Contrato e roadmap transversal](../../../../../../docs/architecture/ACULTA-STUDIO-EXPERIENCE-PLATFORM.md). Esta decisão prevalece sobre terminologia anterior que equiparava o submódulo exclusivamente a GanjaLab. **Não declarar consumidores planejados como rotas implementadas.**

## WE SATIVA — primeira entrada implementada (Studio dev.3 / Wiki dev.39)

`/studio/we-sativa` resolve no purpose `account` e delega a consulta para o
serviço `aculta_wiki.contributor_workspace`. A tela inicial consulta até 20
verbetes e propostas com UID da conta e revalida Entity Access. Criação só aparece com
`createAccess('wiki_entry')`; nenhuma fila/moderação é exposta. Kernel real da
Wiki passou (focal: 1 teste / 37 asserções; classe Kernel: 15 / 612,
286 depreciações Drupal/contrib e 16 PHPUnit), cobrindo rota, propósito,
isolamento por autoria e acesso. Formulários de
autoria, paginação e homologação visual/mobile permanecem pendentes.

## Atualização STUDIO 0.1-B / D-A.2-T2

STUDIO 0.1-B/D-A.2-T2: documentado contrato de provedores multi-experiência e removida a enumeração irrestrita de content_moderation_state. O estado exibido depende do acesso à última revisão. Limitação de 50 Nodes antes do filtro persiste; usar provider Wiki especializado para futura fila e não tratar listagem genérica como moderadora.

## GanjaLab420 — migração para ACULTA Studio (G420-A)

[Contrato e inventário de migração](../../../../../../docs/architecture/GANJALAB420-TO-ACULTA-STUDIO-MIGRATION.md). GanjaLab420 é **a experiência LMS do estudante** dentro de ACULTA Studio. Os entrypoints de `/ganjalab420` e `/minha-aprendizagem` foram transferidos para `aculta_studio.ganjalab420_controller`, que delega ao controlador LMS; o LMS preserva dados, progresso, matrículas, certificados, controle de acesso e cache. Não confundir com GanjaLab editorial nem com WE SATIVA. A extração da composição visual para Studio será a G420-C; não declarar concluída. Antes do deploy habilitar Studio se necessário e executar testes Function/Kernel e smoke de rotas.

## Estado normativo em 2026-10-22 — corrige trechos históricos acima

Este arquivo contém seções históricas anteriores à plataforma multi-experiência. **Prevalece este contrato:** ACULTA Studio é o submódulo transversal; GanjaLab é o editor/moderador, GanjaLab420 é o estudante, WE SATIVA é a futura experiência de colaboração Wiki. `conta.aculta.*` é o host resolvido pelo purpose `account`, não um hostname fixo no PHP. URL canônica do editor: `/studio/ganjalab` e níveis `/studio/ganjalab/{area}/{secao}/{aba}`; URL canônica do estudante: `/studio/ganjalab420`. `/ganjalab`, `/ganjalab420` e formas antigas `/studio/{aba}` permanecem aliases temporários; não considerar esses caminhos canônicos nem remover sem auditoria de consumidores. O cabeçalho multidomínio global é invariável.

A lista antiga que diz haver somente aba Revista está desatualizada: há plugins reais Revista, Wiki, Fórum, Cursos e Galerias no diretório `src/Plugin/StudioTab`. Presença de plugin não implica experiência ou integração visual completa. A paginação e o isolamento de permissões/revisões precisam de testes antes de exibir filas moderadoras. G420-C2 tem cards SDC montados em código, mas ainda não foi validada no Runtime nem no browser.

Próxima frente: **GANJALAB E-0.1 (editor)**; consulte [ROADMAP.md](docs/ROADMAP.md). G420-C2-T fica como gate pendente/condicionado aos trabalhos paralelos LMS/tema. Qualquer operação de escrita/edição/moderação permanece no módulo dono; Studio não absorve entidade, workflow nem ACL.

## Entrada editorial publication-first — GANJALAB E-A.1

A home canônica `/studio/ganjalab` sob purpose `account` ganhou cabeçalho de criação e cartões de ações de publicação autorizadas pelos módulos donos. Reutiliza `aculta420:context-header`, `card` e `content-grid`, sem novo CSS/Twig nem ações fictícias. A home mantém áreas, seções e abas abaixo da entrada. A experiência é progressiva e os formulários reais continuam onde o dono os oferece. Auditoria e pendências em [docs/GANJALAB-E-A-EDITOR-AUDIT.md](docs/GANJALAB-E-A-EDITOR-AUDIT.md). Não declarar homologação visual/mobile ou cache antes dos testes.

## GANJALAB E-B.1 — Meu trabalho

A Revista apresenta `Meu trabalho → Meus conteúdos` como vista pessoal inicial e preserva filtros de rascunho, revisão, publicação e arquivamento no escopo do autor. O filtro `uid` é aplicado à consulta antes da paginação Drupal (20 candidatos por página), com checagem de entidade/revisão. **Filtro de estado pós-pager ainda é parcial** e requer provider especializado; listagens de equipe e moderação não foram disponibilizadas nesta fase. Esta entrega não modifica workflow, Form API nem ownership dos módulos. Consultar `docs/ROADMAP.md` para gates e limitações.

## GANJALAB E-B.2 — correção da contagem dos filtros pessoais

As listagens `scope: mine` agora calculam o pager depois de filtrar por estado da **última revisão autorizada**, sem contar nós de terceiros ou rascunhos invisíveis. A consulta usa `accessCheck(TRUE)`, `uid` e lotes de 100; cada revisão exige acesso explícito. O pager exibe 20 itens por página. Isto corrige a ordem lógica do E-B.1, **mas ainda não houve teste Runtime e a varredura integral de candidatos do autor requer gate de performance**. As filas de moderação não usam esse pager.

## Paginação para volumes grandes — E-B.2-PERF

`Meu trabalho` usa cursor `studio_cursor` com seek (`changed`, `nid`) e leitura limitada a até 10 lotes de 100 candidatos por requisição. Não faz mais varredura de todo o acervo do autor para contar páginas. A interface oferece “Carregar mais publicações” em link HTTP real, preservando acesso por nó e revisão, filtros e cache por usuário/cursor. Não existe número total exato de páginas; o avanço pode exigir várias solicitações em estados muito raros. Validação Kernel, concorrência e desempenho real permanecem pendentes. Consultar `docs/ROADMAP.md`.
