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
explícitas `course_reading` (`revista`/`wiki_entry`), `course_gallery` e `course_document`, esta última quando o plugin PDF Group opcional está registrado.
Consultas revalidam Entity Access sob o purpose `courses` e exigem também
`takeAccess()` ou a permissão contextual de educador (`administer members` ou
`grade students`). Aulas, progresso, matrículas, respostas, notas e certificados
continuam exclusivamente no LMS.

O Media oEmbed compartilhado `remote_video` pertence ao `aculta_portal` e pode
ser consultado pelo Index como entidade autorizada. Por decisão do responsável,
relações de vídeo ficam adiadas até fechar formatos editoriais e contratos de
acesso dos consumidores. O provider LMS ainda não declara relação com vídeo.
Na configuração revisada, `Group:lms_course` não tem
campo direto de Media, `field_description` não seleciona formato de texto e o
campo `lms_activity.no_answer.field_content` aceita apenas `plain_text`; a fonte
pedagógica que armazenará embeds precisa ser identificada antes de criar curso→
vídeo ou atividade→vídeo. Não copiar URL oEmbed, corpo, progresso ou dados de
estudante para o catálogo.

## Estado

### Content Index: boot/DI do owner — validação dev.161

`ContentIndexOwnerIntegrationKernelTest`, no módulo `aculta_index`, carregou a
closure declarada de 103 módulos Portal/LMS e descobriu `lms_course` e
`course_document` pelos services.yml dos owners reais. O gate passou em
1 teste / 8 assertions (83 depreciações Core/contrib). Isso confirma o boot do
container e as tags; não instala configuração/schema dos módulos nem substitui
os testes focados de acesso com Group e Domain. Runtime continua pendente.

No dev.162, o mesmo harness criou GroupType, Group e Media reais, resolveu a
definição `aculta_pdf_viewer_group:course_document` registrada pelo owner e
persistiu a relação curso→documento (1 teste / 33 assertions; 239 depreciações
Core/contrib). O harness pertence ao `aculta_index` no Portal; o LMS não
hospeda nem possui o Index. A policy LMS segue coberta nos testes focais; o
Runtime permanece pendente.

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

Dev 0.1.0-dev.14. A relação outgoing `course_document` passou a ter cobertura
Kernel completa no harness LMS: rebuild, consulta pelo estudante membro,
negação do visitante externo e uso da declaração/tag YAML do provider Media
owner (4 testes / 210 assertions em MariaDB; 53 depreciações Core/contrib).
O plugin Group é fixture de teste; a ativação dos módulos owner completos,
instalação no Runtime e smoke autenticado continuam pendentes.

Dev 0.1.0-dev.15 corrige somente o pacote da fixture Kernel para `ACULTA Portal`,
em conformidade com o gate de componentes próprios.

Dev 0.1.0-dev.16 repete o Kernel LMS/Index em MariaDB: 4 testes / 208
assertions, incluindo acesso de estudante e educador, terceiro negado e relações
de leitura, galeria e documento. O teste usa Group real e plugin fixture para a
relação PDF; as 53 depreciações são de Core/contrib. Boot owner e Runtime seguem
pendentes.
