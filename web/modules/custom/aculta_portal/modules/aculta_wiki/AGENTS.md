# Wiki — instruções locais

- Dev.80: lock de idempotência entre processos validado com MariaDB e classe
  real de submissão; Kernel usa `NullLockBackend` e injeta `DatabaseLockBackend`
  explicitamente. Classe 24/1.134; Runtime intocado.

- Dev.79: Kernel comprova visibilidade alinhada à revisão pública quando uma
  revisão pendente troca a imagem. Classe MariaDB 24/1.129; Runtime intocado.

- Dev.78: fixtures Unit de `WikiEntryImageHooks` sincronizadas com o construtor
  e contexto de formulário atuais; Wiki Unit 66/344, 3 depreciações PHPUnit.
  Runtime intocado.

- Revalidação R1 no HEAD após dev.77: Unit 9/40 e quatro Kernels MariaDB
  (provider, acesso/relações, bootstrap owner e geração Wiki-owned) 11/385;
  243 depreciações Core/contrib/PHPUnit. Projeção v4/fingerprint sem alteração;
  ativação e rebuild do Runtime não executados.
- Smoke visual WE SATIVA: script Firefox ESR visível adicionado. O Runtime de
  teste não registra a rota/provider nem o serviço `aculta_wiki` no container;
  rebuild necessário aguarda autorização. Não tente contornar via HTTP/cache.
- Fila editorial R3: Kernel MariaDB agora cobre 26 revisões autorizadas em duas
  páginas depois de 26 candidatos sem grant; 1/101, 198 depreciações
  Core/contrib e 2 PHPUnit. Nenhum resultado duplicado/perdido; container
  Runtime intocado.

- Dev.77: formulário `wiki_entry` oferece retorno para o workspace somente com
  rota Studio disponível e `WikiContributorWorkspace::access()` permitido;
  URL usa purpose `account` e cache contexts/tags do AccessResult. Kernel classe
  23/1.039 na dev.77; Runtime intocado.

- Dev.76: envio de imagem usa a forma nativa `managed_file` (`fids`). Kernel
  submete formulário Node real e confirma Media despublicada em `draft`; classe
  MariaDB completa 23/1.036, 291 depreciações Core/contrib e 24 PHPUnit. Runtime
  intocado.

- Dev.75: R1 revalidada no HEAD: Unit dos providers 9/40; três Kernels MariaDB
  10/343, 240 depreciações Core/contrib/PHPUnit. Provider v4 inalterado; Runtime
  não foi ativado nem reconstruído.

- Dev.74: a prévia Wiki é região viva nomeada com links de navegação; o campo
  mantém seu role nativo de busca e as setas não são interceptadas. A Unit do
  builder passa (4/18), a fixture Firefox com o JS local passa (14/14) e o host
  com código antigo falha 7/35; Runtime não atualizado.

- Dev.73: nomes de input/submit agora vêm do comando WebDriver `computedlabel`
  do Firefox, não só da árvore heurística do script. Confirmado no host de teste:
  `Pesquisar na Wiki420` e `Buscar`; CSS candidato 22/22, agregado Runtime 18/20.

- Dev.72: o smoke de teclado compara outline/box-shadow do foco com uma cópia
  sem foco e exige nomes acessíveis no campo/submit. CSS candidato: 22/22;
  Runtime agregado: 18/20 pelas duas falhas de viewport no submit.

- Dev.71: Firefox ESR visível validou navegação por teclado na busca. O smoke
  exige que campo e submit focados caibam na viewport: Runtime agregado falha
  (18/20; submit em x=336 numa viewport de 320 px), folha candidata carregada
  somente por GET passa (22/22). Agregado do Runtime não atualizado.

- Dev.70: Core Node Form de edição está protegido contra revisão obsoleta; lock
  de linha e comparação no save handler fecham a janela após validação. Kernel
  focal de conflito 1/37; criação/revisão normal 1/67; Unit uploader 12/79;
  classe Kernel mídia 22/995 (291 depreciações Core/contrib, 23 PHPUnit).

