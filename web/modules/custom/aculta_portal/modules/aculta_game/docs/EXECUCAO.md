# Execução por fases — ACULTA Game

Autorização de 2026-10-15: implementar por fases e commits na mesma PR #286.
Esta autorização substitui a restrição anterior de abertura somente documental.

## G1 — fonte única e integração inicial

Classificação MINOR: Game `0.1.0-dev.1`, User `0.4.0-dev.3`, Portal `0.4.0-dev.33`.

Inventário do Runtime confirmado em `toca`, raiz `/home/aculta`, URI de teste
`https://aculta.toca.net.br`, MariaDB `aculta_runtime`, Core 11.4.10 e PHP 8.4.26.
PHP 8.5 não está disponível para validação neste host.

- Entidade `aculta_badge_award`: provider `aculta_user`; dois registros, um
  `primeiro_comentario_forum` e um `perfil_basico`; zero pares ativos duplicados.
- Nenhum update de banco pendente. Configuração apresenta divergências amplas
  prévias, inclusive `core.extension`; não realizar import/export global.
- `BadgeCatalog`, `BadgeService` e entidade permanecem no User com os mesmos
  identificadores, storage e campos. Nenhuma migração ou alteração de schema.
- `BadgeEvaluationHooks` transferido integralmente para Game, sem avaliador
  duplicado. G1 preserva os critérios; suas correções pertencem a G2.
- Game depende de Portal e User; não cria dependência inversa, tabela ou cópia de
  concessões. Desativar Game interrompe os gatilhos, sem remover os fatos no User.
- O Status descobre Game pelo registro de extensões. Não há helper novo em G1.

Digest SHA-256 de IDs e valores serializados antes da ativação:
`76d19ccb5767ed0c6ca428e9dc5c8d3d9e011e15f49368c3a8182eb72978e53d`.
O digest permite comparar preservação sem registrar dados pessoais neste documento.

### Fontes de API conferidas

- [Metadados de extensão](https://www.drupal.org/docs/develop/creating-modules/let-drupal-know-about-your-module-with-an-infoyml-file).
- [Hooks OOP e autowiring](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Hook!Attribute!Hook.php/class/Hook/11.x).
- Código instalado: `web/core/lib/Drupal/Core/Hook/Attribute/Hook.php` e APIs de
  entidade; baseline do projeto preservado, sem declaração de Drupal 12 ready.

### Validação

- PHP lint PASS; gate Drupal 11+ PASS (773), fronteira PASS (1036), endereços
  PASS (1722); `git diff --check` PASS. Os gates incluem o checkout compartilhado.
- `drush pm:enable aculta_game -y` e `cache:rebuild` concluídos no Runtime
  confirmado. A instalação acionou a atualização de tradução pt-br de um objeto
  de configuração pelo mecanismo existente; não houve importação global.
- Container resolveu `Drupal\aculta_game\Hook\BadgeEvaluationHooks`; a classe
  antiga não existe. Extensão habilitada e provider da entidade continua User.
- Dois registros após ativação; digest integral idêntico ao anterior. IDs, valores
  e revogações preservados, sem escrita de concessões durante a verificação.
- Nenhuma importação histórica ou envio de aviso executado. G1 concluída;
  próxima fase G2 corrige critérios e integridade. UI, missões e privacidade ainda
  pendentes; nenhuma declaração de validação ponta a ponta dessas fases.

## G2 — implementação dos gatilhos e proteção da API

Classificação PATCH: Game `0.1.0-dev.2`, User `0.4.0-dev.4`, Portal `0.4.0-dev.34`.

- Comment insert/update: publicado agora, antes não publicado; pai é tópico
  publicado. Aprovação posterior passa pelo mesmo avaliador idempotente.
- Wiki: revisão publicada/default nova, ou aprovação de revisão antes não
  publicada. Um save comum sem nova revisão não concede. Autoria vem da revisão.
- LMS: interface instalada `CourseStatusInterface::getUserId()`, ausente no
  caminho anterior que chamava `getOwnerId()`; tratar insert e transição a passed.
- Pedido: apenas transição para completed; preservar a abrangência anterior de
  Apoiador até a decisão de critérios, sem introduzir recompensas por gasto.
- Entidades `isSyncing()` são ignoradas, como usa o Migrate Core instalado.
  Importadores próprios devem preservar esse contrato. Conta bloqueada ou com
  papel `usuarios_legados_forum` não recebe nova conquista pela API.
- Lock persistente por pessoa/conquista, com tentativa adicional após espera
  curta. Falha de lock lança exceção, em vez de perder uma avaliação silenciosamente.
  Callback do Transaction Manager libera após a transação externa, inclusive
  rollback; fora de transação libera ao fim da operação. Revogação usa o mesmo lock.
- Sem restrição SQL nova: a serialização vale para chamadas pela API única,
  dentro do prazo do lock (120 segundos). Código não deve criar concessões
  diretamente pelo storage. Unicidade em escrita arbitrária não está comprovada.

Fontes: [Lock API](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Lock!LockBackendInterface.php/interface/LockBackendInterface/11.x),
[callback após transação](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Database!Transaction!TransactionManagerInterface.php/function/TransactionManagerInterface::addPostTransactionCallback/11.x)
e [entidade original](https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Entity!EntityInterface.php/function/EntityInterface::getOriginal/11.x).
Conferidos Core instalado, `EntityContentBase` do Migrate, armazenamento de revisão
e interface do LMS 1.2.3. Perfil básico/WhatsApp continuam critérios anteriores,
pendentes de decisão de produto; nenhum dado adicional é solicitado nesta fase.

### Validação e limites

PHP lint e gates Drupal 11+ (773), fronteira (1036), endereços (1722) PASS;
`git diff --check` PASS. Cache rebuild concluído; container resolveu o serviço com as novas dependências,
anônimo inelegível e os dois registros mantiveram o digest integral de G1. Não foram executadas simulações concorrentes ou fluxos de
publicação/aprovação de conteúdo. **RUNTIME STATUS: DEFERRED** para a matriz
funcional de G2; a implementação não encerra sua homologação. G3 permanece pendente.
