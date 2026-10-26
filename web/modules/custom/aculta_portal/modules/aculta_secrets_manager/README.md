# ACULTA Secrets Manager

Painel administrativo de **credenciais do ambiente**, em `/painel-administrativo/aculta/segredos`,
com duas abas independentes: **Ambiente de Produção** e **Ambiente de Testes**.

## O que este submódulo faz

- Interface e rotas do painel (página, popup de revelação, alteração e remoção).
- Abas: a aba do ambiente ativo mostra estado e ações; a do outro ambiente mostra só os metadados
  públicos do contrato, sem consultar valores nem a existência real da credencial.
- Revelação por ação explícita (popup), sem cache e com `Cache-Control: no-store`.

## O que este submódulo NÃO faz

- Não armazena nem resolve credenciais. O núcleo é `aculta_portal.secrets_manager`
  (`Drupal\aculta_portal\Secrets\SecretsManager`), que é usado pelos consumidores.
  O Portal não pode depender deste submódulo (ciclo), então o núcleo fica lá.
- Não cria um cofre concorrente com Drupal Key nem altera o loader `web/sites/default/aculta.secrets.php`.

## Regra central

**Aba não é ambiente de execução.** O ambiente é definido por configuração confiável do servidor
(ACULTA Deployer, `var/deployer/environment.json`). Trocar de aba não muda o ambiente, não libera
acesso a credenciais de outro ambiente e não faz requisição de leitura de valores.

## Dependências

- `aculta_portal:aculta_portal` (núcleo de segredos, Deployer, permissões de base).
- Permissão: `administer aculta secrets` (declarada aqui, restrita). Ver CHANGELOG para a decisão
  de não separar a permissão nesta versão.

## Instalação e rollback

- Habilitar: `drush en aculta_secrets_manager` (depois do deploy, no ambiente alvo).
- Desabilitar (rollback): as rotas e o menu saem; o núcleo em `aculta_portal` continua funcionando
  para os consumidores, e os links do Portal que apontam para `aculta_portal.secrets_import` passam
  a falhar até o módulo ser reabilitado. Por isso o módulo deve ficar habilitado junto com o Portal.

## Testes

- Unit: `vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_secrets_manager/tests/src/Unit`
  (11 testes: abas, ausência de valores e ações fora do ambiente, negação de revelar/editar/apagar
  fora do escopo, cabeçalho no-store).
- Kernel (rotas e permissões): `vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_secrets_manager/tests/src/Kernel`, com `SIMPLETEST_DB` do banco de teste dedicado (`docs/operations/BANCO-DE-TESTE.md`). Passa com deprecações de contrib.
- Functional: pendente. Um Kernel isolado não sobe, porque `aculta_portal` exige a pilha ACULTA inteira (ver ROADMAP).
