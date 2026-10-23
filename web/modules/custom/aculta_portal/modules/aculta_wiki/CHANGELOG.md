# Changelog — ACULTA Wiki

## 0.1.0-dev.21 — paginação completa da fila de revisões

- `WikiEditorialQueue::entryRevisions()` percorre candidatos em lotes e
  revalida Domain, estado, revisão mais recente, Entity Access e transições.
- Um limite de scan mantém cada requisição finita. Quando filtragem de acesso
  reduz uma página, o cursor avança pelo último VID verificado para que itens
  autorizados mais antigos não desapareçam.
- Kernel cobre 26 revisões sem grant mais recentes que uma revisão visível; o
  item autorizado continua presente na página.
- Kernel MariaDB completo: 9 testes / 344 assertions; 284 depreciações
  Drupal/contrib e 10 do PHPUnit. PHPCS e lint do serviço/teste aprovados.
  Runtime segue desabilitado.

## 0.1.0-dev.20 — caracterização de rotas e acesso em Kernel

- Kernel da Wiki verifica novamente bootstrap real e discovery das três rotas
  públicas, seus nomes/caminhos e a negação quando `access content` é removida.
- Novo cenário exercita o CTA inicial com Entity API: conta autorizada e com
  e-mail confirmado recebe acesso; conta sem confirmação é negada. O resultado
  continua cacheável por permissões.
- Kernel MariaDB completo: 9 testes / 342 assertions; 284 depreciações
  Drupal/contrib e 10 depreciações PHPUnit. Unit: 66 testes / 338 assertions;
  3 depreciações PHPUnit.
- Sem alteração de Runtime; a Wiki permanece desabilitada.

## 0.1.0-dev.19 — moderação na WikiTab existente do Studio

- `WikiTab` oferece uma seção Moderação com gates separados: transições
  válidas + edição ampla para fila de revisões; `moderate wiki proposals` para
  fila de propostas. Moderadores de proposta não ganham as listagens genéricas
  de verbetes/revisões.
- `StudioListingBuilder` consome `aculta_wiki.editorial_queue` opcionalmente e
  renderiza páginas cacheáveis de propostas/revisões. A revisão passa por nova
  checagem de acesso à rota no purpose `wiki`; “Abrir para revisão” usa o
  formulário Drupal do Node/Content Moderation. Proposta abre o Form API Wiki,
  com texto, justificativa, fontes e links à revisão-base/verbete quando a rota
  autoriza acesso.
- A rota de decisão da proposta permanece no contexto account/GanjaLab. Nenhuma
  tabela, ACL, storage ou workflow de moderação foi criada no Studio. Runtime,
  Firefox/X11, acessibilidade visual e decisão/aplicação do aceite pendentes.

## 0.1.0-dev.18 — decisões editoriais revisionáveis

- `WikiProposalDecision` e `WikiProposalDecisionForm` permitem pedir ajustes ou
  recusar proposta com nota obrigatória em texto simples. Cada decisão grava
  nova revisão privada e conserva o estado anterior no histórico.
- Serviço exige `moderate wiki proposals`, confere revisão-base do formulário,
  estado pendente e acesso de atualização. Um lock `FOR UPDATE` na entidade
  serializa decisões concorrentes; submissão obsoleta recebe conflito.
- Aceite/aplicação do conteúdo segue indisponível até definir se o editor aplica
  manualmente em uma revisão do verbete. O Form API de decisão não tem rota nem
  integração Studio nesta entrega.
- Kernel focal verifica decisão, nova revisão, preservação da antiga, conflito
  obsoleto, nota obrigatória, recusa e negação a colaborador. Suíte Kernel
  MariaDB: 8 testes / 301 assertions, sem warnings; 284 depreciações Drupal/
  contrib e 9 PHPUnit.

## 0.1.0-dev.17 — acesso confirmado e link contextual

- `WikiProposalSubmission` exige confirmação de e-mail pelo contrato
  `EmailConfirmationPolicy` além da permissão específica; a checagem também
  protege o GET do formulário.
