# ADR-054/055 — cutover UI Suite e piloto Links — 2026-11-10

- **Objetivo:** migrar ownership de configurações e iniciar UI Skins no Runtime com rollout Links.
- **Estado:** snapshot íntegro validado; `updatedb` executou três updates e import parcial aplicou 12 objetos, seguido de import apenas do mapa Links. UI Styles CKEditor 5 ativo; Forum categories usa `forum-category`; mapa é só `links_aculta_org: ruderalis`, os outros domínios usam `skunk` provisório. Valores migrados preservados e IDs antigos ausentes.
- **Decisões/cuidados:** sem fallback visual legado conforme instrução do responsável; não tocar nos diffs paralelos do Messenger nem `web/tmp`.
- **Commits/arquivos:** código de migração `d3e5c32d0`; esta entrega altera mapa Sync/default, gate UI Skins, ADRs, status e changelog.
- **Verificações:** UI Skins 134, theme settings 16, foundations 83 pares e Portal Drupal 12 1.243 PASS; `drush cr` PASS. Browser/contraste/cache DEFERRED sem DISPLAY X11. Gate de comércio FAIL no rótulo público de login.
- **Pendências:** corrigir/confirmar o rótulo de login; executar piloto Links no Firefox com modos, dimensões e cache antes de expandir domínios; completar migração integral US/RN.
- **Próximo passo:** preparar sessão X11/browser e medir `data-skin`, tokens calculados e cache por domínio em Links; então decidir expansão do mapa.

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
