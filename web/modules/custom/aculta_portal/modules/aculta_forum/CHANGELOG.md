## `0.1.0-dev.326` — isolar fixture de autoria histórica

- `PublicForumLastActivityAuthorKernelTest` usa fixture dedicada para fornecer
  o campo-base de autoria histórica, delegando a definição ao hook de produção.
  Removeu o field storage configurável que colidia com o campo-base.
- Kernel focal 1/23 PASS; suíte Kernel 151/2.886 PASS (42 depreciações);
  Unit 186/1.337 PASS; PHP lint e PHPCS dos arquivos alterados PASS.
- Suíte combinada: 337 testes / 4.223 assertions PASS. Nenhuma escrita no
  Runtime ocorreu. A idempotência do lote já é coberta pelo teste existente;
  a equivalência sintética completa da migração segue pendente.

## `0.1.0-dev.325` — revisão visual do cartão de autoria histórica

- Firefox ESR visível em X11 confirmou o cartão de autoria histórica em 1280,
  390 e 320 px, sem overflow horizontal (7/7 verificações).
- A página servida não tinha posts públicos com `LegacyAuthor`; o cartão foi
  injetado somente no DOM para a revisão visual. A integração de dados reais
  segue coberta pelo Kernel dev.324. Script e limites estão em
  `tests/browser/aculta-forum/`.
- A observação de que faltava teste idempotente foi corrigida em dev.326: o
  Kernel `ForumCommentMigrationBatchKernelTest` já cobre reexecução e
  continuidade em `aculta_simpletest`; esta fase visual não alterou Runtime.

## `0.1.0-dev.323` — aplicar `-gr` no cartão da última atividade

`PublicForumLastActivityAuthorHooks` agora usa o builder de autoria histórica
específico do Fórum. Omissão encontrada na revisão F-D44: cartões de tópico e
comentário já exibiam `-gr`, mas o autor da última atividade não. O memorial
coletivo permanece sem sufixo e não são criadas rotas individuais.

O RC.2 anterior passa a histórico; o componente retorna a Dev até repetir os
testes pertinentes e concluir a validação visual/acessível aplicável. Nenhuma
migração ou alteração de Runtime foi executada.

## `0.1.0-rc.2` — candidato RC após fechar todas as fases — 2026-10-25

- O candidato é declarado só depois do fechamento de F-M0–F-M7: Unit 184/1.332,
  Unit Messenger 261/2.023, Kernel 150/2.863, Functional 6/6, PHPCS sem erros,
  gates de rotas/Portal/slugs/Domain/status PASS e navegador com seguir/avisos
  13/13, acessibilidade 49/49 nos dois temas, categorias 9/9, painel 12/12,
  anexo/mover 4/4 e F-GROUP local.
- Defeitos corrigidos nesta rodada: instalação limpa (configs base, schemas,
  dependências de Commerce e schema_metatag), lock da limpeza, reconciliação de
  avisos ao mover subárvore, validação da migração e denúncias sem post.
- Migração e corte executados no banco local com backup verificado; avisos
  permaneceram estáveis.
- Detalhe, rollback e portas de Alpha em [`docs/RC.md`](docs/RC.md).

## `0.1.0-dev.322` — F-GROUP homologado no ambiente local — 2026-10-25

- `tests/runtime/fgroup-provision.php provision` no Runtime local simulado:
  tipo `forum_associados`, role insider sincronizada, grupo, tópico restrito,
  tópico público e quatro contas. `node_access` recebeu o grant do realm
  `aculta_forum_restricted`; o público seguiu em `all:0`.
- `fgroup-measure.php`: anônimo, ativo sem membership, suspenso e comum
  negados; ativo com membership autorizado. `fgroup-search.php`: o ativo vê 2
  resultados (público + restrito), os demais veem 1, com grants antes da
  paginação. Anexo e galeria seguem a mesma regra.
- Navegador Firefox ESR/X11: ativo com membership lê o título do tópico;
  suspenso recebe 403. `teardown` restaurou `restricted_group_type` vazio e
  `node_access` em `all` (26 linhas).
- Ativação no servidor de testes e em produção não foi executada; produção
  fica para a janela de deploy, conforme decisão de 2026-10-25.

## `0.1.0-dev.321` — migração executável e corte local — 2026-10-25

