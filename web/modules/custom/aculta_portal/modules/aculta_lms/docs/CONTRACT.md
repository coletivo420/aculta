# Contrato de eventos educacionais

## Conclusão de curso

`CourseCompletedEvent` é um contrato neutro de
`aculta_learning_contracts\Event`. O provider atual é o adaptador
`aculta_lms.course_completion_publisher`, que publica o evento depois de
confirmar um status LMS salvo, finalizado e com estado `passed`. Importações
marcadas como sincronização são ignoradas.

O payload contém UUID opaco da origem, ID do curso Group, ID numérico da conta
como string e instante UTC `completedAt`. O evento significa conclusão
aprovada conforme a avaliação nativa do provider Drupal LMS. Não contém score,
respostas, arquivos ou dados de apresentação e não é um registro de progresso
paralelo.

Os consumidores atuais são:

- `aculta_game`: concede o gatilho `course_passed` por meio da API existente
  de conquistas.
- `aculta_messenger`: recebe o aviso encaminhado pelo fluxo LMS e mantém
  `lms_course_status` como tipo de entidade de origem para compatibilidade com
  a deduplicação e o armazenamento atuais.

O publisher despacha por chamada e não mantém registro de entrega nem garante
idempotência. O hook produtor deve chamar somente na transição para conclusão.
Consumidores não devem tratar o UUID como classe de entidade, nem criar
armazenamento paralelo de conclusão.

## Limites e compatibilidade

- O LMS mantém status, tentativas, score e conclusão; Group mantém curso e
  membership; User mantém concessões; Messenger mantém mensagens e avisos.
- O evento neutro reduz dependências de código dos consumidores, mas não remove
  a dependência do provider de `aculta_lms` em Drupal LMS contrib.
- Não há storage de eventos nem fila custom nesta integração.
- Mudanças incompatíveis no payload exigem revisão dos consumidores e da
  política de versionamento do contrato.
