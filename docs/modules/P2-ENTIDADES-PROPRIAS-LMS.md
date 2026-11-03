# P2 — Entidades próprias do aculta_lms (ADR-042)

- **Status:** implementado em `aculta_lms` 0.1.0-dev.129 e vigente em 0.1.0-dev.158; as quatro entidades e a atualização idempotente estão no módulo. Esta atualização registra o estado do desenho sem alterar o modelo aprovado.
- **Data:** 2026-11-02
- **Decisões do responsável:** os quatro modelos (aula, atividade, resposta e progresso) entram juntos no P2; os campos são do ACULTA, sem espelhar o contrib e sem migração de conteúdo legado (ADR-042, decisões 5 e escolha de 2026-11-02).
- **Relaciona-se com:** ADR-042 (P2), ADR-041 (xAPI: referência opaca `lesson_status_id`).

## Princípios

1. Cada entidade nasce com os campos do contrato ACULTA já existente (`CourseLesson`, `CourseActivity`, `CourseAnswer`, `CourseProgress`). Nomes de campos em inglês técnico, rótulos em português.
2. Nenhuma relação com entidade do contrib. Cursos e turmas continuam no Group, por referência de ID.
3. Entidades ficam internas: sem rota pública, sem formulário de conteúdo nesta fase. Acesso só por serviço.
4. Instalação por `hook_update_N` com `$schema`, como já feito em `aculta_lms_update_10003` e `10004`.
5. Os adaptadores atuais continuam lendo o contrib. A troca para a fonte própria é P5 para cada leitor, com gate próprio.

## Modelo de dados

### `aculta_lms_lesson` (aula)
| Campo | Tipo | Obrigatório | Observação |
|---|---|---|---|
| id | serial | sim | chave |
| uuid | uuid | sim | |
| course_id | integer | sim | ID do Group de curso (opaco) |
| title | varchar(255) | sim | |
| published | boolean | sim | padrão falso |
| weight | integer | sim | ≥ 0; ordem no curso |
| changed | integer | sim | timestamp |

Índice: `course_id, weight`.

### `aculta_lms_activity` (atividade)
| Campo | Tipo | Obrigatório | Observação |
|---|---|---|---|
| id | serial | sim | chave |
| uuid | uuid | sim | |
| lesson_id | integer | sim | FK lógica para `aculta_lms_lesson.id` |
| type | varchar(64) | sim | `[a-z_]+`, validado no contrato `CourseActivity` |
| graded | boolean | sim | entra na nota do curso |
| weight | integer | sim | ≥ 0 |
| changed | integer | sim | |

Índice: `lesson_id, weight`.

### `aculta_lms_answer` (resposta)
| Campo | Tipo | Obrigatório | Observação |
|---|---|---|---|
| id | serial | sim | chave |
| uuid | uuid | sim | |
| activity_id | integer | sim | |
| uid | integer | sim | aluno |
| state | varchar(16) | sim | `submitted` ou `graded` (`CourseAnswer::STATE_*`) |
| score | float | não | 0–100; obrigatório quando `graded` |
| created | integer | sim | |
| changed | integer | sim | |

Índice: `activity_id, uid`. Restrição de domínio: `state = graded` exige `score`, como no contrato.

### `aculta_lms_progress` (progresso do curso)
| Campo | Tipo | Obrigatório | Observação |
|---|---|---|---|
| id | serial | sim | chave |
| uuid | uuid | sim | |
| course_id | integer | sim | ID do Group de curso |
| uid | integer | sim | |
| status | varchar(32) | sim | `in progress`, `passed`, `failed`, `evaluation` (`CourseProgress::STATUS_*`) |
| finished | boolean | sim | |
| score | float | não | |
| changed | integer | sim | |

Índice único: `course_id, uid`.

## Ligação com as declarações xAPI

`aculta_lms_xapi_statement.lesson_status_id` já é referência opaca. Na troca para a fonte própria, o campo passa a apontar para `aculta_lms_progress.id`, por script de atualização que não reescreve as declarações (ADR-041). Essa troca é P5 e não entra no P2.

## Testes exigidos no P2

- Kernel de instalação das quatro entidades com `aculta_simpletest` e `key` declarados.
- Unit de cada restrição de domínio (já cobertas no valor objeto; a entidade reusa a mesma regra).
- Atualização `hook_update` idempotente: rodar duas vezes sem erro.
- Gate do Portal sem expansão de dívida.

## Limites registrados para P5

- `aculta_lms_answer` implementa o contrato `CourseAnswer`: atividade, estudante, estado, nota e datas. O desenho P2 não define persistência de payload de resposta, tentativas, comentários ou anexos. Esses dados não foram adicionados nem migrados.
- A ADR-042 determina que comentários e envio de arquivo passem a ser responsabilidade ACULTA, mas o contrato e o esquema próprios desses recursos ainda precisam ser definidos antes de substituir seus fluxos contrib.

## Riscos

- Nomes de campos não podem colidir com os do contrib durante a convivência. Por isso as tabelas usam prefixo `aculta_lms_`.
- A entidade própria começa vazia. Os adaptadores continuam lendo o contrib até o P5; a interface do aluno não muda no P2.

## Decisões pendentes (não bloqueiam o código)

- Nenhuma. Se o responsável quiser campos extras, indicar antes de implementar.
