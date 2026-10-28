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

- `aculta_event.field_turma`: `entity_reference` para `group` com bundle `lms_class`. Declarado por
  `hook_entity_base_field_info()` no próprio `aculta_lms_agenda`, e não em `aculta_events`, para o
  módulo de eventos continuar independente do LMS. Evento sem turma é público; evento de turma é restrito.
- `hook_install` cria a coluna do campo ao habilitar o submódulo.

## Bloco do painel

`ProximosEventosBlock` (`aculta_lms_agenda_proximos`) mostra os próximos 5 itens dos próximos 60
dias, em dd/mm/aaaa HH:mm, para o usuário atual. Visitante vê o aviso de lista vazia. A colocação
na região do painel é configuração do site e depende de aprovação.
