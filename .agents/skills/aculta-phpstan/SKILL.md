---
name: aculta-phpstan
description: Diagnostica e corrige erros PHPStan em PHP/Drupal do ACULTA, incluindo tipos, entidades, testes e dívida histórica. Use em erro phpstan, ajuste de tipos ou migração de nível.
---

# PHPStan no ACULTA

Skill canônica para corrigir erros de análise estática sem enfraquecer contratos funcionais. Carregue **somente** a referência da família do erro, mais [baseline e CI](references/baseline-ci.md) quando houver supressões ou gates. Complementa `aculta-drupal-padroes`, `aculta-gates-por-escopo` e `aculta-seguranca-estatica`; não substitui seus contratos.

## Sequência de diagnóstico

1. Confira `phpstan.neon`, `composer.lock`, SHA, versão de PHP e versão real do PHPStan. O nível oficial é **10** e a dívida histórica está congelada em `phpstan-baseline.neon`; reduzir o nível ou afrouxar `reportUnmatchedIgnoredErrors` reprova o gate.
2. Confirme `vendor/bin/phpstan`, Composer e a extensão `mglaman/phpstan-drupal`. Sem dependências, registre **DEFERRED**, não PASS. Não execute `composer update`.
3. Leia caminho, linha, mensagem, **identificador**, tipo inferido e contrato real do produtor e consumidor. Diferencie erro novo, histórico, ambiental e falso positivo demonstrado.
4. Leia fonte e testes antes de alterar. Prefira corrigir na origem e fazer narrowing real no consumidor; não invente APIs.
5. Execute PHP Lint e testes focais. Análise limitada a um arquivo é diagnóstico, não gate global: pode provocar `ignore.unmatched` por escopo reduzido.
6. Reexecute o comando global e reporte SHA, nível, códigos de saída, erros corrigidos/novos e PASS/FAIL/DEFERRED. Jobs `skipped` ou `cancelled` não são PASS.

Comando canônico na raiz com `vendor/` disponível:

```bash
vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress --memory-limit=2G
```

Para o JSON canônico e os gates, use os comandos de [Baseline e gates](references/baseline-ci.md). O comando deve rodar fora de `set -e` ou tratar o código de saída explicitamente. Não use `|| true`, `--fix` nem `--generate-baseline` para fazer o gate parecer verde.

## Nível 10 e dívida congelada

O baseline canônico é `phpstan-baseline.neon` (nível 10). O gate `scripts/phpstan-debt-guard.php` bloqueia entrada nova, contagem maior, identidade alterada e `@phpstan-ignore` novo no diff. O comando `diff` exige que todo arquivo alterado esteja sem dívida e que cada módulo tocado reduza ao menos uma ocorrência.

Erro novo **nunca** pode ser ignorado: só a dívida já congelada permanece suprimida, pelo baseline. Um erro pré-existente no arquivo alterado também é responsabilidade da tarefa. Comandos, fixtures, testes (`bats tests/shell/phpstan-debt-guard.bats`) e recuperação em [Baseline e gates](references/baseline-ci.md). Snapshot e checksum: `docs/operations/testing/phpstan-baseline-freeze.json`.

## Referências por família de erro

| Identificadores ou sintomas | Referência |
| --- | --- |
| `method.notFound`, `class.notFound`, `property.notFound`, entidades e plugins | [Símbolos e entidades Drupal](references/simbolos-entidades-drupal.md) |
| `argument.type`, `return.type`, `missingType.*`, `mixed`, arrays, generics | [Tipos e contratos](references/tipos-contratos-mixed.md) |
| `staticMethod.alreadyNarrowedType`, `instanceof.alwaysTrue`, `nullCoalesce.offset`, PHPUnit | [Fluxo e testes](references/fluxo-testes.md) |
| `dependencySerializationTraitProperty.*`, `globalDrupalDependencyInjection.*`, `staticMethod.internalClass` | [Serviços e renderização](references/servicos-render-drupal.md) |
| `ignore.unmatched`, `ignore.count`, CI, baseline, regras do nível 10, dívida tocada | [Baseline e gates](references/baseline-ci.md) |

Escolha a causa raiz e consulte referências extras apenas se necessário. Use a [lista oficial de identificadores](https://phpstan.org/error-identifiers) para confirmar o significado de cada erro.

## Critérios de correção

- Tipos nativos, PHPDoc e contratos devem refletir o **Runtime**, inclusive retornos `null`, `false` e coleções vazias.
- Prefira narrowing real, tipos no produtor e validação nos limites de confiança. Não espalhe `@var` fictício ou casts para calar o analisador.
- Não remova guardas defensivas reais em autorização, pagamento, campos opcionais ou integrações só porque um PHPDoc externo afirma retorno não nulo.
- Stubs de terceiro exigem evidência de PHPDoc externo incorreto; descoberta de símbolo e stub são problemas distintos.
- `@phpstan-ignore` exige identificador, escopo mínimo, motivo e revisão; não adicionar ignores globais, reduzir nível ou aumentar dívida congelada.
- Erro resolvido não significa comportamento aprovado: teste o escopo e os consumidores afetados.

Metodologia própria sintetizada a partir de fontes externas, com atribuição e links em [fontes e licenças](references/fontes-e-atribuicao.md); não inclui transcrição nem instalação das skills de terceiros.
