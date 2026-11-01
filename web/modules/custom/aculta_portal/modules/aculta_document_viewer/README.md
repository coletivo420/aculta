# ACULTA Document Viewer

**Versão:** `0.1.0-rc.1` (`RC`).

Miniaturas PDF públicas são geradas pelo serviço compartilhado
`aculta_image.pdf_thumbnail` usando PHP Imagick e Ghostscript; DOCX e planilhas
mantêm as prévias de documento e o fallback Core descritos abaixo.

`aculta_document_viewer` fornece leitores locais compartilhados de PDF, DOCX e
planilhas, com sufixos por tipo de arquivo (`aculta_document_viewer_pdf`,
`aculta_document_viewer_doc` e `aculta_document_viewer_sheet`) em bibliotecas,
serviços, FieldFormatters e subabas administrativas. Não pertence ao LMS:
conteúdo pode usar o formatter de Media `document`, o formatter
`aculta_document_viewer_pdf_file` em um campo File direto ou os builders
(`aculta_document_viewer.pdf_builder`, `aculta_document_viewer.doc_builder` e
`aculta_document_viewer.sheet_builder`) consumidos por integrações como o Fórum
e o LMS.

O display padrão do Media `document` usa o formatter `aculta_document_viewer_media`,
que seleciona o leitor local pelo par MIME/extensão depois de confirmar acesso
à Media, ao campo e ao download do arquivo. DOCX (`aculta_document_viewer_doc`)
e planilhas (`aculta_document_viewer_sheet`) mantêm um link
Core de download permitido ao lado da prévia; formato incompatível, pacote
inválido ou leitor indisponível fica somente com o link Core. O formatter de
campo do LMS mantém seu display próprio.

Padrões globais ficam em ACULTA → Site → Leitor de documentos
(`/painel-administrativo/aculta/leitor-documentos`), separados nas subabas
horizontais **Geral** (`/painel-administrativo/aculta/leitor-documentos`, seção
`aculta_document_viewer_general` com opções conjuntas do módulo), **PDF**
(`/painel-administrativo/aculta/leitor-documentos/pdf`, seção
`aculta_document_viewer_pdf` com opções específicas do PDF), **Documentos**
(`/painel-administrativo/aculta/leitor-documentos/documentos`, seção
`aculta_document_viewer_doc` com opções específicas do DOCX) e **Planilhas**
(`/painel-administrativo/aculta/leitor-documentos/planilhas`, seção
`aculta_document_viewer_sheet` com opções específicas de planilhas). O painel pertence ao módulo Portal;
formatters PDF usam esses valores por padrão e podem optar por configuração
específica do campo. A subaba Documentos define cabeçalhos, rodapés, notas e quebras
para DOCX, e a subaba Planilhas define modo e paginação para planilhas. Planilhas seguem somente
para leitura e seus limites máximos não são configuráveis. A configuração fica
em `aculta_document_viewer.settings` e faz parte do Config Sync. Rolagem horizontal e página dupla são aplicadas pelo
wrapper ao viewer PDF.js completo antes de sua inicialização. O canvas ACULTA
continua em navegação paginada. Em telas móveis, página dupla fica desligada por
padrão e respeita a opção explícita do painel/formatter.

## Responsabilidade e limites

- Fornece leitores PDF (`aculta_document_viewer_pdf`), DOCX (`aculta_document_viewer_doc`)
  e planilhas (`aculta_document_viewer_sheet`), integrando Media/File ao motor PDF.js local
  e aos workers de documentos e planilhas.
  Mantém configuração e políticas próprias para Media/File, sem armazenar
  conteúdo, permissões, documentos ou progresso próprios.
- CSP é observada com `drupal/csp` em Report-Only e endpoint local `drupal/reporting`;
  Config Sync deixa `enforce` desligado. O candidato só vai a RC após
  testes/gates e revisão visual pré-deploy; Alpha confere relatórios e enforcement
  exige decisão posterior. Veja [PV7](docs/ROADMAP.md) e o [ciclo de release](../../../../../../docs/operations/RELEASE-LIFECYCLE.md).
- O builder incorpora PDFs permanentes em `public://` ou `private://`, somente
  após autorização de Media, campo e File. `private://` usa a rota Core e cache
  de render max-age zero.
