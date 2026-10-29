# Changelog — aculta_document_viewer

## 0.1.0-dev.17 — selecionar leitor no Media document — 2026-10-29

- Formatter composto `aculta_document_media` seleciona PDF, DOCX ou planilha
  pelo par MIME/extensão, mantém checagens de acesso de Media/campo/arquivo e
  preserva link Core para DOCX/planilhas e para formatos não suportados.
- O display versionado `media.document.default` agora seleciona o formatter
  composto e preserva as opções existentes de PDF. Runtime não alterado nem
  importado; ativação e smoke visual nele continuam pendentes.
- Unit: 47 testes / 275 assertions PASS. Kernel: 5 testes / 299 assertions
  PASS, 147 depreciações Core/contrib. PHP lint: 38 arquivos PASS. PHPCS
  Drupal/DrupalPractice do módulo PASS; dois avisos preexistentes permanecem
  em `aculta_document_viewer_group`.

## 0.1.0-dev.16 — baseline PHPCS PHP limpo — 2026-10-29

- Corrigidos 10 erros e 26 avisos PHPCS Drupal/DrupalPractice nos PHP do módulo e testes; as opções de zoom agora passam pela tradução Drupal. PHPCS `--extensions=php`: zero erros/avisos.
- Unit: 43 testes/228 assertions; Kernel: 5 testes/296 assertions. PASS, com 147 depreciações de Core/contrib. PHP lint: 30/30 arquivos.
- Nenhuma alteração de Runtime. O formatter atual `media.document` é o de PDF; não há display ativo usando os formatters de DOCX ou planilha. A validação visual Office/planilhas continua pendente junto a CSP e acessibilidade ampliada.

## Revisão PHPCS M-0 (2026-10-26)

- Corrigida a indentação de arrays em `PdfViewerBuilder` e adicionados imports
  explícitos no teste de Media Embed; não houve mudança de comportamento.
- Unit: 42 testes / 225 assertions PASS. Kernel: 5 erros de setup / 0
  assertions devido à cadeia Portal/User e configuração de menu ausentes no
  harness; continua sem aprovação de instalação limpa. PHP lint PASS.
- PHPCS nos seis arquivos do incremento conjunto: zero erros; um aviso de
  linha longa preexistente neste módulo. Nenhuma alteração Runtime.

## 0.1.0-dev.15 — reutilizar o motor PDF compartilhado (2026-10-26)

- `aculta_document_viewer` declara dependência de `aculta_pdf_viewer` e anexa
  sua biblioteca `viewer`; wrapper, JavaScript do viewer, wrapper completo e CSS
  passam a vir de um único owner.
- As cópias de assets removidas eram byte-a-byte idênticas às do PDF Viewer. O
  builder Document Viewer mantém suas próprias opções e checagens Media/File,
  incluindo a regra privada `course_document`; não houve mudança de acesso nem
  de armazenamento.
- Unit completa: 42 testes / 225 assertions PASS. Kernel: 5 erros de setup / 0
  assertions; a instalação pelo módulo Portal exige Search API (`search_api_db`,
  `search_api`) e configuração `system.menu.admin` não inicializadas pelo
  harness. Nenhuma migração ou operação no Runtime foi executada.
- PHP lint, gate de dependências Config Extension (1.560/1.426), pacote próprio
  (41), índice de status (41) e diff check PASS. PHPCS no arquivo completo ainda
  reporta avisos preexistentes fora das linhas alteradas: 31 violações/1 aviso
  no builder e quatro FQN no Kernel; as linhas alteradas não aparecem no report.

## 0.1.0-dev.14 — provider de Media document para o Content Index

- Provider opcional `document` projeta somente nome, UUID, idioma, datas e
  publicação do bundle `media:document`; não copia arquivo, URI, URL nem texto.
- A autorização continua no Media/File original. A integração do Index não é
  dependência obrigatória e não altera o viewer.
- Teste Unit do provider passou; Kernel/Runtime pendentes.

## 0.1.0-dev.12 — manter o DOCX renderizado visível — 2026-10-22

- `mountDocxViewer()` mantém o iframe sandboxed quando o filho informa sucesso;
  ele continua sendo removido em erro, timeout ou falta de bibliotecas. Antes,
  o iframe era removido inclusive no caminho `done`, descartando o documento
  depois de uma renderização bem-sucedida.
