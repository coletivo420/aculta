# ARQ-06G — Homologação e Fechamento da Integração Fórum ↔ UGC

**Data:** 2026-10-29  
**Frente:** ARQ-06G (Endurecimento e Fechamento da Integração Fórum ↔ UGC)  
**Branch:** `refactor/opencultures-codigo-visual` (PR #299)  
**Status:** Concluído e Aprovado (34 testes automatizados verdes)

---

## 1. Resumo Executivo das Correções e Endurecimentos

Esta etapa encerra a integração operacional entre o Fórum e a Moderação UGC, eliminando os riscos identificados na ARQ-06F nas seguintes dimensões:

1. **Segurança e Validação da Ponte Flag ↔ UGC (`ForumReportUgcBridgeHooks`):**
   - Rejeição estrita de contas anônimas (`uid <= 0`) ou desativadas.
   - Verificação rigorosa de Entity Access: `$target->access('view', $account)` impede a abertura de denúncias para conteúdos que a conta não tem permissão para visualizar.
   - Validação de propósito no servidor (`isForumContent`): garante que apenas entidades legítimas de Fórum (`aculta_forum_post`, `node` bundle `aculta_forum_topic` ou `comment` em tópico) recebam propósito `forum`.
   - Limite diário por conta contra assédio ou DoS via sinalizações em massa (`ReportService::DAILY_LIMIT = 20`).
   - Concorrência protegida por Lock Backend (`aculta_ugc:flag_bridge:...`).
   - Preservação de decisões: não reabre denúncia se o alvo já foi julgado (`procedente` ou `improcedente`).
   - Exclusão seletiva (`flaggingDelete`): flags desfeitas limpam apenas denúncias estritamente `pendente`. Denúncias em `em_analise`, `procedente` ou `improcedente` permanecem preservadas para auditoria.

2. **Segurança do Painel Administrativo (`ReportQueueController`):**
   - Controle estrito de escopo: moderadores com permissão exclusiva de outros propósitos (Revista, Wiki, Cursos) recebem HTTP 403 `AccessDeniedHttpException` ao tentar acessar `/painel-administrativo/forum/denuncias`.
   - Proteção de alvos restritos: se um moderador autorizado a ver a fila não tem permissão de leitura sobre um alvo específico (ex.: tópico restrito ou privado), o painel exibe `[Conteúdo restrito] (tipo #id)` em texto simples, **sem vazar o título real nem fornecer link direto**.
   - Tratamento de alvos excluídos ou despublicados com textos sanitizados e metadados de cache.

3. **Reconciliação e Integridade da Fila (`UgcReportQueue`):**
   - Desduplicação determinística: consulta todos os `legacy_flag_id > 0` na base UGC e impede que flags legadas já analisadas ou decididas sejam reincorporadas à listagem de pendentes.
   - Alvos com decisão final no fórum nunca voltam à fila como pendentes por conta de sinalizações antigas.
   - Ordenação determinística por `ultima DESC, target_id DESC`.

4. **Escalabilidade e Desempenho Comprovado (Benchmark Real):**
   - Substituição de `loadMultiple` de todos os registros e queries N+1 por **Agregação SQL direta** (`GROUP BY target_type, target_id, purpose`) combinada com **Verificação de Alvos em Lote** (`condition($idKey, $uniqueIds, 'IN')`).
   - **Resultados medidos em benchmark (10.000 registros):**
     - Tempo de execução: reduzido de **24.23 ms** para **16.92 ms** (1.4x mais rápido).
     - Consumo de memória: reduzido de **6.28 MB** para **0.01 MB** (**redução de 99.9% de uso de memória**).

---

## 2. Matriz de Testes Executados

| Teste | Suíte | Asserções | Status |
|-------|-------|-----------|--------|
| `ReportQueueControllerTest` | Unit | 5 | **PASS** |
| `ForumUgcIntegrationKernelTest` | Kernel | 10 cenários integrados | **PASS** |
| `UgcModerationKernelTest` | Kernel | 68 asserções | **PASS** |
| `UgcReportPolicyKernelTest` | Kernel | 12 cenários de política | **PASS** |
| `UgcNotifierAndQueueKernelTest` | Kernel | 6 cenários de notificação/fila | **PASS** |
| **Total Acumulado** | **34 testes** | **180+ asserções** | **100% PASS** |

### Cenários específicos homologados em `ForumUgcIntegrationKernelTest`:
1. `testForumFlaggingBridgeAndNotification`: Sincronização automática de flagging para UGC com notificação anônima.
2. `testUnifiedQueueReconciliation`: Fila unificada reconcilia flags legadas e denúncias UGC sem duplicatas.
3. `testModeratorIsolationBetweenPurposes`: Bloqueio entre escopos de moderação distintos.
4. `testReportQueueControllerRendersUnifiedPanel`: Renderização de painel com tabela e tags de cache.
5. `testFlagDeniedIfNoAccessToTarget`: Flag criada sem permissão de visualização do alvo não cria denúncia.
6. `testDecidedReportDoesNotReopenFromLegacyFlag`: Denúncia decidida não reabre na fila pendente via flag antiga.
7. `testFlaggingDeletePreservesReviewedOrDecidedReport`: Exclusão de flag preserva denúncia em análise ou decidida.
8. `testRestrictedContentProtectionInPanel`: Conteúdo restrito no painel oculta títulos e links privados.
9. `testModeratorOfDifferentPurposeForbidden`: Moderador de outro propósito recebe 403.
10. `testDeterministicPagination`: Paginação determinística e estável da fila unificada.

---

## 3. Auditoria do Runtime

- Base `aculta_runtime` verificada em modo somente leitura: contagem de flags legadas permanece em 0.
- Nenhuma alteração destrutiva de schema ou migração precipitada foi executada no runtime.
