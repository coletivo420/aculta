# ACULTA Studio Ganjalab - Plano de implementação da experiência educacional

**Versão:** G420-UX 1.0.0-dev.1  
**Data:** 2026-11-01  
**Estado:** três superfícies implementadas em dev e validadas no runtime local com a conta disponível; homologação e matriz completa de acesso não declaradas.  
**Branch de referência:** dev  
**Referências visuais:** prancha conceitual A/B/C apresentada na conversa de 2026-11-01. Ela orienta hierarquia e composição; não substitui o Design System ACULTA420 nem constitui especificação pixel a pixel.

## Decisão de produto

Implementar as três superfícies como partes de uma experiência única do estudante:

1. **A - Continuar aprendendo:** página inicial do Ganjalab, orientada à próxima ação real.
2. **B - Minha jornada:** visão do curso, módulos, aulas e estados reais de aprendizagem.
3. **C - Sala de aula:** leitura e realização de uma aula, com índice curricular contextual.

O fluxo do **Ganjalab Estudante** é Início → Curso/Jornada → Aula/Sala → próxima aula permitida → retorno à Jornada. A pessoa deve reconhecer em qual curso está e retomar o percurso sem atravessar a interface de Minha Conta.

Esta especificação cobre somente a experiência do estudante. O dashboard e a jornada permanecem em `courses:/ganjalab`. **Ganjalab Educador** já tem o workspace em `courses:/ganjalab/educador`; o formulário de novo curso está em `/ganjalab/educador/cursos/novo`. O Course Builder completo continua especificado, ainda não implementado. Os dashboards oferecem atalhos recíprocos condicionados pelo acesso à rota destino; a experiência e os dados permanecem separados. Marley é reservado a Revista, moderação do Fórum e Galerias. Consulte [fronteiras das experiências Studio](ACULTA-STUDIO-EXPERIENCE-BOUNDARIES.md) e [Course Builder do Ganjalab Educador](STUDIO-LMS-COURSE-BUILDER-VISUAL.md).

## Diagnóstico de partida na branch dev

## Progresso da implementação

| Entrega | Estado | Evidência / pendência |
| --- | --- | --- |
| Identificar por que o painel de Minha Conta envolvia Aprendizagem | Concluído por inspeção estática | LearningDashboardHooks.php incluía aculta_lms.student_dashboard no alter de seções da Conta; AccountShellBuilder.php usa essa lista para decidir se deve substituir o conteúdo pelo shell da Conta. Nenhum outro hook recoloca a rota na lista. |
| Remover a inclusão da rota de estudante no shell de Conta | Concluído e confirmado no runtime local | Commit 124e31e removeu o hook. Após `drush cr`, `/ganjalab` respondeu 200 com uma única H1 "Minha aprendizagem" e sem menu/heading lateral de Minha Conta; o link "Minha conta" restante é o atalho da barra institucional global. |
| Ajustar tipografia e espaçamento do conteúdo Ganjalab | Concluído em código | Commit d804adb e CSS da experiência; validação visual no harness de navegador. |
| A — Início: Continuar aprendendo | Implementado e validado no runtime local | Cabeçalho contextual, cartão de retomada somente com curso iniciado, não concluído, com destino LMS e atividade registrada; progresso apenas com aulas publicadas; estado sem matrícula com link ao catálogo; cartões de curso com "Ver jornada" e ação canônica. |
| B — Minha jornada: curso | Implementado e validado no runtime local | Composição na página nativa do curso (`entity.group.canonical`), com progresso, aulas na ordem do LMS, estados textuais e links apenas quando a política de navegação do LMS permite. A ação do curso foi localizada em português reutilizando o componente `lms:start_link`. |
| C — Sala de aula | Implementado e validado no runtime local | Shell em torno de `lms.group.answer_form`: contexto do curso/aula, retorno ao curso, índice recolhível no celular e lateral no desktop, formulário nativo do LMS, sem dependência de JavaScript. Navegar/reproduzir não marca conclusão. |
| A/B/C integradas como percurso único | Implementado em código | Início → jornada → sala → retorno à jornada; links absolutos no propósito `courses`; catálogo e cursos matriculados permanecem distintos. |
| Alternância Estudante ↔ Educador | Implementada em código, validação pendente | Atalhos recíprocos aparecem somente quando Url::access autoriza a rota destino; o dashboard Educador filtra cursos por acesso Group `update`. PHPUnit/browser/Runtime posteriores à mudança estão pendentes. |
| Regressão, acessibilidade e segurança | Parcial | Testes Unit e harness visual de larguras; matriz de estudante A/B, Group A/B, matrícula revogada, docente também estudante e material privado ainda não executada no runtime por falta de conta não administradora matriculada em curso com aulas. |

