---
name: aculta-seguranca-estatica
description: Análise estática PHP/Drupal do ACULTA com Semgrep (regras locais, fixtures, triagem por ocorrência) e advisories de dependências. Use ao revisar pagamento, uploads ou credenciais.
---

# Segurança estática do ACULTA

Escopo prioritário: pagamento, carrinho e webhooks (MAIN), uploads, formulários públicos, integrações, credenciais, permissões administrativas, autorização de entidades, política multidomínio, render arrays, escapes Twig e cache de conteúdo privado. Padrão: `docs/portal/DRUPAL-12-STANDARDS.md`, seção 12.

## Ferramentas (verificado em 2026-11-11)

| Ferramenta | Estado | Uso |
|---|---|---|
| Semgrep 1.183.0 | INSTALLED | regras locais, sem registro remoto |
| Fixtures de regra | CONFIGURED | `tests/check-rule-fixtures.py` |
| composer audit | EXECUTABLE com `--locked` | advisories do lock |
| npm audit | EXECUTABLE em `tests/browser` | dependências Node |
| gitleaks | MISSING | DEFERRED até instalação fixada |
| drupal/security_review | DEFERRED | exige Drush no Runtime |

Não instale pacote sem aprovação do responsável. Ferramenta ausente vira DEFERRED com motivo.

## Procedimento

1. **Escopo:** só os caminhos alterados ou da área revisada. Exclua `vendor/`, `web/core/`, `web/modules/contrib/`, `web/libraries/` e `node_modules/`.
2. **Regras locais** (`rules/aculta-php.yml`), com saída fora do repositório: `semgrep --metrics=off --config <regras> --exclude vendor --exclude web/core --exclude web/modules/contrib --exclude web/libraries --exclude node_modules --json -o "$SUITE_OUT/semgrep.json" <caminhos>`. Semgrep sem login redige o trecho ("requires login"): leia o arquivo, não o texto do achado.
3. **Fixtures:** toda regra tem caso positivo (`ruleid:`) e negativo (`ok:`) em `tests/rules/aculta-php.php`. Rode `python3 -I tests/check-rule-fixtures.py "$(command -v semgrep)" rules/aculta-php.yml tests/rules/aculta-php.php`. Regra que não casa com o positivo está quebrada, mesmo com zero achado.
4. **Triagem por ocorrência:** exatamente uma classe:
   - **REAL:** dado não confiável chega ao sink sem escape ou validação.
   - **FALSO POSITIVO:** sink protegido (ex.: `$this->t()` escapa `@`; `unserialize` com `allowed_classes => FALSE`). Informe a proteção.
   - **ACEITO COM JUSTIFICATIVA:** risco aceito, com motivo e dono.
   - **PENDENTE:** falta prova. Não é PASS.
5. **Achado REAL:** arquivo, linha e regra. Não corrija fora da tarefa autorizada.
6. **Segredo no código:** não repita o valor. Caminho e linha, tratado como incidente.
7. **Relatório:** total por regra e por classe. Nunca declare "sem achados" sem execução.

Relatório da primeira triagem: `docs/operations/testing/SEGURANCA-ESTATICA-2026-11-11.md`.

## Dependências, segredos e Security Review

Procedimento completo em [references/dependencias-segredos-review.md](references/dependencias-segredos-review.md): `composer audit --locked`, `npm audit`, varredura de segredos (gitleaks DEFERRED) e `drupal/security_review` (somente leitura, DEFERRED sem Runtime).

## Referências

- Plugin Trail of Bits `static-analysis` 1.5.1 (usuário, 2026-11-05): use as skills `semgrep` e `codeql` quando o proxy permitir. Licença CC-BY-SA-4.0; não copie conteúdo para o repositório.
- Linha de base anterior: `docs/operations/SEGURANCA-ESTATICA-BASELINE-2026-11-05.md`.
