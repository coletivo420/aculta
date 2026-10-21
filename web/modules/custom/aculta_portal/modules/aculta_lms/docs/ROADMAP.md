# Roadmap — aculta_lms (produtores de eventos)

Roadmap central do LMS: `docs/modules/ACULTA-LMS-ROADMAP.md`. Este arquivo registra só a parte que
alimenta o `aculta_statistics` (ADR-0004), para não regredir no submódulo.

## Produtores de eventos para estatísticas

- Já emitidos (uso pelo `aculta_statistics`): `CourseEnrollmentEvent` (matrícula, por curso e turma),
  `CourseCompletedEvent` (conclusão, uma vez por status), `CertificateIssuedEvent` (certificado, total).
- Pendente: instantâneo de situação por turma (`lms.students.by_status`: não iniciados, em andamento,
  aguardando avaliação, aprovados, reprovados). O painel da turma (`LearningDashboardManager::classDashboard()`)
  calcula ao vivo; o produtor proposto é um evento `ClassStatusSnapshotEvent` emitido pelo cron, com
  contagens por turma, sem identificação de aluno.
- Decisão em aberto: o instantâneo grava por dia (série) ou só o último valor? Recomendação: por dia, com a
  supressão de célula pequena (menos de 5 alunos) já existente em `CellSuppression`.
- Não incluir: notas, respostas e progresso individual. O painel individual fica fora da central.