- Dev.69: Kernel proprietário inicia Wiki + Image + Portal + Index, descobre
  owners reais e completa/valida/ativa geração v4 no banco dedicado (1/42; 201
  depreciações Core/contrib). Runtime/inventário da instalação não cobertos.

- Dev.68: R1 revalidada: Unit dos providers 9/40; Kernels Index provider 1/17,
  acesso 7/238 e owner/bootstrap 2/88, aprovados em MariaDB dedicada. O rebuild
  integral do inventário e a ativação Runtime seguem pendentes.

- Dev.67: Firefox ESR/X11, 46/46 em home/listagem/busca a 1280/390/320 px;
  submit do cabeçalho corrigido no `aculta420` 0.6.1-dev.5. CSS atual entrou
  por GET browser-only; agregado Runtime, Olivero e WCAG integral pendentes.

- Dev.66: Kernel caracteriza limite de 5.000 caracteres, bloqueio de proposta
  retirada, conflito de revisão-base, subconjunto/modes e transição oficial
  (1/159; 201 depreciações Core/contrib, 2 PHPUnit). PHPCS/lint passam; teste
  multiprocesso segue pendente.

- Dev.65: rotas públicas Wiki declaram `_aculta_domain_purpose: wiki`; Kernel
  subscriber real comprova 404 em `main` (1/73), além da raiz `/` e caminho
  interno `/wiki`. PHPCS/lint passaram; somente Wiki foi alterada.

- Dev.64: Kernel caracteriza raiz pública `/` → `/wiki` com o processador de
  domínio real e mantém bloqueio do caminho interno direto (1/65; 199
  depreciações Core/contrib, 2 PHPUnit). Lint e PHPCS passam; Portal/Runtime
  sem mudanças.

- Dev.63: Kernel completo do ciclo de imagem passou em MariaDB (21/941; 291
  depreciações Core/contrib e 22 PHPUnit); publicação da Media/download segue
  estado e referências públicas do Node. Rollback, histórico e cleanup passam;
  Runtime inalterado.

- Dev.62: regressão Kernel de rotas/acesso no HEAD: discovery e listagem,
  CTA por `createAccess()` e busca entre grants (3/127; 283 depreciações
  Core/contrib/PHPUnit). Controller permanece sem alteração; Runtime intocado.

- Dev.61: regressão R1 após mudanças de interface: Unit 66/341 (3 PHPUnit
  depreciações); Kernels provider/acesso/owner real 10/343 em MariaDB isolada
  (243 depreciações Core/contrib/PHPUnit). Projeção v4 sem mudança; Runtime e
  rebuild não executados.

- Dev.60: formulário de moderação GanjaLab compõe contexto da proposta por
  `context-header`/`content-section`; decisão permanece Form API. Kernel focal
  1/156; classe completa 21/941, 291 depreciações Core/contrib/PHPUnit. PHPCS,
  PHP lint passaram; validação visual pendente.

- Dev.59: `list_modes` usa `#tree`; Kernel FormBuilder aplica modos append/
  replace escolhidos em referências, categorias e relações (focal 1/154;
  classe 21/939, 291 depreciações Core/contrib/PHPUnit). PHPCS e PHP lint
  passaram.

- Dev.58: `field_values` usa `#tree` para que os values processados sejam lidos
  pelo serviço de submissão; Kernel FormBuilder cobre validação e persistência
  do campo título (1/59); classe completa 21/935, com 291 depreciações
  Core/contrib/PHPUnit. Runtime inalterado.

- Dev.57: exceções de validação backend da proposta não são exibidas sob o
  campo de resumo incorreto; Kernel focal cobre imagem inexistente e ausência
  de persistência (1/150). PHPCS e PHP lint passaram.

- Dev.56: status da proposta usa SDCs existentes (`context-header`,
  `content-section`); retirada continua limitada à autoria e estados
  submetido/devolvido e passa pelo Form API. Kernel focal 1/148; PHPCS e lint
  passaram; visual pendente.

- Dev.55: R1 revalidada no HEAD após mudanças de WE SATIVA. Unit dos providers
  Wiki/categoria: 9/40; três Kernel do Index com bootstrap real: 10/343,
  MariaDB isolada, 243 depreciações Core/contrib/PHPUnit. Provider permanece
  v4; nenhum rebuild/Runtime.

