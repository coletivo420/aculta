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

## Decisão B1-02 — H1 contextual e evidência de consumidores

O [plano B1-02](theme-ux-0.8-b1-02-ownership-h1.md) autoriza Context Header SDC a ser o proprietário do único H1 visível em consumidores migrados, sem preservar obrigatoriamente a saída nativa. O título segue provido pelo Drupal; remover duplicação seletivamente e jamais ocultar com CSS. A [inspeção B1-02a](theme-ux-0.8-b1-02a-consumer-code-map.md) detectou que o SDC Wiki `wiki-entry` já produz H1 e precisará de integração coordenada; o shell multidomínio continua obrigatório. Sem implementação Runtime.

## Avanço B1-02b — contrato visual multiárea

[Contrato visual Context Header](theme-ux-0.8-b1-02b-visual-contract.md) conecta decisões arquiteturais SDC/UI Suite a composição mobile-first para GanjaLab420 e Wiki, H1 único, UI Patterns/Styles e preservação do cabeçalho multidomínio. Próximo B1-02c só cria SDC após evidência Runtime dos consumidores; backlog B2 de abas contextuais permanece posterior.

## Conclusão de código B1-02d, abertura B1-02e/B2

`aculta420:context-header` possui dois consumidores integrados por código: Wiki verbete completo e dashboard `/ganjalab420`. A renderização contextual H1 e a supressão seletiva do page-title estão documentadas na [matriz B1-02e](theme-ux-0.8-b1-02e-runtime-validation.md). Não marcar homologação sem testes. Próximo planejamento visual é B2 (abas de contexto com SDC/UI Suite, sem reutilizar `product-tabs` para navegação de rotas).

## Situação de transição B2 → B3

B2 tem dois consumidores conectados em código (GanjaLab e Wiki) mas permanece pendente de homologação Runtime; ver [B2-05](theme-ux-0.8-b2-05-static-gates.md). A próxima fase B3 inicia por [auditoria de feedback](theme-ux-0.8-b3-feedback-audit.md), reutilizando somente SDCs alinhados a UI Suite, não copiando alertas Drupal e preservando o shell global.

## Continuidade B6 — inventário atualizado da branch

O tema já dispõe de `context-header`, `context-tabs` e `empty-state` como SDCs. A fotografia estática posterior contabilizou **41 metadados SDC / 65 stories**. A evolução visual segue em [B6: consolidação](theme-ux-0.8-b6-consolidation.md), sem duplicar padrões e sem declarar UI Patterns/Styles em Runtime verificados. Os números de inventário anteriores são históricos e não devem ser usados como contagem atual.

## Avanço B6-S1 — 2026-10-22

O inventário da árvore da PR #297 confirmou **41 SDCs** no tema. A [matriz B6-S1](theme-ux-0.8-b6-s1-contract-audit.md) formaliza os caminhos e os critérios de revisão individual. Este marco é **inventário estático**, não homologação de contratos, story rendering ou Runtime. Próxima microfase: **B6-S1a**, conferir metadata/Twig/CSS/stories de cada componente e registrar achados por arquivo; depois **B6-S1b**, rastrear consumidores por código/configuração. Corrigir somente inconformidades demonstradas em B6-S2; o gate B6-R permanece no homelab. O shell multidomínio e a exigência de SDC/UI Suite para reuso continuam regras obrigatórias.


## B6-S1a — lote prioritário de contratos (2026-10-22)

[Relatório B6-S1a](theme-ux-0.8-b6-s1a-contract-findings.md): inspecionados 13 contratos SDC e 7 templates Twig, com matriz por componente e três findings de seguimento. Os 41 SDCs e 65 stories foram contados por caminhos na árvore. **Não representa auditoria integral, validação do schema Core nem gate Runtime.** Próxima etapa B6-S1a-L2: metadados restantes e Twig/CSS/stories individualizados, depois consumidores B6-S1b e correções B6-S2. Permanecem inegociáveis o shell multidomínio e o reuso somente por SDC/UI Suite.


## B6-S1a-L2 — metadados (2026-10-22)

