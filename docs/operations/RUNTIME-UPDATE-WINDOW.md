# Janela de updates do Runtime — ensaio de snapshot e restauração

Data: 2026-10-26. Autorização do responsável para copiar o `aculta_runtime` a um banco
descartável: **18:32**. Janela operacional: até 20h. Este documento cobre o ensaio de
snapshot/restauração; **não é a janela** e nada foi executado no Runtime.

## Estado do ensaio: CONCLUÍDO na retomada de 19:15 (o bloqueio inicial fica registrado abaixo)

| Passo | Situação |
|---|---|
| 1. Dump `aculta_runtime` | **Não executado** |
| 2. Criar banco descartável | **Falhou**: `ERROR 1044 (42000): Access denied for user 'aculta_runtime'@'localhost' to database 'aculta_restore_ensaio_l3'` |
| 3. Restaurar | Não executado |
| 4. Comparar contagens | Não executado |
| 5. Prova de boot | Não executado |
| 6. Limpeza | Option-file e diretório de backup já removidos |

O usuário `aculta_runtime` **não tem privilégio de `CREATE DATABASE`**. Conforme a regra
da frente, o ensaio parou aí: não foi usado o `aculta_simpletest`, não foi pedido
privilégio por conta própria e nenhum dump foi gerado.

Ambiente confirmado sem expor valores: `mysql`/`mysqldump` presentes; settings local
declara banco `aculta_runtime`, usuário `aculta_runtime`, host `localhost`, prefixo vazio.
Diretório de backup criado em `/home/piradopirata/worktrees/` com modo 700 e option-file
600; ambos removidos na parada.

### Incidente de manuseio de segredo

Durante a inspeção do arquivo `secrets/aculta-runtime-db.php`, um `grep` casou a linha
inteira e o valor da senha apareceu na saída da sessão. O valor não foi copiado para
documento, commit ou log de aplicação, e o arquivo não foi versionado. **Decisão
explícita do responsável (18:38): a senha do usuário `aculta_runtime` NÃO será trocada.**
Este ensaio não depende de rotação. Daqui em diante, a leitura é sempre por PHP para um
option-file 600, sem imprimir valores.

## Bloqueio de leitura no Runtime (independente do privilégio)

Na conferência final, `drush updatedb:status --uri=https://aculta.toca.net.br` passou a
falhar com:

```
The file "modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/
aculta_pagamentos_mercadopago.services.yml" does not contain valid YAML
```

O checkout servido está com esse arquivo em edição por outra frente (extração Mercado
Pago). O ensaio não alterou o checkout. A lista de pendências deve ser reconferida após
a correção; a última leitura válida (antes da falha) mostrava:

- `aculta_messenger` `10008`–`10012` e post-updates `private_message_email_preferences`,
  `shared_email_preferences`, `game_achievement_notices`.
- `aculta_user` `10013`.
- `aculta_commerce_melhorenvio` `8001` e `8002` existem no código; a pendência no Runtime
  precisa ser reconfirmada quando o YAML voltar a ser válido.

## Pré-requisitos para retomar

1. Banco descartável provisionado pelo responsável (ex.: `aculta_restore_ensaio_l3`) com
   um usuário que tenha `ALL` **somente nesse banco**, ou `GRANT CREATE` ao usuário de
   ensaio. Não usar `aculta_simpletest`.
2. Checkout servido com YAML válido, para que `updatedb:status` volte a responder.

## Procedimento (comandos sem credenciais)

Backup em `/home/piradopirata/worktrees/backup-runtime-ensaio-<data>/`, diretório 700,
arquivos 600. Nunca em `/mnt/disco1`, `/tmp` ou outro host.

