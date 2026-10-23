# ACULTA LMS Resources

Módulo opcional que permite ao educador vincular ao curso publicações existentes
da Revista, verbetes Wiki e galerias Media Gallery. O conteúdo e suas permissões
continuam sob responsabilidade do módulo de origem; o curso apenas guarda
referências e mostra links canônicos no purpose correspondente.

Os links são exibidos a membros autorizados e educadores com permissão contextual.
O builder revalida publicação e Entity Access sob o purpose da origem e agrega
cacheability das entidades, domínios e resultados de acesso. Não duplica imagens,
arquivos, conteúdo editorial ou storage.

No caso de galerias, o campo do curso usa uma seleção dedicada: o educador com
permissão contextual escolhe galerias publicadas no purpose Revista. A galeria
concede visualização aos membros ativos e educadores do curso somente enquanto
estiver vinculada a ele; a política geral de acesso da Gallery não é ampliada.

O provider do Content Index projeta somente `Group:lms_course` e relações
explícitas `course_reading` (`revista`/`wiki_entry`) e `course_gallery`.
Consultas revalidam Entity Access sob o purpose `courses` e exigem também
`takeAccess()` ou a permissão contextual de educador (`administer members` ou
`grade students`). Aulas, progresso, matrículas, respostas, notas e certificados
continuam exclusivamente no LMS.

## Estado

### Limpeza coordenada de recursos — dívida de integração em 2026-10-22

O Portal já oferece parcialmente `aculta_portal.unused_file_cleaner` para
verificação/remoção de File. Quando curso, turma ou referência for removida, o
módulo deve fornecer suas relações e submeter candidatos ao Portal antes da
limpeza. O Portal revalida referências e remove Media/File órfãos. A verificação
deve preservar documentos ligados a outras turmas ou conteúdos e respeitar a
relação Group atual. Não excluir arquivos diretamente; ver
[política compartilhada](../../../../../../docs/portal/CONTENT-CLEANUP-POLICY.md).

Dev 0.1.0-dev.10. O provider tem cobertura Unit e Kernel com Group real:
estudante por `takeAccess()`, educador por `grade students`, terceiro negado.
O Kernel passa pelo `ContentIndexAccessFilter`, combina Group Entity Access com
policy LMS e verifica troca/restauração do purpose `courses` com Domains
persistidos e negotiator real: 1 teste / 33 assertions PASS em MariaDB e em
SQLite, com 47 depreciações de Core/contrib por execução. Unit lê e compila o YAML/tag owner (1/4); o módulo
e sua cadeia completa ainda não foram inicializados no Kernel. A descoberta
do provider pela tag/DI do módulo, instalação e smoke autenticado no Runtime e
revisão visual continuam pendentes. PHP CLI 8.4.26; PHP 8.5 não validado.
Consulte o
[roadmap LMS](../../../../../../docs/modules/ACULTA-LMS-ROADMAP.md).
