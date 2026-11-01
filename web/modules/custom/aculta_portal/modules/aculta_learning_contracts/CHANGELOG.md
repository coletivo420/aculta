## 0.1.0-dev.1 — camada neutra de contratos educacionais (2026-11-01)

- Cria o módulo sem dependências Drupal (`aculta_learning_contracts`).
- Move para cá `CourseJourneyReaderInterface`, `CourseJourney` e
  `CourseJourneyLesson`, antes em `aculta_portal`/`aculta_studio`.
- Na fase B2, adiciona `StudentLearningSummaryReaderInterface` e os DTOs
  `StudentLearningSummary`, `StudentLearningCourse` e
  `StudentLearningCertificate`; o antigo `LearnerDashboardComposerInterface`
  foi substituído por esse resumo tipado.
- `aculta_studio` e `aculta_lms` declaram dependência do módulo; nenhum dos dois
  importa classes do outro para esses contratos.
- Validação: Kernel `ContractsInstallTest` 1/8 PASS instalando o módulo com
  apenas `system` (sem `aculta_portal` e sem `lms`); Unit Studio 62/597 e LMS
  91/475 PASS; PHP lint e PHPCS limpos.
- **Runtime:** módulo habilitado em 2026-11-01 após snapshot com hash e
  `drush cr`; `config:status` sem diferenças; jornada e sala 74/74 em
  320–1440 px; dashboard e formulário do Educador 200. Não houve migração de
  dados, storage, rota ou escrita de conteúdo.
