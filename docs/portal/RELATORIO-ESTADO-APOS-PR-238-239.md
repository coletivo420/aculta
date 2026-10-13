> Snapshot anterior à mudança de identidade do submódulo. Estado atual e plano de migração: [ACULTA-ASSOCIADO-ID.md](../modules/ACULTA-ASSOCIADO-ID.md) e [ROADMAP.md](ROADMAP.md).

# Estado atual do Portal após as PRs #238 e #239

Base verificada: `origin/main` em `58ae82b` (PR #238; merge em 2026-10-13 UTC). A PR #239 também está integrada. Verificações locais executadas em PHP 8.4.26; este documento não representa homologação Runtime.

## Código e fronteiras

- `aculta_portal`: `0.3.0-dev.28`. A PR #238 ajusta o acesso ao armazenamento de documento e os formulários de Outros Dados; preserva o contrato: Portal controla funcionalidade e dados, `aculta_id_cpf` cifra/valida/persiste, ACULTA420 apresenta.
- `aculta_id_cpf`: `0.2.0-dev.2`, opt-in; Runtime segue **DEFERRED**. Sem provisionamento das chaves/campos requeridos, gravação não fica habilitada.
- `aculta420`: `0.6.0-dev.15`. A PR #239 mantém fixa somente a barra multidomínio. O cabeçalho de domínio rola com a página. A verificação em Chrome no servidor de testes, desktop e mobile, está registrada no [CHANGELOG do tema](../../web/themes/custom/aculta420/CHANGELOG.md).
- Fronteira módulo/tema: gate atual PASS, 684 verificações e zero classes de design no módulo. Não houve alteração de tema nesta revisão documental.

## Verificações locais desta revisão

| Verificação | Resultado |
| --- | --- |
| `vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/tests/src/Unit` | PASS — 63 testes, 118 asserções |
| `vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_id_cpf/tests` | PASS — 47 testes, 107 asserções |
| `php scripts/validate-aculta-portal-drupal11.php` | PASS — 659 checks |
| `php scripts/validate-module-theme-boundary.php` | PASS — 684 verificações |
| `php scripts/validate-environment-addresses.php` | PASS — 1316 arquivos |
| `php vendor/drush/drush/drush.php scr scripts/validate-aculta420-foundation.php` | FAIL por DT-T19: durações literais fora de `tokens.css` |

Os testes PHPUnit exibiram aviso de que `sites/simpletest/browser_output` não é gravável; não impediu a execução nem alterou o resultado dos testes. A invocação direta do gate Foundation com `php` não faz bootstrap Drupal; o resultado acima foi obtido pelo Drush.

## Dívidas e decisões

- **S0 concluída:** DT-P16, DT-P17 e DT-P18 continuam resolvidas; a busca documental não encontrou as referências obsoletas descritas por esses itens. A auditoria P10-R foi mesclada na PR #91; sua cópia histórica pode ser lida do commit `9c95420`.
- **S2 parcial:** `strict_types`, Kernel de login e Kernel de domínio estão presentes. O teste de Kernel de pagamento permanece dependente de decisão sobre instalar Commerce no ambiente de teste (DT-P03).
- **S3:** DT-O06 e DT-O07 estão resolvidas; DT-P05 é decisão de entrega em produção para o fim do projeto. PHP 8.5, CAPTCHA completo e sandbox de pagamento permanecem abertos/adiados conforme DT-P04, DT-P08, DT-P11 e DT-P07.
- **S4:** DT-G01 tem decisão registrada; DT-P10 (módulos administrativos ativos em produção) segue aberto.
- **DT-T19:** aberta; o gate Foundation falha pelas durações `180ms` e `150ms` em `portal-account.css`.
- **DT-O12:** falhas do `aculta_deployer` permanecem registradas. Não foram reexecutados nesta revisão.
- **DT-O01:** a contagem antiga de 100 branches/64 mescladas está obsoleta. Reconciliar branches atuais antes de qualquer limpeza; a dívida continua aberta.
- Outros itens de produto, Runtime, produção e decisões do responsável permanecem no [registro único de dívidas](../operations/DEBT-REGISTER.md); esta revisão não os fecha por inferência.

## Limites

Não houve conexão ao Runtime, migração de banco, limpeza de branches, teste de gravação de documento ou alteração funcional. A barra fixa foi validada na PR #239; Outros Dados da PR #238 está com **RUNTIME STATUS: DEFERRED**. O relatório [de 2026-10-12](RELATORIO-ESTADO-2026-10-12.md) permanece preservado como snapshot anterior a essas duas PRs.
