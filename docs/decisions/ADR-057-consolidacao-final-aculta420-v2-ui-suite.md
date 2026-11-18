# ADR-057 - Consolidação final do ACULTA420 V2, UI Suite, qualidade visual e performance

**Status:** EM EXECUÇÃO. F0 estático concluído; F1 em andamento.
**Data:** 2026-11-17  
**Branch de trabalho:** feat/adr057-f1-route-snapshots (derivada de origin/dev)
**Baseline auditada:** 5c496047509f43831de2a6996cbc6ba95d867749  
**Tema:** aculta420_v2, versão 1.5.0; versão 1.6.0 apenas como candidata de consolidação, sem alteração agora.  
**Documentos predecessores:** ADR-054, ADR-055 e ADR-056.  
**Auditoria vinculada:** [ACULTA420-V2-AUDITORIA-ADR057-2026-11-17.md](../architecture/ACULTA420-V2-AUDITORIA-ADR057-2026-11-17.md).

## 1. Contexto e decisão

O tema V2 já usa SDC como unidade de apresentação, UI Patterns 2 como integração editorial, UI Patterns Library oficial como catálogo, Layout Builder em 22 displays elegíveis e UI Skins como fonte de identidade visual. A ADR-056 concluiu P0-P4 em 2026-11-14. Não há autorização para reconstruir o tema nem para reabrir a ADR-056.

Esta ADR assume o fechamento **residual, factual e verificável** das ADR-054/055 e todas as cinco incorporações aprovadas pelo responsável:

1. Composições e presets editoriais reutilizáveis.
2. Aprimoramento da regressão visual automatizada.
3. Uso editorial completo e seguro de UI Styles e UI Icons.
4. Medição e otimização de performance e Core Web Vitals.
5. Integração de UI Patterns Field Group com gate estrito de segurança e compatibilidade.

A auditoria comprova que várias fundações já existem. Portanto, cada pacote começa por descobrir e reutilizar a implementação atual e termina com evidência de consumidor real. Não basta um módulo estar habilitado, um SDC existir ou uma story aparecer na Library.

## 2. Precedência e não objetivos

- **ADR-057 complementa, não revoga**, o corte visual e os contratos de segurança da ADR-054, a seleção UI Suite da ADR-055 e os resultados da ADR-056.
- O documento histórico [ACULTA-PORTAL-PURPOSE-PARA-SITE-KEY.md](../architecture/ACULTA-PORTAL-PURPOSE-PARA-SITE-KEY.md) conserva uma capa de 2026-11-10 que diz "implantação não executada". O código atual já contém DomainSiteManager, DomainSiteManagerInterface, SiteKeyRegistry, ActiveSiteKeysState, DomainSiteRequestSubscriber e SiteFrontPathProcessor; a ADR-057 **não reimplementará a migração inteira**. A tarefa é confirmar consumidores, persistência, permissões, isolamento, testes e aceite RN-05.
- Não restaurar o motor visual purpose, aliases de serviços antigos ou rotas públicas de compatibilidade. Preservar os dez valores de site_key e os Domain IDs existentes.
- Não introduzir Bootstrap, bundler novo, Storybook obrigatório, design system paralelo, UI Patterns CKEditor 5 ou uma segunda biblioteca de componentes.
- UI Styles CKEditor 5 continua permitido nas configurações editoriais seguras e existentes.
- Não definir "zero Twig" como sucesso: 17 overrides precisam de decisão por consumidor e hook. Manter adaptadores legítimos de Core, Form API, Views e Comment.
- Não confundir pacote instalado, Config Sync, Runtime ativo, testes estáticos e aceitação humana.

## 3. Contrato arquitetural permanente

**Identidade:** site_key/Domain/ACL e ownership pertencem ao Portal; domain_id resolve skin_id; UI Skins fornece valores visuais; UI Styles aplica classes editoriais autorizadas; UI Icons oferece iconografia; SDC/UI Patterns compõem render arrays. Nenhuma escolha de skin, icon ou preset muda acesso, cache, proprietário de conteúdo ou URL.

**Componentes:** preservar descoberta de 100% dos SDCs na UI Patterns Library. Componentes novos exigem manifest com schema estrito, Twig, story, props tipadas, slots renderáveis e testes. Reutilizar os 150 existentes primeiro. Não inserir render arrays inteiros nem entidades nas props; preservar acesso, cache, tradução, atributos e Form API no owner funcional.

