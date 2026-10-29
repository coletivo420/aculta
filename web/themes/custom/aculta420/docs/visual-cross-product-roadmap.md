## DECISÃO GLOBAL - ACULTA420 inteiramente mobile-first (2026-10-29)

**Regra obrigatória para todo o tema, sem exceção por purpose:** definir a apresentação base a partir do viewport móvel (mínimo 320 px), com aprimoramento progressivo desktop, preservando ordem DOM, foco, acessibilidade, semântica, shell institucional e Domain Header. Converter progressivamente os estilos, layouts, componentes SDC, formulários, navegação, mídia, tabelas, overlays, modal e UI Patterns do ACULTA420, não apenas CSS de Studio.

**THEME-MF0:** inventariar bibliotecas, estilos, `@media`, `@container`, breakpoints, conflitos Bootstrap e overflow de **todas** as telas e purposes. **THEME-MF1:** tokens, base CSS e guidelines mobile-first. **THEME-MF2:** migrar por famílias com stories reais e correção de cascata; não trocar `max-width` por `min-width` mecanicamente. **THEME-MF3:** testar responsividade 320/360/390/599/600/768/1024/1280/1440, zoom 200/400%, teclado, no-JS, modo claro/escuro, reduced motion, formulários/mídia/tabelas e shell em Fórum, Wiki, Revista, Loja, Eventos, GanjaLab, Conta e demais purposes. Registrar bloqueios por proprietário.

A fidelidade visual ao OpenCulturas Next (`opcult`) continua **escopada ao Studio**; a fundação mobile-first é **global**. O Green Stash é uma **área integrada ao Studio**, não uma experiência independente ou shell próprio; utiliza Commerce como owner funcional e componentes do tema. Dependência: THEME-MF3 aprovado antes de OCS-F11. [Plano vigente](../../../../../docs/architecture/PLANO-VISUAL-OPENCULTURAS-NEXT-ACULTA-STUDIO.md).

## Green Stash na frente visual Studio - correção inicial (superada)

Este registro preserva uma retificação intermediária que foi corrigida pelo
plano normativo posterior. Green Stash é área de gestão comercial **integrada**
ao Studio. A sequência de fases e entregas é a do plano vigente, não a deste
trecho histórico.

Para Green Stash, usar SDCs compartilhados com variantes adaptadas a operação comercial e navegação interna mobile-first, nunca duplicar shell, checkout ou UI administrativa. Não mostrar recursos ainda em plano como operacionais; ACL por loja, dados privados e ações de pagamento/estoque/envio permanecem em serviços Commerce. Capturas e testes devem incluir usuários de lojas distintas, sem JS, 320 px e modo escuro. Só após F11 homologada aplicar Aculta Identity em toda a plataforma, inclusive Green Stash.


## Correção da frente visual Studio - todas as experiências (superada)

O roteiro resumido nesta seção foi substituído pela sequência vigente do [plano visual](../../../../../docs/architecture/PLANO-VISUAL-OPENCULTURAS-NEXT-ACULTA-STUDIO.md), que define OCS-F0-F11 antes de OCS-A1-A4. É obrigatório aplicar padrões Next ao Hub, Marley, GanjaLab, WE Sativa, Green Stash integrado, Conta, Perfil e demais experiências reais inventariadas.

O tema fornece padrões transversais de grid, cards, tabs, edição, seções, estados e navegação contextual; cada experiência expõe render arrays/ACL/links pelos serviços donos. Menu Studio deriva do registro de experiências e permissões, não é uma lista estática no CSS/Twig. Não criar SDCs específicos de GanjaLab/WE Sativa se um componente transversal com variante resolve. Instituição/Domain Header continuam invariáveis. **OCS-F10** é o gate de fidelidade; identidade Aculta apenas após aceite. Testar também outros purposes para evitar vazamento CSS.

## Frente Studio: Fidelity → Identity - OCS-F/OCS-A (2026-10-29)

[Plano de implementação e critérios](../../../../../docs/architecture/PLANO-VISUAL-OPENCULTURAS-NEXT-ACULTA-STUDIO.md). **Tema de origem é `opcult` (OpenCulturas Next)**; `openculturas_base` é legado. Etapa F reproduz estrutura, tipografia, espaçamento, componentes, cards, navegação e formulários do Next, mas somente **no contexto interno do Studio**. Etapa A substitui a identidade visual por tokens institucionais, sem reimplementar contratos funcionais.

- [ ] F0 inventário `opcult` → SDC/consumer, matriz de paridade e telas-benchmark.
- [ ] F1 tokens e fundação de fidelidade escopados; F2 navegação/contexto; F3 reaproveitar `patterns/card`, `context-header`, `context-tabs`, `empty-state`, `content-section` e SDCs de Perfil antes de criar componentes.
- [ ] F4 dashboard e F5 Marley com dados reais; F6 Conta e Perfil respeitando S-R4/S-R5 e `/conta-legada`.
- [ ] F7 aprovar fidelidade em capturas, UI Patterns, Firefox ESR, acessibilidade, privacidade, claro/escuro e demais purposes.
- [ ] A1-A4 identidade Aculta, ajuste visual e limpeza de overrides sem alteração de slots/props.

