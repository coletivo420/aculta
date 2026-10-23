# Wiki — instruções locais

Herde as regras da raiz e do Portal. Para trabalho na Wiki, consulte `README.md`,
`docs/ROADMAP.md` e `docs/TEST-MATRIX.md`, somente nas seções pertinentes.

- O módulo existe em código desde 2026-10-22 — controlador, formulário de busca,
  rotas e testes unitários — e **não está habilitado**. Não habilitar, instalar
  configuração nem executar operações persistentes sem autorização do
  responsável. Enquanto estiver desabilitado, as rotas da Wiki não são
  registradas pelo Drupal; o Portal não as define mais.
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