### Limite de retomada e decisão tomada

O manager passou a expor, somente leitura, o status corrente do LMS, a última atividade registrada e a aula apontada por `current_lesson_status`. O cartão "Continuar aprendendo" usa o curso com atividade mais recente apenas quando ele está iniciado, não concluído, possui destino LMS (`lms.course.start`) e não está aguardando avaliação. Quando a aula corrente já está concluída, a home não inventa "Continuar aula": mostra o curso e a ação canônica de continuar. Não existe uma fonte separada de "última aula visitada" além do ponteiro do LMS; a lacuna de uma retomada por aula, com deep link explícito, permanece documentada como trabalho funcional futuro.

### O que não foi implementado nesta etapa

- Agrupamento por módulos: o modelo de dados do LMS instalado tem aulas e atividades, mas não uma entidade de módulo. A jornada apresenta as aulas na ordem oficial e não inventa módulos.
- Atividades, prazos, materiais e discussões no nível da jornada: continuam nos componentes e rotas do LMS; não foram duplicados.
- Anterior/próxima na sala: a submissão e o retorno de atividade continuam sob o Form API do LMS. O shell fornece o índice e o retorno ao curso, sem criar um segundo fluxo de avanço.
- H5P, PDF, vídeo e discussão: os módulos proprietários e seus gates permanecem inalterados; a sala apenas envolve a página nativa.
- Matriz runtime de estudante A/B, Group A/B, matrícula revogada e material privado: pendente de contas/cenários próprios.

### Validação executada em 2026-11-01

- PHPUnit Unit (aculta_lms e aculta_studio): 151 testes, 1067 asserções, OK.
- `php -l` nos arquivos alterados: sem erros de sintaxe.
- PHPCS com o padrão do Drupal core: 0 erros nos arquivos da entrega; restaram avisos de comprimento de linha em trechos preexistentes do manager.
- Runtime local (`https://cursos.aculta.toca.net.br`, banco `aculta_runtime`): `/ganjalab` 200; `/group/1` 200; `/group/85` 200 após corrigir o curso sem aulas; sala `/curso/introducao-ao-antiproibicionismo/origens-do-controle-internacional/tratados-internacionais` 200; acesso anônimo à home e à sala 403; uma H1 por superfície; formulário nativo `#lms-answer-form` presente.
- Harness visual: `tests/browser/aculta-ganjalab/learning-experience.mjs`, executado no Firefox ESR visível. Resultado final: 74/74 verificações OK em 320, 390, 768, 1024, 1280 e 1440 px, incluindo estado sem matrícula, modo escuro e foco por teclado. As 21 capturas e o `results.json` ficam em `$SUITE_OUT`/`GANJA_OUT`, fora do repositório.
- A captura autenticada usa a conta administrativa matriculada no curso 1; o estado sem matrícula usa `teste.comum`. A matriz A/B completa de estudantes, Group A/B, matrícula revogada e material privado não foi executada no runtime.
- Verificação sem JavaScript pela resposta HTTP: o HTML da sala contém o índice, os links canônicos e o formulário nativo `#lms-answer-form`; o shell não depende de script para exibir conteúdo ou enviar formulários.
- Não executado: PHPStan (a configuração do core exige o diretório `composer/` ausente neste layout), testes Kernel, fluxos H5P/PDF/discussão, revogação de matrícula e matriz completa de estudantes/turmas.

### Contrato de leitura da jornada (2026-11-01)