- `WikiProposalLinkHooks` adiciona o link no verbete público somente no purpose
  `wiki`, para contas confirmadas com permissão de propor e sem acesso de edição
  direta. Render cache varia por permissões, usuário e domínio e incorpora os
  resultados de Entity Access.
- Kernel usa o service hook real e comprova link para contribuinte habilitado,
  ausência de link para conta não confirmada, submissão negada para conta não
  confirmada com permissão e persistência para a confirmada. Kernel completo:
  8 testes / 290 assertions, sem warnings; 284 depreciações Drupal/contrib e
  9 PHPUnit.

## 0.1.0-dev.16 — envio de propostas por Form API

- Adiciona `/wiki/verbetes/{node}/contribuir`, protegida por `submit wiki
  proposals` e pelo acesso de visualização do Node. O formulário fornece tipo,
  texto simples, justificativa e até dez fontes HTTP(S) (uma por linha).
- A submissão chama `WikiProposalSubmission`, que revalida conta, verbete,
  publicação, propósito e revisão-base corrente. O formulário informa que a
  proposta não altera o verbete automaticamente. Não foi adicionada UI para
  moderar nem aplicar propostas; o significado operacional do estado `accepted`
  depende da definição do fluxo editorial.
- Kernel MariaDB cobre discovery/acesso da rota, construção do Form API,
  validação e submissão via callback; o serviço existente cobre acesso e
  persistência privadas.

## 0.1.0-dev.15 — consulta editorial autorizada

- `aculta_wiki.editorial_queue` fornece páginas de propostas pendentes e de
  revisões atuais em `review`, com cursores, cache metadata e acesso por conta.
- A fila de revisões descobre VIDs a partir dos registros de Content Moderation
  (cujo acesso de entidade é negado pelo Core), mas só retorna revisões atuais
  `wiki_entry` do domínio Wiki após revalidar `view`, `view revision`, `update`
  e transições válidas para a conta atual dentro do purpose `wiki`.
- Kernel real usa a política Portal `email_confirmed`, role editorial,
  workflow exportado e Domain principal para provar consulta e restauração do
  purpose. A fila de propostas cobre paginação 25+1, cursor malformado,
  permissão e cache. Nenhuma rota nem interface Studio foi adicionada.
- Validação MariaDB: `WikiLeadImageLifecycleKernelTest`, 8 testes / 274
  assertions, sem warnings; 284 depreciações Drupal/contrib e 9 PHPUnit.
  O módulo continua desabilitado no Runtime.

## 0.1.0-dev.14 — persistência segura de propostas

- Adiciona a entidade privada e revisionável `aculta_wiki_proposal` com vínculo
  ao verbete e à revisão-base, intenção allowlisted, texto proposto,
  justificativa, múltiplas fontes, estado e retorno editorial. Cada decisão posterior deve
  gravar nova revisão para manter histórico.
- Acesso separa envio, leitura própria, leitura editorial e moderação. A pessoa
  autora não pode atualizar nem excluir a proposta; moderadores obtêm acesso
  apenas pelas permissões dedicadas.
- `WikiProposalSubmission` exige `submit wiki proposals`, propósito `wiki`,
  verbete publicado e visível, revisão padrão e ausência de acesso direto de
  atualização. O texto é armazenado sem formato HTML; até dez fontes aceitam
  somente URLs HTTP(S).
- Kernel MariaDB exercita persistência, revisão-base, privacidade, acesso de
  moderador, purpose, recarga após edição concorrente, rejeição de URL insegura
  e negação de conta sem permissão (1 teste / 37 assertions, sem
  warning; depreciações Drupal/contrib permanecem).
- Suíte Kernel real completa: 7 testes / 225 assertions, sem warnings (284
  depreciações Drupal/contrib e 8 do PHPUnit). Unit: 66/338; PHPCS e PHP lint
  aprovados.
- Não há rota, formulário ou aplicação da proposta no Node nesta entrega. O
  módulo e Runtime continuam desabilitados; configuração de roles antecipa as
  capacidades para ativação futura. Política de retenção/expiração não foi
  escolhida e não há remoção automática de propostas.

