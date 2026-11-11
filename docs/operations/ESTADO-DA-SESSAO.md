# ADR-054/055 — Piloto Views row/style/display — 2026-11-10

- **Objetivo:** integrar row/style/display na View Álbuns sem perder funções nativas.
- **Estado:** `aculta_albuns:page_1` usa `revista-album-card`, `layout-grid`, `views-display`; 3 resultados, 200 e acesso anônimo antes/depois. Firefox 1280/320 claro/escuro: 3 cartões, sem overflow ou alvo <44 px.
- **Decisão:** sources preservam field handlers; display SDC declara header, filtros, rows, empty, pager, more, footer e anexos.
- **Cuidados:** pager multi-página (só 3 resultados), cache-hit, teclado e zoom DEFERRED. Suíte transversal parou no probe independente da Flag; checks focados de View passaram.
- **Commits/arquivos:** base `9ef4ad36e`; config View, dois SDCs, gate, runner, inventário, ADRs, P8/plano, TODO/status e capturas.
- **Verificações:** contrato 40; SDC 82/1774; UI Library 25; Foundations 83 pares; browser/Runtime PASS no escopo focal.
- **Pendências:** demais Views/domínios, P8/P9, Page/Entity Status, Icons e RN-00–05.
- **Próximo passo:** migrar a próxima View com comparação de render, acesso e cache.

# UI Skins — extensão Sync e limpeza da chave antiga — 2026-11-10

- **Objetivo:** tornar UI Skins obrigatório no Config Sync e retirar a última chave de paleta antiga do Runtime.
- **Estado:** Sync registra `ui_skins`; gate exige extensão ativa no export. Runtime tem UI Skins 1.2.1 e descobriu cinco plugins, cada um com 20 scopes. Mapa e scopes ativos seguem ausentes; `aculta_purpose_palette` foi removida sem cópia após busca sem consumidores.
- **Decisões e alternativas descartadas:** sem dual-read, alias ou backup da configuração antiga, conforme instrução do responsável. Sem `config:import`/atualização não autorizados.
- **Cuidados:** preservar preferência individual de modo; cache varia por domínio/usuário e marca config do mapa.
- **Commits e arquivos:** `d4142969f`, `420141139`; Sync, gate UI Skins, ADR-054/055, plano, docs de gate/skins, roadmap, TODO e status.
- **Verificações:** UI Skins 127, extensões Sync 1753, resolver Unit 2/6 e PHPCS PASS; runtime descobriu 5×20 scopes; `drush cr` PASS. Token budget FAIL por sete pendências preexistentes. Browser DEFERRED sem mapa/scopes ativos; tradução pt-br falhou porque `translations://` não está configurado.
- **Pendências e bloqueios:** importar o mapa e os scopes UI Skins pelo fluxo autorizado; comparar Links em cache, modos e visual antes dos demais domínios.
- **Próximo passo:** executar o cutover de configuração autorizado e validar o piloto Links no tema V2.

# Inventário UI Suite — fallback funcional do Portal Button — 2026-11-10

- **Objetivo/estado:** corrigir classificação auditável de `aculta_portal_button`: o Render Element e seu template sem classes visuais são `FUNCTIONAL_NATIVE_EXCEPTION`; o override do tema compõe `aculta420_v2:button` e é `UI_SUITE_NATIVE`.
- **Decisões/cuidados:** sem comportamento visual legado ou configuração de fallback antiga. Preservar somente o fallback semântico funcional do Render API; não remover o contrato neutro do módulo.
- **Arquivos:** gerador `scripts/inventory-ui-suite-consumers.php`, CSV `docs/architecture/inventarios/ui-suite-consumidores-iniciais.csv`, plano vinculante e TODO do tema.
- **Verificações:** gerador executado (386 registros); classificação/consumidores das duas linhas inspecionados; `php -l` e `git diff --check` PASS. Browser/runtime do adapter não revalidado nesta microfase.
- **Pendências/próximo passo:** continuar migração dos demais templates e validar SDC/Render API em Runtime; mapa/scopes UI Skins ainda aguardam fluxo de import autorizado.
