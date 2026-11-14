# ACULTA - Auditoria PHPStan pré-corte nível 10

Data: 2026-11-14. Branch observada: `dev`. Snapshot auditado: `cdffa658c14000cd8c67c81789c4ed0f6f12f625`.
**Status: concluído. Os escaneamentos pendentes foram executados em checkout isolado; resultados, congelamento e gates estão em [PHPSTAN-LEVEL10-CONCLUSAO-2026-11-14.md](PHPSTAN-LEVEL10-CONCLUSAO-2026-11-14.md).**

## 1. Fontes e limite da auditoria

- `phpstan.neon`, `phpstan-baseline.neon`, `composer.lock`, `.github/workflows/php-quality.yml`, `scripts/phpstan-debt-report.php` e `docs/operations/testing/phpstan-debt.md` da `dev`.
- Baseline blob Git SHA: `0d9535e77b1e276d821fa33abeb9ce29da3e38d4`. Contagem estática das entradas e dos campos `count`; **não** é a quantidade validada por execução bruta.
- Execução real do GitHub Actions: [run #1370](https://github.com/coletivo420/aculta/actions/runs/43617556782), [job PHPStan 132515953275](https://github.com/coletivo420/aculta/actions/runs/43617556782/job/132515953275). O job terminou com `[ERROR] Found 55 errors` no nível 5. Outros jobs foram cancelados; a conclusão não é um PASS da suíte.
- O ambiente de autoria deste relatório tinha PHP 8.4, mas não Composer, `vendor/` ou acesso Git de rede; portanto **não executou localmente `vendor/bin/phpstan`**. Os achados executáveis abaixo são retirados de log real de CI, não de simulação.

## 2. Configuração confirmada

| Item | Estado |
| --- | --- |
| PHPStan | `2.3.5` (`composer.lock`) |
| Extensão Drupal | `mglaman/phpstan-drupal 2.2.2` |
| PHPStan PHPUnit | `2.0.21` |
| Nível | **5** |
| Bleeding edge | `vendor/phpstan/phpstan/conf/bleedingEdge.neon` |
| `reportUnmatchedIgnoredErrors` | `true` |
| Escopo | `web/modules/custom`, `web/themes/custom`, `scripts` |
| Excluídos explicitamente | `vendor/`, `web/core/`, `web/libraries/` |
| CI | Executa `vendor/bin/phpstan analyse --no-progress --memory-limit=2G` com o baseline; não o regenera |

A extensão Drupal é instalada automaticamente pelo extension-installer; confirmar autoload/instalação após o `composer install` no runner descartável. O nível 10 exige tratamento mais rigoroso de `mixed`, inclusive implícito. Isso pode gerar muito mais ocorrências; quantidade ainda **desconhecida**.

## 3. Baseline histórico - leitura estática

| Métrica | Valor |
| --- | ---: |
| Entradas `ignoreErrors` | **1.413** |
| Soma dos campos `count` | **2.245** |
| Módulos personalizados | 1.986 |
| Scripts | 217 |
| Temas personalizados | 42 |
| Em arquivos de testes (`/tests/`) | 1.147 |
| Em demais arquivos | 1.098 |

Principais identificadores registrados:

| Identificador | Ocorrências |
| --- | ---: |
| `method.notFound` | 856 |
| `argument.type` | 176 |
| `staticMethod.alreadyNarrowedType` | 103 |
| `staticMethod.internalClass` | 95 |
| `method.alreadyNarrowedType` | 86 |
| `class.notFound` | 85 |
| `nullCoalesce.offset` | 77 |
| `instanceof.alwaysTrue` | 55 |
| `property.notFound` | 54 |
| `return.type` | 46 |

Módulos ou grupos destacados na contagem estática: `aculta_lms` (210), `aculta_forum` (184), `aculta_messenger` (170), `aculta_lms_h5p` (140), `aculta_pagamentos_mercadopago` (139), `aculta_commerce` (132), `aculta_index` (115), além de `scripts` (217). Não equivale a uma lista ordenada de riscos funcionais.

**Divergência documental:** `docs/operations/testing/phpstan-debt.md` anuncia 2.223 erros e foi gerado em 2026-11-11, enquanto o baseline consultado agora registra 2.245. O documento também contém números históricos de várias rodadas. Nenhum total antigo deve ser apresentado como medição corrente. Regenerar somente após uma passada controlada, preservando o histórico existente.

## 4. Evidência de CI - 55 erros no nível 5

Execução do job `phpstan` no SHA `cdffa658c1`; Composer instalado com sucesso; `Run PHPStan` falhou. Inventário agrupado extraído dos identificadores do log (cada linha na contagem abaixo é um diagnóstico reportado, inclusive descompassos de ignore):

| Arquivo / área | Erros | Observação de triagem |
| --- | ---: | --- |
| `scripts/validate-aculta420-v2-sdc.php` | 14 | 13 novas chamadas `Markup::create()` identificadas como internas; 1 `ignore.count` (baseline esperava 95, CI encontrou 108) |
| `aculta_forum/src/Form/DiscussionCommentModerationForm.php` | 8 | 7 propriedades privadas incompatíveis com `DependencySerializationTrait`; 1 `nullCoalesce.offset` |
| `aculta_events/src/Controller/EventPageController.php` | 6 | Variáveis/atribuições não utilizadas |
| `aculta_forum/src/Hook/DiscussionReactionEntityViewHooks.php` | 5 | Offsets redundantes e atribuição sobrescrita |
| `aculta_forum/src/Plugin/UiPatterns/Source/ViewFieldValueSource.php` | 5 | 3 `class.notFound` ligados à referência `Drupal\\Core\\Field\\FieldableEntityInterface`; rever namespace real |
| `aculta420_v2/tests/src/Unit/PagePresentationHooksTest.php` | 4 | Chamada a `preprocessMapMarkerIconTemplate()` não encontrada, mais duas asserções suspeitas |
| `scripts/validate-aculta420-sdc-library-contract.php` | 3 | `argument.printf` (2 placeholders, 3 valores), `sortArray.empty` e `function.impossibleType` |
| `aculta420_v2/aculta420_v2.theme` | 2 | `ignore.unmatched` |
| `aculta420_v2/src/Story/StudioMenuStory.php` | 2 | `ignore.count` e propriedade privada incompatível com serialização |
| `scripts/validate-aculta420-sdc-schemas.php` | 1 | `nullCoalesce.offset` |
| `aculta_events/src/Plugin/views/field/PublicCatalogueCard.php` | 1 | `nullCoalesce.offset` |
| `aculta_forum/src/Controller/DiscussionReplyController.php` | 1 | Regra de dependência por injeção |
| `aculta_forum/src/Hook/ForumThemeHooks.php` | 1 | `identical.alwaysFalse` |
| `aculta_forum/tests/src/Unit/PublicForumListViewHooksTest.php` | 1 | Propriedade privada incompatível com serialização |
| `aculta420_v2/theme-settings.php` | 1 | `ignore.count` (esperava 7, ocorreram 6) |
| **Total reportado** | **55** | Não são necessariamente 55 novos bugs funcionais |

**Cuidados:** `ignore.count` e `ignore.unmatched` podem indicar baseline desatualizado; `class.notFound`, `method.notFound`, `argument.printf` e serialização merecem investigação de bug real antes de qualquer ignore. A contagem de 55 é de um SHA; branches e PRs concorrentes podem já ter mudado os arquivos.

Também foi observado um run de `push` exibido como sucesso no mesmo SHA com o job PHPStan `skipped`, porque o workflow delega a PR quando há verificação duplicada. **Nunca interpretar a conclusão global de um run com jobs skipped como aprovação do PHPStan**. Confirmar o job de PR ou executar o comando diretamente.

## 5. Resultados da execução

- [x] Nível 5 com baseline no checkout isolado: **55 erros**, reproduzindo o CI no SHA `f4399abb9`.
- [x] Nível 5 sem baseline: **2.292 ocorrências**.
- [x] Nível 10 sem baseline: **27.214 ocorrências** no SHA inicial; **26.848** no SHA final após correções.
- [x] Ocorrências confrontadas com o baseline 5; regras 6-10 separadas de alterações reais de código.
- [x] `class.notFound`, `method.notFound`, `argument.printf`, acesso, serialização e testes inconsistentes triados; correções aplicadas.
- [x] Baseline nível 10 congelado: **15.599 entradas / 26.848 ocorrências**; nível 10 canônico com `[OK] No errors`.
- [x] Relatório, distribuição e checksum publicados; CI com gates bloqueantes.

Evidência: [conclusão](PHPSTAN-LEVEL10-CONCLUSAO-2026-11-14.md), [dívida](phpstan-debt.md) e [manifesto](phpstan-baseline-freeze.json).