**UI editorial:** opções whitelist por papel e formato de texto, sem CSS/JS livres. Decisões sobre presets devem usar integração nativa de UI Patterns Blocks, Layouts, Fields/Formatters, Views e Layout Builder. Persistência e exportação de config precisam ser reproduzíveis.

**Testes:** comparar comportamento, não apenas aparência. Alterações visualmente intencionais exigem revisão humana da baseline. Um teste skipped/DEFERRED não equivale a PASS.

## 4. Pacotes de trabalho e dependências

### P00 - Baseline e reconciliação do estado atual [P0, início]

**Ler e conferir:** HEAD, composer.json/lock, core.extension, aculta420_v2.settings, biblioteca de componentes, assets, scripts de auditoria, workflows, docs de operações e inventários de consumidores. Ler Runtime somente com preflight read-only, caso exista ambiente confirmado.

**Trabalho:**
- Consolidar os 150 SDCs, 244 stories, 17 templates, 22 displays Layout Builder, 159 displays de Views e 52 placements de bloco, distinguindo contagens estáticas de descobertas Runtime.
- Atualizar a matriz inventariada por consumidor com owner, origem dos dados, plugin, permissão, contextos/tags de cache, rota, teste, risco e condição de remoção.
- Resolver conflito documental entre os snapshots ADR-054/055, o plano RN histórico e a ADR-056 atual.
- Conferir divergências de Views Revista/Cursos explicitadas no TODO; não assumir que o preflight limpo de 2026-11-14 continua verdadeiro.
- Fixar baseline de render, autenticação, status HTTP, CSS entregue, JS entregue e principais rotas.
- Verificar upstream de UI Patterns, UI Styles, UI Icons, UI Skins, Field Group e avisos de segurança na data da execução.

**Saída:** B0 assinada e inventário vivo. **Gate:** entradas críticas sem owner ou status são FAIL; Runtime indisponível é DEFERRED.

### P01 - Estabilidade do CI, segurança e qualidade [P0, após P00]

**Aproveitar:** PHPStan nível 10 com baseline congelada; Stylelint/ESLint; validadores SDC e UI Suite; PHPUnit; Semgrep; testes Playwright.

**Trabalho:**
- Catalogar falhas atuais Kernel por suíte, causa, pacote dono e impacto no V2. Não atribuir automaticamente falha de Maps/LMS/Activities ao tema.
- Criar estratégia de Kernel bloqueante para suites estabilizadas; reavaliar o atual continue-on-error do shard, sem mascarar falhas preexistentes e sem enfraquecer a branch protection.
- Definir check obrigatório de regressão do escopo do tema/Portal e evidência de jobs efetivamente executados.
- Rodar gates de PHPStan 10 sem expandir baseline, lint, schemas, strict props, classes, CSS scope, Config Sync e lint Twig.
- Verificar assessoria de segurança e patches upstream; nenhuma mudança Composer/Runtime é autorizada apenas por esta ADR.

**Saída:** painel CI com PASS/FAIL/DEFERRED/N/A e plano de isolamento de falhas. **Gate:** zero regressões novas relevantes; bloqueios de segurança expressos.

### P02 - Encerramento residual ADR-054/055 [P0, após P00/P01]

**Trabalho:**
- Auditar RN-00 a RN-05 com foco em **validação**, pois a API DomainSiteManager já existe. Buscar restos funcionais de purpose apenas no código atual; não remover termos históricos, nomes de APIs contrib ou dados legítimos por string search.
- Verificar route options de fail-closed; 403/404; canonicalização; login_destination site_key; sessão; logout; cookies; host; índice; ownership; acesso; cache por domínio; preferências de tema entre hosts. Usar o runner auth-login.mjs existente.
- Validar US-14 sobre dez site_keys, dois modos, cinco larguras, zoom 200%, teclado, perfis anônimo, comum, editor e admin, temas e menus.
- Confirmar 22 displays Layout Builder elegíveis e exceções justificadas node.document.full e block_content.basic.default; não forçar layout que suprima conteúdo.
- Homologar Views UI Patterns row/style/display com Query/Filters/Pager/Exposed/Empty/Header/Footer/Access intactos, especialmente Revista e Cursos onde o TODO menciona import pendente.
- Inspecionar os 17 Twig existentes por hook e classificar: adapter necessário, substituível com teste ou bloqueado por API upstream. Sem meta artificial de remoção.
- Revalidar UI Patterns Library oficial, 150 previews progressivas, sete variants oficiais, estados vazios e valores das stories.
- Reconciliação Runtime/Sync apenas mediante autorização operacional específica quando envolver import, banco, Composer, update ou clear de schema.

