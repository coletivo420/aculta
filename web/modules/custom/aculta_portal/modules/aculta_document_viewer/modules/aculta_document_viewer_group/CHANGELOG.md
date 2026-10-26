# Changelog — aculta_document_viewer_group

## 0.1.0-dev.2 — provider de conteúdo sob o owner da relação

- O provider `course_document` do Content Index passa a ser declarado pelo
  módulo que registra o plugin Group e cuja relação está na configuração
  `lms_course`. A dependência explícita do Portal cobre a classe do provider.
- A configuração instalada de Media/File permanece no submódulo PDF Group;
  sua transferência é uma migração separada e não foi aplicada.
- Não houve importação de configuração, atualização ou alteração do Runtime.
