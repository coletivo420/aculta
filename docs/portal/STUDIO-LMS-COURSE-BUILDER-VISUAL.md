# ACULTA Studio — LMS Course Builder: contrato visual e execução

**Versão de design:** CB-UX 0.3.0-dev.9 · **Data:** 2026-11-04 · **Estado:** estrutura em cartões responsivos, CKEditor 5 filtrado, reordenação por arraste com controles acessíveis de fallback, prévia privada por aula, lista de materiais existentes e edição contextual de leituras/galerias/vídeos/documentos implementados; homologação visual segue pendente.

**Referências:** [Studio Visual](STUDIO-VISUAL-CONCEPT.md) · [Fluxos de edição](STUDIO-EDITOR-FLOWS.md) · [Roadmap Studio](STUDIO-MAGAZINE-ROADMAP.md) · [Roadmap funcional LMS](../modules/ACULTA-LMS-ROADMAP.md) · [LMS/Group](../modules/LMS-GROUP.md) · [Contratos LMS](LMS-INTEGRATION-CONTRACTS.md).

**Ownership atualizado:** este Course Builder pertence ao workspace **Ganjalab Educador**, separado do **Ganjalab Estudante** e do **Marley**. Marley cobre Revista, moderação do Fórum e Galerias; a experiência do estudante segue o plano Ganjalab. O workspace usa a rota existente `courses:/ganjalab/educador`; a criação de curso fica em `courses:/ganjalab/educador/cursos/novo`. O estudante permanece em `courses:/ganjalab`. Ver [fronteiras das experiências Studio](ACULTA-STUDIO-EXPERIENCE-BOUNDARIES.md).

## Objetivo e público

Construir a autoria de cursos do educador sem usar as telas administrativas do Drupal no fluxo cotidiano. O workspace Ganjalab Educador já oferece criação de curso pelo Form API de Group e, desde 2026-11-03, estrutura inicial em `/ganjalab/educador/cursos/{course}/estrutura`, com criação/edição de aulas e atividades pelo `aculta_lms`. Padrão **Meus cursos**: apenas cursos que a pessoa criou ou efetivamente gerencia, conforme permissões Group, sem confundir “posso ver”, matrícula de estudante e responsabilidade de gestão.

**Course Builder é um workspace e NÃO um segundo LMS.** Group mantém cursos, turmas, memberships e relações; `aculta_lms` é dono das entidades ACULTA de aula, atividade, resposta, nota e estado; `aculta_studio` organiza rotas, ações e presenters; ACULTA420 detém shell, tokens e SDC. Reutilizar Image, Gallery, PDF Viewer, H5P, Fórum contextual, Messenger e User conforme disponibilidade real. Nunca duplicar curso, turma, mídia, gradebook, workflow ou tabela de ordenação por mera conveniência visual.

## Estrutura pedagógica apresentada

Árvore visual preferida: **Curso → Módulo → Item pedagógico (aula/atividade/recurso)**. Máximo de três níveis na navegação principal, para manter usabilidade mobile; não presumir que módulo é uma entidade ou que PDF, vídeo, H5P, discussão são todos tipos de aula. Mapear previamente relações nativas, tipos existentes, posição/ordem, reutilização de conteúdo, pré-requisitos e regras. Onde a árvore conceitual não mapear às APIs existentes, documentar lacuna e propor adapter sem persistência paralela; não criar schema por conta deste design.

**Conceitos separados:** currículo e conteúdo pertencem ao curso; inscrição, prazos, progressos, resultados, discussões de turma e permissões específicas pertencem à turma/Group. Alterações em um currículo compartilhado podem impactar turmas ativas; exibir aviso e impedir gravações destrutivas sem validação do provider.

## Telas

