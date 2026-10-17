# ACULTA PDF Viewer — Group

Integra opcionalmente Media `document` ao Group para que documentos vinculados
a uma turma recebam controle de acesso do grupo. O submódulo depende do
`aculta_pdf_viewer`, Media e Group; o leitor global continua funcionando sem
Group.

## Funcionamento

`MediaDocument` registra o plugin `aculta_pdf_viewer_group:course_document` com
`entity_access: TRUE` e `enforced: FALSE`. Assim o Group não instala a relação
automaticamente em tipos de grupo novos. Ela existe só onde está configurada
explicitamente: `config/sync/group.relationship_type.lms_course-af8ec2205541224f9d8c4.yml`
para `lms_course`. `MediaDocumentDeriver` publica a relação somente para Media
`course_document`.
Esse bundle é instalado com o submódulo e seu field storage usa `private://`;
o bundle `document` público existente não é alterado. `CourseDocumentHooks`
força Media de turma a permanecer não publicada: membros recebem permissões
Group específicas de leitura (inclusive de conteúdo não publicado), enquanto
um arquivo solto não ganha acesso por `view media` global. Estudante e educador
recebem permissões de leitura no Config Sync; educadores também podem criar e
relacionar documentos. A rota privada do Core continua como única entrega de
bytes e reavalia o acesso em cada requisição.

Os PDFs de turma usam o novo bundle `course_document` e não são publicados nem
mesmo quando a caixa de status é submetida. Isso não migra arquivos nem altera
o bundle institucional `document`. O Kernel verifica o endpoint privado do Core
para membro, não membro, remoção da relação Group e revogação de membership. A
homologação dessas condições no Runtime continua necessária antes do aceite
final da PV5.

## Hooks e classes

O módulo implementa somente o hook OOP `#[Hook('entity_presave')]` em
`CourseDocumentHooks`, sem hooks procedurais, rotas ou controllers. O plugin de
relação usa o atributo Drupal Group `#[GroupRelationType]`; o deriver associa o
plugin ao bundle Media. Group
continua fonte de verdade de memberships e permissões, Media/File de arquivos
e metadados, e `aculta_pdf_viewer` da apresentação do PDF.

Rollback: remover a relação Group↔Media e desabilitar este submódulo; não apagar
Media/File nem alterar os arquivos existentes.