- A captura preliminar com um DOCX OOXML completo confirmou status `done`, mas
  revelou que nenhum conteúdo permanecia no quadro; isso identificou o iframe
  removido no sucesso. Após a correção, Firefox ESR no Runtime de teste confirmou
  `data-aculta-docx-status="done"`, iframe mantido e documento OOXML completo
  visível. A captura revisada teve 1354 CSS px e `scrollWidth` igual à largura
  do viewport. A matriz menor e formatos Office adicionais seguem pendentes.

## 0.1.0-dev.11 — gerar URL Core para arquivos privados Office — 2026-10-21

- Builders DOCX e planilha usam `/system/files?file=...` para `private://`,
  pelo mesmo endpoint Core já adotado no viewer PDF neste Runtime. Arquivos
  `public://` continuam usando `FileUrlGeneratorInterface`; a autorização do
  download continua sob Core/File.
- `UrlGeneratorInterface` foi injetado nos builders por DI para gerar a rota
  `system.files`; nenhuma lógica de acesso foi movida ao tema ou ao Fórum.
- Runtime de teste confirmou HTTP 200 tanto em `/system/files?file=...` quanto
  em `/system/files/{filepath}` para um DOCX privado criado com proprietário
  `aculta`, o usuário do PHP-FPM. O `FileException: File must be readable` da
  primeira tentativa veio de uma fixture criada pelo Drush com outro
  proprietário. Firefox mostrou o cartão, controles e fallback, mas não
  confirmou a renderização do conteúdo; a prévia DOCX continua pendente.

## 0.1.0-dev.10 — remove o guarda CSP duplicado — 2026-10-21

- Removidos do módulo o `CspReportRequestGuardSubscriber`, sua entrada de serviço e seu teste unitário. O guarda continua registrado apenas no `aculta_pdf_viewer` até esse módulo ser desinstalado, o que evita dupla execução enquanto os dois coexistem.
- Verificado: o container reconstrói após `cr`; o serviço do módulo antigo continua registrado e o do viewer não existe mais.
- Unit do módulo: 42/42 OK.

## 0.1.0-dev.9 — correções da revisão de código da homologação RC1 — 2026-10-21

- **Segurança:** títulos das colunas do modo Tabela agora são texto puro (`titleFormatter: 'plaintext'`). Antes, o Tabulator escrevia o cabeçalho do arquivo com `innerHTML`, e um cabeçalho com HTML executava na origem do site. Verificado no navegador X11: a versão anterior executava (`xssExecutou: true`, `<img>` criado); a corrigida mostra o texto e não executa.
- **Limites no pai:** o leitor de planilhas reaplica os limites (20 abas, 2000 linhas, 100 colunas, 500 caracteres) às matrizes recebidas do iframe, em vez de confiar no iframe.
- **Descompactação real:** DOCX, XLSX e ODS medem os bytes realmente descompactados, com teto, em vez de confiar no tamanho declarado no diretório do ZIP.
- **Nomes de entrada:** uma única regra (`DocxPackageValidator::isUnsafeEntryName`) para os três validadores; recusa também `..` no fim do nome.
- **Formato decimal:** `0.0#` preserva as casas opcionais.
- **DOCX:** o iframe é removido em todos os caminhos de saída (timeout e falha inclusive).
- **Comentário do XLS** corrigido: só a assinatura OLE2 é verificada; macros não são inspecionadas.
- Testes: Unit 55/55 OK. Regressão no navegador: ODS e XLSX com os mesmos valores de antes.
- **Achados da revisão deixados para o dono ou para a fase de remoção**, com razão:
  - `CspReportRequestGuardSubscriber`: cópia do guarda do `aculta_pdf_viewer`. Mantida enquanto os dois módulos coexistem; deve sair do módulo novo na desinstalação do antigo, junto com o guarda do módulo antigo, para não criar duplicidade nem perder a proteção.
  - Duplicação do fluxo de formatters DOCX e planilha e dos limites entre PHP e JS: refatoração planejada, sem mudança de comportamento agora.
  - `.htaccess` sem a regra `.mjs` na árvore de trabalho: alteração não commitada de outra frente; decisão do dono.

