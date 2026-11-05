# Execução contínua ACULTA — histórico das frentes

> **Limite de registro:** no máximo 10 entradas. Registrar só ao encerrar uma fase ou o trabalho, não a cada microfase. Entradas mais antigas ficam em `docs/history/operations/`, sem alteração de texto.

## Fórum — aposentadoria do permalink individual (2026-11-01, dev.330)

- Aplicada D-65: `entity.comment.canonical` mantém geração de URLs de
  compatibilidade, mas substitui os requisitos por uma checagem que sempre
  lança 404. Comentários contextuais continuam sujeitos ao acesso da entidade
  hospedeira.
- Unit subscriber/access check: 2 testes / 4 assertions PASS. Rebuild de rotas
  e HTTP no Runtime não foram executados; sem escrita Runtime.

## Content Index da Revista — relação de vídeo (2026-11-01, Magazine dev.15)

- O provider Magazine v3 usa a API `entity_usage` para a revisão corrente e
  allowlist `media_embed` no campo `body`, limitando o alvo ao bundle
  `remote_video`. Nenhum corpo, URL oEmbed ou thumbnail é lido/projetado.
- Testes: Unit Magazine 7/43; Kernel de backfill/index Magazine 1/18; Kernel de
  revalidação de acesso Magazine 2/31; boot/DI real dos owners 2/89. PHPCS e PHP
  lint sem achados.
- Wiki/Fórum permanecem sem alteração dos formatos de vídeo; isso ainda requer
  decisão editorial. Sem escrita/rebuild no Runtime.

## Activity Content Index e validação de formulário (2026-11-01, dev.12)

- `aculta_activity` projeta `editorial_image` somente da referência explícita
  `field_media_image`; versão da projeção v3. Sem corpo, URL ou metadados
  privados da Media.
- Corrigida validação para ler os valores submetidos, inclusive campos limpos;
  `EntityTypeManager` e conta atual são injetados por DI.
- Kernel Activity: 6 testes / 123 assertions PASS. Maps Unit + provider Index
  de Events: 13 testes / 186 assertions PASS. PHPCS e PHP lint sem achados.
- Nenhuma escrita ou reconstrução do índice no Runtime.

## Desenvolvimento na branch dev — Maps/Events (2026-11-01)

- Branch `dev` partiu do commit integrado `889c2bae8`; worktree de desenvolvimento
  em `/home/piradopirata/worktrees/aculta-dev-development`.
- `MapController` agora preserva latitude/longitude numéricas zero. Kernel de
  Events e Unit do MapRenderer: **5 testes / 57 assertions, PASS**; PHP lint,
  PHPCS e `git diff --check` passaram. O teste inclui o marcador `0/0`; o Unit
  existente mantém cobertura de faixa inválida e precisão aproximada.
- Commit funcional: `b64010de6` (`fix(events): preserve zero map coordinates`).
- Nenhuma operação foi executada no Runtime. O próximo item Maps ainda pendente
  é a revisão de acesso/cache do catálogo conforme LOC-01/OC-M1.

## Desenvolvimento na branch dev — cache de acesso a Locais (2026-11-01)

- A partir de `3bae36db9`, `LocationCatalogue` passou a agregar cache tags e
  contexts de decisões de view permitidas e negadas. As render arrays da lista
  e do mapa propagam essa cacheability para evitar reutilização entre contas.
- Testes de catálogo/mapa: **9 testes / 179 assertions, PASS**, com 14
  depreciações upstream; PHP lint, PHPCS e `git diff --check` PASS.
- Commit funcional: `1120a4d4a` (`fix(events): bubble location access cache metadata`).
- O item LOC-01 continua parcial: revisão dos demais consumidores e paginação
  completa de consumidores segue pendente; a listagem passou a carregar em
  lotes na execução seguinte. Nenhuma operação no Runtime.

## Desenvolvimento na branch dev — paginação de Locais em lotes (2026-11-01)

- `LocationCatalogue` agora carrega até 100 entidades por consulta e mantém em
  memória somente a página acessível solicitada, preservando a contagem exata
  pós-ACL. Título e ID formam a ordenação estável; página negativa volta a 0.
- Testes de catálogo, mapa, gestão de Locais e MapRenderer: **20 testes / 369
  assertions, PASS**, com 22 depreciações upstream. PHP lint, PHPCS e
  `git diff --check` PASS.
