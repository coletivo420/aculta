# Arquitetura do aculta_lms

`aculta_lms` é dono de cursos, matrículas, progresso, prazos por turma e
certificados. Group mantém memberships e acesso contextual; Messenger entrega
avisos produzidos pelo LMS.

## Decisões vigentes

- **2026-10-21 — Certificado emitido sobrevive à desativação da conta.**
  Situação: vigente. Motivo: o bloqueio de uma conta não altera uma credencial
  acadêmica já emitida; somente revogação explícita invalida o certificado.
  Origem: `0.1.0-dev.32`, [histórico arquivado](../history/changelog/aculta_lms.md).
  `CourseCertificate` conserva o snapshot; `CertificateSnapshotHooks` impede
  reativar um certificado revogado.
- **2026-10-21 — Certificado revogado não pode ser verificado nem baixado como
  PDF.** Situação: vigente. Motivo: revogação explícita deve valer na verificação
  pública e no arquivo gerado. Origem: `0.1.0-dev.50`, [histórico arquivado](../history/changelog/aculta_lms.md).
  `CertificateAccessControlHandler` e `CertificateVerificationController`
  verificam o estado antes de renderizar o snapshot.
- **2026-10-21 — Prazo pertence à relação atividade–turma e aviso exige evento
  acionável persistido.** Situação: vigente. Origem: F13, `0.1.0-dev.17`,
  `0.1.0-dev.18` e `0.1.0-dev.63`, [histórico arquivado](../history/changelog/aculta_lms.md);
  contrato transversal em [ADR-017](../decisions/ADR-017-avisos-educacionais-acionaveis.md).

## Fronteiras

- A matrícula e o acesso a conteúdo dependem do Group/curso atual; o status do
  curso não cria permissão global. Confirmação de e-mail não é requisito de
  participação, conforme [ADR-029](../decisions/ADR-029-lms-sem-requisito-de-email-confirmado.md).
- Eventos de aviso são produzidos a partir de estado persistido; Messenger é
  dono da central e das políticas de entrega.
