# ACULTA Content Index

Submódulo opcional do Kernel `aculta_portal`. O catálogo é derivado e poderá ser apagado e reconstruído; as entidades e permissões continuam pertencendo aos módulos de origem.

**Versão:** `0.1.0-dev.13` — consulta paginada com cursor opaco e Entity Access.
**Status:** Dev; schema, contratos, gerações, provider Revista, descoberta keyset, checkpoint CAS, writer, revalidação de acesso, fila incremental e coordenação retomável de lotes implementados. Reconciliação, validação final/catch-up do rebuild, sincronização de relações-alvo e API completa de consulta pendentes; não instalado no Runtime.
**Arquitetura e roadmap:** [ACULTA Content Index](../../../../../../docs/portal/ACULTA-CONTENT-INDEX.md).
**Auditoria e matriz de reaproveitamento:** [IDX 0.1-A](../../../../../../docs/portal/ACULTA-CONTENT-INDEX-AUDIT-0.1-A.md).

## Provider services

Um módulo proprietário pode registrar um provider como serviço e marcá-lo com a tag `aculta_index.provider`. O provider implementa `ContentIndexProviderInterface`, declara ID estável e lista de tipos/bundles que conhece. Serviços de módulos desativados não são carregados pelo Drupal.

```yaml
services:
  aculta_example.content_index_provider:
    class: Drupal\aculta_example\Index\ExampleContentIndexProvider
    tags:
      - { name: aculta_index.provider }
```

O registry detecta IDs duplicados, IDs inválidos e declarações malformadas de entidade/bundle durante a construção do container. Os DTOs neutros `ContentIndexProjection` e `ContentIndexRelation` e o contrato `ContentIndexProviderInterface` vivem no namespace base do Portal, portanto podem ser implementados sem dependência do submódulo. O registry não concede nem verifica acesso a conteúdo.

## Ativação

O submódulo depende do módulo base `aculta_portal`, mas não é ativado por ele. Nenhum módulo de origem depende obrigatoriamente de `aculta_index`; integrações futuras devem usar dependência opcional e manter seu comportamento principal sem o índice.

Na instalação, `hook_schema()` cria as tabelas derivadas e `hook_install()` cria o singleton com geração ativa vazia. Nenhum conteúdo é indexado ou exposto até uma futura reconstrução validada. Desativar/remover o submódulo não afeta as entidades de origem.

`ContentIndexGenerationManager` cria gerações `building`, permite ativar somente estados validados e mantém a geração anterior como `retained` para rollback. A troca ocorre em transação com bloqueio da linha de estado e comparação de revisão do ponteiro. O manager não grava projeções nem declara uma geração validada por conta própria: o rebuild deve executar e conferir o backfill antes de chamar `markValidated()`.