## 0.1.0-dev.13 — testar rotas e grants com bootstrap real

- O Kernel inicializa os módulos reais da Wiki/Portal e descobre as três rotas
  públicas e a página da View `/wiki/verbetes`.
- A busca passa explicitamente a consulta o `current_user` como metadado de
  conta do Entity Query. Kernel exercita dois usuários da mesma função com
  grants de visualização distintos; cada um recebe apenas seu verbete. Busca
  vazia e sem resultados mantém `user.node_grants:view`.
- Acesso às rotas públicas exige `access content`; o teste verifica permissão
  concedida e negada. UID 1 não é usado como prova de autorização.
- A fixture de grants existe apenas em `tests/modules`; Runtime, configuração
  sincronizada e projeção v3 do Content Index não mudaram.
- Validação inicial: Kernel MariaDB 6 testes/152 assertions (1 warning do Core
  em `EntityStorageBase::loadMultiple()`, registrado para investigação); Unit
  66/338; PHPCS e PHP lint aprovados.

## 0.1.0-dev.12 — sincronizar visibilidade da imagem de destaque

- O upload guarda o arquivo em `private://wiki/imagens` e prepara o diretório
  pelo serviço de arquivos do Drupal.
- Media e permanência do File são preparadas em transação com o Node. A Media
  permanece despublicada durante a submissão e recebe o estado da revisão
  submetida imediatamente antes de salvar o verbete; erro ou rebuild reverte a
  transação.
- O formulário só substitui o handler de salvamento quando encontra o handler
  nativo do formulário de entidade, evitando transações sem callback de commit.
- Testes Unit e Kernel de MariaDB cobrem visibilidade de Media/arquivo em
  rascunho e publicação, rollback em falha e remoção preservando a referência
  histórica (Kernel atualizado: 4 testes / 92 assertions); resultados ficam na
  matriz.
- Sem mudança no provider do Content Index ou em sua projeção v3. Nenhuma
  configuração foi importada e Runtime não foi alterado.

## 0.1.0-dev.11 — caracterizar grants e criação de verbetes

- Busca e página inicial agora variam cache por `user.node_grants:view`, pois a
  query de resultados aplica `accessCheck(TRUE)` e a listagem recente revalida
  acesso por entidade.
- O CTA de colaboração usa `EntityAccessControlHandler::createAccess()` para
  `node:wiki_entry`; o `AccessResult` mantém seus metadados de cache no render.
- Rotas públicas e configuração não mudaram. Testes Unit e gates aplicáveis
  ficam registrados na matriz desta versão; não houve validação no Runtime.

## Auditoria R1 — 2026-10-23

- Handoff documentado: provider Wiki v3 cobre metadados allowlisted e relações
  `related_entry`, `wiki_category` e `wiki_image`; os providers de categoria e
  Media mantêm ownership na Wiki e em `aculta_image`, respectivamente.
- Confirmado no código e na evidência existente o boot combinado real de Wiki +
  Index, com discovery por tag e registry. Não repetido nesta sessão porque
  `SIMPLETEST_DB` não está configurada.
- Unit Wiki executado: 61 testes / 277 assertions aprovados, 3 depreciações
  PHPUnit. Sem mudança de projeção, portanto a versão permanece 3; rebuild de
  geração segue necessário após mudanças semânticas e não é disparado aqui.
- Runtime e geração do Index não foram alterados.

## 0.1.0-dev.10 — integrar categorias e imagens ao Content Index

- `WikiContentIndexProvider` agora projeta relações outgoing allowlisted:
  `related_entry`, `wiki_category` e `wiki_image`; a projeção passa à versão 3.
- Novo `WikiCategoryContentIndexProvider` indexa somente metadados mínimos de
  `taxonomy_term:wiki_category` no próprio owner `aculta_wiki`, com purpose
  canônico `wiki`. A dependência Core `taxonomy` foi declarada.