- Commit funcional: `f0d77b0de` (`perf(events): batch location catalogue results`).
- LOC-01 segue parcial até a auditoria dos demais consumidores. Runtime não foi
  alterado.

### Gate de slugs — exceção OAuth técnica (2026-10-31)

`validate-public-slugs.php` encontrou `simple_oauth.userinfo` (`/oauth/userinfo`), endpoint protocolar OAuth 2.0 de módulo contrib. A rota foi adicionada às exceções técnicas nomeadas do gate, sem mudança de URL ou reativação de aliases humanos. Resultado: **PASS**, 425 rotas e zero violações contrib fora da baseline DT-P20.

### Unit Wiki/WE SATIVA — evidência adicional (2026-10-31)

A suíte Unit completa de `aculta_wiki` foi executada no checkout visual: **85 testes e 461 assertions, PASS**. Isso cobre contratos do Wiki e não substitui a homologação visual autenticada da rota canônica, que permanece dependente de fixture editorial autorizada.

### Fixture editorial WE SATIVA — Runtime (2026-10-31)

Com autorização explícita, foi criada no `aculta_runtime` a conta `studio_editor_test` (UID 258), com os papéis `content_editor` e `email_confirmed`. A política `EmailConfirmationPolicy::markConfirmed()` foi aplicada; Drush confirmou `createAccess('wiki_entry')=1` e `WikiContributorWorkspace::access()=1`. A credencial não é versionada: fica no arquivo protegido `/home/piradopirata/aculta-runtime-snapshots/20261031-editorial/credentials.txt` (modo 600). Snapshot pré-escrita: `/home/piradopirata/aculta-runtime-snapshots/20261031-editorial/runtime.sql.gz`, SHA-256 `7ef607f54e1ad56801a8c53414310eefb396c107bce4e0ce9ff0a5ce48f23c43`.

O baseline Firefox/X11 ainda recebe `Acesso negado` porque o docroot Runtime está no HEAD antigo `ae3227866`: a ULI emitida para a conta usa `/recuperar-acesso/.../entrar`, que retorna HTTP 404 nesse checkout. A fixture e os grants estão prontos; repetir o baseline após o docroot acompanhar o HEAD da branch, sem alterar ACL ou criar alias temporário.

### Alinhamento do docroot para reteste (2026-10-31)

O docroot foi alinhado por checkout destacado ao `origin/refactor/openculturas-codigo-visual` em `7c81acfd5`, após criar branch de backup do HEAD `ae3227866` e stash incluindo os arquivos não rastreados. O cache foi reconstruído. O primeiro ULI direto no host Wiki retornou 404 por política de domínio; esse diagnóstico histórico foi superado usando ULI no domínio Conta e navegação para o host Wiki, conforme o registro abaixo.

### Rotas canônicas e homologação editorial — 2026-10-31

Após decisão do responsável, as rotas das Suites foram alinhadas para `wiki:/wesativa` e `shop:/greenstash`; `/studio/*` deixou de ser usado para essas experiências. Os testes focais de identidade/navegação passaram (7 testes/246 assertions) e aliases temporários passaram. O Runtime foi atualizado para o `config/sync` após snapshot `c03ffc6ae08fa62aafa3daffe50ec297486040f3b2778c2efc9bda8cca1631a8`; o único diff era `private_message_digest_anchor` em `aculta_messenger.settings`, removido pelo import. `config:status` e `updatedb:status` ficaram limpos.

Com ULI emitida no domínio Conta (`conta.aculta.toca.net.br`) e navegação para `https://wiki420.aculta.toca.net.br/wesativa`, `studio_editor_test` carregou o consumer real WE SATIVA em 320/390/599/600/768/1024/1280/1440px: **8/8 capturas, 0 falhas de fidelidade**, conteúdo/context-header/context-tabs presentes, shell único e sem overflow. Evidência: `/home/piradopirata/aculta-suite-out/studio-wesativa-editorial-accountuli-20261031/`.

Matriz HTTP de migração: `wiki420:/wesativa` respondeu 403 anônimo (rota existente e protegida), `wiki420:/studio/we-sativa` respondeu 404; `loja.aculta.org:/greenstash` e o path antigo não puderam ser alcançados neste ambiente (HTTP 000 por resolução/conectividade do host), portanto Green Stash continua DEFERRED para teste de reachability externo.

