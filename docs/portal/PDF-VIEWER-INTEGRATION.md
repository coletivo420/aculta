# Integração compartilhada de documentos PDF

Revisado em 2026-10-17. Esta norma define arquitetura e critérios de integração compartilhada.

## Fontes de verdade e escopo

Media (`document`), entidades que referenciam campos File e File são os registros do documento, associação e metadados. O submódulo próprio `aculta_pdf_viewer` integra formatters de Media e File ao PDF.js local, com versão fixada e proveniência/licença preservadas. O contrib Drupal PDF 1.3 foi removido do Composer em 2026-10-21: estava desativado no Runtime, sem uso ou configuração, e sua função de leitura já é coberta pelo leitor ACULTA. PDFPreview permanece como integração opcional de miniatura da primeira página, usada pelo adapter; falha de preview ou ausência de ferramenta de imagem jamais pode impedir leitura ou download autorizado.

O visualizador é uma capacidade global: aulas e atividades LMS, artigos/blog/Revista420, Wiki420, Fórum, projetos, documentos institucionais e arquivo público usam a mesma política e o mesmo componente funcional. ACULTA420 é o único dono da apresentação SDC; as decisões de acesso a Media, entidade, campo e File pertencem ao Drupal/Portal.

## Inventário confirmado

- Há Media bundle `document`, source `file`, campo obrigatório `field_media_document`, cardinalidade 1 e esquema `public://`. O campo permite PDF e diversos formatos de documento (txt, rtf, Office/ODF e Apple); o fluxo PDF deve rejeitar ou degradar tipos não PDF, não assumir que todo `document` é PDF.
- Existe tipo de conteúdo `document` para documentos institucionais, com `field_document` limitado a `pdf`; coexistem, portanto, referência direta e Media. A integração deve consolidar a UX sem migração implícita ou remoção de conteúdo.
- O formatter `aculta_pdf_file` atende esse campo File direto nos displays `node.document.default` e `node.document.full`. Só PDFs permanentes públicos recebem viewer; acesso da entidade, do campo e de download é acumulado no render. Outros formatos e arquivos privados mantêm o link Core autorizado.
- Revista/editorial, Wiki e Fórum usam `media_embed` do Core para resolver Media `document` ao display comum, sempre restrito ao bundle `document`. Os editores CKEditor 5 da Wiki e do Fórum foram validados com seus formatos; Source Editing preserva as construções HTML históricas que não têm botão dedicado. A autorização do conteúdo continua sob Media/Core.
- `filter.format.full_html` e `revista_editorial` habilitam `entity_embed` e incluem `document` na lista de bundles. Confirmar as configurações CKEditor/Entity Embed completas por uso em conteúdo e validar acesso de mídia embutida; configuração habilitada não prova que o viewer funciona nem que o acesso está correto.
- O papel editorial único `content_editor` recebe `create document media` para permitir o upload/seletor de PDF pela biblioteca do CKEditor 5. O bundle `document` usa `public://`; arquivos nesse bundle devem ser destinados à publicação e nunca conter material confidencial.
- PDF.js 6.4.299, PDFPreview 1.2.0 e ImageMagick contrib 4.0.2 fazem parte do Composer lock nesta entrega; `drupal/pdf` 1.3.0 foi removido. H5P e Entity Print não fazem parte do lock. O código local do viewer está em `web/modules/custom/aculta_portal/modules/aculta_pdf_viewer`; dependências operacionais ImageMagick/Ghostscript precisam ser verificadas separadamente por ambiente e ImageMagick não deve ser selecionado como toolkit padrão só para previews.
- Source exato do tag `8.x-1.3` consultado em 2026-10-16: `pdf.libraries.yml` registra `/libraries/pdf.js/build/pdf.js`; formatter `PdfDefault` escolhe `pdf.settings:custom_viewer` ou `/libraries/pdf.js/web/viewer.html`, forma iframe para o viewer com `?file=` contendo URL absoluta, e o JS/worker de `PdfPages` também usam `.js`. O caminho padrão fixa localização/nome e não tem alternativa configurável para os imports `.mjs`; somente o arquivo do viewer pode ser substituído pela configuração `custom_viewer`. Issue #3417349 pede troca para `.mjs` e `type="module"`, segue sem release estável com essa alteração confirmada. PDF.js 6.3.289 distribui `build/pdf.mjs`, `build/pdf.worker.mjs`, `web/viewer.html`, `web/viewer.mjs`, CSS e assets do viewer. Compatibilidade direta do PDF 1.3 com 6.3.289 está, portanto, **incompatível no loader/API conforme fonte examinada**, não apenas não confirmada.
- Código exato do PDFPreview 8.x-1.2 também foi lido. `PDFPreviewGenerator` usa ImageToolkit (`arguments()`), define source format PDF, source frame `[0]`, resize/quality e salva derivado; o módulo não chama `proc_open()` diretamente: execução externa cabe ao toolkit contrib e ainda requer auditoria. Caminho do derivado usa `system.file:default_scheme`, não o scheme original do PDF. Se o default for `public`, o preview pode expor visualmente um PDF privado por URL pública. Até provar access equivalente, PDFPreview não pode atender arquivos privados; indisponibilidade/falha deve cair para ícone neutro.
- PDFPreview 8.x-1.2 é estável para Drupal 10/11 e seu metadata requer `drupal/imagemagick ^3.7 || ^4.0`. A combinação Composer validada é PDFPreview 1.2.0 + ImageMagick 4.0.2; ImageMagick 5.x não resolve com esse release estável. ImageMagick 5.0.1 declara Drupal 11.3+ e PHP 8.5, mas a compatibilidade de PDFPreview não foi liberada em stable.

