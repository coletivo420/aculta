# GanjaLab420 — Minha Jornada de Aprendizagem
**Trilha visual:** G420-UX 0.5.0-dev.1 · **Data:** 2026-10-22 · **Estado:** planejamento, não implementado.
**Ver:** [GanjaLab420](GANJALAB420-LEARNING-UX.md) · [Sala](GANJALAB420-LEARNING-ROOM-VISUAL.md) · [Atividades](GANJALAB420-ACTIVITIES-ASSESSMENTS-VISUAL.md) · [Roadmap LMS](../modules/ACULTA-LMS-ROADMAP.md).

## Objetivo
Responder ao estudante: **Onde estou? O que falta? O que já concluí?**. Preferir indicadores verificáveis, próximos passos e feedback motivador sem ranking entre estudantes. Progresso, aulas, tentativas, matrículas, notas, prazos e certificados vêm do **Drupal LMS/Group e integrações existentes**, nunca de contadores derivados apenas do frontend. O nome técnico segue `aculta_lms`; a interface chama-se GanjaLab420.

## Estrutura de telas
1. **Minha Jornada** na área de aprendizagem: resumo pessoal, “Continuar”, próximos passos e meus cursos.
2. **Jornada do curso**: percurso curricular em módulos e aulas com estados reais, pendências e continuidade.
3. **Atividades e resultados**: status de envio, correção, feedback e prazos do estudante; link às telas nativas.
4. **Conquistas e certificados**: somente certificados emitidos pelo provider existente; conquistas opcionais consumindo `aculta_game`, sem prêmio ou score paralelo.
5. **Turma**: recorte contextual para prazos e avisos próprios da turma, sem misturar matrículas.

## Wireframe mobile — Jornada do curso
```text
‹ Meus cursos                 GanjaLab420
Minha jornada · Redução de danos
[Continuar aula →]  (se disponível)
Progresso do curso: percentual real OU apenas estados textuais
Módulo 1 — Fundamentos
  ✓ Introdução                Concluída
  ○ História e contexto       Continuar
  • Reflexão                  Pendente
Módulo 2 — Práticas
  🔒 Aula indisponível        (sem revelar conteúdo restrito)
Próximas atividades
  [Questionário]   prazo real no fuso da conta
Conquistas e certificados (se existentes)
```

## Wireframe desktop
Coluna principal (aprox. 65–75%) apresenta a trilha curricular, linha do tempo ou lista acessível de aulas; coluna secundária (25–35%) apresenta próximas ações, pendências, avisos e certificados. Layout colapsa para uma coluna no mobile; não criar dashboard de métricas densas ou gráfico de percentual sem valores verdadeiros.

## Estados e semântica
- **Não iniciado / Em andamento / Concluído** somente quando reconhecidos pelo provider. **Enviado / Aguardando avaliação / Avaliado** são estados de entrega distintos dos estados de conclusão da aula.
- “Próximo passo” escolhe apenas aula realmente acessível e vinculada ao curso/turma atual; nunca sinalizar uma aula como concluída por clique de navegação.
- Percentual de progresso apenas com numerador/denominador oficial; quando a métrica não estiver disponível, usar “X atividades concluídas” somente se contagem confiável, ou não mostrar indicador.
- Pré-requisitos e conteúdo bloqueado não revelam título/arquivo se a política de acesso não permitir.
- Metadados de prazo refletem contexto da turma e fuso horário da conta; aviso vencido apenas conforme contrato real.
- Certificado só aparece como emitido após registro e validação; PDF/QR ligados à fonte canônica. Não criar emissão no frontend.
- Um usuário pode ser docente em outro curso; a Jornada mostra estritamente matrículas próprias no contexto de estudante.

## Componentes de interface candidatos (ACULTA420)
`learning-journey-header`, `learning-next-step-card`, `learning-course-progress`, `learning-module-progress`, `learning-milestone-list`, `learning-pending-activity`, `learning-certificate-preview`, `learning-journey-empty-state`. Nomes apenas propostos, preferir reutilizar SDC/Bootstrap existentes. Twig não realiza queries de dados. Não associar cor isoladamente a conclusão; usar texto e ícones consistentes.

## Acessibilidade, privacidade e testes
320/360/390/768/1024/1280/1440px, modo claro/escuro, teclado, zoom 200–400%, leitor de tela, reduced-motion e sem JS. Progresso sem depender de animação; foco depois de navegação AJAX; empty states para estudante sem cursos/sem histórico. Acesso e cache por usuário, curso e Group; estudante A nunca enxerga nota, tentativa, progresso ou certificado de B. Matrícula removida revalida acesso a materiais. Não usar URLs públicas para arquivos privados.

## Próximos passos versionados G420-UX 0.5
| Passo | Entrega | Gate |
| --- | --- | --- |
| 0.5-A | inventário real de progresso/status LMS e escopos Group | contratos e cache |
| 0.5-B | wireframes de Jornada pessoal/curso e estados vazios | 320px, dark/light |
| 0.5-C | componentes SDC e presenters sobre APIs existentes | sem indicadores fictícios |
| 0.5-D | próximas tarefas, feedback, certificados e avisos contextuais | permissões por estudante/turma |
| 0.5-E | regressão A/B, acessibilidade, domínio e documentação | evidências Runtime |

Este documento **não instala código, não altera rotas e não declara progresso ou certificados já disponíveis**.
