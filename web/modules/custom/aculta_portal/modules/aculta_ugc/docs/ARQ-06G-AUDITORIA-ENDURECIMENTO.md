# ARQ-06G — Auditoria de Endurecimento da Moderação Fórum ↔ UGC

**Data:** 2026-10-29  
**Frente:** ARQ-06G (Endurecimento e Fechamento da Integração Fórum ↔ UGC)  
**Branch:** `refactor/opencultures-codigo-visual` (PR #299)  
**Status:** Em execução

---

## 1. Escopo e Objetivos da Auditoria

A frente ARQ-06F entregou a ponte funcional de moderação entre o Fórum e a infraestrutura transversal UGC. Esta auditoria identifica os pontos de endurecimento necessários para segurança em produção, prevenção de regressões, controle de acesso estrito e escalabilidade da fila.

---

## 2. Matriz de Riscos Identificados e Ações Corretivas

| # | Componente | Risco Identificado | Severidade | Ação Corretiva na ARQ-06G |
|---|------------|--------------------|------------|---------------------------|
| **R1** | `ForumReportUgcBridgeHooks` | Gravação direta de `aculta_ugc_report` contornando verificação de Entity Access da conta denunciante. | **Alta** | Validar `$target->access('view', $account)` e rejeitar criação de denúncia se a conta não tem permissão para visualizar o conteúdo denunciado. |
| **R2** | `ForumReportUgcBridgeHooks` | Inexistência de lock contra concorrência e ausência de verificação do limite diário de denúncias por conta. | **Média** | Reutilizar lock backend (`aculta_ugc:report:...`) e checar cota diária (`ReportService::DAILY_LIMIT`) antes de gravar. |
| **R3** | `ForumReportUgcBridgeHooks` | Exclusão de flagging (`flagging_delete`) removendo denúncia que já entrou em análise ou foi decidida. | **Alta** | Garantir que apenas denúncias no status `pendente` com `legacy_flag_id` correspondente sejam limpas; denúncias em `em_analise`, `procedente` ou `improcedente` permanecem preservadas para integridade da auditoria. |
| **R4** | `UgcReportQueue` | Reconciliação de flags históricas reincorporando como pendentes sinalizações cujas denúncias UGC já foram decididas (`procedente`/`improcedente`). | **Crítica** | Consultar todos os `legacy_flag_id` da base UGC independente de status e checar status da entidade antes de reinserir flags históricas na listagem pendente. |
| **R5** | `ReportQueueController` | Exibição de título, label e link canônico de alvos restritos ou privados para moderadores que não têm acesso ao conteúdo. | **Alta** | Implementar verificação estrita de `access('view', $moderator)`: exibir texto neutro sanitizado `[Conteúdo restrito]` sem hyperlink quando o moderador não puder ver o alvo. |
| **R6** | `ReportQueueController` | Moderador de outros purposes (ex.: Revista, Wiki) acessando indevidamente a fila do Fórum. | **Alta** | Exigir explicitamente permissão de moderação no purpose `forum` (`moderar denuncias do forum` ou `moderar denuncias ugc` global); outros moderadores recebem HTTP 403. |
| **R7** | `UgcReportQueue` | Carregamento integral em memória de todos os relatórios (`loadMultiple`) e queries N+1 ao carregar alvos para paginação. | **Alta** | Implementar agregação SQL com índices, agrupamento determinístico e paginação direta no banco, carregando apenas os alvos da página atual em lote. |

---

## 3. Auditoria do Runtime (`aculta_runtime`)

- **Consulta somente leitura:** Confirmada ausência de flaggings na base `aculta_runtime` (`Total: 0`).
- **Impacto:** Nenhuma migração persistente imediata necessária; infraestrutura pronta para absorver novas denúncias e dados históricos de importações futuras de modo 100% idempotente.
