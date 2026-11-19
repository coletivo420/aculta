# ACULTA - Retirada de aculta_learning_contracts

Data: 2026-11-18. Escopo: refatoração Portal/LMS sem migração de dados educacionais.
Estado: contratos migrados; **desinstalação operacional e exclusão do manifesto/configuração pendentes**.

## Arquitetura final

- `aculta_portal/src/Learning/AccountCoursesReaderInterface.php`: interface de cursos da Conta.
- `aculta_portal/src/Learning/AccountCourseSummary.php`: DTO neutro de cursos autorizados.
- `aculta_portal/AccountCoursesManager.php` e `Presentation/AccountCoursePresenter.php`: apresentação opcional, sem imports LMS.
- `aculta_lms/src/Account/AccountCoursesReader.php`: implementação com Group, membership, progresso e ACL.
- `aculta_lms/LearningDashboardManager.php`: consumo do mesmo contrato, sem wrappers duplicados.
- Contratos de jornada e sumário do GanjaLab continuam pertencendo a `aculta_lms`.

## Preflight obrigatório no homelab

1. Não atualizar o Runtime automaticamente caso isso interrompa a janela de trabalho. Identificar estado real com `git status --short`, `git rev-parse HEAD`, `drush status`, `drush pml --status=enabled`, `drush config:status` e rastrear a configuração ativa de `core.extension`.
2. Executar busca completa: `git grep -n -E 'aculta_learning_contracts|Drupal\\aculta_lms\\Learning\\AccountCourse(Summary|sReaderInterface)'` (expressão pode precisar ser ajustada à semântica de escape do grep). Nenhum consumidor PHP deve apontar para símbolos aposentados. Os arquivos do módulo-túmulo e a documentação são exceções provisórias.
3. Verificar dependentes e configuração ativa antes de `pm:uninstall`; este módulo não possui schema ou entidades próprias, mas a desinstalação ainda é operação estrutural.
4. Auditar PHPStan baseline, scripts, recipes, documentação, testes, implementações de DI, render e cache. Não regenerar baseline para esconder erros.
5. Rodar PHP lint/PHPCS, PHPUnit Unit Portal/LMS e Kernel dos consumidores, `php scripts/validate-aculta-portal-drupal12.php`, `vendor/bin/phpstan analyse`, `php scripts/phpstan-debt-guard.php check` e `php scripts/phpstan-debt-guard.php diff` conforme instruções atuais.

## Cutover seguro

1. Obter a autorização operacional necessária ao preflight da política local para `pm:uninstall`; realizar snapshot auditável sem versionar segredos ou bancos.
2. **Com o manifesto ainda disponível**, executar `vendor/bin/drush pm:uninstall aculta_learning_contracts -y` somente no Runtime de desenvolvimento verificado. Se não estiver instalado, registrar a evidência e não executar à força.
3. Exportar o estado pertinente de `core.extension`, remover exclusivamente a entrada `aculta_learning_contracts: 0` da configuração sincronizada; não importar de forma global alterações estranhas à tarefa.
4. Excluir `web/modules/custom/aculta_portal/modules/aculta_learning_contracts/` integralmente e `docs/operations/status/aculta_learning_contracts.yml`. Regerar o README/status raiz somente pelo gerador existente.
5. Executar `drush cr`, `drush config:status`, `drush updatedb:status`, testes e smoke HTTP da Conta e GanjaLab. Preservar route IDs, ACL, perfis, domínios e cache; confirmar ausência de regressões de matrículas, notas e certificados.
6. Revisão independente R2; commit/push fast-forward na `dev` sem `--no-verify` e sem force push; observar CI PHPStan nível 10.

## Aceite

- O módulo `aculta_learning_contracts` não existe na árvore ou configuração ativa/exportada.
- Portal pode ser instanciado sem LMS e renderiza lista vazia; com LMS, o alias resolve para `aculta_lms.account_courses_reader`.
- Nenhuma classe/alias PHP usa namespace removido; dois IDs de serviço existentes preservados.
- Sem mudanças nas entidades, dados, ACLs e roteamento LMS/Group.
- Gates PASS somente se executados, com evidências no relatório final.
