# GanjaLab420 — Minhas Turmas e Comunidade
**Versão:** G420-UX 0.6.0-dev.1 · **Data:** 2026-10-22 · **Estado:** planejamento visual, sem implementação.
**Referências:** [Minha Jornada](GANJALAB420-LEARNING-JOURNEY-VISUAL.md) · [Sala de Aprendizagem](GANJALAB420-LEARNING-ROOM-VISUAL.md) · [Roadmap LMS](../modules/ACULTA-LMS-ROADMAP.md).

## Visão
A turma é o ambiente comunitário **contextual do curso**; não é um segundo fórum público, rede social paralela ou nova entidade de membership. O estudante acompanha avisos, atividades coletivas, conversas e participantes que a política de visibilidade permitir. O docente edita e modera por GanjaLab; o estudante participa via GanjaLab420. Dados e acesso provêm de Drupal LMS/Group, `aculta_forum_lms`, `aculta_messenger` e `aculta_user`. **O módulo interno continua `aculta_lms`.**

## Destinos
1. **Minhas Turmas:** turmas em que a pessoa possui membership vigente e acesso de estudante; cartão com nome do curso, turma, próxima ação e avisos somente se os providers oferecerem.
2. **Visão da Turma:** identificação de curso/turma, contexto e docentes quando autorizado; abas/painéis **Mural / Discussões / Atividades / Participantes** condicionais.
3. **Mural e Avisos:** comunicados oficiais do contexto Group; não duplicar Messenger nem improvisar feed sem provider.
4. **Discussões:** tópicos de curso/turma e conversas de aula no `aculta_forum_lms` usando layout compartilhado do Fórum; não criar tópicos públicos automaticamente.
5. **Participantes:** apresentar somente contas e campos cujo acesso Group, política de privacidade e consentimento permitam. Cartões de pessoa padronizados do `aculta_user`, não criar cartões específicos no LMS.
6. **Atividades coletivas:** links aos prazos e formulários LMS oficiais; não criar submissão nem notas da turma no frontend.

## Navegação mobile
```text
GanjaLab420 / Minha aprendizagem / Minhas turmas
[Curso: Redução de danos] [Turma Outubro]
[Entrar na turma →]

DENTRO DA TURMA
‹ Minhas turmas
Turma Outubro · Redução de danos
[Mural] [Discussões] [Atividades] [Participantes]
AVISOS
[Comunicado do educador] data real
DISCUSSÕES CONTEXTUAIS
[Tópico da turma] autor autorizado · últimas respostas
ATIVIDADES
[Pendente · prazo da turma] → abrir no LMS
```

**Mobile 320–767px:** título curto e identificação persistente da turma; abas com scroll acessível ou menu compacto sem ocultar conteúdo; um painel por vez, cartões legíveis, ações em tamanho de toque. **Desktop:** coluna principal mural/conversas e sidebar contextual com próximos prazos e atalhos, sem replicar feed global. Tablet adapta os mesmos blocos.

## Componentes visuais candidatos ACULTA420
`learning-class-card`, `learning-class-shell`, `learning-class-notice`, `learning-class-discussion-list`, `learning-class-activity-card`, `learning-class-members-preview`, `learning-class-empty-state`. Priorizar SDC/Bootstrap existentes, especialmente os cartões do `aculta_user` e a apresentação do Fórum; Twig não consulta Entity API. Modo escuro muda tokens, não estrutura.

## Isolamento e privacidade
- A lista **Minhas Turmas** é filtrada pela membership do usuário atual e seu acesso efetivo, não por simples permissão `access content`.
- Turma A e B de um curso não compartilham mural, arquivos privados, avaliações ou participantes por padrão.
- Lista de colegas e perfis exigem política de visibilidade; não expor e-mail, nome legal, status sensível, notas ou matrícula privada.
- Moderadores/docentes acessam área de trabalho **GanjaLab** conforme sua função; isso não amplia o acesso da visão pessoal GanjaLab420.
- Dados de tópicos/avisos/notificações respeitam entidade, Group, cache contexts/tags e Domain purpose.
- Mensagem privada, quando oferecida, usa `aculta_messenger`; Fórum contextual não substitui inbox.