- Imagens apontam a `media:image` e usam o provider que já pertence a
  `aculta_image`; nenhum arquivo, URL, descrição de categoria ou corpo é copiado.
- Kernel MariaDB consulta as relações via Index com Node, Term e Media reais,
  e filtra categoria/imagem não publicadas por Entity Access (3 testes / 84
  assertions; 5 depreciações). Unit da Wiki: 61 testes / 277 assertions; 3
  depreciações PHPUnit. A Wiki permanece desabilitada no Runtime.

## 0.1.0-dev.9 — confirmar consulta de relações do Index em MariaDB

- Kernel do Content Index consulta `related_entry` com a classe do provider e
  a policy Entity Access de produção, usando Domains persistidos. A origem e os
  alvos são revalidados no purpose canônico `wiki`; alvo não publicado é
  filtrado e o Domain `main` é restaurado.
- O teste compila a declaração e a tag a partir do YAML owner, mas usa bridge
  fixture para a policy Portal; não ativa a cadeia completa de DI da Wiki.
- MariaDB dedicado: 2 testes / 32 assertions PASS; 5 depreciações do módulo
  Domain. A cobertura já estava aprovada em SQLite; categories/image continuam
  adiadas até finalizar contratos da Wiki.

## 0.1.0-dev.8 — atualizar evidência de acesso do Content Index

- Kernel `ContentIndexWikiAccessKernelTest` exercita o filtro real com a policy
  `EntityHooks`, provider de produção e Domains persistidos: nega no purpose
  `main`, autoriza em `wiki` e restaura o Domain anterior (1/16).
- O teste usa bridge de fixture e não ativa a cadeia completa da Wiki. O mapa de
  services valida separadamente o YAML/tag owner. Grants específicos,
  homologação Runtime e as relações de categorias/imagem continuam pendentes;
  estas duas relações permanecem adiadas até a finalização da Wiki.

## 0.1.0-dev.7 — registrar evidência Kernel do Content Index

- Harness Kernel do Index usa `WikiContentIndexProvider` real e cobre backfill,
  `related_entry` deduplicada, update de revisão padrão e delete (1/17).
- O módulo continua desabilitado; YAML owner, Domain/grants reais, Runtime e
  relações de categoria/imagem permanecem pendentes/adiadas.

## 0.1.0-dev.6 — documentar gates de integração do Content Index

- Registra a cobertura Unit existente do provider, sem tratá-la como prova de
  discovery DI ou autorização integrada.
- Acrescenta à matriz os Kernel e Runtime ainda pendentes; categorias e imagem
  permanecem adiadas até a finalização dos contratos da Wiki.
- Suíte Unit da Wiki executada: 57 testes, 258 assertions; 2 deprecações do
  PHPUnit observadas.
- A Wiki permanece desabilitada; nenhuma configuração ou estado do Runtime foi
  alterado.

## 0.1.0-dev.5 — versão da projeção no fingerprint do Index

- Provider Wiki declara sua versão de projeção para invalidar rebuilds
  parciais quando mudar a projeção de metadados ou relações.

## 0.1.0-dev.4 — relação outgoing de verbetes no Content Index

- `WikiContentIndexProvider` projeta `field_wiki_related` como relação nomeada
  `related_entry` para `node:wiki_entry`, deduplicada e unidirecional. Cada
  destino continua sujeito ao próprio provider e à revalidação de Entity
  Access no purpose canônico `wiki`.
- Categorias (`field_wiki_categories`) e imagem (`field_wiki_image`) continuam
  adiadas até a finalização de seus contratos. Testes Unit cobrem a allowlist e
  deduplicação; o módulo permanece desabilitado no Runtime.

## 0.1.0-dev.3 — acesso canônico do Content Index

- O provider da Wiki declara o purpose canônico `wiki`; consultas do Index
  vindas de outros domínios revalidam o Entity Access com o mesmo usuário e
  grants, falhando fechado se o domínio não puder ser resolvido.
- Teste Unit cobre o purpose declarado pelo provider. Categorias e imagens
  continuam dívida até a finalização da Wiki.