A jornada e a sala passaram a consumir `Drupal\aculta_learning_contracts\Learning\CourseJourneyReaderInterface` e os DTOs `CourseJourney`/`CourseJourneyLesson`. O adaptador `LearningDashboardManager` continua lendo Group e Drupal LMS; o `CourseJourneyComposer` não importa mais `Drupal\lms` nem `aculta_lms`. O comportamento visual e os destinos canônicos não mudaram. Validação final: PHPUnit Unit `aculta_studio` 62/597 e `aculta_lms` 99/472, ambos OK; PHP lint e PHPCS sem erros nos arquivos alterados; Kernel `ContractsInstallTest` 1/8 PASS; smoke Firefox ESR/X11 do harness `tests/browser/aculta-ganjalab/learning-experience.mjs` com **74/74 verificações OK** em 320, 390, 768, 1024, 1280 e 1440 px. No Runtime, o módulo `aculta_learning_contracts` foi habilitado após snapshot com hash e a configuração ficou sem diferenças; `/ganjalab/educador` e `/ganjalab/educador/cursos/novo` responderam 200 para conta autorizada. Nenhum dado de curso, matrícula ou progresso foi migrado. Inventário, snapshot e fases seguintes: [LMS-CONTRIB-DEPENDENCY-INVENTORY.md](../modules/LMS-CONTRIB-DEPENDENCY-INVENTORY.md).

A mudança de shell remove somente o vínculo da rota do estudante com a navegação/shell privado de Minha Conta. Ela não remove nem altera o shell da Conta, o propósito courses, a rota canônica ou as permissões do LMS.


A captura enviada em 2026-11-01 mostra o conteúdo educacional renderizado dentro da composição visual de Minha Conta: título “Minha conta”, subtítulo “Área pessoal”, menu privado lateral e conteúdo “Meus cursos” disputam a hierarquia. A grade de cursos tem pouco contexto de retomada e títulos/proporções que não orientam bem a ação no celular.

Na árvore atual, a rota de estudante é declarada em aculta_lms.routing.yml como propósito courses em /ganjalab e encaminha para o controller de estudante do aculta_studio. LearnerDashboardComposer compõe a lista de cursos e certificados. A existência dessa rota e desse composer não comprova que haja uma home de aprendizagem, jornada de curso e sala visualmente integradas ou homologadas no Runtime.