Reteste pós-migração de Conta e Perfil com `teste.comum`: Firefox ESR/X11 em 320/390/768/1440px carregou as duas superfícies privadas separadamente, com shell, Institution Bar e Domain Header presentes, conteúdo real e zero falhas de fidelidade. Nenhuma rota de Suite foi reutilizada para Conta ou Perfil; o resultado não cobre privacidade A/B nem upload/crop.

Matriz genérica autenticada de WE SATIVA com `studio_editor_test` passou **18/18 checks** (320–1440px, claro/escuro), HTTP 200, largura do documento igual ao viewport e sem overflow. Evidência: `/home/piradopirata/aculta-suite-out/wesativa-page-matrix-20261101/`. O runner genérico não habilitou axe nesta execução; WCAG completa permanece pendente.

O axe autenticado encontrou e corrigiu contraste insuficiente no modal de aceite em dark mode (texto claro sobre fundo claro, 1,06–1,14:1). `portal-account-terms-modal.css` agora aplica superfície/texto semântico ao diálogo, título e labels. Reteste: **18/18 viewport checks, 0 violações axe**, com 1 resultado `incomplete` não classificável como violação. Evidência: `/home/piradopirata/aculta-suite-out/wesativa-axe-fixed-20261101/`. Commit da correção: `84ca752ba`.

O axe de Conta/Perfil encontrou ausência de H1 e ordem inválida de headings na entrada de Conta. `AccountExperienceProvider` agora renderiza H1 `Minha Conta` e H2 `Experiências` antes das listas. Reteste autenticado: **36/36 viewport checks, 0 violações axe** em Conta e Perfil, claro/escuro. Evidência: `/home/piradopirata/aculta-suite-out/account-profile-axe-fixed2-20261101/`. Commits: `ea2b3df55` e `4baf12561`.

O axe de Marley encontrou `scrollable-region-focusable` na tabela responsiva. O comportamento global `studio-table-a11y.js` agora torna `.studio__listing-wrap > .table-responsive` focável e fornece rótulo acessível. Reteste autenticado: **18/18 viewport checks, 0 violações axe**, claro/escuro. Evidência: `/home/piradopirata/aculta-suite-out/marley-axe-fixed2-20261101/`. Commit: `6685ebc3b`.

Reteste conjunto pós-correção de Marley e Ganjalab: **36/36 viewport checks, 0 violações axe**, claro/escuro, HTTP 200 e sem overflow. Evidência: `/home/piradopirata/aculta-suite-out/marley-ganjalab-axe-fixed2-20261101/`.

Revista pública no host correto `revista.aculta.toca.net.br` passou **18/18 viewport checks e 0 violações axe**, claro/escuro, HTTP 200 e sem overflow. Evidência: `/home/piradopirata/aculta-suite-out/revista-axe-live-20261101/`. O host alternativo `revista420.aculta.toca.net.br` continua indisponível e não é usado como canonical.

### WE SATIVA canônico — evidência adicional (2026-10-31)

O baseline Firefox ESR/X11 autenticado de `https://wiki420.aculta.toca.net.br/studio/we-sativa` foi executado em 320/390/599/600/768/1024/1280/1440px. As 8 capturas retornaram `Acesso negado`; Institution Bar, Domain Header e ausência de overflow foram observados, mas nenhum consumer Wiki foi servido. Evidência: `/home/piradopirata/aculta-suite-out/studio-we-sativa-baseline-recheck-8d4dcc07d-20261031/`. O resultado é DEFERRED por autorização/instalação do Runtime e mantém OCS-F7/OCS-F11 pendentes; não usar `/wesativa` como substituto da rota canônica.

## PR #299 — WE SATIVA: formulário de criação no purpose Wiki (2026-10-31)

- O CTA de autoria apontava para `node.add` (`/node/add/wiki_entry`), uma rota
  do purpose principal que respondia em branco no host Wiki. A mudança
  `c7fc57dba` adiciona `/studio/we-sativa/verbetes/novo`, protegida por
  `createAccess('wiki_entry')`; o controller renderiza o formulário Node Form
  API existente, sem salvar conteúdo. O workspace não envia mais para outra
  finalidade.
- Focais MariaDB `aculta_simpletest`: 2 testes/200 asserções PASS (rota Wiki,
  autoria permitida/leitor negado, formulário nativo e contagem de Nodes
  inalterada); PHP lint, PHPCS Drupal, YAML/discovery estática e
  `validate-no-temporary-route-aliases.php` PASS. Kernel em PHP 8.4.27 emitiu
  365 deprecações Core/contrib/PHPUnit.
