# PHPStan nível 10 — auditoria, migração e congelamento da dívida

Data: 2026-11-14. Branch: `dev`. SHA final do congelamento: `c9fa42b42`.
Documentos relacionados: [auditoria pré-corte](PHPSTAN-AUDITORIA-PRE-LEVEL10-2026-11-14.md), [plano e execução](PHPSTAN-LEVEL10-PLANO-E-EXECUCAO.md), [dívida corrente](phpstan-debt.md) e [manifesto do baseline](phpstan-baseline-freeze.json).

## 1. Configuração anterior

| Item | Antes |
| --- | --- |
| Nível | 5 |
| Bleeding edge | Ativo |
| Extensão Drupal | `mglaman/phpstan-drupal 2.2.2` |
| `reportUnmatchedIgnoredErrors` | `true` |
| Baseline | `phpstan-baseline.neon`: 1.413 entradas, 2.245 ocorrências (`count` somado), blob do commit `2e6318096` |
| Escopo | `web/modules/custom`, `web/themes/custom`, `scripts` |
| Relatório histórico | 2.223 erros (2026-11-11), divergente do baseline |
| CI observado | 55 erros no nível 5 no commit `cdffa658c1` |

## 2. Configuração implementada

```neon
parameters:
    level: 10
    reportUnmatchedIgnoredErrors: true
```

Preservados bleeding edge, extensão Drupal, escopo, exclusões legítimas (`vendor/`, `web/core/`, `web/libraries/`) e ausência de `ignoreErrors` no `phpstan.neon`. O baseline canônico é único e congelado.

## 3. Reescaneamento do nível 5

Checkout isolado em `/home/piradopirata/worktrees/phpstan-l10`, PHP 8.4.27, PHPStan 2.3.5, Composer a partir do lock, mesmo escopo e testes.

| Execução | SHA | Exit | Resultado |
| --- | --- | ---: | --- |
| Nível 5 com baseline | `f4399abb9` | 1 | **55 erros**, reproduzindo exatamente o CI |
| Nível 5 bruto, sem baseline | `f4399abb9` | 1 | 2.292 ocorrências (2.245 registradas no baseline; diferença de 47 por mudanças posteriores no código e supressões obsoletas) |

Os 55 erros do CI foram triados: classes/namespaces inexistentes (`FieldableEntityInterface`), método de teste removido, `printf` com aridade errada, propriedades privadas incompatíveis com `DependencySerializationTrait`, injeção global de `\Drupal`, variáveis mortas e contagens de baseline desatualizadas.

## 4. Nível 10 bruto

| Momento | SHA | Ocorrências brutas |
| --- | --- | ---: |
| Antes das correções | `f4399abb9` | **27.214** |
| Após correções do nível 5 | `c9c891cb0` | **26.964** |
| Após gates e ajustes | `c9fa42b42` | **26.848** |

Distribuição final por identificador (top 8): `offsetAccess.nonOffsetAccessible` 8.234; `argument.type` 4.649; `method.nonObject` 3.059; `missingType.iterableValue` 2.820; `cast.string` 2.094; `cast.int` 1.350; `method.notFound` 1.004; `property.nonObject` 673.

A maior parte é dívida de tipagem dinâmica em código Drupal e scripts, não 26 mil bugs funcionais. `method.notFound` e `class.notFound` foram triados por amostragem e as referências quebradas de produção/teste encontradas foram corrigidas.

## 5. Correções antes do congelamento

- `ViewFieldValueSource`: namespace real de `FieldableEntityInterface`; remoção de narrowing redundante.
- `DiscussionReplyController`: injeção de `KeyValueFactoryInterface` no lugar de `\Drupal::keyValue`; serviço atualizado para 7 argumentos.
- Serialização: propriedades privadas passam a protegidas em formulário, story e test double que usam `DependencySerializationTrait`.
- `EventPageController`: remoção de variáveis mortas, preservando dependências de acesso dos coorganizadores.
- Fórum/catálogo: offsets e coalescências redundantes removidos; `$cid` duplicado eliminado.
- `ModerationNotifier`: `SendResult::isSent` inexistente trocado por `$result->sent`.
- `CourseJourneyComposer` e `QuoteRequestBuilder`: PHPDocs com namespaces inválidos corrigidos.
- Testes: classes/namespaces renomeados de LMS, `ContentIndexRebuilder`, `MockObject` do PHPUnit, contratos de cache e hook de moderação.
- Validador SDC: `printf` com três valores e dois placeholders; dívida de `Markup::create` reduzida de 108 para 106 via `FormattableMarkup`.
- Scripts: `DrupalKernel::createFromRequest` com assinatura correta e import de `ActivityLocationMigrator`.

## 6. Ocorrências congeladas

| Medida | Valor |
| --- | ---: |
| Entradas no baseline | **15.599** |
| Ocorrências congeladas | **26.848** |
| Produção | 15.816 |
| Testes | 11.032 |
| Erros não suprimidos com o baseline aprovado | **0** |
| Checksum SHA-256 do baseline | `9102346a70b269da3b278ae53b3119b503e8413b0868af14c5dae3a69ef0e962` |

O baseline anterior permanece no histórico Git (commit `2e6318096`, checksum `3fa97bb…`). Não há dois baselines ativos.

## 7. Distribuição da dívida

Top módulos: `aculta_forum` 3.096; `scripts` 3.056; `aculta_lms` 2.184; `aculta_messenger` 2.073; `aculta_portal` 2.020; `aculta_events` 1.792; `aculta_wiki` 1.461; `aculta_lms_h5p` 1.299; `aculta_index` 1.112; `aculta_user` 899.

