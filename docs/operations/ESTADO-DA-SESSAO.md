# ADR-054/055 — cutover UI Suite e piloto Links — 2026-11-10

- **Objetivo:** concluir o cutover visual e recuperar `/painel-administrativo/aparencia/ui/components` após 500/erro de agregado JS.
- **Estado:** página autenticada abre após `drush cr` (container compilado estava obsoleto em meio a alterações paralelas no Messenger). O asset dinâmico `locale/translations` foi excluído do preprocess agregado via `AppearanceHooks`; Firefox autenticado verificou 11 scripts com HTTP 200 e MIME `text/javascript`, sem falhas. Avisos inline CSP são `Report-Only`, sem bloqueio. Mapa UI Skins segue só `links_aculta_org: ruderalis`; os outros domínios ficam em `skunk` até aceite.
- **Decisões/cuidados:** não afrouxar CSP; sem fallback visual legado conforme instrução do responsável; não tocar nos diffs paralelos do Messenger nem `web/tmp`.
- **Commits/arquivos:** cutover anterior `992be479a`; correção atual inclui hook Portal, teste Unit, ajuste de raiz do browser runner, TODO/CHANGELOG e handoff.
- **Verificações:** Unit `AppearanceHooksTest` 1/1, PHP lint, PHPCS e `git diff --check` PASS; script visual autenticado passou (1280 e 320 sem overflow); rede 11/11 scripts 200/MIME correto. Validador global `validate-browser-index.php` FAIL por índice desatualizado, READMEs ausentes e referências inválidas sob `.claude/worktrees`; falha fora do escopo.
- **Pendências:** homologar visual/cache/contraste do piloto Links e modos antes de expandir domínios; resolver gate de comércio no rótulo público do login; completar migração integral US/RN.
- **Próximo passo:** publicar correção isolada, limpar cache após push e retomar piloto Links com dimensões, modos e cache por domínio.

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