**Não modificar Institution Bar ou Domain Header**, nem globalizar os estilos OpenCulturas; não ativar `opcult`, não criar CSS/Sass build paralelo. Auditar licença de fontes, ícones e SVGs antes de qualquer porte. A frente B detém tema, SDCs e CSS; arquivos de serviços/controladores/rotas pertencem à frente A.

# ACULTA420 — Roadmap visual transversal (planejamento)

**Linha de planejamento:** THEME-UX 0.8 · **Data:** 2026-10-22 · **Estado:** plano; OCS-F0 documental em andamento, sem alteração visual nem gates Runtime aprovados.

## OCS-F0 — OpenCulturas Next → Studio (2026-10-29)

Inventário 3.1.5 e matriz de paridade inicial ficam em
`studio-visual/upstream-inventory.md` e `studio-visual/visual-parity-matrix.md`.
O escopo permanece no Studio. Institution Bar e Domain Header não entram na
camada de fidelidade. Nenhuma paridade de tela autenticada está homologada por
esta documentação.

## Decisão arquitetural obrigatória
**Preservar Institution Bar + Domain Header multidomínio existentes em TODAS as áreas.** A evolução visual pode aperfeiçoar responsividade, contraste, espaçamento, navegação mobile e light/dark, mas não substitui, oculta ou duplica o shell. O futuro Context Header é **subcabeçalho de página** e não um cabeçalho global. Domain/purpose, URLs, dados, ACL e cache ficam no Portal/providers; tema somente apresenta.

## Transferência formal de escopo
O planejamento inicial nasceu na UX do estudante [GanjaLab420 0.8](../../../../../docs/portal/GANJALAB-VISUAL-QUALITY-GATES.md), mas **componentes, tokens, CSS, histórias e padrões visuais compartilhados são backlog do TEMA**. A integração funcional por módulo permanece em suas documentações próprias. O tema não cria progressão LMS, certificados, envio de respostas, operações H5P, notas ou PDF.js paralelo.

## Inventário / escolhas atuais
- Confirmado na árvore do tema: `patterns/card` com slots e variantes; `content/course_card`; `patterns/rail`, `content-grid`, `content-section`, `figure`, `quote`; `content/author-card`; componentes Messenger, avisos, QR e Fórum.
- Existe shell multidomínio, Bootstrap 5, tokens semânticos e catálogo UI Patterns stories.
- `context-header`, `context-tabs` e `empty-state` já existem na árvore; sua aprovação depende de integração e validação conforme o catálogo. **Lacunas ainda não implementadas:** content-reader, resource-list, status-timeline e next-action. Primeiro verificar se Core/Bootstrap/SDCs atuais já satisfazem o uso.
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

## Candidatos da segunda leva OpenCulturas (2026-10-28)

Origem: `docs/architecture/OPENCULTURAS-COMPARATIVO.md`, seção 9. **Regra da revisão de 2026-10-22 vale:**
cada candidato só vira componente depois de verificar se um SDC existente já atende, e precisa de
`.component.yml`, slots explícitos, story no UI Patterns, tokens, WCAG 2.2 AA, modo claro e escuro, e
pelo menos um consumidor real. Template ou bloco do OpenCulturas é referência, nunca cópia.

| Padrão | Resultado da auditoria B3/B4 | Estado atual |
| --- | --- | --- |
| `feedback/stat-counter` (I-29) | Não há SDC equivalente; criado com valor/rótulo, e a supressão abaixo de cinco fica no provider. | Candidato; sem consumidor real. |
| `content/accessibility-badges` (I-28) | `patterns/figure` cobre mídia/legenda, não a lista de recursos; candidato textual criado. | Candidato; sem consumidor real de eventos/locais. |
| Menção na imprensa (I-26) | `content/editorial-card` atende via slots; story de uso adicionada, sem SDC duplicado. | Candidato de uso; sem consumidor real. |
| `content/sponsor-logos` (I-27) | Candidato criado com slot para mídia do Drupal/aculta_image. | Sem consumidor; cada marca exige autorização registrada. |
| `patterns/bookmark-toggle` (I-31) | Composição do SDC envolve a ação nativa `flag_salvar_topico` no tópico completo do Fórum. | Candidato consumido por código; validação visual Runtime pendente. |
| Layout 5 regiões (I-21) | UI Patterns Layouts 2.0.23 está instalado no código e habilitado em Config Sync; layout criado com cinco slots. | Experimental; sem consumidor real enquanto `aculta_events` não implementar F3. |
| `layouts/page-columns` (I-22) | Layout SDC com regiões nomeadas principal/secundária/complementar, stories 1/2/3 colunas e colapso mobile em ordem DOM. | Candidato sem consumidor real; story não renderizada no Runtime UI Patterns. |

Resultado: stories existem; não declarar aprovação sem validação do schema,
renderização/cache no Runtime e revisão visual autorizada.

## Execução Frente B — 2026-10-28

