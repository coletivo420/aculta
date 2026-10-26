# Janela de updates do Runtime — ensaio de snapshot e restauração

Data: 2026-10-26. Autorização do responsável para copiar o `aculta_runtime` a um banco
descartável: **18:32**. Janela operacional: até 20h. Este documento cobre o ensaio de
snapshot/restauração; **não é a janela** e nada foi executado no Runtime.

## Estado do ensaio: BLOQUEADO no passo 2

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
documento, commit ou log de aplicação, e o arquivo não foi versionado. **Recomenda-se
rotacionar a senha do usuário `aculta_runtime`** e reexecutar este ensaio com a nova
credencial. Daqui em diante, a leitura é sempre por PHP para um option-file 600, sem
imprimir valores.

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
3. Senha do Runtime rotacionada, se o responsável assim decidir.

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
   `aculta_simpletest`) e decide sobre a rotação da senha do Runtime.
2. Corrigir o YAML do submódulo Mercado Pago no checkout servido (owner da extração).
3. Executar este procedimento, registrar tempos/tamanho/sha256 e os nomes de tabelas
   divergentes, se houver.

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

