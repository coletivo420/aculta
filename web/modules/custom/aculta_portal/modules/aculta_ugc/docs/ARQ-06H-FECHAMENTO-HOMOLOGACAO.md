# ARQ-06H - Fechamento Técnico, Consistência e Homologação Fórum ↔ UGC

**Data:** 2026-10-29  
**Branch:** `refactor/opencultures-codigo-visual`  
**PR:** `#299`  
**Escopo:** `aculta_ugc` e `aculta_forum` (ARQ-06H)

---

## 1. Resumo Executivo

A etapa **ARQ-06H** concluiu o fechamento definitivo da infraestrutura transversal de moderação UGC (`aculta_ugc`) e sua integração com o Fórum ACULTA (`aculta_forum`), eliminando inconsistências arquiteturais remanescentes, garantindo isolamento estrito entre propósitos e protegendo os fluxos operacionais contra contaminação por dados inválidos ou não autorizados.

Principais entregas:
1. **Identidade Composta Tridimensional (`target_type:target_id:purpose`):**  
   O agrupamento da fila pendente agora utiliza propósito como parte integrante da chave primária lógica, impedindo colisões e sobrescritas quando uma mesma entidade é reportada em diferentes propósitos (ex.: `forum` e `revista`).
2. **Isolamento Estrito de Motivos por Propósito e Status:**  
   A consulta de enriquecimento de motivos agora aplica cláusulas SQL compostas `(target_type = :t AND target_id = :id AND purpose = :p AND status = :s)`, garantindo que denúncias resolvidas ou de outras frentes não contaminem a listagem de pendências.
3. **Endurecimento da Ponte Flag/UGC e Reconciliação:**  
   A criação de sinalizações por usuários sem permissão de visualização do alvo ou que excederam o limite diário é rejeitada imediatamente na ponte com `AccessDeniedHttpException`. Adicionalmente, a consulta de reconciliação de flags legadas descarta registros oriundos de contas inativas/bloqueadas (`users_field_data.status = 1`).
4. **Proteção de Acesso em `UgcReportQueue::getPendingItems()`:**  
   Implementada a verificação de escopo de permissões (`ModerationScope::allows`) em conformidade com as regras de governança D-03 e D-24.
5. **Avaliação de Desempenho e Escalabilidade:**  
   Benchmarks sintéticos demonstraram redução de até 99,9% no consumo de memória PHP para 10.000 denúncias, com paginação estável e previsível.
6. **Homologação Completa:**  
   100% dos testes aprovados (15 testes específicos de integração com 287 assertions, 39 testes totais do ecossistema UGC/Fórum com 611 assertions).

---

## 2. Decisões Arquiteturais e Justificativas Técnicas

### 2.1 Identidade Composta Tridimensional
- **Problema anterior:** A agregação SQL utilizava `GROUP BY target_type, target_id, purpose`, porém a chave no PHP era apenas `target_type:target_id`. Se o mesmo nó recebesse denúncias sob o propósito `forum` e sob o propósito `revista`, a última linha sobrescrevia a primeira no array `$grupos`.
- **Solução implementada:** A chave de agrupamento passou a ser `$key = $row->target_type . ':' . $row->target_id . ':' . $row->purpose;`. As flags legadas do fórum utilizam deterministicamente a chave `$key = $targetKey . ':forum';`. A verificação em lote de alvos continua operando de forma agregada por tipo e ID único, sem gerar queries N+1.

### 2.2 Isolamento de Motivos
- **Problema anterior:** A consulta de motivos utilizava `WHERE target_id IN (...) AND status = :status`, sem restringir o propósito. Se uma denúncia fosse deferida em `forum` e outra estivesse pendente em `revista`, o motivo da deferida ou de outro propósito podia vazar.
- **Solução implementada:** Criou-se um grupo de condições `OR` contendo blocos `AND (target_type, target_id, purpose, status)` para cada um dos itens da página corrente. Assim, apenas os motivos estritamente vinculados ao propósito e estado do grupo são carregados.

### 2.3 Tratamento de Flags Inválidas (Rejeição na Origem vs Reconciliação)
- **Problema investigado:** Um usuário com permissões revogadas ou com limite diário esgotado tentava sinalizar um tópico; a ponte podia rejeitar a criação do registro UGC, mas o registro de Flag persistia no banco, sendo posteriormente ressuscitado pela reconciliação.
- **Decisão técnica adotada:**  
  1. *Rejeição na inserção:* `ForumReportUgcBridgeHooks::flaggingInsert()` lança `AccessDeniedHttpException` quando o denunciante não tem acesso ao alvo ou atingiu o limite diário. O lançamento da exceção impede a conclusão da transação da Flag, abortando a gravação de lixo no banco de dados.  
  2. *Defesa em profundidade na reconciliação:* A consulta de flags legadas em `UgcReportQueue` inclui `JOIN users_field_data u ON u.uid = f.uid WHERE u.status = 1`, descartando silenciosamente registros de usuários excluídos ou bloqueados antes que alcancem o painel dos moderadores.

