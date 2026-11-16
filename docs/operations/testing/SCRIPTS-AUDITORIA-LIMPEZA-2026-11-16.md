# Auditoria e limpeza de scripts - ACULTA

Data: 16/11/2026 | Branch: `dev` | Referência: `0e0de54fb` / `1370b93c2`

## 1. Escopo e Execução da Limpeza

Esta entrega consolida a limpeza profunda e estrutural do diretório `scripts/`, a aposentadoria definitiva da família institucional legada e de utilitários manuais, e a consolidação de validadores com redução substancial da dívida PHPStan nível 10.

### Resumo Quantitativo
- **Arquivos excluídos**: 22 arquivos (scripts PHP legados de provisionamento/admin, artefatos JSON obsoletos e scripts PowerShell locais).
- **Scripts refatorados/consolidados**:
  - `scripts/validate-ui-patterns-views-cutover.php`: tipagem estrita com helpers seguros contra traversal dinâmico em YAML; eliminados 305 erros PHPStan nível 10 sem supressões.
  - `scripts/validate-aculta420-css-scope.php`: alinhamento aos contratos vigentes de UI Patterns (Views do Fórum e Revista Full) e tipagem segura; eliminados 91 erros PHPStan nível 10.
  - `scripts/validate-portal-commerce-security.php`: eliminação de mutação persistente de configuração (`save()` no gateway Mercado Pago) e tipagem segura do gateway; redução de 1 ocorrência no arquivo.
  - `scripts/validate-institution-content.php`: tipagem completa e estrita de estruturas dinâmicas de JSON e blocos/nós; eliminadas todas as 18 ocorrências do arquivo no PHPStan.
- **Redução da dívida PHPStan**:
  - Total de ocorrências no projeto: reduzido de **21.965** (e 21.843 pós-rebase) para **20.971** ocorrências congeladas e **0 erros não suprimidos**.
  - Dívida em `scripts/`: reduzida de **2.995** para **2.003** ocorrências (redução de **992 ocorrências** em scripts).
  - Entradas removidas do baseline: **361 entradas** expurgadas legitimamente e 1 contagem reduzida.

---

## 2. Matriz de Classificação dos Scripts Auditados