| Tela | Prioridade | Ações e informação |
| --- | --- | --- |
| Meus cursos | inicial | criar curso autorizado, retomar edição, status real, cursos próprios/geridos |
| Construtor — Estrutura | central | árvore, expandir módulos, selecionar item, adicionar conforme catálogo autorizado |
| Editor de aula/atividade | central | formulário nativo, CKEditor 5 onde cabível, materiais, salvar, erros, estado |
| Criar item | contexto de módulo | só tipos suportados: aula, atividade, recurso; vídeo/PDF/H5P/discussão quando realmente disponíveis |
| Reorganizar | contexto da árvore | arraste dentro de cada lista no desktop; controles mover acima/abaixo para teclado/sem JS; mover entre módulos não se aplica enquanto o LMS não tiver um conceito persistido de módulo |
| Informações | secundária | nome, descrição, capa e campos reais do curso |
| Materiais | secundária | mídia/referências e PDF autorizado, via kernel |
| Regras | secundária | disponibilidade, conclusão, pré-requisitos reais |
| Prévia do estudante | contextual | experiência renderizada pelo LMS, sem exposição de rascunhos ou dados de estudantes |
| Publicação | contextual | estado e transições realmente autorizadas |
| Turmas | link contextual separado | participantes, prazos, acompanhamento; nunca poluir árvore do curso |

Ações inacessíveis devem sumir ou ficar em explicação contextual conforme design de acessibilidade; **todas** as rotas e operações revalidam acesso no servidor.

## Wireframe desktop (>= 1024px, referência adaptável)

```text
‹ Meus cursos    Curso: Introdução à redução de danos     [Prévia] [⋯]
┌──────────────────────────────┬──────────────────────────────────────┐
│ Estrutura do curso           │ Aula: Introdução                     │
│ + Adicionar                 │ Título [__________________________] │
│ ▾ Módulo 1 — Fundamentos    │ Toolbar CKEditor 5                  │
│    ▸ Aula: Introdução ◀     │ Conteúdo / Form API                 │
│    ▸ Vídeo: Contexto        │ Materiais e configurações           │
│    ▸ Avaliação: Reflexão    │ [Salvar aula]                       │
│ ▸ Módulo 2 — Práticas       │                                      │
│ ▸ Módulo 3 — Comunidade     │                                      │
└──────────────────────────────┴──────────────────────────────────────┘
```

Árvore recolhível à esquerda (~30–40%, ajustável), formulário à direita. Selecionar item não deve apagar estado não salvo. AJAX progressivo somente para troca de regiões, mantendo URL navegável, `pushState`/`popstate`, foco, `aria-live`, back/forward e fallback de rota normal. Não presumir edição inline se a ação existente só oferece um Form API nativo em rota própria; incorporar o render array via contrato seguro, sem reproduzir campos manualmente.

## Wireframe mobile (320–767px)

```text
‹ Meus cursos                         ⋯
Construtor de curso
Introdução à redução de danos
                              [+ Adicionar]
▾ Módulo 1 — Fundamentos        [⋯]
   Aula: Introdução               >
   Vídeo: História e contexto      >
   Atividade: Reflexão             >
   + Adicionar ao módulo
▸ Módulo 2 — Práticas             [⋯]
▸ Módulo 3 — Comunidade           [⋯]
```

**Uma tarefa por tela:** Estrutura → seleção de item → editor de item → retornar à árvore na mesma posição. Editor mobile em uma coluna com toolbar CKEditor 5 responsiva, configurações em disclosures e opção de salvar visível sem cobrir teclado/safe-area. Voltar preserva foco, expansão e posição de rolagem; alterações não salvas exigem confirmação antes de abandonar. Sem duas colunas comprimidas e sem drag-only. Diferenciar erro de rede, alterações locais e confirmação real de persistência. Proibir afirmação de autosave sem backend transacional que a suporte.

## Catálogo de inserção contextual

O botão “+ Adicionar” informa o local de inserção e consulta tipos e ações autorizados do dono. Possíveis apresentações: nova aula textual, atividade/questionário, documento PDF, vídeo, H5P e discussão contextual. **Não equivale a seis novos bundles**: podem ser widgets, recursos ou integrações de uma atividade/aula existente. Ocultar itens indisponíveis; quando habilitados, usar Form API do LMS e respectivas políticas de Group, Media, Image, PDF e Forum.

## Ordenação e segurança editorial

Desktop: arraste opcional restrito à lista de aulas ou à lista de atividades da mesma aula. Mobile, leitor de tela e teclado: comandos **Mover acima** e **Mover abaixo**; a lista anuncia alteração e mantém alternativa sem JS. O endpoint confere acesso, ancestralidade e permutação exata, então serializa e grava pesos por lock/transação. Mover entre módulos não é oferecido porque módulo não é entidade/escopo persistido neste LMS. Em curso com turma ativa, a ordem compartilhada afeta as turmas; não remover notas, progresso, entregas, referências nem avaliações. Cada operação grava sua própria lista.

## Componentes candidatos ACULTA420

