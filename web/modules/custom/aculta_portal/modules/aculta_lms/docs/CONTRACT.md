# Contrato de eventos educacionais

## Conclusão de curso

`CourseCompletedEvent` é o contrato neutro sediado em
`Drupal\aculta_lms\Event\CourseCompletedEvent` (anteriormente `aculta_learning_contracts`,
cujas interfaces e eventos foram incorporados nativamente ao `aculta_lms`). O provider é o
adaptador `aculta_lms.course_completion_publisher`, que publica o evento depois de
confirmar um status LMS salvo, finalizado e com estado `passed`. Importações
marcadas como sincronização são ignoradas.

O payload contém UUID opaco da origem (`sourceUuid` ou `sourceStatusUuid`), ID do curso Group,
ID numérico da conta como string e instante UTC `completedAt`. O evento significa conclusão
aprovada conforme a avaliação nativa do provider LMS. Não contém score,
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

## Contratos de Jornada e Sumário de Aprendizado

As interfaces e DTOs neutros de leitura educacional agora residem diretamente sob `Drupal\aculta_lms\Learning`:
- `CourseJourneyReaderInterface` / `CourseJourneyDTO`
- `StudentLearningSummaryReaderInterface` / `StudentLearningSummaryDTO`
- `AccountCoursesReaderInterface` / `AccountCourseSummaryDTO`

Estes contratos permitem que módulos de apresentação como `aculta_studio` e `aculta_user` consumam dados curriculares e jornadas de aprendizado de forma desacoplada, sem acoplamento a implementações internas de banco ou armazenamento.

## Limites e compatibilidade

- O LMS mantém status, tentativas, score, materiais/recursos, prazos e conclusão; Group mantém curso e
  membership; User mantém concessões; Messenger mantém mensagens e avisos; Calendar fornece motor de agenda.
- O evento neutro desacopla o ciclo de vida e dependências de código dos consumidores.
- Não há storage de eventos nem fila custom nesta integração.
- Mudanças incompatíveis no payload exigem revisão dos consumidores e da
  política de versionamento do contrato.