- As três migrações desativam a validação de entidade no destino, que rodava
  como anônimo e recusava formato `forum` e transição de moderação; o destino
  mantém as próprias checagens. Denúncias pulam linhas sem post migrado.
- Runtime local, com backup `aculta-local-pre-cut-20261025.sql.gz`: posts
  2/2 criados, pais sem pendências, denúncias sem origem; reexecução sem
  duplicar. Corte executado: os 2 Comments legados foram removidos
  (`comments=0`, `posts=5`) e os avisos permaneceram em 3→3. Rollback do lote
  provado em Kernel; restauração integral pelo dump.
- Defeito registrado: `drush migrate:import` da migração de denúncias falha no
  wrapper do migrate_tools, enquanto a mesma migração pela API conclui.

## `0.1.0-dev.320` — reconciliação de avisos ao mover subárvore — 2026-10-25

- `ForumCommentSubtreeMovedEvent` leva os IDs dos comentários movidos. O
  consumidor transfere o agregado ao destino, soma contadores, preserva os
  maiores marcadores e move as chaves de idempotência desses comentários para
  os destinatários cujo aviso foi transferido. Nenhum aviso novo é enviado.
- Política aprovada em MOVE-TOOLS: mover e mesclar; a transferência é do
  agregado do tópico porque a leitura é registrada por tópico, não por evento.
- Unit Messenger 261/2.023; Kernels do Fórum 32/762 nos arquivos afetados.

## `0.1.0-dev.319` — instalação limpa e limpeza concorrente — 2026-10-25

- Configs de site usadas pelos módulos passam a viajar com eles (media type
  image, vocabulários editoriais, servidor Search API, body, tipos de perfil e
  `field_nickname`), percorrendo a decisão de 2026-10-25. Schema de terceiros
  do `aculta_image`, schema dos plugins de View do Fórum, `image_upload`
  desligado, `tracker_settings` e o campo de título das Views foram corrigidos;
  `commerce_cart`/`commerce_store` viram dependência dura do Portal.
- `ForumAttachmentCleanup` adquire lock de banco (`aculta_forum_attachment_cleanup`),
  serializando execuções concorrentes de cron; release garantido em `finally`.
- Suite Functional criada para instalação, rotas, formulário de tópico,
  desinstalação e lock. Kernel dos arquivos afetados 32/762; Unit 184/1.332.

## `0.1.0-dev.318` — regressão para Dev após auditoria do roadmap — 2026-10-25

- O candidato `0.1.0-rc.1` é retirado: a auditoria das fases F-M0–F-M7
  mostrou critérios abertos que a promoção havia tratado como limites de
  Alpha. A versão volta a `0.1.0-dev.318` e o componente a `state: Dev`.
- Fechadas na auditoria: F-M0 (matriz produtor → evento → destinatário →
  storage → canal → preferência → deduplicação → autorização → testes) e
  F-M4 (matriz de perfis dos fóruns restritos).
- Devem ser fechadas antes de novo RC: F-M1/F-M2 (homologação local do
  contrato de avisos), F-M3 (seguir/deixar de seguir e avisos no navegador,
  mobile/teclado), F-M5 (migração sintética completa), F-M6 (mover subárvore,
  concorrência da limpeza, restritos reais) e F-M7 (Functional,
  instalação/desinstalação).
- Gates reexecutados nesta revisão: Unit 184/1.333 e Kernel 150/2.862 no
  MariaDB, 42 depreciações de Core/contrib, exit 0.
- Nenhuma alteração de Runtime, configuração ou dados.

## `0.1.0-rc.1` — candidato a RC — retirado em 2026-10-25

- Promoção a candidato RC: escopo de código fechado, sem item funcional
  obrigatório pendente antes do deploy. Os passos que dependem de operação
  (migração sobre dados reais, ativação de F-GROUP, entrega de e-mail,
  aparelho físico) estão registrados como limites de Alpha em
  [`docs/RC.md`](docs/RC.md), não como pendência de código.
- Gates: Unit 184/1.333; Kernel 150/2.862 em MariaDB `aculta_simpletest`;
  PHPCS 0 erros (1 aviso aceito de `version`); rotas temporárias, Portal
  Drupal 11+, slugs públicos, caminhos de Domain e índice de status PASS;
  `updatedb:status` sem pendências. Os gates globais de documentação de UI,
  de pacotes, de endereços de ambiente e de inventário de Views reprovam
  apenas itens de outras frentes ou do ambiente local (zero do Fórum). O
  inventário de `validate-admin-cleanup.php` recebeu a View
  `aculta_forum_gallery_slides`, que faltava.
