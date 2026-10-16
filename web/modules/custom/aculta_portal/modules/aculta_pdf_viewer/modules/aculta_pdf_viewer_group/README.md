# ACULTA PDF Viewer — Group

Integra opcionalmente Media `document` ao Group para que documentos vinculados
a uma turma recebam controle de acesso do grupo. O submódulo depende do
`aculta_pdf_viewer`, Media e Group; o leitor global continua funcionando sem
Group.

## Funcionamento

`MediaDocument` registra o plugin `aculta_pdf_viewer_group:document` com
`entity_access: TRUE`. `MediaDocumentDeriver` publica a relação somente para o
bundle Media `document`. A configuração opcional instala a relação no tipo
Group `lms_course`; ao vincular Media a um curso, Group passa a avaliar a
permissão `view aculta_pdf_viewer_group:document entity` no papel da turma.
Estudante e educador recebem essa permissão no Config Sync; quem não tem
membership não deve receber acesso mesmo que possua `view media` global. A rota de arquivo privado do Core
continua sendo a única entrega de bytes e reavalia o acesso em cada requisição.

Os PDFs de turma precisam usar `private://`. Isso não migra arquivos e não
transforma o bundle `document` público atual em privado. O enable/config import
não foi executado no Runtime nesta fase; a verificação ponta a ponta ainda é um
gate de PV5. Não colocar documentos privados em `public://`.

## Hooks e classes

O módulo não implementa hooks PHP, rotas, controllers, storage próprio ou
subscribers. O plugin de relação usa o atributo Drupal Group
`#[GroupRelationType]`; o deriver associa o plugin ao bundle Media. Group
continua fonte de verdade de memberships e permissões, Media/File de arquivos
e metadados, e `aculta_pdf_viewer` da apresentação do PDF.

Rollback: remover a relação Group↔Media e desabilitar este submódulo; não apagar
Media/File nem alterar os arquivos existentes.
