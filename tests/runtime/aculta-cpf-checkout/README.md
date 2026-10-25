# Diagnóstico de configuração — CPF/checkout

Scripts `drush php:script` usados na frente "ACULTA CPF ID + checkout compartilhado
Loja/LMS" para medir o drift entre `config/sync` e a tabela `config` do banco
ativo. Foram escritos por esta frente e pelo agente que a antecedeu.

Todos são somente leitura sobre o banco e sobre `config/sync`. Nenhum executa
`config:import`, `config:export` ou `Config::save()`. A única escrita é a de
`config-reexport-review.php`, que grava YAML solto em um diretório fora do
repositório (`/tmp/aculta-reexport` por padrão).

## Pré-requisitos

- raiz do projeto (`/home/piradopirata/projetos/aculta`);
- `vendor/bin/drush` (o `drush` não está no PATH);
- banco do ambiente que se quer medir, já conectado pelo `settings.php`.

## Scripts

### `config-drift-compare.php`

Compara todo o `config/sync` (`FileStorage`) com o banco (`DatabaseStorage`) e
imprime as contagens `only_sync`, `only_active` e `differing`, além de classificar
cada divergência como `MAP_ORDER`, `MISSING` ou `VALUE`.

```bash
vendor/bin/drush php:script tests/runtime/aculta-cpf-checkout/config-drift-compare.php
```

Medição de referência em 2026-10-23, no banco local: `only_sync=0
only_active=31 differing=40`.

### `config-order-diff.php`

Percorre uma lista fixa de dez configurações e mostra onde a divergência é só de
ordem (`LIST_ORDER`, `MAP_ORDER`), de valor (`VALUE`) ou de raiz (`ROOT`). Serve
para confirmar a quais frentes pertencem os itens de ordem restantes.

```bash
vendor/bin/drush php:script tests/runtime/aculta-cpf-checkout/config-order-diff.php
```

### `config-field-diff.php`

Diff detalhado dos 11 objetos de campo, mídia e workflow. Imprime as chaves que
só existem em um dos lados (`ONLY_SYNC`, `ONLY_ACTIVE`) e os valores diferentes.

```bash
vendor/bin/drush php:script tests/runtime/aculta-cpf-checkout/config-field-diff.php
```

### `config-reexport-review.php`

Lê do banco ativo os mesmos 11 objetos e grava o YAML correspondente em
`$REEXPORT_OUT` (padrão `/tmp/aculta-reexport`), para revisão dos donos. Não
escreve em `config/sync` e não altera o banco.

```bash
REEXPORT_OUT=/tmp/aculta-reexport-2026-10-23 \
  vendor/bin/drush php:script tests/runtime/aculta-cpf-checkout/config-reexport-review.php
```

**Atenção:** a reexportação do ativo não é uma cópia fiel do arquivo atual. Em
`field.field.media_gallery.media_gallery.field_gallery_purpose` ela remove
`settings.max_length`/`is_ascii`/`case_sensitive`, e em
`field.storage.group.field_roster_visible` remove `settings.on_label`/`off_label`
— valores que só existem no sync. Nos outros nove a diferença é o `uuid` do
banco. O resultado precisa de revisão antes de qualquer uso; nunca importe
`core.extension` nem aplique o resultado por cima do banco.

### `diagnostics/`

Auxiliares menores do agente anterior, mantidos para reproduzir as medições:
`order-sorted-check.php`, `map-order-scan.php`, `sorted-scan.php`,
`set-compare.php` e `field-settings-check.php`. Rodam do mesmo modo
(`vendor/bin/drush php:script ...`) e só imprimem diagnóstico.