- Revisão de código de esforço médio, sete achados. Corrigidos: definição de
  serviço do controlador de histórico com argumento faltante (500 na rota);
  consulta de Groups que cruzava nid e tid de plugins diferentes; denúncia de
  tópico/comentário que aceitava conteúdo despublicado e não revalidava
  acesso; progresso do "marcar tudo como lido" baseado em nid esparso; alias
  fixo de Comment na fila de moderação. Novos testes cobrem os quatro
  primeiros e a aridade geral dos serviços. Os achados de performance e de
  predicado duplicado ficaram como DT-F01/DT-F02.
- Acessibilidade pré-deploy: novo
  `tests/browser/aculta-forum/accessibility-readonly.py` mede título único,
  hierarquia, nome acessível, `alt`, texto de links, IDs e foco por teclado
  em páginas públicas e administrativas. Resultado 49/49 em ACULTA420 e
  49/49 em Olivero.
- Olivero: a agrupação de `/categorias` passa a usar `h2` no título do grupo
  por um `hook_theme` do módulo, eliminando o salto h1 → h3 do template
  genérico do Core; ACULTA420 mantém seu template próprio.
- Correção de infraestrutura local fora do módulo: o serviço do hook de
  avisos pessoais do Messenger passou a público no container, pois era
  descartado na compilação e fazia toda página falhar com
  `ArgumentCountError`; commit separado.

## `0.1.0-dev.317` — homologar aviso de resposta no Runtime local — 2026-10-24

- Runtime local (`127.0.0.1`): resposta publicada por uid 15 em tópico seguido
  pelo uid 1 produziu exatamente um `aculta_aviso` (`forum_resposta`,
  `contador_site=1`, `contador_email=1`, `ultimo_comentario` = CID). O item de
  e-mail foi apenas enfileirado (nenhum cron executado) e removido na limpeza;
  não houve envio. Ao final: zero Comments sintéticos, zero flaggings novos e
  avisos no baseline.
- Para o Runtime ficar apto, foi preciso aplicar updates pendentes do Messenger.
  Corrigidos no caminho: updates 10001/10006 aceitam `allowed_values` em mapa
  ou lista e só gravam quando falta a opção; o campo
  `field_aculta_messenger_enqueue_attempts` (39 caracteres) foi renomeado para
  `field_aculta_enqueue_attempts` (29), pois a Field API rejeita nomes acima de
  32. Divergência local de tipo do campo de amizade foi recriada com os dois
  valores existentes preservados.
- Recorte: ambiente local, não servidor de testes; digest/entrega de e-mail e
  concorrência entre processos continuam fora do escopo autorizado.

## `0.1.0-dev.316` — History preservado na migração de Comments — 2026-10-24

- `ForumCommentMigrationBatchKernelTest` passa a criar um recibo de leitura
  (`history` uid/tópico) antes da migração e prova que ele permanece com o
  mesmo timestamp após importar raiz e resposta: o recibo é por Node, então a
  conversão Comment → ForumPost não o duplica nem o apaga.
- Fecha o item “History” da equivalência de F-M5 em dados sintéticos; flags,
  árvore, conteúdo e embeds já tinham prova própria.
- Testes: migração 5/165; suíte Kernel do Fórum 150/2.862; PHPCS limpo.

## `0.1.0-dev.315` — pré-visualização de anexo e mover comentário na UI — 2026-10-24

- Novo teste de navegador `tests/browser/aculta-forum/synthetic-preview-and-move.py`
  (Firefox ESR/X11, local): cria imagem/Media/tópico/Comment sintéticos e
  despublicados, abre a pré-visualização autenticada com a imagem e o `alt`
  corretos, e o formulário de mover comentário com destinos, confirmação e
  submit. Resultado 4/4, sem erro visível do Fórum.
- O script apaga as quatro entidades e o arquivo no `finally` e restaura
  `comment_move_enabled`; a verificação posterior registra setting=0 e zero
  nós/media/comments/arquivos sintéticos. Nenhum formulário foi submetido e
  nenhum e-mail foi disparado.
- Recorte: uid 1 no dump local; autorização de não-superusuário segue nos
  Kernels access-aware.

