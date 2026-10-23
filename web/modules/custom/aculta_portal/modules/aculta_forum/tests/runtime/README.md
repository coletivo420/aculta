# Runtime controlado do fórum restrito (F-GROUP)

Scripts da validação F-GROUP no Runtime de testes. Eles alteram estado do site
e devem rodar somente no servidor de testes (`*.aculta.toca.net.br` / host
`toca`, Runtime `aculta_runtime`), com backup validado, janela reservada e
autorização do responsável. Produção não participa.

## Arquivos

| Script | Modo | Efeito |
| --- | --- | --- |
| `fgroup-provision.php` | `provision` | Cria Group type `forum_associados`, relação de tópico, role de membro com `view group_node:aculta_forum_topic entity`, um Group, quatro contas `fgroup_teste_*`, um tópico publicado e as situações em `aculta_associado`; ativa `restricted_group_type` e executa o rebuild de `node_access`. |
| `fgroup-provision.php` | `teardown` | Desliga a frente, executa o rebuild, remove Group, tópico, contas, associações, role e Group type e invalida caches. O conteúdo preexistente não é tocado. |
| `fgroup-measure.php` | leitura | Mede `node_access`, `node->access('view')` e a query com tag `node_access` por conta; cria e remove um anexo privado de teste para medir `media_access` e o download do File. |

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

## Limitações

- O navegador não está aqui: o teste visual fica em
  `tests/browser/aculta-forum/fgroup-runtime.py` e exige Firefox ESR visível em
  `DISPLAY=:0`, geckodriver e links únicos de login gerados em
  `conta.aculta.toca.net.br`.
- O `measure` cria o anexo com autor uid 1 para que o Comment seja publicado;
  contas de teste sem permissão de moderação têm o Comment pendente, como no
  fluxo real.
- Busca e contagem sob grants, cache de página e regressão do fórum público no
  Runtime continuam fora destes scripts.
