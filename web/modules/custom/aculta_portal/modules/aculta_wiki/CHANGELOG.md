## Retificação de caminho ACULTA STUDIO WE SATIVA — 2026-10-30

- Rotas e links canônicos usam `/studio/we-sativa` no purpose `wiki`, conforme ADR-038 retificado pelo responsável.
- `/wesativa` não fica como alias ou redirect. Testes de rota após a retificação e homologação Runtime continuam pendentes.

## 0.1.0-dev.118 — proteger grants versionados das propostas (2026-10-26)

- Teste Unit `WikiProposalRolePermissionsConfigTest` verifica que submissão e
  visualização própria ficam em `email_confirmed`, enquanto visualização ampla
  e moderação ficam em `content_editor`; ambos os papéis declaram dependência
  de `aculta_wiki` no Config Sync.
- Resultado: 1 teste / 8 assertions PASS. Os grants continuam ausentes no
  Runtime Wiki; nenhuma configuração ativa foi alterada. O handler permanece
  fail-closed; homologação e reconciliação ativa ficam pendentes.

## 0.1.0-dev.115 — publicar eventos editoriais no Messenger (2026-10-24)

- O publisher Wiki reside no módulo proprietário; o consumer, Message, central,
  canais e workers estão centralizados no `aculta_messenger`. O fluxo legado de
  entrega do submódulo foi removido após migração.
- Unit e Kernel MariaDB integrados passaram; Runtime não homologado.

## 0.1.0-dev.113 — enfileirar eventos editoriais com retry

- `WikiReviewHooks` enfileira revisões padrão que entram em `review`, incluindo
  criação já em revisão; ignora sincronizações e não repete evento em saves
  dentro do mesmo estado. O e-mail editorial continua independente.
- `WikiProposalDecision` grava o evento versionado após salvar a revisão da
  decisão, dentro da transação que aplica/publica os campos aprovados; o e-mail
  à autoria continua independente.
- QueueWorker despacha os eventos por nome estável, usa Queue API confiável e
  mantém itens após falhas/no consumer até expirar o lease. Consumidor
  Messenger e idempotência da gravação do aviso ainda estão pendentes. Install
  e update `10002` preparam a tabela Core `queue` fora de transações editoriais,
  evitando o DDL de criação sob demanda e commit implícito MariaDB.
- Unit passou 18 testes / 73 asserções. Kernel MariaDB isolado comprovou
  rollback/persistência (1/8; 11 depreciações Core/contrib). Kernel abrangente
  de proposta parou após 94 asserções porque o Studio paralelo não fornece
  `aculta_studio.tab_manager` no worktree atual; esse bootstrap segue pendente.

## 0.1.0-dev.112 — fixar os tipos públicos dos eventos editoriais

- Adiciona os eventos imutáveis `WikiEntryReviewRequestedEvent` e
  `WikiProposalOutcomeRecordedEvent`, cada um com `NAME`, schema de fila v1,
  UUID e revisão validados; resultado da proposta restrito a `needs_changes`,
  `declined` e `accepted`. O envelope não carrega conteúdo editorial.
- Unit passou 4 testes / 5 asserções. Ainda não há producer, QueueWorker nem
  integração Messenger; os value objects fecham somente a interface do evento.

## Contrato Wiki → Messenger v1 — avisos editoriais

- Escopo decidido: aviso de revisão de verbete para contas `content_editor`
  atualmente autorizadas e resultado de proposta para sua autoria. Payload
  mínimo por UUID/revisão, deduplicação por destinatário e fila persistente com
  retry; os e-mails transacionais existentes permanecem ativos.
- A especificação está em `docs/MESSENGER-EVENT-CONTRACT.md`. A Wiki ainda não
  emite os eventos; a API pública de registro de avisos do Messenger precisa de
  revisão do owner antes do adapter.

## 0.1.0-dev.111 — instalar schema de propostas em Wiki já ativa

