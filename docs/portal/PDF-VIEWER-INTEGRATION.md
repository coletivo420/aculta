# Integração compartilhada de documentos PDF

Revisado em 2026-10-16. Esta norma define arquitetura e critérios de integração compartilhada.

## Fontes de verdade e escopo

Media (`document`) e File são os registros do arquivo e metadados. O submódulo próprio `aculta_pdf_viewer` integra o formatter Media ao PDF.js local, com versão fixada e proveniência/licença preservadas. O contrib Drupal PDF 1.3 permanece no Composer como referência, mas seu formatter não é usado devido ao loader legado; PDFPreview é uma melhoria opcional de miniatura da primeira página. Falha de preview ou ausência de ferramenta de imagem jamais pode impedir leitura ou download autorizado.

O visualizador é uma capacidade global: aulas e atividades LMS, artigos/blog/Revista420, Wiki420, Fórum, projetos, documentos institucionais e arquivo público usam a mesma política e o mesmo componente funcional. ACULTA420 é o único dono da apresentação SDC; a decisão de abrir/autorizar Media pertence ao Drupal/Portal.

## Inventário confirmado

- Há Media bundle `document`, source `file`, campo obrigatório `field_media_document`, cardinalidade 1 e esquema `public://`. O campo permite PDF e diversos formatos de documento (txt, rtf, Office/ODF e Apple); o fluxo PDF deve rejeitar ou degradar tipos não PDF, não assumir que todo `document` é PDF.
- Existe tipo de conteúdo `document` para documentos institucionais, com `field_document` limitado a `pdf`; coexistem, portanto, referência direta e Media. A integração deve consolidar a UX sem migração implícita ou remoção de conteúdo.
- `filter.format.full_html` e `revista_editorial` habilitam `entity_embed` e incluem `document` na lista de bundles. Confirmar as configurações CKEditor/Entity Embed completas por uso em conteúdo e validar acesso de mídia embutida; configuração habilitada não prova que o viewer funciona nem que o acesso está correto.
- O papel editorial único `content_editor` recebe `create document media` para permitir o upload/seletor de PDF pela biblioteca do CKEditor 5. O bundle `document` usa `public://`; arquivos nesse bundle devem ser destinados à publicação e nunca conter material confidencial.
- `drupal/pdf` 1.3.0 e PDF.js 6.4.299 fazem parte do Composer lock. PDFPreview, ImageMagick, H5P e Entity Print não fazem parte do lock. O código local do viewer está em `web/modules/custom/aculta_portal/modules/aculta_pdf_viewer`; dependências operacionais ImageMagick/Ghostscript precisam ser verificadas separadamente por ambiente e ImageMagick não deve ser selecionado como toolkit padrão só para previews.
- Source exato do tag `8.x-1.3` consultado em 2026-10-16: `pdf.libraries.yml` registra `/libraries/pdf.js/build/pdf.js`; formatter `PdfDefault` escolhe `pdf.settings:custom_viewer` ou `/libraries/pdf.js/web/viewer.html`, forma iframe para o viewer com `?file=` contendo URL absoluta, e o JS/worker de `PdfPages` também usam `.js`. O caminho padrão fixa localização/nome e não tem alternativa configurável para os imports `.mjs`; somente o arquivo do viewer pode ser substituído pela configuração `custom_viewer`. Issue #3417349 pede troca para `.mjs` e `type="module"`, segue sem release estável com essa alteração confirmada. PDF.js 6.3.289 distribui `build/pdf.mjs`, `build/pdf.worker.mjs`, `web/viewer.html`, `web/viewer.mjs`, CSS e assets do viewer. Compatibilidade direta do PDF 1.3 com 6.3.289 está, portanto, **incompatível no loader/API conforme fonte examinada**, não apenas não confirmada.
- Código exato do PDFPreview 8.x-1.2 também foi lido. `PDFPreviewGenerator` usa ImageToolkit (`arguments()`), define source format PDF, source frame `[0]`, resize/quality e salva derivado; o módulo não chama `proc_open()` diretamente: execução externa cabe ao toolkit contrib e ainda requer auditoria. Caminho do derivado usa `system.file:default_scheme`, não o scheme original do PDF. Se o default for `public`, o preview pode expor visualmente um PDF privado por URL pública. Até provar access equivalente, PDFPreview não pode atender arquivos privados; indisponibilidade/falha deve cair para ícone neutro.
- PDFPreview 8.x-1.2 é estável para Drupal 10/11. Seu Composer metadata requer `drupal/imagemagick ^3.7 || ^4.0`, incompatível com ImageMagick 5.x; a issue upstream “Support for ImageMagick 5.x” não torna uma branch dev adequada para produção. ImageMagick 5.0.1 declara Drupal 11.3+ e PHP 8.5. A combinação estável precisa de release compatível antes de ser proposta.

