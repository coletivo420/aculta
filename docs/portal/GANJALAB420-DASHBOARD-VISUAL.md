# GanjaLab420 — Dashboard, cursos, atividades e conquistas
**Versão de design:** G420-UX 0.2.0-dev.1 · **Data:** 2026-10-22 · **Estado:** planejamento, não implementado.
**Referências:** [GanjaLab420 — produto](GANJALAB420-LEARNING-UX.md) · [Sala de aprendizagem](GANJALAB420-LEARNING-ROOM-VISUAL.md) · [Branding](GANJALAB-BRANDING.md).

## Princípios
Interface do estudante **mobile-first**, não painel administrativo. O primeiro trabalho é continuar uma aula autorizada. Layout compartilhado com ACULTA420 (tokens, tipografia, light/dark), distinguindo GanjaLab420 (aprender) de GanjaLab (criar). **Módulo técnico continua `aculta_lms`**; a proposta de renomeação interna para `aculta_ganjalab_lms` ou `aculta_escola` está descartada e não deve ser executada.

## Navegação
Quatro destinos primários da experiência: **Início**, **Meus cursos**, **Atividades**, **Conquistas**. Turmas, recursos, discussões e mensagens são destinos contextuais, sem abas globais redundantes. Barra inferior somente quando não houver duplicidade com shell de conta. Descoberta de cursos públicos pelo catálogo, separada da aprendizagem matriculada.

## Dashboard mobile — ordem dos componentes
1. Cabeçalho compacto GanjaLab420, contexto “Minha aprendizagem”, acesso a conta/avisos existentes.
2. Saudação simples sem exigir dados pessoais; não empurrar o CTA para baixo.
3. Cartão principal “Continuar de onde parou”: curso, aula, progresso verificado, CTA “Continuar aula” — somente com fonte e acesso reais.
4. Meus cursos em cartões com capa (fallback textual), estado acessível, título, próxima ação.
5. Próximas atividades com prazo real e feedback; nenhuma tarefa fictícia ou concluída indevidamente.
6. Turmas, certificados e histórico como cartões contextuais quando providers existirem.
7. Sem matrícula: mensagem acolhedora e navegação ao catálogo; sem progresso: esconder números, não presumir 0%.

```text
GanjaLab420                       [Avisos] [Conta]
Minha aprendizagem
Continue evoluindo
┌───────────────────────────────────────────┐
│ CONTINUE DE ONDE PAROU                    │
│ Curso / próxima aula permitida            │
│ progresso real (se existir)               │
│ [Continuar aula →]                        │
└───────────────────────────────────────────┘
Meus cursos                            Ver todos →
[cartão de curso] [cartão de curso]
Próximas atividades
[pendência real] [prazo real]
```

## Dashboard desktop
Área central com cabeçalho reduzido; destaque “Continuar”, lista/grade de cursos e painel discreto de atividades pendentes. Não reproduzir cartões excessivos nem dezenas de KPIs. Conteúdo de curso e turma deve considerar Group e permissões, nunca depender do CSS para esconder dados.

## Sala e páginas secundárias
Sala como principal experiência de estudo: leitor central e índice recolhível, progresso e conclusão oficiais, anexos PDF, H5P, vídeo, discussões autorizadas e navegação anterior/próxima (não confundir navegar com concluir). Ver [contrato de Sala](GANJALAB420-LEARNING-ROOM-VISUAL.md). Páginas: Meu curso (capa, descrição e currículo), Atividades (instruções/envio/status/feedback), Conquistas (certificados oficiais e integração opcional game). Usar formulários e dados nativos LMS.

## Componentes ACULTA420 sugeridos
`learning-dashboard`, `continue-learning-card`, `enrolled-course-card`, `learning-activity-card`, `learning-achievement-card`, `student-empty-state`; nomes candidatos, não implementados. SDC usa dados/render arrays permitidos, sem queries, access ou armazenamento. Indicadores verdadeiros, não percentuais produzidos em JS.

## Critérios de aceite
WCAG 2.2 AA; teclado, leitor de tela, 320/360/390/768/1024/1280/1440px; zoom/reflow, safe area, erro/offline, no-JS, Domain account, estudantes A/B e Groups A/B, dark/light. Evitar vazamento de nota, inscrição, conteúdo privado ou presença de turma; contextos/cache por usuário e Group. **Esta documentação não implementa recursos nem altera `aculta_lms`.**