## 0.1.0-dev.8 — isolamento do leitor de planilhas e limites de descompactação (0.1.0-dev.10 do plano) — 2026-10-21

- O SheetJS deixa a página principal: roda em `js/sheet-frame.html` + `sheet-frame.js`, em iframe `sandbox="allow-scripts"` sem `allow-same-origin`, com CSP em `<meta>` (`connect-src 'none'`, sem imagens). O pai (`js/sheet-reader.mjs`) só recebe matrizes de texto.
- O pai não carrega mais o SheetJS: a biblioteca `spreadsheet` tem só o CSS do Tabulator e o módulo JS. O caminho do parser vai por atributos (`data-aculta-sheet-frame-url`, `data-aculta-sheet-libs`).
- Limites no iframe e no pai: 2000 linhas e 100 colunas por aba, 20 abas, 500 caracteres por célula.
- ODS passa a ter limites de descompactação (2000 entradas, 200 MiB descompactados, razão 100) e teste de ZIP bomb. XLSX já tinha, pelo validador OOXML.
- Testes: Unit 54/54 OK. Navegador (Chromium X11, fixtures ODS, XLS, CSV e XLSX): valores idênticos aos da etapa anterior; `window.XLSX` indefinido na página principal; sem iframe residual.
- Sonda de isolamento durante a leitura: `sandbox="allow-scripts"`; a página não acessa o documento do iframe (origem opaca); célula com `<img ...>` chega como texto, sem elemento `img` na página.
- Runtime (sessão X11): XLSX de três abas no modo full de `node.document`; `status: ok`, abas listadas, modo Tabela com cabeçalhos corretos. Display restaurado; fixtures removidos.
- Pendente: o isolamento do PDF continua o do PDF.js (dev.8/PV7). A decisão sobre expor DOCX e planilhas em displays públicos segue com o responsável.

## 0.1.0-dev.7 — interface unificada dos leitores DOCX e planilhas (0.1.0-dev.8 do plano) — 2026-10-21

- `Document\DocumentViewerChrome`: cabeçalho (tipo e título escapados), barra de controles e área de status, com atributos neutros `data-aculta-document-part` e `data-aculta-document-control`. Sem classe de design no módulo.
- `js/document-chrome.mjs`: zoom em passos fixos (75% a 200%, com `zoom` no quadro de conteúdo), tela cheia do contêiner e status em região `aria-live`. Rótulos por `Drupal.t()`.
- Leitores DOCX e planilhas usam o cabeçalho e a barra. O modo Planilha/Tabela e o seletor de aba ficam dentro da barra. Mensagens de carregamento, limite e falha são traduzidas.
- O PDF não foi alterado: continua com a própria barra (`pdf-viewer.css`), conforme o plano.
- Tema 0.6.0-dev.145: `css/components/document-viewer.css` com tokens semânticos; gate de fundação atualizado.
- Gates: fronteira módulo-tema sem falha nesse módulo (as falhas restantes são de outras frentes); mapa módulo-tema documenta o módulo e o submódulo Group; atributos documentados em `TOKENS-E-ATRIBUTOS.md`.
- Testes: Unit 53/53 OK (incluindo escape do título). Runtime (Chromium X11, DOCX e XLSX públicos com título contendo HTML): título como texto; zoom 100% → 115%; tela cheia por clique real; status oculto ao concluir; modos Planilha/Tabela e abas presentes.
- Pendente: revisão visual em Firefox e em 390/768/1024 px; o gate de design do Mailer falha em três checks que já existiam.

## 0.1.0-dev.6 — planilhas XLS, ODS e CSV, pt-BR e modo Tabela (0.1.0-dev.7 do plano) — 2026-10-21