**Saída:** matriz RN/US com evidência atual e status por domínio. **Gate:** RN-05 e US-14 PASS para encerrar formalmente ADR-054 e ADR-055.

### P03 - Catálogo de composições e presets editoriais [P1, após P02]

**Trabalho:**
- Inspecionar APIs nativas instaladas de UI Patterns e Layout Builder antes de decidir a persistência. Não criar um engine proprietário de layouts.
- Definir preset como uma composição reutilizável de SDCs, slots, props, variantes, estilos e fontes permitidas, mantendo o modelo de dados em cada módulo owner.
- Cobrir ao menos uma composição editável, persistente, exportável e renderizável em cada site_key: MAIN institucional/transparência; Conta dashboard; Apoio campanha; Revista artigo/listagem; Wiki verbete; Loja catálogo/produto; Cursos jornada/catálogo; Fórum tópicos; Links vitrine; Eventos agenda/organização.
- Diferenciar presets nativos de design (reutilizáveis) de layouts específicos de cada domínio e de dados/editorial revisionado.
- Fornecer estados vazios, textos alternativos, hierarquia H1-H6, comportamento responsivo e variantes claro/escuro. Documentar permissão para criar/alterar/aplicar presets.
- Testar edição, reversão, tradução, revisão, cache, export/import e ausência de alteração involuntária de acesso.

**Saída:** catálogo editorial com composição real e documentação de uso. **Gate:** um fluxo completo em cada um dos dez sites; nenhum duplicado visual desnecessário.

### P04 - UI Styles, UI Icons e controles editoriais [P1, paralelo a P03]

**Ponto de partida:** aculta420_v2.ui_styles.yml já descreve botões, superfícies, faixas tricolores, títulos, raio, elevação, espaçamento e estados não publicados. Packs Phosphor regular/duotone, Brócolis e Fluent Emoji já são definidos. core.extension já habilita UI Icons Picker/Popover/Menu/Patterns e UI Styles Page/Blocks/Layouts/Views/CKEditor5/Entity Status.

**Trabalho:**
- Inventariar opções existentes e consumidores reais (Block, Views, UI Patterns, Layout Builder, Page e CKEditor). Corrigir lacunas sem replicar configuração já existente.
- Conferir UI Styles Page: tema/region, configuração persistida, saída real de classe e prévia, principalmente porque o estado antigo aponta falta de ui_styles_page.regions no settings.
- Completar formatos editoriais seguros em CKEditor5 usando apenas UI Styles; revisar filtros/XSS e papel content_editor.
- Validar UI Icons Menu, Patterns, Picker, Popover e Fields de ponta a ponta: descoberta, rótulos, gravação, export, ícone decorativo com aria-hidden versus ícone de significado com nome acessível.
- Unificar o seletor de emojis no contexto permitido sem substituir o dono funcional de reações/emoji por um componente editorial genérico.
- Usar classes/tokens semânticos, mantendo tokens globais, UI Skins e CSS por SDC como fonte visual.

**Saída:** matriz de controles UI Suite e manual editorial. **Gate:** aplicação/remoção real por papel e consumidor, claro/escuro, CSP, cache e acessibilidade.

### P05 - UI Patterns Field Group [P1, após P01 e contratos P03/P04]

**Ponto de partida:** pacote drupal/ui_patterns_field_group dev-2.0.x documentado no lock histórico; relato ADR-055 de módulo presente no Runtime anterior e **ausente do Config Sync** atual. Upstream **não coberto pela política Drupal Security Advisory** e release beta2 declara Drupal ^9/^10/^11, sem Drupal 12.

