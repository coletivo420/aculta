# Estado da sessão

> **Limite de registro:** no máximo 3 entradas. Registrar só ao encerrar uma fase ou o trabalho, não a cada microfase. Entradas mais antigas ficam em `docs/history/operations/ESTADO-DA-SESSAO-ARQUIVO-2026-11-05.md`, sem alteração de texto.

# Runtime ACULTA420 V2 — Revista, PHPUnit, purpose e botões — 2026-11-06

- **Objetivo:** reconciliar configuração de teste, validar accent global da Revista, PHPUnit, palette/botões e registrar a evidência do shell.
- **Estado:** origem `aculta420_v2.settings`; `preprocess_node` adiciona `revista-teaser--accent-{green|yellow|red}` ao wrapper do node e ao SDC, com dependência de cache. Form UI Styles submetido; quatro teasers passaram no Firefox ESR (claro/escuro, 320/1440, sem overflow). Accent global restaurado vazio; banco: 5 nós Revista, 0 accents próprios; node 602 vazio. Campo legado fica fora do fluxo e preservado sem remoção destrutiva.
- **Configuração ativa:** CAPTCHA global `0`; divergências de captcha display e `core.extension` reconciliadas; `aculta420_v2.settings` criada. `config:status` ainda lista blocos/config local do tema administrativo, Turnstile sensível e definições legadas de field apenas no Sync.
- **Commits publicados antes deste handoff:** `c8e9f37f9e` (config/reconciliação e shell), `42ca2af7e9` (matriz palette/botões), `80aae13a1c` (wrapper/cache/teste Revista), `33561c8743` (manifest Revista), `77904cc2ea` (suíte de botões).
- **Verificações:** UI Styles 24 PASS; foundations 73 pares texto + 6 faixas PASS; SDC schema 55/764 PASS; SDC render 41 PASS; PHPUnit tema 12 testes/56 assertions PASS. Bootstrap criou diretório de saída a partir de ausência e a execução não mostrou aviso. Firefox: palette/botões 358/360; shell 108/120; os PNGs estão ignorados e só manifests versionados.
- **Pendências:** F3 permanece aberta pelos dois overflows do Fórum a 320 px. F2 permanece para aceitação visual do responsável; a matriz também detectou contraste insuficiente da navegação em claro a 1440 px nos dez purposes. A suíte ampla `aculta-editor/access.py`, após corrigir seu import sob `-I`, parou por ausência de CKEditor no formulário de Revista para `content_editor`; o formulário administrativo de UID 1 foi validado separadamente. Não alterar o shell nesta fase.
- **Próximo passo:** tratar o overflow do Fórum sem alterar o shell e repetir a suíte de botões; aguardar aceitação visual de F2 e verificar o fluxo editor quando o CKEditor do Runtime estiver disponível.


# Adoção por purpose — `profile-details` no Studio — 2026-11-06

- **Objetivo:** integrar bio e redes sociais do perfil do Studio a um SDC, respeitando os limites de UI Styles e autorização de Loja.
- **Estado:** `profile-bio` e `profile-social` mapeados a `profile-details`, sem alterar `author-card`; slots do `aculta_user` preservados. Captura Firefox ESR antes/depois em `/meu-perfil`: 97/97 checks por rodada, 22 ignorados e zero falhas, claro/escuro, 320/1440 px, foco no slot de ações e sem overflow. Evidências externas em `$SUITE_OUT/layouts-purpose-studio-profile-details-paired-20261106/{before,after}/`.
- **Decisões e alternativas descartadas:** Revista agora tem adapter nativo no módulo owner e usa o mecanismo UI Styles sem alterar contrib; validação do estado salvo ainda pendente. Loja continua pendente sem autorização em `docs/`.
- **Arquivos:** SDC `components/profile-details/`, `PagePresentationHooks`, gate de render SDC, teste unitário e suíte visual Studio; documentação de consumer/status/roadmap.
- **Verificações:** PHPUnit 7/33; schema 764 checks; SDC render 41 componentes; mapped classes (459), foundations (73 pares), UI Styles (20), browser validators (21), module-theme boundary (4274), diff check e captura ESR PASS. `drush cr` PASS. `config:status` mostra apenas diferenças preexistentes em blocos administrativos, CAPTCHA/Turnstile e `core.extension`.
- **Pendências:** Revista aguarda captura do submit visual com escolha salva; Loja aguarda autorização documentada. Baselines anteriores não foram modificadas.
- **Próximo passo:** concluir o aceite visual da seleção nativa da Revista; não iniciar templates de Loja até autorização em `docs/`.

# Fase 5 — Componentes e templates (SDC consumers integrados) — 2026-11-05

- **Objetivo:** implementar consumers reais no tema `aculta420_v2` para os componentes SDC pendentes (`author-card`, `dialog`, `form-field`, `progress`, `data-table`, `empty-state` e `tabs`), fechar pendências de templates e preparar handoff operacional da F5.
- **Concluído:**
  - Implementado `templates/navigation/menu-local-tasks.html.twig` integrando `aculta420_v2:tabs` nas abas primárias e secundárias do Drupal.
  - Implementado `templates/misc/progress-bar.html.twig` integrando `aculta420_v2:progress` com rótulo e valor percentual.
  - Implementado `templates/form/form-element.html.twig` integrando `aculta420_v2:form-field` para campos de formulário Drupal preservando atributos e classes nativas.
  - Implementado `templates/views/views-view-table.html.twig` integrando `aculta420_v2:data-table` com rolagem horizontal acessível e `data-label` nas células para mobile.
  - Implementado `templates/views/views-view.html.twig` integrando `aculta420_v2:empty-state` para apresentação padronizada de resultados vazios de Views.
  - Documentação e consolidação de consumers de `author-card` (`aculta-user-author-card.html.twig`) e `dialog` (`lms-tour.twig`).
  - Atualização dos arquivos de documentação do tema (`docs/status-componentes.md`, `docs/roadmap.md`, `CHANGELOG.md` e `TODO.md`).
  - Confirmação de ausência de entrada obsoleta `bootstrap5` em `core.extension` e no Runtime.
- **Verificações:** SDC Schema PASS (55 componentes, 27 includes, 764 checks), Drush SDC PASS (40 componentes isolados), V2 Foundations PASS (73 pares de contraste, 6 pares de faixa, foco e bordas), Mapped Classes PASS (459 classes, 0 exceções, 0 Bootstrap), Module-Theme Boundary PASS (4274 checks), Browser Validators PASS (21 checks), UI Styles PASS (20 checks), `drush cr` PASS.
- **Pendências:** componente `price` aguarda autorização de Commerce/Loja; testes de navegador/capturas visuais delegados para outro agente.
- **Próximo passo:** validação visual no servidor de testes e avanço para Release Candidate (RC).