- Dev.54: workspace WE SATIVA usa SDCs existentes do ACULTA420 para cabeçalho,
  cards, seções, grade e estados vazios. O serviço Wiki preserva Entity Access,
  purpose/Domain e cursores independentes. Focal MariaDB: 2 testes / 89
  asserções; classe Kernel completa 21/923, 291 depreciações upstream/PHPUnit.
  Homologação visual ainda pendente.

- Dev.53: a limpeza de imagem usa cursor `keyvalue.expirable` (TTL 7 dias) e
  percorre candidatos por ID em lotes rotativos de 25, evitando starvation por
  arquivos retidos. Testes Kernel cobrem referência em outro bundle Node, falha
  da inspeção global, retenção de uma hora e passagem ao próximo lote (focal
  4/169; classe completa 21/918; 291 depreciações upstream/PHPUnit).

- Dev.52: o Kernel owner real do Index prova que destino sem provider ativo não
  aparece na consulta outgoing e que Media publicada não abre relação originada
  em verbete `draft`. Classe MariaDB: 7/238, 201 depreciações upstream. Nenhuma
  mudança no provider Wiki ou no fingerprint; não requer rebuild.

- Dev.51: o hook de cron chama `aculta_wiki.lead_image_cleanup`, que considera
  até 25 Files permanentes com mais de uma hora em `private://wiki/imagens/`.
  Reutiliza `UnusedMediaCleaner` e `UnusedFileCleaner` do Portal, que verificam
  referências globais em entidades/revisões e falham fechados. A cobertura
  Kernel retém imagem referenciada por revisão histórica e remove Media/File
  após a última referência desaparecer (1/46). Sem Runtime.

- Dev.50: provider de Node omite `owner_uid`, não projetando o UID autoral
  interno do Index. `getProjectionVersion()` passou a 4 e altera o fingerprint;
  uma nova geração deve ser reconstruída antes de servir a projeção. Sem rebuild
  ou alteração no Runtime.

- Dev.49: ciclo Index real também troca a projeção de `field_wiki_related` e
  verifica remoção da relação antiga e limpeza após delete da origem. Classe
  owner completa: 7/234; 201 depreciações upstream. R1 sem alteração no Runtime.

- Dev.48: Kernel combinado no teste owner real valida backfill Wiki, fingerprint
  de providers da geração, update incremental (rótulo/hash) e delete da projeção
  ativa. Classe completa deve ser reexecutada antes de declarar R1 encerrada.
  Sem rebuild no Runtime; projeção permanece v3.

- Dev.47: Meu Trabalho tem paginação keyset separada para verbetes e propostas,
  links GET no Domain da conta e cache variado pelos dois cursores. A consulta
  limita candidatos por UID e revalida `view`/`update` antes de renderizar.
  Kernel MariaDB: 2/84; 200 depreciações upstream e 3 PHPUnit.

- Dev.46: boot real Wiki + Image + Portal + Index comprova provider v3,
  consulta de relações por grants, rejeição de revisão pendente não padrão,
  revisão padrão publicada ainda projetável e negação sem Domain Wiki. Kernel
  MariaDB: 6/185, 201 depreciações upstream. Sem rebuild/Runtime.

- Dev.45: `ContentIndexWikiAccessKernelTest` inicializa a closure real de Wiki,
  Image, Portal e Index; container descobre providers por tag sem bridge fixture.
  Consulta as três relações, verifica grants e restaura Domain. MariaDB: 4/125;
  projection v3 inalterada, sem rebuild ou Runtime.

- Dev.44: Meu Trabalho oferece “Editar” somente após revalidar `update` do Node
  para a autora no purpose Wiki e monta a rota Core no Domain Wiki. Kernel
  comprova preview nativo no tempstore privado, acesso só à autora e ausência
  de gravação da prévia (focal combinado: 2 testes / 111 asserções).

- Dev.43: download de imagem privada é permitido só se algum verbete publicado
  atualmente a referenciar. Troca/remoção da imagem também reconcilia a Media
  anterior; revisões antigas preservam o arquivo, mas não reabrem o download
  quando não houver referência pública atual. Kernel MariaDB focal: 8 testes /
  237 assertions; PHPCS e PHP lint passam. Não remover arquivo histórico aqui.

