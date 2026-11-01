# ARQ-06F — Auditoria da Migração Histórica das Sinalizações do Fórum

Este documento registra os resultados da auditoria somente leitura das sinalizações legadas do Fórum (`aculta_forum` / `aculta_user`) e o procedimento homologado para execução futura da migração controlada para `aculta_ugc`.

---

## 1. Auditoria Somente Leitura no Runtime (`aculta_runtime`)

A verificação foi executada de forma estritamente somente leitura no banco de dados de desenvolvimento:

| Critério | Valor Auditado | Observação |
| :--- | :--- | :--- |
| **Flags avaliadas** | `sinalizar_topico`, `sinalizar_comentario`, `sinalizar_post_forum` | Flags registradas no Core e módulo Flag |
| **Total de registros `flagging` legados** | **0** | Não há sinalizações pendentes na base de teste |
| **Por tipo de entidade (`node`)** | 0 | — |
| **Por tipo de entidade (`comment`)** | 0 | — |
| **Por tipo de entidade (`aculta_forum_post`)** | 0 | — |
| **Alvos órfãos / excluídos** | 0 | — |
| **Duplicatas na origem** | 0 | — |
| **Denúncias já migradas em `aculta_ugc_report`** | 0 | Módulo `aculta_ugc` não habilitado no Runtime |

---

## 2. Mecanismo de Migração e Garantias Idempotentes

A transição é gerenciada pelo serviço `aculta_ugc.report_queue` via método `migrateFromReportQueue($dryRun, $limit)`:

1. **Modo Simulação (`$dryRun = TRUE`):**
   - Executa leitura e agregação dos registros.
   - Computa a estimativa de novas denúncias, duplicatas e alvos órfãos sem invocar `$report->save()`.
2. **Preservação de Histórico e Datas:**
   - O campo `legacy_flag_id` em `UgcReport` armazena o identificador original do `flagging`.
   - A data `created` é copiada diretamente do registro original.
   - O motivo é atribuído como `'sinalizacao_legada_forum'`.
3. **Idempotência Rigorosa:**
   - A consulta verifica tanto a existência prévia de `legacy_flag_id` quanto o par único `(target_type, target_id, uid)`.
   - Reexecuções sucessivas geram **0 novas denúncias**, contabilizando-as como `ignoradas_duplicadas`.
4. **Capacidade de Rollback:**
   - O método `rollbackMigration()` permite reverter de forma segura e exclusiva os registros originados do legado (`legacy_flag_id IS NOT NULL AND motivo = 'sinalizacao_legada_forum'`), sem afetar denúncias novas criadas por usuários via UGC.

---

## 3. Procedimento Operacional para Execução Futura

Quando o responsável aprovar formalmente a execução persistente da migração no Runtime compartilhado, o procedimento a seguir deverá ser utilizado:

```bash
# 1. Validação prévia em modo simulação (dry-run):
vendor/bin/drush ev "
  \$queue = \Drupal::service('aculta_ugc.report_queue');
  \$stats = \$queue->migrateFromReportQueue(TRUE);
  print_r(\$stats);
"

# 2. Execução persistente autorizada:
vendor/bin/drush ev "
  \$queue = \Drupal::service('aculta_ugc.report_queue');
  \$stats = \$queue->migrateFromReportQueue(FALSE);
  print_r(\$stats);
"

# 3. Rollback de emergência (se necessário):
vendor/bin/drush ev "
  \$queue = \Drupal::service('aculta_ugc.report_queue');
  \$reverted = \$queue->rollbackMigration();
  echo 'Revertidos: ' . \$reverted . PHP_EOL;
"
```

> [!IMPORTANT]
> Nenhuma migração persistente, exclusão de flags históricas ou remoção de tabelas legadas foi executada nesta fase. A transição foi preparada e validada de forma não destrutiva.
