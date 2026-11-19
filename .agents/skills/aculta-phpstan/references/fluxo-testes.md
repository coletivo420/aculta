# Fluxo de controle, nulidade e testes

Aplicar a `staticMethod.alreadyNarrowedType`, `method.alreadyNarrowedType`, `instanceof.alwaysTrue`, `nullCoalesce.offset`, `identical.alwaysFalse`, `staticMethod.impossibleType` e asserções PHPUnit suspeitas.

## Investigar antes de modificar

1. Descubra por que o PHPStan inferiu um tipo restrito: assinatura nativa, PHPDoc, branch, `assert()`, `instanceof`, mock, fixture ou extensão de análise.
2. Condição realmente impossível e sem utilidade defensiva: simplifique o fluxo, conservando um teste de comportamento significativo.
3. Condição redundante **apenas no papel**, mas necessária no Runtime por contrato externo impreciso: documente a divergência e mantenha defesa apropriada. Corrija contrato/stub quando justificado.
4. `nullCoalesce.offset` pode mostrar checagem repetida de chave obrigatória em array shape. Antes de eliminar `??`, valide todos os produtores reais.
5. `assertSame` e `assertNull` tautológicos em testes podem apontar literal, mock errado ou teste que não chamou o sistema real. Troque por asserções do resultado produzido, não por supressões.
6. Testes de Entity API precisam carregar/validar entidade do storage, não confiar em `@var` arbitrário. Mocks e data providers devem usar contratos consistentes.

## Guardas que merecem proteção especial

- `EntityStorageInterface::load()` e campos opcionais.
- Verificações de acesso, ownership, sessão, cache contexts e access results.
- Interfaces contrib cuja implementação devolve `null` ou `false` apesar de assinatura mais estreita.
- Formulários, dados de webhook, arquivos e APIs externas.

Não remover guarda por inferência sem evidência de comportamento. Para segurança e pagamentos, testes Kernel/integração podem ser necessários além de Unit.

Fontes: [PHPStan - identificadores](https://phpstan.org/error-identifiers), [PHPStan - PHPDoc](https://phpstan.org/writing-php-code/phpdoc-types), [Drupal - PHPStan](https://www.drupal.org/docs/develop/development-tools/phpstan).
