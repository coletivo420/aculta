# Revisão LGPD: série diária do progresso individual do aluno

Data: 21/10/2026. Status: **não aprovado** para armazenamento em série por aluno. Aprovado apenas o agregado por turma
e situação (ver conclusão). Decisão final: responsável (controlador).

Referências: `docs/compliance/LGPD-INVENTARIO.md` (linha `aculta_lms`, pendências 1, 4 e 5), ADR-0005 do
`aculta_statistics` (seleção de período e exceção de dado pessoal), roadmap do `aculta_lms`.

Limite desta revisão: a política de privacidade publicada não está no repositório. A análise usa o inventário, que
registra a base da finalidade "cursos e acompanhamento". Se a política publicada disser outra coisa, a revisão deve
ser refeita.

## 1. O que seria tratado

Fonte: entidade `lms_course_status` do módulo contrib `drupal/lms` (uma linha por aluno e curso, com `current`).

| Campo | Natureza | Dado pessoal |
|---|---|---|
| `uid` (aluno) | Identificador | Sim, direto |
| `status` (novo, em andamento, aguardando avaliação, aprovado, reprovado, expirado) | Situação acadêmica | Sim |
| `score` (pontuação) | Avaliação de desempenho | Sim |
| `started`, `last_activity_ts`, `finished` | Datas de atividade | Sim |
| `lesson_status` (por lição) | Progresso detalhado | Sim |

Série proposta: uma linha por aluno, curso e dia, com a situação ou a pontuação naquele dia.

## 2. Análise por princípio (LGPD, art. 6º)

- **Finalidade (I) e adequação (II).** A finalidade declarada no inventário é "cursos e acompanhamento", com base em
  execução de contrato (art. 7º, V). Uma série longitudinal por aluno, para análise em painel, não está nessa
  finalidade. Usar o dado para outra finalidade exige base própria ou compatibilidade demonstrada, o que não se
  sustenta aqui.
- **Necessidade (III).** A série por aluno não é necessária para a finalidade de análise. O painel da turma precisa de
  contagens por situação, e isso já é atendido pela série por turma (`lms.students.by_status`). Para o próprio aluno
  ver o seu progresso, basta o estado atual, que já é exibido.
- **Livre acesso, qualidade e transparência (IV, V, VI).** O titular deve saber que a série existe e poder consultá-la.
  Isso não está declarado hoje.
- **Segurança (VII) e prevenção (VIII).** Uma série por aluno concentra mais dado do que uma foto atual. Vazamento ou
  acesso indevido expõe a trajetória de cada pessoa, não só um número.
- **Não discriminação (IX).** Uma série por aluno, lida em conjunto, serve para ranquear ou classificar pessoas. Isso
  abre risco de decisão automatizada (art. 20) e de tratamento discriminatório, sobretudo em turmas pequenas.
- **Responsabilização (X).** Exigiria registro de finalidade, prazo e acesso por série, além de atualização do inventário.

## 3. Base legal

- **Execução de contrato (art. 7º, V):** cobre o acompanhamento necessário ao curso. Não cobre análise longitudinal por
  aluno sem relação com a execução do curso.
- **Consentimento (art. 7º, I e art. 8º):** exigiria escolha específica para a série, livre, informada e revogável, com
  tela própria e remoção dos dados ao revogar. Possível, mas exige interface e processo que não existem.
- **Legítimo interesse (art. 7º, IX):** exigiria teste de balanceamento. Acompanhamento individual de desempenho de
  aprendizes tende a pesar mais contra o titular do que a favor do interesse da associação. **Não recomendado.**
- **Dado sensível (art. 11):** a pontuação em si não é sensível. Atenção: conteúdos sobre políticas de drogas podem
  revelar convicções ou dados de saúde nas respostas. A série não deve incluir respostas, só situação e pontuação.

## 4. Retenção

O inventário declara retenção "pelo período do curso". A série por aluno usaria o prazo de métrica do
`aculta_statistics` (90 dias por padrão), o que mudaria a retenção declarada sem decisão. Retenção só poderia ser
maior se fosse justificada e declarada. Não há base para isso hoje.

## 5. Direitos do titular (art. 18)

Se a série existir, ela entra em: acesso (exportação), correção, eliminação (ao excluir a conta ou ao revogar o
consentimento), e informação sobre compartilhamento. Hoje o processo de atendimento aos direitos está pendente
(inventário, pendência 5). Sem esse processo, a série não pode existir.

## 6. Pseudonimização

Trocar o `uid` por um código (HMAC) não torna o dado anônimo: o controlador ainda consegue reidentificar. O resultado
continua sendo dado pessoal (art. 13, §4º). Não conta como medida que dispensa a análise acima.

## 7. Conclusão

1. **Não aprovado:** série diária por aluno armazenada no `aculta_statistics`.
2. **Aprovado:** série diária por turma e situação (`lms.students.by_status`), sem identificação de aluno. Exibição
   pública com supressão de célula abaixo de 5 alunos, conforme `CellSuppression`. Esse agregado segue a finalidade de
   acompanhamento da turma e não exige nova base.
3. **Progresso do próprio aluno:** continua sendo o estado atual, lido da fonte no momento da consulta, sem armazenar
   histórico. Se o aluno quiser ver o próprio histórico, isso pode ser calculado a partir de datas que já existem na
   fonte, sem gravar nada novo.

## 8. Opções para decisão do responsável

| Opção | Base | Exige | Recomendação |
|---|---|---|---|
| A. Só agregado por turma (estado atual do aluno sem série) | Execução de contrato | Nada novo | **Recomendada** |
| B. Série por aluno com consentimento opcional e revogável | Consentimento (art. 7º, I) | Tela de consentimento, revogação, exportação, texto na política, retenção declarada | Só se houver demanda pedagógica comprovada |
| C. Série por aluno sem consentimento, com retenção do curso | Execução de contrato | Justificativa de necessidade do DPO, atualização do inventário e da política, acesso restrito ao docente | Não recomendada |

## 9. Ações se a opção B for escolhida

- Atualizar `LGPD-INVENTARIO.md` (linha `aculta_lms` e linha `aculta_statistics`) e a política de privacidade.
- Definir a retenção da série por escrito e configurar o prazo de forma idêntica ao curso.
- Implementar exportação e eliminação da série no fluxo de conta e de consentimento.
- Restringir a leitura por aluno ao próprio dado e ao docente da turma, com permissão `grade students`.
- Não publicar série por aluno em nenhuma tela pública.