## `0.1.0-dev.314` — fechar o mapa das fases do Fórum — 2026-10-24

- O roadmap passa a registrar o fechamento por fase com a evidência real
  (dev.297–dev.313): F-M4 em Kernel, F-M5 sintético com árvore aninhada, F-M6
  com controladores/anexo/mover tópico/dois temas/painel autenticado e F-M7 com
  os gates executados.
- Só permanecem abertos itens bloqueados por autorização ou ambiente:
  homologação Runtime do Messenger, pré-visualização autenticada de anexo,
  mover comentário pela UI sem Comments no dump, aparelho físico, migração
  sobre dados reais, F-GROUP, RC/Alpha/deploy e testes de e-mail.
- Sem alteração de Runtime nesta fase.

## `0.1.0-dev.313` — revisão autenticada somente leitura do painel — 2026-10-24

- Novo teste de navegador `tests/browser/aculta-forum/authenticated-readonly.py`
  (Firefox ESR/X11, ambiente local simulado): login por link único, painel com
  Conteúdo/Comentários/Revisões/Configurações, fila de publicações, formulário
  de mover tópico com 12 categorias e confirmação obrigatória, configurações e
  reflow em 1024 e 390 CSS px. Resultado 12/12, sem erro visível do Fórum.
- GET-only: nenhum formulário foi enviado, nenhum tópico movido e nenhuma
  configuração alterada. O aviso global de atualizações de segurança foi
  ignorado por não pertencer ao Fórum.
- Recorte: uid 1 no dump local (único com mover tópico e configurações);
  autorização de não-superusuário já está nos Kernels access-aware.
- Testes: Kernel do Fórum 149/2.841; Unit 181/1.019; PHPCS limpo.

## `0.1.0-dev.312` — provar o envio de anexo para o editor — 2026-10-24

- Novo Kernel `ForumAttachmentUploadDialogFormKernelTest`: um PDF válido cria
  exatamente uma Media `forum_attachment` despublicada, com autoria, arquivo
  vinculado e arquivo promovido a permanente; a resposta AJAX devolve
  `editorDialogSave` com UUID, tipo `media` e display de embed, seguida do
  fechamento do diálogo.
- Recusas provadas: dois arquivos, arquivo inexistente, extensão não permitida
  e imagem sem descrição; nenhuma Media é criada.
- Limitações: imagem raster válida e pré-visualização renderizada no navegador
  não foram exercitadas; o teste cobre documentos e as regras de recusa.
- Testes: anexo 2/34; suíte Kernel do Fórum 149/2.841; PHPCS limpo.

## `0.1.0-dev.311` — restaurar o formulário canônico de mover tópico — 2026-10-24

- A operação “Mover de categoria” no tópico apontava para
  `aculta_forum.topic_move`, removida no dev.290; o link quebraria ao ser
  renderizado. A rota canônica volta com o `ForumTopicMoveForm` real (form,
  título, permissão `move aculta forum topics`, upcast `entity:node`,
  GET/HEAD/POST), em vez do redirecionamento placeholder anterior.
- Corrigido defeito real no formulário: `$term->id()` vinha como string e a
  categoria atual aparecia entre os destinos; a comparação passou a converter
  para inteiro.
- O Unit de configuração de tópicos volta a exigir a rota, agora com o form, e
  o Kernel de mover tópicos cobre opções, estado, confirmação obrigatória e
  recusa sem consentimento.
- Testes: Unit 181/1.019; Kernel do Fórum 147/2.807; PHPCS limpo; gate de
  aliases de rota PASS.

## `0.1.0-dev.310` — equivalência de árvore aninhada na migração — 2026-10-24

- `ForumCommentMigrationBatchKernelTest` ganha o cenário de três níveis
  (raiz, filha, neta): assunto, corpo, autoria, `thread_position` = CID,
  `created`, `changed`, publicação, `moderation_state` e `first_published`
  (campo legado `field_aculta_published_at`) são preservados; o mapa aponta um
  post distinto por Comment; a migração de pais liga raiz ← filha ← neta; o
  replay mantém os mesmos IDs e três posts.
- Dados sintéticos em MariaDB; nenhuma migração sobre dados reais foi executada.
- Testes: migração 4/144; suíte Kernel do Fórum 146/2.779; PHPCS limpo.

## `0.1.0-dev.309` — consolidar estado do roadmap do Fórum — 2026-10-24

