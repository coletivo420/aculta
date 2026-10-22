# ACULTA PDF Viewer — Group

## Limpeza futura de documentos associados a turmas

No planejamento IDX 0.1-R, este submódulo deve fornecer ao Portal as
referências Media↔Group antes da limpeza de um documento. A remoção respeitará
outras turmas, conteúdos e acesso atual; o submódulo não excluirá File/Media
diretamente. Ver [política compartilhada do Portal](../../../../../../../../docs/portal/CONTENT-CLEANUP-POLICY.md).

## Content Index

O serviço opcional `course_document_content_index_provider` registra o bundle
`course_document` no ACULTA Content Index com nome, UUID, idioma, datas e estado
de publicação. Entity Access continua aplicado pelo Group no consumidor; o
provider declara o purpose canônico `courses` para a revalidação transversal.
Nenhum ID de arquivo, URI, URL ou conteúdo do documento é copiado. O serviço
tagged não torna `aculta_index` uma dependência obrigatória do módulo.

Integra opcionalmente Media `document` ao Group para que documentos vinculados
a uma turma recebam controle de acesso do grupo. O submódulo depende do
`aculta_pdf_viewer`, Media e Group; o leitor global continua funcionando sem
Group.

## Funcionamento

`MediaDocument` registra o plugin `aculta_pdf_viewer_group:course_document` com
`entity_access: TRUE` e `enforced: FALSE`. Assim o Group não instala a relação
automaticamente em tipos de grupo novos. Ela existe só onde está configurada
explicitamente: `config/sync/group.relationship_type.lms_course-af8ec2205541224f9d8c4.yml`
para `lms_course`. Esse arquivo fica no Config Sync, não em `config/optional`:
o Group precisa descobrir o plugin depois que o submódulo está habilitado; a
instalação automática da config opcional ocorre cedo demais e falha ao resolver
o plugin. `MediaDocumentDeriver` publica a relação somente para Media
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
mesmo quando a caixa de status é submetida. Habilite o submódulo antes de
importar a relação Group do Config Sync; importar o pacote completo de
configuração continua sendo uma operação separada e revisada. Isso não migra arquivos nem altera
o bundle institucional `document`. O Kernel verifica o endpoint privado do Core
para membro, não membro, remoção da relação Group e revogação de membership. Essa
matriz também foi homologada no Runtime de teste em 2026-10-17: Firefox
renderizou o documento para membro (HTTP 200); não membro e anônimo receberam
HTTP 403. Remover a relação ou a membership negou a URL conhecida (HTTP 403);
restaurar cada vínculo devolveu HTTP 200. A Media e o arquivo de teste foram
removidos. Não importar toda a configuração como parte desta validação.

## Hooks e classes

O módulo implementa somente o hook OOP `#[Hook('entity_presave')]` em
`CourseDocumentHooks`, sem hooks procedurais, rotas ou controllers. O plugin de
relação usa o atributo Drupal Group `#[GroupRelationType]`; o deriver associa o
plugin ao bundle Media. Group
continua fonte de verdade de memberships e permissões, Media/File de arquivos
e metadados, e `aculta_pdf_viewer` da apresentação do PDF.

Rollback: remover a relação Group↔Media e desabilitar este submódulo; não apagar
Media/File nem alterar os arquivos existentes.
