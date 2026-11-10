# ACULTA420 V2 — P9: runbook de cutover e rollback

**Estado:** cutover **não executado**. Este documento prepara a execução e o rollback. O corte depende de aceite visual humano e da migração dos blocos das regiões antigas.

## 1. Pré-requisitos bloqueantes

1. Aceite visual do shell v2 e das landings pelo responsável.
2. `php scripts/validate-aculta420-shell-regions.php --strict` **PASS** (hoje falha com 5 blocos em `highlighted`).
3. Importação/exportação de configuração validada em staging.
4. Backup do Config Sync e das coleções de domínio afetadas.

## 2. Blocos a migrar antes de remover regiões

| Bloco | Região atual | Destino no shell v2 |
| --- | --- | --- |
| `aculta420_v2_aculta_messages` | `highlighted` | `content`, peso negativo (antes do conteúdo principal) |
| `aculta420_v2_aculta_primary_admin_actions` | `highlighted` | `content`, peso negativo |
| `aculta420_v2_aculta_primary_local_tasks` | `highlighted` | `content`, peso negativo |
| `aculta420_v2_aculta_secondary_local_tasks` | `highlighted` | `content`, peso negativo |
| `aculta420_v2_aculta_donation_quick_apoio` | `highlighted` | `content`, peso negativo |

As regiões `navigation`, `help` e `breadcrumb` não têm blocos globais; ainda assim, verificar **todas** as coleções de domínio (`aculta_domain_config`) antes de removê-las.

## 3. Sequência de cutover

1. **Aceite e janela:** registrar aprovação do responsável e janela de manutenção curta no desenvolvimento.
2. **Snapshot:**
   ```sh
   mkdir -p "$SUITE_OUT/backup-cutover"
   vendor/bin/drush config:get block.block.aculta420_v2_aculta_messages --format=yaml > "$SUITE_OUT/backup-cutover/messages.yml"
   # repetir para os cinco blocos e para cada override de domínio afetado
   cp web/themes/custom/aculta420_v2/aculta420_v2.info.yml "$SUITE_OUT/backup-cutover/info.yml"
   ```
3. **Migrar blocos globais:** carregar cada entidade `block` e definir `region: content` e o peso negativo planejado; salvar. Conferir que o bloco global não foi excluído.
4. **Migrar overrides de domínio:** repetir a região/peso nas coleções de domínio que sobrescrevem esses blocos. A entidade global deve continuar existindo.
5. **Validar:** `php scripts/validate-aculta420-shell-regions.php --strict` deve passar sem blocos em regiões antigas.
6. **Remover regiões do `info.yml`:** retirar `navigation`, `highlighted`, `help` e `breadcrumb`. Manter `institution`, `header`, `content`, `sidebar_first` e `footer`. `page_top`/`page_bottom` continuam placeholders do Core.
7. **Ativar o shell v2 por padrão:** `aculta_shell_variant: v2` em `config/install` e na configuração ativa; `drush cr`.
8. **Retirar o container legado:**
   - remover a leitura de `aculta_purpose_container` de `SurfaceSettingsHooks::preprocessHtml`;
   - remover `data-purpose-layout` e `data-purpose-style`;
   - retirar `resolveContainerStyle` e a resolução de larguras de `PurposeContainerLayout`, mantendo `isComponentLibraryRoute` em uma classe própria;
   - remover o schema `aculta420_v2.purpose_container_layout` e a chave `aculta_purpose_container`;
   - remover seletores e variáveis CSS comprovadamente sem consumidores;
   - atualizar `docs/configuracao-do-tema.md`, `docs/architecture.md` e ADRs.
9. **Gates finais:** todos os gates do tema e o teste Playwright de 86 checks; smoke HTTP nos dez purposes.
10. **Publicar** o commit em `dev` e sincronizar o Runtime com `drush cr`.

## 4. Rollback

1. **Shell:** `drush config:set aculta420_v2.settings aculta_shell_variant classic -y && drush cr` (enquanto o template clássico existir).
2. **Blocos:** restaurar as regiões/pesos a partir do snapshot (`drush config:set` ou API de entidade de bloco) e `drush cr`.
3. **Regiões:** restaurar `aculta420_v2.info.yml` do snapshot e limpar o cache.
4. **Código:** `git revert` do commit de cutover (não reescrever histórico).
5. **Verificação:** `validate-aculta420-shell-regions.php` e smoke HTTP nos dez purposes.

## 5. O que não fazer

- Não remover região enquanto houver bloco ou override apontando para ela.
- Não executar `drush cim` no desenvolvimento com drift.
- Não apagar configuração, entidades de bloco ou dados.
- Não remover `PurposeContainerLayout::isComponentLibraryRoute` sem separar seu uso na UI Patterns Library.
- Não declarar cutover concluído sem aceite visual registrado.

## 6. Situação atual dos gates

- `validate-aculta420-shell-regions.php`: PASS normal (31 checks); **FAIL em `--strict`** com 5 blocos em `highlighted` — exatamente o bloqueio do item 2.
- Layout Builder: 22 dos 24 displays compatíveis; 2 exclusões documentadas.
- P8 automatizado: PASS; aceite humano pendente.
