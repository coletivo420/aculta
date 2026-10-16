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
- PDF.js oficial fixado no Composer lê o arquivo no browser. Não existe cópia
  ou proxy privado implementado nesta fase.
- O viewer não cria rotas, armazenamento, permissões, dados de LMS ou política
  de autorização independente.
- PDFPreview é uma melhoria de miniatura futura. Falha/ausência de preview não
  muda a leitura nem o fallback autorizado.

## Serviço e classes

### `PdfViewerBuilderInterface`

Contrato de renderização: `build(MediaInterface $media, string $field_name)`
recebe uma entidade Media e o campo esperado, e retorna um render array com
cacheabilidade e access incorporados. O contrato evita que consumidores
construam URLs ou markup de viewer diretamente.

### `PdfViewerBuilder`

Serviço `aculta_pdf_viewer.builder`, com DI de `file_url_generator` e
`current_user`. A sequência é fail-closed:

1. Confere Media `view`, bundle `document` e presença do campo.
2. Confere `view` no campo e obtém a referência File.
3. Confere File `view`, permanência, esquema `public://`, MIME e extensão PDF.
4. Gera uma URL local pelo File URL Generator; rejeita URL com scheme/host ou
   fora de path local.
5. Cria o render array acessível com canvas, estado e controles, anexa a
   biblioteca local e borbulha cache tags/contexts/max-age de Media, File e
   resultados de acesso.

Arquivo `private://`, stream wrapper remoto, MIME divergente ou entidade sem
acesso não recebe viewer. O builder não aceita URL enviada pela pessoa, por
querystring ou pelo editor.

### `AcultaPdfMediaFormatter`

Plugin do Field API descoberto pelo atributo `#[FieldFormatter]`, ID
`aculta_pdf_media`, tipo `file`. O display padrão `media.document` usa esse
plugin. O formatter valida o acesso à Media e ao campo, verifica
`File::access('download')` e delega PDF ao builder. Arquivos de outros tipos
usam `file_link`, preservando o comportamento Core.

Se o builder negar o viewer, o formatter mantém `file_link` somente quando o
access de download permite e acumula também a cacheabilidade do motivo de
negação. Para arquivo privado, a URL gerada pelo Core volta ao endpoint de
download privado, cuja autorização é recalculada por Core sobre as referências
e campo. Isso oferece fallback de arquivo; não implementa leitura privada
embutida nem comprova isolamento por turma. O bundle `document` exportado usa
`public://`; arquivo público pode ser acessado diretamente e não deve conter
trabalhos confidenciais.

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
  checagem no servidor. A URL de bytes vem do File URL Generator local.
- Ocultar o botão não protege arquivo `public://`; a URL pública é acessível
  diretamente. Material privado deve usar `private://` e não recebe viewer nesta
  versão.
- CSP, tamanho/tempo de PDF, renderização browser, troca de membership, usuários
  de turmas diferentes e produção LiteSpeed são gates de homologação.
- PDFPreview 1.2 escreve derivados no scheme padrão e exige toolkit/binaries.
  ImageMagick 5 não é aceito pela constraint estável 1.2; nenhum binário ou
  policy PDF/PS foi habilitado neste trabalho.
- O tema ACULTA420 ainda não recebeu apresentação própria; primeiro é necessário
  aprovar a integração funcional do formatter/viewer em ambiente Drupal.

## Testes

- Unit: `tests/src/Unit/Pdf/PdfViewerBuilderTest.php` e
  `tests/src/Unit/Plugin/Field/FieldFormatter/AcultaPdfMediaFormatterTest.php`.
- Kernel: `tests/src/Kernel/AcultaPdfViewerInstallTest.php` valida descoberta
  do submódulo, registro do serviço/formatter e ausência de storage/hooks de
  download próprios.
- O viewer ESM e worker passaram smoke test Firefox headless na fixture oficial
  da distribuição e contra um artigo temporário de Media Embed no Runtime
  `aculta_runtime`: 14 páginas, navegação, zoom 125%, busca (cinco resultados),
  tela cheia e zero erros JS. O node, Media, File e binário temporários foram
  removidos e a contagem do bundle `document` voltou a zero.
- A tentativa de abrir a edição CKEditor com link de login único recebeu 403,
  sem diagnóstico independente de sessão/permissão; o fluxo de autoria pela UI
  segue DEFERRED, embora o filtro `media_embed` → formatter esteja coberto pelo
  Kernel e tenha renderizado no artigo de teste. PDFPreview, acesso privado,
  acessibilidade visual e Hostinger seguem DEFERRED.

## Referências upstream

- [Drupal module metadata/dependencies](https://www.drupal.org/docs/develop/creating-modules/let-drupal-know-about-your-module-with-an-infoyml-file)
- [Drupal FormatterBase e DI](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Field%21FormatterBase.php/class/FormatterBase/main)
- [Drupal FileAccessControlHandler](https://api.drupal.org/api/drupal/core%21modules%21file%21src%21FileAccessControlHandler.php/main)
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