## Decisão de integração para F2/F3

1. Configurar `custom_viewer` não resolve a incompatibilidade de `.js` no `pdf.libraries.yml`/`PdfPages`; não é adaptação suficiente para PDF.js 6.3.289. O formatter padrão requer nomes antigos `pdf.js`/`pdf.worker.js` e API global `pdfjsLib`, enquanto PDF.js moderno distribui módulos ES.
2. Decisão implementada: adapter pequeno no submódulo independente `aculta_pdf_viewer` (formatter de campo + builder) para viewer moderno local e API ESM, com Media/File access validado e render arrays/cacheability. Alternativamente, só considerar um build oficial de PDF.js compatível com loader PDF 1.3 depois de pin, licença, CVE review e teste, se isso cumprir o requisito de versão atualizada. Não aplicar patch contrib preventivamente nem manter um fork como padrão.
3. O viewer recebe somente referência de entidade autorizada (Media ID/revisão e campo/file associado), modo de exibição e título acessível. Não aceitar URL arbitrária de request, querystring controlada pelo editor, nem URL externa. A URL de bytes deve ser produzida pelo Drupal com File Access e contexto de acesso atual.
4. Media sem PDF, arquivo ausente, viewer indisponível, erro JS ou PDFPreview indisponível mantém título, descrição e ação de abrir/baixar conforme access. Fallback não eleva acesso e oferece link comum somente se autorizado.
5. ACULTA420 recebe render array autorizado e dimensões/estado neutros. Paginação, contagem de páginas ou erro só aparecem se obtidos pelo viewer sem divulgar conteúdo não autorizado. `CacheableMetadata` agrega Media, File, usuário/grupo/turma e access result; respostas de arquivo privado usam cache privado/no-store conforme implementação Core e nunca cache público compartilhado.

### Contrato de apresentação

| Campo neutro | Regra |
| --- | --- |
| Mídia | Entidade Media ou render array já autorizado; sem hostname/ID em estado visual desnecessário |
| Modo | `embedded`, `link` ou `download`; decisão de configuração do display, não parâmetro livre do usuário |
| Arquivo | URL Drupal gerada a partir do objeto File; respeita esquema, entidade e File Access |
| Título/alternativa | Título Media e nome/descrição acessíveis; controles com nome acessível |
| Acesso | Resultado de access e membership da turma avaliados no servidor em cada request |
| Fallback | Download/link somente com permissão; caso contrário mensagem genérica e nenhum endereço de arquivo |
| Layout | Largura fluida, altura com proporção adequada e limites para viewport pequena; tema controla aparência |
| Estado | Paginação e erro de renderização, sem tornar estado de viewer prova de conclusão LMS |
| Cache | Tags da Media/File/configuração, contexts de usuário/permissão/grupo necessários e max-age privado quando houver conteúdo privado |

## PDFPreview, miniaturas e risco operacional

PDFPreview gera a primeira página usando ImageMagick e binários relacionados. O código 1.2 escreve o derivado em scheme baseado em `system.file:default_scheme`, não no scheme do source. Definir ou manter o default global como `public` pode expor um derivado de documento originalmente privado, mesmo que o PDF fonte permaneça protegido. Não usar PDFPreview em conteúdo privado antes de uma implementação/teste que preserve File Access para derivado (ou escolha um destino privado e entrega autorizada).

A página do projeto recomenda permitir leitura de PDF em ImageMagick `policy.xml`; isso não autoriza alteração global de policy, execução de PostScript, nem permissões amplas. Alteração de policy do sistema requer revisão operacional e de segurança própria.

Antes de F3, revisar o código/tag de PDFPreview e ImageMagick: chamada `proc_open`/processo, argumentos sem shell injection, binários e timeout, Ghostscript e configuração de delegates, policies PDF/PS, limites CPU/memória/tamanho/páginas, diretório temporário, permissões, validade de derivados, concorrência e invalidação em replace/delete. Preferir limites operacionais restritivos e PDFs não processados se o ambiente não puder satisfazê-los. ImageMagick não será o toolkit Drupal padrão apenas por instalar PDFPreview.

Preview é cache derivado e reconstruível; testar falhas, fila/lote, aquecimento de cache e respostas idempotentes. Se thumbnail falhar/estiver atrasada, exibir ícone genérico e continuar viewer/download autorizado. Verificar Hostinger/LiteSpeed com suporte de binários e policy sem presumir acesso a `httpd.conf` ou execução de processos.

