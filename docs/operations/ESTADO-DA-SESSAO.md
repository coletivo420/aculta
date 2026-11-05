> **Limite de registro:** no máximo 10 entradas. Registrar só ao encerrar uma fase ou o trabalho, não a cada microfase. Entradas mais antigas ficam em `docs/history/operations/`, sem alteração de texto.

# Economia de tokens — oito itens aprovados — 2026-11-05

- **Objetivo:** reduzir a leitura e a saída de comandos pelos agentes, conforme aprovação do responsável (itens 1 a 8 de `docs/operations/AUDITORIA-TOKENS-TESTES-2026-11-05.md`, seção 9).
- **Concluído:** itens 1, 2, 3 (Wiki e Studio), 4, 5, 6, 7 e 8. `TESTES-NAVEGADOR.md` caiu de 57 KB para 5,5 KB; as rodadas F a V estão em `docs/history/portal/TESTES-NAVEGADOR-RODADAS.md`. AGENTS da Wiki caiu de 34 KB para 6,7 KB; do Studio, de 13 KB para 7,5 KB. Criado `docs/operations/GATES.md`. Removidos `scripts/validate-institution.php` e `scripts/validate-podplant420-assets.php`.
- **Commits em `origin/dev`:** `b1589e8e` (arquivo e compactação de testes de navegador), `8dbb5066` (AGENTS de módulos), `f9aa6230` (fonte única de regras, gates, não ler, saída obrigatória e remoção de validadores).
- **Verificações:** links relativos dos arquivos alterados conferidos por script; restaram quebras anteriores a esta entrega (`docs/portal/README.md` e `ACULTA-MODULES-CLOSEOUT.md`, para módulos de PDF Viewer que já não estão no caminho indicado). Gates PHP **DEFERRED**: o ambiente não tem `vendor/` nem Drush.
- **Pendências:** `handoff/podplant420` não foi arquivado, por causa de referências vivas em `config/sync/views.view.revista_sessoes.yml` e em scripts de verificação. `aculta_portal/docs/AGENT-POLICIES.md` não foi deduplicado, porque exige conferência item a item com `DRUPAL-11-STANDARDS.md`. Cerca de 30 documentos ativos ainda trazem caminhos de máquina; a regra está em `AGENTS.md`, seção Escopo e segurança, e a correção fica para uma fase própria. Validadores com uma única citação em auditoria ou dívida foram mantidos e precisam de decisão do responsável.
- **Próximo passo:** no servidor de desenvolvimento, rodar `php scripts/validate-aculta-portal-drupal11.php` e os gates do escopo de cada área alterada, colando a saída; depois decidir o arquivamento de `handoff/podplant420`.

# Continuação — ACULTA420 V2, Meu Perfil H2 e HTMX F1 parcial — 2026-11-05