**Fase A - perícia de segurança/compatibilidade:** conferir pacote efetivamente instalado, SHA, versão de Field Group, UI Patterns, Core; comparar API upstream 2.0.x-dev e alternativas; inspecionar plugin formatter, form display, access, cacheability, sanitização, traduções, dependências, eventual compatibilidade Drupal 12 e advisories. Verificar se o estado Runtime previamente habilitado foi mantido, removido ou divergente.

**Fase B - sandbox controlado:** snapshot/rollback; piloto com display não destrutivo em Revista e Wiki, com fields escalares, múltiplos, renderables, entidades referenciadas, campos ocultos e vazios, Field API, acesso por papel, revisão e tradução. Não tocar em conteúdos de produção.

**Fase C - integração configurada:** após aprovação independente R2/R3 e autorização operacional, habilitar ou reconciliar no Config Sync, importar somente configs autorizadas, provar export/import reproduzível, homologar UI Patterns Field Group no mesmo conjunto de SDCs/presets. Não editar contrib diretamente.

**Gate negativo:** se segurança, compatibilidade ou integridade falharem, registrar BLOCKED_UPSTREAM com solução viável (patch contribuído auditado ou adaptador seguro) e manter Field Formatters/SDC como fallback **temporário**. Não declarar a incorporação concluída enquanto faltar o Field Group.

### P06 - Regressão visual e acessibilidade [P1, iniciar após P00, continuar nos demais]

**Ponto de partida:** já existe playwright.visual.spec.mjs, config visual, 140 capturas PNG versionadas, 50 arquivos routes.json e suíte Axe. É proibido reinventar esta infraestrutura.

**Trabalho:**
- Conferir mapeamento rota/host/papel/skin, inclusive nomes de snapshot exclusivos por rota para impedir colisões.
- Preservar a geração de baselines sob revisão humana; definir processo de aprovação, fingerprints e ambientes herméticos. Não usar update-snapshots automático no CI.
- Adicionar cobertura de dez domínios representativos, perfis autenticados e páginas privadas, além de estados de SDC e UI Patterns Library.
- Reconciliar breakpoints existentes 320/375/768/1024/1440 com padrão desejado 320/390/768/1024/1440 sem invalidar imagens silenciosamente.
- Criar suites em camadas: smoke rápido por diff, regressão crítica por domínio, matriz completa noturna/manual e cross-browser quando necessário.
- Estabilizar tempos, fontes, mídia, fixtures e dados voláteis sem mascarar falhas reais; aceitar tolerâncias calibradas por componente ao invés de maxDiffPixelRatio 0 imposto indiscriminadamente.
- Expandir Axe para registrar também problemas moderate, manter bloqueio serious/critical, resolver barreiras WCAG 2.2 AA, executar navegação por teclado, foco, zoom 200%, reduced-motion e revisão com tecnologia assistiva.
- Diferenciar snapshot alterado e aprovado de regressão não autorizada. Teste skipped por ausência de host/credencial não vale PASS.

**Saída:** baselines revisadas, relatórios de diff e rotinas de CI estáveis. **Gate:** sem regressões visuais não aceitas nas rotas críticas e sem problemas graves de a11y.

### P07 - Performance, fontes e Web Vitals [P1, medir após P00; otimizar após P03-P05]

**Ponto de partida:** 161 arquivos CSS do tema somam 433.593 bytes **de código-fonte**, não transferência por página. assets/icons tem milhares de SVGs, não necessariamente descarregados em rede. global-styling usa Google Fonts externo Inter/Oswald.

**Trabalho:**
- Medir por rota, domínio, modo, aparelho e cache: CSS/JS realmente anexados, requests, transferSize, gzip/brotli, parse/exec, requests a fontes, imagens, prioridade LCP, layout shifts e interações.
- Criar script/runner de performance reproduzível (p.ex. Lighthouse CI ou Playwright + Web Vitals, após avaliação do stack), integrado ao navegador já existente. Não instalar novo framework obrigatório.
- Medir LCP, CLS e INP em dados de campo no p75, quando houver amostragem legítima. Referências de qualidade: LCP <= 2,5 s; INP <= 200 ms; CLS <= 0,1.
- Em laboratório, registrar LCP/CLS e TBT/long tasks, explicando que TBT **não substitui INP de campo**. Não declarar meta de INP aprovada por medição de carregamento sem interação.
- Investigar self-host de fontes com licenças, subset, pesos, WOFF2, preload criterioso, font-display e fallback; implementar só se o ganho for medido e autorizado na fase executiva.
- Auditar CSS global versus SDC sob demanda; reduzir duplicação de seletores, JS de interação, imagens sem dimensões, cargas na barra multidomínio e páginas mais densas (Fórum, Studio, Loja).
- Definir budgets por família de página derivados da baseline real e tolerâncias do ambiente; verificação no CI para regressões mensuráveis.