| Script / Artefato | Classificação | Justificativa | Ação |
|---|---|---|---|
| `scripts/install-institution.php` | REMOVER | Instalador procedural legado de entidades institucionais superado por `config/sync` e `home-content.json`. 163 ocorrências de dívida. | Excluído |
| `scripts/apply-official-institution.php` | REMOVER | Script de aplicação pontual de registro institucional. 62 ocorrências de dívida. | Excluído |
| `scripts/configure-institution-modules.php` | REMOVER | Script de configuração procedural de módulos históricos. 35 ocorrências. | Excluído |
| `scripts/finalize-institution-local.php` | REMOVER | Ajuste editorial local pontual que dependia de `home-editorial.json`. 60 ocorrências. | Excluído |
| `scripts/refine-institution.php` | REMOVER | Refinamento pontual de blocos e taxonomias institucionais já consolidadas. 22 ocorrências. | Excluído |
| `scripts/clean-admin-structure.php` | REMOVER | Procedimento concluído de limpeza de estrutura legada do admin. 38 ocorrências. | Excluído |
| `scripts/refine-admin-retained-structure.php` | REMOVER | Procedimento concluído de ajuste das estruturas administrativas retidas. 32 ocorrências. | Excluído |
| `scripts/audit-admin-structure.php` | REMOVER | Diagnóstico manual procedural sem consumidor em CI ou gates. 13 ocorrências. | Excluído |
| `scripts/finalize-admin-references.php` | REMOVER | Correção de referências concluída no histórico. 9 ocorrências. | Excluído |
| `scripts/describe-institution-admin.php` | REMOVER | Descritor textual informativo superado pela documentação canônica em `docs/`. 4 ocorrências. | Excluído |
| `scripts/diagnose-institution.php` | REMOVER | Diagnóstico local procedural pontual. 7 ocorrências. | Excluído |
| `scripts/inspect-institution.php` | REMOVER | Utilitário procedural de inspeção manual sem consumidores. | Excluído |
| `scripts/review-final-config.php` | REMOVER | Script de revisão de configuração final de fase anterior. 11 ocorrências. | Excluído |
| `scripts/institution/home-editorial.json` | REMOVER | Artefato de dados consumido exclusivamente por script finalizado. | Excluído |
| `scripts/institution/ESTADO-FASE8-INTEGRAL-ENTITIES.json` | REMOVER | Snapshot estático de migração de Fase 8 concluída. | Excluído |
| `scripts/institution/FINAL-CONFIG-CHANGES.json` | REMOVER | Diff de configuração superado pelo histórico do Git. | Excluído |
| `scripts/institution/PORTAL-APOIO-COMMERCE-CONFIG-MANIFEST.json` | REMOVER | Manifest de fase intermediária sem consumidores ativos. | Excluído |
| `scripts/institution/MULTIDOMAIN-SETTINGS-PRODUCTION.example.php` | REMOVER | Exemplo redundante; convenções de settings estão na documentação de operações. 1 ocorrência. | Excluído |
| `scripts/local/configure-aculta-hosts.ps1` | REMOVER | Script PowerShell pontual de máquina local; redundante com instruções em docs. | Excluído |
| `scripts/local/start-aculta-multidomain.ps1` | REMOVER | Script PowerShell local redundante. | Excluído |
| `scripts/serve.ps1` | REMOVER | Script PowerShell local redundante com servidor web padrão. | Excluído |
| `scripts/verify-schema-migration.mjs` | REMOVER | Script pontual de verificação sem consumidores no projeto. | Excluído |
| `scripts/validate-ui-patterns-views-cutover.php` | CONSOLIDAR / REFATORAR | Validador crítico de corte para UI Patterns; tipagem implementada, eliminando 305 ocorrências. | Mantido & Refatorado |
| `scripts/validate-aculta420-css-scope.php` | CONSOLIDAR / REFATORAR | Gate ativo de escopo de CSS e temas; sincronizado com SDC/UI Patterns e tipado, eliminando 91 ocorrências. | Mantido & Refatorado |
| `scripts/validate-portal-commerce-security.php` | REFATORAR / ISOLAR | Gate ativo de segurança e webhooks; mutação persistente do gateway removida e status tipado. | Mantido & Refatorado |
| `scripts/validate-institution-content.php` | REFATORAR / TIPAR | Gate de validação de home-content.json; tipado com eliminação total de 18 ocorrências. | Mantido & Refatorado |
| `scripts/phpstan-debt-guard.php` | MANTER | Guardrail do baseline e do nível 10; intacto e funcional. | Mantido |
| `scripts/phpstan-debt-report.php` | MANTER | Gerador canônico do relatório de dívida; intacto e funcional. | Mantido |

---

## 3. Homologação e Evidências dos Gates

Todos os gates foram executados localmente e validados:

1. **PHP Linter (`php -l`)**: PASS em todos os scripts modificados e existentes.
2. **PHPStan Nível 10 & Bleeding Edge**:
   - `vendor/bin/phpstan analyse --configuration=phpstan.neon`: **PASS** (Exit code 0).
   - Erros não suprimidos: **0**.
3. **PHPStan Debt Guard**:
   - `php scripts/phpstan-debt-guard.php check`: **PASS** (361 entradas removidas legitimamente, 1 contagem reduzida).
   - `php scripts/phpstan-debt-guard.php diff`: **PASS** (todos os arquivos PHP tocados reduziram sua dívida).
4. **Bats Tests**:
   - `bats tests/shell/phpstan-debt-guard.bats`: **PASS** (20/20 testes aprovados, 0 falhas).
5. **Gates de Validação do Tema V2 e SDC**:
   - `php scripts/validate-ui-patterns-views-cutover.php`: **PASS** (98 checks).
   - `php scripts/validate-aculta420-css-scope.php`: **PASS** (215 checks).
   - `php scripts/validate-aculta420-v2-foundations.php`: **PASS** (85 text contrast pairs; 6 accent stripe pairs).
   - `php scripts/validate-institution-content.php`: **PASS** (30 checks).
6. **Segurança e Análise Estática**:
   - `semgrep --config=auto`: **PASS** nos arquivos modificados (0 vulnerabilidades introduzidas).
   - Nenhuma chamada mutável persistente em `scripts/validate-portal-commerce-security.php`.
