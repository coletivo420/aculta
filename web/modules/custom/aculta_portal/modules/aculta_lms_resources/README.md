# ACULTA LMS Resources

Módulo opcional que permite ao educador vincular ao curso publicações existentes
da Revista, verbetes Wiki, galerias Media Gallery e vídeos remotos. O conteúdo e
suas permissões continuam sob responsabilidade do módulo de origem; o curso
guarda as referências e exibe destinos próprios para cada tipo, revalidando o
acesso antes de apresentar cada link.

Os links são exibidos a membros autorizados e educadores com permissão contextual.
O builder revalida publicação e Entity Access sob o purpose da origem e agrega
cacheability das entidades, domínios e resultados de acesso. Não duplica imagens,
arquivos, conteúdo editorial ou storage.

No caso de galerias, o campo do curso usa uma seleção dedicada: o educador com
permissão contextual escolhe galerias publicadas no purpose Revista. A galeria
concede visualização aos membros ativos e educadores do curso somente enquanto
estiver vinculada a ele; a política geral de acesso da Gallery não é ampliada.

O formulário contextual do Course Builder reutiliza também o widget H5P
registrado pelo módulo owner em `field_h5p_activities`, quando disponível. A
integração não cria um player ou storage próprio e mantém a autorização e o
ciclo de edição do H5P/Group.

O provider do Content Index projeta somente `Group:lms_course` e relações
explícitas `course_reading` (`revista`/`wiki_entry`), `course_gallery`,
`course_document` (quando o plugin
`aculta_document_viewer_group:course_document` está registrado) e `course_video`
(`media:remote_video`). Consultas revalidam Entity Access sob o purpose
`courses` e exigem também `takeAccess()` ou a permissão contextual de educador
(`administer members` ou `grade students`). Aulas, progresso, matrículas,
respostas, notas e certificados continuam exclusivamente no LMS.

O Media oEmbed compartilhado `remote_video` pertence ao `aculta_portal` e pode
ser consultado pelo Index como entidade autorizada. O curso referencia vídeos
pelo campo explícito `field_lms_videos`, limitado ao bundle `remote_video`;
novas instalações recebem a configuração de `config/install` e sites existentes
recebem o campo pelo update `8001`. O provider LMS emite `course_video` e a
versão de projeção sobe para 4 com o plugin PDF Group ou 3 sem ele. O Media e
sua Entity Access continuam no Portal; não copiar URL oEmbed, corpo, miniatura,
progresso ou dados de estudante para o catálogo. O contrato completo das
relações editoriais de vídeo está em
[ACULTA-CONTENT-INDEX-VIDEO-RELATIONS](../../../../../../docs/portal/ACULTA-CONTENT-INDEX-VIDEO-RELATIONS.md).

No workspace Ganjalab Educador, leituras e galerias usam os links dos seus
purposes owners. Para `remote_video`, o builder revalida Media no purpose
Cursos e aponta ao endereço fonte oEmbed: com `media.settings:standalone_url`
desligado, a rota Core `entity.media.canonical` abre `/media/{id}/edit`, não um
player público. O filtro aceita apenas URI absoluta HTTP(S) sem credenciais.
Documentos privados usam sua rota protegida pelo viewer. H5P usa seu widget
owner no formulário contextual quando o módulo está ativo. Vídeos e documentos
são revalidados no Domain `courses`; documentos privados exigem a relação Group
de curso e o Entity Access da mídia. O bloco só entra na página do curso que o
educador já pode editar. O botão **Gerenciar materiais do curso** abre um
formulário contextual que reusa os widgets do Form Display Group para os campos
de referência existentes. O LMS revalida a autorização ao exibir e salvar; os
owners mantêm conteúdo, relações e arquivos. Não há armazenamento paralelo nem
cópia de conteúdo.

## Estado

### Content Index: boot/DI do owner — validação dev.161

`ContentIndexOwnerIntegrationKernelTest`, no módulo `aculta_index`, carregou a
closure declarada de 103 módulos Portal/LMS e descobriu `lms_course` e
`course_document` pelos services.yml dos owners reais. O gate passou em
1 teste / 8 assertions (83 depreciações Core/contrib). Isso confirma o boot do
container e as tags; não instala configuração/schema dos módulos nem substitui
os testes focados de acesso com Group e Domain. Runtime continua pendente.