## Estados
Sem turmas (direcionar para Meus Cursos ou catálogo autorizado); matrícula pendente/encerrada; turma sem avisos; fórum vazio; discussão fechada/moderada; aviso não lido apenas quando estado real existir; turma sem lista pública de participantes; prazo vencido; acesso revogado. Não inventar contador online nem mostrar “lido”/“não lido” sem dados nativos.

## Conexão com Jornada e Sala
O estudante encontra prazos e discussões no contexto do curso/turma. Discussão de uma **aula** aparece na própria Sala e é referenciada no espaço da turma quando o modelo de permissões/visibilidade permitir; não copiar tópicos. “Voltar à aula” preserva o contexto. Avaliações individuais e feedback pessoal ficam em suas páginas privadas, nunca no Mural.

## Planejamento por etapas
| Etapa | Entrega | Gate |
| --- | --- | --- |
| 0.6-A | inventariar Group memberships, rotas de turma, Fórum, Messenger, política de participantes | contratos e acesso |
| 0.6-B | wireframes mobile e desktop: turmas, mural, discussões, avisos, membros | visual/accessibility |
| 0.6-C | componentes SDC e navegação contextual sobre providers existentes | sem feed paralelo |
| 0.6-D | integrações Fórum contextual, Messenger, User e prazos LMS | isolamento Group A/B |
| 0.6-E | homologação 320–1440px, teclado, WCAG 2.2 AA, privacidade, no-JS, dark/light | Runtime + testes A/B |

**Esta fase é somente documentação. Não criar rotas, alterar o nome de `aculta_lms`, reestruturar Group ou substituir o Fórum.**

## Refinamento de wireframes 0.6-B — Minhas Turmas e Comunidade

**Minhas turmas (mobile):** cabeçalho GanjaLab420, título e cartões de turmas realmente acessíveis com curso de origem, identificação da turma e CTA “Entrar na turma”. Avisos recentes ou próximos prazos apenas se provider canônico devolver dados reais. Sem contagem fictícia de membros online.

**Dentro da turma:** contexto persistente de curso/turma e navegação por quatro regiões condicionais **Mural / Discussões / Atividades / Participantes**. No mobile, abas acessíveis com rolagem controlada ou menu compacto; no desktop, área central de conteúdo e painel contextual de prazos quando disponível. Mudança de abas não mistura turmas no histórico AJAX, e deve funcionar sem JS.

- **Mural:** somente comunicados e avisos oficiais com data e autoria autorizadas, provenientes de Messenger ou provider existente; não criar segundo feed.
- **Discussões:** listagem e tópicos do Fórum contextual, usando cartões/estrutura do `aculta_forum_lms`, sem criação automática de tópico público.
- **Atividades:** lista das tarefas da turma provenientes do LMS; notas e respostas permanecem privadas.
- **Participantes:** renderização pelos cartões `aculta_user` e políticas de visibilidade de Group/privacidade; sem mostrar e-mail nem status sensível.

**Estados vazios e restrições:** turma sem discussões, não autorizada a mostrar participantes, grupo encerrado, membership revogada, mural sem avisos e carregamento AJAX falho devem oferecer mensagens claras e navegação segura. Uma pessoa matriculada em dois grupos não deve ver mural, anexos, prazos exclusivos ou identidades da outra turma por cache. Geometria igual em light/dark, WCAG 2.2 AA, 320–1440px. Wireframes exemplificativos não representam funcionalidades prontas.

**Próxima experiência:** [Conquistas, Certificados e Histórico](GANJALAB420-ACHIEVEMENTS-CERTIFICATES-VISUAL.md).
