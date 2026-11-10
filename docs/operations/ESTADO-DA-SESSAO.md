# ADR-054 + ADR-055 - Migracao integral visual e UI Skins - 2026-11-10

- **Objetivo:** complementar as ADR-054 e ADR-055 para substituir a camada visual antiga por UI Skins e migrar TODO o visual legado para UI Suite, com classificacao obrigatoria deprecado/obsoleto.
- **Decisoes:** UI Skins passa a ser fonte visual unica por dominio; `DomainPurposeManager` continua apenas para roteamento, acesso, URLs, dados e cache. Preferir `ui_patterns_field_group:2.0.x-dev@dev` com gates de seguranca/Drupal 12. `ui_patterns_ckeditor5` expressamente fora desta rodada; `ui_styles_ckeditor5` permanece.
- **Entregas documentais:** `docs/architecture/ACULTA-UI-SUITE-MIGRACAO-LEGADO-SKINS.md`, plano US-00 a US-14 revisto, ADR-054/055 reconciliadas, politica de catalogo/deprecacao e prompt `docs/architecture/ACULTA-ADR054-055-PROMPT-AGENTE-EXECUCAO.md`; TODO, README e status do tema sincronizados.
- **Evidencias:** leitura do codigo de `DomainPurposeManager`, `SurfaceSettingsHooks`, `AppearanceHooks`, `theme-settings.php`, manifestos e Config Sync, mais pesquisas upstream UI Skins e Field Group. Nao houve instalacao, mudanca de configuracao ativa ou alteracao de codigo funcional.
- **Gates:** auditoria documental apenas, testes Runtime/Drupal/Browser **DEFERRED**, nao PASS.
- **Proximo passo:** executar US-00 inventario real, US-02 ponte UI Skins/domínio e US-12 classificacao integral, depois migracoes por area e encerramento US-14 com aceite visual e validações.

# Skills Drupal e UI Suite — 2026-11-10

# FST-00 a FST-11 — integração analítica Fórum + Statistics — 2026-11-10

- **Objetivo:** executar o plano canônico FST-00 a FST-11 em `web/modules/custom/aculta_portal/modules/aculta_statistics/docs/FORUM-ANALYTICS-PLANO-FST-00-11.md`.
- **Estado:** FST-00 a FST-10 implementadas; update 9008 aplicado, reconciliação 15/15 (14 iguais + 1 indisponível), Firefox ESR 54/54 PASS com 640 px (zoom 200%) e estrutura acessível. Moderação passou ao owner `aculta_cgu`; busca ligada só no desenvolvimento. Functional, modo escuro do admin e leitor de tela real pendentes.
- **Entregas:** escopo público/restrito, ledger de idempotência e update 9008, reconciliações, catálogo tipado com 23 métricas novas, provedor diário do Fórum, provedor de moderação no `aculta_cgu` sobre `aculta_ugc_report`, oito SDCs de estatísticas no tema V2, drilldown por tópico com acesso revalidado e coleta de busca agregada habilitada no desenvolvimento.
- **Arquivos principais:** `web/modules/custom/aculta_portal/modules/aculta_forum/src/Analytics/`, `modules/aculta_statistics/src/Provider/`, `src/EventSubscriber/`, `src/Controller/StatisticsDashboardController.php`, `web/themes/custom/aculta420_v2/components/statistics-*`, testes Unit/Kernel e documentação de ambos os módulos.
- **Verificações:** Unit 376/2420, Kernel statistics 56/602, Kernel focal forum 26/505, painel 8/37, moderação CGU 2/11; browser 54/54; SDC schema, classes mapeadas, fronteira, token budget, rotas administrativas e Portal Drupal 12 PASS; PHPStan focal PASS; PHPCS 0 erros.
- **Runtime:** update 9008 aplicado; ledger presente; `drush cr`; 13 snapshots; reconciliações em dry-run sem divergência. `config:import` não executado. Desempenho: 74 ms no provedor e 116 ms na coleta, 249 queries, máximo 3 ms.
- **Próximo passo:** Functional em origem isolada, modo escuro do painel administrativo (tema ativo não expõe `data-theme`), leitor de tela real e Config Sync autorizado. Publicar a finalidade da busca na Política de Privacidade antes de produção; manter desligada fora do desenvolvimento.

- **Objetivo:** auditar e modernizar as Skills para SDC, UI Patterns, UI Styles e UI Icons, incorporar o ecossistema opcional e compartilhar uma fonte canônica entre Claude Code e Codex.
- **Estado:** implementação e documentação concluídas. 18 Skills canônicas em `.agents/skills/`; symlinks relativos em `.claude/skills/` e `.codex/skills/`; alias legado `docs/agent-skills/` preservado.
- **Arquivos:** Skills existentes e quatro Skills UI Suite, `scripts/validate-agent-skills.py`, `docs/operations/AUDITORIA-SKILLS-DRUPAL-2026-11.md`, `docs/operations/TESTES-SKILLS-UI-SUITE.md`, ponteiros `AGENTS.md`/`CLAUDE.md`.
- **Verificações:** validador de Skills PASS (18 skills, links e 18 avaliações); `git diff --check` PASS; Claude Code 2.1.322 invocou `/ui-patterns` e respondeu regra exclusiva; Codex 0.167.0 incluiu a Skill UI Patterns no diagnóstico de contexto. Invocação completa de todas as Skills em ambos os agentes não executada. Core/contrib/vendor ausentes neste worktree; testes Drupal/runtime não repetidos.
- **Limites:** `core.extension.yml` e lock não comprovam runtime. SEC-01 tem guard `isAllowed()` e teste-fonte; sessão anterior registra PHPUnit 9/9, mas README do módulo está desatualizado. Não houve alteração de runtime, Composer, configuração ou implementação funcional.
- **Publicação:** commit `c3385e86d` publicado em `origin/dev` e confirmado por `git ls-remote`.
- **Próximo passo:** em outra rodada, corrigir a nota obsoleta SEC-01 no README do módulo e executar avaliação individual das 18 Skills nos dois CLIs.