- `Spreadsheet\SpreadsheetFileValidator`: valida por formato. XLSX pelo validador OOXML (`forXlsx`), ODS (`mimetype` declarado, `content.xml`, sem `Basic/` ou `Scripts/`), XLS (assinatura OLE2) e CSV (texto, sem bytes NUL). Limite de 25 MiB; extensão fora da lista é recusada.
- Formatter e builder aceitam XLSX, ODS, XLS e CSV, com MIME por extensão; `data-aculta-sheet-format` informa o formato ao leitor.
- Formatação pt-BR: números com vírgula decimal e casas do formato da célula; percentuais; datas `dd/mm/aaaa` a partir dos componentes da data (sem depender do fuso); hora com segundos só quando pedidos; booleanos como VERDADEIRO/FALSO; erros como texto.
- CSV é lido como texto (`raw`): o SheetJS não converte "25.6%" em número.
- Modo Tabela: uma aba por vez, com seletor de aba, primeira linha como cabeçalho, filtros por coluna e paginação local. Somente leitura. Modo Planilha segue igual.
- Testes: Unit 52/52 OK (inclui validação por formato). Browser (Chromium na sessão X11, fixtures ODS, XLS, CSV e XLSX gerados com SheetJS 0.20.3): status ok em todos; XLS e ODS com pt-BR, percentual, data, booleanos e cabeçalhos; CSV como texto; modo Tabela com títulos e filtros.
- Runtime (`https://aculta.toca.net.br`, sessão X11): XLSX de três abas e ODS renderizados pelo formatter de planilha no modo full de `node.document`; status ok, abas listadas e modo Tabela com seletor e filtros. Display restaurado ao formatter PDF e fixtures removidos.
- Pendências: CSV não é aceito pelo campo de documentos do Runtime (extensões de `field_media_document`), então não foi testado nele; isolamento do SheetJS (0.1.0-dev.10); formato de data e número vindo do arquivo para locales além de pt-BR; recorte de módulos do Tabulator; botões da barra ainda sem estilo ACULTA420 (0.1.0-dev.8).

## 0.1.0-dev.5 — leitor de planilhas com SheetJS e Tabulator (0.1.0-dev.6 do plano) — 2026-10-21

- Bibliotecas locais e vendorizadas: SheetJS CE 0.20.3 (`web/libraries/sheetjs`) e Tabulator 6.5.3 (`web/libraries/tabulator`), com manifestos.
- `Docx\DocxPackageValidator`: parâmetros de partes obrigatórias e tipo principal; `forXlsx()` valida `xl/workbook.xml` com os mesmos limites e recusas (macros, HTML).
- `Spreadsheet\SpreadsheetViewerBuilder` e `Plugin\Field\FieldFormatter\SpreadsheetMediaFormatter` (`aculta_document_spreadsheet_media`): XLSX autorizado, mesmas checagens do formatter DOCX, fallback para o link do Core.
- Biblioteca `aculta_document_viewer/spreadsheet` com `js/spreadsheet-viewer.mjs`: SheetJS lê o workbook, uma aba do Tabulator Spreadsheet por planilha, coordenada A1 preservada, primeira linha não é cabeçalho, células não editáveis, formatter `plaintext`, limites de 2000 linhas e 100 colunas por aba.
- Testes: Unit 44/44 OK (inclui validação XLSX). Browser (Chromium na sessão X11, arquivos reais do módulo e bibliotecas vendorizadas): três abas exibidas; coordenadas e primeira linha preservadas; fórmula mostrada pelo valor em cache; HTML de célula exibido como texto e nenhum script executado.
- Limitações conhecidas, previstas para 0.1.0-dev.7 e 0.1.0-dev.10: o SheetJS roda no contexto da página (não isolado); datas e números usam o formato `m/d/yy` do arquivo, sem a localização pt-BR; XLS, ODS e CSV ainda não; `TabulatorFull` (bundle completo, sem recorte de módulos).
- Integração no Runtime pendente: a criação de nó e arquivo no Runtime falha desde o início de 0.1.0-dev.5 por um hook do Fórum da outra frente (`ForumPublicationTimeHooks` sem argumentos de construtor, ainda sem commit). Nada foi gravado no Runtime nesta etapa.

## 0.1.0-dev.4 — formatter DOCX integrado ao Drupal e testado no Runtime (0.1.0-dev.5 do plano) — 2026-10-21

