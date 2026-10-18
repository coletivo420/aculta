# ACULTA LMS H5P

Integração própria do LMS ACULTA, derivada do módulo Drupal H5P 2.0.0-beta1,
licenciado sob GPL-2.0-or-later. As alterações ACULTA estão identificadas nos
arquivos adaptados. O motor PHP H5P Core e o Editor continuam dependências
Composer independentes, sob GPL-3.0-only.

## Estado

O módulo permanece desabilitado até a conclusão dos testes de isolamento,
permissões, associação com turma/curso e persistência de tentativas. A rota
pública de iframe do módulo de origem foi removida porque não fazia a validação
de acesso ao conteúdo pai. O conteúdo deve ser apresentado no contexto de uma
aula/turma com acesso validado pelo Drupal e pelo LMS.

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

O painel do educador é, nesta etapa, uma visão dos cursos administráveis. O
editor de atividades ainda não fica disponível: abrir o formulário nativo do
Group antes de portar com segurança os serviços do editor exporia uma interface
incompleta.

## Proveniência

Origem: projeto Drupal H5P, release 2.0.0-beta1. Consulte LICENSE.txt e
`docs/modules/LMS-H5P-AUDIT.md` para proveniência e riscos conhecidos.