- **Objetivo:** executar o plano V2 em `web/themes/custom/aculta420_v2/docs/roadmap.md`; respeitar decisões pendentes e publicar cada entrega em `dev`.
- **Concluído nesta entrega:** Meu Perfil privado usa `/meu-perfil` e `/meu-perfil/*` no purpose `account`. Mantidos os route IDs, `StudioProfileAccess`, Form API e owners do `aculta_user`; todas as rotas declaram `no_cache`, o hook aplica `noindex, nofollow`, e paths privados antigos não têm alias/redirect. Rota pública segue sem decisão.
- **Arquivos:** rotas e testes em `web/modules/custom/aculta_portal/modules/aculta_studio/`; documentação do owner em `web/modules/custom/aculta_portal/modules/aculta_studio/{AGENTS.md,README.md,CHANGELOG.md}` e `web/modules/custom/aculta_portal/modules/aculta_user/README.md`; contrato em `docs/architecture/ACULTA-STUDIO-CONTA-PERFIL-SEPARADOS.md`; plano/visual em `web/themes/custom/aculta420_v2/docs/{roadmap.md,studio-estrutura.md,studio-visual-spec.md,layouts-plan.md}`; índice `docs/operations/status/aculta_studio.yml`/`README.md`.
- **Commit enviado:** `5043cc8f5b58c180401568b8d43609f92986ae9b` (`Move edição privada de perfil para /meu-perfil`) em `origin/dev`.
- **Validações:** Studio Unit 69 testes/738 assertions PASS; PHPCS Drupal/DrupalPractice PASS; YAML (40 rotas), PHP lint focal e `git diff --check` PASS. `drush cr` PASS; route provider Runtime confirmou cinco paths `/meu-perfil*`, purpose `account`, `no_cache: true`, ausência dos paths privados antigos; anônimo não autorizado. Antes/depois: `updatedb:status` sem updates, `config:status` sem diferenças. Nenhum formulário/conteúdo foi alterado.
- **Gates bloqueados por estado preexistente:** `validate-module-theme-boundary.php` falha em seis classes de `aculta_lms`/`aculta_lms_resources`; `validate-aculta-portal-drupal11.php` e `validate-project-status-index.php` falham por versões divergentes de `aculta_calendar`, `aculta_donation`, `aculta_file_uploader`, `aculta_linkweed`, `aculta_mailer`, `aculta_messenger`, `aculta_paths`, `aculta_ugc` e `aculta_user`. Nenhuma foi modificada nesta entrega.
- **Pendências H2:** HTTP autenticado, revisão visual e submissão dos formulários não executadas; nenhum dado de usuário foi alterado. Não decidir a rota pública (`/u/<apelido>` vs `/perfil/<apelido>`), nem telefone/data de nascimento.
- **HTMX entregue depois de H2:** regra documentada em `AGENTS.md`, `CLAUDE.md`, `web/themes/custom/aculta420_v2/AGENTS.md`, `web/themes/custom/aculta420_v2/docs/architecture.md` e `web/modules/custom/aculta_portal/docs/AGENT-POLICIES.md`: AJAX Drupal é legado; HTMX é o padrão do tema. Portal implementa resposta HTMX do login popup; V2 usa HTMX no login do shell e nas gavetas de mensagens/avisos, com foco/título/anúncio e contadores. Commits `79a271d778` e `e6259f8767` foram enviados a `origin/dev`.
- **Validações HTMX:** PHP lint e PHPCS focal, SDC schemas (55/764), Design Foundations (2468), V2 foundations, shell contract (13), browser validators (21) e render isolado (35 SDCs) PASS; fixture Firefox sintética cobriu drawer/login, sem provar autenticação real. `updatedb:status` sem atualizações e `config:status` limpo após o push.
- **Falta:** busca do shell continua em Fetch; cadastro popup não foi migrado; login/formulário reais autenticados não foram verificados; matriz visual de 320, 390, 768, 959, 960 e 1440 px em claro/escuro e zoom 200% não foi capturada. F1 permanece parcial. Não declarar F1 concluída; retomar pelo bloco HTMX deste `roadmap.md` e ADR-050. Continuar itens independentes; `price`/Loja segue aguardando autorização.
- **Retomar lendo:** este registro; `web/themes/custom/aculta420_v2/docs/roadmap.md`; `docs/decisions/ADR-050-migracao-ajax-para-htmx.md`; `docs/htmx-confirmacao.md`; `web/modules/custom/aculta_portal/modules/aculta_studio/aculta_studio.routing.yml`.

# Continuação — Institution Bar, Gin e bloco Core Navigation quebrado — 2026-11-04