- `Plugin\Field\FieldFormatter\DocxMediaFormatter` (`aculta_document_docx_media`): campos `file` de qualquer entidade (Media ou nó). Verifica a entidade hospedeira, o campo e o arquivo (download) antes de montar o leitor. Sem acesso, o elemento sai com `#access` FALSE.
- `Docx\DocxViewerBuilder`: valida o pacote com `DocxPackageValidator`, aceita só `public://` e `private://`, e gera a URL pelo Core (`file_url_generator`). Estrutura inválida ou URI remoto: sem leitor, só o link do Core.
- Biblioteca `aculta_document_viewer/docx` com `js/docx-viewer-behavior.mjs`, carregada somente quando há DOCX autorizado. Monta o iframe isolado e esconde o leitor em caso de falha; o link de download continua.
- Serviços `aculta_document_viewer.docx_package_validator` e `aculta_document_viewer.docx_builder`.
- Testes Unit: builder recusa URI remoto, pacote inválido e caminho não resolvido (41 testes no total do módulo, OK).
- Runtime (sessão X11, Chromium, `https://aculta.toca.net.br`): DOCX válido renderizado no iframe (`sandbox="allow-scripts"`), pai sem acesso ao DOM do iframe, link de download presente. DOCX corrompido: sem leitor, link mantido (200). Página de Media privada de nó não publicado: 403 para anônimo; arquivo privado: 404, igual ao de um caminho inexistente.
- Fixtures de teste criados e removidos; displays `node.document` restaurados ao formatter PDF (verificado no config).
- Ainda não ativado nos displays de produção: os displays `media.document` e `node.document` continuam com o formatter PDF.

## 0.1.0-dev.3 — renderizador isolado do DOCX (etapa de isolamento do plano) — 2026-10-21

- `docx/docx-frame.html` e `docx/docx-frame.js`: página estática do próprio módulo, renderizada em `<iframe sandbox="allow-scripts">` sem `allow-same-origin`. CSP em `<meta>` com `script-src 'self'`, `connect-src 'none'`, `img-src blob: data:`. Bootstrap externo (script inline é bloqueado pelo CSP).
- Protocolo por `postMessage` com `ev.source` validado: `ready` → `init` (URLs de biblioteca absolutas) → `libs` → `render` (ArrayBuffer) → `done` ou `error` (com motivo e detalhe).
- Bibliotecas carregadas em sequência (`async = false`). Carregamento paralelo fazia o UMD do docx-preview capturar `JSZip` indefinido e falhar no caminho de `altChunk`; corrigido e verificado.
- `js/docx-viewer.mjs`: `mountDocxViewer()` busca os bytes autorizados pela URL recebida, envia ao iframe e devolve `{status, reason?}`. Prazo configurável. O pai não lê o conteúdo renderizado.
- Testes de navegador (Chromium na sessão X11, arquivos reais do módulo via servidor de teste local): DOCX válido e `altChunk` com `done`; DOCX corrompido com `error` e motivo; URL inexistente com `error`. Em todos, o pai não lê o DOM do iframe e o atributo `sandbox` é `allow-scripts`.
- Ainda não integrado a formatter nem a Drupal: falta o formatter DOCX, a checagem de acesso com a URL do Media e o teste no Runtime.

## 0.1.0-dev.2 — validação estrutural de DOCX (etapa DOCX do plano) — 2026-10-21

- `Docx\DocxPackageValidator` valida o DOCX antes de qualquer renderização: assinatura ZIP, ZIP legível, limite de bytes, número de entradas, soma descompactada, razão de compressão (ZIP bomb), caminhos inseguros, partes obrigatórias (`[Content_Types].xml`, `word/document.xml`), tipo principal WordprocessingML declarado e ausência de `vbaProject.bin` e partes HTML (altChunk).
- Rejeições têm código estável (`DocxValidationException::getReason()`).
- Testes Unit: 12 casos com pacotes gerados no teste (válido, não-ZIP, corrompido, ausente, vazio, partes ausentes, tipo errado, macro, HTML, entradas, bomba, tamanho).
- Ainda não ligado a nenhum formatter: a renderização DOCX depende do spike de isolamento (iframe sem `allow-same-origin` ou origem separada), exigido pelo plano antes de expor HTML gerado.

## 0.1.0-dev.1 — nova identidade do visualizador (cópia de aculta_pdf_viewer 0.1.0-dev.39) — 2026-10-21

