# UI Skins — extensão Sync e limpeza da chave antiga — 2026-11-10

- **Objetivo:** tornar UI Skins obrigatório no Config Sync e retirar a última chave de paleta antiga do Runtime.
- **Estado:** Sync registra `ui_skins`; gate exige extensão ativa no export. Runtime tem UI Skins 1.2.1 e descobriu cinco plugins, cada um com 20 scopes. Mapa e scopes ativos seguem ausentes; `aculta_purpose_palette` foi removida sem cópia após busca sem consumidores.
- **Decisões e alternativas descartadas:** sem dual-read, alias ou backup da configuração antiga, conforme instrução do responsável. Sem `config:import`/atualização não autorizados.
- **Cuidados:** preservar preferência individual de modo; cache varia por domínio/usuário e marca config do mapa.
- **Commits e arquivos:** `d4142969f`; Sync, gate UI Skins, ADR-054/055, plano, docs de gate/skins, roadmap, TODO e status.
- **Verificações:** UI Skins 127, extensões Sync 1753, resolver Unit 2/6 e PHPCS PASS; runtime descobriu 5×20 scopes; `drush cr` PASS. Token budget FAIL por sete pendências preexistentes. Browser DEFERRED sem mapa/scopes ativos; tradução pt-br falhou porque `translations://` não está configurado.
- **Pendências e bloqueios:** importar o mapa e os scopes UI Skins pelo fluxo autorizado; comparar Links em cache, modos e visual antes dos demais domínios.
- **Próximo passo:** executar o cutover de configuração autorizado e validar o piloto Links no tema V2.

# UI Icons Menu, Picker e Patterns — 2026-11-10

- **Objetivo:** ligar os contratos oficiais UI Icons ao Sync e ao SDC button.
- **Estado:** Menu, Picker, Fields e Patterns declarados; menus main/account limitam seleção a Phosphor; Picker configurado em UI Patterns. Nenhum link recebeu ícone automaticamente. Runtime/importação e validação administrativa/browser seguem pendentes.
- **Decisões e alternativas descartadas:** Popover não selecionado para evitar seletor paralelo; Fluent Emoji permanece no catálogo, fora dos menus institucionais.
- **Cuidados:** preserve acesso e propriedade editorial dos links; UI Icons Menu não torna ícones obrigatórios.
- **Commits e arquivos:** `a55eaf1ba`; configuração em `config/sync/`, inventário UI Suite, teste `UiIconsPackTest`, ADR-055, roadmap, changelog, TODO e status.
- **Verificações:** PHPUnit focal 2/2 (47), YAML, schema SDC 1721, dependências Sync 1753 e PHPCS PASS. Kernel upstream UI Icons retornou erro por ausência de `SIMPLETEST_DB`; SDC amplo FAIL por `auth-dialog.twig`; token budget FAIL por sete pendências globais. Import Runtime DEFERRED.
- **Pendências e bloqueios:** testar formulários, permissões, foco e render após cutover autorizado.
- **Próximo passo:** validar a importação no fluxo autorizado e continuar integração/migração visual por domínio.

# ADR-054 + ADR-055 — UI Suite editorial e Views — 2026-11-10

- **Objetivo/estado:** UI Skins cortou paletas purpose. UI Styles CKEditor está no Sync. O catálogo Eventos migrou do markup do módulo para o SDC `card-cultural` pelo override do tema, com prop de cancelamento; inventário: 386 itens/88 templates Twig.
- **Decisões/cuidados:** sem fallback legado. `DomainPurposeManager` mantém função. UI Patterns CKEditor 5 obsoleto; ativação é snapshot histórico e uninstall Runtime aguarda autorização.
- **Commits/arquivos:** `99d8bcc62`, `36b0a977c`, `2c6b356c7`, `869c1a3dd`; Events, `card-cultural`, inventário e status.
- **Verificações:** Kernel Eventos 7/7 (130), ForumTopicDisplayConfig 20/20 (312), SDC 1721, fronteira 7871, classes mapeadas 483, foundations 83 pares, UI Library 25, PHPCS e status PASS. Token budget FAIL em 7 limites globais. Render visual/runtime DEFERRED.
- **Pendências/próximo passo:** browser com tema ativo, homologar acesso/cache e contraste do cartão; concluir cutovers UI Skins/Styles e migrar demais templates/domínios. Gate amplo SDC falha em `auth-dialog.twig` preexistente.

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