- **Objetivo:** corrigir a sobreposição da Institution Bar com o Gin na sessão administrativa, remover o aviso de bloco quebrado e seguir a migração visual V1→V2.
- **Causa do aviso:** `navigation.block_layout` mantinha `navigation_shortcuts` com `shortcut` desativado. O plugin legado não é descoberto e o Core renderizava `block_broken`. A configuração exportada foi corrigida e importada; Firefox autenticado não mostra mais o aviso.
- **Implementado:** barra fixa mede `#admin-toolbar`, ocupa a coluna geral depois da sidebar e atualiza durante resize/transição sem deslocar Gin. Coluna estreita empilha marca/utilitários. Margem fluida; gap dos ícones reduzido a zero; busca, carrinho, mensagens e avisos em 32 px (avatar). Painel de conta restrito à coluna geral.
- **Validação:** Firefox ESR visível, UID de teste 257, 11 larguras 320–1440, Gin aberto/fechado, claro/escuro e menu de conta aberto: 44/44 verificações de geometria PASS. SDC 25/25; Foundations 51 pares PASS; Shell contract 13 checks PASS. Config import e `drush cr` PASS; `updatedb:status` sem pendências; `config:status` limpo. Capturas: `$SUITE_OUT/aculta420-v2-admin-sidebar-20261104/accepted-final/`.
- **Limite observado:** com Gin recolhido, `scrollWidth` excede a viewport (385 px em 320; 420 px em 390). Diagnóstico aponta `.top-bar__actions` e controles do toolbar administrativo, fora da Institution Bar. A rolagem horizontal geral ficou DEFERRED para correção própria do toolbar. Zoom e teclado DEFERRED.
- **Causa e sync de configuração:** removida a entrada obsoleta `navigation_shortcuts`; import executado após snapshot externo de 66.256.506 bytes, SHA-256 `ff5eea49d393f4901f706bd57a53d8e07481edc212c097f912bc5724f95a1ea5`.
- **Git:** commit `26f0af0657` (`fix(aculta420_v2): reserva coluna do Gin e remove bloco quebrado`) publicado por fast-forward em `origin/dev`. Arquivos de CSS/JS, ícones e config do mesmo commit sincronizados no Runtime; HEAD local não foi trocado por estar 35 commits atrás e conter alterações paralelas.
- **Próximo:** continuar inventário da família Eventos conforme `web/themes/custom/aculta420_v2/docs/roadmap.md`, após concluir esta correção administrativa.

---

# Continuação — ACULTA420 V2, auditoria de templates G3 — 2026-11-05

- Removido `web/themes/custom/aculta420_v2/templates/content/node--teaser.html.twig`: o override aplicava cartão cultural genericamente e podia omitir campos do entity view display (por exemplo, arquivo em Documento). Adapters específicos permanecem; bundles sem adapter usam template/display Core.
- Documentação atualizada em `web/themes/custom/aculta420_v2/docs/architecture.md`, `docs/roadmap.md`, `docs/gates.md` e `CHANGELOG.md`. Loja/catálogo continuam aguardando autorização.
- Verificação: gate `php vendor/drush/drush/drush.php php:script scripts/validate-aculta420-v2-sdc.php` PASS, 35 SDCs; confirmação da remoção e `git diff --check` PASS. Captura visual/teclado DEFERRED nesta execução por ausência de sessão gráfica.
- Runtime `toca`, Drupal 11.5.0-beta1, banco `aculta_runtime`, V2 ativa. Antes e após o push: `updatedb:status` sem pendências e `config:status` sem diferenças; `drush cr` PASS para invalidar o registry Twig. Nenhuma alteração persistente de conteúdo/configuração/banco. O checkout Runtime e `origin/dev` estão no commit `d6a59e7af0655803dee517a2e89e488b10902dc2`.
- Próximo: continuar G3 com inventário de adapters e templates por tipo/view mode; manter perfil público sem rota nova e demais decisões registradas aguardando o responsável.

## Continuação — HTMX Core F0, confirmação e fixture — 2026-11-05

