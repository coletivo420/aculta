---
name: aculta-shell-qualidade
description: Verifica scripts shell e workflows do GitHub Actions com ShellCheck, shfmt, actionlint e Bats, sem tocar o Runtime real. Use ao alterar .sh, Runtime Sync ou .github/workflows.
---

# Qualidade de shell e workflows

Use junto com `aculta-runtime-sync` (fluxo e riscos), `aculta-gates-por-escopo` (escolha dos gates) e `aculta-seguranca-estatica` (segredos em scripts).

## Ferramentas e estado (verificado nesta entrega)

| Ferramenta | Estado | Como é usada |
|---|---|---|
| ShellCheck | INSTALLED (`/usr/bin/shellcheck`) | `shellcheck -S warning <arquivos>` |
| shfmt | INSTALLED (`/usr/bin/shfmt`) | `shfmt -d -i 2 -ci <arquivos>` (somente diff, nunca `-w` em legado) |
| Bats-core | INSTALLED (`/usr/bin/bats`) | testes em `tests/shell/` com fixtures temporárias |
| actionlint | INSTALLED (`/usr/local/bin/actionlint`, v1.7.12) | `actionlint .github/workflows/*.yml`; CI usa versão fixada |
| `bash -n` | sempre disponível | sintaxe de `scripts/runtime-sync.sh` e de scripts novos |

Não afirme que uma ferramenta passou se ela não foi executada. Registre DEFERRED com o motivo.

## Escopo: arquivos novos e alterados primeiro

- Legado não é reformatado em massa. `shfmt -d` roda somente sobre arquivos do diff da entrega.
- ShellCheck sobre legado é dívida: registre o resultado, não bloqueie a entrega por ele, salvo quando o arquivo foi tocado.
- Scripts Runtime Sync existentes: `scripts/runtime-sync.sh`, `scripts/test-runtime-sync.sh`, `scripts/runtime-sync/helper.py`, `scripts/runtime-sync/risk-rules.yml`. Preserve os cenários simulados da suíte v2.

## Bats: quando usar

Use Bats quando houver ganho real de isolamento, reutilização ou cobertura:

- argumentos inválidos e códigos de saída;
- ambiente incorreto e falta de dependência;
- falha de Git, interrupção, lock e concorrência;
- cache pendente e falha de Drush (mock);
- worktree compartilhado, estado parcial e classificação de risco;
- bloqueio de alteração estrutural.

Regras obrigatórias:

1. Fixtures em diretório temporário (`mktemp -d`), nunca no Runtime real.
2. `drush`, `git` remoto e `composer` são substituídos por mocks no `PATH` do teste.
3. Não executar `drush cr`, migração, `updatedb` ou config import em teste.
4. Não usar `aculta_runtime` nem qualquer banco operacional.
5. Nunca tornar um teste verde por `|| true`; o código de saída do script é a asserção.

## Comandos

```bash
actionlint .github/workflows/*.yml
shellcheck -S warning <scripts novos ou alterados>
shfmt -d -i 2 -ci <scripts novos ou alterados>
bash -n scripts/runtime-sync.sh
bash scripts/test-runtime-sync.sh
bats tests/shell/
```

`bats tests/shell/` só existe quando a primeira suíte Bats for adicionada; antes disso o gate fica N/A, não PASS.

## Workflows do GitHub Actions

- `actionlint` valida sintaxe, expressões e permissões dos workflows alterados.
- Ações de terceiros: preferir pin por SHA. Quando o SHA não puder ser verificado no ambiente, use tag e registre a pendência em `docs/operations/testing/`.
- Permissões mínimas por job (`permissions:`), sem segredo em workflow disparado por PR de fork.

## Relação com Runtime Sync

- Estes gates testam o script. Não substituem a execução de `aculta-runtime-sync` no Runtime, que é análise e não migração estrutural.
- Mudança em `scripts/runtime-sync*` exige `bash -n` e a suíte de cenários antes do commit.
