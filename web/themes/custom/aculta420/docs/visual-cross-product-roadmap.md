# ACULTA420 — Roadmap visual transversal (planejamento)

**Linha de planejamento:** THEME-UX 0.8 · **Data:** 2026-10-22 · **Estado:** plano, sem implementação e sem gates Runtime aprovados.

## Decisão arquitetural obrigatória
**Preservar Institution Bar + Domain Header multidomínio existentes em TODAS as áreas.** A evolução visual pode aperfeiçoar responsividade, contraste, espaçamento, navegação mobile e light/dark, mas não substitui, oculta ou duplica o shell. O futuro Context Header é **subcabeçalho de página** e não um cabeçalho global. Domain/purpose, URLs, dados, ACL e cache ficam no Portal/providers; tema somente apresenta.

## Transferência formal de escopo
O planejamento inicial nasceu na UX do estudante [GanjaLab420 0.8](../../../../../docs/portal/GANJALAB420-VISUAL-QUALITY-GATES.md), mas **componentes, tokens, CSS, histórias e padrões visuais compartilhados são backlog do TEMA**. A integração funcional por módulo permanece em suas documentações próprias. O tema não cria progressão LMS, certificados, envio de respostas, operações H5P, notas ou PDF.js paralelo.

## Inventário / escolhas atuais
- Confirmado na árvore do tema: `patterns/card` com slots e variantes; `content/course_card`; `patterns/rail`, `content-grid`, `content-section`, `figure`, `quote`; `content/author-card`; componentes Messenger, avisos, QR e Fórum.
- Existe shell multidomínio, Bootstrap 5, tokens semânticos e catálogo UI Patterns stories.
- **Lacunas candidatas, não implementadas:** context-header, context-tabs, empty/state, content-reader, resource-list, status-timeline, next-action. Primeiro verificar se Core/Bootstrap/SDCs atuais já satisfazem o uso.
- Evidência apenas de inventário estático; uso em Runtime e componentes realmente renderizados ainda requerem inspeção.

## Matriz de reuso
| Padrão | Primeiro caso | Reutilização prioritária | Ownership |
| --- | --- | --- | --- |
| Cabeçalho contextual interno | aula GanjaLab420 | Revista, Wiki, Fórum, Minha Conta, GanjaLab | ACULTA420 |
| Abas de contexto | turmas / GanjaLab | Fórum, Wiki, Conta | ACULTA420 visual; AJAX pelo módulo |
| Estados/feedback | biblioteca vazia/erro | Loja, Messenger, Revista, Wiki | ACULTA420 visual; estados pelo provider |
| Leitor editorial | aula texto/vídeo | Wiki, Revista, Document Viewer | ACULTA420 reader; mídias pelos kernels |
| Lista de recursos | biblioteca por aula | Gallery, Wiki, Revista | ACULTA420; arquivo em Media/File |
| Linha de percurso/estados | Minha Jornada | workflows de Revista/Wiki/Projetos | ACULTA420 visual; semântica pelo provider |
| Cartões | cursos/avisos/documentos | todo o portal | estender `patterns/card` antes de criar |
| Pessoa/autoria | participantes | Fórum, Wiki, Revista | `aculta_user` + `author-card` |
| PDF/H5P | aula e avaliações | consumidores reais de cada kernel | wrappers visuais, nunca players novos |

## Roadmap próprio do tema — microfases (planejadas)
| Fase | Ação | Aceite |
| --- | --- | --- |
| THEME-UX 0.8-A | auditar SDCs, slots, CSS, navegação, providers e consumidores **reais** | matriz do que existe/variante/lacuna; sem inventar evidência |
| THEME-UX 0.8-B0 | inventariar shell e renderização por purpose (GanjaLab420, Revista, Wiki, Fórum, Conta, Loja) | Institution + Domain Header presentes; conflitos mobile identificados |
| THEME-UX 0.8-B1 | criar/adaptar **context header interno** quando realmente necessário | shell global intacto; >=2 consumidores reais |
| THEME-UX 0.8-B2 | tabs responsivas, links reais e AJAX opcional do módulo | fallback sem JS, teclado, histórico correto |
| THEME-UX 0.8-B3 | estados universais: vazio, carregamento, erro, restrito e sucesso confirmado | mensagem acessível e ação verdadeira |
| THEME-UX 0.8-B4 | reader, resource-list, progress/timeline por demanda | tokens e compatibilidade multimódulo |
| THEME-UX 0.8-B5 | catalogar stories + homologar dark/light, A11y, 320–1440px | WCAG 2.2 AA e evidências Runtime |

