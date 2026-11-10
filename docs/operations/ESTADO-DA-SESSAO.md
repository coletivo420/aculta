# ADR-054 + ADR-055 — UI Skins/site_key — 2026-11-10

- **Objetivo:** cortar o motor visual por purpose e adotar UI Skins sem dual-read nem config legada.
- **Estado:** dez Domain IDs mapeados a skins; cinco variáveis e vinte scopes claro/escuro. Removidos formulário/config, esquemas, tokens/seletores e testes antigos. `DomainPurposeManager` mantém funções de domínio.
- **Commit/arquivos:** `693d5e173` publicado; resolver Portal, hooks/tokens/settings do tema, Config Sync, inventário e docs.
- **Verificações:** UI Skins 126, theme-settings 16, UI Styles 38, foundations 83+6, Unit 11/31, PHPStan/PHPCS, YAML, boundary/diff e `drush cr`: PASS; push confirmado.
- **Limites:** Runtime tem UI Skins Disabled e Config Sync apresenta drift; sem `cim`/enable. Render real, cache visual, capturas e aceite por domínio DEFERRED. Stylelint indisponível. Token-budget gate registra dívida preexistente.
- **Próximo passo:** validar versão instalada/API UI Skins no Runtime após procedimento de Sync autorizado; completar piloto Links com capturas/modos/cache e seguir migração UI Suite/legado.
# FST-00 a FST-11 — integração analítica Fórum + Statistics — 2026-11-10

- **Objetivo:** executar o plano canônico FST-00 a FST-11 em `web/modules/custom/aculta_portal/modules/aculta_statistics/docs/FORUM-ANALYTICS-PLANO-FST-00-11.md`.
- **Estado:** FST-00 a FST-11 implementadas e testadas; update 9008 aplicado, reconciliação sem divergências, Firefox ESR 54/54, Functional PASS (2/20) e estrutura acessível/AT-SPI coletada. Moderação no owner `aculta_cgu`; busca ligada só no desenvolvimento. Modo escuro do admin e leitor de tela real permanecem limites de tema/ferramenta.
- **Entregas:** escopo público/restrito, ledger de idempotência e update 9008, reconciliações, catálogo tipado com 23 métricas novas, provedor diário do Fórum, provedor de moderação no `aculta_cgu` sobre `aculta_ugc_report`, oito SDCs de estatísticas no tema V2, drilldown por tópico com acesso revalidado e coleta de busca agregada habilitada no desenvolvimento.
- **Arquivos principais:** `web/modules/custom/aculta_portal/modules/aculta_forum/src/Analytics/`, `modules/aculta_statistics/src/Provider/`, `src/EventSubscriber/`, `src/Controller/StatisticsDashboardController.php`, `web/themes/custom/aculta420_v2/components/statistics-*`, testes Unit/Kernel e documentação de ambos os módulos.
- **Verificações:** Unit 376/2420, Kernel statistics 56/602, Kernel focal forum 26/505, painel 8/37, moderação CGU 2/11, Functional 2/20, browser 54/54, acessibilidade Playwright/AT-SPI; SDC schema, classes mapeadas, fronteira, token budget, rotas administrativas e Portal Drupal 12 PASS; PHPStan focal PASS; PHPCS 0 erros.
- **Runtime:** update 9008 aplicado; ledger presente; `drush cr`; 13 snapshots; reconciliações em dry-run sem divergência. `config:import` não executado. Desempenho: 74 ms no provedor e 116 ms na coleta, 249 queries, máximo 3 ms.
- **Próximo passo:** modo escuro do painel administrativo exige theming do admin; leitor de tela real e Config Sync autorizado seguem pendentes. Publicar a finalidade da busca na Política de Privacidade antes de produção; manter desligada fora do desenvolvimento. Guia Functional em `docs/operations/BANCO-DE-TESTE.md` e skill `aculta-navegador-testes`.

- **Objetivo:** auditar e modernizar as Skills para SDC, UI Patterns, UI Styles e UI Icons, incorporar o ecossistema opcional e compartilhar uma fonte canônica entre Claude Code e Codex.
- **Estado:** implementação e documentação concluídas. 18 Skills canônicas em `.agents/skills/`; symlinks relativos em `.claude/skills/` e `.codex/skills/`; alias legado `docs/agent-skills/` preservado.
- **Arquivos:** Skills existentes e quatro Skills UI Suite, `scripts/validate-agent-skills.py`, `docs/operations/AUDITORIA-SKILLS-DRUPAL-2026-11.md`, `docs/operations/TESTES-SKILLS-UI-SUITE.md`, ponteiros `AGENTS.md`/`CLAUDE.md`.
- **Verificações:** validador de Skills PASS (18 skills, links e 18 avaliações); `git diff --check` PASS; Claude Code 2.1.322 invocou `/ui-patterns` e respondeu regra exclusiva; Codex 0.167.0 incluiu a Skill UI Patterns no diagnóstico de contexto. Invocação completa de todas as Skills em ambos os agentes não executada. Core/contrib/vendor ausentes neste worktree; testes Drupal/runtime não repetidos.
- **Limites:** `core.extension.yml` e lock não comprovam runtime. SEC-01 tem guard `isAllowed()` e teste-fonte; sessão anterior registra PHPUnit 9/9, mas README do módulo está desatualizado. Não houve alteração de runtime, Composer, configuração ou implementação funcional.
- **Publicação:** commit `c3385e86d` publicado em `origin/dev` e confirmado por `git ls-remote`.
- **Próximo passo:** em outra rodada, corrigir a nota obsoleta SEC-01 no README do módulo e executar avaliação individual das 18 Skills nos dois CLIs.