- Criado `aculta_document_viewer` a partir do conteúdo commitado de `aculta_pdf_viewer` (HEAD `d43b9623`), sem alterar o módulo antigo.
- Renomeados machine name, namespace `Drupal\aculta_document_viewer`, serviços, rota administrativa (`/painel-administrativo/aculta/leitor-documentos`), library e submódulo (`aculta_document_viewer_group`).
- Formatters com IDs próprios `aculta_document_pdf_media` e `aculta_document_pdf_file`. Decisão B da estratégia de migração (`docs/portal/ACULTA-DOCUMENT-VIEWER-MIGRACAO.md`): IDs compartilhados não coexistem, e desinstalar o provider antigo sem trocar o componente faz o display cair para `string`.
- Config install do submódulo Group não duplica `media.type.course_document`; essa configuração continua pertencendo ao módulo antigo até a migração de Runtime.
- Módulo antigo `aculta_pdf_viewer` permanece instalado e intacto, como camada de compatibilidade (estado B).
- Alterações não commitadas de outra frente em `aculta_pdf_viewer/src/Pdf/` não foram copiadas nem alteradas.


## 0.1.0-dev.39 — remover formatter PDF contrib sem uso — 2026-10-21

- `drupal/pdf` 1.3 estava instalado apenas no código, desativado no Runtime, sem referência em Config Sync e sem consumidores fora do próprio contrib. O formatter compartilhado do `aculta_pdf_viewer` substitui sua função de leitura usando PDF.js ESM.
- Removido `drupal/pdf` pelo Composer do projeto; nenhum uninstall de Runtime foi necessário porque o módulo já estava desativado. `pdfpreview` permanece ativo e no Composer: o adapter ainda usa `pdfpreview.generator` para miniaturas públicas, em conjunto com `aculta_image`.
- `composer remove` registrou uma operação de remoção do pacote e apagou `web/modules/contrib/pdf`; Composer dry-run apontou somente essa remoção no estado atual. Core extension e displays confirmam ausência de dependência/config do módulo `pdf`.
- A documentação atual foi alinhada. A análise técnica do loader contrib continua apenas como histórico da decisão de substituição.

## 0.1.0-dev.38 — manter controles do PDF.js após inicialização — 2026-10-20

- A revisão visual X11 encontrou que o PDF.js repunha o botão principal de download durante a inicialização, apesar do formatter desativá-lo.
- O wrapper agora reaplica as opções quando a aplicação fica pronta e observa alterações posteriores da toolbar; as escritas são idempotentes para evitar ciclos de mutação.
- Firefox ESR 153.4 no X11 local, contra o Runtime de teste `aculta_runtime`, carregou o PDF público de 14 páginas. Impressão/download desligados: quatro botões primários/secundários ocultos visualmente e com `aria-hidden=true`/`tabindex=-1`; ligados: quatro disponíveis e focáveis. Uma mutação artificial após a inicialização também foi revertida pelo observador. Capturas temporárias ficaram em `/tmp/aculta-pdf-viewer-x11-fixed-{off,on}.png`.
- Nenhum clique de impressão/download foi executado; a validação observou apresentação e foco. Nenhum dado do Runtime foi alterado.

## 0.1.0-dev.37 — respeitar controles de download e impressão no PDF.js completo — 2026-10-20

- Corrige a configuração por formatter: os links dedicados já eram ocultados, mas o PDF.js completo mantinha seus próprios botões de imprimir/baixar.
- Os valores de controle agora seguem no link same-origin; o wrapper esconde os botões principais e secundários do PDF.js quando desativados. A permissão Drupal do arquivo continua obrigatória e não é substituída por esses controles visuais.
- Unit do módulo: 26 testes, 195 assertions PASS; o builder cobre flags ligados/desligados e visibilidade dos links. `node --check`, PHP lint e `git diff --check` PASS.
- O PDF.js instalado não expõe AppOption pública para remover esses botões; a adaptação usa IDs presentes no viewer 6.4.299 instalado. Não altera arquivos Composer.
- Firefox headless validou em fixture local do pacote PDF.js (14 páginas): flags `0` ocultam os quatro botões e os removem do foco/árvore acessível; flags `1` os tornam visíveis e focáveis. Teste executado em `127.0.0.1`, sem Runtime nem deploy.

## 0.1.0-dev.36 — padrões de rolagem e página dupla no PDF.js completo — 2026-10-20

