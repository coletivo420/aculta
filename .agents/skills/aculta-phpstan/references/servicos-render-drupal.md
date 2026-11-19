# Serviços, serialização, hooks e renderização Drupal

Aplicar a `dependencySerializationTraitProperty.unsupportedPrivateProperty`, `globalDrupalDependencyInjection.useDependencyInjection`, `staticMethod.internalClass` e problemas de plugins, SDC e render arrays.

## Diagnóstico e soluções

1. Leia a classe, a herança e a versão instalada. Quando `DependencySerializationTrait` é usado com serviços em propriedades privadas, confira as limitações documentadas e escolha uma forma de armazenamento/injeção compatível. Não elimine o trait apenas para apagar o alerta.
2. Em `src/`, prefira serviços injetados e a fábrica/container suportados pelo Drupal. Não troque `\\Drupal::` por outra dependência global disfarçada.
3. Em erro de API `@internal`, localize equivalente público no Drupal instalado. `Markup::create()` pode gerar `staticMethod.internalClass`; prefira Render API e formatação segura sem perder filtragem XSS ou cache metadata.
4. Em plugins UI Patterns, inspecione provider, source, definição e tipo do valor. Não converta um render array ou entidade para string sem conhecer o consumidor.
5. Em Form API, revise constructor, `create()`, serialização de dependências, validação, acesso e comportamento de modais Drupal/HTMX.
6. Teste o caminho alterado e seus consumidores, incluindo segurança, contexto multidomínio e cache quando relevantes.

## Casos da auditoria histórica

O relatório `docs/operations/testing/PHPSTAN-AUDITORIA-PRE-LEVEL10-2026-11-14.md` identificou em um SHA anterior:
- `DiscussionCommentModerationForm` e `StudioMenuStory` relacionados a `DependencySerializationTrait`.
- `ViewFieldValueSource` com símbolo de interface não encontrado.
- Scripts SDC com chamadas de API marcadas internas.
- `PagePresentationHooksTest` chamando método que não foi descoberto.

Esses achados são **históricos** e precisam ser reproduzidos no HEAD antes de corrigir.

Fontes: [Drupal - PHPStan](https://www.drupal.org/docs/develop/development-tools/phpstan), [PHPStan - identificadores](https://phpstan.org/error-identifiers), [Drupal - getting started](https://www.drupal.org/docs/develop/development-tools/phpstan/getting-started).