## Decisão de integração para F2/F3

1. Configurar `custom_viewer` não resolve a incompatibilidade de `.js` no `pdf.libraries.yml`/`PdfPages`; não é adaptação suficiente para PDF.js 6.3.289. O formatter padrão requer nomes antigos `pdf.js`/`pdf.worker.js` e API global `pdfjsLib`, enquanto PDF.js moderno distribui módulos ES.
2. Decisão implementada: adapter pequeno no submódulo independente `aculta_pdf_viewer` (formatter de campo + builder) para viewer moderno local e API ESM, com Media/File access validado e render arrays/cacheability. O módulo PDF contrib foi removido por redundância e incompatibilidade do loader; não aplicar patch contrib preventivamente nem manter um fork como padrão.
3. O viewer recebe somente referência de entidade autorizada (Media ID/revisão e campo/file associado), modo de exibição e título acessível. Não aceitar URL arbitrária de request, querystring controlada pelo editor, nem URL externa. A URL de bytes deve ser produzida pelo Drupal com File Access e contexto de acesso atual.
4. Media sem PDF, arquivo ausente, viewer indisponível, erro JS ou PDFPreview indisponível mantém título, descrição e ação de abrir/baixar conforme access. Fallback não eleva acesso e oferece link comum somente se autorizado. Para `private://`, a URL local do File URL Generator usa a rota privada Core, que revalida referência e acesso no request de bytes; o render do viewer recebe max-age zero. PDFPreview é exclusivamente público.
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

`private://` não significa audiência restrita: a rota Core consulta File/Media
Access. A Config Sync atual concede `view media` aos papéis anônimo e
autenticado, então uma Media publicada com PDF `private://` pode ser lida por
ambos. O submódulo opcional `aculta_pdf_viewer_group` agora fornece relação
Media `document` para Group `lms_course`; os papéis Group estudante e educador
recebem a permissão específica de visualização. O Kernel do submódulo valida
membro, não membro, remoção da relação e revogação pelo endpoint privado Core.
Essa evidência isolada não substitui a homologação no Runtime: não usar PDF
privado como confidencialidade de turma até validar a configuração e esses
fluxos no ambiente de teste. O Kernel do viewer base também cobre o acesso
anônimo quando o papel anônimo recebe `view media`.

A página do projeto recomenda permitir leitura de PDF em ImageMagick `policy.xml`; isso não autoriza alteração global de policy, execução de PostScript, nem permissões amplas. Alteração de policy do sistema requer revisão operacional e de segurança própria. A configuração Drupal versionada habilita o formato PDF no toolkit ImageMagick usado pelo PDFPreview; ela não altera a policy do sistema.

Para habilitar conversão real em cada ambiente, revisar binários e timeout, Ghostscript e configuração de delegates, policies PDF/PS, limites CPU/memória/tamanho/páginas, diretório temporário, permissões, validade de derivados, concorrência e invalidação em replace/delete. Preferir limites operacionais restritivos e PDFs não processados se o ambiente não puder satisfazê-los. ImageMagick não será o toolkit Drupal padrão apenas por instalar PDFPreview.

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
página, tela cheia e link de fallback. Firefox headless no Runtime homologou
14 páginas e controles em desktop/viewport estreito; nomes acessíveis, foco
visível, live regions e alvos de toque passaram no smoke de PV6. A matriz ampla
de browsers, CSP e produção LiteSpeed continua em [TESTES-NAVEGADOR](TESTES-NAVEGADOR.md).

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

PDFPreview 1.2.0 e ImageMagick contrib 4.0.2 foram resolvidos e adicionados ao
Composer, com configurações versionadas para uso opcional. No Runtime de teste,
os módulos permanecem desativados. O host tem Ghostscript 10.05.1 e os dados
comuns do ImageMagick 7, mas não os executáveis `magick`, `convert` ou
`identify`. A policy instalada foi inspecionada: mantém `Undefined` sem acesso,
limites de recursos e nega coders URL/HTTP/HTTPS; não foi encontrada regra
específica permitindo PDF. Como o executável está ausente, conversão não foi
testada. Nenhuma policy foi alterada. Hostinger/LiteSpeed segue sem verificação
operacional. A geração real permanece DEFERRED; falta/erro de preview não afeta
o viewer nem o link autorizado.

