# Arquitetura do ACULTA PDF Viewer

`aculta_pdf_viewer` é o submódulo responsável pela visualização compartilhada
de PDFs no site. Fica ao lado de `aculta_lms` dentro do conjunto de submódulos
do Portal, mas não depende funcionalmente de `aculta_portal` ou do LMS. É
integrado ao Drupal Media e pode ser usado por aulas, artigos, Revista420,
Wiki420, Fórum, projetos e documentos institucionais que referenciem Media.

## Responsabilidade e fontes de verdade

- Media/File são donos da entidade, URI, metadados e arquivo.
- Core Media/File decide acesso a Media, campo e arquivo; o submódulo aplica
  esses resultados antes de criar render arrays.
- PDF.js oficial fixado no Composer lê o arquivo no browser. Para `private://`,
  o File URL Generator aponta à rota privada do Core, que revalida o acesso ao
  File e às entidades/campos referenciadores a cada requisição.
- O viewer não cria rotas, armazenamento, permissões, dados de LMS ou política
  de autorização independente.
- O canvas renderiza a página como imagem: `aria-label` identifica a página, mas
  não expõe o texto nem a estrutura semântica do PDF. O link Core para o arquivo
  original permanece disponível como alternativa de leitura; a acessibilidade
  nessa alternativa depende da marcação do próprio PDF e do leitor do navegador.
  Portanto, controles acessíveis do viewer não significam conformidade WCAG do
  conteúdo do documento. Uma camada textual/estrutural exige avaliação própria:
  o upstream PDF.js registra limitações de teclado e estrutura acessível no
  text layer ([#15635](https://github.com/mozilla/pdf.js/issues/15635),
  [#20114](https://github.com/mozilla/pdf.js/issues/20114)).
- Formatos que embutem Media usam o filtro `media_embed` do Core depois dos
  filtros de HTML; a Wiki permite somente o bundle público `document`. O
  render continua passando pelo display Media e pela checagem Core de acesso.
- PDFPreview é uma melhoria opcional de miniatura da primeira página. Falha/ausência de preview não
  muda a leitura nem o fallback autorizado.

## Serviço e classes

### `PdfViewerBuilderInterface`

Contrato de renderização: `build(MediaInterface $media, string $field_name)`
recebe uma entidade Media e o campo esperado; `buildFile()` recebe um File e a
cacheabilidade acumulada pelo consumidor de campo direto. Ambos retornam um
render array com acesso/cache incorporados. O contrato evita que consumidores
construam URLs ou markup de viewer diretamente.

### `PdfViewerBuilder`

Serviço `aculta_pdf_viewer.builder`, com DI de `file_url_generator`,
`current_user` e `aculta_pdf_viewer.thumbnail_builder`. A sequência é fail-closed:

1. Confere Media `view`, bundle `document` e presença do campo.
2. Confere `view` no campo e obtém a referência File.
3. Confere File `view` e `download`, permanência, esquema `public://` ou
   `private://`, MIME e extensão PDF.
4. Para arquivos privados, limita o cache do render a max-age zero. A entrega
   de bytes permanece na rota Core `system.private_file_download`; não há rota,
   token ou política paralela no submódulo.
5. Opcionalmente inclui miniatura da primeira página somente para arquivo
   público. Se binário, policy,
   formato, tamanho ou conversão falhar, segue com o leitor sem miniatura.
6. Gera uma URL local pelo File URL Generator; rejeita URL com scheme/host ou
   fora de path local.
7. Cria o render array acessível com miniatura, canvas, estado e controles, anexa a
   biblioteca local e borbulha cache tags/contexts/max-age de Media, File e
   resultados de acesso.

Stream wrapper remoto, MIME divergente ou entidade sem acesso não recebe
viewer. O builder não aceita URL enviada pela pessoa, por querystring ou pelo
editor. Arquivos temporários não recebem viewer.

### `PdfPreviewThumbnailBuilder`

O serviço `aculta_pdf_viewer.thumbnail_builder` usa `pdfpreview.generator` para
obter uma miniatura da primeira página. Só processa File permanente `public://`,
PDF até 20 MiB, com scheme padrão público e configuração fixa
`public://pdfpreview`, 480×640, JPEG e nome derivado do ID. O processo
ImageMagick recebe timeout de 15 segundos. A imagem usa texto alternativo
derivado do título Media. O resultado é validado e registrado como File
temporário pelo `aculta_image.generated_file`; a limpeza de temporários do
`aculta_image` o remove após `system.file:temporary_maximum_age`. A marcação é
construída pelo serviço `aculta_image.render`, com tags do File e contexto
`url.site`.

Exceção, binário ausente, policy que negue PDF, arquivo inválido ou resultado
não local retornam `NULL`; canvas e link seguem disponíveis. Arquivo privado
nunca é enviado ao gerador, e miniaturas fora de `public://pdfpreview/` são
recusadas. A configuração do toolkit Drupal habilita só o formato de leitura
PDF; a policy global do sistema não é alterada. Ghostscript permanece
dependência operacional cuja disponibilidade e policy serão validadas por
ambiente.

### `AcultaPdfMediaFormatter`

Plugin do Field API descoberto pelo atributo `#[FieldFormatter]`, ID
`aculta_pdf_media`, tipo `file`. O display padrão `media.document` usa esse
plugin. O formatter valida o acesso à Media e ao campo, verifica
`File::access('download')` e delega PDF ao builder. Arquivos de outros tipos
usam `file_link`, preservando o comportamento Core.

Se o builder negar o viewer, o formatter mantém `file_link` somente quando o
access de download permite e acumula também a cacheabilidade do motivo de
negação. Para arquivo privado, tanto o viewer quanto o link fallback usam a URL
da rota Core de download; Core recalcula autorização sobre referências,
entidades e campos. Isso reaproveita a política Core, mas não substitui testes
de isolamento por Group/turma. O bundle `document` exportado continua usando
`public://`; conteúdo confidencial deve usar um campo/bundle configurado para
`private://`.

### `AcultaPdfFileFormatter`

O plugin `aculta_pdf_file` atende campos File diretos, sem criar Media ou
duplicar metadados. Ele exige acesso de visualização à entidade e ao campo, e
acesso `download` ao File. PDF permanente `public://` usa `buildFile()` com a
cacheabilidade acumulada pelo formatter; qualquer outro tipo, URI privado,
arquivo temporário ou viewer negado mantém o link Core permitido. O formatter
não oferece uma rota de leitura privada própria: campos privados continuam com
a entrega protegida do formatter Core.

Os displays `node.document.default` e `node.document.full` usam esse formatter
para o campo institucional `field_document`. Isso amplia o contrato existente
sem alterar a fonte de verdade: o node e o campo continuam donos da associação,
enquanto o File continua dono do arquivo e seus metadados.

### Wiki e formatos de texto

Os formatos `wiki` e `usuario_aculta` processam tokens `drupal-media` pelo filtro
`media_embed` após a sanitização HTML e permitem somente Media `document`. Ambos
oferecem o botão Media no CKEditor 5 e foram aprovados pelo `CKEditor5::validatePair()`.
Source Editing mantém os elementos históricos que não têm controles visuais; o
filtro HTML continua limitando o que é persistido. Wiki, Fórum e formatos
editoriais compartilham o display Media, sem URL ou política de acesso própria.
A Media continua sujeita ao acesso Core e o bundle privado `course_document` não
pode ser selecionado nesses formatos.

## JavaScript e carregamento

`js/pdf-viewer.mjs` é carregado pela biblioteca `aculta_pdf_viewer/viewer`,
anexada somente ao render array autorizado. Importa `pdf.mjs` e configura
`pdf.worker.mjs` da distribuição Mozilla local em `web/libraries/pdf.js`.
Carrega o PDF no canvas, oferece navegação anterior/próxima, zoom, busca de
texto por página e tela cheia. A busca usa `getTextContent()` do PDF.js; os
resultados navegam até a página correspondente, sem criar armazenamento,
anotação ou estado persistente. Erro de leitura mantém o link autorizado.

O arquivo registra `Drupal.behaviors.acultaPdfViewer.attach(context)` para
renderizações Drupal e também faz bootstrap em `DOMContentLoaded`. Isso é um
JavaScript behavior do Drupal, não um hook PHP. Não há `hook_*`, subscriber,
controller nem classe de formulário neste módulo; não existe arquivo
`aculta_pdf_viewer.module`.

## Configuração e dependências

- `.info.yml` declara Core 11.3+, File e Media. Não depende do módulo geral
  `aculta_portal`, do LMS ou de Drupal PDF.
- `aculta_pdf_viewer.services.yml` registra o builder.
- Registra também `PdfPreviewThumbnailBuilder`, adapter fail-soft do serviço
  `pdfpreview.generator` com limites de tamanho e duração. Referências a
  PDFPreview e ImageMagick são opcionais no container para permitir que o
  viewer continue iniciando enquanto os contribs estiverem desativados.
- `aculta_pdf_viewer.libraries.yml` registra o ES module e `core/drupal`.
- `core.entity_view_display.media.document.default` escolhe o formatter e
  declara dependência no submódulo.
- `core.extension.yml` o ativa na Config Sync para que o formatter seja
  conhecido ao importar o display. Esta mudança versionada não executa a
  importação no Runtime.
- `drupal/pdf` 1.3 permanece instalado no Composer, mas o formatter dele usa o
  loader `.js`/API global e não é chamado. O PDF.js `.mjs` é o viewer real.
- Nenhuma chamada a hook `file_download` foi adicionada. Core continua sendo o
  responsável por entregar downloads privados.

## Segurança, acessibilidade e limites conhecidos

- Cache inclui entidades e `AccessResult`; links e viewer só são montados após
  checagem no servidor. Para `private://`, o render não é armazenado e a URL de
  bytes passa pela rota Core de arquivo privado.
- Ocultar o botão não protege arquivo `public://`; a URL pública é acessível
  diretamente. Material confidencial deve usar `private://`; não mudar o
  esquema do bundle existente sem migração e decisão próprias.
- `private://` protege o caminho de entrega do servidor web, mas não decide
  quem compõe a audiência: a rota Core usa `File::access('download')`, que
  revalida a Media referenciadora e seus campos. A Config Sync atual concede
  `view media` aos papéis anônimo e autenticado; assim, Media publicada com PDF
  privado também fica acessível a esses papéis. Group 3.3.5 não fornece relação
  Media; `aculta_pdf_viewer_group` adiciona a relação para `document` em
  `lms_course` e permissões explícitas para os papéis estudante/educador. A
  autorização do curso não é herdada automaticamente pela Media. Não usar este
  contrato para PDFs confidenciais de turma até ativar e homologar a integração
  opcional `aculta_pdf_viewer_group` para membro, não membro e revogação.
- CSP, entrega de PDF privado em browser, revogação de membership, usuários de
  turmas diferentes e produção LiteSpeed são gates de homologação.
- PDFPreview 1.2 escreve derivados no scheme padrão e exige toolkit/binaries.
  Seu código converte sincronamente no primeiro render e não captura exceções.
  A integração ACULTA limita tamanho/timeout e omite a miniatura em caso de
  falha. PDFPreview 1.2.0 aceita ImageMagick contrib 3.7/4.x; Composer resolve
  ImageMagick 4.0.2, mas não 5.0.1. O binário não está instalado neste Homelab e
  a policy global não foi liberada, então a geração real permanece DEFERRED até
  provisionamento operacional seguro.

## Testes

- Unit: `tests/src/Unit/Pdf/PdfViewerBuilderTest.php` e
  `tests/src/Unit/Plugin/Field/FieldFormatter/AcultaPdfMediaFormatterTest.php`.
- Kernel: `tests/src/Kernel/AcultaPdfViewerInstallTest.php` valida descoberta
  do submódulo, registro do serviço/formatter e ausência de storage/hooks de
  download próprios.
- Kernel: `AcultaPdfMediaEmbedTest` percorre o endpoint Core privado com um
  arquivo local `private://`; usuário autorizado recebe 200 e resposta privada,
  enquanto usuário autenticado sem permissão e acesso anônimo recebem 403. O
  usuário com `view media` recebe 200 para Media publicada e 403 após
  despublicação. O teste confirma também que conceder `view media` ao papel
  anônimo permite a entrega Core, espelhando a política da Config Sync atual.
  O render não é compartilhado em cache e não gera thumbnail privada.
  Group/turma, revogação e browser seguem pendentes.
- O viewer ESM e worker passaram smoke test Firefox headless na fixture oficial
  da distribuição e contra um artigo temporário de Media Embed no Runtime
  `aculta_runtime`: 14 páginas, navegação, zoom 125%, busca (cinco resultados),
  tela cheia e zero erros JS. O node, Media, File e binário temporários foram
  removidos e a contagem do bundle `document` voltou a zero.
- A biblioteca CKEditor 5 abriu no Runtime após autenticar pelo host Conta; a
  aba Documento selecionou a Media PDF existente, inseriu o widget, salvou o
  artigo e a página publicada mostrou o leitor. A Media fixture foi criada por
  Drush, então upload/criação por usuário `content_editor` ainda é DEFERRED.
  O primeiro link de login foi emitido no host Magazine e recebeu 403; o
  procedimento correto é emitir no host Conta. Sem ImageMagick/Ghostscript ou
  permissão de policy, só a miniatura fica ausente e o leitor/fallback continua.
  Geração real de miniatura, isolamento Group/turma, revogação, acessibilidade
  visual e Hostinger seguem DEFERRED.

## Referências upstream

- [Drupal module metadata/dependencies](https://www.drupal.org/docs/develop/creating-modules/let-drupal-know-about-your-module-with-an-infoyml-file)
- [Drupal FormatterBase e DI](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Field%21FormatterBase.php/class/FormatterBase/main)
- [Drupal FileAccessControlHandler](https://api.drupal.org/api/drupal/core%21modules%21file%21src%21FileAccessControlHandler.php/main)
- [Drupal FileDownloadController](https://api.drupal.org/api/drupal/core%21modules%21system%21src%21FileDownloadController.php/function/FileDownloadController%3A%3Adownload/main) e [hook_file_download](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21File%21file.api.php/function/hook_file_download/main)
- [Drupal MediaAccessControlHandler](https://api.drupal.org/api/drupal/core%21modules%21media%21src%21MediaAccessControlHandler.php/main)
- [PDF 1.3](https://www.drupal.org/project/pdf/releases) e [PDFPreview 1.2](https://www.drupal.org/project/pdfpreview/releases)
- [PDF.js 6.4.299](https://github.com/mozilla/pdf.js/releases/tag/v6.4.299)
- [Drupal 3D Flipbook 11.0.2](https://www.drupal.org/project/flipbook/releases/11.0.2)

### Auditoria e proveniência do Drupal 3D Flipbook

O release 11.0.2 foi lido no commit upstream
`79e9cd35b74f2045b97dc71fc8a73d0c4b2cfbd5`. O módulo fornece uma entidade
`flipbook` com campo File próprio e integração com Views; seu runtime inclui
jQuery, Bootstrap, Three.js, PDF.js e `3dflipbook.min.js`. O próprio projeto
alerta que não é coberto pela política de avisos de segurança do Drupal e que
uso pago da biblioteca 3D exige licença CodeCanyon. Não houve cópia de código,
CSS ou asset deste contrib: o formatter Media ACULTA já cobre a apresentação do
PDF sem storage paralelo, e não depende do componente 3D. Ideias de controles
foram reimplementadas sobre a distribuição PDF.js local. A proveniência é
registrada para diferenciar esta auditoria do TNC FlipBook (plugin WordPress).
