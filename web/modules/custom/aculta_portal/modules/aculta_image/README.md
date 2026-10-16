# ACULTA Image (`aculta_image`)

Sistema único de imagens do site ACULTA. Todo uso de imagem passa por ele: catálogo de finalidades, política de upload,
renderização responsiva, modelos de imagem no texto, compressão, leitura de dimensões, exclusão controlada, ingestão de
origem externa, auditoria, quarentena e comparação visual.

**Versão:** 0.1.0-dev.14 (ver [CHANGELOG](CHANGELOG.md)).
**Para retomar o trabalho:** [docs/HANDOFF.md](docs/HANDOFF.md).
**Plano e fases:** [docs/PLANO.md](docs/PLANO.md). **Modelos no texto:** [docs/MODELOS-DE-IMAGEM.md](docs/MODELOS-DE-IMAGEM.md).
**Estilos:** [docs/ESTILOS.md](docs/ESTILOS.md). **Importação do Growroom:** [docs/IMPORTACAO-GROWROOM.md](docs/IMPORTACAO-GROWROOM.md).

## Regra

Toda imagem e todo arquivo de imagem do site passa por este módulo (AGENTS, "Imagens e arquivos de imagem"). Módulos e
temas pedem uma **finalidade**, não um estilo. Novo estilo nasce no catálogo. Novo campo de imagem declara a política
no `third_party_settings`. Importação externa passa pela ingestão.

## Serviços

Todos com injeção de dependência e, quando couber, tag `aculta_portal.helper` (registro em `PortalHelperRegistry`).

| Serviço | Classe | Função |
| --- | --- | --- |
| `aculta_image.catalog` | `ImageCatalog` | Finalidades (`PURPOSES`), estilos (`STYLES`) e modelos de texto (`MODELOS`). Única fonte de nome de estilo. |
| `aculta_image.upload_policy` | `UploadPolicy` | Extensões (png, gif, jpg, jpeg, webp) e tamanho máximo (10 MB). |
| `aculta_image.render` | `ImageRenderer` | `build()`, `styleId()`, `styleEntity()`, `displaySettings()`. |
| `aculta_image.inspector` | `ImageInspector` | Dimensões de arquivo ou de bytes. |
| `aculta_image.file_remover` | `FileRemover` | Exclusão que recusa arquivo com referência. |
| `aculta_image.ingester` | `ImageIngester` | Ingestão de imagem externa (SHA-256, reaproveitamento, origem). |
| `aculta_image.audit` | `ImageAudit` | Resumo, duplicatas exatas, impressões visuais e candidatas. Somente leitura. |
| `aculta_image.quarantine` | `ImageQuarantine` | Envio, restauração e exclusão de vencidas à quarentena. |
| `aculta_image.compressor` | `ImageCompressor` | Redução de largura para 1200 px e compressão sem perda (binários com argumentos fixos). |

## Texto dos editores (CKEditor 5 e filtro)

- **Upload:** a imagem enviada a um editor vai para `public://inline-images/`. O hook `file_insert` (`InlineImageHooks`)
  reduz para 1200 px de largura (só se for maior), depois comprime. A validação de dimensão do core foi desligada nos
  editores com upload: a imagem é aceita e convertida, não recusada.
- **Modelo:** o plugin `acultaImage.ImageModel` (`js/ckeditor5/aculta-image-model.js`, JavaScript simples, sem build)
  adiciona um dropdown único na barra, com rótulo do modelo selecionado. Grava `data-aculta-modelo` no `<img>`.
  A lista de modelos oferecidos
  vem da configuração do editor (classe PHP `Plugin\CKEditor5Plugin\ImageModel`).
- **Legenda:** o botão contextual “Editar legenda” grava `data-aculta-legenda`; o filtro transforma o atributo em
  `<figcaption>`. A interface e a serialização foram verificadas no Firefox.
- **Filtro:** `aculta_imagem_modelo` (`Plugin\Filter\ImagemModelo`, peso 20) transforma o `<img>` em `<figure>` com
  `srcset` e `sizes` do modelo, e legenda em `<figcaption>` quando houver `data-aculta-legenda`. Crédito não existe.
- **Modelos:** Pequena (320 e 640), Padrão (480 e 1160), Larga (800 e 1200), Destaque (1024 e 1200). Ver `ImageCatalog::MODELOS`.
- **Editores:** dropdown de modelo em `full_html` e `revista_editorial`. `basic_html` sem seletor. Formato `usuario_aculta`
  com Pequena e Padrão (é o formato das respostas do Fórum).
