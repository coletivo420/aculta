# ACULTA Content Index File

Submódulo **opcional** do `aculta_index`. Fornece o provider mínimo
`file:file` para que a métrica `media.files.requests` do Statistics possa ser
correlacionada pelo bridge do Content Index.

## O que projeta

Somente arquivos **permanentes** (`FileInterface::isPermanent()`):

- identidade (`file`, id, bundle, UUID, idioma);
- datas de criação/alteração;
- estado publicado (= permanente);
- `owner_uid` interno, quando disponível.

Não projeta nome de arquivo, URI, MIME type, tamanho, conteúdo, relações,
domínio ou qualquer dado pessoal adicional. Arquivos temporários não entram no
catálogo.

## Acesso e uso

- O bridge exige uma página compatível (`entity_type=file`, `bundle=file`,
  `published=TRUE`) e revalida Entity Access do File.
- A métrica `media.files.requests` é INTERNAL no Statistics: a conta precisa de
  `administer aculta_statistics`.
- O submódulo é opt-in; habilitá-lo faz todo File permanente salvo/atualizado
  passar pela fila do Index. Sem ele, a métrica continua sem provider.
- Config Sync e ativação no Runtime pertencem ao agente de integração.

## Evidência

- Unit do submódulo: 2 testes / 21 assertions.
- Kernel do bridge Statistics com `file:file`: 3 testes / 55 assertions.
- O provider não chama `getFilename()`, `getFileUri()`, `getMimeType()` nem
  `getSize()`; há teste Unit que falha se esses métodos forem chamados.

## Operação

```text
drush pm:enable aculta_index_file
drush aculta-index:rebuild start
... batch/validate/activate ...
```

Não execute rebuild/ativação no Runtime sem autorização e plano de rollback.