- O roadmap registra as provas de F-M6 (controladores, moderação, histórico,
  comparação, opções) e a revisão visual local nos dois temas, além dos dois
  defeitos reais corrigidos em dev.307–dev.308.
- O status do componente passa a listar como pendências somente itens que
  dependem de homologação/autorização fora do código do Fórum: Runtime do
  Messenger, anexos autenticados, fluxo de mover tópico na UI, migração sobre
  dados reais, F-GROUP e RC/Alpha/deploy.
- Sem alteração de Runtime nesta fase.

## `0.1.0-dev.308` — corrigir acesso ao diálogo de anexos — 2026-10-24

- Corrigido defeito real: `ForumAttachmentUploadAccess` importava
  `Drupal\user\AccountInterface` (inexistente); qualquer chamada de acesso ao
  diálogo de anexo do editor falhava com `TypeError`. O tipo correto é
  `Drupal\Core\Session\AccountInterface`.
- Novo Kernel `ForumAttachmentUploadAccessKernelTest`: membro autenticado com o
  formato `forum` no purpose `forum` é liberado, com contextos `domain` e
  `user.roles:authenticated`; anônimo, outro formato e purpose `main` não são
  liberados.
- Smoke de carregamento: todas as classes de `src/` do Fórum carregam em PHP
  8.4 e todos os imports de produção resolvem.
- Testes: acesso a anexo 2/14; suíte Kernel do Fórum 145/2.717; PHPCS limpo.

## `0.1.0-dev.307` — corrigir formulário de seleção de revisões — 2026-10-24

- Corrigido defeito real: `ForumPostRevisionSelectionForm` promovia
  `RouteMatchInterface $routeMatch` como propriedade `readonly`, colidindo com a
  propriedade não-readonly de `FormBase`; em PHP 8.4 a classe não carregava
  (fatal), o que quebraria a comparação a partir da segunda página do histórico.
  A propriedade passou a se chamar `$forumRouteMatch`.
- Novo Kernel `ForumPostRevisionSelectionFormKernelTest`: padrões da página,
  envio ordena as revisões da mais antiga para a mais recente, recusa valor não
  numérico, revisões iguais, revisão de outra publicação e ausência do post na
  rota; opções vazias devolvem formulário vazio.
- Testes: seleção 4/61; suíte Kernel do Fórum 143/2.703; PHPCS limpo.

## `0.1.0-dev.306` — revisão visual local dos temas no Fórum — 2026-10-24

- `/categorias` no Growroom local simulado (`growroom420.aculta.toca.net.br`
  resolvido para `127.0.0.1` no perfil do Firefox): 9/9 verificações em
  ACULTA420 e 9/9 em Olivero, com 12 categorias, 12 contadores, sem erro
  visível e sem overflow em 1024 e 500 CSS px.
- Índice de Discussões verificado no mesmo ambiente nos dois temas: sem erro
  visível, contadores e categorias presentes. Tema restaurado para ACULTA420
  após a rodada de Olivero.
- Ambiente local simulado, não servidor de testes; capturas fora do checkout.

## `0.1.0-dev.305` — documentar tokens do Fórum no contrato do tema — 2026-10-24

- `docs/portal/TOKENS-E-ATRIBUTOS.md` passa a registrar o token
  `forum-category-count` (contador de tópicos por categoria) e o atributo
  `data-aculta-forum-post-timestamp`, usados pelo Fórum e mapeados no
  ACULTA420; o gate `validate-portal-ui-docs` deixa de reprovar esses dois.
- Gate: `validate-portal-ui-docs` sem ocorrências do Fórum; demais falhas
  pertencem a LMS/Statistics/Studio (outras frentes).

## `0.1.0-dev.304` — corrigir teste da configuração de tópicos — 2026-10-24

- O Unit `ForumTopicDisplayConfigTest` ainda exigia a rota placeholder
  `aculta_forum.topic_move`, removida em dev.290; o teste agora afirma a
  ausência da rota e mantém as provas de mover comentário e do painel.
- Testes: Unit do Fórum 181/1.014; PHPCS limpo.

## `0.1.0-dev.303` — provar as opções do Fórum — 2026-10-24

- Novo Kernel `ForumSettingsFormKernelTest`: mover comentários continua
  desligado por padrão, o prazo de edição começa em 10 minutos com mínimo zero
  e obrigatoriedade; o envio grava o valor informado e converte negativos em
  zero.
