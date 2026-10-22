# ACULTA Content Index

Submódulo opcional do Kernel `aculta_portal`. O catálogo é derivado e poderá ser apagado e reconstruído; as entidades e permissões continuam pertencendo aos módulos de origem.

**Versão:** `0.1.0-dev.1` — IDX 0.1-B, contratos iniciais.
**Status:** Dev; schema inicial declarado, mas sem writer, consumers ou adaptadores de conteúdo e sem instalação no Runtime.
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

O registry detecta IDs duplicados e IDs inválidos durante a construção do container. Contratos de projeção, relações e persistência pertencem à IDX 0.1-C e não foram antecipados nesta fundação. O registry não concede nem verifica acesso a conteúdo.

## Ativação

O submódulo depende do módulo base `aculta_portal`, mas não é ativado por ele. Nenhum módulo de origem depende obrigatoriamente de `aculta_index`; integrações futuras devem usar dependência opcional e manter seu comportamento principal sem o índice.

Não há `hook_schema()`, atualização de banco nem alteração de Runtime nesta etapa. Desativar/remover o submódulo não afeta as entidades de origem.
