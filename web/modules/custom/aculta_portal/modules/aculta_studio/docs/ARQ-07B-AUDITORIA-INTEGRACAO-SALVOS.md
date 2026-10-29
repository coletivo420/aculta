# ARQ-07B — Auditoria de Integração de Salvos ao ACULTA Studio

## 1. Contexto e Objetivos

Conectar a infraestrutura transversal de conteúdos salvos e seguidos (`aculta_portal.saved_content_query`, implementada em ARQ-07) à plataforma de experiências pessoais do **ACULTA Studio**, eliminando consultas diretas à tabela `flagging` e provendo suporte completo a paginação, filtros, isolamento de contas e cacheability.

## 2. Inventário dos Consumidores no ACULTA Studio

| Componente | Localização | Papel Atual | Ponto de Refatoração ARQ-07B |
| :--- | :--- | :--- | :--- |
| **`SavedContentService`** | `src/Saved/SavedContentService.php` | Executava queries diretas na tabela `flagging`. | Refatorar para delegar integralmente a `SavedContentQueryInterface`. Eliminar queries SQL diretas. |
| **`SavedContentController`** | `src/Controller/SavedContentController.php` | Renderizava `/studio/salvos` com limite rígido (30 itens), sem paginação por URL nem filtros. | Integrar paginação real (`page`, `limit`), filtros opcionais (`purpose`, `bundle`) e propagação de cache metadata. |
| **`SavedExperienceProvider`** | `src/Experience/SavedExperienceProvider.php` | Expõe o card/link de "Meus Salvos" no Hub geral do Studio (`/studio`). | Preservar; consome contagens através de `SavedContentService`/`SavedContentQueryInterface`. |
| **`RecommendationService`** | `src/Recommendation/RecommendationService.php` | Gera recomendações baseadas no histórico de salvos/seguidos. | Mantido intacto; consome `SavedContentService::getSavedItems()`. |
| **Rota `/studio/salvos`** | `aculta_studio.routing.yml` | Rota autenticada sob purpose `account`. | Preservada. Não conflita com `/conta-legada` nem com Perfil público. |

## 3. Matriz de Implementação vs. Pendências

| Requisito ARQ-07B | Estado Anterior | Implementação ARQ-07B | Pendência / Responsável |
| :--- | :--- | :--- | :--- |
| **Eliminação de SQL direto** | Violação (SQL direto em `flagging`) | `SavedContentService` delega a `SavedContentQueryInterface`. | Concluído na ARQ-07B. |
| **Paginação real** | Ausente (hardcoded 30 itens) | Paginação baseada em `page` e `limit`, com links acessíveis e resumo. | Concluído na ARQ-07B. |
| **Filtros por Purpose/Bundle** | Inexistente no controller | Suporte a `purpose` e `bundle` via `SavedContentFilter`. | Concluído na ARQ-07B. |
| **Isolamento de Contas** | Parcial via UID | Garantido por `SavedContentQuery` (Entity Access + UID). | Concluído na ARQ-07B. |
| **Separação Conta x Perfil** | Respeitado no Studio | Mantido estritamente em `/studio/salvos` (privado da Conta). | Concluído na ARQ-07B. |
| **Resiliência a Órfãos** | Parcial | Tratado no serviço transversal (entidades deletadas ignoradas). | Concluído na ARQ-07B. |
| **Design System / SDCs** | Render array com classes padrão | Render array estruturado com `studio-card-compact` e classes nativas. | Handoff visual para Frente B / Tema. |