**Saída:** benchmark antes/depois, budgets versionados e recomendações executadas. **Gate:** metas aprovadas de laboratório, ausência de regressão significativa e avaliação honesta de CrUX/RUM quando houver dados.

### P08 - Homologação multidomínio e aceite humano [P0 de fechamento]

- Matriz: dez site_keys; claro/escuro; larguras 320, 390, 768, 1024, 1440; zoom 200%; teclado; reduced motion; Firefox e navegador complementar quando aplicável.
- Perfis: anônimo, membro, editor, administrador, com testes negativos de autorização e sessão real entre hosts.
- Conteúdos: shell, navbar, menus, breadcrumb, rodapé, formulário, Views e pagers, Layout Builder, campos agrupados, presets, diálogos, galeria, cursos, comércio e páginas sem conteúdo.
- Verificar acesso, tradução, revisionamento, cache contexts/tags e isolamento Domain, e registrar comportamento esperado quando a resposta legítima é UNCACHEABLE em vez de exigir HIT.
- Repetir doctor read-only, backup/restore planejado e verificação Config Sync após cada operação R3 autorizada; não transformar essa ADR em autorização de importar config ou alterar banco.
- Capturas e revisão visual humana por domínio; registrar diferenças aceitas e problemas.
- **Gate:** matriz crítica PASS; exceções não bloqueantes com responsável e issue; nenhum DEFERRED crítico tratado como aprovação.

### P09 - Encerramento, governança e release [P0 final]

- Atualizar README, TODO, roadmap, docs/gates, inventários e skills dos agentes **quando** os contratos realmente forem implementados; não declarar entrega no planejamento.
- Registrar decisões e eventual limitação upstream sobre Field Group e mudanças em UI Patterns estável/dev; executar gate de compatibilidade Drupal 12.
- Encerrar ADR-054 e ADR-055 somente após RN-05 e US-14 aceitos; ADR-056 permanece encerrada.
- Revisar pacote de release candidata 1.6.0 e documentação de rollback sem criar tag/PR nem publicar produção automaticamente.
- Revalidar CI efetivamente executado e Runtime após o commit funcional final.

**Saída:** relatório de implementação ADR-057, release candidata e aceite editorial formal. **Gate:** todos os objetivos aprovados e verificáveis; status IMPLEMENTED apenas então.

## 5. Ordem executiva e possibilidade de paralelismo

| Ordem | Pacote | Dependência | Marco |
| --- | --- | --- | --- |
| 1 | P00 | Nenhuma | Baseline atual e evidências |
| 2 | P01 | P00 | Qualidade, CI e segurança |
| 3 | P02 | P00/P01 | Encerramento funcional ADR-054/055 |
| 4 | P03 | P02 | Presets |
| 4 | P04 | P02, paralelo a P03 | Styles/Icons |
| 4 | P06 | P00, contínuo | Regressão visual |
| 5 | P05 | P01/P03/P04 | Field Group seguro |
| 5 | P07 | baseline P00, otimização após P03-P05 | Performance |
| 6 | P08 | P02-P07 | Homologação |
| 7 | P09 | P08 | Release candidata |

Não iniciar importação operacional só porque P00/P01 foram aprovados. Processos paralelos devem evitar escrita concorrente em config, layout e CSS compartilhados.

## 6. Riscos, bloqueios e revisão