- Dev.42: `create_new_draft` aceita origem `draft` para o formulário Core
  selecionar o estado inicial; Kernel persiste um Node privado em `draft` e
  depois uma nova revisão em `review` por `draft_to_review` (1 teste / 54
  assertions). Não conceder `review_to_published` a `email_confirmed`.

- Dev.41: o Kernel constrói o Node Form API real para uma conta confirmada e
  workflow `wiki_editorial`; comprova `draft_to_review` e ausência da opção de
  publicação no widget (focal: 1 teste / 38 assertions). Ainda não submete nem
  persiste o formulário; preservar isso como gate distinto.

- Dev.40: Kernel valida o `node.add` nativo, acesso de criação de conta
  confirmada e link da WE SATIVA explicitamente no Domain Wiki apesar do
  contexto `main` (focal: 1 teste / 41 assertions). Isso não comprova o build
  nem o submit completo do Node Form API; mantenha esse gate pendente.

- Dev.39 WE SATIVA inicial: `aculta_wiki.contributor_workspace` busca apenas
  candidatos com UID da própria conta e revalida `view` de cada Node/proposta
  no purpose `wiki` antes de mostrar dados. A entrada `/studio/we-sativa` fica
  no purpose `account`; mantenha acesso e dados no serviço Wiki. Até 20 itens
  por tipo; cursor/paginação e formulários seguem pendentes. Kernel focal
  aprovado: 1 teste / 37 assertions; 200 depreciações Drupal/contrib e 2 do
  PHPUnit. A classe Kernel passou em MariaDB isolada: 15 testes / 612
  assertions; 286 depreciações Drupal/contrib e 16 do PHPUnit.

- Dev.38 R2: Kernel executa `validateUpload()` com `alt` vazio, verifica erro,
  zero Media ligada ao arquivo e File temporário (focal 1/27; classe 14/575).
  O caminho completo de submit do Form API continua pendente.
- Dev.37 R2: `WikiEntryImageHooks` sincroniza Media de destaque após insert,
  update e delete de Nodes; mantenha-a publicada enquanto outro Node publicado
  referir a mesma entidade. Teste MariaDB cobre duas referências, última
  retirada e exclusão do último Node. Dev.43 também nega download quando só
  restarem referências em revisões históricas.
- Dev.36 WES-C01-B1 parcial: teste Kernel usa `aculta_mail_confirmer` no realm
  `aculta_registration`, valida o hook real de confirmação do Portal, papel
  `email_confirmed` e `createAccess('wiki_entry')`. Renovar o cache do handler
  após confirmação representa a requisição seguinte. Focal 1/33; classe
  completa 11/492 em MariaDB isolada. Runtime permanece sem homologação.
- Dev.33 R2: ao reverter upload por erro/rebuild, invalidar caches de File/Media
  e limpar a referência temporária do FormState. Isso permite tentar o mesmo
  upload de novo sem duplicar Media nem tratar como permanente o arquivo cujo
  commit foi revertido.
- Dev.32: Kernel prova `replace`/`append` para categorias e relações da Wiki,
  com acesso e domínio revalidados na aplicação.
- Dev.31: Kernel prova as escolhas `append` e `replace` em referências, com
  deduplicação. Manter testes de integração correspondentes para categorias e
  relações ao ampliar essa área.
- Dev.30 R2: Kernel com bootstrap real garante rota única para Início, Busca,
  Prévia e listagem; a listagem exige acesso autenticado e mantém purpose `wiki`.
  Busca usa `user.node_grants:view` também em entradas vazias/malformadas.

- Dev.29: proposta editorial guarda campos tipados e a aceitação aplica um
  subconjunto, com modo substituir/acrescentar em cada lista, após revalidar
  revisão-base, Entity/Field Access e transições oficiais. Preservar a gravação
  da proposta e do Node na mesma transação; nunca aplicar proposta obsoleta.

Herde as regras da raiz e do Portal. Para trabalho na Wiki, consulte `README.md`,
`docs/ROADMAP.md` e `docs/TEST-MATRIX.md`, somente nas seções pertinentes.