- Inspecionada a API do Core 11.5: `Drupal\Core\Htmx\Htmx` sem `@internal`, library `core/drupal.htmx`, `onlyMainContent()` gera `data-hx-drupal-only-main-content`; `FormBuilder` atualiza `form_build_id` via `swapOob` em requisição HTMX e mantém o fluxo normal de token CSRF para autenticados.
- Provas oficiais do Core em Chromium headless 156.0.8078.79/ChromeDriver 156.0.8078.79: FunctionalJavascript `HtmxDynamicFormTest` 1 teste/19 assertions PASS, com 55 depreciações Core/contrib; Kernel `HtmxRequestTest` 5 testes/60 assertions PASS. Banco `aculta_simpletest`; outputs HTML movidos para `$SUITE_OUT/aculta-htmx-f0-20261105/core-html/`. O FunctionalJavascript precisou de `SIMPLETEST_BASE_URL`; a primeira tentativa sem a variável falhou antes do teste, e a segunda passou.
- F0 foi concluída com a fixture `web/modules/custom/aculta_portal/tests/modules/aculta_htmx_f0_test/` e `tests/src/FunctionalJavascript/HtmxF0DialogTest.php`: Chromium headless (1 teste/7 assertions, 55 depreciações) e Firefox headless (1/7, 54 depreciações) abriram o diálogo, carregaram/enviaram o formulário e rejeitaram token inválido. HTMLs foram movidos para `$SUITE_OUT/aculta-htmx-f0-20261105/{dialog-proof,dialog-proof-firefox}/`. O módulo só é descoberto durante testes e `config:get core.extension module.aculta_htmx_f0_test` retornou `null`; não foi habilitado nem mudou código de produção. I2/I3/I4 ficam liberados conforme dependências do ADR-050.
- Nota, ADR e roadmap atualizados em `docs/htmx-confirmacao.md`, `docs/decisions/ADR-050-migracao-ajax-para-htmx.md` e `web/themes/custom/aculta420_v2/docs/roadmap.md`. Runtime `aculta_runtime`: `updatedb:status` sem pendências e `config:status` sem diferenças; os testes usaram o banco dedicado. Nenhum módulo foi instalado no Runtime.
- Próximo: iniciar F1 do tema e do login como entregas separadas, em commits diretos a `dev`; F2 depende de F1a e F3 pode avançar pela sequência independente dos módulos.

# Continuação — migração visual do Fórum V1 para SDC V2 — 2026-11-04

- **Objetivo:** prosseguir na migração visual completa V1→V2 por famílias de página; esta fatia cobre tópicos, categorias, Views públicas e apresentação compartilhada de discussão.
- **Feito:** SDCs `forum-topic`, `forum-category` e `discussion-comment`; adapters para node, Views e comentário; estilos de listagem, busca, estados vazios e discussão. A V2 mantém render arrays de conteúdo/autoria/contagem.
- **Arquivos na worktree de migração:** `web/themes/custom/aculta420_v2/components/{forum-topic,forum-category,discussion-comment}/`, `src/styles/forum.css`, `templates/content/node--aculta-forum-topic.html.twig`, `templates/views/views-view-{fields,unformatted}--aculta-forum-*.html.twig`, `templates/comment--aculta-discussion-post.html.twig`, `scripts/validate-aculta420-v2-sdc.php`, catálogo, roadmap e changelog.
- **Validações:** Drush descobriu e renderizou 25/25 SDCs; foundations 51 pares de contraste PASS; PHP lint e sintaxe Python PASS; Firefox ESR visível no purpose `forum`, landing/categorias/tópico real, 9 larguras, claro/escuro, teclado e zoom 200/400: 76/76 PASS. Corrigido o bloco da landing, que antes imprimia campos genéricos e não usava o SDC. Capturas em `$SUITE_OUT/aculta420-v2-forum-landing-20261104/`.
- **Runtime:** `toca`, Drupal 11.5.0-beta1, banco `aculta_runtime`; `updatedb:status` sem pendências, `config:status` limpo; `drush cr` PASS. Nenhuma alteração de config/conteúdo. O comentário `aculta_discussion_post` não apareceu nas rotas públicas verificadas; o componente passou render isolado e o estado real continua DEFERRED.
- **Git:** commit `79138060ae` (`feat(aculta420_v2): migra apresentação do Fórum para SDC`) enviado por fast-forward para `origin/dev`. Os templates de Views e CSS relevantes foram sincronizados ao checkout Runtime compartilhado e exercitados; o HEAD completo do Runtime não foi trocado para preservar alterações locais paralelas de conta, LMS e shell.
- **Próximo:** inventariar a família de Eventos. Retomar lendo `web/themes/custom/aculta420_v2/docs/roadmap.md`, `docs/architecture/ACULTA420-MOBILE-FIRST-GUARDRAILS.md`, ADR-046 seção 3 e os templates/CSS de Eventos V1.

# Continuação — Phosphor Icons, Leaflet e cache/CDN da V2 — 2026-11-04