- **B1:** `patterns/card` ganhou `compact`, story e consumidor na View de álbuns.
- **B2:** tokens e modificadores fechados de seção; contraste mínimo calculado de
  4,69:1. O bloco básico agora apresenta `field_section_color`, separado de
  `field_section_variant`, e passa a escolha pelo hook `aculta_section`.
- **B3:** cada padrão avaliado; novos SDCs ficam experimentais/candidatos sem
  consumidor ou validação suficientes. `editorial-card` foi reutilizado para
  menções; a flag do Fórum foi composta por `bookmark-toggle`.
- **B4:** layout UI Patterns de cinco regiões criado; o módulo Eventos ainda não
  fornece consumidor.
- **B5:** Stylelint e ESLint configurados em `package.json`; os dois scripts
  passaram. Verificação visual das mudanças Twig continua pendente de autorização
  para `drush cr`.

## Revisão visual B7 — 2026-10-28

Firefox ESR confirmou o card compacto na View pública de álbuns em 1280 px,
320 px e dark; em 320 px a página não transborda. A revisão encontrou links
vazios sem nome gerados por duas opções de link concorrentes na View. O Config
Sync foi corrigido e aplicado por import parcial restrito a
`views.view.aculta_albuns`, depois de snapshot com hash do Runtime. A verificação
Firefox ESR em desktop, 320 px e dark confirmou zero links sem nome/placeholders
nos dois cartões compactos.

No tópico do Fórum, os estados salvar/não salvo foram fotografados em desktop,
320 px e dark. O controle tem foco visível de 3 px, alvo mínimo de 44 px e rótulo
textual; a Flag do usuário de teste voltou ao estado inicial. As rotas
protegidas do UI Patterns abriram após corrigir descrições YAML inválidas. As quatro cores, bookmark-toggle, layout de cinco regiões,
stat-counter, accessibility-badges e sponsor-logos foram capturados no Firefox
ESR. As stories sem consumidor permanecem experimentais/candidatas. Drawers
ocultos elevam `scrollWidth` em algumas stories, mas `overflow-x: clip` impede
rolagem real em 320 px (`maxScrollX=0`).

## B8 — composições de página em colunas

Auditoria estática: `rail` não atende colunas de página por ser uma faixa
horizontal; `content-section` fornece um único corpo; UI Patterns Layouts não
inclui grades genéricas 1/2/3. Criado `layouts/page-columns`, candidato sem
consumidor funcional, com três stories e stack de uma coluna abaixo de 768 px.
As três stories foram capturadas no Runtime em desktop, 320 px e dark. O layout
empilha regiões na ordem DOM. Drawers offcanvas ocultos elevam `scrollWidth`,
mas não permitem rolagem real em 320 px (`maxScrollX=0`). Sem consumidor real,
permanece candidato.

## B9 — grade mista de conteúdo, termos e links externos

Os consumidores existentes foram inventariados antes da alteração: ações
GanjaLab em `MarleyController`; cursos e certificados em
`LearnerDashboardComposer`; escolhas WE SATIVA em
`WikiContributionIntentController`; listagem WE SATIVA em
`WikiContributorWorkspace::listSection`. Como o slot renderizado `items` já
aceita esses tipos heterogêneos, o contrato props/slots não mudou. A nova story
mistura os três tipos na mesma grade. A primeira captura revelou que a regra de
três colunas precedia a media query; a ordem foi corrigida e o empilhamento
validado em 320 px, além das capturas desktop/dark.

## Links
- [Shell multidomínio](shell.md)
- [Arquitetura](architecture.md) · [Componentes](components.md) · [Catálogo de SDCs](component-catalog.md)
- [Auditoria cruzada](../../../../../docs/portal/GANJALAB-CROSS-VISUAL-AUDIT.md)
- [Cabeçalho e navegação 0.8-B](../../../../../docs/portal/GANJALAB-SHARED-UI-PLAN.md)
- [Inventário B0/B1](../../../../../docs/portal/GANJALAB-SHELL-CONTEXT-HEADER-B0-B1.md)

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


## USER-PART 0.1-A — dependência visual WE SATIVA

[Inventário de Fórum, LMS e Wiki](../../../../../web/modules/custom/aculta_portal/modules/aculta_user/docs/USER-PART-0.1-A-CROSS-KERNEL-INVENTORY.md) documentado. O tema somente compõe SDC/UI Suite e preserva cabeçalhos global/domínio. A UI de moderação mobile-first deverá receber estados e links autorizados dos respectivos subkernels após a política de primeira participação no `aculta_user` estar comprovada por testes. Não migrar regras ou lógica de participação para CSS/Twig.


## XK-10 — visual das centralizações (2026-10-22)

A [auditoria transversal](../../../../../docs/architecture/ACULTA-CROSS-MODULE-CENTRALIZATION-AUDIT-2026-10.md) define kernels de dados e serviços. Fóruns Group restritos a associados ativos precisam de ACL efetiva, sem esconder conteúdo só por CSS. Wiki/WE SATIVA, LMS, Fórum, Messenger e Group seguem mobile-first, Core SDC/UI Patterns/UI Styles, com Institution Bar e Domain Header preservados. Fase visual só após contratos de acesso/ownership e histórias reais.