- Configuração: `config/sync/filter.format.{basic_html,usuario_aculta}.yml`, `config/sync/editor.editor.{basic_html,full_html,revista_editorial,usuario_aculta}.yml`.
- Plugin: `aculta_image.ckeditor5.yml` (biblioteca `ckeditor5.image_model`, dependente de `ckeditor5/internal.drupal.ckeditor5.image`).

## Rotas e permissões

- `/painel-administrativo/imagens`: relatório (resumo, duplicatas exatas, pares visuais, candidatas).
- `/painel-administrativo/imagens/quarentena`: enviar candidatas, restaurar e excluir vencidas.
- Permissão: `administer aculta images`.

## Dependências

- Core: `file`, `image`, `media`, `ckeditor5`, `filter`, `editor`.
- Contrib (justificativa em `docs/PLANO.md`): `entity_usage` 5.0.1, `media_duplicates` 2.0.4, `imageapi_optimize` 4.2.0,
  `imageapi_optimize_binaries` 1.2.0-beta1 (beta, aprovado pelo responsável).
- Binários no sistema: ImageMagick 7 (com Ghostscript para PDF), optipng, jpegoptim, gifsicle e cwebp. Na Hostinger, ver
  `docs/PLANO.md`, seção 7.

## Regras de negócio

- **Quarentena:** 30 dias. Exclusão só depois do prazo e com nova verificação de uso.
- **Comparação visual:** dHash de 64 bits com GD. Impressões são calculadas no cron, em lotes de 200. Pares com até 6
  bits de diferença aparecem para revisão. Imagens lisas e não lidas são excluídas dos pares.
- **Ingestão:** um arquivo por conteúdo (SHA-256). Origem registrada por arquivo e URL. Nunca sobrescreve.
- **Compressão:** sem perda, com `optipng -o2`, `jpegoptim --strip-all --all-progressive` e `gifsicle -O2`. Tempo máximo
  de 60 s por arquivo. Falha mantém o arquivo original.
- **Redução de largura:** `mogrify -resize 1200x>` (só reduz, mantém a proporção) em jpeg, png, gif e webp.

## Gates e testes

- `php scripts/validate-aculta-image.php`: tolerância zero (R1 a R8). Deve passar antes de qualquer PR.
- Testes unitários (14): `ImageCatalogTest`, `ImageIngesterTest`, `DHashTest`, `QuarantineRulesTest`, `ImageCompressorTest`.
  ```sh
  php vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_image/tests/src/Unit
  ```
- JavaScript do seletor: `node --check web/modules/custom/aculta_portal/modules/aculta_image/js/ckeditor5/aculta-image-model.js`.
- Runtime de testes: configurações de editor importadas e cache reconstruído. Firefox confirmou a serialização do modelo
  em `full_html`, `usuario_aculta` e `revista_editorial`, incluindo o overflow a 488 px. Um upload de 1600×800 foi
  convertido a 1200×600; o arquivo de teste foi removido. Um artigo temporário não publicado preservou o modelo após
  reabertura e foi excluído. Tema claro/escuro e viewport útil de 390 px continuam pendentes; o Firefox headless ficou em
  488 px mesmo ao solicitar 402 px. Ver o handoff.

## Atualizações de banco

- `aculta_image_update_10001`: `aculta_image_origin`.
- `aculta_image_update_10002`: `aculta_image_quarantine`.
- `aculta_image_update_10003`: `aculta_image_fingerprint`.
- 0.1.0-dev.9 não altera o schema.

## Estrutura do código

```
src/
  ImageCatalog.php, UploadPolicy.php, ImageRenderer.php, ImageInspector.php, FileRemover.php, ImageIngester.php
  Audit/ImageAudit.php
  Compression/ImageCompressor.php
  Controller/ImageAuditController.php
  Form/ImageQuarantineForm.php
  Hook/   CronHooks, ImageStyleHooks (srcset), SocialImageHooks (og:image), InlineImageHooks (upload do texto)
  Plugin/CKEditor5Plugin/ImageModel.php
  Plugin/Filter/ImagemModelo.php
  Quarantine/ImageQuarantine.php, QuarantineRules.php
  Visual/DHash.php
js/ckeditor5/aculta-image-model.js
config/schema/aculta_image.schema.yml
aculta_image.{info,install,libraries,routing,services,ckeditor5}.yml
```
