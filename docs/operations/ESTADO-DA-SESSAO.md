# ADR-054/055 — ComponentLayout SDC e piloto Links — 2026-11-10

- **Objetivo:** migrar displays de página compatíveis para UI Patterns Layouts, validar a ponte UI Skins Links/MAIN e continuar a substituição visual SDC First.
- **Estado:** `node.page.default/full` usam `ui_patterns:aculta420_v2:layout_section`; Runtime confirma ambas as configurações e mantém os blocos body/links. UI Skins ativo: Links Ruderalis, MAIN Skunk. A/B/A anônimo confirmou HTTP 200 e tokens claros/escuros corretos sem mistura. Cache de página permanece `UNCACHEABLE`, então cache-hit não foi homologado.
- **Decisões/cuidados:** rollback por import parcial dos dois YAML anteriores; sem alterações em dados. Preservar as duas exclusões Layout Builder (`node.document.full`, `block_content.basic.default`). Não tocar nas alterações paralelas do Fórum nem em `web/tmp`.
- **Arquivos:** dois displays Config Sync, gerador/CSV do inventário, snapshot Runtime, gate SDC, teste Unit `LayoutSectionComponentTest`, TODO/status/changelog, ADR/plano e manifesto de capturas.
- **Verificações:** PHPUnit 2 testes/20 assertions; PHPCS; gate UI Skins 134, UI Styles 38, SDC 41 componentes; Firefox Links 1280/320 sem overflow, uma main/H1, capturas com hashes no manifesto. Cache-hit, contraste de página total, teclado, zoom e demais displays seguem pendentes.
- **Pendências:** migrar os demais displays compatíveis; investigar `X-Drupal-Dynamic-Cache: UNCACHEABLE`; concluir aceite visual Links e expandir somente após validação; executar US/RN restantes.
- **Próximo passo:** seguir o inventário para migrar o próximo display Layout Builder compatível e validar acesso/render/cache, mantendo os dois casos excluídos.

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
