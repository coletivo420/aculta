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
LMS. As fixtures temporárias foram removidas; `H5P.HelloWorld`, preexistente,
foi preservada. Nenhuma alteração de produção foi feita.

Na F15, o Kernel confirma que o estado de tentativa persiste quando a mesma
conta retorna em outra sessão e não pode ser lido por outro estudante
matriculado no curso. O H5P continua dono de seu progresso interno; resultados
não são convertidos em notas oficiais do LMS. A decisão do vínculo direto de discussões Forum ao conteúdo H5P já foi implementada no adapter `aculta_forum_lms`: tópico e conteúdo usam referência própria, seleção é limitada ao curso/turma e as conversas renderizam sob o player após revalidar acesso. Unit do resolver e do transporte de Group ao autocomplete passaram. Runtime `cursos` tem o campo instalado e o seletor falha fechado sem Group; ainda faltam Kernel específico de integração/seletor e smoke autenticado/visual.

## Painéis e vínculo com cursos

- Administração: `/painel-administrativo/lms/h5p`, com acesso pela permissão
  `administer h5p libraries` e atalhos para bibliotecas/configuração.
- Estudante: `/minha-conta/atividades-interativas`, ligado ao papel global
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
  curso, Group, matrícula e Entity Access. Smoke HTTP autenticado e visual ainda
  pendentes.
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
