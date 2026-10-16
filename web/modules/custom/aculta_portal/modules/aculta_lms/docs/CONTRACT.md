# Contrato de eventos educacionais

## Conclusão aprovada

`CourseCompletedEvent` representa uma entidade `lms_course_status` já salva,
finalizada e com estado `passed`. O produtor chama
`aculta_lms.course_completion_publisher` somente após confirmar a transição
para esse estado. A classe contém UUID do status de origem, ID do Group curso,
ID numérico da conta Drupal e instante UTC `completedAt`.

O evento significa conclusão aprovada conforme a avaliação nativa do Drupal
LMS. O payload não contém score, respostas, arquivos ou dados de apresentação.
Não é uma fonte de verdade nova e não autoriza qualquer consumidor a conceder
nota, certificado, amizade ou mensagem sem consultar novamente a entidade LMS.

O publisher despacha uma vez por chamada e não é idempotente. O produtor é
responsável por chamar apenas na transição; consumidores futuros devem usar
UUID de origem + destinatário para deduplicação, reabrir o estado atual e
revalidar access/membership antes de qualquer ação. Nenhum produtor/listener de
produção está registrado em F1.

## Limites e compatibilidade

- LMS mantém status, tentativas, score e conclusão; Group mantém curso e
  membership; User mantém concessões; Messenger mantém mensagens/avisos.
- Não há storage de eventos, fila custom, rota, configuração ou envio nesta
  fase.
- A API é um contrato inicial interno do submódulo. Mudança incompatível
  requer revisão do ADR-005, consumidores e política de versionamento.
- Próximo consumidor educacional previsto: contrato LMS → Messenger F13, após
  produtor confiável, preferência, dedupe e revalidação de acesso.
