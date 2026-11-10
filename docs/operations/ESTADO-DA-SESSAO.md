# ADR-054/055 — inventário, UI Skins e migração SDC — 2026-11-10

- **Objetivo/estado:** inventário 381 itens; adapters de seções, pagamento e carrinho usam SDCs; cinco overrides Twig de categorias retirados. Runtime: UI Skins ativo, mapa/scopes ausentes; View em `fields`; UI Styles CKEditor desativado; UI Patterns CKEditor e Field Group ativos fora do Sync. Field Group `BLOCKED_UPSTREAM` (sem cobertura de segurança e sem Core 12); UI Patterns CKEditor `LEGACY_OBSOLETE`.
- **Decisões/cuidados:** removidos valores e fallbacks literais da paleta purpose. Preview global mostrou 65 diferenças, inclusive módulos, blocos, campos, permissões e filtros; importar só seis configs revisadas, preservando módulos ativos.
- **Commit/arquivos:** `afaf75c3c` publicado em `origin/dev`; inventário, snapshot Runtime, ADRs/plano, tema, Portal e Fórum. Backup externo pré-import SHA-256 `05e0fd0358db79fb4d22657666ec320307d91adb7e91bd5cfc585d338553f517`.
- **Verificações:** PHPUnit 4/26; SDC 80/119/1747; Skins 132; UI Styles 38; UI Library 25; layouts 42; classes 483; boundary 7858; PHPStan focal/PHPCS PASS; `drush cr` PASS. Composer válido com avisos de constraints exatas; token budget FAIL (7 dívidas globais).
- **Pendências:** `drush cim` parcial foi autorizado e preview revisado; aguarda autorização específica para post-update `active_site_keys_state` via `updatedb`. Após import: render/cache/acesso, browser e CSS calculado. Alterações locais de LMS foram preservadas fora do commit.
- **Próximo passo:** executar post-update autorizado, importar as seis configs, validar Views/Skins/CKEditor e confirmar retirada de templates no Runtime.
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
