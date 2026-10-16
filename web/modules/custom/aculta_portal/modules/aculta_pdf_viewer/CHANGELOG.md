# Changelog — aculta_pdf_viewer

## 0.1.0-dev.9 — integração opcional Group↔Media

Adiciona o submódulo opcional `aculta_pdf_viewer_group`, que fornece a relação
Group para Media `document`, e configuração opcional para `lms_course`. Papéis
estudante e educador recebem a permissão explícita de visualizar a Media
relacionada. O módulo base continua independente de Group. Runtime e teste de
revogação por membership continuam DEFERRED; não usar isso como aprovação para
arquivos confidenciais até o teste ponta a ponta PV5.

## 0.1.0-dev.8 — PDFPreview opcional e contrato atualizado

PDFPreview deixa de ser dependência obrigatória de ativação; o serviço de
miniaturas continua fail-soft quando o contrib não está habilitado. README e
metadados passam a refletir leitura `private://` conforme Media/File access,
incluindo que permissões amplas podem permitir acesso anônimo. Kernel verifica
registro do formatter e serviço de miniaturas sem habilitar PDFPreview.

## 0.1.0-dev.7 — contrato de acesso do arquivo privado

Kernel confirma que o endpoint Core revalida a permissão efetiva da Media:
nega sem `view media` e para Media despublicada sem permissão de proprietário,
mas permite Media publicada ao papel anônimo quando ele tem `view media`.
Config Sync concede `view media` a anônimo e autenticado, então `private://`
protege a entrega direta do servidor web, mas não define audiência restrita.
O Group instalado não tem relação Media habilitada; documentos confidenciais
por turma aguardam integração de access Group↔Media.

## 0.1.0-dev.6 — validar entrega privada pelo Core

O teste Kernel cria um PDF `private://`, confirma a URL local da rota Core,
resposta 200 para o proprietário autorizado e 403 para usuário autenticado sem
permissão e acesso anônimo. Outro usuário com `view media` recebe 200 enquanto a
Media está publicada e 403 após ela ser despublicada. Confirma cache privado,
max-age zero no render e ausência de miniatura privada. Group/turmas, revogação
após mudança de membership e browser/PDF.js continuam pendentes.

## 0.1.0-dev.5 — leitura privada pela rota Core

O viewer aceita PDFs permanentes `private://` quando Media, campo e File
autorizam `view` e `download`. O File URL Generator produz a rota privada do
Drupal Core, que revalida as referências e o acesso no pedido dos bytes; o
render privado usa max-age zero. Miniaturas continuam limitadas a arquivos
públicos. Testes de integração por turma, revogação e browser seguem pendentes.

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