[Auditoria de metadados L2](theme-ux-0.8-b6-s1a-l2-metadata-audit.md): os 41 arquivos `.component.yml` foram lidos em dois lotes; a árvore registra 41 Twig, 32 CSS e 65 stories, ainda sem inspeção exaustiva do conteúdo. **Quatro componentes de doação referenciam o schema SDC legado** e exigem migração controlada em B6-S2. Próximo slice: **B6-S1a-L3**, revisão integral Twig/CSS/stories; B6-R permanece pendente de homelab.


## B6-S1a-L3 — inspeção prioritária de assets (2026-10-22)

A [auditoria B6-S1a-L3](theme-ux-0.8-b6-s1a-l3-priority-assets.md) leu 5 CSS e 3 stories e registrou divergência concreta em `cart-offcanvas.default.story.yml` (`items` ausente da metadata). O inventário atual contém 41 Twig, 32 CSS e 65 stories. **L3b** examinará os assets ainda não inspecionados; **S1b** rastreará consumidores; **S2** tratará correções reproduzidas. Shell multidomínio preservado e Runtime não homologado.


## B6-S1a-L3b — revisão dirigida (2026-10-22)

[Relatório L3b](theme-ux-0.8-b6-s1a-l3b-asset-findings.md): Twig do carrinho confirmou `sites` e `site.items`; story corrigida sem alterar o renderer. Inspecionados sete arquivos adicionais de Twig/stories, com limites documentados. **B6-S1a-L3c** segue para revisão sistemática dos demais assets e **B6-S1b** para consumidores. Homologação Runtime não executada.


## B6-S1a-L3c — auditor estático versionado (2026-10-22)

O [gate B6-S1a-L3c](theme-ux-0.8-b6-s1a-l3c-automated-gate.md) adicionou `scripts/audit-aculta420-sdc-assets.php`: varredura somente leitura de metadata/Twig e chaves das stories, schema Core e contagens. **Script ainda não executado com Composer; nenhuma aprovação presumida.** Seguir B6-S1a-L3d (executar, registrar achados), B6-S1b (consumidores) e B6-S2 (correções pequenas). Preservar shell multidomínio e padrão Core SDC/UI Suite.


## Wiki420 — trilha visual W420-UX 0.1 (2026-10-22)

O plano [Wiki420 — Experiência visual](../../../../../docs/portal/WIKI420-VISUAL-EXPERIENCE.md) organiza Home, busca, categorias, verbete enciclopédico, revisões, discussão contextual e colaboração, utilizando Core SDC/UI Patterns/UI Styles. **Ownership de componentes, tokens e CSS transversais: ACULTA420; regras, rotas e acesso: aculta_wiki/Core/Portal.** Reutilizar `context-header`, `wiki-entry`, `context-tabs`, `figure`, `empty-state` e `card` apenas após gate por consumidor. Institution Bar + Domain Header preservados. W420-UX 0.1-A é próximo slice estático; 0.1-R é Runtime.


## WE SATIVA — linha própria WES-UX 0.1 (2026-10-22)

[WE SATIVA — Wiki Editor Sativa](../../../../../docs/portal/WE-SATIVA-VISUAL-EDITOR.md) será o frontend de contribuição da Wiki420, **separado de GanjaLab/GanjaLab420**, para público editorial mais amplo segundo acesso real do Drupal. Wiki pública **e** editor serão mobile-first. Compor `context-header`, `context-tabs`, `card`, `empty-state` e Form API/CKEditor com SDC/UI Suite; evitar componentes legados ou um segundo editor. O shell multidomínio permanece em todas as telas. Fases WES-UX 0.1-A–R: auditoria, protótipos, providers/rotas, composição visual, fluxos, qualidade e homologação.


## WE SATIVA 0.2 — contribuição a partir do verbete

[Pesquisa e fluxo contextual](../../../../../docs/portal/WE-SATIVA-CONTRIBUTION-FIRST-RESEARCH.md): o caminho preferencial sai da leitura Wiki420, escolhe uma microtarefa e abre o editor WE SATIVA apropriado, sem exigir passagem pelo GanjaLab. Reusar SDC Core/UI Patterns/UI Styles e Form API/CKEditor 5, sem criar engine de edição ou workflow duplicado. Entrada, ações e estados são condicionados a ACL/rotas reais; visual mobile-first e shell global inalterado.


## WES-UX 0.2-A0 — fundação aprovada e iniciada