## 0.1.0-dev.2 — provider inicial do Content Index

- Serviço opcional `aculta_wiki.content_index_provider`, marcado com
  `aculta_index.provider`, projeta somente metadados allowlisted de
  `node/wiki_entry`: título, UUID, idioma, bundle, domínio, estado e datas.
- Corpo, categorias, imagem e todas as relações ficam como dívida técnica até
  a finalização da Wiki, conforme decisão do responsável. Não foi criada
  dependência obrigatória do submódulo `aculta_index`.
- Teste Unit cobre projeção, bundle e limite de revisão padrão. O módulo não foi
  habilitado no Runtime.

## Não lançado — busca e aviso de revisão — 2026-10-22

- O `wiki_entry` entra em `bundles.selected` do índice `aculta_conteudo`
  (`config/sync/search_api.index.aculta_conteudo.yml`), para que verbetes
  publicados apareçam na busca do site. A busca própria da Wiki
  (`WikiController::findWikiEntries()`) e as rotas `/wiki`, `/wiki/busca` e
  `/wiki/busca/previa` não mudam. O `SearchPageController` reaplica
  `access('view')`, então rascunho e revisão não aparecem.
- `WikiReviewHooks` (`entity_update`) avisa quando um `wiki_entry` **entra** em
  revisão, comparando o estado atual com `getOriginal()` — a propriedade
  `original` está depreciada desde o Drupal 11.2.
- `WikiReviewMailType` (`aculta_wiki_review`) declara as variáveis `verbete`,
  `estado` e `link`. `WikiReviewNotifier` enfileira uma mensagem por endereço
  editorial na fila do `aculta_mailer`, com `MailMessage` (UUID v4, `pt-br`) e
  contexto **sem o nome de quem editou**.
- Sem dependência de `aculta_mailer` em `info.yml`: a fila e a URL pública chegam
  como serviços opcionais (`@?aculta_mailer.queue`,
  `@?Drupal\aculta_mailer\Api\PublicUrlInterface`); sem o mailer, nada é enviado
  e nada quebra.
- Aviso desligado por padrão, sem destinatários. A tela
  `/painel-administrativo/aculta/wiki` (`aculta_wiki.settings`, permissão
  `administer aculta_wiki`, item de menu "Wiki420" no painel) liga o aviso,
  valida os endereços e salva a lista normalizada. A configuração não tem arquivo
  em `config/sync` nem `config/install`; os padrões vivem no código.
- Pacote LGPD no mesmo commit: linha `aculta_wiki` e pendência 3 do inventário,
  cláusula 5 das cláusulas de aceite, bullet da Wiki na política de privacidade,
  regra em `AGENT-CONTENT.md` e neste `AGENTS.md`.
- Testes: `WikiReviewNotifierTest` (6), `WikiReviewHooksTest` (8),
  `WikiReviewMailTypeTest` (3) e `WikiSearchIndexConfigTest` (1). Suíte do
  submódulo: `52 testes, 240 asserções, OK`.
- Deploy: verbetes já publicados só entram no índice após
  `drush search-api:index` no ambiente. Nenhuma operação de Runtime nesta sessão.

## Não lançado — upload da imagem de destaque — 2026-10-22

- O `field_wiki_image` deixa de ser um autocomplete de mídia no formulário do
  verbete. `WikiEntryImageHooks` (`form_node_wiki_entry_form_alter`) oculta o
  widget central e abre a aba **"Imagem de destaque"** dentro das abas verticais
  `advanced`: um `managed_file` em `public://wiki/imagens`, o campo de descrição
  (até 512 caracteres, como no Fórum), o resumo da imagem atual e a opção de
  removê-la.
- A aba consome o **consumidor `wiki` da política central**
  (`aculta_portal.user_upload_policy`): `UserUploadSettingsForm` ganhou a aba
  "Wiki", `config/sync/aculta_portal.user_upload.yml` ganhou `consumers.wiki`
  herdando a política global e `aculta_portal.schema.yml` ganhou o mapeamento.
  Extensões, tamanho e inspeção da imagem continuam vindo do mesmo serviço que
  o Fórum usa.
