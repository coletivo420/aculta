---
name: aculta-seguranca-estatica
description: Faz análise estática de segurança em PHP e Drupal do ACULTA (Semgrep) com triagem de falsos positivos. Use ao revisar pagamento, carrinho, uploads, formulários ou credenciais.
---

# Segurança estática do ACULTA

Escopo prioritário: pagamento e carrinho (MAIN), uploads, formulários públicos, integrações e tratamento de credenciais. Padrão de código: `docs/portal/DRUPAL-12-STANDARDS.md`, seção 12.

1. Verifique a ferramenta: `semgrep --version`. Se faltar, registre DEFERRED com o motivo e pare nesta etapa. Não instale pacote sem aprovação do responsável.
2. Escopo: liste só os caminhos alterados ou da área revisada. Não varra `vendor/`, `web/core/`, `web/modules/contrib/` nem `web/libraries/`.
3. Regras locais: `rules/aculta-php.yml` (sem biblioteca remota; o proxy bloqueia o registro do Semgrep). Rode com saída resumida: `python3 scripts/agent-output.py --lines 60 --match 'ERROR|finding' -- semgrep scan --config docs/agent-skills/aculta-seguranca-estatica/rules/aculta-php.yml --metrics=off --json -o "$SUITE_OUT/semgrep.json" <caminhos>`. Use `$SUITE_OUT` fora do repositório.
4. Triagem: para cada achado, classifique como **real**, **falso positivo** (com a razão) ou **não aplicável**. Não reporte achado não triado.
5. Achado real: informe arquivo, linha e regra. Não corrija fora da tarefa sem autorização.
6. Segredo encontrado no código: não repita o valor no relatório. Informe só o caminho e a linha, e trate como incidente.
7. Relatório: total por classe, reais, falsos positivos e DEFERRED. Nunca declare "sem achados" sem a execução.

Plugin da Trail of Bits `static-analysis` (versão 1.5.1) instalado no usuário em 2026-11-05, com as skills `semgrep` e `codeql`. Use-as para regras da biblioteca quando o proxy permitir. Licença: CC-BY-SA-4.0; não copie o conteúdo para o repositório.

Linha de base: `docs/operations/SEGURANCA-ESTATICA-BASELINE-2026-11-05.md`.
