# GanjaLab420 — experiência do estudante e participante

**Versão:** 0.1.0-dev.1 · **Estado:** documentação inicial de produto/UX; não é um novo módulo nem uma implementação funcional. **Data:** 2026-10-22.

## Identidade e divisão de responsabilidades

- **GanjaLab**: experiência pública de criação, edição, autoria, educação e moderação, executada tecnicamente pelo submódulo `aculta_studio` e pelos providers funcionais; acesso por permissões atuais.
- **GanjaLab420**: experiência de **usuário/estudante** para consumir cursos, acompanhar atividades, turmas, materiais, evolução e certificados. É uma identidade da experiência existente no portal/conta e LMS, **não** um segundo LMS, sistema de login, módulo, domínio, banco de dados ou conjunto de roles.
- Uma pessoa pode utilizar ambos com a mesma conta: estudante em um curso e docente/criadora noutro. A escolha da interface é dada pela tarefa e acesso real, jamais por uma separação rígida de pessoas/roles.
- **ACULTA420** continua dono de toda a apresentação/tokens/SDC, independente da identidade pública. Curso/turma/participação são responsabilidade de Group e Drupal LMS; `aculta_lms` integra; `aculta_user`, `aculta_messenger`, `aculta_pdf_viewer`, `aculta_image`, `aculta_forum`, `aculta_gallery` e `aculta_game` são serviços compartilhados nas suas fronteiras atuais.

## Promessa do produto

**Aprender, participar e acompanhar o próprio percurso**, confortavelmente no celular. A experiência deve ser acolhedora, clara e sem expor terminologia interna de Drupal/Group. Foco em retomar a próxima aula e acompanhar pendências; não copiar dashboards administrativos nem exibir cursos da equipe por padrão.

## Mapa inicial das telas

| Superfície GanjaLab420 | Objetivo | Fonte/limite |
| --- | --- | --- |
| Minha aprendizagem | cursos em que a pessoa tem matrícula/acesso educacional; “Continuar aprendendo” | LMS/Group membership e access |
| Meu curso | capa, descrição, programa, próxima aula e progresso **real** | LMS/Group, sem escore paralelo |
| Sala de aprendizagem | aula/atividade atual, navegação anterior/próxima, materiais, conclusão | entidades/rotas/formulários nativos do LMS |
| Atividades | entregas, avaliações pendentes e feedback próprio | LMS Answer/Score com restrição por usuário/turma |
| Minhas turmas | vínculos reais de estudante, avisos e calendário/prazos existentes | Group/relations LMS |
| Materiais | PDFs, anexos, mídia e leituras autorizados | Media/File, PDF Viewer e Group |
| Discussões | conversas do contexto da turma/aula | Fórum contextual; não abrir tópico público automaticamente |
| Certificados | certificados emitidos/verificáveis, quando o provider existir | contratos LMS/Entity Print/QR; mostrar como previsto até habilitação |
| Notificações | avisos relevantes sobre cursos/turmas e entregas | `aculta_messenger`, sem canal paralelo |
| Perfil e preferências | identidade, aparência, privacidade e acessibilidade | `aculta_user`/conta; persistência existente |

O catálogo público de cursos permanece um fluxo separado de descoberta, enquanto “Minha aprendizagem” mostra só cursos elegíveis e autorizados da pessoa. Não misturar cursos matriculados com cursos apenas disponíveis no catálogo.

## Dashboard mobile first

Ordem de informação: (1) identidade contextual **GanjaLab420 / Minha aprendizagem**; (2) cartão “Continuar aprendendo” quando o LMS fornecer último item permitido; (3) pendências de ação e prazo do usuário (somente dados reais); (4) Meus cursos em cartões; (5) minhas turmas/avisos; (6) certificados e histórico quando suportados.

```text
ACULTA  /  GanjaLab420                        [Conta]
Minha aprendizagem
Continue de onde parou
┌─────────────────────────────────────────────┐
│ Curso: Redução de danos                     │
│ Aula: História das políticas de drogas      │
│ [Continuar aula →]                          │
└─────────────────────────────────────────────┘
Próximas atividades (quando houver)
Meus cursos  [cartões verticais no celular]
Minhas turmas / Avisos
```