- DOCX e planilhas privados montam `/system/files?file=...`, via rota Core
  `system.files`. No Runtime de teste, em 2026-10-21, query e path foram ambos
  servidos com HTTP 200 quando o arquivo da fixture pertencia ao usuário
  `aculta` do PHP-FPM. O erro `File must be readable` veio de uma fixture
  criada pelo Drush com proprietário diferente; a entrega do viewer DOCX no
  navegador foi validada em Firefox ESR com um DOCX OOXML completo: status
  `done`, iframe sandboxed preservado e conteúdo visível. Uma falha anterior
  em mostrar conteúdo revelou que o iframe era removido após sucesso; a dev.12
  mantém-no em caso de sucesso e ainda o encerra em erro/timeout. A validação
  não cobre a matriz de viewports nem documentos Office adicionais.
- O esquema `private://` não define a audiência: a permissão da Media governa a
  leitura. Config Sync concede `view media` aos papéis anônimo e autenticado;
  Media publicada com arquivo privado pode ser lida por ambos. Não usar esse
  fluxo para documentos confidenciais de turma.
- A integração Group é interna a `aculta_document_viewer` (módulo único); vincula Media `course_document` ao
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
- O guarda `CspReportRequestGuardSubscriber` (limita e valida os corpos CSP antes do
  controller do Reporting) pertence a `aculta_document_viewer`. Não cria rota nem controller próprios. A entrega de PDFs privados continua sob
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
   atributo `#[FieldFormatter]` com ID `aculta_document_viewer_pdf`. Ele valida Media,
   acesso ao campo e `File::access('download')`; delega PDFs ao builder, e usa o
   tema Core `file_link` para documentos que não entram no viewer/fallback.
2. `Plugin/Field/FieldFormatter/AcultaPdfFileFormatter` (`aculta_document_viewer_pdf_file`) aplica o mesmo viewer a
   campos File de entidades como o tipo de conteúdo `document`. Confere acesso
   da entidade, do campo e `File::access('download')`; só embute PDF permanente
   público. Para arquivo privado ou outro formato, mantém o link Core autorizado.
3. `Pdf/PdfViewerBuilderInterface` define o contrato para construir um render
   array a partir de Media ou de um File com cacheabilidade fornecida pelo
   consumidor.
4. `Pdf/PdfViewerBuilder` (`aculta_document_viewer.pdf_builder`) exige Media autorizada (`document`, `course_document` ou `forum_attachment`), acesso de visualização à Media,
   ao campo e ao File, arquivo permanente, URI `public://` ou `private://`, MIME
   `application/pdf`, extensão `.pdf` e URL local gerada pelo File URL Generator.
   Arquivo privado tem cache max-age zero e não recebe thumbnail. A resposta contém
   tags/contexts/max-age de Media, File e access, os controles acessíveis e a
   biblioteca `aculta_document_viewer/aculta_document_viewer_pdf`.
   A miniatura pública, quando habilitada, é validada e registrada como File
   temporário pelo `aculta_image.generated_file`, renderizada por
   `aculta_image.render` e removida pela limpeza normal após
   `system.file:temporary_maximum_age`.
5. `aculta_document_viewer/js/pdf-viewer.mjs` importa `pdf.mjs` e aponta o worker para
   `pdf.worker.mjs` da distribuição Mozilla instalada localmente em
   `/libraries/pdf.js`. Usa canvas, navegação, zoom com restauração, busca textual por página e
   tela cheia; erro de carregamento informa o usuário e preserva o link de
   fallback. Firefox 153.4 ESR validou leitor e inserção/salvamento de uma Media
   já existente via CKEditor no Runtime; a criação/upload pela UI como
   `content_editor` ainda não foi verificada. O registro, inclusive dos itens limpos, está em
   [TESTES-NAVEGADOR](../../../../../../docs/portal/TESTES-NAVEGADOR.md).
6. `aculta_document_viewer/viewer-wrapper.html` e
   `aculta_document_viewer/js/pdf-viewer-wrapper.mjs` aplicam os padrões do
   formatter ao viewer completo pelo evento `webviewerloaded` do PDF.js 6.4.299,
   antes de `PDFViewerApplication.run()`. Nenhum arquivo da distribuição
   Composer é alterado. O builder fornece somente URL local após validar o
   acesso; o wrapper exige PDF same-origin, e o acesso privado continua na rota
   protegida do Core. Os padrões de download e impressão também são propagados
   ao viewer completo; quando desativados no formatter, os respectivos botões
   principal e secundário do PDF.js ficam ocultos e fora da árvore acessível.

## Hooks e integração Drupal