- Acrescenta um wrapper local e same-origin para inicializar o viewer PDF.js com rolagem vertical/horizontal e spread configurados por painel ou formatter. O PDF.js continua sem alterações nos arquivos Composer.
- Desktop pode abrir em página dupla; em telas móveis o spread é desligado por padrão e respeita a opção explícita para mantê-lo. Canvas ACULTA segue paginado.
- Links de impressão e viewer completo passam pelo wrapper. O caminho PDF continua vindo do builder após checagem de acesso; arquivos privados continuam na rota Core. O wrapper valida origem e extensão antes de carregar.
- Unit 26/26 (183 assertions), `php -l` e `node --check` PASS. Firefox no Homelab carregou o PDF público PV7 de 14 páginas: PDF.js reportou scroll mode horizontal e spread no desktop; ao estreitar a janela para 560 px, spread mudou para desligado e não houve overflow. A camada textual continha texto do PDF e o elemento da página tinha rótulo acessível no DOM; WebDriver confirmou o atalho `n` avançando da página 1 à 2. Compatibilidade real com leitor de tela segue pendente.
- RUNTIME STATUS: PARTIAL; Firefox verificou wrapper/modos no Homelab. O builder real no Runtime gerou links de impressão e viewer com o wrapper e os padrões correntes (`vertical`, spread desligado), sem título editorial na query; HTML respondeu HTTP 200 em `main.aculta`. Kernel do Portal precisa ser repetido após estabilizar as alterações concorrentes atuais; gates finais e revisão acessível seguem abertos. Permanece Dev.

## Evidência PV7 — validações retomadas — 2026-10-20

- Unit passou: 26 testes, 183 assertions (contagem confirmada em nova execução em 2026-10-20).
- Kernel no MariaDB dedicado não alcançou assertions: a compilação do container do Portal falhou por referências a `agreement.handler` e `commerce_cart.cart_provider` ausentes no fixture. O bloqueio está registrado no roadmap para resolução antes de RC.
- Status-index e Drupal 11+ gate passaram após atualizar a linha do componente no índice raiz: 24 componentes e 1026 checks. `aculta_image`, fronteira módulo/tema, endereços de ambiente, slugs públicos e `.htaccess` passaram.
- No Homelab, a rota administrativa foi carregada após `drush cr`; a configuração ausente foi inicializada somente com o objeto de Config Sync `aculta_pdf_viewer.settings`. Firefox autenticado confirmou a subaba Site → Leitor de PDF e os controles padrão ligados (10 opções); página dupla móvel e desktop permanecem desligadas. O rebuild registrou aviso preexistente de diretório de asset H5P, mas concluiu.
- Sem mudança de código nesta nota; na ocasião, a versão era `0.1.0-dev.35`. PV7 não estava em RC. O modo de rolagem/página dupla foi concluído para o viewer oficial no dev.36; Kernel/gates finais permanecem abertos.

## Diagnóstico PV7 — repetição das suítes — 2026-10-20

- Unit foi repetido no MariaDB-independent runner: 26 testes e 183 assertions, PASS.
- A suíte Kernel completa foi repetida no MariaDB dedicado: os quatro cenários de render/Media Embed falham antes das assertions porque a compilação do Portal referencia `agreement.handler`, ausente no fixture; o teste de instalação falha porque a configuração Views de LMS depende de `system.menu.admin`, não instalado no fixture.
- Uma tentativa local de instalar toda a configuração `system` revelou dependências de ações de outros módulos ausentes; foi revertida. Não foram adicionados mocks nem alteradas as dependências do Portal nesta frente. A correção deve preservar a validade do grafo real de serviços.
- RC continua bloqueado pelo Kernel, além da investigação acessível e gates compartilhados.

## 0.1.0-dev.35 — subaba Leitor de PDF em Site — 2026-10-20

- Integra o formulário global como subaba “Leitor de PDF” dentro da aba “Site” do painel ACULTA, mantendo o caminho e a permissão administrativa existentes.
- RUNTIME STATUS: DEFERRED; confirmar descoberta da aba no Runtime Alpha junto ao fluxo autorizado de implantação.

## 0.1.0-dev.34 — controles globais no painel ACULTA — 2026-10-20

