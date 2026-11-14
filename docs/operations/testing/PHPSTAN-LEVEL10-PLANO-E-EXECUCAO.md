# ACULTA - Plano de auditoria executável e adoção do PHPStan nível 10

Data: 2026-11-14. Situação: **PLANEJADO / NÃO AUTORIZA CONGELAMENTO ANTES DE REESCANEAR**.

Documento complementar: [auditoria pré-corte](PHPSTAN-AUDITORIA-PRE-LEVEL10-2026-11-14.md). Escopo: `dev`, todos os módulos e temas personalizados e `scripts`, incluindo seus testes. Objetivo: adotar nível 10 com rastreabilidade da dívida histórica, evitando regressão de qualidade e sem mascarar bugs.

## Princípios obrigatórios

1. **Não** editar `phpstan.neon`, `phpstan-baseline.neon`, `composer.lock` nem os workflows de produção na rodada de auditoria. Não executar `--fix` ou `--generate-baseline` contra o arquivo canônico.
2. Validar a execução em checkout **isolado, limpo e imutável** do SHA de origem. Não tocar em banco, cache, configuração do site ou arquivos `secrets/`. Usar PHP 8.4, Composer lock e dependências `--dev`.
3. Evidências exigidas: comando, PHP/PHPStan/Drupal extension, SHA, horário, exit code, total bruto, JSON, erro de configuração, memória, arquivo e identificador. `exit 1` com JSON de erros válidos é **resultado de diagnóstico**, não PASS.
4. Separar supressão legada de nível 5, achados novos no nível 5, problemas adicionais dos níveis 6-10, entrada obsoleta/contagem divergente e falso positivo demonstrado. Não chamar todos os avisos de bugs funcionais.
5. Triar primeiro erros reais de método/classe, argumentos/retornos, autorização, pagamentos, dados e serialização, e testes que chamam APIs inexistentes. Sem regeneração automática de baseline por agente.
6. A mudança definitiva para `level: 10` só passa após **zero erro não baselined e zero ignore obsoleto** no CI, com baseline aprovado e crescimento futuro proibido.
7. Não reduzir testes, remover verificações de segurança, enfraquecer nível/regras, desligar `reportUnmatchedIgnoredErrors` ou criar ignores globais para obter verde.
8. Observar agentes concorrentes: revalidar `origin/dev` antes de publicar; usar commits só com arquivos deliberadamente alterados, sem `git add -A` em worktree compartilhada.

## Fase A - Reproduzir o nível 5 (gate obrigatório)

