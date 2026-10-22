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

### Limpeza coordenada de recursos — dívida planejada em 2026-10-22

Quando curso, turma ou referência for removida, o módulo deve fornecer suas
relações ao serviço de limpeza do Portal antes de retirar File/Media. A
verificação deve preservar documentos ligados a outras turmas ou conteúdos e
respeitar a relação Group atual. Não excluir arquivos diretamente; ver
[política compartilhada](../../../../../../docs/portal/CONTENT-CLEANUP-POLICY.md).

Dev 0.1.0-dev.3. O provider tem cobertura Unit própria (3 testes / 14
assertions); Unit de recursos preexistente (8 testes / 39 assertions) passou; Kernel
(2 testes / 46 assertions) já passava na versão anterior e não foi alterado nesta
subfase. PHP CLI 8.4.26; PHP 8.5 não validado. Instalação e smoke autenticado no
Runtime e revisão visual continuam pendentes. Consulte o
[roadmap LMS](../../../../../../docs/modules/ACULTA-LMS-ROADMAP.md).