- Testes: configurações 2/8; suíte Kernel do Fórum 139/2.642; PHPCS limpo.

## `0.1.0-dev.302` — provar a comparação entre revisões — 2026-10-24

- Novo Kernel `ForumPostRevisionComparisonControllerKernelTest`: a comparação
  mostra somente o campo alterado, com rótulo em português e contexto de
  domínio em cache; revisão repetida, inexistente ou de outra publicação
  resultam em não encontrado.
- Testes: comparação 2/31; suíte Kernel do Fórum 137/2.634; PHPCS limpo.

## `0.1.0-dev.301` — provar o histórico de revisões da publicação — 2026-10-24

- Novo Kernel `ForumPostRevisionHistoryControllerKernelTest`: a tabela lista a
  revisão mais recente primeiro, distingue a atual da histórica, mostra estado
  e assunto de cada revisão, liga a revisão mais nova à comparação com a
  anterior e não oferece operação na revisão mais antiga; sem revisão anterior
  à página não há formulário de seleção.
- Testes: histórico 1/30; suíte Kernel do Fórum 135/2.603; PHPCS limpo.

## `0.1.0-dev.300` — provar o formulário de moderação por revisão — 2026-10-24

- Novo Kernel `ForumPostModerationFormControllerKernelTest`: o identificador de
  revisão solicitado chega ao form builder, inclusive quando não é a revisão
  padrão; revisão inexistente resulta em não encontrado sem construir formulário.
- Testes: moderação por revisão 2/28; suíte Kernel do Fórum 134/2.573; PHPCS limpo.

## `0.1.0-dev.299` — provar o formulário público de edição — 2026-10-24

- Novo Kernel `ForumPostEditControllerKernelTest`: a autora abre o modo
  `public_submission` para a própria publicação publicada no purpose `forum`;
  UUID ausente ou desconhecido, publicação de outra pessoa, fora do purpose e
  publicação despublicada resultam em não encontrado.
- Testes: edição 2/32; suíte Kernel do Fórum 132/2.545; PHPCS limpo.

## `0.1.0-dev.298` — provar a fila de moderação do Fórum — 2026-10-24

- Novo Kernel `ForumPostModerationQueueControllerKernelTest`: a fila lista
  apenas revisões em `review` de tópicos publicados no purpose `forum` que o
  moderador pode ver; publicações do purpose `main` e já publicadas ficam de
  fora; a linha aponta para revisão e histórico; sem revisões a fila fica vazia.
- Testes: fila 2/39; suíte Kernel do Fórum 130/2.513; PHPCS limpo.

## `0.1.0-dev.297` — provar a construção do formulário público de publicação — 2026-10-24

- Novo Kernel `ForumPostSubmissionControllerKernelTest`: tópico vazio ou
  desconhecido e resposta pai desconhecida resultam em `NotFoundHttpException`;
  tópico válido com pai publicado no purpose `forum` entrega ao form builder um
  rascunho com autor, tópico, pai e `moderation_state = draft`.
- Testes: controlador 2/41; suíte Kernel do Fórum 128/2.474; PHPCS limpo.

## `0.1.0-dev.296` — provar acesso ao histórico e à comparação de revisões — 2026-10-24

- Novo Kernel `ForumPostRevisionAccessKernelTest`: histórico liberado só no
  purpose `main` com acesso ao tópico publicado; comparação aceita duas
  revisões distintas do mesmo post e recusa revisão repetida, inexistente ou de
  outro post.
- Testes: revisões 3/39; suíte Kernel do Fórum 126/2.433; PHPCS limpo.

## `0.1.0-dev.324` — cobrir autoria histórica da última atividade

- Novo Kernel focal executa a View de tópicos e confirma que a resposta mais recente com `legacy_author` usa `buildLegacyForum`, apresenta `-gr` e propaga a tag de cache da autoria. A fixture registra a entidade histórica real sem instalar toda a cadeia de serviços do módulo User; nenhum usuário é criado para representar autor legado.
- Kernel focal 1 teste / 23 assertions PASS; suíte completa do Fórum 343 testes / 4.223 assertions PASS, 42 depreciações e 6 skips. PHP lint e PHPCS Drupal/DrupalPractice dos arquivos novos PASS.
- A revisão Firefox ESR segue pendente até servir o candidato. Nenhum dado Runtime foi alterado.

