# Análise do módulo lms contrib 1.2.4 (referência, sem dependência)

Status: análise de referência para a F3 do xAPI (ADR-041). **O `lms` não é requisito do projeto e não entra no `composer.json`.** Esta análise lê o código do pacote para decidir o modelo de vínculo, sem executar nada dele.

Fonte: tarball `lms-1.2.4` enviado pelo responsável, extraído em diretório isolado do scratchpad, fora do repositório. Não houve checagem de versões posteriores nem da página do projeto no drupal.org.

## 1. Metadados

- Versão: `1.2.4`, projeto `lms`, mantenedor listado no `composer.json`.
- Licença: GPL-2.0-or-later.
- Compatibilidade declarada: Drupal `^10.3 || ^11`. O projeto usa `^11.3`.
- Dependências: `drupal:user`, `drupal:views`, `group:group` (`drupal/group ^3.2`).

## 2. Entidades relevantes

| Entidade (`id`) | Papel | Campos úteis |
| --- | --- | --- |
| `lms_lesson_status` | Progresso de um aluno em uma aula | `score` (int), `required_score`, `max_score`, `percent`, `evaluated`, `started`, `finished`, `course_status` |
| `lms_answer` | Resposta de um aluno a uma atividade | `user_id`, `score` (int), `evaluated`, `data` (texto longo), `lesson_status` |
| `lms_course_status` | Progresso de um aluno em um curso | status (`new`, `in progress`, `passed`, `failed`, `evaluation`, `expired`) |
| `lms_activity`, `lms_activity_type`, `lms_lesson` | Conteúdo do curso | — |

## 3. Pontos que afetam a F3

1. **Não há entidade de tentativa por lançamento.** O `lms_lesson_status` é um registro por aluno e aula. Um mesmo aluno pode ter várias tentativas de uma aula, mas o módulo não as separa como entidades. Assim, o `registration` do xAPI não tem um destino natural no contrib.
2. **Notas são inteiros.** O deriver da F2 produz float de 0 a 100. A F3 precisa arredondar ao gravar, e documentar o arredondamento.
3. **Estados do curso são fixos e em inglês com espaços** (`in progress`). O ACULTA deve mapear, sem alterar o contrib.
4. **Escrita de resposta passa por hooks do contrib** (`lms_create_answer`, `lms_update_lesson_status`, `lms_all_answers_evaluated`). Uma integração que grave direto na entidade contorna esses pontos; o caminho seguro é usar o serviço do contrib, se houver, ou documentar o desvio.
5. **Armazenamento de dados brutos.** O `lms_answer.data` é texto longo. Não é adequado para guardar declarações xAPI completas, porque mistura respostas de atividade com o progresso.

## 4. Consequências para o projeto

- Não adicionar o `lms` como requisito para a F3. A dependência, se houver, seria decisão própria, com justificativa e ADR.
- Para vincular `registration` à aula, a opção que respeita a regra de não criar armazenamento paralelo é guardar apenas o ID do `lms_lesson_status` (ou da tentativa, se o ACULTA criar essa entidade) na declaração, sem copiar o estado. Pendente de decisão do responsável.
- Se o ACULTA criar uma entidade própria de tentativa, ela deve ser motivada no ADR: o contrib não a oferece, e o histórico xAPI é uma capacidade nova (D-67).
- O valor de `percent` e de `score` do contrib deve ser calculado a partir da mesma escala da F2 (0 a 100), para não haver dois critérios de nota.

## 5. Limites desta análise

- Não li a camada de formulários, as views nem os plugins de atividade em profundidade. Os plugins `ActivityAnswer` existentes foram listados, não lidos por completo.
- Não testei o comportamento do módulo; a leitura é só do código.
- Não verifiquei se há versão mais nova do contrib com mudanças nessas entidades.