- `WikiLeadImageUploader` valida na submissão (um arquivo por envio, descrição
  obrigatória, imagem real dentro da política) e, antes de salvar o verbete, cria
  uma mídia `image` publicada — título = nome do arquivo, `field_media_image`
  com `alt` — e aponta `field_wiki_image` para ela.
- Decisão: **aba em vez de diálogo modal**. Um diálogo não teria como gravar no
  campo de autocomplete de um nó ainda não salvo sem repetir a mecânica AJAX da
  Media Library; a aba reusa o padrão do diálogo do Fórum sem esse acoplamento.
- Decisão: **substituir não apaga**. A mídia anterior fica no banco porque
  revisões publicadas podem apontar para ela; a limpeza fica para uma frente
  própria.
- A aba define `#tree => TRUE` explicitamente: `<details>` não sintetiza essa
  propriedade e, sem ela, os valores não chegariam sob `wiki_lead_image`.
- O uploader marca o arquivo como permanente antes de criar a mídia, porque o
  envio não passa pelo autocomplete que registraria o uso do arquivo e ele
  seria coletado como temporário.
- A aba só aparece quando a política do consumidor tem alguma extensão de
  imagem habilitada; caso contrário o widget central continua visível para não
  travar o campo.
- `aculta_wiki.info.yml` passa a declarar `drupal:file` e `drupal:media`.
- Testes: `WikiLeadImageUploaderTest` (10), `WikiEntryImageHooksTest` (6) e
  `WikiLeadImagePolicyTest` (3). Suíte do submódulo: `32 testes, 158 asserções,
  OK`. PHPCS `Drupal,DrupalPractice` limpo nos arquivos novos.
- Nenhuma operação de Runtime; nenhum cache limpo. A validação em navegador
  (ACULTA420 e Olivero) fica para depois da importação de configuração.

## Não lançado — imagem e campos no card da listagem — 2026-10-22

- O view mode `teaser` do `wiki_entry`
  (`config/sync/core.entity_view_display.node.wiki_entry.teaser.yml`) passa a
  renderizar `field_wiki_image` pelo mesmo formatador da página do verbete
  (`entity_reference_entity_view`, view mode `default`, sem link); o campo sai
  de `hidden`. A ordem do card fica imagem (peso 0), resumo (1) e categorias
  (2). Nenhum view mode de mídia novo e nenhuma configuração adicional.
- O tema exibe a imagem no card: `node--wiki-entry--teaser.html.twig` calcula
  `has_image` e repassa o campo ao SDC `aculta420:wiki-entry`, que já tinha os
  slots `image`; `wiki-entry.css` recorta em 16:9 (`aspect-ratio` +
  `object-fit: cover`) e a story `wiki-entry.teaser.story.yml` cobre o estado
  com imagem, resumo e categorias.
- Nenhuma URL muda (`/wiki`, `/wiki/verbetes`, `/wiki/busca`,
  `/wiki/busca/previa`). Os blocos `block_changes` e `block_recent` não foram
  tocados.
- `WikiTeaserConfigTest` (2 testes) trava o contrato: imagem sem link via
  entidade de mídia, ordem dos campos e `body`/relacionados/referências
  ocultos. Execução junto da suíte do módulo: `13 testes, 59 asserções, OK`.
  Validador de SDC: `PASS (41 components, 25 include calls, 610 checks)`.
- Nenhuma operação de Runtime; nenhum cache limpo. A renderização em navegador
  (ACULTA420 e Olivero) fica para depois da importação de configuração.

## Não lançado — contagem e paginação da listagem — 2026-10-22

- A view `wiki_entries` (`config/sync/views.view.wiki_entries.yml`) passa a
  contar e paginar a listagem pública. O display `page_1` (`wiki/verbetes`,
  caminho inalterado) usa pager completo de 12 itens e cabeçalho
  `@total verbetes publicados`, exibido inclusive quando não há resultados.