## ACULTA-XK 0.1-B — contrato arquitetural (2026-10-22)

XK 0.1-B: definições de participação, avisos e ACL Group foram registradas antes das alterações visuais. UI do WE SATIVA, Fórum e LMS continua mobile-first, conforme Core SDC/UI Suite; não esconder acesso por CSS e manter Institution Bar + Domain Header multidomínio.

Fonte de verdade: [ADRs XK-01 a XK-03](../../../../../docs/architecture/ACULTA-XK-0.1-B-SHARED-CONTRACTS.md). **Somente planejamento, sem alteração funcional ou testes Runtime.**

## WES-UX 0.4 — projeto visual por telas (2026-10-22)

[Inventário de 12 estruturas WE SATIVA](../../../../../docs/portal/WE-SATIVA-VISUAL-SCREENS-0.4.md), incluindo entrada pelo verbete, início, Meu trabalho, microtarefas, novo verbete, CKEditor, prévia, revisão, feedback, ajuda, fontes e mídia. Sequência V-0.4-A–R, mobile-first em toda a Wiki, componentes Core SDC/UI Suite exclusivamente, Institution Bar + Domain Header preservados. Cada tela depende de providers, estados e permissões reais; não implementar links fictícios. Autosave apenas futuro.

## WES-UX V-0.4-B — entrada, início e Meu trabalho

[Especificação detalhada das telas V01–V04](../../../../../docs/portal/WE-SATIVA-V-0.4-B-ENTRY-DASHBOARD-WORK.md): convite contextual único, seletor de intenções com ações realmente disponíveis, início com criação/continuação autorizadas e lista própria de trabalhos com filtros HTTP. Histórias WES-B-01 a WES-B-10, cenários vazios/erro/acesso, mobile-first 320px e claro/escuro. Somente Core SDC/UI Suite, sem rotas fictícias ou novas permissões. Próximo marco V-0.4-C (microtarefas e editor).

## WES-UX V-0.4-C — microtarefas, novo verbete, editor e prévia

[Especificação visual e funcional V05–V08](../../../../../docs/portal/WE-SATIVA-V-0.4-C-EDITOR-FLOWS.md): fluxos progressivos, 12 stories de aceite, Form API com CKEditor 5 **já configurado para o formato wiki**; toolbar exportada, Media integrado e upload direto de imagem desativado. Contas email_confirmed criam e enviam à moderação, content_editor publica. Sugestões de alteração de terceiros dependem de backend seguro. SDC/UI Suite, mobile-first, sem autosave nesta fase. Próximo V-0.4-D.


## V-0.4-D — divisão visual WE SATIVA x GanjaLab

[Janela de moderação Wiki no GanjaLab](../../../../../docs/portal/MARLEY-WIKI-MODERATION.md): visualização de fila, comparação e aprovação fica em Studio. WE SATIVA mantém escrita e acompanhamento, sem botões de publicação para colaborador. SDC/UI Suite rege ambas as superfícies, com mobile full-screen na revisão e desktop comparativo, nenhum cabeçalho global duplicado. Contratos entre Studio/Wiki definidos antes de componentizar.

## V-0.4-R / GanjaLab D-A — auditoria do código existente

[Inventário e contrato da janela Wiki](../../../../../docs/portal/MARLEY-WIKI-V-0.4-R-D-A-AUDIT.md). **A área Wiki em `WikiTab` já existe**: não criar outra aba/plugin. Acrescentar futuramente seção `moderacao` com gate próprio, pois permissão `create wiki_entry content` já abre a área para colaboradores e não equivale a permissão de moderar. StudioPresenter e URL/AJAX existentes serão preservados; `aculta_wiki` é o dono de fila, grants, revisões e POST. Ainda faltam exame dirigido do listing builder, adapter de acesso e testes Runtime. Design com SDC/UI Suite, mobile-first e shell multidomínio preservado.

## GanjaLab Wiki D-A.2 / D-B — listagens seguras e composição visual

[Auditoria de StudioListingBuilder e especificação dos componentes](../../../../../docs/portal/MARLEY-WIKI-D-A2-D-B-AUDIT-VISUAL-CONTRACT.md). Achados prioritários: cache de listagens por usuário/grants, consulta auxiliar de moderation_state com accessCheck(FALSE), necessidade de autorização explícita por revisão, acesso separado para aba Moderação e paginação. **Bloquear integração da fila até testes de caracterização e provider Wiki seguro**. D-B prevê cards mobile 320px, comparador empilhado no celular e paralelo no desktop, SDC/UI Suite, shell multidomínio e fallback HTTP. Não declarar Runtime PASS.

## D-A.2-T — progresso e próximos gates

D-A.2-T: a base de listagem do GanjaLab ganhou isolamento de cache por usuário e guarda por revisão; revisão visual D-B permanece planejada, condicionada a provider seguro. Não mascarar com SDC problemas de grants; preservar shell multidomínio e mobile-first.

## Decisão normativa: ACULTA Studio (2026-10-22)