```sh
B=/home/piradopirata/worktrees/backup-runtime-ensaio-<data>
install -d -m 700 "$B"

# Option-file escrito por PHP a partir do settings local, sem imprimir valores.
php -r '$a=include "secrets/aculta-runtime-db.php";
  $f="'$B'/client.cnf";
  file_put_contents($f,"[client]\nuser=".$a["username"]."\npassword=".$a["password"]."\nhost=".$a["host"]."\n");
  chmod($f,0600);'

# 1. Dump (medir duração, tamanho e sha256)
start=$(date +%s)
mysqldump --defaults-extra-file="$B/client.cnf" \
  --single-transaction --routines --triggers --hex-blob \
  aculta_runtime > "$B/runtime.sql"
echo "dump_s=$(( $(date +%s) - start ))"
stat -c '%s' "$B/runtime.sql"
sha256sum "$B/runtime.sql" | tee "$B/runtime.sql.sha256"

# 2. Banco descartável já provisionado (não criar com o usuário do Runtime)

# 3. Restauração
start=$(date +%s)
mysql --defaults-extra-file="$B/client.cnf" aculta_restore_ensaio_l3 < "$B/runtime.sql"
echo "restore_s=$(( $(date +%s) - start ))"

# 4. Comparação: lista de tabelas e COUNT(*) por tabela; só números
for db in aculta_runtime aculta_restore_ensaio_l3; do
  mysql --defaults-extra-file="$B/client.cnf" -N -e \
    "SELECT table_name FROM information_schema.tables WHERE table_schema='$db' ORDER BY table_name" |
  while read t; do
    n=$(mysql --defaults-extra-file="$B/client.cnf" -N -e \
      "SELECT COUNT(*) FROM \`$db\`.\`$t\`")
    printf '%s\t%s\t%s\n' "$db" "$t" "$n"
  done > "$B/counts-$db.tsv"
done
# Divergência: diff dos TSV por tabela/contagem; listar apenas nomes divergentes.

# 5. Prova de boot: settings descartável FORA do checkout servido
#    (ex.: worktree em /home/piradopirata/worktrees/…), apontando ao banco restaurado.
#    Nunca alterar o settings.php do checkout servido.
drush status
drush updatedb:status   # deve listar as mesmas pendências do Runtime

# 6. Limpeza
mysql --defaults-extra-file="$B/client.cnf" -e "DROP DATABASE aculta_restore_ensaio_l3"
rm -f "$B/client.cnf" "$B/runtime.sql" "$B/runtime.sql.sha256" "$B/counts-"*.tsv
rmdir "$B"
```

Para o settings descartável, montar um `settings.php` em worktree próprio com as mesmas
chaves de banco, `$settings['hash_salt']` próprio e sem herdar o settings do checkout
servido. O `settings.php` descartável também é removido na limpeza.

## Critério de rollback por módulo

O rollback seguro é **restaurar o snapshot integral** e reconferir `updatedb:status` e a
versão de código do snapshot. Não improvisar SQL nem desfazer apenas partes.

| Módulo | O que a janela altera | Reversível sem snapshot? | Critério de rollback |
|---|---|---|---|
| `aculta_commerce_melhorenvio` `8001`/`8002` | Migra credenciais de `aculta_commerce_melhorenvio.settings` para o token store cifrado e remove registros malformados | **Não** — a config antiga é consumida; a cifra depende das chaves do ambiente | Restaurar snapshot se contagem de tokens migrados ou credenciais de checkout divergirem; validar cotação/etiqueta e `live_mode` antes de reabrir |
| `aculta_lms_resources` `8001` | Cria `field_lms_videos` e componentes de display em `group:lms_course` | **Não** sem perder o campo/valores | Restaurar snapshot se o campo, os displays ou os dados de cursos divergirem; validar Entity Access do Media `remote_video` |
| `aculta_messenger` `10008`–`10012` + post-updates | Instala config default e migra preferências de e-mail e fila de conquistas; remove chaves legadas de `users_data` | **Não** — chaves antigas são apagadas e Messages publicadas | Restaurar snapshot se contagens de `avisos_email_mode_*`, fila de conquistas ou Messages divergirem; cron/workers parados durante toda a janela |
| `aculta_user` `10013` | Cria `aculta_user_nickname` (slug único) e reconcilia role Profiles | **Parcial** — a tabela é reconstruível, mas a reconciliação sobrescreve `field_nickname` dos Profiles | Restaurar snapshot se contagem de claims, conflitos ou apelidos reconciliados divergirem; não reexecutar em cima de dados divergentes |

## Critérios de aceite do ensaio de restauração