`course-builder-shell`, `course-outline`, `course-module`, `course-outline-item`, `course-item-editor-shell`, `course-add-menu`, `course-move-dialog`, `course-save-state`, `course-impact-warning`, `course-student-preview`. São **nomes propostos**, ainda não implementados. Props: dados escalares semanticamente seguros e slots com render arrays; tema jamais consulta storage, Group ou EntityAccess. Primeiro avaliar Bootstrap 5 (offcanvas, accordion, dropdown, grid) e SDC reutilizáveis existentes. Light/dark mantêm geometria e navegação idênticas; usar tokens semânticos existentes.

## Estados e acessibilidade

- Curso vazio: “Criar primeiro módulo”; módulo vazio: “Adicionar aula ou atividade”; sem tipos autorizados: explicar o limite sem expor dados restritos.
- Indicador real de salvamento, erros ligados ao campo, conflito de edição/versão, perda de conexão, acesso revogado, conteúdo indisponível e ação destrutiva.
- Alvos preferencialmente >=44×44px, inputs >=16px no mobile, rolagem sem overflow, CSS reflow/zoom 200–400%, WCAG 2.2 AA, labels, contraste, foco, teclado, ARIA e reduced-motion; não depender de cor, hover ou arrastar.
- Matriz: 320/360/390/768/1024/1280/1440px, light/dark, AJAX/no-JS, role educador/gestor/aluno, domínio account/courses, Group A/B, cursos próprios/terceiros, turma ativa, revisões, erros e reordenação por teclado.
- Testes automatizados e snapshots em `tests/browser/aculta-studio/` quando houver implementação; aceitação final exige evidência Runtime e smoke de não vazamento.

## Roadmap visual CB-UX (não substitui fases funcionais F0–F17)

| Fase | Entrega | Dependência/gate |
| --- | --- | --- |
| **0.3.0** | inventário de Graph LMS/Group, relações/ordem, Form API, papéis, providers, layout e contratos | provar fonte canônica e escopo “Meus” |
| **0.3.1** | shell mobile-first, cartões de aula/atividade e estrutura reflow no mobile | cartões/resumo/estados vazios implementados; navegação contextual e validação visual pendentes |
| **0.3.2** | navegação hierárquica, seleção, expansão, histórico, estados vazios | isolamento por curso/Group |
| **0.3.3** | criação contextual de aulas e tipos de atividade suportados em entidades ACULTA | formulário, acesso e payload estão implementados; validação visual/Runtime pendente |
| **0.3.4** | edição contextual com CKEditor 5/Form API, erros e salvamento real | formulário da aula usa CKEditor 5/Form API e `aculta_lms_lesson` com allowlist (parágrafos, títulos, listas, links, ênfase, citação e código); somente `lms_teacher` recebe o formato; valores `plain_text` legados são escapados antes da edição. Render é `processed_text`; edição do gabarito segue bloqueada após respostas. Integração funcional implementada; homologação visual e o erro JS residual do plugin pack ainda pendentes |
| **0.3.5** | mover/reordenar, teclado e aviso de impacto em turmas | controles acima/abaixo e drag-and-drop por lista implementados; serialização por coleção, transação e Kernel. Homologação visual/acessível em browser pendente |
| **0.3.6** | mídias, Gallery, PDF Viewer e H5P quando disponíveis | leituras, galerias, vídeos e documentos existentes listados via owners/Entity Access; documento PDF privado pode ser criado/alterado no contexto do curso reutilizando formulários Media/Group e o viewer canonical; edição limitada a autor com permissão Group `update own`; H5P segue a confirmar por contrato, sem embed paralelo |
| **0.3.7** | prévia estudante e publicação | prévia privada de aula implementada sem respostas/dados de estudantes ou submit; prévia interativa, regras e publicação pendentes |
| **0.3.8** | acessibilidade, testes, responsividade, documentação e homologação | gates Drupal 11+/LMS, evidências |

**Sequência:** especificação primeiro; novas operações só depois da conclusão de seus contratos funcionais. A integração visual do Studio prevista em `0.10.0` (roadmap Studio) e etapas LMS F6/F7/F10/F12/F15 (roadmap LMS) referenciam esta linha CB-UX, **sem renumerar nem declarar entregues marcos preexistentes**. Nenhum commit deste planejamento executa migrações, modifica permissões/configuração ou instala dependências.