All custom interfaces (GanjaLab, GanjaLab420, WE SATIVA and future consumers) share the ACULTA Studio interface foundation while aculta420 owns visual SDC/UI Suite components. Never reuse components outside that basis; preserve the global multidomain header. Distinct experiences need separate authorized navigation/purpose.

[Contrato e roadmap transversal](../../../../../docs/architecture/ACULTA-STUDIO-EXPERIENCE-PLATFORM.md). Esta decisão prevalece sobre terminologia anterior que equiparava o submódulo exclusivamente a GanjaLab. **Não declarar consumidores planejados como rotas implementadas.**

## Atualização STUDIO 0.1-B / D-A.2-T2

STUDIO 0.1-B: providers de experiência propostos para GanjaLab, WE SATIVA e futuras interfaces, com SDC/UI Suite como única camada de apresentação. D-A.2-T2 endurece listing de estados; nova fila Wiki somente após provider seguro e testes. Nenhuma alteração visual nesta etapa.

## OC315 - novo backlog de integração visual (2026-10-29)

Origem: [auditoria 3.1.5](../../../../../docs/architecture/AUDITORIA-OPENCULTURAS-3.1.5-2026-10-29.md). A presença estática dos SDCs não prova homologação. Integração funcional proposta, após gates existentes: (1) página de evento em cinco regiões e H1 único; (2) selos acessíveis ligados a campos reais; (3) listagem cultural filtrável com cartão atual e paginação; (4) marcas autorizadas, imprensa e overrides editoriais; (5) salvos por usuário com estado seguro. Registrar consumers e evidências de cada SDC; não importar CSS/Twig do `opcult`.
# Retificação de escopo — THEME-MF e OCS-F11 (2026-10-29)

Esta retificação é a decisão vigente para o trabalho visual: mobile-first
abrange **todo o tema ACULTA420 e todos os purposes**, enquanto a fidelidade
OpenCulturas Next é aplicada inicialmente somente ao conteúdo do Studio. A
Etapa de identidade Aculta não começa até a aprovação de THEME-MF3 e OCS-F11.
Green Stash é gestão comercial integrada ao ACULTA Studio; um provider técnico
do Studio não constitui shell, menu, autenticação ou plataforma independentes.

THEME-MF0 está parcialmente inventariada em
[`mobile-first-audit.md`](mobile-first-audit.md) e
[`theme-mobile-file-inventory.tsv`](theme-mobile-file-inventory.tsv). Este
inventário estático não prova responsividade. MF1–MF3, o gate OCS-F11 e
OCS-A1–A4 continuam pendentes; consultar o plano e a matriz de paridade atual.


## THEME-MF0 e OCS-F0 — evidência parcial (2026-10-29)

O inventário estático inclui 92 folhas CSS/Sass, 11 scripts, 98 templates,
20 bibliotecas e 85 pontos custom de anexação; os 10 purposes declarados estão
mapeados. A captura Firefox visível cobre homepage pública OpenCulturas e oito
URLs Studio a 390/1440, não o conjunto de purposes. `/studio` está vazio,
GanjaLab aponta conteúdo de Conta e Green Stash requer fixture autorizada. Ver
[`mobile-first-audit.md`](mobile-first-audit.md) e
[`studio-visual/visual-parity-matrix.md`](studio-visual/visual-parity-matrix.md).
Uma repetição instrumentada encontrou Institution Bar presente nas sete rotas
renderizadas, Domain Header ausente nas oito URLs capturadas e marcadores
`data-aculta-studio` somente em Marley. Nenhum gate MF3/F11 foi aprovado.

### Evidência Studio ampliada — 2026-10-29

A grade Firefox de nove larguras foi executada em oito URLs Studio (72
capturas) e na homepage pública upstream (nove). As sete telas que renderizaram
não apresentaram overflow horizontal medido; Hub vazio, GanjaLab com conteúdo
de Conta e Green Stash com acesso negado persistem em todos os tamanhos. A
Institution Bar apareceu nas sete páginas com conteúdo, o Domain Header não
apareceu em nenhuma URL e marcadores Studio foram encontrados somente em Marley.
A captura foi feita no checkout servidor `f2708e60f`, com árvore versionada
visual idêntica à PR em `5734bed87`, mas havia trabalho local de Eventos; logo a
evidência não é gate global nem homologação da cabeça atual da PR. MF3/F11
continuam pendentes.

### THEME-MF0 — raízes públicas por purpose (2026-10-29)

Foram capturadas as dez raízes canônicas nos nove viewports do gate: 90/90
páginas completaram e não tiveram overflow horizontal medido. Institution Bar
estava em todas; `.aculta-header` apenas em MAIN e Wiki (18/90), então o shell
multidomínio precisa de decisão/validação do owner em oito purposes. ACCOUNT
redirecionou ao login; Revista mostrou corpo vazio; a página MAIN divide a
palavra longa do título em 320 px. Conteúdo autenticado, formulários e fluxos
funcionais não foram exercitados. Detalhes e Pede-A em `mobile-first-audit.md`.

### THEME-MF1 — fundação global inicial