- Fixar SHA e registrar `git status --porcelain`, `php -v`, `composer show phpstan/phpstan mglaman/phpstan-drupal`.
- Instalar dependências do lock (`composer install --prefer-dist --no-interaction`) **no checkout isolado**.
- Executar análise configurada com baseline; registrar JSON e verificar se as **55 mensagens** do [job CI de 2026-11-14](https://github.com/coletivo420/aculta/actions/runs/43617556782/job/132515953275) se reproduzem no SHA `cdffa658c1`. Nova `dev` pode ter resultados diferentes, e isso deve ser explicitado.
- Diagnosticar mensagens `ignore.count` e `ignore.unmatched` sem apagá-las cegamente. Se dependências/ambiente diferirem, tratar a rodada como inconclusiva, não como limpeza de dívida.
- Executar **nível 5 bruto sem qualquer baseline**; comparar com a soma estática de 2.245 ocorrências, mas não presumir igualdade.

**Entrega A:** `phpstan-5-com-baseline.json`, `phpstan-5-bruto.json`, status e relatório de delta documentado.

## Fase B - Escaneamento bruto nível 10

- Criar arquivo de configuração temporário no diretório raiz do checkout isolado removendo **apenas** o include do baseline do `phpstan.neon`. Manter `bleedingEdge`, extensão Drupal, paths/excludes e regras; confirmar que nenhum ignore adicional foi introduzido.
- Executar PHPStan `--level=10` sobre os mesmos paths e com memória suficiente (sugestão inicial `4G`, aumentar com evidência se houver OOM). Manter relatório integral em JSON fora do commit.
- Contabilizar `totals.file_errors` e `totals.errors`, além de messages por arquivo; não confundir `count` agregado de baseline com número de mensagens de JSON. Capturar erros gerais e falhas de bootstrap separadamente.
- Publicar agregados por nível 5 e nível 10; identificar delta das regras superiores, classes não descobertas e problemas de análise provocados por configuração.
- Reexecutar por módulo somente para diagnóstico focal, nunca como substituto do escaneamento global.

**Entrega B:** `phpstan-10-bruto.json`, ranking de módulos/identificadores/arquivos, árvore de criticidade revisada e total bruto verificável.

## Fase C - Triagem e correção pré-congelamento

Prioridade P0/P1: classes/métodos inexistentes em produção, contratos de argumento e retorno, acesso e permissões, segurança, financeiro, serialização de classes Drupal, inconsistências de teste que encobrem ausência de implementação. P2: tipagem/nullable e lógica redundante. P3: limpeza e documentação. A classificação automática de `scripts/phpstan-debt-report.php` é heurística; validação funcional é necessária.

Tratar os casos já observados no CI: `DiscussionCommentModerationForm` e `StudioMenuStory` (private + `DependencySerializationTrait`), `ViewFieldValueSource` (namespace de interface), `PagePresentationHooksTest` (método não encontrado), `validate-aculta420-sdc-library-contract.php` (`printf`), `validate-aculta420-v2-sdc.php` (`Markup::create` + 13 ocorrências extras) e contagens obsoletas do tema. Revisar cada um no HEAD antes de modificar.

Correções devem alterar tipos/contratos reais, adicionar stubs restritos quando houver erro da ferramenta demonstrado e executar PHPUnit Unit/Kernel relacionado. Cada fix deve diminuir o relatório bruto; evitar `@phpstan-ignore` salvo justificativa documentada e aprovação.

**Entrega C:** lista de issues/patches priorizados e resultados de regressão. Nenhum baseline gerado nesta etapa.

## Fase D - Corte para nível 10 (após revisão)

1. Escolher um único baseline canônico `phpstan-baseline.neon` para dívida *ainda existente* no nível 10; arquivar o baseline anterior **por SHA de Git e relatório de comparação** (não manter dois baselines ativos sobrepostos). Pode-se usar candidato temporário com nome diferente, mas não incluí-lo na configuração de produção antes da homologação.
2. Gerar baseline candidato no **mesmo** commit e ambiente do escaneamento validado, revisar diffs de ocorrências, nomes/identificadores e escopo; **não** incluir novos erros posteriores ao snapshot de corte.
3. Comitar atomicamente `phpstan.neon` com `level: 10`, o baseline aprovado, o snapshot/manifesto de dívida e mudanças pontuais do CI. Manter `reportUnmatchedIgnoredErrors: true` e gate bloqueante.
4. Garantir que CI realmente rode PHPStan (não `skipped`): em push com PR aberta, confirmar job do PR. Verificar `[OK] No errors` como ausência de erros **não baselined**, não ausência de dívida.
5. **Não trocar a meta se o bruto for excessivo**: documentar volume e custo, preparar triagem e decidir corte com base em dados. Não esconder milhares de mensagens com ignore abrangente.

**Entrega D:** snapshot versionado com SHA, versão PHP/PHPStan/Drupal, total inicial nível 5 bruto, nível 10 bruto, baseline pós-triagem, dívida por módulo/identificador, correções, exceções justificadas e resultado do CI.

## Fase E - Política permanente de redução

- Novas ocorrências quebram CI; baseline não se regenera no CI e não pode crescer por atualização rotineira de agente.
- Remoção de supressões resolvidas é obrigatória e revisada com teste, respeitando `reportUnmatchedIgnoredErrors: true`.
- PR/commit que toca um módulo deve registrar seu delta de dívida quando houver. Priorizar redução progressiva em módulos refatorados.
- Manter relatório agregado atualizado e separar `baseline`/dívida de `erros não suprimidos`/regressões.
- Evitar números artificiais: toda comparação precisa indicar SHA, versão da ferramenta e mesmo escopo.

## Comandos de execução da auditoria (checkout descartável; NÃO altera baseline)

Executar no diretório raiz de um checkout limpo e isolado. O agente deve corrigir problemas ambientais antes de concluir. A saída é conservada no diretório local `artifacts/phpstan-audit/`, sem commit automático.

```bash
set -euo pipefail
test -f phpstan.neon
test -x vendor/bin/phpstan
mkdir -p artifacts/phpstan-audit
git rev-parse HEAD | tee artifacts/phpstan-audit/sha.txt
php -v > artifacts/phpstan-audit/php-version.txt
vendor/bin/phpstan --version > artifacts/phpstan-audit/phpstan-version.txt

# O include do baseline histórico deve existir UMA vez.
test "$(grep -Ec '^[[:space:]]*-[[:space:]]*phpstan-baseline[.]neon[[:space:]]*$' phpstan.neon)" -eq 1
audit_cfg="$(mktemp ./.phpstan-audit-XXXXXX.neon)"
trap 'rm -f "$audit_cfg"' EXIT
sed '/^[[:space:]]*-[[:space:]]*phpstan-baseline[.]neon[[:space:]]*$/d' phpstan.neon > "$audit_cfg"

run_audit() {
  local label="$1" config="$2" level="$3" rc
  set +e
  vendor/bin/phpstan analyse --configuration="$config" --level="$level" \
    --no-progress --error-format=json --memory-limit=4G \
    > "artifacts/phpstan-audit/$label.json" \
    2> "artifacts/phpstan-audit/$label.stderr"
  rc=$?
  set -e
  printf '%s %s\n' "$label" "$rc" >> artifacts/phpstan-audit/exit-codes.txt
  php -r '
    $j = json_decode((string) file_get_contents($argv[1]), true);
    if (!is_array($j) || !isset($j["files"], $j["totals"])) {
      fwrite(STDERR, "JSON PHPStan ausente/invalido: {$argv[1]}\n");
      exit(2);
    }
    printf("%s: file_errors=%d general_errors=%d\n",
      $argv[1], $j["totals"]["file_errors"] ?? -1, $j["totals"]["errors"] ?? -1);
  ' "artifacts/phpstan-audit/$label.json"
  if [ "$rc" -gt 1 ]; then
    echo "Erro de execução, não apenas erro estático: $label ($rc)" >&2
    return "$rc"
  fi
}

run_audit "nivel5-com-baseline" phpstan.neon 5
run_audit "nivel5-bruto" "$audit_cfg" 5
run_audit "nivel10-bruto" "$audit_cfg" 10
```

Nota: `composer install` deve ocorrer **antes**, no checkout isolado; nunca `composer update`. Se não houver memória ou se a análise interromper antes do JSON íntegro, registrar `INCONCLUSIVO` e repetir com evidência. Não classificar um erro de infraestrutura como zero dívida.

Referências: [PHPStan níveis](https://phpstan.org/user-guide/rule-levels), [baseline](https://phpstan.org/user-guide/baseline), [CLI](https://phpstan.org/user-guide/command-line-usage), [Drupal PHPStan](https://www.drupal.org/docs/develop/development-tools/phpstan/getting-started).

## Prompt para o agente CLI

> Trabalhe no repositório `coletivo420/aculta`, branch `dev`. Leia `AGENTS.md`, skills aplicáveis e os documentos `docs/operations/testing/PHPSTAN-AUDITORIA-PRE-LEVEL10-2026-11-14.md` e `docs/operations/testing/PHPSTAN-LEVEL10-PLANO-E-EXECUCAO.md`. Não execute alterações no Runtime compartilhado. Crie um checkout isolado do HEAD remoto e registre o SHA. Instale dependências travadas pelo Composer, sem `update`. Execute os três escaneamentos do runbook: nível 5 com baseline, nível 5 bruto sem baseline e nível 10 bruto sem baseline. Preserve JSON, stderr, códigos de saída e versões. Confronte com a execução CI no SHA `cdffa658c1` (55 erros, se o SHA atual mudou documente o delta), com o baseline de 1.413 entradas e 2.245 ocorrências, e com a divergência do relatório antigo de 2.223 erros. Classifique por módulo, produção/testes, identificador, criticidade real, causa e correção possível, distinguindo falso positivo fundamentado. Investigue primeiro os problemas de serialização Drupal, classe/método inexistentes, `printf`, UI Patterns e contagens `ignore.count`. Registre relatório no GitHub em `docs/operations/testing/` com contagens reais e evidências, e publique commits diretos em `dev` somente dos relatórios, scripts auxiliares validados e correções pontuais seguras e autorizadas. **NÃO** modifique `phpstan.neon` nem o baseline canônico, **NÃO** ative o nível 10 ainda e **NÃO** faça regeneração automática/ignores globais. Ao final, entregue um plano do corte, total bruto nível 10, erros críticos, testes rodados, arquivo de relatório e SHAs dos commits. Se algo não rodar, registre claramente como inconclusivo, nunca como PASS.
