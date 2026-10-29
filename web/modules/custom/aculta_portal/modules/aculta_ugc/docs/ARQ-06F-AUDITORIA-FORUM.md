# ARQ-06F — Auditoria de Consumidores e Integração Operacional Fórum ↔ UGC

Este documento registra o inventário dos fluxos de denúncia existentes no Fórum, a fila de moderação legada e a matriz de compatibilidade para a transição para a infraestrutura transversal `aculta_ugc`.

---

## 1. Inventário de Componentes e Consumidores

### 1.1 Flags de Denúncia do Fórum
1. **`sinalizar_topico`** (bundle: `node:aculta_forum_topic`):
   - Exibida no view display de tópicos do Fórum.
   - Protegida por `HistoricForumFlagAccessHooks` (`flag_action_access` e `flagging_presave`).
   - Rejeita tópicos despublicados e conteúdo histórico importado do Growroom antigo.
2. **`sinalizar_comentario`** (bundle: `comment:aculta_discussion_post`):
   - Sinalização histórica de comentários vinculados a tópicos do Fórum.
3. **`sinalizar_post_forum`** (bundle: `aculta_forum_post`):
   - Sinalização de publicações revisionáveis do Fórum.
   - Exige que o post pertença a um tópico publicado no purpose `forum`.

### 1.2 Fila Legada (`aculta_user\Moderation\ReportQueue`)
- Classe: `web/modules/custom/aculta_portal/modules/aculta_user/src/Moderation/ReportQueue.php`
- Serviço: `@aculta_user.report_queue`
- Comportamento: Realiza varredura em `flagging` filtrando pelas 3 flags do fórum e tipos `node`, `comment`, `aculta_forum_post`. Agrupa por ID em memória.
- Limitações identificadas na auditoria:
  - Sem validação de permissão de quem chama a fila (`getPendingItems()` sem parâmetro de autorização).
  - Sem suporte a paginação real (varre até 500 itens).
  - Sem persistência de ciclo de vida ou decisão de moderação (as flags continuavam salvas até eventual exclusão manual).

### 1.3 Painel Administrativo de Denúncias
- Rota: `/painel-administrativo/forum/denuncias` (`aculta_user.denuncias_forum`)
- Permissão: `moderar denuncias do forum`
- Controller: `ReportQueueController`
- Situação anterior: Redirecionava temporariamente (302) para `/painel-administrativo/forum`.

---

## 2. Matriz de Transição e Compatibilidade

| Requisito | Solução Operacional |
| :--- | :--- |
| **Preservação de Histórico** | Nenhuma flag é excluída. Registros históricos em `flagging` permanecem intactos. |
| **Novas Denúncias** | Bridge automático (`ForumReportBridgeHooks`): ao criar um `flagging` de denúncia do Fórum, sincroniza imediatamente com `aculta_ugc_report` vinculando `legacy_flag_id`. |
| **Fila Unificada** | `UgcReportQueue::getQueueItems()` unifica denúncias novas em `aculta_ugc_report` com sinalizações legadas da tabela `flagging` pendentes de migração. |
| **Isolamento de Escopo** | Moderador do fórum visualiza estritamente o purpose `forum` e não tem acesso a outros purposes. |
| **Privacidade** | A identidade do denunciante nunca é exibida na fila nem nos avisos por e-mail. |
| **Migração Persistente** | Mantida sob controle de `UgcReportQueue::migrateFromReportQueue()` com suporte a dry-run e rollback. Nenhuma migração persistente roda automaticamente. |