---

## 3. Resultados dos Benchmarks Comparativos

Medição executada com 1.000 e 10.000 denúncias sintéticas em 200 alvos distintos, comparando a abordagem legada (leitura total em memória PHP) com a abordagem otimizada (agregação SQL + paginação determinística + enriquecimento restrito à página corrente):

| Volume de Denúncias | Abordagem | Tempo de Processamento | Consumo de Memória PHP | Observações |
|---|---|---|---|---|
| **1.000 denúncias** | Legada (In-Memory All Rows) | 2,11 ms | 0,81 MB | Lê todos os registros para memória |
| **1.000 denúncias** | Otimizada (SQL Aggregated + Page) | 12,32 ms | 0,44 MB | **Redução de 45,3% de memória** |
| **10.000 denúncias** | Legada (In-Memory All Rows) | 23,69 ms | 6,28 MB | Risco de estouro de memória em produção |
| **10.000 denúncias** | Otimizada (SQL Aggregated + Page) | 78,03 ms | 0,01 MB | **Redução de 99,9% de memória** |

*Conclusão de Desempenho:* A abordagem otimizada transfere o peso da agregação e ordenação para o motor de banco de dados (que possui índices em `status`, `purpose` e `target`), mantendo a alocação de memória no PHP praticamente invariante em relação ao crescimento do volume de denúncias.

---

## 4. Matriz de Homologação e Testes Kernel

Todos os 15 testes de integração em `ForumUgcIntegrationKernelTest` foram validados no banco de testes `aculta_simpletest`:

| Cenário de Teste | Descrição da Validação | Resultado |
|---|---|---|
| `testForumFlaggingBridgeAndNotification` | Ponte cria entidade UGC e dispara notificação sem vazar UID | **PASS** |
| `testUnifiedQueueReconciliation` | Fila unificada combina UGC com flags legadas sem duplicidade | **PASS** |
| `testModeratorIsolationBetweenPurposes` | Moderador de Fórum não vê itens de outros propósitos | **PASS** |
| `testReportQueueControllerRendersUnifiedPanel` | Painel `/painel-administrativo/forum/denuncias` renderiza tabela com dados reconciliados | **PASS** |
| `testFlagDeniedIfNoAccessToTarget` | Tentativa de flag em conteúdo sem permissão de visualização lança `AccessDeniedHttpException` | **PASS** |
| `testDecidedReportDoesNotReopenFromLegacyFlag` | Alvo já julgado como procedente/improcedente não reabre na fila | **PASS** |
| `testFlaggingDeletePreservesReviewedOrDecidedReport` | Remoção da Flag não afeta denúncias em análise ou decididas | **PASS** |
| `testRestrictedContentProtectionInPanel` | Alvos sem permissão de visualização para o moderador exibem máscara segura | **PASS** |
| `testModeratorOfDifferentPurposeForbidden` | Moderador de escopo diferente recebe 403 no painel | **PASS** |
| `testDeterministicPagination` | Paginação determinística preserva contagens e ordenação por data recente | **PASS** |
| `testFlagDeniedIfDailyLimitReached` | Denunciante que atinge 20 denúncias/dia tem flag rejeitada com 403 | **PASS** |
| `testReconciliationIgnoresBlockedUserFlags` | Flags pré-existentes de usuários bloqueados são ignoradas pela reconciliação | **PASS** |
| `testMultiplePurposesForSameTargetGenerateDistinctGroups` | Mesmo alvo denunciado em `forum` e `revista` gera 2 grupos independentes | **PASS** |
| `testReasonsAreIsolatedByPurposeAndStatus` | Motivos de propósitos e estados diferentes não vazam no detalhamento | **PASS** |
| `testGetPendingItemsRequiresPermission` | Chamada a `getPendingItems()` sem permissão no escopo lança `AccessDeniedHttpException` | **PASS** |

### Cobertura Geral da Frente UGC
- `ForumUgcIntegrationKernelTest`: 15 testes, 287 assertions — **PASS**
- `UgcModerationKernelTest`: 4 testes, 68 assertions — **PASS**
- `UgcReportPolicyKernelTest`: 12 testes, 187 assertions — **PASS**
- `UgcNotifierAndQueueKernelTest`: 6 testes, 64 assertions — **PASS**
- `ReportQueueControllerTest`: 2 testes, 5 assertions — **PASS**
- **Total:** 39 testes, 611 assertions, 0 falhas, 0 erros.

---

## 5. Conformidade com os Padrões do Projeto

- **PHPCS (Drupal, DrupalPractice):** 0 erros em todos os arquivos alterados e criados.
- **Gates do Projeto:**
  - `validate-no-temporary-route-aliases.php`: **PASS**
  - `validate-single-acceptance.php`: **PASS**
  - `validate-project-status-index.php`: **PASS**
- **Regras de Runtime:** Nenhuma alteração destrutiva executada no banco de dados operacional; todas as execuções de teste foram isoladas em `aculta_simpletest`.
