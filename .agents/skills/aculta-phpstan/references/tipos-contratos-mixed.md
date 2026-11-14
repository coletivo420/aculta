# Tipos, contratos, arrays e mixed

Aplicar a `argument.type`, `return.type`, `missingType.*`, `nullsafe.neverNull`, unions, arrays imprecisos e erros novos dos níveis 6 a 10.

## Ordem de diagnóstico

1. Leia assinatura nativa, PHPDoc, produtor e consumidores. Diferencie um **contrato errado** de uma **prova estática insuficiente**.
2. Declare tipos nativos quando possíveis e PHPDoc preciso onde necessário: `list<T>`, `array<TKey, TValue>`, `array{key: type, optional?: type}` e `class-string<T>`.
3. Valide `mixed` em fronteiras externas (Form API, JSON, webhook, banco, API, campo dinâmico) antes de ler offsets, iterar, chamar métodos ou repassar dados a funções restritas.
4. Corrija o tipo na origem (factory, reader, serviço, DTO) se vários consumidores repetem `@var`. Evite casts ou anotação que apenas mude o diagnóstico sem provar o comportamento.
5. Preserve `null`, `false`, coleções vazias e exceções reais. Não afrouxe tipos por conveniência e não declare tipo estrito sem validação.
6. Use `@template`, `class-string<T>`, shapes e DTOs quando tornam a API mais verificável. Não crie estrutura genérica complexa sem consumidor real.

Exemplo de array shape com contrato condicionado à validação de entrada:

```php
/**
 * @param array{id: int, title: non-empty-string, tags?: list<string>} $item
 */
function formatItem(array $item): string {
  return $item['title'];
}
```

A shape só é válida se a origem garante as chaves e tipos. Dados não confiáveis exigem verificação anterior. Um helper de validação pode declarar `@phpstan-assert` quando a implementação realmente valida.

## Nível 10

Os níveis são cumulativos. O nível 6 detecta falta de tipos, o 7 endurece operações sobre unions, o 8 cobra nulidade, o 9 restringe `mixed` explícito e o 10 estende o rigor ao `mixed` implícito. Não use `mixed` explícito como falsa correção; ele pode ser legítimo somente onde o contrato efetivamente aceita valores heterogêneos.

Teste entradas inválidas e efeitos observáveis nos módulos sensíveis a permissões, comércio, Mercado Pago e dados.

Fontes: [PHPStan - rule levels](https://phpstan.org/user-guide/rule-levels), [PHPDoc types](https://phpstan.org/writing-php-code/phpdoc-types), [argument.type](https://phpstan.org/error-identifiers/argument.type).