- **Objetivo:** completar a integração Phosphor Icons (Duotone) no `aculta420_v2` e usar os SVGs do SDC nos marcadores Leaflet locais do `aculta_maps`, com compatibilidade de cache/CDN sem reintroduzir Vite, Bootstrap ou jQuery.
- **Feito:** vendorizados Phosphor Icons Core 2.1.1 (MIT), 1.512 SVGs regular e 1.512 duotone; criado SDC `icon` com `source()` inline e schema estrito. `badge`, `button`, `card-cultural`, barra superior, busca, carrinho, mensagens, avisos, navegação e status usam agora o sistema Phosphor; auditoria confirmou zero SVG de interface inline fora do SDC.
- **Maps:** hook neutro fornece template inerte de ícone; o tema escolhe Phosphor. `aculta-map.js` monta `L.divIcon` a partir do template, sem transferir SVG em `drupalSettings`; usa `core/once` e mantém o agrupador Leaflet próprio.
- **Cache/CDN:** bibliotecas Drupal versionadas/agregáveis, URLs relativas e sem hostname CDN fixado. SVG é parte do HTML cacheado; metadados de cache continuam em render arrays. TTL, surrogate headers e purge são configuração do ambiente. Vite permanece removido por decisão anterior.
- **Arquivos principais:** `web/themes/custom/aculta420_v2/assets/icons/phosphor/`, `components/icon/`, `components/{badge,button,card-cultural}/`, `templates/aculta-maps-marker-icon-template.html.twig`, `src/styles/tokens.css`; `web/modules/custom/aculta_portal/modules/aculta_maps/{src/MapRenderer.php,src/Hook/MapsThemeHooks.php,js/aculta-map.js,css/aculta-map.css,templates/}`; `scripts/validate-aculta420-v2-sdc.php`.
- **Validações:** 15/15 SDCs (schema/asset/render) PASS; MapRenderer Unit 4 testes/26 assertions PASS; PHP lint, PHPCS, Node syntax, YAML e `git diff --check` PASS. Drush render confirmou SVG inline; `drush cr` PASS; Runtime `updatedb:status` sem pendências e `config:status` limpo. HTTP home e Eventos `/mapa` retornaram 200. PHPUnit exibiu aviso de `browser_output` não gravável, mas os testes Unit passaram.
- **Runtime:** `toca`, Drupal 11.5.0-beta1, banco `aculta_runtime`, tema V2 ativo. Nenhum import de configuração, update de banco ou alteração de conteúdo. Após o push, repetir `drush cr` e os dois status read-only para confirmar HEAD.
- **Pendências:** revisão visual/assistiva e validação de headers/purge quando uma CDN/reverse proxy for habilitada.
- **Preservar:** alterações paralelas em `composer.json`, `composer.lock`, `docs/operations/status/aculta_lms.yml`, módulo `aculta_lms`, `web/themes/custom/aculta420/` e `web/themes/custom/aculta_theme/`; não incluir nesta entrega.
- **Retomar lendo:** este handoff; `web/themes/custom/aculta420_v2/assets/README.md`; `web/themes/custom/aculta420_v2/docs/roadmap.md`; `web/modules/custom/aculta_portal/modules/aculta_maps/README.md`.

## Continuação — conta fixa e popup AJAX nativo V2 — 2026-11-04

- A barra fixa volta a renderizar o menu Drupal `account` dentro de sua região
  utilitária. O render array original é movido do header preservando acesso,
  lazy builder e cacheabilidade; o menu entrega “Minha conta” e “Sair” após
  autenticação e “Entrar” para visitantes.
- “Entrar” abre `<dialog>` nativo; V2 busca o formulário do Portal com Fetch e
  envia o POST no formato `drupal_ajax`. O cliente aplica somente os comandos
  Portal/Core previstos: atualizar build ID, substituir o formulário em erro
  ou redirecionar no sucesso, validando destino same-origin. Busca também usa
  Fetch e mantém o behavior Vanilla de prévia do Portal.
- A V2 remove o carregamento global de `core/drupal.dialog.ajax` para esta
  apresentação. A home ainda carrega jQuery porque uma View VVJB existente
  anexa `core/drupal.ajax`; não é dependência dos componentes da barra e foi
  preservada para não remover AJAX da View.