Desktop: manter o mesmo fluxo em uma região central e sidebar contextual opcional, sem modificar hierarquia nem duplicar navegação global. Mobile: priorizar texto claro, cartões, indicadores de progresso acessíveis e CTA de retomar. Não renderizar valores fictícios, progresso calculado no navegador, cronograma/nota de outra pessoa ou botões de ação sem acesso.

## Sala de aprendizagem — UX

Cabeçalho leve com breadcrumb “Minha aprendizagem / Curso / Aula”, título e estado quando suportado. Navegação curricular recolhível no desktop e offcanvas/índice em tela separada no celular; área central com conteúdo real da atividade. Viewer PDF unificado, mídias acessíveis, H5P apenas quando disponível/seguro e submissões por formulários LMS/Drupal. Ao concluir uma atividade, mostrar feedback comprovado pelo backend, próxima aula permitida e respeito a pré-requisitos. Discussões contextualizadas dentro da aula/turma por `aculta_forum` + acesso Group. Nenhuma conclusão fictícia disparada só por clique frontend.

## Estados e privacidade

- Sem matrículas: estado acolhedor com link ao catálogo permitido, nunca inventar cursos.
- Inscrição sem acesso à aula: descrever indisponibilidade sem revelar conteúdo protegido.
- Prazo vencido, entrega pendente, envio concluído e avaliação: refletir estados oficiais do LMS, ajustados ao fuso pessoal.
- Em progresso, concluído e certificado: calcular a partir de fonte canônica. Contagens de atividades só para o participante autorizado.
- Perda de acesso à turma revoga acesso aos arquivos e discussões conforme Group/Media; revalidar em cada requisição.
- `user`, memberships, domain purpose, Group, route e cache tags específicos devem impedir vazamento cruzado. Nenhum analytics, certificados, armazenamento de mensagens ou perfil duplicado.

## Conexões com GanjaLab

Um mesmo curso pode aparecer de modo diferente:
- **GanjaLab / Course Builder:** “Editar curso”, currículo, turmas e avaliações autorizadas para criadores.
- **GanjaLab420:** “Continuar aula”, materiais, atividades e resultados próprios para estudantes.
A alternância, quando permitida, deve ser explícita e não trocar o contexto de estudante por gestão silenciosamente; links dirigem a rotas reais do dono. Ambos compartilham conteúdo e conta, jamais tabelas de cópia.

## Componentes visuais propostos

No tema ACULTA420, avaliar/reutilizar SDC e Bootstrap 5 para: `learning-dashboard`, `continue-learning-card`, `enrolled-course-card`, `learning-course-outline`, `lesson-reader-shell`, `activity-status`, `learning-deadline`, `certificate-card` e `student-empty-state`. Os IDs são exemplos, não componentes instalados. Apenas propriedades seguras/cacheáveis; tema não conhece entidades/Group e não faz queries de permissões.

Modos claro/escuro compartilham estrutura; tokens do ACULTA420 determinam superfícies, tipografia, bordas e foco. Alvos de toque preferencialmente >=44px, zoom/reflow, teclado, leitor de tela, reduced motion e WCAG 2.2 AA.

## Roadmap inicial G420-UX

| Marco | Planejamento / entrega | Pré-requisito |
| --- | --- | --- |
| 0.1.0 | Identidade pública, escopo, jornada e inventário das rotas/fontes | manter contratos existentes, sem novo módulo |
| 0.2.0 | Dashboard Minha aprendizagem e retomar curso | LMS status e memberships reais |
| 0.3.0 | Página pessoal do curso e índice curricular | LMS/Group, access/cache |
| 0.4.0 | Sala mobile-first, reader de aula e materiais | Form API, PDF Viewer, Media |
| 0.5.0 | Atividades, feedback e prazos pessoais | LMS Answer/Score e Group |
| 0.6.0 | Turmas e discussões contextuais | Group e Fórum contextual |
| 0.7.0 | Certificados e notificações integradas | providers existentes/estáveis |
| 0.8.0 | Light/dark, acessibilidade, estados de erro, testes e homologação | 320–1440px, acesso A/B e browser |

