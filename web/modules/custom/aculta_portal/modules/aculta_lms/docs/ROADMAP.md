# Roadmap — aculta_lms (produtores de eventos)

Roadmap central do LMS: `docs/modules/ACULTA-LMS-ROADMAP.md`. Este arquivo registra só a parte que
alimenta o `aculta_statistics` (ADR-0004), para não regredir no submódulo.

## Instantâneo por turma (2026-10-21)

- Feito (0.1.0-dev.78): `ClassStatusSnapshotEvent` no cron, série diária por turma e situação, sem dado individual.
- Decidido: a barra de progresso do aluno continua sendo o estado atual, sem série. Série diária **por aluno** exige revisão LGPD antes (dado pessoal ao longo do tempo); não foi criada.
- Pendente: seletor de período no painel da turma, lendo a série do `aculta_statistics` (regra de período em gráficos, ADR-0005).
- Pendente: validar no Runtime com turmas reais (o de teste não tem turmas).

## Produtores de eventos para estatísticas

- Já emitidos (uso pelo `aculta_statistics`): `CourseEnrollmentEvent` (matrícula, por curso e turma),
  `CourseCompletedEvent` (conclusão, uma vez por status), `CertificateIssuedEvent` (certificado, total).
- Pendente: instantâneo de situação por turma (`lms.students.by_status`: não iniciados, em andamento,
  aguardando avaliação, aprovados, reprovados). O painel da turma (`LearningDashboardManager::classDashboard()`)
  calcula ao vivo; o produtor proposto é um evento `ClassStatusSnapshotEvent` emitido pelo cron, com
  contagens por turma, sem identificação de aluno.
- Decidido (2026-10-21, responsável): série diária por turma, com a supressão de célula pequena (menos de 5
  alunos) já existente em `CellSuppression`.
- Não incluir: notas, respostas e progresso individual. O painel individual fica fora da central.
