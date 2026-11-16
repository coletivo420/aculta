# Auditoria e limpeza de scripts - ACULTA

Data: 16/11/2026 | Branch: `dev` | Preparado contra `73f149131b69eb819373bbc42b01bb2d6dc88453`.

## Escopo e evidencias

- Arvore inicial: 205 arquivos em `scripts/`, sendo 120 PHP.
- Baseline anterior a este commit: **21.948 ocorrencias**, com **2.993 em scripts/**. Sao registros de divida congelada, **nao uma execucao nova do PHPStan**.
- Fontes analisadas: inventario recursivo Git, scripts de maior divida, `composer.json`, `package.json`, `.github/workflows/`, `.githooks/pre-push`, `AGENTS.md`, `docs/operations/GATES.md` e gates do tema V2.
- Limite: a pesquisa de referencias em pontos centrais nao prova a ausencia de consumidores em toda a arvore. Nao foi acessado o runtime; testes que necessitam de Composer/Drupal permanecem **DEFERRED**.

## Corte inicial neste commit

1. Em `scripts/validate-aculta420-v2-sdc.php`, **105 chamadas a `Markup::create()` com literais** foram trocadas por `new FormattableMarkup(..., [])`, API ja empregada no mesmo script. Removido o import `Drupal\\Core\\Render\\Markup`. Os arquivos de componentes SDC nao foram alterados.
2. Retirados `scripts/audit-support-data.php` e `scripts/audit-support-data-details.php`, diagnosticos manuais sem vinculo identificado com CI/Composer/gates consultados; o segundo percorria entidades de apoio e imprimia propriedades de registros.
3. Retirado `scripts/capture-layout-container.py`, ligado a layout de container legado e dependente de paths absolutos da estacao local.
4. Retirado `scripts/verify-yoast-compatibility.mjs`, teste manual que pressupunha `web/modules/contrib/yoast_seo`; o pacote nao consta das dependencias Composer declaradas.
5. Removidas do baseline congelado as **quatro entradas exatas** relacionadas: 105 ocorrencias `staticMethod.internalClass` do validador SDC e 14 do diagnostico de apoio. **Sem regenerar o baseline**.

Reducao **esperada**: 119 ocorrencias, scripts de 2.993 para **2.874**, projeto de 21.948 para **21.829**. Os novos totais so devem ser considerados homologados apos a execucao canonica do PHPStan.

## Fila de auditoria complementar

| Familia | Ocorrencias previas | Acao |
| --- | ---: | --- |
| `validate-ui-patterns-views-cutover.php` | 305 | Gate ativo: corrigir tipagem das estruturas YAML, nao remover |
| `inventory-ui-suite-consumers.php` | 237 | Confirmar consumidores do CSV e do snapshot de runtime; apos isso manter ou retirar |
| `lib/Aculta420DesignFoundationsAnalyzer.php` | 206 | Refatorar tipagem e sobreposicao com demais gates de fundacao V2; preservar testes |
| `validate-portal-commerce-security.php` | 177 | Separar teste que altera o status do gateway Mercado Pago para ambiente isolado |
| `install-institution.php` | 163 | Revisar corte de legado junto com `apply-official-institution.php` e `refine-institution.php`; nao excluir dependencias isoladamente |
| `institution/phase6-account-email-test.php` | 84 | Migrar teste mutavel para PHPUnit Kernel isolado ou demonstrar obsolescencia |
| `validate-aculta420-css-scope.php` | 91 | Manter cobertura, corrigir acessos a YAML como `mixed` |

## Procedimento de homologacao local

1. `git fetch origin && git merge --ff-only origin/dev`, preservando a worktree compartilhada e as alteracoes de outros agentes.
2. Buscar referencias restantes: `rg -n --hidden 'audit-support-data|capture-layout-container|verify-yoast-compatibility' --glob '!vendor/**' --glob '!node_modules/**' .`. Referencias em historico podem permanecer; atualizar apenas contratos ativos.
3. `php -l scripts/validate-aculta420-v2-sdc.php`, depois `vendor/bin/drush scr scripts/validate-aculta420-v2-sdc.php` apenas em Runtime autorizado; comparar a saida com a anterior.
4. `mkdir -p artifacts && vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress --error-format=json --memory-limit=2G > artifacts/phpstan.json` (registrar exit code); rodar `php scripts/phpstan-debt-guard.php check --baseline=phpstan-baseline.neon --config=phpstan.neon --git-base=<SHA-pai> --analysis=artifacts/phpstan.json` e `php scripts/phpstan-debt-guard.php diff --baseline=phpstan-baseline.neon --git-base=<SHA-pai>`.
5. Rodar `bats tests/shell/phpstan-debt-guard.bats`, os gates do tema em `web/themes/custom/aculta420_v2/docs/gates.md` e o conjunto de testes afetados. Nao contornar pre-push, nao declarar PASS para um gate nao executado.
6. Atualizar **o relatorio**, nao regenerar o baseline: `php scripts/phpstan-debt-report.php --baseline=phpstan-baseline.neon --analysis=artifacts/phpstan.json --out=docs/operations/testing/phpstan-debt.md`. Atualizar o estado corrente de `phpstan-baseline-freeze.json` preservando o snapshot historico, e corrigir eventuais apontamentos de CI.

## Regras para a continuidade

- Antes de remover outros PHP: `rg` amplo, mapa de consumidores e classificacao (ativo, operacao pontual, historico concluido, duplicado, inseguro). Migracoes e ajustes de config/DB nao devem ser executados durante a auditoria.
- Preservar os contratos de `phpstan-debt-guard.php`, `lib/PhpstanDebt.php`, `phpunit-suites.php`, `runtime-sync.sh`, Composer hooks, seguranca, permissao e isolamentos multidominio.
- Cada commit de limpeza deve retirar apenas as entradas exatas do baseline e executar os gates; proibido reduzir o nivel 10, adicionar ignores genericos ou excluir o diretorio `scripts/` da analise.
- Registrar explicitamente PASS, FAIL, DEFERRED ou N/A por gate e SHA usado.
