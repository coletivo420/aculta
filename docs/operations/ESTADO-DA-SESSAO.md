# Estado da sessão

> **Limite de registro:** no máximo 3 entradas. Registrar só ao encerrar uma fase ou o trabalho, não a cada microfase. Entradas mais antigas ficam em `docs/history/operations/ESTADO-DA-SESSAO-ARQUIVO-2026-11-05.md`, sem alteração de texto.

# Accent administrativo da Revista — adapter UI Styles

- **Objetivo:** ligar a escolha de accent do UI Styles a cada nó Revista por campo nativo.
- **Estado:** código publicado em `153b393611`; campo nativo e adapter UI Styles presentes. Firefox ESR confirmou o widget e opções green/yellow/red. A falha de submit decorreu do widget sem `#tree`; correção aplicada e coberta por teste. O value callback real do UI Styles, entity builder e render HTML passaram em transação revertida; o teaser trouxe a classe no `<article>`. Capturas ESR demonstram CSS red em claro/escuro e 320/1440 px com campo nativo temporariamente salvo; o valor foi restaurado vazio na revisão 825. Submit visual segue sem aceite: houve acesso negado/navegação incompleta; uma tentativa pré-correção criou revisão 822 sem valor.
- **Decisões e alternativas descartadas:** sem alteração no contrib UI Styles; classes continuam definidas pelo tema. Loja permanece pendente de autorização.
- **Cuidados:** não executar a atualização pendente `aculta_portal-post-active_purposes_state`; node 602 mantém o campo vazio (revisão 822).
- **Commits e arquivos:** `153b393611`; adapter `aculta_magazine`, campo Config Sync, estilos e gate UI Styles, docs de layout/roadmap.
- **Verificações:** PASS UI Styles (24), mapped classes (462), V2 foundations, SDC schemas (764), SDC render (41), PHPUnit focal (7/42), Form API value callback, builder nativo e render Drupal transacional. Browser Firefox ESR confirmou stylesheet e breakpoints com campo nativo salvo temporariamente e depois restaurado; submit automatizado pré-correção não validou persistência; revisão corrente 825 mantém o campo vazio. Suíte Unit completa e Portal Drupal12/status-index mantêm falhas alheias documentadas.
- **Pendências e bloqueios:** validar o submit visual pelo formulário e capturar a publicação com accent salvo; a tentativa automatizada de submit expirou antes de salvar. F4/F5 permanecem parciais.
- **Próximo passo:** concluir validação visual do estado salvo, mantendo Loja pendente de autorização documentada.


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