- Validação: 15/15 SDCs; PHPCS e PHP lint; Node syntax; `drush cr`; HTTP 200 na
  home, popup de login e popup de busca; os dois formulários e a prévia foram
  encontrados no HTML dos endpoints. `updatedb:status` e `config:status` sem
  pendências/diferenças. Nenhuma credencial foi enviada; autenticação de ponta
  a ponta permanece para validação manual.
- **Refinamento visual V1 (dev.14):** o modal voltou ao verde exato `#6b9a2c`,
  com texto escuro (5,45:1), Google com SVG V1, sombra e realce creme em hover/
  foco. Firefox ESR visível no Runtime confirmou formulário, Google e Escape
  em claro/escuro, sem overflow nos viewports efetivos 500 e 1430 CSS px. O
  Firefox/X11 não reduziu abaixo de 500 px; testes em 320/390, zoom 200%, foco
  real do Google e outros purposes ficaram DEFERRED. Capturas e resultados em
  `$SUITE_OUT`. Gates: fundações 55 pares PASS; schemas 55/764 PASS; render SDC
  19/19 PASS; `drush cr` PASS. Pré-check `updatedb:status`/`config:status` limpo.
  Commit `e546eba028` publicado; só os arquivos do tema desta entrega foram
  sincronizados ao Runtime, sem trocar HEAD nem tocar config/banco.
- Arquivos desta entrega: `web/themes/custom/aculta420_v2/src/Hook/PagePresentationHooks.php`,
  `templates/layout/page.html.twig`, `components/multidomain-bar/`, manifesto/
  bibliotecas/changelog/roadmap do tema e `scripts/validate-aculta420-v2-sdc.php`.
  Foram preservadas alterações paralelas em Composer, LMS, `aculta420` e
  `aculta_theme`.
- **Publicação:** commit `bebdc2cde5e8d25082b17f476f5c2a9eaea82115` enviado por
  fast-forward a `origin/dev`.
- **Runtime:** `toca`, Drupal 11.5.0-beta1, banco `aculta_runtime`, tema
  `aculta420_v2`. Pós-push, `drush cr` passou, `updatedb:status` não requer
  updates e `config:status` não encontrou diferenças. Runtime é o checkout
  compartilhado e está no mesmo HEAD. Nenhum import de configuração ou update
  de banco foi executado.
- **Retomar lendo:** seção acima, `components/multidomain-bar/`,
  `PagePresentationHooks.php` e `LoginPopupForm.php`.

## Retomada — objetivo persistente LMS → pagamentos → updatedb, 2026-11-04