O [contrato de fundação WE SATIVA](../../../../../web/modules/custom/aculta_portal/modules/aculta_wiki/docs/WE-SATIVA-FOUNDATION.md) detalha intents, ownership de Form API e controle de acesso, ordem das microfases e restrições de implementação. **Autosave Form postergado**. Primeiro mapear consumidores/rotas/permissões; componentes compartilhados precisam de Core SDC/UI Patterns/UI Styles. Wiki pública e editor permanecem mobile-first com Institution Bar + Domain Header em todas as telas.


## WE SATIVA 0.3 — composição da arquitetura interna

[Arquitetura completa das áreas WE SATIVA](../../../../../docs/portal/WE-SATIVA-INTERNAL-ARCHITECTURE.md): início, meu trabalho, contribuir, ajuda e contextos editoriais (novo verbete, editor completo, fontes, mídia, revisões). O tema fornece exclusivamente SDC/UI Patterns/UI Styles, sem duplicar Form API, workflows ou o shell multidomínio. Primeiro validar a conformidade e consumidores de `context-header`, `context-tabs`, `card`, `editorial-card` e `empty-state`. Todas as telas mobile-first.


## WE SATIVA C01 — componente de entrada (WES-UX 0.3-B1)

[Contrato de composição C01](../../../../../docs/portal/WE-SATIVA-COMPONENT-01-CONTRIBUTION-ENTRY.md): reutilizar `context-header` para convite/heading e `card` para escolhas condicionais, `context-tabs` e `empty-state` apenas se houver consumidor real. Nenhum novo SDC até provar lacuna; primeiro inventariar a ação no Twig full da Wiki e ACL/rotas. Institution Bar e Domain Header seguem inalterados; mobile-first.


## WE SATIVA C01-A — escolha de slot e gate de acesso

[Análise C01-A](../../../../../docs/portal/WE-SATIVA-C01-A-ACCESS-AND-CTA-AUDIT.md): `context-header.actions` é a posição candidata para um único CTA `Contribuir`, fornecido como render array autorizado pelo kernel Wiki; `wiki-entry.actions` já contém operações Core e **não pode duplicar o convite**. O tema não gera URL/permissão e não remove operações Core por CSS. A próxima etapa C01-B especifica o contrato de intents e o acesso mínimo; o comportamento só será implementado após rota e testes. Mobile-first em Wiki e WE SATIVA, SDC/UI Suite e shell global preservados.


## WE SATIVA C01-B — confirmação de e-mail + moderação

[Contrato C01-B](../../../../../docs/portal/WE-SATIVA-C01-B-CONTRIBUTOR-MODERATION-CONTRACT.md): papéis exportados já permitem a `email_confirmed` criar/editar verbetes próprios e enviar à revisão, porém **não publicar**; `content_editor` revê e publica. Microtarefas em conteúdo de terceiros aguardam mecanismo seguro de propostas, sem ampliar `edit any`. O tema exibirá `Contribuir` uma única vez no `context-header.actions`, somente com URL/access válidos, e seguirá mobile-first com shell multidomínio intacto.


## Wiki420 / WE SATIVA — moderação antes da próxima fase visual

O [plano WES-MOD 0.1](../../../../../web/modules/custom/aculta_portal/modules/aculta_wiki/docs/WE-SATIVA-MODERATION-PLAN.md) estabelece a primeira publicação de conta `email_confirmed` como moderada e cria a especificação da fila editorial Wiki. Serviços neutros reutilizáveis entre Fórum, Wiki e demais kernels passam pelo `aculta_user` conforme [política transversal](../../../../../docs/architecture/ACULTA-SHARED-MODERATION-AND-KERNEL-POLICY.md), mantendo dados/workflows por contexto. **Adiar inspeção/refatoração visual até concluir o desenho de contratos, acesso e ownership**. Depois compor UI SDC/UI Patterns/UI Styles mobile-first, sem shell duplicado.

## Retificação de ownership (2026-10-22)

**A política compartilhada de moderação e primeira participação reside em `aculta_user`**, estendendo `ParticipationRules`, sem migração para `aculta_portal`. Wiki e Fórum permanecem donos de filas/workflows e entidades próprias. Esta decisão substitui indicações anteriores de centralizar essa política no Portal. [Contrato revisado](../../../../../docs/architecture/ACULTA-SHARED-MODERATION-AND-KERNEL-POLICY.md).
