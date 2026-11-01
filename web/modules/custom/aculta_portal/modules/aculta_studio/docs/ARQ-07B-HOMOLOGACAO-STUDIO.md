# ARQ-07B — Homologação da Integração de Salvos ao ACULTA Studio

## 1. Visão Geral e Objetivo
A frente **ARQ-07B** integrou a infraestrutura transversal de conteúdos salvos (`aculta_portal.saved_content_query`, implementada na frente ARQ-07) à plataforma de experiência pessoal **ACULTA Studio** (`aculta_studio`).

Esta entrega consolida a biblioteca pessoal em área autenticada do Studio, eliminando acoplamento direto com tabelas do banco de dados, assegurando isolamento estrito de conta e separando a Conta privada do Perfil público.

---

## 2. Diagnóstico Prévio vs. Solução Implementada

| Aspecto | Antes (ARQ-07B) | Depois (ARQ-07B Concluído) |
| :--- | :--- | :--- |
| **Acesso a Dados** | Consulta SQL direta à tabela `flagging` via `SavedContentService` | Delegação ao serviço transversal `SavedContentQueryInterface` (`aculta_portal.saved_content_query`) |
| **Entity Access** | Ausente ou manual por iteração | Verificação nativa antes de devolver itens (`access('view')`) |
| **Paginação** | Ausente (lista estática truncada) | Paginação real via parâmetro `page` e `limit`, compatível com query args |
| **Purposes & Bundles** | Inexistente na listagem | Identificação de propósito institucional/portal e filtro por tipo de conteúdo |
| **Flags de Moderação** | Não filtradas na camada de serviço | Bloqueadas por contrato (`report`, `flag_denuncia` etc. são excluídas) |
| **Cacheability** | Tags genéricas ou ausentes | Tags exatas de usuário, tags `flagging_list` e contextos de URL (`url.query_args:page`, `url.query_args:bundle`) |
| **Privacidade** | Risco de vazamento em listagens genéricas | Isolamento total entre contas; restrito a usuários autenticados |

---

## 3. Serviços e Controladores Refatorados

1. **`SavedContentService`** ([`SavedContentService.php`](file:///home/piradopirata/projetos/aculta/web/modules/custom/aculta_portal/modules/aculta_studio/src/Saved/SavedContentService.php))
   - Não executa queries brutas em `flagging`.
   - Implementa `SavedContentInterface`, injetando `SavedContentQueryInterface`.
   - Mantém retrocompatibilidade para o motor de recomendações (`RecommendationService`).

2. **`SavedContentController`** ([`SavedContentController.php`](file:///home/piradopirata/projetos/aculta/web/modules/custom/aculta_portal/modules/aculta_studio/src/Controller/SavedContentController.php))
   - Acesso restrito a `/studio/salvos` (`_user_is_logged_in: 'TRUE'`).
   - Paginação acessível com indicador "Página X de Y" e navegação contextual anterior/próxima.
   - Apresentação estruturada com badges temáticos de purpose (`aculta_portal.purpose_resolution`).
   - Links canônicos seguros com fallback para rotas canônicas de entidades.

3. **`aculta_studio.services.yml`** ([`aculta_studio.services.yml`](file:///home/piradopirata/projetos/aculta/web/modules/custom/aculta_portal/modules/aculta_studio/aculta_studio.services.yml))
   - Dependência limpa e explícita do serviço `@aculta_portal.saved_content_query`.

---

## 4. Evidências de Testes e Validação de Segurança

### 4.1 Testes Kernel (`SavedAndRecommendationsKernelTest`)
- **Arquivo**: [`tests/src/Kernel/SavedAndRecommendationsKernelTest.php`](file:///home/piradopirata/projetos/aculta/web/modules/custom/aculta_portal/modules/aculta_studio/tests/src/Kernel/SavedAndRecommendationsKernelTest.php)
- **Execução**:
  ```bash
  SIMPLETEST_DB="mysql://aculta_simpletest:aculta_simpletest_pass@127.0.0.1/aculta_simpletest" \
  vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  web/modules/custom/aculta_portal/modules/aculta_studio/tests/src/Kernel/SavedAndRecommendationsKernelTest.php
  ```
- **Resultado**:
  ```text
  PHPUnit 11.5.57 by Sebastian Bergmann and contributors.
  Runtime: PHP 8.4.27
  ........                                                            8 / 8 (100%)
  Time: 00:36.637, Memory: 26.00 MB
  OK, but there were issues!
  Tests: 8, Assertions: 150, Deprecations: 13.
  ```

### 4.2 Casos de Teste Cobertos
1. `testSavedContentAccessAndIsolation`: Confirma isolamento estrito entre Contas. O usuário A nunca visualiza itens salvos pelo usuário B.
2. `testAnonymousUserDeniedAccess`: Confirma que anônimos recebem `AccessDeniedHttpException` ao tentar consultar `/studio/salvos`.
3. `testSavedContentPaginationAndFilter`: Valida paginação de conteúdos salvos e filtragem correta por `bundle`.
4. `testModerationFlagsExcluded`: Garante que flags com purpose de moderação não aparecem na biblioteca pessoal do Studio.
5. `testDeletedEntityResilience`: Confirma ausência de falha e omissão correta quando uma entidade salva é removida do banco.
6. `testCacheabilityMetadata`: Valida presença de contextos `user`, `url.query_args:page`, `url.query_args:bundle` e tags de cache de flagging.
7. `testSavedContentControllerPage`: Valida estrutura de render array devolvida para montagem do tema.
8. `testRecommendationService`: Confirma que o serviço de recomendações continua funcionando sem regressões.

### 4.3 Padrões de Código e Lint
- Executado `vendor/bin/phpcs --standard=Drupal,DrupalPractice` nos arquivos modificados: **0 erros, 0 avisos**.

---

## 5. Alinhamento com a Frente Visual (Handoff Frente B)

A listagem em `SavedContentController` retorna render arrays semânticos preparados para receber os componentes SDC/Twig do tema `aculta420` ou do Studio.

Contrato de saída disponibilizado para os templates/SDCs:
- Itens estruturados com classes utilitárias acessíveis: `.aculta-saved-library`, `.aculta-saved-item`, `.aculta-saved-badge`.
- Controles de paginação nativos com links semânticos e preservação de query parameters.
- Empty states padronizados com link de incentivo à exploração do catálogo.
- Não há classes CSS conflitantes com os designs em andamento nas frentes visuais.

---

## 6. Estado do Runtime (`aculta_runtime`)
- Como o `aculta_studio` já se encontrava habilitado e os serviços utilizam injeção de dependência e tabelas existentes do core e módulo `flag`, nenhuma atualização destrutiva de banco (`updatedb`) ou importação de configuração é exigida.
- A sincronização requer apenas rebuild de contêiner e cache do Drupal (`drush cr`).
