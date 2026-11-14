# Símbolos, métodos e entidades Drupal

Aplicar a `method.notFound`, `class.notFound`, `property.notFound`, `function.notFound` e símbolos dinâmicos de plugins ou entidades.

## Passos de correção

1. Verifique se a classe ou método realmente existe na versão do Core/contrib de `composer.lock` e no código instalado. Confira `namespace`, `use`, módulos habilitados, autoload e API pública.
2. Identifique o tipo retornado pelo produtor. `EntityStorageInterface::load()` pode devolver `EntityInterface|null`; não afirmar `NodeInterface`, `UserInterface` ou uma classe concreta sem evidência.
3. Use a interface do Drupal apropriada e narrowing com `instanceof` antes de acessar métodos específicos. Para campos, a interface é `Drupal\Core\Entity\FieldableEntityInterface` (não `Drupal\Core\Field\FieldableEntityInterface`); para conteúdo, `ContentEntityInterface`.
4. Para classes desconhecidas, corrija Composer/autoload ou dependência real. `scanFiles`/`scanDirectories` só resolvem descoberta de símbolos **existentes** fora do autoload. Stubs corrigem PHPDocs externos, não inventam classes.
5. Se uma API dinâmica comprovada não for inferida, confira `mglaman/phpstan-drupal`. Avalie anotação legítima, stub pontual de terceiro ou extensão PHPStan, com teste que demonstra comportamento em Runtime.

Exemplo de narrowing verificável:

```php
use Drupal\Core\Entity\FieldableEntityInterface;

$entity = $storage->load($entityId);
if (!$entity instanceof FieldableEntityInterface || !$entity->hasField('field_image')) {
  return [];
}
$items = $entity->get('field_image');
```

Esse padrão não substitui verificação de acesso, cardinalidade e nulidade do valor do campo. Erros de métodos em testes podem indicar teste obsoleto que invoca código removido, não uma autorização para reintroduzir a API.

No ACULTA, conferir os padrões em `docs/portal/DRUPAL-12-STANDARDS.md`, plugins UI Patterns, módulos contrib realmente instalados e serviços em `*.services.yml`.

Fontes: [Drupal - métodos indefinidos](https://www.drupal.org/docs/develop/development-tools/phpstan/fixing-reports-of-undefined-methods), [PHPStan - descoberta de símbolos](https://phpstan.org/user-guide/discovering-symbols), [PHPStan - class.notFound](https://phpstan.org/error-identifiers/class.notFound), [PHPStan - stubs](https://phpstan.org/user-guide/stub-files).