- Checkout de desenvolvimento em `toca`, `/home/piradopirata/projetos/aculta`,
  destacado em `c7fc57dba`, Drupal 11.4.10, URI `http://default`, DB
  `aculta_runtime`, 611 tabelas. `updatedb:status` sem pendências;
  `config:status` sem diferenças; `drush cr` PASS; discovery confirma rota e
  `_aculta_domain_purpose: wiki`.
- Antes de cada uma das duas tentativas ULI foram feitos snapshots integrais,
  modo 0600: `runtime-snapshots/aculta_runtime-before-we-sativa-create-form-c7fc57dba-20261031.sql`
  (51.604.818 bytes; SHA-256
  `d626a71f5943c6556c0db22ee9e83747a8d19421354b4d77e87fef50f45ced74`) e
  `runtime-snapshots/aculta_runtime-before-we-sativa-create-form-account-uli-c7fc57dba-20261031.sql`
  (53.693.907 bytes; SHA-256
  `ab749edaca6009afa9426f061210869a9d04a2fa97059d1b7edebeb065a88e87`). A
  primeira tentativa emitiu ULI pelo host Wiki e ficou anônima; a segunda,
  emitida pelo host Conta, autenticou corretamente. Ambas foram GET-only e não
  submeteram nem salvaram conteúdo.
- Firefox ESR visível/X11 validou o fluxo no host Wiki em 1440, 1280, 1024,
  768, 640, 390, 360 e 320 px: 36/36 verificações PASS, sem overflow; botão de
  criação permaneceu visível, formulário `node-wiki-entry-form` e retorno para
  o workspace foram confirmados. Capturas/resumo privados em
  `/home/piradopirata/aculta-suite-out/we-sativa-create-form-account-uli-c7fc57dba-20261031/`.
- O script WE SATIVA agora usa o host Conta para emitir a ULI por padrão e
  salva `create-form.png` em `$SUITE_OUT`, permitindo inspecionar o formulário.
  A captura da sessão UID 257 mostrou que essa conta estava sem confirmação de
  e-mail; por isso os campos editoriais estavam corretamente bloqueados apesar
  do shell/form retornar. O runner passou a exigir título/corpo. UID 5 foi
  verificado ativo, autorizado e com e-mail confirmado; Firefox ESR/X11 passou
  61/61 em oito larguras (1440/1280/1024/768/640/390/360/320 px), abrindo
  também o formulário em cada largura e confirmando campos editoriais, retorno
  ao início e ausência de overflow. Capturas `create-form.png` (320px) e
  `create-form-wide.png` (1440px) inspecionadas; nada foi submetido. Snapshot
  pré-ULI modo 0600:
  `runtime-snapshots/aculta_runtime-before-we-sativa-form-matrix-b2d79189a-20261031.sql`
  (47.614.244 bytes; SHA-256
  `26958edd662bb6d071edf6f072f948ce0b342903d4936c695849f0d7ad556a4a`). Evidência
  em `/home/piradopirata/aculta-suite-out/we-sativa-form-matrix-b2d79189a-20261031/`.
  WCAG 2.2 AA completa, leitor de tela e submissão/ACL em matriz de contas
  continuam pendentes.

## PR #299 — OCS-F3/F4: adapter SDC da entrada da Conta (2026-10-31)

