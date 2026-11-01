# Runtime controlado do fórum restrito (F-GROUP)

Scripts da validação F-GROUP no Runtime de testes. Eles alteram estado do site
e devem rodar somente no servidor de testes (`*.aculta.toca.net.br` / host
`toca`, Runtime `aculta_runtime`), com backup validado, janela reservada e
autorização do responsável. Produção não participa.

## Arquivos

| Script | Modo | Efeito |
| --- | --- | --- |
| `fgroup-provision.php` | `provision` | Cria Group type `forum_associados`, relação de tópico, role de membro **sincronizada insider** com `authenticated` e `view group_node:aculta_forum_topic entity`, um Group, quatro contas `fgroup_teste_*`, um tópico restrito, um tópico público e as situações em `aculta_associado`; ativa `restricted_group_type` e executa o rebuild de `node_access`. |
| `fgroup-provision.php` | `teardown` | Desliga a frente, executa o rebuild, remove Group, tópicos, galerias, Media, Files, contas, associações, role e Group type e invalida caches. O conteúdo preexistente não é tocado. |
| `fgroup-measure.php` | leitura | Mede `node_access`, `node->access('view')` e a query com tag `node_access` por conta, para o tópico restrito e o público; mede a listagem global; cria e remove um anexo privado e uma galeria de teste para medir `media_access`, `file->access('download')` e `entity_access` da galeria. |
| `fgroup-search.php` | leitura | Indexa o `aculta_forum` e consulta `keys=fgroup` com `range(0,1)` por conta dentro do purpose `forum`; imprime total e página, provando que o filtro de grants ocorre antes da paginação. |
| `fgroup-index-freshness.php` | escrita na relação | Remove e recria a relação do tópico com o Group, mede o índice antes/depois da reindexação e confirma que o item sai do servidor no intervalo (fail closed). |

## Execução

No checkout do servidor de testes, com Drush funcional:

```sh
php vendor/drush/drush/drush.php status
php vendor/drush/drush/drush.php sql:dump --gzip \
  --result-file=/home/<usuario>/private/aculta/backups/aculta_runtime-pre-fgroup.sql
gzip -t /home/<usuario>/private/aculta/backups/aculta_runtime-pre-fgroup.sql.gz
sha256sum /home/<usuario>/private/aculta/backups/aculta_runtime-pre-fgroup.sql.gz

php vendor/drush/drush/drush.php php:script \
  web/modules/custom/aculta_portal/modules/aculta_forum/tests/runtime/fgroup-provision.php provision
php vendor/drush/drush/drush.php php:script \
  web/modules/custom/aculta_portal/modules/aculta_forum/tests/runtime/fgroup-measure.php
php vendor/drush/drush/drush.php php:script \
  web/modules/custom/aculta_portal/modules/aculta_forum/tests/runtime/fgroup-provision.php teardown
```

O `provision` imprime um JSON com os IDs criados. O `measure` imprime JSON com
acesso, listagem e anexos por conta. Nenhum dos dois imprime segredo.

## Critérios de interrupção e reversão

Interromper se qualquer conta não elegível receber `can_view: true`, se o
tópico restrito tiver grant `all:0` ou se o rebuild não concluir. Nesse caso,
rodar o `teardown`; se o estado não voltar ao inicial, restaurar o backup
validado. O `teardown` não apaga conteúdo preexistente.

## Busca

O `fgroup-search.php` roda entre o provision e o teardown. Ele indexa o
`aculta_forum` e consulta por conta. A query precisa rodar dentro de
`aculta_portal.domain_purpose->runInPurpose('forum', ...)`: fora do purpose, o
`ForumSearchQuerySubscriber` adiciona a condição sentinela
`forum_search_domain = __forum_scope_denied__` e o resultado é zero em CLI.
A View pública `/pesquisar?keys=fgroup` pode ser medida por HTTP com e sem o
cookie de login; espera-se o público para todos e o restrito só para o ativo
com membership.

## Cache, regressão e revogação

O `measure` não faz HTTP. A matriz de cache/regressão é medida com `curl`
seguindo redirects (`-L`) e contando "Tópico restrito" e "Tópico público" no
corpo, com `X-Drupal-Cache` e `X-Drupal-Dynamic-Cache`. A revogação é medida
gerando um link único de login, suspendendo a conta com
`aculta_user.active_associate->definir(uid, 'suspenso', 1, ...)` e reativando em
seguida, sem `drush cr` entre as etapas: espera-se 200 → 403 → 200.

## Limitações

- O navegador não está aqui: o teste visual fica em
  `tests/browser/aculta-forum/fgroup-runtime.py` e exige Firefox ESR visível em
  `DISPLAY=:0`, geckodriver e links únicos de login gerados em
  `conta.aculta.toca.net.br`.
- O `measure` cria o anexo com autor uid 1 para que o Comment seja publicado;
  contas de teste sem permissão de moderação têm o Comment pendente, como no
  fluxo real.
- `private://forum/anexos` pertence ao usuário do PHP-FPM e não é gravável pelo
  Drush como `piradopirata`; o `fgroup-measure.php` grava o arquivo na raiz
  `private://`, que é gravável. Use `fgroup-measure.php keep` para preservar o
  arquivo e medir `/system/files/<nome>` por HTTP antes do teardown. O
  resultado traz `file_exists`, `image_file_exists` e `download_url`.
- O cache da busca é medido por `curl` em `/pesquisar?keys=fgroup` antes/depois
  de restringir e reindexar: a primeira resposta deve ser MISS sem o título.
- Olivero exige trocar `system.theme default`, medir e restaurar `aculta420` no
  mesmo comando, com `trap`.