- Adiciona `/painel-administrativo/aculta/leitor-pdf` como subpágina do painel ACULTA, protegida por `administer site configuration`.
- O painel salva padrões globais tipados/exportáveis; cada formatter herda esses valores por padrão ou pode desativar a herança e configurar aquele campo. A cache do viewer varia pela configuração global.
- Rolagem horizontal e página dupla ficam visíveis com descrição explícita de que ainda não afetam o canvas; leitura em voz alta continua pós-deploy. Runtime e formulário visual DEFERRED.

## 0.1.0-dev.33 — miniaturas de páginas sob demanda — 2026-10-20

- Inclui miniaturas navegáveis no canvas próprio. Só renderiza ao expandir a seção, limita cada miniatura a 65.536 pixels e a lista a 100 páginas; PDFs maiores informam o limite aplicado.
- Usa controles Bootstrap e texto acessível; miniatura sem renderização é removida sem interromper o leitor. RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.32 — corrigir escala inicial configurada — 2026-10-20

- Distingue “ajustar à largura” de escala real 100%/125%; restaurar zoom volta ao padrão selecionado no formatter.
- Zoom em tela estreita respeita o limite seguro de canvas já existente. RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.31 — navegação pelo contorno do PDF — 2026-10-20

- Exibe o outline fornecido pelo PDF.js em lista hierárquica de controles acessíveis, navegando para a página de cada destino. Documento sem outline não exibe a área vazia.
- Limita a construção a 500 itens e 20 níveis; títulos são inseridos como texto, sem interpretar HTML. Miniaturas por página continuam pendentes. RUNTIME STATUS: DEFERRED.

## 0.1.0-dev.30 — rotação do canvas — 2026-10-20

- Acrescenta controle opcional de rotação no formatter; o canvas gira em passos de 90° e recalcula a escala respeitando o limite de pixels e dimensões.
- Não altera o arquivo PDF nem persiste rotação no documento. RUNTIME STATUS: DEFERRED; testes e validação visual pré-RC pendentes.

## 0.1.0-dev.29 — opções do leitor no formatter — 2026-10-20

- Os formatters Media e File passam a oferecer configuração Drupal por campo para controles, zoom inicial, rolagem, página dupla, idioma e apresentação do link, sem string posicional compactada.
- Builder recebe as opções e oculta controles conforme a configuração. Download e impressão continuam disponíveis apenas quando o formatter já confirmou `File::access('download')`; impressão abre o viewer completo local do PDF.js, cujo botão de impressão processa o documento.
- Zoom inicial é aplicado ao canvas próprio; idioma pt-BR é propagado para o viewer completo. A leitura custom ainda não implementa modo horizontal nem página dupla: as opções correspondentes se aplicam ao leitor completo até concluir o adapter.
- Nenhum arquivo de TNC FlipBook estava disponível neste checkout; a análise de capacidades foi feita pela matriz enviada e pelas referências upstream já registradas no roadmap. A comparação detalhada, validação funcional do botão de impressão e inspeção do protótipo seguem pendentes. RUNTIME STATUS: DEFERRED.
- Decisão do responsável: leitura em voz alta pelo navegador fica para pós-deploy; antes de implementação, avaliar suporte no ambiente de produção e testar com leitor de tela.

## 0.1.0-dev.28 — link alternativo ao viewer completo PDF.js — 2026-10-20

- O formatter oferece link secundário para o viewer oficial `web/viewer.html`, com locale `pt-BR`; o canvas custom permanece a experiência padrão.
- A URL do documento vem do builder após validar acesso de Media/campo/File. PDFs privados continuam apontando à rota protegida do Drupal Core; nenhum controller ou cópia de arquivo foi criado.
- O mapa transversal de caminhos registra o viewer na mesma origem para MAIN, Revista, Wiki, Fórum e Cursos.
- Homelab: HTTP GET de `viewer.html` respondeu 200 em MAIN, Revista, Wiki, Fórum e Cursos; `viewer.mjs` e locale `pt-BR/viewer.ftl` também responderam 200. Isso confirma apenas a entrega estática; ainda validar abertura do PDF pelo parâmetro `file`, foco/teclado, leitor de tela, PDFs marcados/não marcados e CSP Report-Only. RUNTIME STATUS: DEFERRED para o fluxo funcional.