- O preflight read-only revalidado em 2026-10-24 no host `toca`, URI
  `https://wiki420.aculta.toca.net.br`, branch
  `docs/aculta-forum-0.1.0-dev.1` confirmou `aculta_wiki` ativo e
  `aculta_index` desativado no Runtime de teste. `/` retornou 200; `/wiki`
  retornou 404 por ser o caminho interno da raiz Wiki, conforme
  `FrontPathPolicy`/`PurposeFrontPathProcessor`; `/wiki/verbetes`, `/wiki/busca`
  e `/wiki/busca/previa` retornaram 200. Drush confirmou bootstrap Drupal
  11.4.10, URI e banco `aculta_runtime`. Nenhuma configuração, cache ou dado foi
  alterado. Runtime não está homologado integralmente. Não instalar
  configuração nem executar outras operações persistentes sem autorização
  aplicável. O Portal não define mais as rotas da Wiki.
- Testes unitários: `vendor/bin/phpunit -c web/core/phpunit.xml.dist
  web/modules/custom/aculta_portal/modules/aculta_wiki/tests/src/Unit`.
- Nomes e caminhos de rota são contrato público: `aculta_portal.wiki_home`
  em `/wiki`, `aculta_portal.wiki_search` em `/wiki/busca` e
  `aculta_portal.wiki_search_preview` em `/wiki/busca/previa`. O prefixo
  `aculta_portal.` permanece mesmo com as rotas definidas neste submódulo.
- Domínio e purpose continuam no Portal (`DomainPurposeManager`,
  `ContentPurposeResolver`, `PurposeFrontPathProcessor`, `DomainRouteSubscriber`).
  A Wiki não redefine purpose nem host.
- Não importe classes de `aculta_statistics` neste módulo. A integração de
  métricas é feita por eventos e pelo `WikiProvider` do lado da estatística.
- Conteúdo sincronizado ou importado não gera métrica de publicação.
- Administração: `/painel-administrativo/aculta/wiki` (`aculta_wiki.settings`,
  permissão `administer aculta_wiki`) liga o aviso de revisão e cadastra as
  caixas editoriais. A configuração `aculta_wiki.settings` não tem arquivo em
  `config/sync` nem `config/install`; os padrões (desligado, sem destinatários)
  vivem no código.
- Aviso de revisão: `WikiReviewHooks` (`entity_update`) usa `getOriginal()` para
  avisar só na **entrada** em revisão. O contexto do e-mail tem título, estado e
  link de edição — **não inclua o nome de quem editou** sem atualizar o pacote
  LGPD. A fila do `aculta_mailer` e a URL pública são serviços opcionais
  (`@?…`); o módulo não depende do mailer em `info.yml`.
- Busca: o `wiki_entry` está no índice `aculta_conteudo` (`config/sync`). A busca
  própria da Wiki não mudou e as três rotas seguem sendo o contrato público;
  verbetes já publicados só entram no índice após `drush search-api:index` no
  ambiente.
- Preserve UUIDs e IDs de `config/sync`; a propriedade da configuração ainda é
  decisão pendente do responsável.
- Teste com o tema ACULTA420 e também com o Olivero: o que é do módulo deve
  funcionar sem o tema.
- Use os gates pertinentes ao diff. Não deduza o estado do Runtime a partir
  desta documentação.
- Imagem de destaque (dev.12): armazenar em `private://wiki/imagens`; a Media
  acompanha o estado publicado do verbete. Testar o ciclo transacional em Kernel
  isolado; não ativar ou importar configuração no Runtime como parte desse teste.
- Rotas e grants (dev.13): Kernel MariaDB carrega a Wiki real e verifica as
  rotas, a View e a busca com duas contas da mesma função e grants distintos.
  A fixture de grants fica em `tests/modules`; UID 1 não serve como prova de
  acesso. A ativação do Runtime segue pendente.
- Propostas editoriais (dev.14): `aculta_wiki_proposal` mantém proposta privada
  revisionável e revisão-base; `WikiProposalSubmission` revalida permissão,
  publicação, Entity Access e purpose `wiki`. Não há rota/formulário de envio ou
  operação editorial ativa; manter moderação e concorrência no próximo gate.
