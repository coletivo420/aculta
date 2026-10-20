# ACULTA PDF Viewer

`aculta_pdf_viewer` é o submódulo que fornece a leitura de PDFs compartilhada
pelas experiências do site. Não pertence ao LMS: conteúdo pode usar o formatter
de Media `document` ou o formatter `aculta_pdf_file` em um campo File direto.

Padrões globais ficam em ACULTA → Site → Leitor de PDF
(`/painel-administrativo/aculta/leitor-pdf`). O painel pertence ao módulo Portal;
formatters usam esses valores por padrão e podem optar por configuração
específica do campo. A configuração fica em `aculta_pdf_viewer.settings` e faz
parte do Config Sync. Rolagem horizontal e página dupla são aplicadas pelo
wrapper ao viewer PDF.js completo antes de sua inicialização. O canvas ACULTA
continua em navegação paginada. Em telas móveis, página dupla fica desligada por
padrão e respeita a opção explícita do painel/formatter.

## Responsabilidade e limites

- Integra Media/File ao PDF.js oficial local, sem armazenar conteúdo, permissões,
  documentos ou progresso próprios.
- CSP é observada com `drupal/csp` em Report-Only e endpoint local `drupal/reporting`;
  Config Sync deixa `enforce` desligado. O candidato só vai a RC após
  testes/gates e revisão visual pré-deploy; Alpha confere relatórios e enforcement
  exige decisão posterior. Veja [PV7](docs/ROADMAP.md) e o [ciclo de release](../../../../../../docs/operations/RELEASE-LIFECYCLE.md).
- O builder incorpora PDFs permanentes em `public://` ou `private://`, somente
  após autorização de Media, campo e File. `private://` usa a rota Core e cache
  de render max-age zero.
- O esquema `private://` não define a audiência: a permissão da Media governa a
  leitura. Config Sync concede `view media` aos papéis anônimo e autenticado;
  Media publicada com arquivo privado pode ser lida por ambos. Não usar esse
  fluxo para documentos confidenciais de turma.
- A integração opcional `aculta_pdf_viewer_group` vincula Media `document` ao
  Group `lms_course` e faz Group controlar o acesso à entidade. Para conteúdo de
  turma, use `private://`; o bundle `document` existente continua público e não
  pode receber arquivos confidenciais.
- PDF sem viewer conserva o link Core apenas quando `download` access permite.
- O canvas tem limite de 16 milhões de pixels e 16.384 px por lado. Se uma página
  muito extensa exigir redução para menos da metade da escala pedida, o leitor
  informa a limitação e mantém navegação e link autorizado para abrir/baixar.
- O viewer oferece também um link ao viewer oficial completo do PDF.js, em nova
  aba e com locale `pt-BR`, como alternativa de exploração de text layer e
  estrutura marcada. O canvas continua sendo a experiência padrão; o viewer
  oficial ainda precisa de validação de teclado/leitor de tela e não torna PDFs
  sem estrutura marcada acessíveis por si só.
- O bundle Media `document` atual usa `public://`. Não o use para trabalhos de
  estudante ou outro documento que exija isolamento por turma.
- O `CspReportRequestGuardSubscriber` limita e valida os corpos CSP antes do
  controller do Reporting; não altera relatórios válidos. Não cria rota nem
  controller próprios para o viewer. A entrega de PDFs privados continua sob
  Core e não chama `hook_file_download`.
- PDFPreview/ImageMagick gera miniatura fail-soft somente para PDFs públicos;
  serviço e dependências contrib não são requisito para habilitar o viewer.
- Campos File diretos usam o viewer somente para PDFs permanentes públicos. O
  formatter verifica acesso à entidade, ao campo e ao download do File; tipos
  não PDF preservam o link Core. Arquivos privados continuam usando o formatter
  Core, que entrega pela rota protegida e revalida acesso.
- Wiki e Fórum processam Media Embed do Core com `document` como único tipo
  permitido. Seus editores CKEditor 5 foram validados junto aos formatos; Source
  Editing preserva tags históricas fora das ferramentas visuais. A autorização
  da Media continua sendo verificada pelo Core durante o embed.

## Classes e fluxo

1. `Plugin/Field/FieldFormatter/AcultaPdfMediaFormatter` é descoberto pelo
   atributo `#[FieldFormatter]` com ID `aculta_pdf_media`. Ele valida Media,
   acesso ao campo e `File::access('download')`; delega PDFs ao builder, e usa o
   tema Core `file_link` para documentos que não entram no viewer/fallback.
2. `Plugin/Field/FieldFormatter/AcultaPdfFileFormatter` aplica o mesmo viewer a
   campos File de entidades como o tipo de conteúdo `document`. Confere acesso
   da entidade, do campo e `File::access('download')`; só embute PDF permanente
   público. Para arquivo privado ou outro formato, mantém o link Core autorizado.
