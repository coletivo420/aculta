# Triagem do PHPStan — 2026-11-07

Análise: PHPStan 2.3.5, nível 5, com `mglaman/phpstan-drupal` carregada pelo `phpstan/extension-installer`. Escopo: `web/modules/custom`, `web/themes/custom` e `scripts`, sem nenhum `ignoreErrors` (o `phpstan.neon` deixou de silenciar tudo em `f79fbe3bf9`). Total: **3.690 erros**.

Este documento classifica os erros. Não há supressão: nenhum `@phpstan-ignore` nem baseline foi adicionado. Cada grupo tem uma decisão e uma ação.

## Distribuição por pasta

| Pasta | Erros |
| --- | --- |
| `web/modules/custom/aculta_portal/modules` | 3.250 |
| `web/modules/custom/aculta_portal/src` | 154 |
| `scripts` | 161 |
| `web/themes/custom` | 19 |

## Grupos

| Grupo | Erros | Causa provável | Decisão | Ação |
| --- | ---: | --- | --- | --- |
| Entidade genérica: `EntityInterface::get/set/hasField/isPublished/getOwnerId/status/isDefaultRevision`, `ContentEntityInterface::getChangedTime` | 970 | `entityTypeManager()->load()` devolve `EntityInterface`, mas o código usa campos e métodos de `FieldableEntityInterface`. Em execução o tipo costuma ser correto, então o risco é de tipagem fraca, não de falha imediata. | **Corrigir por estreitamento de tipo** (`instanceof FieldableEntityInterface` ou tipo de retorno específico). Não silenciar. | Lotes por módulo, começando por `aculta_portal/src`. |
| Cache em `AccessResultInterface` (`getCacheContexts/getCacheTags/getCacheMaxAge/addCacheableDependency`) | 130 | O tipo declarado é a interface, que não declara esses métodos no stub usado. `AccessResult` implementa todos. | **Corrigir o tipo declarado**: usar `AccessResult` ou `RefinableCacheableDependencyInterface` onde o método é usado. | Lote separado. |
| Checagens mortas ou já estreitadas (`alwaysTrue`, `alwaysFalse`, `alreadyNarrowedType`, `nullCoalesce.offset`, `nullsafe.neverNull`, `identical.alwaysFalse`) | 770 | Código redundante ou condições que o tipo já resolve. | **Revisar caso a caso**. Remover só o que for confirmado como morto; nunca alterar comportamento. | Baixa prioridade, depois dos grupos de tipo. |
| `argument.type`, `return.type`, `property.notFound`, `method.childReturnType` | 612 | Mistura de tipagem frouxa e assinaturas divergentes entre classe e interface. | **Triar por arquivo**. Alguns são bugs reais, outros só tipo. | Média prioridade. |
| Docblock com namespace errado ou `use` ausente (`class.notFound`, `globalDrupalDependencyInjection`) | 120 | Só texto de documentação. Exemplo: `CourseJourneyComposer` cita `aculta_studio\Learning\CourseJourney` nos docblocks, mas a classe e o `use` corretos são de `aculta_learning_contracts\Learning`. Em runtime não há falha. | **Corrigir o docblock**, sem mudar código. | Corrigir nos próximos lotes; risco zero. |
| `method.notFound` residual (`Query\QueryInterface::expects`, `MessageInterface::expects`, `MockObject::expects` em testes) | ~100 | Testes que usam `MockObject` sem o `use` do PHPUnit, e interfaces de terceiros. | **Corrigir o `use`** nos testes; verificar as interfaces de terceiros caso a caso. | Junto com os docblocks. |

## Achados reais já confirmados

- Nenhum erro de runtime confirmado até aqui. Os casos examinados foram todos de tipo ou de docblock.
- `scripts/validate-aculta420-shell-contract.php:115` e `:123` comparam `null` com array ou `false` de forma sempre falsa. É código morto de validação, a revisar.
- `scripts/install-institution.php:325` compara um conjunto de valores com `contact`, que nunca está no conjunto. A comparação é sempre falsa. Confirmar a intenção antes de mudar.

## Próximos passos

1. Corrigir docblocks e `use` ausentes (grupo 5 e parte do 6). Sem mudança de comportamento.
2. Tipar `AccessResult` onde os métodos de cache são usados (grupo 2).
3. Estreitar tipos de entidade em `aculta_portal/src` (grupo 1), por arquivo, com teste unitário quando existir.
4. Revisar os dois achados de `scripts/` antes de qualquer alteração.
5. Só depois, os grupos 3 e 4, por arquivo.

Cada lote deve rodar `php -l`, o `phpcs` do arquivo alterado e o PHPStan só daquele caminho, para medir a queda sem regressão.