- Preflight no servidor `toca`: `/home/piradopirata/projetos/aculta`, HEAD destacado `6c71c5a13`, Drupal 11.4.10, URI `http://default`, banco `aculta_runtime`, 611 tabelas; `updatedb:status` sem updates e `config:status` sem diferenças. A conta sintética `aculta_visual_admin` (UID 257) estava ativa e tinha `access aculta portal`.
- Antes de cada sessão ULI foram salvos snapshots integrais modo 0600: `runtime-snapshots/aculta_runtime-before-studio-hub-card-retest-20261031.sql` (55.599.739 bytes; SHA-256 `2a5283018b21e24cc545b1becbb09951a52c4eae6dc60cbb55705f6a522e7b4f`) e `runtime-snapshots/aculta_runtime-before-studio-account-hub-card-retest-20261031.sql` (57.157.968 bytes; SHA-256 `82b13a97f786dc5882b55f269323a53761c8bdc09fc8f8190ff4f4c66f581e4d`). As duas sessões fizeram GET apenas; nenhum formulário ou conteúdo foi alterado.
- Firefox ESR/X11 percorreu a raiz Account em 320/360/390/599/600/768/1024/1280/1440px. Tanto `/` quanto o caminho de rota `/conta` normalizado para `/` exibiram cinco links de experiência, Institution Bar e Domain Header únicos e zero overflow. O teste `OCS_EXPECT_STUDIO_ROOT` encontrou zero SDCs `studio_fidelity`; os cinco links mediram 19px e o gate do Hub falhou 9/9.
- Causa no source: `AccountExperienceProvider` coloca `data-portal-ui=account-experiences` em `#attributes`; o preprocess de `item_list` do Core entrega isso em `attributes`, enquanto o adapter só lia `element`/`wrapper_attributes`. `PortalUiHooks::preprocessStudioHub()` agora lê também `attributes`; o Unit foi alinhado ao contrato real. PHP lint e PHPCS Drupal/DrupalPractice passaram; `PortalUiHooksTest`: 27 testes/149 assertions; SDC: 55 componentes/27 includes/759 checks; `git diff --check` PASS.
- A correção ainda precisa ser publicada no HEAD do Runtime, reconstruir cache e passar a mesma captura sem override. Até essa evidência, OCS-F3/F4 seguem parciais; não há homologação global THEME-MF3/OCS-F11.

## PR #299 — harness de navegador compartilhado e reteste transversal (2026-10-31)

- Documentação e comandos de `tests/browser/` foram explicitamente definidos como infraestrutura para todo o ACULTA: todos os módulos, produtos, domínios/purposes e consumers. As instruções agora exigem reutilizar smoke/matriz genéricos com URLs da área e só criar runner específico para uma lacuna funcional, ACL ou fixture; runners especializados não substituem a camada comum.
- No HEAD publicado `3648c50db`, Firefox ESR headless passou 180/180 combinações anônimas nas dez raízes públicas atuais × nove larguras × claro/escuro. Todas responderam HTTP 200, não houve overflow nem violações axe; quatro resultados `incomplete` (H1 Conta e select Growroom em ambos os modos) seguem para revisão manual. Chrome headless passou 36/36 checks focais de Cursos/Eventos; Firefox ESR/X11 passou 16/16. A faixa de Cursos passou 17/17 com JavaScript e 17/17 sem JavaScript, inclusive zoom real de 400%, foco e alcance de todos os cards.
- Para remover dependência do `node_modules` do tema, `page-matrix.py` agora aceita `AXE_SCRIPT` para qualquer instalação local compatível de axe-core. Compilação Python passou; validação real na capa da Revista com Firefox ESR headless, axe, 320/1280px e claro/escuro: 4/4, HTTP 200, sem overflow, zero violações e zero `incomplete`. Evidência: `/home/piradopirata/aculta-suite-out/project-generic-runner-revista-20261031/`.
- Evidências em `/home/piradopirata/aculta-suite-out/project-roots-firefox-matrix-3648c50db/`, `course-event-chrome-matrix-3648c50db/`, `course-event-x11-3648c50db/`, `courses-rail-served-3648c50db/` e `courses-rail-nojs-served-3648c50db/`. Detalhes e limitações em `tests/browser/README.md`, `docs/operations/NAVEGADOR-TESTES.md` e `web/themes/custom/aculta420/docs/mobile-first-audit.md`.
- Verificação atual do servidor `toca`: checkout `/home/piradopirata/projetos/aculta`, HEAD destacado `3648c50db`, Drupal 11.4.10, URI `http://default`, DB `aculta_runtime`; `updatedb:status` sem atualizações e `config:status` sem diferenças. Nenhuma escrita no Runtime nesta microfase documental.
- Limite: a matriz é anônima e cobre as raízes, não todas as telas internas nem ACL, leitores de tela ou WCAG 2.2 AA completa. Não aprova THEME-MF3/OCS-F11.

### Histórico de alinhamento Runtime no HEAD 3648c50db

