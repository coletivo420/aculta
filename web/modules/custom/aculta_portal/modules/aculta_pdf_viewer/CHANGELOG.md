# Changelog — aculta_pdf_viewer

## 0.1.0-dev.4 — miniaturas seguras da primeira página

Integra PDFPreview 1.2.0 com ImageMagick contrib 4.0.2. Gera miniatura somente
para PDFs permanentes em `public://`, com pasta e dimensões controladas, limite
de 20 MB e timeout de conversão de 15 s. Falhas ou ausência de binários/policy
mantêm o leitor PDF.js e o link. Nenhum PDF privado passa pelo gerador, que usa
o esquema padrão do Drupal para derivados. `imagemagick` e `pdfpreview` ficam
declarados no Config Sync; política de imagem do host não foi relaxada.

## 0.1.0-dev.3 — controles do leitor integrado

O viewer adiciona zoom/restauração, busca textual por página, navegação dos resultados e
modo tela cheia, mantendo o PDF.js local e o link autorizado. O Kernel valida
markup dos controles e o fluxo Media Embed; `node --check` valida sintaxe ESM.
Firefox 153.4 ESR confirmou no Runtime o leitor público (14 páginas, navegação,
zoom, busca, tela cheia e zero erros JS) e a inserção da Media existente pelo
CKEditor 5 seguida de salvamento e renderização do artigo. Todas as fixtures
foram removidas. A Media foi criada por Drush; upload/criação pelo papel
`content_editor`, leitura privada,
acessibilidade visual, CSP, preview e Hostinger continuam pendentes.

## 0.1.0-dev.2 — inserção editorial pelo CKEditor 5

O papel `content_editor` recebe `create document media` para criar PDFs
destinados à publicação via biblioteca do CKEditor 5. PV2 está parcialmente
aplicada no Runtime de teste: submódulo, display e permissão ativos; Kernel
confirma a passagem por Core Media Embed até o formatter PDF.js. Não há PDF ou
embed editorial no Runtime para validação interativa, que segue DEFERRED. O
bundle Media `document` usa `public://` e não é adequado a conteúdo confidencial.

## 0.1.0-dev.1 — F3: viewer compartilhado como submódulo

O serviço `aculta_pdf_viewer.builder`, formatter `aculta_pdf_media` e biblioteca
PDF.js passam a pertencer ao submódulo opcional `aculta_pdf_viewer`. A configuração
exportada habilita o módulo para o display padrão Media `document`. Testes Unit do builder/formatter e Kernel de descoberta do submódulo passaram
no ambiente isolado. O viewer ESM também teve smoke test isolado na F2. A etapa
PV2 (CKEditor da Revista), Runtime, PDFPreview/ImageMagick, leitura privada e
produção LiteSpeed seguem DEFERRED; veja [roadmap](docs/ROADMAP.md).