3. `Pdf/PdfViewerBuilderInterface` define o contrato para construir um render
   array a partir de Media ou de um File com cacheabilidade fornecida pelo
   consumidor.
4. `Pdf/PdfViewerBuilder` exige Media `document`, acesso de visualização à Media,
   ao campo e ao File, arquivo permanente, URI `public://` ou `private://`, MIME
   `application/pdf`, extensão `.pdf` e URL local gerada pelo File URL Generator.
   Arquivo privado tem cache max-age zero e não recebe thumbnail. A resposta contém
   tags/contexts/max-age de Media, File e access, os controles acessíveis e a
   biblioteca local `aculta_pdf_viewer/viewer`.
   A miniatura pública, quando habilitada, é validada e registrada como File
   temporário pelo `aculta_image.generated_file`, renderizada por
   `aculta_image.render` e removida pela limpeza normal após
   `system.file:temporary_maximum_age`.
5. `js/pdf-viewer.mjs` importa `pdf.mjs` e aponta o worker para
   `pdf.worker.mjs` da distribuição Mozilla instalada localmente em
   `/libraries/pdf.js`. Usa canvas, navegação, zoom com restauração, busca textual por página e
   tela cheia; erro de carregamento informa o usuário e preserva o link de
   fallback. Firefox 153.4 ESR validou leitor e inserção/salvamento de uma Media
   já existente via CKEditor no Runtime; a criação/upload pela UI como
   `content_editor` ainda não foi verificada. O registro, inclusive dos itens limpos, está em
   [TESTES-NAVEGADOR](../../../../../../docs/portal/TESTES-NAVEGADOR.md).
6. `viewer-wrapper.html` e `js/pdf-viewer-wrapper.mjs` aplicam os padrões do
   formatter ao viewer completo pelo evento `webviewerloaded` do PDF.js 6.4.299,
   antes de `PDFViewerApplication.run()`. Nenhum arquivo da distribuição
   Composer é alterado. O builder fornece somente URL local após validar o
   acesso; o wrapper exige PDF same-origin, e o acesso privado continua na rota
   protegida do Core. Os padrões de download e impressão também são propagados
   ao viewer completo; quando desativados no formatter, os respectivos botões
   principal e secundário do PDF.js ficam ocultos e fora da árvore acessível.

## Hooks e integração Drupal

Não há hooks PHP (`hook_*`) nem subscriber neste módulo. O formatter é um plugin
registrado pelo mecanismo de atributos do Drupal Field API; não é um hook. O
JavaScript registra `Drupal.behaviors.acultaPdfViewer.attach()` e também inicia
em `DOMContentLoaded` para renderizações fora de attach Drupal. A biblioteca é
anexada pelo render array somente quando o builder autoriza e reconhece PDF.

O display padrão `media.document` seleciona `aculta_pdf_media`; os displays
`node.document.default` e `node.document.full` selecionam `aculta_pdf_file` em
Config Sync.
O módulo depende do Portal (painel de administração), aculta_image, Core File e
Media; PDFPreview e ImageMagick são opcionais no runtime. Não depende do LMS nem
de PDF contrib. A configuração global é exportável e versionada. O PDF.js é gerenciado pelo Composer do site. O
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
da Media e o efeito de `view media` para o papel anônimo. O Kernel
`aculta_pdf_viewer_group` valida membro, não membro, remoção da relação Group e
revogação de membership pela URL privada conhecida. No Runtime de teste, PV5 está
ativa por importação parcial dirigida:
submódulo, bundle privado, relação `lms_course` e permissões de turma estão
sincronizados. Firefox 153.4 ESR validou o viewer privado embutido em página:
membro recebeu 200 e renderizou 14 páginas; não membro e anônimo receberam 403;
remover a relação Media↔Group ou a membership negou a mesma URL (403), e
restaurar cada relação restabeleceu 200. O upload/criação foi confirmado na UI
Firefox como `content_editor`: o arquivo foi selecionado pelo input nativo, a
Media `document` foi salva e a tela de edição abriu após conceder somente
`edit own document media` ao papel editorial. A miniatura pública foi gerada no
Runtime pelo pipeline `aculta_image`. A rota Core `/system/files?file=...` é necessária neste Runtime,
pois `/system/files/{filepath}` responde 404. Fixtures, uploads, miniatura,
contas, conteúdo e tokens de login de teste foram removidos. Nesta homologação
não houve `drush cim` global nem `updb`;
CDN, policy ImageMagick e migração de arquivos também não fazem parte do módulo.

## Documentação de arquitetura

- [Arquitetura, hooks, classes e fluxo](docs/ARQUITETURA.md)
- [Roadmap do PDF Viewer](docs/ROADMAP.md)
- [Contrato e segurança do viewer](../../../../../../docs/portal/PDF-VIEWER-INTEGRATION.md)
- [Roadmap LMS](../../../../../../docs/modules/ACULTA-LMS-ROADMAP.md)
- [Changelog do módulo](CHANGELOG.md)
