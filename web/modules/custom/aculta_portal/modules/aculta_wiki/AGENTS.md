# Wiki — instruções locais

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
  retirada e exclusão do último Node.
  Core ainda permite acesso ao arquivo por revisão histórica publicada mesmo
  com Media privada; a política desse caso segue pendente.
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

- O preflight read-only de 2026-10-23 no host `toca`, URI
  `https://wiki420.aculta.toca.net.br`, branch
  `docs/aculta-forum-0.1.0-dev.1` confirmou `aculta_wiki` ativo e
  `aculta_index` desativado no Runtime de teste. `/` retornou 200; `/wiki`
  retornou 404 por ser o caminho interno da raiz Wiki, conforme
  `FrontPathPolicy`/`PurposeFrontPathProcessor`; `/wiki/verbetes`, `/wiki/busca`
  e `/wiki/busca/previa` retornaram 200. Não houve alteração de configuração,
  cache ou banco. Runtime não está homologado integralmente. Não instalar
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