Os documentos visuais anteriores de Dashboard, Jornada e Sala registram contratos úteis, mas ainda se identificam como planejamento. Há referências históricas a /studio/ganjalab em documentação antiga. A implementação deve seguir o contrato atual de domínios e caminhos, registrado em AGENTS.md, ADR-038 e ACULTA-STUDIO-IDENTITY.md: Ganjalab pertence ao propósito courses; /studio e /studio/* não são caminhos públicos; a infraestrutura aculta_studio é compartilhada. A área de Conta e a Minha Conta legada permanecem separadas do Ganjalab.

A primeira microfase deve inspecionar a composição real no Runtime e reconciliar divergências entre rota, propósito, shell, template, blocos e estilos antes de atribuir a causa visual a um único arquivo.

### Limite atual dos dados para a home de aprendizagem

A implementação atual de LearningDashboardManager::studentDashboard fornece a lista autorizada de cursos, contagem de aulas concluídas/total, percentual calculado pelo serviço, estado pendente de avaliação, certificados e metadados de cache. Ela não fornece a última aula visitada, um destino oficial de retomada ou a próxima aula permitida como URL. A home não deve inventar “Continuar aula” usando apenas o primeiro curso da lista. A auditoria G0 deve verificar rotas/providers existentes e, se não houver fonte canônica de retomada, implementar A com links de curso honestos e deixar o requisito de retomar aula como lacuna funcional documentada.

## Princípios e limites de arquitetura

- ACULTA420 é dono de tokens, SDCs e apresentação. aculta_studio compõe a experiência e o contexto. aculta_lms e Drupal LMS/Group são donos dos dados pedagógicos, matrículas, estados e acesso.
- Reutilizar padrões SDC, UI Patterns, Bootstrap, Twig, formulários e render arrays existentes. Primeiro reutilizar; depois estender uma variante ou slot; só propor novo componente após demonstrar lacuna e consumidores reais.
- Não criar outro shell global, segundo sistema de cartões, navegação concorrente, módulo LMS, entidade, armazenamento, gradebook, progresso paralelo ou regra de acesso no tema.
- Respeitar os serviços atuais de domínio. Links de Conta devem usar a política de propósito/domínio, sem embutir Minha Conta no shell de courses.
- Acesso deve ser decidido no servidor antes da renderização. Dados, ações, URLs e estados devem ser fornecidos por controllers/providers autorizados, preservando cache contexts, tags e attachments.
- Progresso, prazo, matrícula, envio, avaliação, certificado e conclusão só aparecem quando fornecidos por fonte canônica. Navegar para a aula seguinte não conclui uma aula. Enviado não significa avaliado.
- Catálogo público e aprendizagem matriculada são fluxos distintos. Uma pessoa não deve confundir cursos disponíveis para inscrição com cursos aos quais já tem acesso.
- A implementação deve preservar o funcionamento HTTP sem JavaScript. AJAX, quando já existente e útil, mantém URL, histórico, foco, erro e fallback.

## Sistema visual compartilhado

- Cabeçalho institucional e shell multidomínio atuais permanecem. Abaixo deles, o Ganjalab apresenta cabeçalho contextual próprio, sem duplicar barras globais.
- Identidade ACULTA Studio Ganjalab, título da página e ação principal aparecem em ordem clara. O link de Conta é secundário e respeita o domínio correto.
- Aplicar tokens ACULTA420, com verde-escuro como ação primária, creme e branco como superfícies, verde médio e amarelo como acentos com contraste adequado. Usar tipografia do tema e escala equilibrada: a ação e o curso devem ganhar destaque, sem títulos gigantes.
- Em telas estreitas, uma coluna e conteúdo legível; no desktop, largura de leitura limitada, composição mais ampla e índice lateral somente quando ajudar.
- A navegação inferior mostrada na prancha é uma alternativa visual, não requisito de arquitetura. Só implementá-la se o inventário confirmar que ela não duplica navegação do shell e que os destinos existem. Caso contrário, usar navegação compacta contextual e links reais.

## Superfícies que serão implementadas

### A - Início: Continuar aprendendo

**Objetivo:** permitir que a pessoa retome o curso ou encontre rapidamente o próximo passo.

**Ordem sugerida:**

1. Cabeçalho contextual “ACULTA Studio Ganjalab” e “Minha aprendizagem”.
2. Cartão “Continuar aprendendo” quando houver curso/aula acessível e informação confiável para retomar.
3. Atividades próximas ou pendentes, apenas com prazo e estado reais.
4. Lista ou grade flexível de cursos matriculados, cada um com próximo passo e progresso quando disponível.
5. Turmas, materiais, certificados ou histórico apenas quando a fonte funcional correspondente estiver pronta e houver informação útil.
6. Estado vazio acolhedor quando não houver matrícula, com link para o catálogo público real.

O cartão de retomada terá curso, aula, progresso textual acessível e uma ação principal “Continuar aula”. Se não houver fonte confiável de última aula ou progresso, a tela mostra os cursos e links canônicos sem inventar retomada, percentual ou tarefa.

### B - Curso: Minha jornada

**Objetivo:** responder “onde estou, o que vem agora e o que já fiz?” dentro de um curso.

**Conteúdo:**

- título, descrição e contexto do curso;
- módulos e aulas em ordem definida pelo LMS;
- estado de cada item apenas quando reconhecido pelo provider;
- destaque da aula atual/próxima aula permitida;
- pendências e prazos da matrícula/turma da própria pessoa;
- materiais e discussões contextuais somente se vinculados e autorizados;
- acesso à Sala da aula e retorno à lista de cursos.

No mobile, a jornada é uma lista vertical com módulos recolhíveis quando isso reduzir rolagem. No desktop, pode ocupar a coluna principal, com resumo e próximas ações em coluna secundária. Estados de curso bloqueado ou pré-requisito não podem revelar título, material ou informação que o usuário não possa ver.

### C - Aula: Sala de aula

**Objetivo:** oferecer um espaço focado em consumir o conteúdo e realizar a atividade atual.

**Composição compartilhada:**

- retorno ao curso e identificação de módulo/aula;
- índice curricular recolhível no mobile e lateral no desktop;
- conteúdo em uma região de leitura central;
- navegação anterior/próxima apenas para itens acessíveis;
- abas ou seções contextuais para aula, materiais e discussão somente quando houver conteúdo;
- estados de carregamento, erro, vazio e acesso restrito comunicados claramente;
- progresso e conclusão oficiais, sem inferência por clique ou reprodução.

**Modelos de conteúdo a integrar no mesmo shell:**

| Modelo | Apresentação | Dono funcional |
| --- | --- | --- |
| Leitura | tipografia editorial, imagens responsivas e links acessíveis | Drupal LMS e renderização de conteúdo |
| Vídeo | player responsivo, legendas/transcrição quando disponíveis e sem reprodução automática | Media/player autorizado |
| PDF | viewer compartilhado e alternativa acessível | aculta_pdf_viewer e Media/File |
| Atividade | instruções, formulário, envio e feedback nativos | Drupal LMS/Form API e integrações existentes |
| H5P | área própria, responsiva, com estados e acessibilidade verificados por tipo | aculta_lms_h5p e Drupal LMS |
| Discussão | Comments/tópicos existentes no contexto autorizado da aula/turma | aculta_forum_lms e Group |

Discussão não cria conversas, grupos ou threads automáticos no Messenger. Avisos continuam sob aculta_messenger. Não implementar novo player PDF ou um segundo sistema de mensagens.

## Planejamento por fases e microfases

As fases são sequenciais quando dependem da anterior. Cada entrega deve ser um commit pequeno e restrito ao Ganjalab, preservando alterações alheias na branch.

| Fase | Microfases e entregas | Gate para avançar |
| --- | --- | --- |
| **G0 - Auditoria e contrato real** | G0.1 mapear rotas, purpose, providers, controllers, shell e destinos; G0.2 capturar desktop/mobile da página atual e localizar o wrapper que produz “Minha Conta”; G0.3 rastrear dados e acesso disponíveis para curso, retomar aula, progresso, atividades, certificado e jornada; G0.4 comparar as referências documentais antigas com ADR-038 e corrigir links/nomes desatualizados no escopo | Matriz evidenciada por rota, arquivo, owner e estado; causa da composição visual explicada; sem alteração de acesso ou URL por suposição |
| **G1 - Fundação visual e isolamento do shell** | G1.1 definir contrato de cabeçalho contextual e área principal; G1.2 reutilizar SDC/tokens existentes; G1.3 remover somente a composição do painel legado da experiência courses, mantendo Conta e Minha Conta legada intactas; G1.4 validar links cruzados de Conta por política de domínio | Captura autenticada no domínio courses sem menu/heading de Minha Conta; shell institucional preservado; uma H1 e um contexto claros; sem rota /studio pública |
| **G2 - Home A: Continuar aprendendo** | G2.1 criar composição do dashboard com render arrays; G2.2 conectar cartão de retomada somente se provider fornecer item seguro e URL real; G2.3 organizar atividades reais; G2.4 adaptar cartões/lista de cursos; G2.5 estado sem matrícula e ausência de progresso | Estudante com vários cursos identifica a ação principal; dados são próprios e autorizados; estudantes sem retomada/sem matrícula recebem alternativa útil; sem contadores inventados |
| **G3 - Jornada B: curso e percurso** | G3.1 confirmar rotas/providers canônicos de curso e aula; G3.2 compor módulos e aulas em ordem oficial; G3.3 apresentar estados reais e aula atual/próxima; G3.4 integrar atividades, prazos e recursos quando existirem; G3.5 criar estados sem conteúdo, bloqueado e concluído sem vazamento | Links abrem destinos existentes; navegação não altera conclusão; estados e permissões vêm do LMS/Group; leitura e uso adequados em celular |
| **G4 - Sala C: aprendizagem em aula** | G4.1 compor shell de sala com índice; G4.2 integrar modelo de leitura; G4.3 vídeo e legendas/transcrição disponíveis; G4.4 PDF pelo viewer existente; G4.5 atividade e submissão pelas APIs existentes; G4.6 H5P apenas dentro dos gates próprios; G4.7 discussão autorizada do aculta_forum_lms; G4.8 anterior/próxima e retorno | Cada modelo funciona no fallback HTTP; nenhum player ou armazenamento duplicado; a troca de aula preserva acesso, foco e contexto; progresso não é inferido |
| **G5 - Continuidade e destinos complementares** | G5.1 conectar navegação Início, Cursos e Atividades a destinos reais; G5.2 integrar turmas, avisos, materiais e certificados quando providers já existirem; G5.3 definir menu móvel sem duplicar shell; G5.4 verificar distinção entre catálogo e cursos matriculados | Nenhuma aba ou botão sem destino funcional; integrações mostram somente dados pertinentes ao estudante |
| **G6 - Acessibilidade, responsividade e segurança** | G6.1 revisar 320, 360, 390, 768, 1024, 1280 e 1440 px; G6.2 teclado, foco, leitor de tela, zoom/reflow e reduced motion; G6.3 claro/escuro e safe areas; G6.4 matriz de estudante A/B, Group A/B, docente também estudante e matrícula revogada; G6.5 cache, sessão, erro, offline e no-JS; G6.6 captura visual e correções | WCAG 2.2 AA; sem scroll horizontal involuntário; sem vazamento de matrícula, nota, resposta, certificado ou arquivo privado; screenshots aprovadas nas larguras-alvo |
| **G7 - Consolidação e prontidão para validação** | G7.1 atualizar documentação visual e status real de cada fase; G7.2 registrar evidências dos testes automatizados e smoke visual; G7.3 revisar diff e commits; G7.4 preparar checklist para validação posterior no Runtime, sem executar deploy/importação neste plano | Plano não marca fase como concluída sem evidência; commits escopados; nenhuma alteração a produção; decisão de release segue o ciclo Dev → RC → Alpha |

## Matriz mínima de estados

A implementação deve cobrir, quando aplicável: estudante sem cursos; curso sem progresso fornecido; curso em andamento; curso concluído; curso sem aulas publicadas; nenhuma atividade pendente; prazo definido e prazo ausente; atividade enviada aguardando avaliação; avaliação concluída; matrícula revogada; pré-requisito indisponível; erro de mídia/PDF/H5P; conteúdo sem discussão; certificado inexistente ou emitido. Não representar estados indisponíveis com “0%” fictício.

## Critérios transversais de aceite

- Uma pessoa entende que está no Ganjalab, não em Minha Conta, em todas as três superfícies.
- A primeira ação da home é identificável sem rolagem excessiva em viewport móvel.
- Jornada e Sala mantêm curso, módulo e aula visíveis sem repetir cabeçalhos ou criar menus concorrentes.
- Todas as ações apontam para rotas reais e autorizadas; URLs e links seguem o propósito de domínio canônico.
- 320 px não produz overflow horizontal; controles de toque têm área confortável; texto continua útil com zoom até 400%.
- WCAG 2.2 AA, navegação por teclado, foco visível/restaurado, estrutura de títulos, rótulos, contraste e alternativas de mídia.
- Cache considera usuário, curso, Group e permissões; nenhum conteúdo privado depende de CSS para ficar oculto.
- Nenhum progresso, nota, prazo, certificado, conclusão ou estado de H5P é calculado ou presumido no frontend.
- Layouts mobile e desktop compartilham hierarquia e identidade; desktop não estica linhas de leitura nem cria vazios desnecessários.

## Arquivos e documentação relacionados

- [Dashboard, cursos, atividades e conquistas](GANJALAB-DASHBOARD-VISUAL.md)
- [Experiência do estudante](GANJALAB-LEARNING-UX.md)
- [Minha Jornada](GANJALAB-LEARNING-JOURNEY-VISUAL.md)
- [Sala de Aprendizagem](GANJALAB-LEARNING-ROOM-VISUAL.md)
- [Modelos de aula](GANJALAB-LESSON-MODELS-VISUAL.md)
- [Consolidação visual e gates](GANJALAB-VISUAL-QUALITY-GATES.md)
- [Identidade e rotas do ACULTA Studio](ACULTA-STUDIO-IDENTITY.md)
- [Roadmap do LMS](../modules/ACULTA-LMS-ROADMAP.md)
- [ADR-038 - Suites ACULTA Studio por domínio](../decisions/ADR-038-aculta-studio-suites-por-dominio.md)

Este documento planeja a implementação das três superfícies e registra os limites para o trabalho futuro. Não declara que as fases de produto, QA ou homologação estejam implementadas ou aprovadas.
