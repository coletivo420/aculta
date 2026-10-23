# Roadmap — aculta_lms (produtores de eventos)

Roadmap central do LMS: `docs/modules/ACULTA-LMS-ROADMAP.md`. Este arquivo registra só a parte que
alimenta o `aculta_statistics` (ADR-0004), para não regredir no submódulo.

## Instantâneo por turma (2026-10-21)

- Feito (0.1.0-dev.78): `ClassStatusSnapshotEvent` no cron, série diária por turma e situação, sem dado individual.
- Decidido: a barra de progresso do aluno continua sendo o estado atual, sem série. Série diária **por aluno** não foi criada. Revisão LGPD feita em 2026-10-21: não aprovada para armazenamento (ver `docs/compliance/REVISAO-PROGRESSO-INDIVIDUAL.md`). Aguarda a decisão do responsável entre as opções A, B e C.
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
- Decidido (2026-10-21, responsável): série diária **por aluno**, sob o aceite único (Termos e Política), sem
  checkbox separado. Implementado no 0.1.0-dev.79 (`StudentStatusSnapshotEvent`). Vale depois da publicação da função
  na política (`docs/compliance/CLAUSULAS-POLITICA-ACEITE-UNICO.md`).
- Não incluir: notas, respostas e progresso individual. O painel individual fica fora da central.

## Auditoria transversal XK 0.1 — 2026-10-22

XK-01/04/05/07/08/12: manter Group de turmas/cursos independente do Group de associados do Fórum; avisos saem por aculta_lms_messenger → Messenger; não criar mensageria LMS paralela. Fórum contextual usa adapter Forum LMS e grants de Group do curso. Viewer/estatísticas usam kernels próprios.

Plano de dependências e gates: [ACULTA-XK 0.1](../../../../../../../docs/architecture/ACULTA-CROSS-MODULE-CENTRALIZATION-AUDIT-2026-10.md). **Planejado; sem alterações funcionais ou validação Runtime.**

## ACULTA-XK 0.1-B — contrato arquitetural (2026-10-22)

XK 0.1-B: preservar membership e grants Group de cursos/turmas; não herdar elegibilidade de associado do Fórum. Eventos LMS seguem o adaptador aculta_lms_messenger, sob idempotência e revalidação na entrega. Teste regressão Group depois de qualquer refatoração User/Forum.

Fonte de verdade: [ADRs XK-01 a XK-03](../../../../../../../docs/architecture/ACULTA-XK-0.1-B-SHARED-CONTRACTS.md). **Somente planejamento, sem alteração funcional ou testes Runtime.**

## Content Index — adapter de curso (IDX 0.1-F)

O provider `Group:lms_course` vive em `aculta_lms_resources`, junto das referências editoriais `field_lms_readings` e `field_lms_galleries`. Ele projeta metadados mínimos e relações para Revista/Wiki/Gallery; não indexa lições, matrículas, progresso, respostas, notas ou certificados. O acesso exige Entity Access mais `CourseInterface::takeAccess()` ou permissão contextual de educador. Pendentes: Kernel/DI, reconciliação das referências e smoke no Runtime após preflight. O contrato geral está em [ACULTA Content Index](../../../../../../docs/portal/ACULTA-CONTENT-INDEX.md).

## Identidade fiscal no checkout comercial (CPF-CHECKOUT-0.2-I, 2026-10-23)

- Cursos pagos e gratuitos que passam pelo checkout comercial exigem CPF/CNPJ válido para concluir o pedido. Política compartilhada com a Loja, sem duplicar validação nem o campo (fonte: `/dados/outros-dados`, `aculta_id_cpf`).
- Flow e pane de checkout compartilhados com a Loja; tipo de pedido `curso` e matrícula inalterados.
- Matrícula continua dependendo do pagamento concluído. CPF/CNPJ cadastrado não libera matrícula.
- Pendente: implementação (I-D-LMS) e testes de regressão. Ver `docs/portal/CPF-CONSOLIDACAO-E-CHECKOUT.md`.
