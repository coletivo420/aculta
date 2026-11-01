# DBTNG-2: registro da migração do Runtime para MariaDB

Este arquivo registra a migração do Runtime do Homelab. Pelo `AGENTS.md`, a
portabilidade entre motores pertence ao projeto DBTNG-2. Como esse projeto ainda
não tem repositório ou pasta próprios, este registro serve como ponto de
acompanhamento até ser movido para lá.

## Evento

- **Data:** 2026-10-10.
- **Origem:** Runtime local de origem (372 tabelas, 54.579 linhas).
- **Destino:** MariaDB 11.8.6 (`aculta_runtime`, utf8mb4, usuário próprio com
  privilégio apenas nesse banco).
- **Resultado:** contagem idêntica em todas as 372 tabelas. Conferidos:
  `users` (incluindo `uid 0`), `node_field_data` (28), `config` (956) e
  `key_value` (1.881).
- **Drupal:** `settings.local.php` (ignorado pelo Git) carrega
  `secrets/aculta-runtime-db.php`. `drush status` e `drush cr` passaram.

## Decisões e correções

- Tipos: `INTEGER` → `BIGINT`; `VARCHAR`/`TEXT` mantêm charset utf8mb4; colunas
  `NOCASE_UTF8` usam `utf8mb4_general_ci`; demais, `utf8mb4_bin` (mesma
  semântica de comparação do banco de origem).
- Índices: nomes acima de 64 caracteres recebem sufixo por hash. TEXT em PK/UNIQUE
  vira `VARCHAR(255)`, com verificação de tamanho; em índice não único, usa prefixo.
- Carga: `NO_AUTO_VALUE_ON_ZERO` ativo, para o `uid 0` (anônimo) não virar `uid 1`.
- Lotes limitados em bytes (caches do Drupal chegam a ~2 MB por linha).

## Limites e pendências

- O conversor de carga ficou no scratchpad da sessão e não está versionado.
  Se for necessário repetir a migração, precisa ser recriado ou versionado.
- O erro `Operation not permitted` durante o COMMIT, que apareceu em tentativas
  anteriores, não foi reproduzido após a correção do `uid 0`. A causa exata não
  foi confirmada.
- Foi testada e revertida a desativação do AIO nativo (io_uring) do InnoDB; o
  servidor voltou ao padrão.
- A cópia local de segurança foi removida em 2026-10-10, após confirmação de que
  o Drupal usa MariaDB.
- Não há Estado integral do Runtime MariaDB atual. Backups de
  `aculta_runtime` devem ser definidos no processo de operação do Homelab.
- ADR-004 atualizada para MariaDB.