## Segurança e validação antes de release

- Testes de Media/File publicados e não publicados, público e privado, usuários A/B, duas turmas, usuário sem membership e administrador; tentativa direta pela URL de arquivo e URL de viewer.
- Não confundir ausência de botão de download com proteção; não expor `public://` para material que deva ser privado. A configuração atual `document` usa esquema público, logo F3 não deve usá-la para trabalhos de estudante sem decisão e migração próprias.
- Avaliar CSP, iframe sandbox/allowlist, origem same-origin, `X-Content-Type-Options`, MIME e `Content-Disposition`; não liberar URL remota; não usar CDN em produção.
- PDFs grandes e maliciosos, timeout, worker `.mjs`, MIME JavaScript, CSP, cache, bloqueio de acesso após mudança de membership e falhas de thumbnail.
- Confirmar aviso de licença e proveniência de cada arquivo distribuído do PDF.js. Atualização exige pin de versão e revisão de segurança.

## Pesquisa e fontes upstream

- [PDF module e instruções da biblioteca](https://www.drupal.org/project/pdf)
- [PDF releases](https://www.drupal.org/project/pdf/releases)
- [Issue PDF: referências `.mjs` (#3417349)](https://www.drupal.org/project/pdf/issues/3417349)
- [PDFPreview](https://www.drupal.org/project/pdfpreview)
- [PDFPreview releases](https://www.drupal.org/project/pdfpreview/releases)
- [Issue queue PDFPreview: compatibilidade ImageMagick 5.x](https://www.drupal.org/project/issues/pdfpreview)
- [ImageMagick releases](https://www.drupal.org/project/imagemagick/releases)
- [PDF.js v6.3.289](https://github.com/mozilla/pdf.js/releases/tag/v6.3.289) e [layout prebuilt/API](https://mozilla.github.io/pdf.js/getting_started/)
- [Drupal FileAccessControlHandler](https://api.drupal.org/api/drupal/core%21modules%21file%21src%21FileAccessControlHandler.php/main) e [FileDownloadController](https://api.drupal.org/api/drupal/core%21modules%21system%21src%21FileDownloadController.php/function/FileDownloadController%3A%3Adownload/main)
- [Drupal Field Formatter API](https://api.drupal.org/api/drupal/core%21modules%21field%21field.api.php/group/field_formatter/main)
- [Source `pdf.libraries.yml` do tag 8.x-1.3](https://git.drupalcode.org/project/pdf/-/blob/8.x-1.3/pdf.libraries.yml), [`PdfDefault`](https://git.drupalcode.org/project/pdf/-/blob/8.x-1.3/src/Plugin/Field/FieldFormatter/PdfDefault.php) e [`PdfPages`](https://git.drupalcode.org/project/pdf/-/blob/8.x-1.3/src/Plugin/Field/FieldFormatter/PdfPages.php)
- [Source PDFPreview 8.x-1.2 `PDFPreviewGenerator`](https://git.drupalcode.org/project/pdfpreview/-/blob/8.x-1.2/src/PDFPreviewGenerator.php) e [Composer metadata](https://git.drupalcode.org/project/pdfpreview/-/blob/8.x-1.2/composer.json)

## F2 — integração implementada

Em 2026-10-16, F2 instalou `drupal/pdf` 1.3.0 e a distribuição oficial Mozilla
PDF.js 6.4.299 pelo Composer. A versão 6.4.299 era a release estável marcada
como Latest no upstream consultado. O ZIP de distribuição tem SHA-1
`fd7b4405be7d5f41db503c5c5ebf898f8ab8fd71` e SHA-256
`f2245eb7ef3f674c872fe568a177bfec15874f5c034613e55d8e307f93ebd86d`; a
proveniência e pin Composer estão em `composer.json`/`composer.lock`. O pacote
inclui licença Apache-2.0 e arquivos `.mjs` oficiais.

O roadmap próprio do módulo registra a integração de autoria: inserir Media `document` pelo CKEditor 5 na Revista, usando o botão `drupalMedia` já configurado em `revista_editorial`, e validar o leitor inline integrado ao site. O formato, filtro, bundle e permissão foram confirmados no Runtime e no Config Sync; o fluxo editorial segue sem smoke test porque não há PDF/embedding de Revista de teste no Runtime. O escopo funcional inspirado no TNC está em [ROADMAP.md do PDF Viewer](../../web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/docs/ROADMAP.md); TNC é referência WordPress, sem reaproveitamento de código.

O serviço atual `aculta_pdf_viewer.builder` no módulo `aculta_pdf_viewer` é o
adapter de renderização neutro do site. A versão inicial do F2 esteve no Portal
e foi extraída para o submódulo no commit de arquitetura `0.4.0-dev.56`.
Ele verifica `Media::access('view')`, acesso ao campo e ao File, exige bundle
`document`, MIME/extensão PDF, arquivo permanente e `public://` com URL local
gerada pelo File URL Generator. Acrescenta as dependências de cache de Media,
File e resultados de acesso. Não recebe URL por query/formulário, não aceita
host/URL externa, e entrega link de fallback somente junto ao mesmo arquivo
público autorizado. O JS importa `pdf.mjs`, define `pdf.worker.mjs` local, mostra
uma página por vez e oferece navegação anterior/próxima, zoom, busca textual por
página, tela cheia e link de fallback. A interação precisa de smoke test em
navegador antes de declarar a entrega visual concluída.

O renderer `PdfDefault` do contrib 1.3 permanece incompatível com o pacote ESM
moderno (`.js`/global `pdfjsLib` versus `.mjs`). O adapter não usa o formatter
legado nem altera contrib. O submódulo implementa e liga o formatter ao display
Media document; não depende de `aculta_lms` nem do serviço geral do Portal.

**Validação F2:** browser smoke em Chrome headless isolado validou o import
ESM, worker local e renderização de 14 páginas do PDF de amostra incluído na
distribuição oficial. O builder também passou Unit para acesso permitido e
negado. Não foi feita validação em Drupal Runtime; o Drush aponta
`http://default`, fora do URI canônico do Homelab. O builder rejeita arquivos
privados nesta etapa. Os arquivos do bundle Media `document` usam
`public://`, portanto essa configuração não deve ser usada para conteúdo
privado/estudantil; F3 deve validar um delivery path protegido antes de exibir
PDF privado e nunca inferir proteção a partir da ocultação do link.

## F3 — integração do display Media

O display padrão do Media `document` agora seleciona `aculta_pdf_media`. O formatter
valida acesso à entidade Media, campo e arquivo; PDFs locais públicos passam ao
builder PDF.js. Arquivos não PDF preservam o link Core. Para PDF privado, o viewer
é negado e o fallback usa a URL privada gerada pelo File URL Generator somente
quando `File::access('download')` autoriza; a rota de arquivo privado Core volta a
validar o acesso à entidade referenciadora e ao campo. Este fallback não prova
isolamento de turma em runtime e não converte o viewer em leitor privado.

O bundle `document` continua `public://`; não se alterou esquema nem se migrou
arquivo existente. Logo, o bundle não pode ser usado para documentos que exigem
confidencialidade por turma. O download de arquivo público não é restringido por
access da entidade no Core, então esconder o link ou validar Media não protege a
URL direta existente.

PDFPreview 8.x-1.2 não foi instalado/configurado. Não há binário ImageMagick
(`magick`, `convert`, `identify`) disponível neste checkout; `gs` existe, mas não
supre o conversor. O release estável do ImageMagick 5.0.1 é compatível com Core
11.3/PHP 8.5, enquanto PDFPreview 1.2 declara `^3.7 || ^4.0` para o módulo
ImageMagick; a issue de suporte a 5.x permanece em revisão. Instalar o 4.x sem
binários funcionais não produziria thumbnails, e liberar política PDF/PS não foi
feito. Hostinger/LiteSpeed segue sem verificação operacional. Preview permanece
opcional: falta/erro dele não afeta o viewer nem o link autorizado.

Composer `--dry-run` resolveu PDFPreview 1.2.0 + ImageMagick 4.0.2 e cinco
dependências transitivas (sete instalações no total, sem updates ou remoções e
sem advisories); `composer.json`/`composer.lock` permaneceram intactos. Essa
resolução PHP não verifica binários, PHP-FPM, policy nem a segurança do
processamento PDF no servidor.

**Validação F3:** formatter e display adicionados no código e em `config/sync`;
PHPUnit/gates e resolução Composer registrados na PR. Importação no Runtime,
renderização real, arquivos privados por usuário/turma, geração de thumbnail e
Hostinger ficam **DEFERRED**. Não executar `drush cim` com URI `http://default`.

Composer dry-run inicialmente propunha atualizações indiretas. A resolução final
foi limitada a `drupal/pdf` e `mozilla/pdfjs-dist`, usando `--with-dependencies
--minimal-changes`; o Composer ainda alinhou `drupal/core-dev` 11.4.8→11.4.10,
PhotoSwipe 5.0.11→5.0.12 e instalou o plugin de captions 1.2.7 já exigido pelo
manifesto. Nenhuma configuração Drupal ou estado do Runtime foi alterado.

