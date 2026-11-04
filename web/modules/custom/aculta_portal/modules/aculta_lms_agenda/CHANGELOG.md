## 0.1.0-dev.2 — prazos da agenda usam conteúdo ACULTA

- `AgendaEntriesProvider` verifica acesso ao curso pela permissão Group
  `take course` e lê prazo por `ActivityEntity`.
- `AgendaEntriesProviderKernelTest` usa entidades próprias de aula/atividade,
  sem importar interfaces LMS; o harness ainda habilita LMS por dependência
  declarada em `aculta_lms`.
- Corrigido o hook de acesso de eventos de turma para negar explicitamente
  alunos de outra turma; a resposta neutra anterior deixava o handler público
  de eventos liberar a visualização.
- Agenda Kernel 4/168 PASS (94 depreciações Core/contrib), Unit 8/18 PASS e
  PHPCS PASS.
# Verificação adicional — publicação do curso invalida acesso à agenda (2026-11-04)

- A negativa de acesso a eventos da turma inclui o curso pai despublicado como dependência cacheável. O teste confirma as tags da entidade e da relação Group e o acesso após a publicação.
- Agenda Kernel 5 testes / 195 assertions PASS; Unit Agenda 8/18 PASS. LMS Unit 270/1.024 e Kernel 41/1.330 PASS. Depreciações emitidas pelo Kernel vêm de Core/contrib.

