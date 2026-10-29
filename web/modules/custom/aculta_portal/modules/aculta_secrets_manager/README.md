# ACULTA Secrets Manager

**Versão:** `0.1.0-dev.6`.

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

- Unit no banco `aculta_simpletest`: 13 testes / 56 assertions PASS. Cobre abas,
  ausência de valores e ações fora do ambiente, negação de revelar/editar/apagar fora do escopo,
  escape, log sem valor e cabeçalho no-store.
- Kernel Routes não revalidou nesta rodada: a suíte encerrou antes de asserções porque
  `commerce_shipping` não está disponível no checkout de teste. Nenhum módulo foi instalado.
- PHPCS Drupal/DrupalPractice (`--extensions=php`), PHP lint do submódulo e `node --check`
  dos JavaScript: PASS.
- HTTP com sessão real no servidor de desenvolvimento: 9/9; Firefox ESR visível: painel
  16/16, popups sem envio 7/7 e foco/alvos por largura 28/28.
- Revisão manual das capturas encontrou a tabela ilegível a 320 px e uma coluna comprimida
  em linhas verticais a 1280 px; os asserts geométricos atuais não detectam isso. Functional
  de instalação/rollback continua pendente.


Exceção de fronteira: UI administrativa renderizada no Claro mantém CSS mínimo no próprio submódulo, com seletores de classe e `data-portal-ui`. O tema administrativo não executa os hooks do ACULTA420.