Adicionados tokens mobile-first para gutter, largura máxima e espaçamento
vertical, com aprimoramento a partir de 48rem; o container mantém o limite
existente de 1280px. A escala H1 agora começa em 2rem em 320px para reduzir
quebras forçadas de títulos longos. Firefox no checkout servido passou a suíte
Studio Marley nos viewports 320/390/768/1024/1440, mas isso não homologa toda a
fundação global nem conclui MF1. Ver `mobile-first-audit.md`.

### OCS-F1 — tokens de fidelidade iniciais

Mapeados ao prefixo `--aculta-studio-fidelity-*` os valores de cor e tabulação
do `opcult` 3.1.5, aplicados somente ao layout Studio de Marley. Nenhuma fonte
ou folha upstream foi copiada. A tipografia aguarda licença verificável.
Firefox pós-alteração passou a navegação, contraste e reflow em Marley; a
comparação não cobre todas as áreas nem um dashboard autenticado upstream, por
isso F1 segue parcial. Detalhes em `studio-visual/upstream-inventory.md`.

### THEME-MF2 — `content-grid`

Primeiro SDC global migrado: uma coluna na base mobile, duas colunas a partir
de 576px e três a partir de 992px. Consumidores encontrados em Marley, LMS e
Wiki; stories atualizadas. Firefox confirmou Marley em nove larguras sem
overflow e reflow esperado para a variante 2 colunas. LMS/Wiki e a variante de
3 colunas não tiveram consumer visível nesta rodada; MF2 permanece em
andamento.

### THEME-MF2 — `content-section`

Removido o `max-width: 575.98px` redundante: o gap `clamp(.75rem, 2vw, 1.5rem)`
já garante a base móvel e cresce fluidamente. Atualizadas cinco stories do SDC;
consumers reais identificados na Wiki. A captura pós-alteração da rota consumer
autenticada WE SATIVA/workspace passou 34/34 checks em 320/360/390/640/768/1024/
1280/1440 px sem overflow. O script não mede o gap do SDC diretamente; MF2
segue parcial. Evidência em `/home/piradopirata/suite-out/aculta-mf2-content-section-dd37b35d5/`.

### THEME-MF2 — `author-card`

Migrada a base móvel do cartão SDC compartilhado por Fórum, LMS e Wiki; 36rem
restaura a variante inline, mantendo os layouts editorial/empilhado, tamanhos e
overrides das listagens. Stories Fórum, LMS e Wiki atualizadas. `npm run lint:css`
e `npm run lint:js` passaram após instalar as dependências já fixadas no lockfile.
O Firefox encontrou 1px de overflow na data
em 576/600px; retirada a regra `nowrap` para permitir quebra natural. O teste
reutilizável confirmou a correção: 32/32 checks, consumer presente, alinhamento
mobile/inline correto e zero overflow em 320/360/390/575/576/600/768/1024/1280/
1440px. Capturas/JSON em `/home/piradopirata/suite-out/aculta-theme-author-card-892f803d5/`.

### THEME-MF2 — navegação `portal-account`

Migradas as regras do shell autenticado para mobile-first: sanfona e coluna única
na base; menu lateral, sticky e duas colunas a partir de 48rem. CSS candidato
passou 33/33 checks autenticados no Firefox em 320/360/390/599/600/767/768/
1024/1280/1440px sem overflow. A repetição integrada sem CSS override passou
33/33 após `drush cr`; evidência em
`/home/piradopirata/suite-out/aculta-account-shell-8768825cf/`. Tabelas de pedido
continuam com regras móveis próprias para fase posterior.

### THEME-MF0 — baseline SUPPORT / `donation-quick` (2026-10-29)

O consumer real de `donation-quick` no propósito SUPPORT passou 36/36
verificações em Firefox ESR visível, em 320/360/390/480/481/599/600/768/1024/
1280/1440px. Foram observados cinco cartões, reflow da grade e zero overflow.
O teste registra que a Institution Bar aparece, mas o Domain Header não é
renderizado nesse purpose; segue o Pede-A global, sem shell paralelo. Este é
baseline auditável, não implementação de THEME-MF2 nem gate MF3. Detalhes,
capturas e limitações estão em `mobile-first-audit.md`.

### THEME-MF2 — navegação global do shell (2026-10-29)

Regras da navegação responsiva migradas para base mobile, com a apresentação
horizontal restaurada em `min-width: 1100px`; nenhuma mudança no Twig ou JS.
Firefox visível passou 28/28 checks na home MAIN em 13 larguras, incluindo
abertura do botão, menu sem JavaScript, presença de Institution Bar e Domain
Header e zero overflow. A validação integrada após publicação/cache e os demais
purposes continuam pendentes; MF2 segue parcial. Ver detalhes em
`mobile-first-audit.md`.

### THEME-MF2 — Wiki pública

Header, formulários e grids de Wiki usam composição móvel como base e retomam as
dimensões anteriores em 576/768 CSS px. O smoke público aceita injeção da CSS
candidata local e mede 575/576/767/768 px; execução Firefox DEFERRED. O workspace
autenticado de WE Sativa exige rodada própria; ver `mobile-first-audit.md`.

### THEME-MF2 — composição institucional mobile-first (2026-10-29)