| ID | Risco | Tratamento | Classe mínima |
| --- | --- | --- | --- |
| R57-01 | Field Group fora da cobertura Security Advisory e sem declaração Drupal 12 | Gate de código/segurança e piloto isolado; não habilitar cegamente | R2; R3 quando operacional |
| R57-02 | Config Sync x Runtime com relatos históricos divergentes | Doctor read-only, snapshots e autorização para import | R3 |
| R57-03 | Vazamento por domínio/cache ou perda de sessão | Testes negativos multihost, revisão independente e fail-closed | R2 |
| R57-04 | Falhas Kernel preexistentes mascaradas por continue-on-error | Triar por owner, estabilizar gate bloqueante | R1/R2 conforme consumidor |
| R57-05 | Baselines visuais frágeis, colididas ou silenciosamente atualizadas | IDs estáveis, revisão humana, CI read-only | R1 |
| R57-06 | Preset compromete dados/formulários/fields | Source/Field API nativa e teste da edição completa | R2 |
| R57-07 | Otimizar CSS por tamanho de origem em vez de bytes transferidos | Benchmark por rota/Device/Cache antes do corte | R1 |
| R57-08 | Remoção prematura de overrides Twig | Mapa do hook, substituto, acesso/cache e testes | R2 quando auth/comentário |
| R57-09 | Alteração Composer/Core durante ciclo | Verificar advisories e solicitar autorização operacional específica | R3 |

Aplicar [AGENT-REVIEW-POLICY.md](../operations/AGENT-REVIEW-POLICY.md). R2 exige revisão independente; R3 exige revisão independente **e autorização por operação**. A atividade puramente documental desta ADR é R0.

## 7. Formato de aceite por pacote

Cada pacote deverá registrar: (1) baseline/HEAD; (2) owner e consumidores; (3) arquivos e configs alterados; (4) testes com PASS/FAIL/DEFERRED/N/A; (5) perfis/rotas/ambientes realmente avaliados; (6) capturas ou evidências; (7) revisão e classe R; (8) procedimento de rollback; (9) SHA do commit; (10) estado da sincronização operacional.

Não ampliar contratos editoriais a partir de um único preview de story. A Library prova descoberta; render Runtime prova composição; teste funcional prova comportamento; revisão visual aprova aparência.

## 8. Critério definitivo de conclusão

ADR-057 só é IMPLEMENTED quando RN-05 e US-14 estão PASS, ADR-054/055 formalmente encerradas, dez domínios homologados, 150 SDCs preservados/validados (ou inventário atualizado com justificativa), presets implementados, UI Styles e Icons editoriais operacionais, Field Group seguro e integrado, regressão visual bloqueante ativa, acessibilidade crítica aprovada, performance medida e com budgets, Config Sync/Runtime reconciliados por operações autorizadas e release candidata revisada.

Se Field Group estiver bloqueado por upstream, registrar a decisão e **não classificar a incorporação como entregue**. É permitido homologar os demais pacotes separadamente; a ADR como um todo continua PARTIAL/BLOCKED até revisão do escopo pelo responsável.

## 9. Estado desta criação

Esta revisão foi apenas **auditoria estática GitHub, consulta a resultados existentes do CI e pesquisa documental upstream**. Não executou PHPStan, PHPUnit, Playwright nem Drush local, não acessou o Runtime vivo, não importou config, não instalou pacotes e não alterou arquivos de código. A publicação deste documento não implementa nenhum pacote.

**Referências:** [ADR-054](ADR-054-refatoracao-aculta420-v2-sdc-layout-builder.md), [ADR-055](ADR-055-integracao-ui-icons-ui-styles-page-e-sistema-unificado-de-emojis.md), [ADR-056](ADR-056-ui-patterns-2-recomendacoes-e-corte-documentacao.md), [Gates V2](../../web/themes/custom/aculta420_v2/docs/gates.md), [TODO V2](../../web/themes/custom/aculta420_v2/TODO.md), [Auditoria ADR-057](../architecture/ACULTA420-V2-AUDITORIA-ADR057-2026-11-17.md).

### Entrega adicional UI Suite — Color Mode Switcher

Em complemento ao escopo da ADR-057, o tema publica o SDC
`aculta420_v2:color-mode-switcher` com stories isoladas na UI Patterns Library.
O Portal mantém a propriedade da preferência e do endpoint autenticado, e o
tema resolve `auto` no navegador. O botão não foi colocado em consumer de
produção. Testes automatizados e validadores focais estão registrados no commit
de entrega; homologação visual de navegador/Runtime permanece independente e
pendente. Esta entrega não altera o estado global da ADR-057 nem reabre a
ADR-056.
