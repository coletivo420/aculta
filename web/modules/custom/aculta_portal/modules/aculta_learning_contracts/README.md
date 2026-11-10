# ACULTA Learning Contracts

Contratos neutros de aprendizagem consumidos pelo Portal. O módulo não conhece
Group, LMS, matrícula ou progresso: apenas define a interface e o DTO que os
provedores implementam.

## Contrato

- `Drupal\aculta_learning_contracts\Learning\AccountCoursesReaderInterface`
  expõe `getAccountCourses(AccountInterface): array`.
- `Drupal\aculta_learning_contracts\Learning\AccountCourseSummary` é o DTO
  autorizado com status normalizado, descrição e URL.

## Provedor atual

O `aculta_lms` registra o alias
`Drupal\aculta_learning_contracts\Learning\AccountCoursesReaderInterface`
para `aculta_lms.account_courses_reader`. O Portal consome apenas o contrato e
continua funcional quando nenhum provedor está instalado.

## Limites

- Não lê Group, matrícula ou progresso.
- Não renderiza nem define acesso; o provedor entrega somente cursos autorizados.