**Ordem de implementação:** reutilizar → variante/slot → SDC transversal → adaptador especializado. Nunca gerar vários SDCs vazios na mesma fase. Só implementar quando há consumidor real e segundo consumidor validável; atualizar documentação, catálogo e story no mesmo commit. Respeitar o ciclo Dev → RC → Alpha.

## Matriz mínima de qualidade
- 320, 360, 390, 768, 1024, 1280, 1440 px, zoom 200–400%, teclado, foco, sem JS, reduced motion, modos light/dark **com geometria idêntica**.
- Instituição/Domain Header preservados em **todos os purposes**, inclusive em AJAX e mudanças de URL.
- Props/slots tipados e render arrays com attachments/cache; Twig sem queries, permissões, dados de usuário ou Group; states em origem oficial.
- Biblioteca UI Patterns: story simples, variações, vazio/erro se necessário; componente permanece `experimental` até integração validada.
- Evidência de consumidores reais e testes; sem declarar PASS com base em wireframe.

## Links
- [Shell multidomínio](shell.md)
- [Arquitetura](architecture.md) · [Componentes](components.md) · [Catálogo de SDCs](component-catalog.md)
- [Auditoria cruzada](../../../../../docs/portal/GANJALAB420-CROSS-VISUAL-AUDIT.md)
- [Cabeçalho e navegação 0.8-B](../../../../../docs/portal/GANJALAB420-SHARED-UI-PLAN.md)
- [Inventário B0/B1](../../../../../docs/portal/GANJALAB420-SHELL-CONTEXT-HEADER-B0-B1.md)

## Decisão de implementação visual — ferramentas UI Suite (2026-10-22)

**Core SDC + UI Patterns + UI Styles são os instrumentos preferenciais**, integrados ao Bootstrap 5 do ACULTA420. O uso de ferramentas já configuradas é obrigatório como **etapa de avaliação** antes de qualquer Twig customizado, nova dependência ou CSS isolado; não obriga a converter todos os displays. Ver [plano UI Suite existente](ui-suite-migration-plan.md) para o inventário real, versões e exceções.

| Ferramenta | Emprego na próxima fase | Limite |
| --- | --- | --- |
| **Drupal Core SDC** | contrato canônico de componentes, schemas de props, slots e assets locais | não possui ACL/queries/states funcionais |
| **UI Patterns 2.x** | mapear reutilização de SDC em Views, fields, formatters, blocks e layouts **onde integração estável já existir** | não converter displays massivamente |
| **UI Patterns Library** | stories `.story.yml`, variantes e estados para comparação visual com acesso administrativo | não exibir dados reais nem abrir permissões públicas |
| **UI Styles** | aplicar estilos semânticos ACULTA420 em blocos, Layout Builder, Views e UI Patterns | não duplicar tokens ou liberar valores arbitrários |
| **SDC Devel** | validações de metadados, propriedades e diagnóstico de componente no desenvolvimento | ferramenta dev, não interface de produção |
| **Fences** | adequar markup/wrapper semântico de campos quando justificado | não competir com UI Patterns Field no mesmo display sem razão |
| **Views + UI Patterns** | montar listas/cartões existentes (cursos, materiais, artigos, turmas) | providers e access/cache continuam no Drupal |
| **Section Library** | considerar composições salvas para site building onde for necessidade real | não substitui SDC; permissões restritas ao administrator |
| **VVJ (família já aprovada)** | reaproveitar apresentações de Views com consumidores adequados, como Gallery | não usar carrossel em navegação pedagógica por conveniência |

**Não instalar nem ativar UI Suite Bootstrap**: é fonte de ideias revisadas, não dependência nem novo tema base. **Não reinstalar Display Suite/`ui_patterns_ds`**, removidos por falta de consumidor. Não ativar UI Styles para CKEditor sem nova decisão; não tornar Storybook externo pré-requisito para stories no UI Patterns Library.