- **Objetivo:** concluir lacunas funcionais do LMS; somente depois trabalhar pagamentos e, por último, resolver pendências `updatedb` individualmente. Escopo da sessão é código/funcionalidade, sem revisão estética do tema.
- **LMS Resources:** `aculta_lms_resources` local está em dev.33. Além do upload PDF AJAX e dos widgets leitura/galeria/vídeo já validados, o builder de vídeo corrige a rota Core `media/{id}/edit` para linkar a URL oEmbed apenas depois de verificar Media publicada e Entity Access sob `courses`; URLs inválidas, credenciais e esquemas diferentes de HTTP(S) não são expostos. Unit + Kernel focal: 9 testes / 89 assertions PASS.
- **Smoke funcional Runtime:** conteúdo de teste descartável foi autorizado pelo responsável. Um Group temporário autenticado referenciou Wiki, galeria, vídeo e Media `remote_video` existente ID 12; os widgets carregaram e salvaram. A estrutura exibiu Wiki em `wiki420`, galeria em Revista e vídeo no destino YouTube. Antes da correção, o canonical Media `/media/12/edit` retornava 404 sem sessão editorial. Firefox headless verificou Form API, submit, foco e ausência de overflow em 320/390/600/768/1280 px; sem capturas ou critérios estéticos. Group removido e fontes editoriais preservadas. O PDF privado também passou pelo AJAX upload, persistência Group→Media→File e lista; todas as fixtures foram removidas. Formulário de PDF sem overflow em 320/390/768/1280 px.
- **Bloqueio Runtime H5P:** `aculta_lms_h5p.framework` falhou antes da criação de fixtures com “The private H5P storage directory is unavailable” (watchdog 843707). Nenhuma conta, turma, tópico ou conteúdo H5P de teste foi criado nesse smoke. Não alterei `private/` nem contornei a configuração do storage. Kernel permanece como cobertura da integração de discussão.
- **Runtime:** `toca`, Drupal 11.5.0-beta1, PHP 8.5.12, DB `aculta_runtime`, tema `aculta420_v2`. Pós-smoke: `updatedb:status` sem updates, `config:status` limpo e zero conteúdo de teste residual.
- **Git:** commits `972a1b4a33` (`corrige recursos funcionais dos materiais LMS`) e `c5e3da6f2e` (`atualiza status do LMS Resources`) enviados por fast-forward a `origin/dev`; o primeiro precisou rebasear após o remoto avançar. O Runtime usa o checkout principal ainda no HEAD `b05a3b131b`, 17 commits atrás, com alterações locais paralelas; os arquivos de Resources e status foram comparados ao publicado e coincidem, inclusive o hook novo. `updatedb:status` e `config:status` limpos; `drush cr` PASS. O HEAD local não foi trocado para preservar alterações alheias.
- **Índice geral:** `docs/operations/status/aculta_lms_resources.yml` atualizado para dev.33. `scripts/validate-project-status-index.php` continua FAIL por divergências preexistentes de nove outros componentes e README raiz desatualizado; não rodei `--write` para não editar outros módulos.
- **Próximos passos:** revisar os contratos F15/F16 ainda abertos, especialmente a indisponibilidade de storage H5P e o fechamento das integrações Forum/entregas; não avançar para pagamentos antes de resolver o que pode ser concluído no LMS. Depois pagamentos; por último `updatedb` item a item. O link direto à fonte oEmbed não altera o Content Index nem `media.settings` global.
- **Retomar lendo:** seção F15 em `docs/modules/ACULTA-LMS-ROADMAP.md`, `aculta_lms_resources/src/CourseResourceBuilder.php`, o erro H5P 843707 e a regra Runtime de `AGENTS.md`; depois revisar as integrações Forum/entregas antes de iniciar pagamentos.

# Estado da sessão

## Handoff — assets ACULTA, multidomain-bar e fundamentos V2 — 2026-11-04

- `aculta420_v2` recebe assets copiados seletivamente da V1: logos preto/branco
  horizontais e quadrados, variante horizontal 900×300 e favicon ICO. Não há na
  V1 logo separado do Coletivo 4e20, banners/texturas adequados ou manifest/touch
  icon. `site.webmanifest` foi criado e `apple-touch-icon.png` (180×180) foi
  gerado do bitmap 32×32 do favicon com fundo da superfície clara. Inventário:
  `web/themes/custom/aculta420_v2/assets/README.md`.
- `multidomain-bar` agora representa a faixa institucional V1: fixa no topo,
  logo com link ao domínio principal, somente ícones de busca/carrinho/mensagens/
  avisos e espaço reservado para não cobrir o conteúdo. Links de portais são
  separados em uma linha secundária opcional. `aculta_portal` fornece os dados
  neutros e cacheáveis; modo de cor continua no seletor explícito.
- O V2 permanece `base theme: false` e sem cadeia Bootstrap/jQuery: os ícones
  da barra são SVG inline, os painéis e confirmações usam `<dialog>` nativo, e
  nenhuma biblioteca do tema declara Bootstrap, Popper ou jQuery. O validador
  SDC reprova regressões de tema base, dependências, atributos ou classes do
  framework. Os behaviors usam Vanilla JS, `Drupal.behaviors` e `core/once`.
- A reconciliação V2 dos tokens segue a identidade da V1. A pendência do gate
  Design Foundations foi resolvida renomeando o token de tipografia Studio para
  seu namespace de fidelity, sem mudar seu valor nem sua apresentação.
