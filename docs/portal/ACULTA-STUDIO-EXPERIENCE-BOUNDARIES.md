# ACULTA Studio — fronteiras entre experiências e extração visual do Opigno

**Estado:** decisão de produto e planejamento; não representa implementação das novas telas.  
**Branch de referência:** dev.  
**Objetivo:** organizar as experiências editoriais, de autoria educacional e de aprendizagem sem duplicar dados, permissões ou capacidades do Drupal LMS, Group e módulos ACULTA.

## Decisão de produto

O ACULTA terá três espaços de trabalho com responsabilidades diferentes:

| Experiência | Para quem / tarefa | Escopo |
| --- | --- | --- |
| **ACULTA Studio Marley** | Equipe editorial, moderadores e responsáveis por galerias | Revista, moderação do Fórum e Galerias. |
| **ACULTA Studio Ganjalab Educador** | Educadores e autores de cursos | Autoria, organização, revisão e prévia educacional por meio do Course Builder. |
| **ACULTA Studio Ganjalab Estudante** | Pessoas matriculadas ou aprendizes | Descoberta de cursos elegíveis, jornada, sala de aprendizagem, progresso e materiais. |

Uma pessoa pode atuar em mais de um contexto. A navegação e as ações disponíveis dependem da tarefa, permissão efetiva e contexto de curso/turma. Ser educador em uma turma não remove a experiência de estudante em outra.

### Marley

Marley fica restrito a **Revista, Moderação do Fórum e Galerias**. A autoria e fluxo editorial da Revista permanecem no owner Magazine; a moderação de tópicos/comentários permanece nos owners Forum/UGC conforme os contratos existentes; Galerias permanecem com o módulo owner correspondente. Marley compõe os workspaces e navegação, sem armazenar cópias dos conteúdos.

Marley não é a área de autoria educacional nem a área de aprendizagem. Conteúdo de discussão do curso pode continuar incorporado contextualmente à Sala Ganjalab, respeitando Fórum e Group; ferramentas gerais de moderação ficam no workspace Marley com acesso contextual.

### Ganjalab Educador

É a experiência de autoria e gestão pedagógica. O **ACULTA Studio LMS Course Builder** pertence a esta experiência, não a Marley nem à área do estudante. Deve permitir construir/editar cursos usando os providers, Form API, entidades e relações realmente disponíveis.

O Course Builder é um workspace sobre Drupal LMS/Group; não é um LMS paralelo. Não presumir entidade “módulo”, relação de ordenação, editor, preview ou regra de progressão até provar a API e persistência disponíveis.

O dashboard do workspace já existe em `courses:/ganjalab/educador`, protegido pela permissão `create lms_course group`. A criação foi alinhada sob `courses:/ganjalab/educador/cursos/novo`, e o retorno do formulário aponta ao dashboard Educador. O acesso continua dependente dos grants nativos Group/LMS; esta mudança não cria papéis nem concede permissões. O dashboard de estudante oferece atalho para o Educador somente quando a rota passa em access; o Educador oferece retorno ao Estudante somente quando o acesso à rota passa. Esses atalhos conectam tarefas sem compartilhar o mesmo painel.

### Ganjalab Estudante

É uma experiência de aprendizagem, não uma área administrativa. O trabalho de estudante já documentado em Ganjalab - início, Minha Jornada e Sala de Aula - continua sendo exclusivo desta superfície. Deve mostrar progresso oficial, próxima ação, conteúdo autorizado, materiais e feedback sem expor rascunhos, respostas de outras pessoas ou dados de turma indevidos.

As rotas atuais preservam o estudante em `courses:/ganjalab` e o Educador em `courses:/ganjalab/educador`; a criação de cursos usa `courses:/ganjalab/educador/cursos/novo`. A experiência do educador não substitui a rota do estudante.

## O que aproveitar visualmente do Opigno

A inspiração vem da organização visual das capacidades, não da skin ou do DOM do Opigno.

| Padrão visual/funcional observado | Destino ACULTA | Regra de adaptação |
| --- | --- | --- |
| Percurso educacional em etapas | Ganjalab Estudante | Lista de etapas com estado real, etapa atual e próxima ação. Uma coluna no mobile; índice recolhível na Sala. |
| Hierarquia/árvore para compor a aprendizagem | Ganjalab Educador | Estrutura do curso no desktop; no mobile, sequência Estrutura → Item → Edição. Mover também por teclado e controles de toque. |
| Inserção contextual e reuso de atividades | Ganjalab Educador | Mostrar apenas tipos/ações autorizados pelo provider. Não criar um banco de conteúdo duplicado. |
| Prévia do estudante | Ganjalab Educador | Renderizar a experiência real de estudante, sem tornar rascunhos ou dados pessoais visíveis. |
| Catálogo e retomada | Ganjalab Estudante | Aproveitar padrões de descoberta/retomada usando catálogo, matrícula e status nativos do ACULTA. |
| Dashboard personalizável com blocos arrastáveis | Nenhum port direto | Manter as prioridades de Ganjalab já planejadas; a personalização do Opigno não é necessária para a primeira experiência. |