Regressão de boot em 2026-11-04: a closure de 103 módulos owners compila no
Kernel `aculta_index`; os providers LMS e Document Viewer são descobertos e o
plugin Group `course_document` é derivado depois de registrar seu bundle Media.
Com a política de upload do Portal, a execução combinada passou em 8 testes /
162 assertions (216 depreciações Core/contrib). Isso não instala os campos do
Runtime nem substitui smoke autenticado/revisão visual, que permanecem pendentes.

No dev.162, o mesmo harness criou GroupType, Group e Media reais, resolveu a
definição `aculta_document_viewer_group:course_document` registrada pelo owner e
persistiu a relação curso→documento (1 teste / 33 assertions; 239 depreciações
Core/contrib). O harness pertence ao `aculta_index` no Portal; o LMS não
hospeda nem possui o Index. A policy LMS segue coberta nos testes focais; o
Runtime permanece pendente.

### Regressão de acesso sem Drupal LMS contrib — 2026-11-04

`LmsCourseContentIndexAccessKernelTest` agora monta os tipos e relacionamentos
Group usados pelo LMS ACULTA sem habilitar `lms` ou `lms_classes`. O teste cobre
acesso de estudante/educador, leituras, galerias, vídeos e documentos: 5 testes
/ 239 assertions PASS no MariaDB `aculta_simpletest` (14 depreciações
Core/contrib). O fixture de Domain usa contexto isolado e não acessa o container
Drupal antes do boot. Em conjunto, `ContentIndexOwnerIntegrationKernelTest`
passou 2 testes / 65 assertions, comprovando a descoberta dos providers pela
closure de módulos real. Em 2026-11-04, leitura no Runtime `cursos` confirmou
`aculta_lms_resources` habilitado, três field storages e três instâncias de
campo em `lms_course`; `updatedb:status` e `config:status` estão limpos. Nenhum
update ou import foi aplicado. Smoke funcional autenticado com vínculos
existentes de leitura Wiki, galeria e vídeo passou em 2026-11-04: os três
widgets carregaram e a submissão manteve as referências na turma descartável.
O formulário de adição de documento também recebeu um PDF privado efêmero,
salvou Media/File, persistiu a relação Group→Media→File e listou o documento;
Group, Media e File foram removidos após o teste. A fixture usou associação
criadora válida para a conta docente. Revisão visual está fora do escopo
funcional atual.

### Upload e vínculo de documento PDF no workspace — dev.32

O formulário de adição usa a form owner da Media `course_document`, mas não
envia o objeto Group no cache do Form API. Um hook restrito à rota
`aculta_lms.course_document_add` registra um callback estático antes do cache;
depois do salvamento owner, o callback revalida permissão e cria a relação
Group→Media. O Runtime validou upload AJAX, salvamento e apresentação na lista,
com remoção das entidades e do arquivo privado de teste. O Kernel focal do
controller passou 4/36; a validação ainda não cobre produção nem Drupal 12.

### Limpeza coordenada de documentos PDF do curso — dev.31

Quando uma relação `aculta_document_viewer:course_document` é removida, o hook
do Group entrega o ID da Media ao `CourseDocumentCleanup`. Media inexistente,
publicada, de outro bundle ou sem campo de arquivo é ignorada. A Media não
publicada é removida apenas por `aculta_portal.unused_media_cleaner`, que
revalida referências globais; somente após resultado `Deleted` seu File é
submetido a `aculta_file_uploader.unused_file_cleaner`, que faz nova verificação
e delega a remoção física ao owner. Referências compartilhadas e falhas de
verificação retêm os assets. O caminho é idempotente e cobre a remoção da relação
individual e o `preDelete` do Group. Arquivos nunca são apagados diretamente
pelo LMS. A política compartilhada continua limitando a decisão final e a
remoção às APIs do Portal:
[política de limpeza](../../../../../../docs/portal/CONTENT-CLEANUP-POLICY.md).

Unit do módulo: 28 testes / 185 assertions PASS. O Kernel de lifecycle
Group→Media→File passa em 1 teste / 27 assertions; ele exercita a associação
compartilhada, a retenção após o primeiro detach e a remoção após o último.
O inspector compartilhado agora inclui referências Group por bundle. Runtime
permanece pendente.

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
