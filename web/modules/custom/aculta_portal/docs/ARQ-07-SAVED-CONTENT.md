# ARQ-07 — Salvos e Biblioteca Pessoal Transversal

## Visão Geral

A infraestrutura transversal de conteúdos salvos e seguidos do ACULTA (`ARQ-07`) fornece um contrato universal e desacoplado para consultar itens salvos ou seguidos pelo usuário atual, reutilizando diretamente o ecossistema nativo do módulo **Flag** sem criar tabelas paralelas, bancos duplicados ou contornar as permissões de acesso do Drupal.

O **ACULTA Studio** e o tema atuam como consumidores deste serviço (através de blocos, endpoints ou controladores de Minha Biblioteca / Salvos), enquanto o **ACULTA Portal** provê a infraestrutura transversal de consulta, cache e segurança.

## Serviços e Contratos

- **Serviço Drupal:** `aculta_portal.saved_content_query`
- **Interface:** `Drupal\aculta_portal\Saved\SavedContentQueryInterface`
- **Implementação:** `Drupal\aculta_portal\Saved\SavedContentQuery`

### DTOs Imutáveis

1. **`SavedContentFilter`**:
   - Critérios de busca imutáveis: `$account` (obrigatório), `$category` (`saved`, `following` ou `all`), `$entityTypeId`, `$bundle`, `$purpose`, `$limit` (1-100), `$offset`.
2. **`SavedContentItem`**:
   - Item autorizado com acesso validado: `$entityTypeId`, `$entityId`, `$entity`, `$flagId`, `$createdTime`, `$category`, `$purpose`, `$label` e resolução de URL segura.
3. **`SavedContentQueryResult`**:
   - Resultado paginado com suporte nativo a `CacheableDependencyInterface`: `$items`, `$totalCount`, `$hasMore`, cache tags (`flagging_list`, `user:UID`, tags das entidades) e contexts (`user`, `user.permissions`).

## Garantias de Segurança e Privacidade

1. **Isolamento Estrito entre Contas:**
   - Consultas são vinculadas exclusivamente ao UID do usuário autenticado. Nenhuma conta visualiza ou contabiliza itens salvos por terceiros.
2. **Entity Access Mandatório:**
   - Cada entidade candidata é carregada e submetida ao método `$entity->access('view', $account)`. Conteúdos despublicados, privados ou pertencentes a áreas restritas nunca vazam em listagens ou contagens.
3. **Resiliência a Exclusões:**
   - Quando uma entidade é removida fisicamente do banco de dados enquanto o registro na tabela `flagging` persiste, a query ignora graciosamente o registro órfão sem disparar exceções.
4. **Exclusão de Flags de Moderação e Sistema:**
   - Flags com prefixo `sinalizar_`, `abuse`, `claim_` ou identificadas em `EXCLUDED_FLAGS` são estritamente excluídas do escopo pessoal.