- O update `aculta_wiki_update_10001()` cobre instalações em que a Wiki já
  estava ativa quando `aculta_wiki_proposal` foi introduzida: registra a
  definição da entidade e suas tabelas antes do campo estruturado, sem repetir
  uma instalação já concluída.
- Kernel MariaDB isolado removeu o registro/tabelas da entidade de proposta,
  executou o update real e confirmou as quatro tabelas e `proposed_fields`
  (1 teste / 30 asserções; 197 depreciações Core/contrib, uma PHP/Webform e 2
  PHPUnit).
- O Runtime tinha as rotas após `drush cr`, mas a autorização da WE SATIVA
  consultava tabelas ausentes. A aplicação autorizada está confirmada na
  revalidação R2 abaixo; sem importação de configuração.

### Revalidação R2 no Runtime de teste — 2026-10-24

- `drush updatedb --yes` executou somente `aculta_wiki_update_10001`; as quatro
  tabelas da entidade de proposta passaram a existir e não restaram updates.
  `drush cr` foi executado novamente; Wiki e Studio ativos, Index permaneceu
  desativado.
- Firefox ESR visível em `DISPLAY=:0`, conta autorizada UID 5: 19/19 verificações
  passaram na home Wiki, CTA para o host de conta, início WE SATIVA, Meu Trabalho,
  formulário e retorno; reflow sem overflow em 1280/390/320 px. Sem submissão ou
  escrita de conteúdo. Evidências fora do repositório em
  `/tmp/aculta-wiki-we-sativa-final6`.

## 0.1.0-dev.110 — ligar a home Wiki ao início WE SATIVA

- A home Wiki direciona contas que podem criar `wiki_entry` e acessar a
  experiência no Domain `account` para `/studio/we-sativa/inicio`; as demais
  contas autorizadas para criação mantêm fallback ao formulário nativo. Acesso
  de criação continua condicionado ao `createAccess()` real.
- O Kernel da home verifica destino, Domain, cache de permissão e restauração do
  purpose. A classe `WikiLeadImageLifecycleKernelTest` passou em MariaDB
  isolada: 27 testes / 1.504 asserções; 304 depreciações Core/contrib e 28
  PHPUnit, além de 3 depreciações PHP do Webform contrib.
- `drush cr` foi executado no Runtime de teste após autorização e revelou as
  rotas. A homologação autenticada encontrou as tabelas de proposta ausentes;
  update de schema aguarda a etapa seguinte, sem importação de configuração.

## 0.1.0-dev.108 — notificar aceite de proposta

- A autoria confirmada agora recebe o mesmo e-mail privado de resultado quando
  a proposta é aceita e publicada. O contexto não inclui os campos sugeridos;
  preserva somente assunto, estado, nota editorial e URL protegida.
- Kernel do fluxo completo confirma o aviso de `accepted` junto ao resultado
  existente para `declined` (1 teste / 220 asserções; 202 depreciações
  Core/contrib/PHPUnit). Integração com avisos pessoais do Messenger segue
  pendente de contrato próprio.

## Revalidação R2 — rotas e acesso no HEAD `8d0bf703f` (2026-10-24)

- Três Kernels cobriram discovery das rotas/View, CTA por `createAccess()` e
  resultados da busca para contas com grants diferentes: 3 testes / 167
  asserções. PHPUnit reportou 300 depreciações Core/contrib, 4 PHPUnit e uma
  PHP do Webform contrib. Runtime intocado e não homologado.

## Revalidação WIKI-R1 — HEAD `fc162b184` (2026-10-24)

- Kernels MariaDB de acesso Wiki, bootstrap owner e rebuild Wiki-owned passaram
  em 10 testes / 368 asserções. O contrato do provider v4, relações
  `related_entry`, `wiki_category` e `wiki_image`, fingerprint e Runtime não
  mudaram. PHPUnit reportou 243 depreciações upstream; Runtime/rebuild instalado
  não foram executados.

## 0.1.0-dev.107 — renderizar a home com ACULTA420 no Kernel