- Fila editorial (dev.15): `aculta_wiki.editorial_queue` consulta propostas
  pendentes e revisões atuais em `review`. A descoberta interna de VIDs de
  Content Moderation usa query sem access check porque Core nega acesso à
  entidade de estado; cada Node segue revalidado por Domain, estado e acessos
  de entidade, além de transições válidas. Kernel carrega workflow real e usa
  `email_confirmation_policy`; não criar bypasses para contas não confirmadas.
  Fila e serviço ainda não têm rota ou integração de UI com Studio.
- Envio de propostas (dev.16): `/wiki/verbetes/{node}/contribuir` usa Form API,
  permission `submit wiki proposals` e `_entity_access: node.view`. O serviço
  revalida a revisão-base durante o salvamento. Texto sugerido é texto simples;
  o formulário avisa que ele não altera o verbete automaticamente. Não marcar
  proposta como aceita sem definir e implementar a aplicação editorial.
- Acesso à contribuição (dev.17): submissão e abertura do formulário também
  exigem `EmailConfirmationPolicy::isConfirmed()`. Link contextual é renderizado
  somente para verbete publicado, no purpose Wiki, quando existe permissão de
  proposta e a pessoa não tem acesso direto de update. Preservar cache contexts
  de domínio, permissões e cacheability dos resultados de acesso.
- Decisão de proposta (dev.18): `WikiProposalDecision` aceita só `needs_changes`
  e `declined` até a semântica de aceite/aplicação no Node ser decidida. Salva
  revisão privada, exige nota, permissão e VID esperado; usa lock de banco para
  impedir duas decisões concorrentes sobre o mesmo estado. Form API está pronto
  para consumo, mas não tem rota nem link Studio.
- Integração GanjaLab (dev.19): usar a `WikiTab` existente. Revisões consomem
  `entryRevisions()` do serviço Wiki e edição segue formulário Node/Content
  Moderation. Propostas consomem `proposals()` e `WikiProposalDecisionForm`;
  gates de seção não devem conflar moderadores de propostas com os grants de
  Nodes. Não criar uma segunda fila, aba ou camada de autorização no Studio.
- Rotas e acesso (dev.20): manter os três nomes/caminhos públicos e testar a
  negação com permissão ausente; o CTA deve usar `createAccess('wiki_entry')`,
  respeitando acesso efetivo e confirmação compartilhada de e-mail.
- Paginação da fila (dev.21): limitar candidatos por requisição, mas avançar
  por cursor através de itens sem acesso ou obsoletos; validar cada Node no
  Domain Wiki e não revelar dados de revisões filtradas.
- Retorno da proposta (dev.22): status privado exige Entity Access real no
  parâmetro `{proposal}`; notificação assíncrona usa Mailer opcional e somente
  e-mail confirmado. Não incluir texto sugerido ou dados pessoais no aviso.
- Continuação da submissão (dev.23): após persistir, redirecionar para o ID
  retornado pelo serviço de submissão e a rota de status protegida; não adivinhar
  identificadores nem redirecionar para um registro diferente.
- Histórico privado (dev.24): listar revisões em páginas limitadas por cursor,
  revalidando `view revision` para cada uma. Exibir estado e retorno sem nomes de
  moderadores; cache varia por conta, permissões e cursor.
- Proteção de envio (dev.25): usar Flood Core, 10 propostas por conta em 3.600
  segundos; lançar 429 antes de salvar excedentes.
- Idempotência (dev.26): token aleatório por formulário, indexado por hash de
  conta/token no KeyValue expirable por 24h; lock e gravação transacional. O
  replay retorna a mesma proposta e não consome nova cota Flood.
- Concorrência (dev.27): Kernel segura o lock numa segunda conexão MariaDB e
  confirma que a segunda tentativa não cria duplicata. Não declarar cobertura
  multiprocesso PHP até executar um worker independente.
- Retirada (dev.28): apenas autoria autenticada com acesso de leitura pode
  retirar estados `submitted` ou `needs_changes`; revalidar VID sob lock e gravar
  nova revisão `withdrawn`, preservando versões e sem apagar a proposta.
