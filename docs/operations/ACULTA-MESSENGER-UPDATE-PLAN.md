# Plano de atualização pendente — ACULTA Messenger

**Ambiente inspecionado:** Homelab MAIN, 2026-10-26 (`https://aculta.toca.net.br`, Drupal 11.4.10, banco `aculta_runtime`).  
**Estado:** somente leitura. Nenhum hook, post-update, importação, fila ou e-mail foi executado.

## Estado confirmado

`drush updatedb:status --uri=https://aculta.toca.net.br` reportou cinco hooks
`aculta_messenger_update_10008`–`10012` e três post-updates:

- `private_message_email_preferences`
- `shared_email_preferences`
- `game_achievement_notices`

Uma consulta agregada read-only à tabela `users_data`, limitada às chaves
legadas dessas migrações, encontrou cinco registros/contas em
`aculta_user.game_achievement_notice_queue`. Não retornou linhas para
`private_message.receive_notification`, `avisos_resumo_email`,
`avisos_resumo_forum_resposta` ou `avisos_resumo_forum_atualizacao`. Nenhum UID,
valor de preferência ou conteúdo de fila foi lido ou registrado.

As migrações de preferências preservam uma escolha Messenger já existente antes
de copiar o legado e removem a chave antiga somente depois de gravar a nova.
A migração de conquistas revalida conta ativa, award, titularidade, revogação e
definição do badge; publica pelo contrato Messenger e só então remove a fila
legada daquela conta. Falha de publicação interrompe o caminho antes da
remoção, permitindo repetição idempotente. Os hooks 10008–10012 instalam defaults
de configuração do módulo; não executam transporte externo.

## Evidência automatizada existente

- `PrivateMessageEmailPreferenceMigrationTest` e
  `PreferenceMigrationPostUpdateKernelTest` cobrem a cópia das preferências
  privadas e preservação de escolhas já existentes.
- `SharedEmailPreferenceMigrationTest` e
  `SharedEmailPreferenceMigrationKernelTest` cobrem migração em lote,
  preferências explícitas e chaves antigas.
- `GameAchievementNoticePersistenceKernelTest` cobre revalidação do award,
  repetição, revogação e cursor/fila preservados em falha do publisher.
- A suíte combinada Messenger/Mailer/Confirmador teve evidência registrada de
  112 testes / 1.504 assertions PASS em MariaDB isolado. Isso não homologa os
  cinco hooks contra os dados do Runtime.

## Preflight antes de qualquer execução no Runtime

1. Obter autorização operacional específica e janela; confirmar novamente host,
   checkout, URI, banco e `updatedb:status`.
2. Criar snapshot consistente do banco `aculta_runtime` e provar restauração em
   banco isolado antes da janela. Não usar rollback parcial de `users_data`,
   configuração ou Message: o versionamento dos hooks/post-updates também deve
   voltar junto com os registros.
3. Repetir somente a consulta agregada deste plano. Guardar contagens protegidas
   no registro operacional, sem exportar UIDs nem valores individuais.
4. Em clone isolado, executar `updatedb`, confirmar conclusão de todos os hooks
   e post-updates, reexecutar `updatedb:status`, validar settings e preferências
   migradas e rodar os testes focais acima.
5. Antes de retomar cron, workers ou digest, confirmar que os novos avisos estão
   corretos e que nenhum job enviará e-mail real. Não processar filas nesta
   atualização.

## Procedimento futuro e rollback

Somente após autorização específica, o operador poderá executar, no checkout
canônico e durante a janela aprovada:

```bash
php vendor/drush/drush/drush.php --root=web --uri=https://aculta.toca.net.br updatedb
```

Não combinar com `cim`, `cron`, execução de filas, envio SMTP ou atualização de
permissões. Se qualquer hook/post-update falhar ou a validação revelar uma
mudança incorreta, manter cron/workers parados e restaurar o snapshot integral
do banco antes de reabrir o site; depois confirmar a versão de código e
`updatedb:status` correspondem ao snapshot. Não improvisar reversão por SQL.

## Pendência operacional

O código e os testes estão preparados, mas a operação real continua **DEFERRED**
até existir autorização específica, snapshot verificável e ensaio de restauração.

## Ensaio local em banco isolado (2026-10-26)

- Site descartável `msg3_` no MariaDB de teste, perfil `standard`, schema do Messenger retrocedido
  a 10007.
- Pendências repetidas: hooks `10008`–`10012` e post-updates `private_message_email_preferences`,
  `shared_email_preferences` e `game_achievement_notices`.
- Dados sintéticos: três contas com `private_message.receive_notification`, `avisos_resumo_email`,
  `avisos_resumo_forum_resposta`/`_atualizacao` e fila `game_achievement_notice_queue`.
- Resultado: o `updatedb` concluiu os oito itens; as chaves legadas foram removidas e
  `avisos_email_mode_*` foram criadas por conta; a fila de conquistas foi consumida; o
  `updatedb:status` seguinte não tinha pendências.
- Desvio registrado: a instalação no commit anterior ao 10008 (`e9abaa366`) falha com
  `PreExistingConfigException` no ciclo Portal↔MP. Por isso o ensaio instalou o código atual
  (`d74a6779`) e retrocedeu o schema a 10007; os cinco hooks são `installDefaultConfig` e foram
  executados de forma idempotente.
- Limitações: não foi criado award de conquista válido — o caminho de publicação foi coberto apenas
  pela revalidação negativa (award inexistente), e os testes Kernel de conquista seguem como
  evidência do caminho positivo. O tempo de execução não foi medido porque `/usr/bin/time` não
  existe no host.
- Limpeza: 287 tabelas `msg3_` removidas pela Schema API (0 restantes); settings e diretório privado
  descartáveis removidos; nenhuma credencial em log.

## Rollback do ensaio e da janela

- Os hooks 10008–10012 só instalam config default; reverter é apagar a config nova ou restaurar o
  snapshot. Os post-updates de preferências removem chaves legadas de `users_data` e criam
  `avisos_email_mode_*`; a reversão exige restaurar o snapshot integral (não há cópia das chaves
  antigas depois da execução). A migração de conquistas consome a fila `game_achievement_notice_queue`
  e publica Messages; também não é reversível por SQL.
- Portanto, antes da janela: snapshot consistente de `aculta_runtime`, restauração provada em banco
  isolado e cron/workers parados. O ensaio local não substitui o snapshot.
