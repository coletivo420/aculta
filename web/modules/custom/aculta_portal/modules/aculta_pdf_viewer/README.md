# ACULTA PDF Viewer

`aculta_pdf_viewer` é o submódulo que fornece a leitura de PDFs compartilhada
pelas experiências do site. Não pertence ao LMS: qualquer conteúdo pode usar o
formatter de Media `document`.

## Responsabilidade e limites

- Integra Media/File ao PDF.js oficial local, sem armazenar conteúdo, permissões,
  documentos ou progresso próprios.
- O builder incorpora PDFs permanentes em `public://` ou `private://`, somente
  após autorização de Media, campo e File. `private://` usa a rota Core e cache
  de render max-age zero.
- O esquema `private://` não define a audiência: a permissão da Media governa a
  leitura. Config Sync concede `view media` aos papéis anônimo e autenticado;
  Media publicada com arquivo privado pode ser lida por ambos. Não usar esse
  fluxo para documentos confidenciais de turma.
- O Group instalado não tem relação Media configurada. A autorização de um curso
  não é herdada pela Media; documentos por turma aguardam integração e testes
  Group↔Media.
- PDF sem viewer conserva o link Core apenas quando `download` access permite.
- O bundle Media `document` atual usa `public://`. Não o use para trabalhos de
  estudante ou outro documento que exija isolamento por turma.
- Não cria rota, controller, hook PHP ou configuração própria. Não chama
  `hook_file_download`; a entrega privada continua sob Core.
- PDFPreview/ImageMagick gera miniatura fail-soft somente para PDFs públicos;
  serviço e dependências contrib não são requisito para habilitar o viewer.

## Classes e fluxo

1. `Plugin/Field/FieldFormatter/AcultaPdfMediaFormatter` é descoberto pelo
   atributo `#[FieldFormatter]` com ID `aculta_pdf_media`. Ele valida Media,
   acesso ao campo e `File::access('download')`; delega PDFs ao builder, e usa o
   tema Core `file_link` para documentos que não entram no viewer/fallback.
2. `Pdf/PdfViewerBuilderInterface` define o contrato para construir um render
   array a partir da Media autorizada e do campo de arquivo.
3. `Pdf/PdfViewerBuilder` exige Media `document`, acesso de visualização à Media,
   ao campo e ao File, arquivo permanente, URI `public://` ou `private://`, MIME
   `application/pdf`, extensão `.pdf` e URL local gerada pelo File URL Generator.
   Arquivo privado tem cache max-age zero e não recebe thumbnail. A resposta contém
   tags/contexts/max-age de Media, File e access, os controles acessíveis e a
   biblioteca local `aculta_pdf_viewer/viewer`.
4. `js/pdf-viewer.mjs` importa `pdf.mjs` e aponta o worker para
   `pdf.worker.mjs` da distribuição Mozilla instalada localmente em
   `/libraries/pdf.js`. Usa canvas, navegação, zoom com restauração, busca textual por página e
   tela cheia; erro de carregamento informa o usuário e preserva o link de
   fallback. Firefox 153.4 ESR validou leitor e inserção/salvamento de uma Media
   já existente via CKEditor no Runtime; a criação/upload pela UI como
   `content_editor` ainda não foi verificada. O registro, inclusive dos itens limpos, está em
   [TESTES-NAVEGADOR](../../../../../../docs/portal/TESTES-NAVEGADOR.md).

## Hooks e integração Drupal

Não há hooks PHP (`hook_*`) nem subscriber neste módulo. O formatter é um plugin
registrado pelo mecanismo de atributos do Drupal Field API; não é um hook. O
JavaScript registra `Drupal.behaviors.acultaPdfViewer.attach()` e também inicia
em `DOMContentLoaded` para renderizações fora de attach Drupal. A biblioteca é
anexada pelo render array somente quando o builder autoriza e reconhece PDF.

O display padrão `media.document` seleciona `aculta_pdf_media` em Config Sync.
O módulo exige apenas Core File e Media; PDFPreview e ImageMagick são opcionais
no runtime. Não depende do módulo geral `aculta_portal`, LMS, PDF contrib, nem de
configuração ativa importada. O PDF.js é gerenciado pelo Composer do site. O
contrib `drupal/pdf` não é chamado porque
seu loader 1.3 usa `pdf.js`/API global, enquanto o pacote escolhido publica ES
modules `.mjs`.

## Testes e validação

Os testes Unit estão em `tests/src/Unit/`: acesso e renderização do builder;
delegação para viewer, link Core de outros formatos e fallback quando o viewer
nega o PDF. O Kernel test em `tests/src/Kernel/` verifica descoberta do módulo,
serviço e plugin sem storage ou hook de download próprio.

PHPUnit é executado com a configuração `web/core/phpunit.xml.dist`. Kernel
valida Media Embed, endpoint privado Core, acesso autorizado/negado, publicação
da Media e o efeito de `view media` para o papel anônimo. Upload como
`content_editor`, integração Group↔Media, browser privado, binários/miniaturas e
Hostinger continuam pendentes. Nenhum `drush cim`, `updb`, CDN, ImageMagick
policy ou migração de arquivo é executado por este módulo.

## Documentação de arquitetura

- [Arquitetura, hooks, classes e fluxo](docs/ARQUITETURA.md)
- [Roadmap do PDF Viewer](docs/ROADMAP.md)
- [Contrato e segurança do viewer](../../../../../../docs/portal/PDF-VIEWER-INTEGRATION.md)
- [Roadmap LMS](../../../../../../docs/modules/ACULTA-LMS-ROADMAP.md)
- [Changelog do módulo](CHANGELOG.md)
