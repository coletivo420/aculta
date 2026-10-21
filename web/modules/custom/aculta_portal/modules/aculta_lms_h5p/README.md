# ACULTA LMS H5P

Integração própria do LMS ACULTA, derivada do módulo Drupal H5P 2.0.0-beta1,
licenciado sob GPL-2.0-or-later. As alterações ACULTA estão identificadas nos
arquivos adaptados. O motor PHP H5P Core e o Editor continuam dependências
Composer independentes, sob GPL-3.0-only.

## Estado

F10 foi homologada no Runtime de teste `cursos`: o pacote de biblioteca foi
instalado pela tela administrativa, uma atividade foi salva pelo editor e
reproduzida para estudante matriculada no Group. A rota de iframe do módulo de
origem foi removida porque não validava o acesso ao conteúdo pai. O conteúdo é
apresentado no contexto do curso, após validação de acesso pelo Drupal e pelo
LMS. Bibliotecas que declaram apenas `iframe` usam o iframe interno `about:blank`
do H5P Core na mesma página autorizada; não há endpoint público de incorporação.
As fixtures temporárias foram removidas; `H5P.HelloWorld`, preexistente, foi
preservada. Nenhuma alteração de produção foi feita.

Na F15, o Kernel confirma que o estado de tentativa persiste quando a mesma
conta retorna em outra sessão e não pode ser lido por outro estudante
matriculado no curso. O H5P continua dono de seu progresso interno; resultados
não são convertidos em notas oficiais do LMS. A integração de discussões do
`aculta_forum_lms` liga tópicos ao conteúdo H5P, limita seleção e acesso pelo
curso/turma e renderiza conversas sob o player. Unit cobre o resolver; Kernel
com entidades persistidas valida isolamento por conteúdo, curso e turma,
permissão do tópico, contexto do Group no autocomplete e composição do player.
No Runtime de teste, a revisão visual autenticada confirmou o player iframe-only,
o PDF privado e a conversa H5P–Fórum sob a atividade; o link do tópico abriu no
host de cursos com HTTP 200. As fixtures temporárias foram removidas após o
smoke, sem manter conteúdo H5P ou tópico de validação no Runtime.

Educadores também podem associar PDFs `course_document` existentes a cada
atividade H5P. A referência aponta para o mesmo Media do curso, sem duplicar o
arquivo. O formatter valida novamente a relação com o Group e usa
`aculta_pdf_viewer.builder`, preservando as permissões de arquivo privado. O
campo de referência foi instalado pelo update `aculta_lms_h5p_update_8006` no
Runtime de teste. O Kernel verifica que o formatter entrega ao builder somente
documentos `course_document` relacionados ao mesmo curso. O smoke autenticado
no Runtime confirmou o PDF privado renderizado pelo PDF.js para estudante
matriculado; Media, arquivo, relação, atividade e curso temporários foram
removidos depois da validação. A revisão visual da integração com o Fórum
confirmou que o estudante vê apenas o tópico vinculado àquela atividade e
consegue abrir seu Node no mesmo host de cursos; o tópico e o curso temporários
também foram apagados.

O formatter escolhe `div` quando disponível; para bibliotecas iframe-only, usa o
inicializador interno do H5P Core com `about:blank`. A suíte Unit cobre a
seleção dos modos `div` e `iframe`; o Kernel verifica que a reprodução
iframe-only mantém a autorização do curso, o título acessível e os assets
associados. Os assets Composer são preparados na instalação/update do módulo,
sem gravação durante a descoberta de bibliotecas em requisições. O Runtime
confirmou `H5PIntegration.core`, scripts/estilos do Core e da biblioteca e a
atividade carregada no iframe autorizado.

## Painéis e vínculo com cursos

- Administração: `/painel-administrativo/lms/h5p`, com acesso pela permissão
  `administer h5p libraries` e atalhos para bibliotecas/configuração.
- Estudante: `/atividades-interativas` no purpose `account`, ligado ao papel global
  `estudante`. Lista apenas cursos em que `CourseInterface::takeAccess()` permite
  entrada e que tenham atividades no campo H5P; o campo pertence ao Group
  `lms_course`, que continua sendo a fonte de verdade do curso e da matrícula.
- Educador: `/educador/atividades-interativas`, ligado ao papel `lms_teacher`.
  Lista apenas cursos em que o acesso `update` do Group é permitido e aponta para
  a sala do curso no purpose `courses`.
- A autorização e a cacheabilidade são calculadas com os resultados de access
  do Group/LMS; a lista acrescenta contextos de usuário/permissões e tags dos
  cursos. O serviço `DomainPurposeManager` resolve o host dos cursos.
- O player serve assets de conteúdo por `private://` após conferir matrícula e
  vínculo do H5P com o Group; os estados e resultados aplicam a mesma relação.
- Imagens do editor e assets de imagem das bibliotecas usam a política e os
  serviços de `aculta_image`; uploads permanecem arquivos gerenciados privados.
- H5P usa o acesso ao curso do Group para renderizar atividades no contexto do
  LMS. Documentos PDF complementares do curso pertencem à Media
  `course_document` e ao viewer `aculta_pdf_viewer` com controle
  `aculta_pdf_viewer_group`; H5P não cria outro leitor nem converte seus assets
  internos em Media. Educadores associam diretamente um tópico existente de `aculta_forum` ao
  conteúdo H5P por `field_h5p_content`; `field_lms_activity` continua servindo
  para discussões gerais de atividade. O adapter lista somente conteúdo H5P do
  curso/turma editável e injeta tópicos permitidos sob o player, revalidando o
  curso, Group, matrícula e Entity Access. O painel administrativo passou por
  revisão visual autenticada do player, do PDF e da conversa contextual
  H5P–Fórum no Runtime de teste. Nenhuma fixture temporária permaneceu no
  Runtime.
- Integração com CTools: a condição `Course has interactive activities (H5P)`
  recebe contexto `entity:group` e permite condicionar displays/contextos CTools
  à existência de atividades. O projeto não tem Page Manager instalado; não
  foi criado um painel/layout paralelo nem uma relação que escolha arbitrariamente
  uma atividade entre várias.

O painel do educador lista cursos administráveis; a criação e edição de atividades
acontece na aba Editar da sala, pelo campo do curso protegido pelas permissões do
Group. O adapter do editor cobre listagem e metadados de bibliotecas locais, traduções,
validação de parâmetros e upload privado de mídia, com autorização Group e token
H5P. Administradores podem instalar pacotes `.h5p` locais pelo validador oficial
do H5P; o conteúdo do pacote é ignorado e apenas bibliotecas aprovadas são
instaladas. Imagens raster e SVG passam pelo `aculta_image`, e os demais assets
ficam como arquivos privados gerenciados. Hub, telemetria e instalação remota
continuam desabilitados. F10 foi homologada ponta a ponta no Runtime de teste;
o changelog registra os testes Unit/Kernel, gates e limites da homologação.
Exportação pública e estatísticas externas ficam desabilitadas.

O inventário inicial em `docs/modules/LMS-H5P-AUDIT.md` é histórico e antecede
a homologação F10. Estado atual e evidências estão neste README e no changelog.

## Proveniência

Origem: projeto Drupal H5P, release 2.0.0-beta1. Consulte LICENSE.txt e
`docs/modules/LMS-H5P-AUDIT.md` para proveniência e riscos conhecidos.

Entregas da integração ACULTA: [CHANGELOG.md](CHANGELOG.md).