Composer `--dry-run` resolveu PDFPreview 1.2.0 + ImageMagick 4.0.2 e cinco
dependências transitivas. A instalação foi então registrada em
`composer.json`/`composer.lock`, sem advisories; essa resolução PHP não verifica
binários, PHP-FPM, policy nem a segurança do processamento PDF no servidor.

## PV4 — miniaturas opcionais implementadas

O serviço `PdfPreviewThumbnailBuilder` chama o gerador PDFPreview somente para
File permanente `public://` identificado como PDF, com tamanho entre 1 byte e
20 MiB, `system.file:default_scheme` em `public` e configuração fixa do derivado
em `public://pdfpreview`, JPEG 480×640 e qualidade 82. O processo recebe limite
de 15 segundos e o timeout padrão do serviço ImageMagick é restaurado ao fim.
URL e dimensões do resultado são validadas antes de compor o render array. O
preview tem alt text, dimensões, lazy loading, cache tags do File e falha de
forma silenciosa para o usuário, registrada no log; o leitor e o fallback
autorizado continuam disponíveis.

Os serviços contrib são referências opcionais no container. Assim, o
`aculta_pdf_viewer` existente inicializa mesmo antes de PDFPreview/ImageMagick
serem habilitados. Uma falha de container identificada durante a implementação
por referência obrigatória a `pdfpreview.generator` foi corrigida dessa forma;
após a correção, Drush iniciou e os hosts de teste principal, cursos, revista e
fórum responderam HTTP 200. Unit e Kernel passaram em execução isolada. A
miniatura efetiva continua não testada porque os módulos estão desativados e o
binário ImageMagick não existe no Homelab. Nenhuma configuração ativa foi
importada e nenhuma policy de sistema foi modificada.

## PV5 — arquivos privados

O adapter permite PDF `private://` por URL local gerada pelo Core somente após
access de Media, campo e File (`view` e `download`). A rota Core
`system.private_file_download` e `hook_file_download` do módulo File repetem a
autorização pelas entidades e campos que referenciam o arquivo e devolvem uma
resposta privada. O render recebe max-age zero; não há endpoint ou token próprio
do PDF Viewer. Preview permanece bloqueado para scheme privado. A URL de
arquivo público continua acessível diretamente e não protege arquivos do
bundle `document`, que segue usando `public://`.

Unit validou URL local do endpoint, max-age zero e ausência do gerador de
miniaturas para privado. Kernel confirmou resposta 200 e cache privado para o
usuário autorizado, e 403 para usuário autenticado sem permissão e acesso
anônimo sem `view media`. Ao conceder a permissão ao papel anônimo, a rota
respondeu 200. Outro usuário com `view media` recebeu 200 para Media publicada
e 403 após sua despublicação. Config Sync concede `view media` a ambos os papéis
anônimo e autenticado; portanto, `private://` não torna uma Media publicada
confidencial. O submódulo Group↔Media e permissões de membro/educador estão
versionados. Kernel valida 200 para membro e 403 para não membro, relação
removida e membership revogada; ainda faltam importação da configuração e
validação destes casos, do cache e do carregamento PDF.js/range no Runtime. A
conversão real de miniaturas permanece DEFERRED
porque não há binário ImageMagick no Homelab. O arquivo
[ROADMAP.md do submódulo](../../web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/docs/ROADMAP.md)
mantém os critérios restantes de PV5–PV7.

**Validação F3:** formatter e display estão no código e em `config/sync`; o
Runtime `aculta_runtime` foi atualizado pontualmente, sem import global. Um
Media `document` e artigo temporários usaram o PDF de amostra oficial e
renderizaram via CKEditor Media Embed → formatter. Firefox 153.4 ESR confirmou
14 páginas, navegação, zoom 125%, busca (cinco resultados), tela cheia e zero
erros JavaScript. A UI CKEditor 5, autenticada por link único gerado no host
Conta, selecionou uma Media `document` existente, inseriu o widget e salvou o
artigo publicado. A primeira tentativa de edição, com link de login emitido no
host Magazine, retornou 403; ao repetir pelo fluxo documentado no host Conta,
a edição funcionou. A fixture Media foi criada
por Drush, portanto upload/criação como `content_editor` ainda segue DEFERRED.
Todos os objetos e arquivos temporários foram removidos; o bundle voltou a zero.
PHPUnit/gates e resolução Composer estão registrados na PR. Acesso privado por
usuário/turma, PDFPreview/thumbnails, CSP, auditoria ampliada com tecnologias assistivas e Hostinger
seguem **DEFERRED**. Não executar `drush cim` com URI `http://default`.

Composer dry-run inicialmente propunha atualizações indiretas. A resolução final
foi limitada a `drupal/pdf` e `mozilla/pdfjs-dist`, usando `--with-dependencies
--minimal-changes`; o Composer ainda alinhou `drupal/core-dev` 11.4.8→11.4.10,
PhotoSwipe 5.0.11→5.0.12 e instalou o plugin de captions 1.2.7 já exigido pelo
manifesto. Nenhuma configuração Drupal ou estado do Runtime foi alterado.