### Gate UI Suite da fase THEME-UX 0.8-A/B0
1. Inventariar componentes Core SDC, variantes, histórias e uso UI Patterns em displays reais (Views, campos, blocos e layouts); confirmar módulos habilitados no Runtime antes de assumir capacidades.
2. Auditar estilos semânticos UI Styles e wrappers Fences existentes; classificar cada lacuna em **reusar SDC / integrar UI Patterns / ajustar UI Styles / variante / novo SDC**.
3. Prototipar primeira composição de **Context Header** abaixo do cabeçalho multidomínio, preferindo Core page title/breadcrumb + componente `content-section`; só criar novo SDC quando a lacuna for demonstrada.
4. Prototipar **Context Tabs** com menu/breadcrumb/links do Core e Bootstrap; UI Patterns pode compor apresentação, não controlar o AJAX do GanjaLab.
5. Prototipar estados genéricos em biblioteca protegida do UI Patterns; garantir acesso, cache, mobile 320px, dark/light e WCAG 2.2 AA no Runtime.
6. Atualizar catálogo, stories e documentação do **tema** no mesmo commit funcional; conservar a matriz de consumidores cruzados.

**Status deste capítulo:** somente planejamento/documentação; a auditoria Runtime e os protótipos ainda não foram executados.

## Revisão normativa — elegibilidade de reutilização SDC/UI Suite (2026-10-22)

**Regra obrigatória:** apenas reutilizar diretamente componentes que já sejam **Drupal Core SDC**, com schema/slots/assets próprios e aptos ao ecossistema **UI Patterns + UI Styles** do ACULTA420. Um template Twig isolado, widget legado JS, conjunto avulso de classes Bootstrap ou bloco PHP renderizado sem contrato SDC **não conta como componente reutilizável aprovado**. Recursos nativos Drupal/Bootstrap continuam infraestrutura e podem ser insumos, mas sua apresentação reutilizável deve ser encapsulada ou composta por SDC.

**Classificação auditável:**
1. **SDC-CONFORME:** componente SDC Core válido, metadados e props/slots documentados, integrado/compatível com UI Patterns e estilos semânticos UI Styles; elegível a reuso após checar consumidores e runtime.
2. **SDC-PARCIAL:** há SDC, mas schema, slots, stories, integração UI Patterns/UI Styles ou acessibilidade exigem alinhamento; corrigir antes de reutilizar em nova área.
3. **LEGADO-MIGRAR:** padrão visual útil, porém implementado somente em Twig/JS/PHP/Bootstrap sem SDC; pode servir de **referência funcional/visual**, jamais ser copiado ou compartilhado diretamente. Primeiro migrar para SDC e integrar ao catálogo.
4. **DESCARTAR/SUBSTITUIR:** solução redundante ou incompatível; substituir por SDC do tema e/ou recurso UI Suite apropriado, preservando dados, ACL e providers.

O fluxo passa a ser **inventariar → classificar SDC/UI Suite → adequar/migrar → validar story e consumidores → reutilizar/estender → criar SDC transversal apenas se faltar**. Não reaproveitar componente legado antes do gate de adequação. Exceção: interfaces *funcionais* de terceiros (PDF.js, H5P, formulários Core) são mantidas como providers, não copiadas ou reescritas como componentes; a camada visual compartilhável ao redor delas segue SDC.

**Gate obrigatório:** componente elegível deve ter arquivo `.component.yml` válido e Twig de componente, props/slots explícitos e cache renderizável preservado, histórias no UI Patterns Library, uso de tokens/estilos semânticos UI Styles onde adequados, WCAG 2.2 AA, light/dark e evidência de pelo menos um consumidor real. A integração UI Patterns não deve ser forçada quando incompatível com o consumidor; nesse caso documentar explicitamente a compatibilidade e a razão técnica. A ausência do padrão **bloqueia reutilização em novas áreas**.

## Primeiro slice transversal M2/B1

O contrato [Context Header do tema](theme-ux-0.8-m2-b1-context-header.md) prioriza Core page title/breadcrumb e SDC existente antes de criar novo componente. Caso necessário, criar apenas SDC com dois consumidores reais, UI Patterns/Styles e stories, sem substituir Institution Bar e Domain Header. Documentação concluída; execução funcional depende da auditoria Runtime.
