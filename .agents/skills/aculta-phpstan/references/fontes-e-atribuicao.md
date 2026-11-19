# Fontes técnicas, inspiração e atribuição

A skill `aculta-phpstan` foi **redigida para o ACULTA**, sintetizando princípios técnicos de duas metodologias externas complementares: a DrupalTools, com foco em executar e corrigir PHPStan em Drupal; e a voku, com foco em contratos rigorosos, tipagem na origem, array shapes e supressões restritas. A revisão de tipos da voku também foi consultada como apoio.

**Não foram copiados arquivos, exemplos ou longos trechos dessas skills**, nem foram instaladas como dependências. A política operacional própria do ACULTA prevalece.

## Skills externas

- [DrupalTools - drupaltools-phpstan](https://github.com/drupaltools/skills/tree/master/skills/drupaltools-phpstan). Repositório `drupaltools/skills`, licença GPL-2.0. Inspiração na sequência de diagnóstico Drupal, execução, leitura de mensagens e verificação após corrigir.
- [voku - php-static-analysis](https://github.com/voku/agent-skills/tree/main/skills/php-static-analysis). Declara licença MIT no `SKILL.md`. Inspiração em tipos nativos, formas de dados, contratos honestos, tipos no produtor e supressões pontuais.
- [voku - code-review-type-safety](https://github.com/voku/agent-skills/tree/main/skills/code-review-type-safety). Declara MIT no `SKILL.md`. Apoio para revisão de nulidade e assimetria de contratos.

Qualquer incorporação futura de texto ou código dessas fontes exige revisão de termos de licença, atribuição e eventuais obrigações de distribuição. Referência e síntese de conceitos não constituem instalação de terceiros.

## Documentação oficial

- [PHPStan - rule levels](https://phpstan.org/user-guide/rule-levels)
- [PHPStan - error identifiers](https://phpstan.org/error-identifiers)
- [PHPStan - PHPDoc e generics](https://phpstan.org/writing-php-code/phpdoc-types)
- [PHPStan - discovering symbols](https://phpstan.org/user-guide/discovering-symbols)
- [PHPStan - stub files](https://phpstan.org/user-guide/stub-files)
- [PHPStan - baseline](https://phpstan.org/user-guide/baseline)
- [PHPStan - ignoring errors](https://phpstan.org/user-guide/ignoring-errors)
- [Drupal - PHPStan](https://www.drupal.org/docs/develop/development-tools/phpstan)
- [Drupal - getting started](https://www.drupal.org/docs/develop/development-tools/phpstan/getting-started)
- [Drupal - fixing undefined methods](https://www.drupal.org/docs/develop/development-tools/phpstan/fixing-reports-of-undefined-methods)
- [Drupal Core - baseline](https://www.drupal.org/docs/develop/development-tools/phpstan/phpstan-in-drupal-core)

Documentos públicos podem descrever versões diferentes das instaladas. Em divergência, prevalecem o código atual, `composer.lock`, configuração local e evidência dos testes.