- O Kernel instala o tema público, valida o schema de `aculta420.settings` e
  renderiza a home da Wiki com os SDCs `context-tabs` e `wiki-home` no HTML.
  O focal passou em 1 teste / 96 asserções; foram reportadas 300 depreciações
  Core/contrib/PHPUnit. A classe Kernel completa passou em 27 testes / 1.483
  asserções, com 304 depreciações Core/contrib e 28 PHPUnit. HTTP 200 no
  Runtime e revisão visual continuam pendentes.

## Revalidação R2 — ciclo de vida da imagem no HEAD `b5995d171`

- `WikiLeadImageLifecycleKernelTest` passou completa em MariaDB: 27 testes /
  1.475 asserções. Cobertura inclui upload privado, publicação alinhada ao
  verbete, revisão pendente, rollback e retry, retenção de revisões antigas e
  limpeza segura. Foram reportadas 291 depreciações Core/contrib e 28 PHPUnit.
  Sem alteração de código, Runtime, filesystem ou configuração instalada.

## 0.1.0-dev.95 — confirmar CKEditor 5 no formulário de autoria

- O Kernel carrega `filter.format.wiki` e `editor.editor.wiki` exportados e
  submete o formulário Node nativo como conta `email_confirmed`: o formato `wiki`
  está disponível, usa CKEditor 5 e mantém upload direto desativado. Os submits
  de criação/edição usam o formato permitido real. Kernel MariaDB combinado:
  Focais: 2 testes / 120 asserções; classe Kernel completa: 27 / 1.425,
  291 depreciações Core/contrib e 28 PHPUnit. PHPCS/lint e gate Drupal 11
  passam; Runtime e Firefox não foram executados.

## 0.1.0-dev.94 — verificação opcional de títulos parecidos

- A criação nativa oferece a ação Form API “Verificar títulos parecidos”. Ela
  consulta somente títulos publicados no Domain Wiki, com Entity Query e Entity
  Access, limita a seis resultados e revalida acesso ao renderizar. Resultados
  são sugestões: não impedem salvar e a ação não persiste o Node em criação.
- Kernel MariaDB cobre grants diferentes, rascunho e outro Domain; só o título
  público autorizado aparece (focal 1/34; busca mais submit do FormBuilder nativo,
  2/116). PHPCS/lint e gate Drupal 11 passaram; 203 depreciações upstream e 3
  PHPUnit. O validador Portal–tema segue
  falhando em 12 ocorrências preexistentes no escopo Wiki/Portal; a nova classe
  não referencia tema. Runtime e visual intocados.

## Revalidação R2 — rotas e acesso no HEAD `c610030dd`

- Três Kernels MariaDB passaram com 148 asserções: discovery das rotas e View,
  CTA condicionado ao `createAccess()` efetivo e busca/alterações recentes
  isoladas por grants/cache. Sem operação ou homologação de Runtime.

## Revalidação R2 — ciclo de vida da imagem no HEAD `369db1537`

- A classe Kernel MariaDB `WikiLeadImageLifecycleKernelTest` passou completa:
  26 testes / 1.382 asserções. Cobriu upload/validação, mídia privada/publicada,
  revisão pendente, falha e retry, referências históricas e limpeza de órfãs.
  PHPUnit reportou 291 depreciações Core/contrib e 27 PHPUnit. Sem mudança de
  código, Runtime, filesystem ou configuração instalada.

## Revalidação R1 no HEAD `b8e486673` — sem alteração de código

- Providers Wiki/categoria e quatro Kernels Wiki-owned/Index passaram em 20 testes
  / 425 asserções. Projeção v4, relações `related_entry`, `wiki_category` e
  `wiki_image` e fingerprint permanecem sem mudança; 243 depreciações upstream
  e 3 do PHPUnit. Runtime não foi ativado e nenhuma geração instalada foi
  reconstruída.

## Revalidação R1 no HEAD `847e45231` — sem alteração do contrato