**Gates:** validar 320/360/390/768/1024/1280/1440 px; no-JS, teclado virtual, zoom 200–400%, dark/light, cache entre estudante A/B, diferentes Group e purpose. Revisar UX com evidências de Runtime antes de declarar entregue. Marcos são trilha visual, não substituem F0–F17 do [roadmap do LMS](../modules/ACULTA-LMS-ROADMAP.md).

## Regra de transição de marca

Nome público da experiência: **GanjaLab420**. Nesta fase manter URLs, machine names, serviços, storage, rotas, menu links e nomenclatura interna existentes, sem criar `ganjalab420` como módulo. Antes de aplicar rótulos ao Runtime, inventariar blocos e menus consumidores para não promover mudança generalizada de conta. Referência à interface de criação: [GanjaLab](GANJALAB-BRANDING.md).

## Rota inicial da experiência

O dashboard de estudante canônico passa a `/ganjalab420` no domínio de conta, usando o mesmo controller e route name de `/minha-aprendizagem`; este último permanece alias temporário. Nenhuma outra rota de sala, avaliação, matrícula ou certificado foi renomeada nesta fase. Ver [contrato de marca](GANJALAB-BRANDING.md).

## Design visual detalhado — 2026-10-22

- [Dashboard, cursos, atividades e conquistas](GANJALAB420-DASHBOARD-VISUAL.md): G420-UX 0.2.0-dev.1; mobile first, continuidade de estudos e progresso real.
- [Sala de Aprendizagem](GANJALAB420-LEARNING-ROOM-VISUAL.md): planejamento G420-UX 0.4.0-dev.1; índice curricular, leitor multimídia, atividades, PDF, H5P, Fórum contextual e critérios de acesso.
- **Decisão técnica definitiva neste planejamento:** preservar o nome interno `aculta_lms`; descartar propostas de renomear para `aculta_escola` ou `aculta_ganjalab_lms`. Essas entregas são somente documentação.

## Cinco formatos de sala

Incluir explicitamente o **H5P como quinto modelo visual**, além de leitura, vídeo, atividades e PDF. Ver [Modelos de aula do GanjaLab420](GANJALAB420-LESSON-MODELS-VISUAL.md). A implementação permanece condicional aos gates de integração, segurança e acessibilidade do H5P. Nenhuma renomeação técnica de LMS está autorizada.

## Atividades e avaliações — especificação dedicada

Consultar [G420-UX 0.4.3](GANJALAB420-ACTIVITIES-ASSESSMENTS-VISUAL.md): questionários, entregas, H5P, feedback, tentativas e acessibilidade. Contrato visual apenas; `aculta_lms` permanece o módulo técnico.

## Biblioteca de materiais e leitor PDF

[Planejamento G420-UX 0.4.4](GANJALAB420-MATERIALS-PDF-VISUAL.md): materiais da aula, biblioteca por curso, reader PDF mobile-first, imagens, Gallery, vídeo e H5P como recurso integrado. Preservar `aculta_lms` e providers existentes.

## Minha Jornada de Aprendizagem — planejamento visual 0.5

[Minha Jornada](GANJALAB420-LEARNING-JOURNEY-VISUAL.md) define percurso de aulas, progresso e pendências reais, feedback, turmas e certificados, sem métricas ou dados paralelos. Os contratos da Biblioteca contextual permanecem em [Materiais e PDF](GANJALAB420-MATERIALS-PDF-VISUAL.md).

## Minhas Turmas e Comunidade — G420-UX 0.6

[Planejamento visual de turmas e comunidade](GANJALAB420-CLASSES-COMMUNITY-VISUAL.md): mural de avisos, discussões contextuais, prazos, participantes autorizados, Group e integração com Fórum/Messenger/User. Planejamento somente; `aculta_lms` permanece inalterado.

## Conquistas, certificados e histórico — UX 0.7

[Contrato visual de Conquistas, Certificados e Histórico](GANJALAB420-ACHIEVEMENTS-CERTIFICATES-VISUAL.md): emissão e validação oficiais de certificados, PDF/QR, histórico pessoal e conquistas opcionais, sem métricas paralelas. `aculta_lms` permanece o módulo técnico. Planejamento apenas.
