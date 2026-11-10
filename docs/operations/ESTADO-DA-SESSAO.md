# ADR-054/055 — migração de ownership para desbloquear UI Suite — 2026-11-10

- **Objetivo/estado:** rechecagem apontou dependências de `aculta_forum_lms` antigas no DB e configs simples com IDs de módulos consolidados. Código agora tem post-updates que movem `aculta_contributions.settings` → `aculta_cgu.settings` e `aculta_mail_confirmer_user.settings` → `aculta_mail_confirmer.settings`, removendo os IDs antigos. Plug-in H5P usa o namespace proprietário atual.
- **Decisões/cuidados:** valores antigos são copiados para os destinos sem fallback; rollback é restauração do snapshot pré-update. Não reativar módulos removidos nem tocar nos diffs paralelos de LMS.
- **Arquivos:** post-updates, schemas, Sync, consumidores, testes Kernel, TODO/status, ADRs/plano e inventário CGU; staged revisado, sem arquivos LMS paralelos.
- **Verificações:** Kernel focal 2 testes/9 assertions PASS; PHPStan e gate Portal Drupal 1.243 PASS; suites/index PASS; PHPCS zero erros (warnings preexistentes); token budget FAIL (7 globais); comércio Runtime aguarda migration.
- **Pendências:** autorização `updatedb` recebida; gerar snapshot integral atualizado, publicar código, rodar post-updates, conferir valores/remoção e revisar import parcial das configurações UI Suite/Views.
- **Próximo passo:** concluir gates, commit/push, snapshot, `updatedb`, depois preview e import parcial autorizado; validar Views/skins/CKEditor no Runtime.

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
