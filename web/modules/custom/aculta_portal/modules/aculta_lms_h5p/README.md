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

## Proveniência

Origem: projeto Drupal H5P, release 2.0.0-beta1. Consulte LICENSE.txt e
`docs/modules/LMS-H5P-AUDIT.md` para proveniência e riscos conhecidos.