- Quatro Kernels MariaDB passaram: provider harness, acesso/relações, bootstrap
  owner real e rebuild Wiki-owned (11 testes / 385 asserções). Projeção v4,
  relações `related_entry`, `wiki_category`, `wiki_image` e fingerprint
  inalterados. 243 depreciações Core/contrib/PHPUnit; Runtime e geração
  instalada intocados.

## 0.1.0-dev.93 — criação guiada e envio explícito para revisão

- O formulário nativo organiza título, síntese, texto, referências, categorias
  e relações, com instruções curtas nos campos iniciais. A ação primária fica
  identificada como “Salvar rascunho”; uma segunda ação envia para `review`
  somente quando a transição está disponível no widget oficial do workflow.
- O submit dedicado define `review` antes do salvamento Core. Kernel MariaDB
  focal passou com 1 teste / 77 asserções, incluindo tentativa de forçar
  `status=1` no rascunho, que permaneceu não publicado. Foram reportadas 203
  depreciações Core/contrib e 2 PHPUnit. Runtime e validação visual intocados.

# Changelog — ACULTA Wiki

## 0.1.0-dev.106 — compor a home Wiki em Kernel

- Kernel invoca `WikiController::home()` sob purpose Wiki e verifica seu render
  array, incluindo categorias, verbetes recentes e alterações recentes. Focal
  combinado: 1 teste / 88 asserções; 199 depreciações Core/contrib e 2 PHPUnit.
- O HTTP 200 renderizado permanece pendente: este bootstrap não ativa ACULTA420,
  necessário aos SDCs usados pelo tema. Nenhuma alteração no Runtime.

## 0.1.0-dev.105 — caracterizar acesso da home por Domain

- Kernel exercita `DomainPurposeRequestSubscriber` com a rota real da home:
  caminho `/wiki` com marcador de raiz é aceito no Domain Wiki; o mesmo caminho
  no Domain principal responde 404. Focal MariaDB: 1 teste / 84 asserções,
  199 depreciações Core/contrib e 2 PHPUnit. Runtime intocado.

## 0.1.0-dev.104 — validar serialização da decisão editorial

- Kernel MariaDB usa um processo PHP e conexão independentes para manter o lock
  da linha do Node enquanto o serviço `WikiProposalDecision` executa. A decisão
  aguarda o lock e aplica sua revisão; o mesmo cenário confirma conflito de
  revisão-base obsoleta sem escrita parcial.
- Focal: 1 teste / 216 asserções; classe completa: 27 testes / 1.468
  asserções, 291 depreciações Core/contrib e 28 PHPUnit. PHPCS/lint passaram;
  Runtime intocado.

### Revalidação WE SATIVA no Runtime de teste — 2026-10-24

- Os Kernels MariaDB do workspace passaram em 3 testes / 240 asserções (200
  depreciações upstream).
- Firefox ESR visível autenticou e confirmou 404 em `/studio/we-sativa` (0/2).
  Drush read-only confirmou Wiki/Studio ativos, Index desativado e ausência da
  rota e do serviço de workspace no container; o DB ativo identificado foi
  `aculta_runtime`. Rebuild permanece aguardando autorização.

## 0.1.0-dev.103 — fixar purpose da rota de envio de proposta

- `aculta_wiki.proposal_submit` declara `_aculta_domain_purpose: wiki`, então
  o subscriber do Portal rejeita acesso ao endpoint no Domain principal.
- Kernel MariaDB confirma metadados e 404 no host principal (focal: 1 teste /
  81 asserções; 199 depreciações Core/contrib e 2 PHPUnit). PHPCS, lint PHP e
  parse YAML passaram. Os Kernels focais de submissão privada e fila editorial
  também passaram (2 testes / 306 asserções; 202 depreciações upstream e 3
  PHPUnit). Runtime intocado.

## 0.1.0-dev.92 — proposta contextual em duas etapas

- O formulário de proposta separa escrita e revisão/envio, preserva os valores
  no estado server-side do Form API ao voltar e exibe os campos selecionados
  antes do POST final. A submissão continua passando pelo serviço existente.