- Validações atuais: SDC schemas 55/764 PASS; V2 SDC 14 componentes com
  descoberta, schema e render isolado PASS; Design Foundations 2468 PASS;
  JavaScript syntax, PHP lint, PHPCS focal e `git diff --check` PASS. Os scripts
  npm `lint:css/js` não se aplicam: o projeto e o tema V2 não têm `package.json` nem
  pipeline Vite. A validação de fronteira tema/módulo falha em seis classes
  antigas de formulários LMS, todas fora do escopo. O Runtime `toca`/
  `aculta_runtime` estava limpo antes do rebuild desta retomada.
- A captura visual não foi executada: `DISPLAY` e `SUITE_OUT` não estão
  configurados e `DISPLAY=:0` não tem servidor X disponível. A referência
  legada 390×844 permanece como artefato externo `aculta-institution-bar-scroll-baseline-581c9f571-final`;
  manter aceite visual como pendência, sem usar Firefox headless.
- O gate amplo `validate-aculta420-foundation.php` não pode validar esta sessão
  porque exige V1 como tema público padrão e Runtime está com V2. O SDC strict
  e Design Foundations foram executados separadamente e passaram. O validador
  de índice de status ainda reporta dez divergências preexistentes entre
  manifests e status de componentes fora do escopo; o README gerado foi
  mantido apenas com a linha do tema ACULTA420; linhas de outros componentes
  foram excluídas desta entrega. A validação do índice ainda reporta divergências
  preexistentes fora do tema.
- **Publicação:** commit `3a7341656da7fb248f37b1387c4a6352b5c7c7cb` enviado por
  fast-forward para `origin/dev`. O Runtime é o mesmo checkout; pós-push,
  `drush cr` passou e `updatedb:status`/`config:status` permaneceram limpos.
- **Retomar lendo:** assets README, `components/multidomain-bar/`,
  `PagePresentationHooks.php`, roadmap/changelog V2 e este handoff. Arquivos
  Composer, alterações paralelas do módulo Messenger, JS solto da V1 e o tema
  `aculta_theme` foram preservados fora desta entrega.

## Handoff — microfase HTMX de login e gavetas — 2026-11-05

- **Regra permanente:** AJAX Drupal (`core/drupal.ajax`, `#ajax`, `use-ajax`) é legado; o padrão para novas interações assíncronas do tema ACULTA420 V2 é HTMX (`core/drupal.htmx`, `data-hx-*`). A regra foi registrada em `AGENTS.md`, `CLAUDE.md`, `web/themes/custom/aculta420_v2/AGENTS.md`, `web/themes/custom/aculta420_v2/docs/architecture.md` e políticas do Portal. Compatibilidade V1 permanece identificada como legado durante a migração.
- **Portal:** `LoginPopupForm` responde a requisições HTMX, substitui o conteúdo do popup e usa `HX-Redirect` após autenticação; fallback sem JS/HTMX permanece. Nenhum contrato novo de domínio foi criado.
- **Tema V2:** login do multidomain bar foi conectado ao HTMX; gavetas de mensagens/avisos carregam conteúdo via HTMX; counters recebem headers da resposta e comportamentos de foco/título/anúncio são compartilhados. Busca do multidomain bar e popup de registro ainda não foram migrados; F1 não está concluída.
- **Validação:** lint PHP/PHPCS focal e fixture Firefox sintética foram executados; a fixture cobriu drawer, título/foco, opções lazy, busca de destinatário, reply/token, counters, filtros de avisos e login/HX-Redirect. Isso não comprova autenticação real Drupal. Gates amplos do Portal continuam acusando divergências preexistentes de manifests/módulos e classes LMS; captura visual responsiva/modos/zoom e integração autenticada ficam pendentes.
- **Runtime:** antes da entrega, `updatedb:status` e `config:status` estavam limpos em `toca`/`aculta_runtime`; o checkout Runtime é este mesmo worktree. Revalidar após os pushes. Sem import de configuração nem escrita de dados prevista.
- **Próximos passos ao retomar:** concluir gates e commits/push desta microfase; depois retomar F1 com busca e registro, validar login contra Drupal real, capturas do checklist e demais etapas do plano. Loja permanece aguardando autorização.