1. Dump concluído com `--single-transaction --routines --triggers --hex-blob`, sha256
   registrado, tamanho e duração medidos.
2. Restauração concluída no banco descartável, com duração medida.
3. Mesmo número de tabelas e mesma `COUNT(*)` por tabela; qualquer divergência listada
   por nome de tabela (sem conteúdo).
4. `drush status` e `drush updatedb:status` no banco restaurado mostram o mesmo conjunto
   de pendências do Runtime.
5. Dump, settings descartável e banco descartável removidos; nenhuma credencial em log
   ou documento.

## Retomada

1. Responsável provisiona o banco descartável/usuário com `CREATE` (sem usar
   `aculta_simpletest`). A senha do Runtime não é rotacionada (decisão de 18:38).
2. Corrigir o YAML do submódulo Mercado Pago no checkout servido (owner da extração).
3. Executar este procedimento, registrar tempos/tamanho/sha256 e os nomes de tabelas
   divergentes, se houver.

## Ordem obrigatória do cutover do frete

O cutover do frete (`feat/aculta-commerce-cutover`) remove o `commerce_shipping` do Composer. A ordem abaixo é obrigatória. **Nunca o contrário.**

1. **`drush updatedb` com o vendor atual**, que ainda tem `commerce_shipping`. Roda `aculta_commerce_update_8001` (instala as entidades do motor) e os post-updates de migração (`migrate_shipping_methods`, `retarget_shipments`).
2. **Conferir a migração.** Métodos do contrib têm cópia no motor (`blockersForUninstall()` vazio). O campo `commerce_order.shipments` aponta para `aculta_shipment`. Nenhum pedido foi apagado (contagem antes e depois).
3. **Desinstalar o contrib, passo explícito**, depois da conferência: `drush pm:uninstall commerce_shipping`. Não é post-update, de propósito: é destrutiva e exige o operador acompanhando.
4. **Só então `composer install`** com o `composer.lock` da PR, que já não tem `commerce_shipping`.

Se o passo 1 ou 2 falhar, pare antes do passo 3. O contrib continua instalado e o site segue como estava.

## Limpeza local de branches, worktrees e stashes (2026-10-26)

- Branches locais integrados na PR e de minha autoria foram apagados, com `git branch -d` ou `-D` justificado (ancestral da PR e `git cherry` vazio).
- Worktrees limpos e integrados foram removidos: `frente3-merge-teste` e `aculta-frete-simulador-e0`.
- `/mnt/disco1/tmp/aculta-fronteira-links-secrets`: o worktree deixou de estar registrado no git, mas a remoção parou no meio ("Diretório não vazio"). Restam 7.532 arquivos (376 MB) no diretório. A amostra confere com o commit `c1e210934`, contido na PR. **Não apagar sem decisão**: a remoção pode ter deixado dependências não rastreadas.
- Não removido: `/mnt/disco1/tmp/aculta-token-cipher` (18 alterações pendentes; regra de parar).
- Preservados: `backup/checkout-antes-pr-8499180a6` (bundle verificado em `/home/piradopirata/worktrees/backup-bundles/checkout-antes-pr-8499180a6.bundle`; a branch fica até o fim da janela de updates); stashes de Fórum, Messenger e mailer, com patch em `/home/piradopirata/worktrees/backup-stashes-20261026/`; `stash@{0}` da fase B, só sai depois que o Local 2 confirmar a recuperação.
- Não tocados: branches e worktrees de agentes (`agent/*`), do Local 2 (`feat/aculta-secrets-claro`) e do Local 3 (`docs/aculta-mp-extracao-b0`, `docs/aculta-runtime-snapshot-ensaio`, `feat/aculta-messenger-update-ensaio`, `feat/aculta-user-update-ensaio`).
- Classificação dos 101 commits do backup: ver `ACULTA-CONTINUOUS-EXECUTION.md`.
## Retomada 2 — 2026-10-26 19:09 (bloqueio de credencial)

O responsável informou que o banco descartável e o usuário de restauração **já existem**;
portanto o `CREATE DATABASE` não deveria ser tentado. A busca por credencial foi refeita
**só por nomes**, sem abrir valores:

- `secrets/`: `aculta-runtime-db.php`, `aculta.secrets.env`, `simpletest-db.env`,
  `teste_comum.env`, entre outros. Nenhuma chave de restauração/ensaio/cutover.
- `web/sites/default/`: `settings.php`, `settings.local.php`, `aculta.secrets.php`,
  `services.local.yml`. `aculta.secrets.php` carrega variáveis de ambiente; nenhuma chave
  de banco de restauração.
- `/home/aculta/secrets/`: o mesmo conjunto de `secrets/`.
- Nomes de variáveis de ambiente do processo: nada de banco/restauração.
- Busca por nome de arquivo (`*restore*`, `*restaur*`, `*cutover*`, `*ensaio*`): nenhum
  arquivo de credencial de restauração.

O único usuário acessível com credencial local é o do Runtime. Com ele, somente leitura:

- `SHOW DATABASES` → `aculta_runtime`, `aculta_id_cpf_test`, `information_schema`.
- `SHOW GRANTS` → `ALL` em `aculta_runtime.*` e em `aculta_id_cpf_test.*`.
- Contagem de tabelas: `aculta_runtime` = 588; `aculta_id_cpf_test` = 0 (vazio).

`aculta_id_cpf_test` é o único banco não-Runtime/não-simpletest visível, está vazio e o
usuário do Runtime tem `ALL` nele — mas o nome sugere banco de testes de outro módulo.
**Não foi usado sem confirmação.** Nenhum dump, restauração ou boot foi executado; o
option-file temporário e o diretório de backup foram removidos.

### Indicação necessária do responsável

1. `aculta_id_cpf_test` é o banco descartável declarado para este ensaio?
2. O usuário de restauração é o próprio `aculta_runtime` (com `ALL` nesse banco) ou há uma
   credencial separada, em qual arquivo/chave?
3. Confirmado o par banco/usuário, o ensaio prossegue nos passos 2–6 deste documento,
   incluindo a prova de boot com settings descartável fora do checkout servido.

## Retomada 3 — execução autorizada (2026-10-26, 19:11–19:27)

Decisões do responsável às 19:11: destino = `aculta_simpletest`; remoção das 14 tabelas
sem prefixo autorizada; `sudo` permitido no ambiente local apenas para corrigir
privilégios do MariaDB, documentando cada uso (não foi necessário).

### Pré-escrita e tabelas sem prefixo

- `pgrep -af phpunit`: Kernel do Local 1 em execução (PIDs `261010`/`261033`), com prefixo.
  Não interrompido; os testes seguem com o próprio prefixo.
- Às 19:13, `aculta_simpletest` tinha **0 tabelas** (`SHOW TABLES` vazio). As 14 tabelas
  sem prefixo do incidente **não estavam presentes**; nenhum `DROP TABLE` foi executado.
  Nada com prefixo foi tocado.

### Dump

- Usuário do Runtime, somente leitura, com option-file 600 temporário (sem eco).
- `mysqldump --single-transaction --routines --triggers --hex-blob aculta_runtime`.
- Diretório `/home/piradopirata/worktrees/backup-runtime-ensaio-20261026/` (700).
- **Duração: 5 s. Tamanho: 96.156.440 bytes (≈91,7 MiB).**
- **sha256: `58072497778eef66c95b8446968ea34a4caabfeb1381abc866c19ad81404e10a`.**
- O dump foi apagado após a comparação; só o arquivo de sha256 permanece no diretório até
  a limpeza final.

### Restauração

- Usuário do `aculta_simpletest`, sem prefixo, com option-file 600 temporário.
- **Duração: 16 s.** Nenhum ajuste de privilégio foi necessário; `sudo` não foi usado.
- O banco restaurado **não foi esvaziado**: fica para o ensaio do cutover do frete
  (Local 1).

### Comparação

- 588 tabelas canônicas do Runtime comparadas por `COUNT(*)` com o restaurado:
  **588 linhas de cada lado, zero divergências**.
- Na hora da medição o banco também tinha tabelas **prefixadas** do Kernel do Local 1 em
  execução (632 no total); elas não fazem parte da prova e não foram tocadas.

