# ARQ-06H — Auditoria de Consistência Final, Isolamento e Paginação UGC

**Data:** 2026-10-29  
**Frente:** ARQ-06H (Consistência Final, Isolamento e Paginação da Moderação UGC)  
**Branch:** `refactor/opencultures-codigo-visual` (PR #299)  
**Status:** Em execução

---

## 1. Escopo e Objetivos da Revisão Técnica Final

A frente ARQ-06G endureceu a segurança da ponte de flags e o painel de moderação. Esta etapa (ARQ-06H) soluciona as inconsistências residuais em:
1. **Identidade composta de agrupamento por propósito:** Substituir chaves planas `target_type:target_id` por chaves tridimensionais `target_type:target_id:purpose` em todo o ciclo de vida da fila.
2. **Isolamento de motivos por propósito e status:** Garantir que o enriquecimento de motivos respeite estritamente `(target_type, target_id, purpose, status)`.
3. **Reconciliação e bloqueio de flags inválidas:** Impedir que sinalizações rejeitadas pela ponte contornem as regras de acesso ou limites diários ao serem lidas da tabela `flagging`.
4. **Paginação e integridade de contagens:** Garantir determinismo e páginas completas, documentando limitações e ganhos de escalabilidade.
5. **Segurança operacional:** Proteger `getPendingItems()` com verificação de escopo de moderador.

---

## 2. Inventário de Consumidores de `getPendingItems()`

- Pesquisa no código: `getPendingItems()` foi introduzido como método utilitário de retrocompatibilidade em `UgcReportQueue.php`.
- Consumidores externos: Nenhum módulo externo consome `getPendingItems()` diretamente; os controladores utilizam `getQueueItems()`.
- Endurecimento: Adicionar verificação de autorização de escopo em `getPendingItems()` para proteger contra chamadas indevidas.