CTAs do hero e as grades institucionais/detalhes agora declaram primeiro o
reflow móvel; `min-width: 36rem` restaura os CTAs em linha e `min-width: 48rem`
restaura duas colunas. O CSS anterior e o candidato foram comparados em Firefox
ESR visível, com fixture sintético: 24/24 checks em 12 larguras, screenshots
idênticas e sem overflow. Lint CSS, SDC schemas, Design Foundations e browser
validators passaram. As páginas reais ainda não foram conferidas com este
candidato servido; THEME-MF3 segue pendente. Ver `mobile-first-audit.md` e
`tests/browser/aculta-theme-mobile-first/institutional-layout.py`.

### THEME-MF2 — Institution Bar global (2026-10-29)

As dimensões compactas agora são CSS base; `min-width: 768px` recupera o layout
desktop. O seletor móvel foi estendido ao `<ul class="nav flex-row">` usado
pela conta real, além de preservar o `.navbar-nav`. O baseline servido e a folha
candidata passaram 84/84 verificações no Firefox ESR, seis rotas e sete larguras
de 320 a 1440px, sem overflow. Capturas e JSON ficam em
`/home/piradopirata/aculta-suite-out/aculta-institution-bar-{baseline,candidate}/`.
Isto é um slice de MF2, não valida todo o shell/purposes e não conclui MF3.

### THEME-MF2 — Institution Bar integrada nos dez purposes (2026-10-29)

Após publicar `4d8993645` e reconstruir cache, Firefox ESR passou 140/140 checks
na Institution Bar em MAIN, Conta, SUPPORT, Revista, Wiki, Loja, Cursos, Fórum,
Links e Eventos, em sete larguras de 320 a 1440px, sem overflow. Hosts lidos de
`aculta_deployer/config/deploy.json`; `INSTITUTION_BAR_DOMAIN_ENV` permite
selecionar `production_host` na transição Alpha. `.aculta-header` foi renderizado
apenas em MAIN e Wiki (2/10 purposes); o Pede-A mantém a política de
rotas/disponibilidade no `aculta_portal`, sem regra funcional no tema. Links
esconde o utilitário por contrato e foi tratado como tal. Evidência e limites em
`mobile-first-audit.md`; MF3 não aprovado.

### THEME-MF2 — correção da Institution Bar ao rolar com Navigation

O tema agora exclui a Navigation top-bar em fluxo do cálculo de obstrução no
mobile e mantém o offset real quando ela fica fixa no desktop. Testes Firefox
visíveis de candidato autenticado, fallback sem JavaScript e homes anônimas dos
dez purposes passaram; validação pós-publicação/cache e o gate transversal
THEME-MF3 permanecem pendentes. Evidências e escopo em `mobile-first-audit.md`.

### THEME-MF2 — sticky/offset da Institution Bar repetidos no HEAD servido (2026-10-29)

Firefox ESR visível confirmou 99/99 checks anônimos nas dez homes em 320/390px e
38/38 checks administrativos na MAIN em 320/390/1023/1024/1280px, sem CSS/JS
override. A barra estabilizou sem salto e permaneceu sticky; em mobile respeita
a top-bar enquanto visível e passa ao topo quando ela sai da viewport; no
desktop acompanha o offset de 64px. Evidências e limites completos em
[`mobile-first-audit.md`](mobile-first-audit.md). Esta validação focal não
aprova THEME-MF3.


### THEME-MF2 — tabela mobile-first de pedidos na Conta

A apresentação das tabelas de pedidos e detalhes agora começa no layout mobile;
a partir de `48rem`, o tema restaura a tabela e confirma equivalência com as
medidas desktop Bootstrap anteriores. Firefox ESR visível passou 22 checks no
estado real vazio e em fixture sintético nas dez larguras de 320 a 1440px, sem
overflow. A linha real de pedido e o atributo `data-label` ainda estão DEFERRED,
pois a conta autorizada não tem pedido não-rascunho. Após publicação e rebuild,
a CSS servida repetiu 22 PASS/0 FAIL e uma DEFERRED; o snapshot e os validadores
estão registrados em `docs/operations/ACULTA-CONTINUOUS-EXECUTION.md`. A implementação é parcial
da THEME-MF2 e não homologa o gate THEME-MF3. Detalhes e limites em
`mobile-first-audit.md`.

### THEME-MF2 — Perfil público (2026-10-29)

`public-profile.css` passa a usar o perfil empilhado como base e recupera o
arranjo horizontal em `min-width: 36rem`, preservando espaçamentos computados
antes da migração. A primeira fixture Firefox fez 21/22 checks e levou à correção
de espaçamento em 320 px; repetição ficou DEFERRED por sessões concorrentes e
memória baixa. A página real ainda depende de sincronização/cache rebuild no
servidor de desenvolvimento. Esta fatia não aprova THEME-MF3. Ver
`mobile-first-audit.md` e
`tests/browser/aculta-theme-mobile-first/public-profile-layout.py`.

### THEME-MF2 — autenticação pública (2026-10-29)