- A revisão-base é capturada ao abrir o formulário. Mudança concorrente gera
  conflito sem persistir proposta; ao voltar, a autoria precisa confirmar que
  revisou o conteúdo para a nova versão. Kernel MariaDB focal passou com 1 teste
  / 202 asserções; Runtime e validação visual intocados.

## 0.1.0-dev.91 — decisão explícita para listas editoriais

- O formulário de moderação não preseleciona mais `replace` em referências,
  categorias ou relações. Ao aceitar um desses campos, a pessoa editora precisa
  escolher substituir ou acrescentar; a validação do serviço permanece como
  proteção de backend.
- Kernel focal MariaDB passou com 1 teste / 183 asserções; 202 depreciações
  upstream e 2 PHPUnit. Runtime intocado.

## 0.1.0-dev.90 — caracterizar grants em Alterações recentes

- O Kernel de acesso agora verifica que duas contas com o mesmo papel e grants
  distintos recebem apenas seus próprios verbetes na lista de alterações
  recentes; confirma também o cache context `user.node_grants:view`.
- Focal MariaDB: 1 teste / 43 asserções; 283 depreciações upstream e 2 PHPUnit.
  Nenhuma alteração do Runtime.

## Revalidação WIKI-R1 no HEAD `5504aced2` — sem alteração de código

- Os dois providers Unit e quatro Kernels Wiki-owned/Index passaram em 20 testes
  / 425 asserções; projeção v4 e fingerprint não mudaram. PHPUnit reportou 243
  depreciações upstream e 3 depreciações PHPUnit.
- Execução isolada com MariaDB; sem alteração de arquivos do `aculta_index`, sem
  ativação ou rebuild no Runtime.

## 0.1.0-dev.89 — seletor contextual de contribuição WE SATIVA

- O CTA em verbetes publicados abre uma página contextual com edição nativa
  para quem tem `update` Entity Access, ou quatro intenções existentes de
  proposta para participantes confirmados sem edição direta.
- A rota revalida bundle, revisão padrão, publicação, Domain Wiki e acesso de
  leitura; a submissão continua no Form API e serviço de proposta já existentes.
- Kernel focal passou com 3 testes / 311 asserções; PHPCS Drupal/DrupalPractice,
  lint PHP e `git diff --check` passaram. Runtime e validação visual não foram
  executados.

## 0.1.0-dev.88 — filtros de estado no Meu Trabalho WE SATIVA

- Adiciona navegação GET para `Em andamento` (rascunho), `Em revisão`,
  `Publicados` e `Todos`, baseada nos estados oficiais do workflow.
- O Domain `account`, o cursor independente das propostas, o marcador
  acessível `aria-current` e o cache context do filtro são preservados. A
  consulta faz varredura limitada e revalida Entity Access antes de renderizar.
- Kernel focal passou com 1 teste / 141 assertions; três Kernels WE SATIVA
  passaram com 3 / 235, incluindo página 20+3 sem perda ou duplicação de
  verbetes filtrados. PHPCS Drupal/DrupalPractice e PHP lint passaram.
  Runtime intocado.

## Revalidação no HEAD `d3bfd3715` — sem alteração de versão ou código

- Content Index: providers Unit Wiki/categoria 9 testes / 40 assertions;
  quatro Kernels MariaDB de provider, acesso/relações, bootstrap e rebuild
  11/385. Projeção v4 e fingerprint mantidos; Runtime intocado.
- Ciclo de imagem: Kernel MariaDB 25 testes / 1.187 assertions. Cobertura atual
  inclui rollback de upload, revisão pendente, visibilidade, referências
  históricas, retenção e limpeza limitada. PHPUnit reportou 291 depreciações
  Core/contrib e 26 PHPUnit; não houve falha.
- O aviso de saída BrowserTest não gravável apareceu no início das execuções;
  não impediu os testes Kernel.

