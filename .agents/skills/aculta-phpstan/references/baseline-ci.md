# Baseline, dívida histórica e gates

A fonte de verdade do nível, caminhos, extensões e ignores é `phpstan.neon`. Relatórios históricos não substituem reescaneamento no SHA atual.

## Regras

1. O baseline canônico `phpstan-baseline.neon` registra dívida conhecida. `reportUnmatchedIgnoredErrors: true` pode reprovar a análise quando uma supressão se torna obsoleta ou sua contagem não corresponde.
2. `[OK] No errors` com baseline é **zero erros não suprimidos**, não dívida zero. Distinga erros brutos, ocorrências baselined e novos diagnósticos.
3. Não regenerar baseline por rotina, não criar `ignoreErrors` genérico, não baixar nível nem neutralizar o CI com `continue-on-error` ou `|| true`.
4. Para reduzir dívida, registre caminho, identificador, mensagem, quantidade antes/depois, testes e SHA. Atualize apenas entradas realmente resolvidas e revise o diff.
5. Sob política de congelamento, crescimento de contagens, inclusão de novo ignore ou modificação de identidade do erro deve reprovar o gate até revisão explícita. Uma redução só no número total não prova ausência de substituições.
6. Na migração para nível 10, rode **nível atual com baseline**, **nível atual bruto** e **nível 10 bruto** no mesmo SHA/ambiente antes do corte. Siga `docs/operations/testing/PHPSTAN-LEVEL10-PLANO-E-EXECUCAO.md`. Esta skill **não ativa** nível 10.
7. Registre JSON completo e exit code; interrupção, erro de bootstrap e JSON inválido são INCONCLUSIVOS/FAIL, nunca zero dívida.
8. Workflows podem pular checks em `push` com PR aberta. Verifique o job `phpstan` efetivamente executado; workflow `success` com job `skipped` não basta.
9. `--fix` pode alterar ignores; `--generate-baseline` reescreve baseline. Jamais usá-los em gate ou para ocultar novos erros.
10. Escaneamento focal pode gerar `ignore.unmatched` artificial ao não analisar todo o escopo. O gate canônico é global.

## Evidência mínima

Registre `SHA | PHP | PHPStan | level | escopo | comando | EXIT | novos | corrigidos | delta baseline | testes | CI`.

Ao mudar configuração ou baseline, revise também `docs/operations/GATES.md` e os documentos de dívida; realize testes dos validadores e do pipeline. Uma skill é instrução de trabalho, **não** implementação dos gates.

Fontes: [PHPStan - baseline](https://phpstan.org/user-guide/baseline), [ignores](https://phpstan.org/user-guide/ignoring-errors), [rule levels](https://phpstan.org/user-guide/rule-levels), [Drupal Core - baseline](https://www.drupal.org/docs/develop/development-tools/phpstan/phpstan-in-drupal-core).
