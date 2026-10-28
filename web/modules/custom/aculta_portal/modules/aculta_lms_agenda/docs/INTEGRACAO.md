# Integração do calendário e dos eventos ao LMS

Decisão do responsável (D-15): integrar em todos os pontos, **restrito aos cursos e aos usuários
matriculados nele**:

1. **Prazos das turmas entram no calendário.** Cada prazo de atividade (`lms_class_activity_due`,
   campo `field_activity_due_at`) vira uma entrada da agenda do aluno matriculado.
2. **Eventos de turma entram no calendário.** Um evento (`aculta_event`) pode pertencer a uma turma
   (`field_turma`, referência a `lms_class`). Só os alunos daquela turma o veem.
3. **Painel de aprendizado mostra os próximos eventos.** Lista, em ordem de data, os próximos
   prazos e eventos das turmas em que o usuário está matriculado.

## Regra de acesso (a mesma em todos os pontos)

Um usuário vê uma entrada somente se:

- é membro (`GroupMembership`) de uma turma `lms_class` publicada;
- essa turma pertence a um curso publicado (`lms_classes`);
- o usuário tem acesso ao curso (`takeAccess`), que é a mesma checagem de `ClassActivityDeadlineManager`.

Quem não está matriculado não recebe a entrada, nem por lista nem por consulta direta. O provedor
da agenda não tem caminho público: toda consulta recebe o usuário e aplica essa regra.

## Arquitetura

- `aculta_lms_agenda` (novo submódulo, ao lado de `aculta_lms`). Depende de `aculta_lms` e de
  `aculta_events`. Nenhum dos dois depende dele: sem ciclo.
- `AgendaEntriesProvider::forAccount(AccountInterface, \DateTimeImmutable $de, \DateTimeImmutable $ate)`:
  devolve as entradas do usuário na janela, ordenadas por início. Cada entrada tem tipo (`prazo` ou
  `evento`), título, início, fim, curso e turma.
- Prazos usam `ClassActivityDeadlineManager` e as relações do grupo; eventos usam
  `aculta_events.occurrences` (expansão de série, RECORRENCIA.md).
- O painel usa o mesmo provedor, limitado aos próximos N itens.

## Fora desta etapa (ficam para as microfases seguintes)

- Interface da agenda e da grade do calendário (F5).
- Exportação `.ics` pessoal: exige token por usuário; decisão própria, não entra sem aprovação.
- Notificação por e-mail de eventos de turma.

## Dados

- `aculta_event.field_turma`: `entity_reference` para `group` com bundle `lms_class`.
  Evento sem turma é público, conforme o purpose eventos; evento de turma é restrito.