### Prova de boot (somente leitura)

- Settings descartável fora do checkout servido, apontando ao `aculta_simpletest` sem
  prefixo, com o código do worktree limpo (`3c810a78a`, Drupal 11.4.10).
- `drush status`: `Database : Connected`, `Drupal version : 11.4.10`.
- `drush updatedb:status`: mesmo conjunto de pendências do Runtime — Messenger
  `10008`–`10012` e os três post-updates (`private_message_email_preferences`,
  `shared_email_preferences`, `game_achievement_notices`), além de `aculta_user 10013`.
- O settings descartável foi removido; nenhuma credencial ficou em log.

### Aviso obrigatório

> **`aculta_simpletest` contém cópia do Runtime (dados pessoais) sem prefixo desde 19:16;
> é proibido rodar `site:install` sem `--db-prefix`; apagar a cópia ao fim do ensaio do
> cutover.**

Acesso restrito ao usuário `aculta_simpletest` e aos administradores do MariaDB; não
exportar, não copiar para outro host e não versionar. Nenhuma credencial foi impressa.

## Complemento — pendências com o código do PR head (2026-10-26 19:26)

Repetição somente do `drush updatedb:status` contra a cópia restaurada em
`aculta_simpletest`, com settings descartável recriado e apagado ao final, usando o código
do HEAD da PR `origin/chore/aculta-m0-integridade` @
`bc7e4880db4f1cec9760cb6d095df1d1d9a0c33b` (Drupal 11.4.10). Nenhum update foi executado;
a cópia restaurada não foi alterada nem apagada.

| Módulo | Pendências |
|---|---|
| `aculta_commerce_melhorenvio` | `8001`, `8002` |
| `aculta_id_cpf` | `10002` |
| `aculta_lms_resources` | `8001` |
| `aculta_messenger` | `10008`, `10009`, `10010`, `10011`, `10012` e post-updates `game_achievement_notices`, `private_message_email_preferences`, `shared_email_preferences` |
| `aculta_user` | `10013` |

As pendências esperadas do Melhor Envio (`8001`/`8002`) e do LMS Resources (`8001`)
aparecem. Há uma adicional fora do escopo desta frente: `aculta_id_cpf 10002` (owner do
módulo `aculta_id_cpf`). O settings descartável foi removido; nenhuma credencial ficou em
log.




## Regra de armazenamento da cópia do Runtime (2026-10-26)

- **A cópia do Runtime fica em `aculta_runtime_copia`**, banco exclusivo, com dados pessoais e acesso restrito ao usuário do ensaio. Nenhum outro banco guarda cópia.
- **`aculta_simpletest` NUNCA guarda cópia do Runtime.** `drush site:install` apaga todas as tabelas do banco, com ou sem prefixo. Em 19:24 um `site:install` (Local 2) apagou a cópia restaurada ali.
- Antes de qualquer `site:install` ou `drush` que altere o schema, conferir qual banco o settings aponta e se há cópia.

### Estado do ensaio em 2026-10-26 (registrado)

- A cópia restaurada às 19:23 em `aculta_simpletest` foi substituída pelo `site:install` das 19:24. Perdida.
- Dump guardado em `/home/piradopirata/worktrees/ensaio-cutover-private/runtime-dump-1923.sql` (96 MB). **sha256 `31d5350b20caaa171f53b316d1a757276eff1c34929b7c64d3c679a9add8c1d1`, diferente do sha256 `58072497778eef66c95b8446968ea34a4caabfeb1381abc866c19ad81404e10a` informado pelo Local 3.** Não importado até explicar a diferença (dumps de horários diferentes).
- `aculta_runtime_copia`: **não criado.** O acesso administrativo ao MariaDB não foi concedido nesta sessão (a autorização de `sudo mysql` não permitiu escrever a credencial administrativa). Pendente de decisão do responsável.
- Durante a restauração, o `updatedb` de teste já havia criado 18 tabelas novas no banco da cópia (entidades do motor e tabelas de módulos), somadas às 588 do Runtime (606 no total).
- **Ensaio geral congelado** até a fase B ser aprovada e integrada.