**Não portar:** CSS, templates, ícones, Angular compilado, sidebar administrativa, widgets ou dashboard Opigno pixel a pixel. Usar tokens, SDCs e shell do ACULTA420. O foco visual é clareza, responsividade, acessibilidade e identidade ACULTA.

## Arquitetura e ownership

- **Group:** cursos, turmas, memberships, papéis e relações.
- **Drupal LMS:** lições, atividades, respostas, tentativas, status, notas e APIs de avaliação.
- **aculta_lms:** integrações e projeções educacionais próprias já previstas; não duplicar estado do LMS.
- **aculta_studio:** composição de experiências/workspaces e integração de rotas; sem banco educacional paralelo.
- **Marley:** composição de Magazine, moderação e Galerias, delegando cada operação ao módulo owner.
- **Ganjalab Educador:** interface para operações autorizadas de autoria e gestão.
- **Ganjalab Estudante:** apresentação de currículo, progresso e materiais aos quais a pessoa tem acesso.
- **ACULTA420:** shell, identidade visual, tokens e SDCs.

Uma ação de composição não contorna acesso da entidade, revisão, campo, workflow, Group, purpose ou rota canônica. Os presenters filtram no servidor antes de contagens/paginação e carregam cache contexts/tags adequados.

## Caminho de implementação e alinhamento dos roadmaps

O trabalho usa os marcos já existentes; esta decisão **não declara fases concluídas nem renumera os roadmaps**.

| Passo | Documento/marco existente | Ajuste de escopo |
| --- | --- | --- |
| 1. Mapear ownership/rotas e autorização | LMS F0/F4; CB-UX 0.3.0; identidade Studio/ADR-038 | Distinguir Marley, Ganjalab Educador e Ganjalab Estudante; determinar purpose/rota do educador sem presumir. |
| 2. Fechar a experiência estudante | Plano Ganjalab; LMS F5/F6/F12 | Dashboard, percurso e Sala permanecem voltados ao aprendiz. Extrair padrões visuais de percurso/retomada. |
| 3. Construir o workspace educador | Course Builder CB-UX 0.3.1–0.3.8; LMS F8–F10/F15 conforme providers | Árvore, edição, movimentação e preview só quando os contratos e APIs estiverem provados. |
| 4. Consolidar Marley | Roadmap Magazine/Studio; contratos de Fórum, UGC e Galerias | Limitar Marley a Revista, moderação do Fórum e Galerias. Manter administração central no MAIN quando exigido pelo owner. |
| 5. Homologar as três superfícies | Ganjalab UX 0.8 e LMS F16/RC | Matriz de papéis/contextos, Group A/B, teclado/toque, mobile, cache, shell e não vazamento. |

Dependências ou gates não devem ser inferidos como resolvidos por este ajuste documental. Os gates Runtime, visual, segurança, acessibilidade e release dos roadmaps originais continuam válidos.

## Referências normativas e fontes

- [Identidade e rotas do ACULTA Studio](ACULTA-STUDIO-IDENTITY.md)
- [Roadmap Magazine/Marley](STUDIO-MAGAZINE-ROADMAP.md)
- [Plano de experiência Ganjalab](GANJALAB-EXPERIENCE-IMPLEMENTATION-PLAN.md)
- [Gates visuais Ganjalab](GANJALAB-VISUAL-QUALITY-GATES.md)
- [Contrato visual do Course Builder](STUDIO-LMS-COURSE-BUILDER-VISUAL.md)
- [Roadmap funcional do LMS](../modules/ACULTA-LMS-ROADMAP.md)
- [Inventário de ownership LMS/Group](../modules/LMS-GROUP.md)
- [Auditoria do Opigno](../modules/OPIGNO-CODE-AUDIT.md)
- [Opigno Learning Path - interface gráfica e composição de etapas](https://www.drupal.org/project/opigno_learning_path)
- [Opigno Community - encerramento dos lançamentos comunitários](https://www.drupal.org/project/opigno_lms)

A referência Opigno descreve percurso composto por módulos/atividades e interface gráfica de composição com cliques/arrastar e soltar. Ela serve como inspiração de interação; não é uma especificação do modelo de dados ACULTA nem indicação para reutilizar o frontend do Opigno.
