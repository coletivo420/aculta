# Baseline, dívida histórica e gates

A fonte de verdade do nível, caminhos, extensões e ignores é `phpstan.neon`. Relatórios históricos não substituem reescaneamento no SHA atual.

## Regras

1. O baseline canônico `phpstan-baseline.neon` registra dívida conhecida. `reportUnmatchedIgnoredErrors: true` pode reprovar a análise quando uma supressão se torna obsoleta ou sua contagem não corresponde.
2. `[OK] No errors` com baseline é **zero erros não suprimidos**, não dívida zero. Distinga erros brutos, ocorrências baselined e novos diagnósticos.
3. Não regenerar baseline por rotina, não criar `ignoreErrors` genérico, não baixar nível nem neutralizar o CI com `continue-on-error` ou `|| true`.
4. Para reduzir dívida, registre caminho, identificador, mensagem, quantidade antes/depois, testes e SHA. Atualize apenas entradas realmente resolvidas e revise o diff. **Erro novo não pode ser ignorado**: `@phpstan-ignore*` adicionado no diff é bloqueante; só a dívida já congelada pode permanecer suprimida.
5. Sob política de congelamento, crescimento de contagens, inclusão de novo ignore ou modificação de identidade do erro deve reprovar o gate até revisão explícita. Uma redução só no número total não prova ausência de substituições.
6. O nível 10 está ativo. O congelamento foi feito em 2026-11-14 a partir do SHA `4740f6103`; snapshot, checksum e distribuição estão em `docs/operations/testing/phpstan-baseline-freeze.json`. O baseline anterior (nível 5) permanece no histórico Git. Mudanças futuras **não** regeram o baseline: só removem entradas efetivamente corrigidas.
7. Registre JSON completo e exit code; interrupção, erro de bootstrap e JSON inválido são INCONCLUSIVOS/FAIL, nunca zero dívida.
8. Workflows podem pular checks em `push` com PR aberta. Verifique o job `phpstan` efetivamente executado; workflow `success` com job `skipped` não basta.
9. `--fix` pode alterar ignores; `--generate-baseline` reescreve baseline. Jamais usá-los em gate ou para ocultar novos erros.
10. Escaneamento focal pode gerar `ignore.unmatched` artificial ao não analisar todo o escopo. O gate canônico é global.

## Gates executáveis

| Gate | Comando | Bloqueia |
| --- | --- | --- |
| Integridade e configuração | `php scripts/phpstan-debt-guard.php check --baseline=phpstan-baseline.neon --config=phpstan.neon --git-base=origin/dev` | path absoluto, contagem inválida, nível < 10, `reportUnmatchedIgnoredErrors` desligado, escopo reduzido, `ignoreErrors` no `phpstan.neon` |
| Crescimento | idem, com baseline de referência no `git-base` | entrada nova, contagem maior, identidade de erro alterada |
| Erros não suprimidos | idem, com `--analysis` da execução canonica | qualquer mensagem fora do baseline e `ignore.unmatched`/`ignore.count` |
| Dívida tocada | `php scripts/phpstan-debt-guard.php diff --baseline=phpstan-baseline.neon --git-base=origin/dev` | arquivo alterado com dívida congelada; módulo tocado sem reduzir ao menos 1 ocorrência |
| Relatório | `php scripts/phpstan-debt-report.php --baseline=... --analysis=... --out=...` | não bloqueia; publica totais, módulos, identificadores e evolução |
| Testes dos guardrails | `bats tests/shell/phpstan-debt-guard.bats` | regressão dos próprios gates |

Execução canônica com JSON preservando o código de saída (fora de `set -e`):

```bash
mkdir -p /tmp/aculta-phpstan
set +e
vendor/bin/phpstan analyse -c phpstan.neon --no-progress --error-format=json \
  --memory-limit=2G > /tmp/aculta-phpstan/check.json 2> /tmp/aculta-phpstan/check.stderr
rc=$?
set -e
printf 'phpstan_exit=%s\n' "$rc"
```

Código 1 com JSON válido é diagnóstico; o guard classifica cada mensagem. Código acima de 1, JSON inválido ou execução interrompida é falha de infraestrutura, nunca PASS.

O comando `diff` considera os arquivos PHP de `git diff --diff-filter=ACMR <base> HEAD`. O CI usa o SHA base do PR ou o `before` do push. Em commit de transição (baseline de referência ainda no nível 5) a comparação de crescimento é suspensa e o gate informa isso; a partir do commit seguinte ela é obrigatória.

O job estável para branch protection é **PHPStan level 10 gate**. Ele falha se o job `phpstan` não tiver executado com sucesso; um `skipped` por deduplicação de push só é aceitável porque o job do PR aberto executa a mesma verificação.

## Evidência mínima

Registre `SHA | PHP | PHPStan | level | escopo | comando | EXIT | novos | corrigidos | delta baseline | testes | CI`.

Ao mudar configuração ou baseline, revise também `docs/operations/GATES.md` e os documentos de dívida; realize testes dos validadores e do pipeline. Uma skill é instrução de trabalho, **não** implementação dos gates.

Fontes: [PHPStan - baseline](https://phpstan.org/user-guide/baseline), [ignores](https://phpstan.org/user-guide/ignoring-errors), [rule levels](https://phpstan.org/user-guide/rule-levels), [Drupal Core - baseline](https://www.drupal.org/docs/develop/development-tools/phpstan/phpstan-in-drupal-core).