`auth.css` usa espaçamentos compactos de login, registro e recuperação como
base, restaurando padding e margem amplos em `min-width: 36rem`. O harness de
fixture em 11 larguras foi criado; Firefox e validação das páginas reais estão
DEFERRED por falta de recursos enquanto a suíte visível da Revista está ativa.
Não é gate MF3. Ver `mobile-first-audit.md` e
`tests/browser/aculta-theme-mobile-first/auth-layout.py`.

### THEME-MF0 — inventário estático regenerável (2026-10-29)

`scripts/audit-aculta420-responsive-inventory.py --write` refaz o inventário
lexical do tema e inclui styles, JavaScript e Twig/HTML atuais. O snapshot atual
encontra 541 arquivos no tema, 92 folhas de estilo, 12 scripts JS e 98 templates;
44 folhas têm 77 blocos `@media`, sem `@container`, com 599 matches dimensionais
e 54 declarações overflow. Isso documenta fontes e regras literais, sem medir
layout computado ou declarar cobertura de runtime. Fonte detalhada em
`mobile-first-audit.md` e `theme-mobile-file-inventory.tsv`.

### THEME-MF2 — carrossel editorial da home MAIN (2026-10-29)

O consumidor real `view-home-editorial-highlights` declara primeiro o layout
compacto, e restaura largura ampla do wrapper e dos slides em
`min-width: 48.0625rem` (após 768 CSS px). Harness comparativo em 12 viewports
criado; execução Firefox e conferência da rota servida estão DEFERRED por
sessões concorrentes. Não aprova MF3. Evidência e limites em `mobile-first-audit.md`
e `tests/browser/aculta-theme-mobile-first/editorial-carousel-layout.py`.

### THEME-MF2 — leitor PDF de Media

A folha visual do contrato `aculta_pdf_viewer` declara primeiro toolbar/busca
compactos e restaura a apresentação ampla acima de 640 CSS px. O smoke Firefox
reutilizável agora mede a fronteira 640/641 além de 320–1280 e pode injetar o
CSS candidato local na rota servida. Runtime/Firefox estão DEFERRED enquanto há
sessões concorrentes; fullscreen não é exercitado. Evidência em
`mobile-first-audit.md` e `tests/browser/aculta-document-viewer/README.md`.

### THEME-MF2 — apresentação Likert de Webform

Conteúdo de ajuda e descrição ocultado pelo formulário no desktop agora é
apresentado como base móvel e ocultado novamente acima de 768 CSS px. O modal
fica com sua única exceção mobile-only porque sua largura vem de opções
jQuery UI/Webform dinâmicas, sem contrato fixo do tema. Harness comparativo
Firefox criado em 14 larguras; a execução está DEFERRED por sessões
concorrentes. Ver `mobile-first-audit.md` e
`tests/browser/aculta-theme-mobile-first/webform-layout.py`.

### THEME-MF2 — gavetas utilitárias do shell

Messenger, alertas e carrinho compartilham `aculta-utility-offcanvas`; a largura
edge-to-edge é a base móvel, com limite 420px após 560 CSS px. O smoke
read-only do Messenger agora injeta CSS candidato e verifica a fronteira usando
o viewport efetivo do Firefox. Execução DEFERRED pelas sessões concorrentes;
Alertas e Carrinho ainda precisam de consumidores visuais próprios. Ver
`mobile-first-audit.md` e `tests/browser/aculta-messenger/README.md`.

### THEME-MF2 — rodapé global

A grade do rodapé passa a uma coluna como base, duas colunas em `min-width:
36rem` e quatro colunas em `min-width: 62rem`, mantendo ordem DOM e conteúdo.
Lint CSS/validação estática executados; comparação no Firefox e páginas reais
DEFERRED por sessões concorrentes. Não aprova THEME-MF3; ver
`mobile-first-audit.md`.

### THEME-MF2 — Institution Bar em viewport estreito

A marca é ocultada como base e volta a ser exibida em `min-width: 23.4375rem`;
o harness existente confere a fronteira 374/375px nos dez purposes. Firefox e
rotas reais DEFERRED por sessões concorrentes; não é gate MF3. O comportamento
de shell fica documentado em `mobile-first-audit.md`.

### THEME-MF2 — controles de discussão

`discussion.css` usa grade de 2 colunas como base, 4 colunas a partir de 360px e
barra sticky de 7 trilhas a partir de 576px; o formulário de resposta também
começa compacto. O harness do Fórum passou a aceitar CSS candidata e cobre
320/360/390/500/575/576/900px, mas a execução Firefox fica DEFERRED. Deve ser
rodado em consumers de Fórum, Wiki e Revista. Ver `mobile-first-audit.md`.

### THEME-MF2 — controles de discussão

`discussion.css` usa grade de 2 colunas como base, 4 colunas a partir de 360px e
barra sticky de 7 trilhas a partir de 576px; o formulário de resposta também
começa compacto. O harness do Fórum passou a aceitar CSS candidata e cobre
320/360/390/500/575/576/900px, mas a execução Firefox fica DEFERRED. Deve ser
rodado em consumers de Fórum, Wiki e Revista. Ver `mobile-first-audit.md`.