Não há hooks PHP (`hook_*`) neste módulo; há apenas o subscriber `CspReportRequestGuardSubscriber`. Os formatters (`aculta_document_viewer_pdf`, `aculta_document_viewer_pdf_file`, `aculta_document_viewer_doc`, `aculta_document_viewer_sheet` e `aculta_document_viewer_media`) são plugins
registrados pelo mecanismo de atributos do Drupal Field API. O
JavaScript registra `Drupal.behaviors.acultaPdfViewer.attach()` e também inicia
em `DOMContentLoaded` para renderizações fora de attach Drupal. As bibliotecas (`aculta_document_viewer_pdf`, `aculta_document_viewer_doc` e `aculta_document_viewer_sheet`) são
anexadas pelo render array somente quando o respectivo builder autoriza e reconhece o formato.

O display padrão `media.document` seleciona `aculta_document_viewer_media`; o display `media.course_document` seleciona `aculta_document_viewer_pdf`; os displays
`node.document.default` e `node.document.full` selecionam `aculta_document_viewer_pdf_file` em
Config Sync.
O módulo depende do Portal (painel de administração), aculta_image, Core File e
Media; PDFPreview e ImageMagick são opcionais no runtime. Não depende do LMS nem
de `drupal/pdf`: o contrib foi removido do Composer em 2026-10-21 porque seu
formatter legado não era usado e o `aculta_document_viewer` já cobre essa função com
PDF.js ESM. PDFPreview permanece porque o adapter o usa opcionalmente para
miniaturas públicas. A configuração global é exportável e versionada; o PDF.js
é gerenciado pelo Composer do site.

## Testes e validação

Os testes Unit estão em `tests/src/Unit/`: acesso e renderização do builder;
delegação para viewer, link Core de outros formatos e fallback quando o viewer
nega o PDF. O Kernel test em `tests/src/Kernel/` verifica descoberta do módulo,
serviço e plugin sem storage ou hook de download próprio.

PHPUnit é executado com a configuração `web/core/phpunit.xml.dist`. Kernel
valida Media Embed, endpoint privado Core, acesso autorizado/negado, publicação
da Media e o efeito de `view media` para o papel anônimo. O Kernel
`CourseDocumentAccessTest` valida membro, não membro, remoção da relação Group e
revogação de membership pela URL privada conhecida. No Runtime de teste, PV5 está
ativa por importação parcial dirigida:
bundle privado, relação `lms_course` e permissões de turma estão
sincronizados. Firefox 153.4 ESR validou o viewer privado embutido em página:
membro recebeu 200 e renderizou 14 páginas; não membro e anônimo receberam 403;
remover a relação Media↔Group ou a membership negou a mesma URL (403), e
restaurar cada relação restabeleceu 200. O upload/criação foi confirmado na UI
Firefox como `content_editor`: o arquivo foi selecionado pelo input nativo, a
Media `document` foi salva e a tela de edição abriu após conceder somente
`edit own document media` ao papel editorial. A miniatura pública foi gerada no
Runtime pelo pipeline `aculta_image`. Em teste posterior do Fórum, query e path
de `system.files` entregaram HTTP 200 para um DOCX privado com propriedade
compatível com o PHP-FPM; Firefox ESR confirmou a prévia de um pacote OOXML
completo após a dev.12 manter o iframe no estado de sucesso. Fixtures, uploads,
miniatura, contas,
conteúdo e tokens de login de teste foram removidos. Nesta homologação
não houve `drush cim` global nem `updb`;
CDN, policy ImageMagick e migração de arquivos também não fazem parte do módulo.

Em 2026-10-29, a suíte completa (módulo + submódulo Group) passou com 56 testes /
645 assertions, 0 depreciações PHPUnit e 156 depreciações Core/contrib; PHPCS
Drupal/DrupalPractice passou com 0 erros e 0 avisos nos 39 arquivos PHP e `node
--check` nos 8 arquivos JS/MJS. Firefox ESR visível (`DISPLAY=:0`) validou o
viewer PDF público (`visual.py`: 7/7 checks em 1280/390/320 px) e as opções
administrativas D-48 de DOCX e planilhas (`office-options.py`: 17/17 checks em
ACULTA420 e Olivero, 1280/390/320 px, cabeçalho/rodapé/notas/quebra de página em
iframe sandboxed, modo inicial Tabela, paginação de 25 linhas e foco visível por
`Tab`). Scripts reutilizáveis:
[`tests/browser/aculta-document-viewer/`](../../../../../../tests/browser/aculta-document-viewer/).

## Documentação de arquitetura

- [Arquitetura, hooks, classes e fluxo](docs/ARQUITETURA.md)
- [Roadmap do PDF Viewer](docs/ROADMAP.md)
- [Contrato e segurança do viewer](../../../../../../docs/portal/PDF-VIEWER-INTEGRATION.md)
- [Roadmap LMS](../../../../../../docs/modules/ACULTA-LMS-ROADMAP.md)
- [Changelog do módulo](CHANGELOG.md)
