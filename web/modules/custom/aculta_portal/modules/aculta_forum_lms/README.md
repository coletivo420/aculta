# ACULTA Forum LMS

Adapter opcional entre `aculta_forum`, `aculta_lms` e Group. Esta primeira
entrega registra um provider privado para Nodes `aculta_lms_topic` e delega a
autorização do marcador de moderação aos Educadores com `grade students` no
curso contextual.

## Estado

O módulo não deve ser habilitado até a instalação versionada do bundle,
`field_aculta_discussion`, relações `group_node:aculta_lms_topic` e a matriz de
acesso serem cobertas por testes Kernel. Não foi incluído Config Sync de
permissões, Views, Search API, rotas ou conteúdo. `gnode` está listado como
dependência do adapter, não como alteração de `core.extension`.

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

Adicionar configuração instalável e testes Kernel no banco `aculta_simpletest`
para estudante da turma, educador do curso, membro de outra turma e usuário
externo. Validar também acesso direto ao Node e ao Comment; só então criar as
filas administrativas LMS.
