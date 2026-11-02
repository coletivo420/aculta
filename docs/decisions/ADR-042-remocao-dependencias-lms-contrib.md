# ADR-042 — Remoção das dependências do Drupal LMS contrib no aculta_lms

- **Status:** Proposta, aguardando aprovação do plano por fase. Nenhuma remoção de código, de configuração ou de dado está autorizada por este ADR até a aprovação de cada fase.
- **Data:** 2026-11-02
- **Relaciona-se com:** ADR-041 (xAPI 2.0, LRS próprio), `docs/modules/LMS-CONTRIB-DEPENDENCY-INVENTORY.md`, `docs/modules/ANALISE-LMS-CONTRIB-1-2-4.md`, `docs/modules/OPIGNO-ACULTA-INDEPENDENCE-PLAN.md`.

## Contexto

O `aculta_lms` depende do Drupal LMS contrib (`drupal/lms` 1.2.4) e de submódulos e vizinhos dele. Medição em 2026-11-02:

- 60 arquivos PHP do código ACULTA importam `Drupal\lms`: 22 de produção e 38 de teste.
- Módulos com importação: `aculta_lms` (41 em `src`, 23 em `tests`), `aculta_lms_h5p` (7 em `src`, 2 em `tests`), `aculta_forum_lms` (6 e 5), `aculta_lms_resources` (5 e 5), `aculta_messenger` (1 e 2) e `aculta_lms_agenda` (1 em `tests`).
- `aculta_lms` declara `lms`, `lms_classes`, `lms_answer_plugins`, `lms_answer_comments`, `lms_file_upload`, `webform_score`, `entity_print` e `group`.
- Lições, atividades, respostas, tentativas, status e notas vivem em entidades do contrib: `lms_lesson`, `lms_activity`, `lms_answer`, `lms_lesson_status` e `lms_course_status`. Cursos e turmas vivem no Group.

Remover só o `composer.json` não torna o ACULTA independente. Ele também não pode ser feito de uma vez: depende de dados, de configuração ativa e de views e rotas que assumem IDs e formatos do contrib.

## Decisões do responsável (2026-11-02)

1. **Escopo:** plano em fases, com este ADR aprovado antes de qualquer código de remoção.
2. **Dados do Runtime:** os dados de teste (cursos, respostas e notas) podem ser descartados. A exclusão só acontece na fase própria, com ordem explícita no momento da execução, conforme as regras do projeto para destruição de dados.
3. **Vizinhos:** remover só o que é do `lms` (o módulo e os submódulos `lms_*`). `webform_score` e `entity_print` não entram nesta sequência; ficam para revisão própria.

## Decisão de arquitetura

O `aculta_lms` passa a ser dono do domínio educacional, por contratos ACULTA:

- Entidades próprias de aula, atividade, resposta, progresso de aula e progresso de curso, com nomes e campos do ACULTA. Nenhuma entidade do contrib é reutilizada depois da fase de corte.
- Plugins de resposta (`ActivityAnswer`) implementados no `aculta_lms`, sem o `lms_answer_plugins`.
- Comentários de resposta e envio de arquivo ficam com o `aculta_lms`, sem `lms_answer_comments` nem `lms_file_upload`.
- A ligação com Group permanece onde já está: cursos e turmas. Esta decisão não muda a fronteira com o Group.
- A xAPI do ADR-041 usa a referência opaca ao progresso. Quando o progresso próprio existir, a referência passa a apontar para ele, sem reescrever as declarações.

## Fases propostas

Cada fase é um commit ou um conjunto de commits, com gate próprio e rollback ensaiado no Runtime antes de qualquer corte.

| Fase | Entrega | Gate de saída |
| --- | --- | --- |
| P0 | Inventário completo por arquivo e por entidade, com dono de cada item. Lista de contratos a criar. | Inventário aprovado; nenhum código alterado. |
| P1 | Contratos ACULTA de aula, atividade, resposta e progresso, com adaptador para o contrib. Testes Unit. | Testes passam com o adaptador do contrib; nenhum comportamento muda. |
| P2 | Entidades próprias instaladas em paralelo ao contrib, sem leitura de produção. | Kernel com `aculta_simpletest`; instalação no Runtime com snapshot por hash. |
| P3 | Plugins de resposta no `aculta_lms`, substituindo `lms_answer_plugins`. | Testes de cada plugin; Olivero e ACULTA420 nos testes de Portal e Revista. |
| P4 | Migração ensaiada dos dados de teste para as entidades próprias, com rollback. | Ensaio no Runtime com snapshot; restauração verificada. |
| P5 | Corte: remoção das importações de `Drupal\lms`, dos módulos `lms*` e do Composer. | Grep sem ocorrências em `web/modules/custom`; gates do Portal; migração já executada. |
| P6 | Remoção dos dados de teste do contrib, com ordem explícita. | Ordem explícita do responsável no momento da execução. |

## Riscos

- Views, rotas, permissões e o tema assumem IDs e formatos do contrib. O corte precisa de inventário de views e de rotas (P0).
- Os 38 testes que importam `Drupal\lms` precisam ser reescritos junto com cada fase, não depois.
- Sem o módulo `lms`, o `aculta_lms_h5p` também perde a dependência declarada. O submódulo precisa de avaliação própria em P0.
- Migração de dados só depois de ensaio. Dados de teste descartados não voltam.
- Não há Runtime disponível nesta sessão para P2 em diante. Essas fases ficam DEFERRED até o ambiente.

## Pendências

- Aprovar o plano por fase antes de P1.
- Confirmar a lista de módulos do contrib que serão removidos, além de `lms` e `lms_*`.
- Definir quem valida as views e rotas em P0.