Criticidade funcional revisada (não automática por identificador): 231 ocorrências altas, 23.564 médias e 3.053 baixas. A distribuição completa e os arquivos prioritários estão em [phpstan-debt.md](phpstan-debt.md).

## 8. Gates implementados

`.github/workflows/php-quality.yml`:

| Gate | Efeito |
| --- | --- |
| PHPStan nível 10 com baseline congelado | Bloqueante |
| Detecção de erros não suprimidos e `ignore.unmatched`/`ignore.count` | Bloqueante |
| Crescimento do baseline por path+identificador+mensagem+contagem | Bloqueante |
| Integridade do baseline e da configuração | Bloqueante |
| `@phpstan-ignore*` novo no diff | Bloqueante |
| Dívida em arquivo alterado (redução ≥ 1) | Bloqueante |
| Módulo tocado sem redução de dívida (≥ 1) | Bloqueante |
| Job estável `PHPStan level 10 gate` | Falha se o PHPStan não executou com sucesso; `skipped`/`cancelled` não é PASS |
| Relatório de evolução e artefatos | Publicados sempre |

Sem `continue-on-error`, `|| true`, ignores globais ou exclusões para neutralizar os gates. O baseline nunca é regenerado no CI.

## 9. Guardrails implementados

- `scripts/phpstan-debt-guard.php check`: integridade, configuração, crescimento, identidade de erro, erros não suprimidos e supressões obsoletas.
- `scripts/phpstan-debt-guard.php diff`: arquivo alterado com dívida precisa reduzir ≥ 1; módulo tocado precisa reduzir ≥ 1; novo ignore é proibido.
- `scripts/phpstan-debt-report.php`: totais bruto/congelado/não suprimido, produção/testes, módulos, identificadores, arquivos e evolução por classificação.
- `scripts/lib/PhpstanDebt.php`: parser do baseline, identidade, criticidade e comparação.
- `bats tests/shell/phpstan-debt-guard.bats`: **20 cenários**, incluindo PASS/FAIL, JSON inválido, nível rebaixado, `reportUnmatchedIgnoredErrors` desligado, exclusão não autorizada, supressão obsoleta, identidade disfarçada, dívida tocada e novo ignore.
- Transição documentada: no commit em que a referência ainda está no nível 5, a comparação de crescimento é suspensa; a partir do commit seguinte é obrigatória.

## 10. Skills e instruções atualizadas

- `AGENTS.md`: nível 10 obrigatório, proibição de ignore novo, correção da dívida do arquivo e redução por módulo, gates e relato de erros preexistentes/introduzidos/corrigidos.
- `docs/operations/GATES.md`: comandos e escopo dos gates.
- `.agents/skills/aculta-phpstan/SKILL.md` e `references/baseline-ci.md`: nível 10 ativo, comandos, política de baseline, recuperação e testes, mantendo a skill enxuta e os detalhes nas referências.
- `phpstan-baseline-freeze.json`: SHA, checksum, distribuição, ambiente, política e comandos.

## 11. Testes executados

| Verificação | Resultado |
| --- | --- |
| PHPStan nível 10 com baseline aprovado | `[OK] No errors`, exit 0 (PHP 8.4.27 e 8.5.12) |
| Nível 5 nos 21 arquivos corrigidos, sem baseline | 0 erros |
| PHPUnit focado (fórum, tema, LMS, studio, pagamentos, melhorenvio) | 320 testes, 2.401 asserções, PASS |
| Recorder de assinaturas Mercado Pago | 30 testes, 194 asserções, PASS |
| Bats dos guardrails | 20/20 PASS |
| Bats do projeto | PASS |
| Actionlint | PASS |
| `validate-agent-skills.py` | PASS |
| `validate-agent-token-budget.php` | PASS |
| PHP Lint dos arquivos alterados | PASS |

Falha preexistente registrada e não mascarada: `PagePresentationHooksTest::testForumTopicViewModesUseSdcComposition` já falhava no SHA inicial, fora do escopo desta tarefa. O job Kernel continua informativo no CI por dívida preexistente documentada.

## 12. Pendências e oportunidades

- 26.848 ocorrências congeladas; prioridade por módulo: `aculta_forum`, `scripts`, `aculta_lms`, `aculta_messenger`, `aculta_portal`, `aculta_events`.
- `scripts/content/load-course-introducao-antiproibicionismo.php` ainda referencia a API antiga `Drupal\lms\Entity\*`; exige decisão de produto antes de reescrever o carregador.
- `method.notFound` (1.004) e `class.notFound` devem ser triados por módulo durante refatorações, com narrowing real.
- Cada arquivo alterado e cada módulo tocado agora precisam reduzir ao menos uma ocorrência de dívida.
- Não há autorização para regenerar o baseline; reduções exigem remover as entradas correspondentes no mesmo commit.

## 13. Commits e SHA final

| Commit | Conteúdo |
| --- | --- |
| `c9c891cb0` | Correções reais do nível 5 |
| `4740f6103` | Gate e relatório da dívida |
| `888714d05` | Nível 10 e baseline congelado inicial |
| `954cfd810` | Bloqueio de `@phpstan-ignore` novo |
| `4f39863f7` | Gates bloqueantes no GitHub Actions |
| `5e886355f` | Instruções e skill |
| `b9939d941` | Correção da dívida dos próprios gates |
| `c9fa42b42` | Correção do validador SDC e refinamento do gate por arquivo |

SHA final publicado em `origin/dev`: registrado no relatório de entrega da sessão. O commit concorrente `013b011f8` (métricas SDC) foi integrado e o crescimento de dívida que ele introduziu foi corrigido em `c9fa42b42`.
