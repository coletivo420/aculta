# Estado da sessão

> **Limite de registro:** no máximo 3 entradas. Registrar só ao encerrar uma fase ou o trabalho, não a cada microfase. Entradas mais antigas ficam em `docs/history/operations/ESTADO-DA-SESSAO-ARQUIVO-2026-11-05.md`, sem alteração de texto.

# Fórum — Comentários Compartilhados 0.2.0-dev.2 (Saneamento Agente 1) — 2026-11-08

- **Objetivo:** executar saneamento da base de comentários antes do novo compositor inline (C02-01 e C02-02, achados COM-02/03/07/08/09/10/11) e preparar entrega para o Agente 2.
- **Estado:** C02-01 e C02-02 concluídos. `WikiDiscussionContextProvider` migrado e registrado em `aculta_forum` sem dependência reversa; contrato neutro de ações `#links['comment-quote']` em conformidade com o Drupal Core; atributos `id`, classes, autoria SDC sem leitura do User no Twig; permalinks sob D-65 apontando ao host canônico com pager 0 e `#comment-{cid}`; remoção de injeção insegura no cliente e escopo global em `forum-quote.js`.
- **Decisões:** COM-01, COM-05 e eliminação de redirects em COM-12 dependem da criação do compositor inline e foram atribuídos explicitamente ao Agente 2 (C02-03 a C02-08). Idempotência auditada no fluxo nativo (não possui deduplicação; recomendada inclusão de token no compositor inline em C02-07).
- **Arquivos:** `WikiDiscussionContextProvider.php`, `DiscussionCommentPermalinkBuilder.php`, `DiscussionCommentPermalinkHooks.php`, `ForumQuoteHooks.php`, templates `comment--field-aculta-discussion` e `comment--comments`, `forum-quote.js`, `forum-post-card.css`, planos e testes unitários.
- **Verificações:** PHPUnit isolado 32/32 PASS (123 assertions); `validate-project-status-index.php --write` PASS (41 componentes); SDC schemas e classes mapeadas validadas.
- **Runtime:** Homelab inacessível localmente para `drush cr`; status registrado como `DEFERRED`.
- **Próximo passo:** Agente 2 inicia C02-03 (experimento Form API + HTMX para resposta inline na mesma página) e fecha COM-01, COM-05 e COM-12.

# ACULTA CGU e Consolidação Modular Transversal — 2026-11-08

- **Objetivo:** unificar e consolidar módulos intermediários e duplicados no portal, integrando recursos aos seus donos de domínio e criando o módulo transversal `aculta_cgu` para gestão de Conteúdo Gerado por Usuários.
- **Estado:** funcional e estável. `aculta_cgu` reúne propostas comunitárias privadas (`Contribution`) e moderação de denúncias (`UgcReport`); submódulos `aculta_forum_wiki`, `aculta_lms_resources`, `aculta_lms_agenda`, `aculta_lms_messenger`, `aculta_forum_lms` foram incorporados diretamente nos seus módulos de domínio (`aculta_wiki`, `aculta_lms`, `aculta_forum`, `aculta_calendar`, `aculta_messenger`). Runtime respondendo HTTP 200 OK.
- **Decisões e alternativas descartadas:** criação de aliases de serviços legados (`aculta_contributions.*` e `aculta_ugc.*`) no `aculta_cgu` para transição transparente; desregistro ordenado dos módulos extintos no banco e em `core.extension.yml` antes da remoção dos arquivos do disco para prevenir erro 500 no autoloader.
- **Cuidados:** preservação dos esquemas de banco e entidades (`aculta_contribution` e `aculta_ugc_report`) sob o novo provider para manter os dados existentes íntegros; `drush cr` disparado a cada intervenção estrutural.
- **Commits e arquivos:** `3c16b497c`, `900734aa8`, `c44b8e3c5`, `9a734b0ef`, `a92c31f58`, `8f4e9e534`, `7f05433a2`, `ca1015201`; módulos `aculta_cgu`, `aculta_lms`, `aculta_wiki`, `aculta_forum`, `aculta_events`, `aculta_user`, `aculta_content_admin` e sync `core.extension.yml`.
- **Verificações:** Unit CGU 3/19 PASS; Unit Wiki/Events 129/545 PASS; `validate-project-status-index.php` PASS (44 componentes); `validate-config-extension-dependencies.php` PASS (1714 checks); smoke test HTTP 200 PASS. Kernels com banco isolado: `UgcReportPolicyKernelTest` 12/162 PASS.
- **Pendências e bloqueios:** homologação funcional autenticada da fila unificada em `/painel-administrativo/moderacao/denuncias` e submissões com perfis de teste.
- **Próximo passo:** homologar operacionalmente o painel administrativo de denúncias e o ciclo de revisão de propostas no Runtime.

# ACULTA Maps + OpenStreetMap — Diagnóstico, Visual e Homologação — 2026-11-07

- **Objetivo:** diagnosticar mapas não renderizados, corrigir causa raiz, alinhar apresentação visual no tema V2 e homologar funcional e visualmente.
- **Estado:** causa diagnosticada e documentada (EventLocation::preSave só geocodificava em isNew ou mudança de endereço; local salvo sem coordenadas ficava sem marker); corrigido no owner aculta_events. Ordem mobile-first (mapa -> aviso -> lista), URLs canônicas e categorias aplicadas. Tema V2 atualizado com alturas responsivas (18rem/24rem/30rem), Leaflet attribution legível e foco visível. Ponte SDC map-popup consolidada com story mínima. Homologação Playwright Firefox ESR completa (320, 390, 768, 1024, 1440 px, claro/escuro, zoom 200%, sem JS) com PASS e sem overflow.
- **Decisões:** OSM/Leaflet é o padrão sem chave. OpenStreetMap integration administrativa no MAIN protegida por permission (403 anônimo) e sem escrita externa.
- **Cuidados:** nenhuma escrita no OSM; nenhuma alteração destrutiva ou de banco no Runtime; nenhuma credencial gravada.
- **Commits e arquivos:** 7f05e3b21b, 1ea74f2ddf, c2ca27a068; aculta_maps (docs, status, TODO), aculta_events (Entity, Controller, Kernel tests), aculta420_v2 (SDC stories, styles, docs), capturas (manifest.json).
- **Verificações:** Kernel MapControllerKernelTest 4/4 PASS (79 assertions); Kernel OpenStreetMapOAuthSafetyKernelTest 4/4 PASS; SDC Schemas PASS (52 components, 1126 checks); Mapped Classes PASS; Stylelint PASS; PHPCS PASS; Homologação Playwright Firefox 24 capturas PASS.
- **Pendências e bloqueios:** atualização de coordenadas do local existente no banco aguarda atualização/re-save via interface administrativa pelo responsável (sem UPDATE direto no DB conforme AGENTS.md); minuta de privacidade 0.6 pendente de aprovação antes de publicação.
- **Próximo passo:** aprovação da minuta de privacidade com o responsável e homologação das telas de edição/importação com usuário autenticado.

