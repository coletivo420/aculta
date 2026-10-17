# Changelog — aculta_pdf_viewer

## Submódulo aculta_pdf_viewer_group 0.1.0-dev.3 — regressão de relação removida — 2026-10-17

Classificação: PATCH (cobertura de segurança, sem alteração funcional).

- O Kernel agora confirma que a URL privada conhecida retorna 403 quando a relação Media↔Group é removida; religa a Media para testar separadamente não membro e revogação de membership.
- PHPUnit Kernel: 1 teste, 39 asserções, PASS; execução isolada no MariaDB `aculta_simpletest`.
- RUNTIME STATUS: DEFERRED; o import de configuração e a homologação com memberships reais continuam pendentes.

## Submódulo aculta_pdf_viewer_group 0.1.0-dev.2 — relação de turma restrita a lms_course — 2026-10-16

Classificação: PATCH (submódulo; o módulo pai não mudou de versão).

- `MediaDocument` passa a `enforced: FALSE`. Antes, `enforced: TRUE` instalava a relação de documento em todo tipo de grupo criado. Agora a relação existe só onde está configurada: `lms_course`, pela configuração `group.relationship_type.lms_course-af8ec2205541224f9d8c4` em `config/sync/`.
- Evidência: Kernel `aculta_pdf_viewer_group` 1/1 (37 asserções) OK, com 23 deprecações de contrib, no banco `aculta_simpletest`.
- RUNTIME STATUS: DEFERRED. No Runtime de teste, o tipo de mídia `course_document` e o submódulo ainda não existem; a configuração está só no sync. A relação em `lms_course` deve ser verificada depois da importação de configuração, que é operação separada.

## 0.1.0-dev.12 — mapeamento único de documentos, miniatura sem checagem morta, versão do submódulo de turma — 2026-10-16

Classificação: PATCH.

- `DocumentFieldMap::fieldForBundle()` passa a ser a única fonte do mapeamento bundle → campo, usada por `PdfViewerBuilder` e `AcultaPdfMediaFormatter`. Um novo bundle de PDF não pode ser aceito em um ponto e recusado no outro.
- `PdfPreviewThumbnailBuilder`: removida a checagem da URL gerada. A marcação usa a URI do arquivo gerenciado (`buildManagedImage`), então a checagem não protegia o `src` renderizado. Sai também a dependência `file_url_generator` desse serviço.
- `aculta_pdf_viewer_group` 0.1.0-dev.1: versão declarada no `info.yml` (não tinha). O README registra que `enforced: TRUE` instala a relação em todos os tipos de grupo; restringir a `lms_course` é decisão do responsável antes do Runtime.
- Evidência: PHPUnit Unit `aculta_pdf_viewer` 7/7 (82 asserções) OK. Kernel `aculta_pdf_viewer` 3/3 (61 asserções) OK, com 4 deprecações; Kernel `aculta_pdf_viewer_group` 1/1 (37 asserções) OK, com 23 deprecações de contrib. Os testes rodaram no banco MariaDB dedicado `aculta_simpletest`, conforme `docs/operations/BANCO-DE-TESTE.md`. Nenhum teste usou SQLite.
- RUNTIME STATUS: cache rebuild feito no Runtime de teste com a URI de teste. Testes de Kernel executados fora do Runtime.

## 0.1.0-dev.11 — correções de testes e formulário dos documentos de turma — 2026-10-16

- Kernel tests `AcultaPdfViewerInstallTest`, `AcultaPdfMediaEmbedTest` e `CourseDocumentAccessTest` passam a instalar com dependências (`install(..., TRUE)`). Com `FALSE`, `aculta_image` não era ativado e o container falhava em `aculta_pdf_viewer.thumbnail_builder` (4 erros).
- Formulário do bundle `course_document`: `status` deixa de constar em `hidden` e permanece como checkbox em `content`, conforme o README. A regra de `entity_presave` continua mantendo a mídia de turma não publicada.
- Testes unitários do formatter e do builder passam bundle e campo de origem (`course_document` / `field_media_course_document`).
- Evidência: PHPUnit Unit (pdf_viewer e aculta_image) 21/21; Kernel (pdf_viewer e group) 4/4 com SQLite em memória; gates `validate-aculta-image`, `validate-aculta-portal-drupal11`, `validate-module-theme-boundary`, `validate-public-slugs` e `validate-environment-addresses` em PASS.
- RUNTIME STATUS: DEFERRED. A config da turma (`course_document`, `lms_course`, papéis) ainda não foi importada no Runtime `aculta_runtime`, que tem 273 itens de drift pré-existentes; importar exige revisão separada.

## 0.1.0-dev.10 — documentos de turma privados e miniaturas alinhadas

- Bundle `course_document` em `private://`, separado do bundle público institucional `document`; relação de turma Group e guarda de ciclo de vida mantêm as mídias de curso não publicadas.
- Config Sync define permissões de leitura/criação por papel da turma e instala o bundle junto à integração Group.
- JPEGs gerados pelo PDFPreview são validados e registrados como arquivos temporários gerenciados pelo `aculta_image`; a limpeza de imagens existente remove os derivados sem uso após a idade configurada.
- A marcação da miniatura usa `aculta_image.render`, incluindo metadados de cache do arquivo. A ausência ou falha do preview continua sem bloquear o leitor PDF.js.

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