- Os blocos `block_changes` (5 itens) e `block_recent` mantêm o limite fixo,
  sem paginador; a troca fica restrita ao display da página.
- `url.query_args` entra nos contextos de cache da página para que `?page=N`
  não receba o cache da primeira página.
- `WikiListingConfigTest` (2 testes) trava o contrato: pager e contagem na
  página, blocos intactos. Execução: `2 testes, 13 asserções, OK`.
- Nenhuma operação de Runtime; nenhum cache limpo.

## Não lançado — testes do controlador e do formulário — 2026-10-22

- Cria testes unitários em `tests/src/Unit` para as duas classes movidas:
  `WikiControllerTest` (5 testes) e `WikiSearchFormBuilderTest` (4 testes).
  Execução: `9 testes, 34 asserções, OK`.
- Correção de robustez encontrada pelos testes: `$request->query->get('q', '')`
  lança `BadRequestException` (`Input value "q" contains a non-scalar value.`)
  quando a URL traz `q[]=...`. `WikiController::search()`,
  `WikiController::preview()` e `WikiSearchFormBuilder::build()` passam a ler
  `query->all()['q'] ?? ''` e só aceitam valor escalar; um `q[]` malformado
  deixa o campo vazio em vez de derrubar a requisição.
- Os testes rodam sem o módulo habilitado e sem banco; nenhuma operação de
  Runtime.

## Não lançado — rotas movidas — 2026-10-22

- `aculta_portal.wiki_home` (`/wiki`), `aculta_portal.wiki_search`
  (`/wiki/busca`) e `aculta_portal.wiki_search_preview` (`/wiki/busca/previa`)
  passam de `aculta_portal.routing.yml` para `aculta_wiki.routing.yml`,
  mantendo nomes de máquina, caminhos e permissões (`access content`). Nenhuma
  URL pública muda.
- Os nomes continuam com o prefixo `aculta_portal.` por serem contrato público
  citado em tema, testes e documentação; não foram renomeados.
- Enquanto o módulo `aculta_wiki` estiver desabilitado, as rotas não são
  registradas pelo Drupal. O Portal não as define mais; não há rota duplicada.
- Nenhuma operação de Runtime; nenhum cache limpo.

## Não lançado — controlador e formulário movidos — 2026-10-22

- `WikiController` e `WikiSearchFormBuilder` passam de `aculta_portal` para
  `aculta_wiki`, com os namespaces `Drupal\aculta_wiki\Controller` e
  `Drupal\aculta_wiki\Presentation` e os mesmos construtores. Nenhuma URL muda.
- O serviço `aculta_wiki.presentation.wiki_search_form` passa a ser definido em
  `aculta_wiki.services.yml`.
- O Portal cria `WikiSearchFormBuilderInterface` e injeta o serviço de forma
  opcional (`@?`). Sem o submódulo, `wiki_search` na apresentação é `NULL` e o
  restante da apresentação continua funcionando.
- As rotas do Portal apontam temporariamente para o controlador movido. A
  definição das rotas passa para `aculta_wiki.routing.yml` no commit seguinte.
- O módulo continua desabilitado; nenhuma operação de Runtime.
- `php -l` nos quatro arquivos alterados: sem erros de sintaxe.

## Não lançado — casca do submódulo — 2026-10-22

- Cria o manifesto `aculta_wiki.info.yml` (`0.1.0-dev.1`) com as dependências
  `aculta_portal`, `domain`, `views`, `node` e `content_moderation`. Sem
  dependência de `aculta_statistics`.
- Adiciona `AGENTS.md`, `README.md`, `docs/ROADMAP.md` e `docs/TEST-MATRIX.md`.
- Documento apenas: nenhum código, rota ou configuração foi movido e o módulo
  não foi habilitado. Nenhuma alteração de Runtime.
- Validação desta versão: `validate-own-module-packages` PASS (45 checagens) e
  `validate-public-slugs` PASS (380 rotas; 17 violações contribuídas na linha de
  base DT-P20).