- O Runtime `aculta_runtime` (611 tabelas) foi validado no servidor `toca` após as mudanças funcionais publicadas. Antes da escrita de tradução houve snapshot íntegro em `/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-course-label-axe-fix-20261031.sql`, SHA-256 `0c74994afe87a19f93e08eea58f002ccfc360ae120f4121c97ba90948d2fdc6e`, modo 0600. Foi importada uma tradução customizada de um rótulo de Cursos; Drush informou 0 adicionadas, 1 atualizada e 0 removidas.
- A importação de tradução também atualizou cinco configurações View ativas. Antes da reconciliação houve segundo snapshot: `/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-course-label-view-sync-reconcile-20261031.sql`, SHA-256 `08d7dc95f59c5627cff12565c7c7f98dadb239bb150a2adb4f3b2c31881f3c9e`, modo 0600. A fonte de import foi isolada aos cinco YAML de `config/sync` (`commerce_order_item_table`, `commerce_order_item_table_admin`, `courses_admin`, `locked_content`, `moderated_content`); após import parcial e rebuild, `config:status` ficou sem diferenças. Nenhum conteúdo foi alterado.
- Incidente operacional: `drush config:export --diff` nesta versão do Drush escreveu 352 arquivos sob `config/sync` em vez de apenas exibir um diff. O estado do Git estava limpo antes da execução; verificou-se que os 352 caminhos afetados eram exclusivamente `config/sync`, e somente esses arquivos gerados foram restaurados para o HEAD publicado. O export não foi usado como fonte para a reconciliação. O estado final do checkout permaneceu limpo e o Runtime está novamente alinhado ao Sync. Evitar esse comando como operação somente leitura nesta instalação.

## PR #299 — recuperação de rota 500 durante migração de Suites — 2026-10-30

- Servidor `toca`; checkout servido `/home/piradopirata/projetos/aculta`, branch `refactor/opencultures-codigo-visual`, HEAD `8ec034700`; Drush confirmou Drupal 11.4.10, URI `http://default`, root `web` e Runtime `aculta_runtime`.
- A inspeção do watchdog encontrou erro em 14:40:17: `Route "aculta_events.organizer_dashboard" does not exist.` A rota já estava definida no código da migração, mas ainda não estava na coleção/cache ativo. Reconstruído o cache Drupal com `drush cache:rebuild` (sem `updatedb`, `config:import` ou escrita de conteúdo/configuração).
- Pós-rebuild, a montagem do controller da entrada da Conta em contexto autenticado de leitura retornou render array; HTTP: MAIN `/` 200, Conta `/entrar` 200, Conta `/studio` 404, Marley anônimo 403 e `/culturas` anônimo 403 (rotas presentes e acesso negado, sem 500). Não foi possível inspecionar o log do PHP-FPM por falta de permissão de leitura; nenhuma falha 500 foi reproduzida nos endpoints verificados.
- Host novo da Wiki não validado nesta microfase; o Domain ativo permanece `wiki420`. Nenhum update de banco, importação ou mudança de dados Runtime foi executado.
- A captura autenticada posterior reproduziu HTTP 500 em `/culturas`: `EventOrganizerDashboardController` chamou `createAccess()` no modo padrão e tentou executar `isAllowed()` no booleano retornado. Drupal 11 exige `createAccess($bundle, $account, $context, TRUE)` para retornar o `AccessResultInterface` cacheável. Corrigido em `95314a5a7`; `drush cr` PASS; `/culturas` anônimo responde 403 (sem 500) e Firefox ESR autenticado confirmou página 200 em 1440/390 px.
- Matriz de purpose pelo Firefox/HTTP: `DomainPurposeManager::environmentPathUrl()` resolveu os cinco hosts de teste; os cinco caminhos retornaram 403 anônimo no purpose próprio e os 20 GET/POST cross-purpose retornaram 404. `/studio`, `/studio/marley` e `/studio/conta` retornaram 404 em GET/POST nos cinco hosts. Baseline Firefox autenticado: 20 capturas (home OpenCulturas + nove páginas × 1440/390), 0 documentos vazios, 0 overflow; duas capturas Green Stash são estado de acesso negado para a conta de teste sem grant e não contam como homologação de acesso.
- Unit focado: 89 testes / 653 assertions PASS. Alias validator, Conta Root, Domain Front Paths e Project Status Index PASS. Kernel `EventDomainBoundaryKernelTest` não executado: `SIMPLETEST_DB` não está configurada nesta sessão. A suíte Unit ampla (457 testes / 1.949 assertions) ainda tem uma falha existente em `GuestCheckoutRegistrationConfigTest`: o checkout do tipo `curso` está configurado como `curso`, teste espera `default`; arquivo não foi alterado nesta migração.
- Novos commits publicados por fast-forward em `origin/refactor/opencultures-codigo-visual`: `faefae9ee` (rotas/purposes) e `95314a5a7` (correção do HTTP 500). Nenhum `updatedb`, `config:import`, escrita de conteúdo ou dado Runtime foi executado.
