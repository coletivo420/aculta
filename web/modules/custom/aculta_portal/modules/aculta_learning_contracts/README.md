# ACULTA Learning Contracts

Camada neutra dos contratos educacionais consumidos pelo ACULTA Studio
(Ganjalab) e implementados pelo `aculta_lms`.

## Fronteira

- Não declara dependência de `aculta_portal`, de `aculta_studio` ou de `lms:lms`.
- Contém apenas interfaces e value objects (sem entidades, storage, rotas ou
  permissões).
- `aculta_studio` e `aculta_lms` dependem deste módulo e podem importar seus
  tipos sem criar uma dependência de código entre si.
- A fonte de verdade continua no Group/Drupal LMS durante a transição; os DTOs
  carregam somente dados já autorizados pelo provedor.

## Contratos

- `CourseJourneyReaderInterface`, `CourseJourney` e `CourseJourneyLesson`:
  jornada do curso e sala de aula.
- `StudentLearningSummaryReaderInterface`, `StudentLearningSummary`,
  `StudentLearningCourse` e `StudentLearningCertificate`: resumo tipado do
  painel do estudante (B2).

## Estado

Implementação inicial em Dev. Não há migração de dados nem armazenamento
próprio. A retirada do Drupal LMS contrib depende das fases registradas em
`docs/modules/LMS-CONTRIB-DEPENDENCY-INVENTORY.md`.
