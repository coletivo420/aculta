# ACULTA Forum LMS

Adapter opcional entre `aculta_forum`, `aculta_lms` e Group. Esta primeira
entrega registra um provider privado para Nodes `aculta_lms_topic` e delega a
autorização do marcador de moderação aos Educadores com `grade students` no
curso contextual.

## Estado

O módulo não deve ser habilitado até as permissões Group de course/class roles,
membership, Comment e a matriz de acesso serem cobertas por testes Kernel. A
dev.94 adiciona install config do bundle `aculta_lms_topic`, Comment
compartilhado e relações `group_node:aculta_lms_topic` para curso/turma. A
dev.97 acrescenta em Config Sync a herança `grade students` do curso para a
permission Group Node dos tópicos de turma, validada pelo `ClassPermissionCalculator`
real. Essa configuração ainda não foi importada no Runtime. Não foram criadas
Views, Search API, rotas ou conteúdo.
`gnode` está listado como
dependência do adapter, não como alteração de `core.extension`. Um hook
`entity_access` nega acesso a Nodes do bundle quando falta relação Group única;
para `lms_class`, também exige uma relação única com um curso. Com relações
válidas, o hook retorna neutro e deixa o access handler do Group aplicar os
papéis.

Discussões de turma exigem exatamente uma relação `lms_classes` para o curso
pai. A associação de aula/atividade ainda não faz parte desta entrega. Acesso a
leitura combina Entity Access do Node, acesso ao Group e membership real na
turma/curso ou permissão contextual de Educador. Ausência, duplicidade ou tipo
inesperado de relação falha fechado.

## Ownership

- Nodes e Comments continuam no Core.
- `aculta_forum_lms` resolve contexto e autorização; não duplica matrícula,
  respostas, cartões, busca, notificações ou mídia.
- Cartões educacionais continuam pertencendo ao `aculta_user`.
- `aculta_messenger`, `aculta_image`, Search API e tema não são alterados aqui.

## Próxima etapa

Os Kernels `LmsGroupNodeAccessKernelTest` e
`LmsClassEducatorPermissionKernelTest` usam `aculta_simpletest`: provam acesso
de membro e Educador contextual, bloqueio de outsider (inclusive com papel
global `lms_teacher`) e herança real de `grade students` para o Group Node da
turma. Unit cobre escrita, status fechado e moderação do comentário. A
combinação de acesso trata membership e permissão contextual como alternativas
explícitas e nega somente se ambas falharem; `orIf()` não pode ser usado porque
`forbidden` prevalece sobre uma alternativa permitida. Próximo: integrar e provar
Comment real com callbacks de criação, estado aprovado e moderação antes de
criar as filas administrativas LMS ou habilitar o adapter.
