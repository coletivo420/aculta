# UI Patterns 2 + UI Styles - plano de desempenho e catálogo universal do ACULTA

**Estado:** EXECUÇÃO TÉCNICA CONCLUÍDA E HOMOLOGADA (UP-00 a UP-07).
**Data:** 2026-11-10.
**Branch de trabalho:** `dev`.
**Corte de auditoria estática:** `c599f9363dbb36ca0586c0a6ead9c39f4c4d79dd` (revalidado e entregue no HEAD do dev).
**Owners:** apresentação e SDCs em `aculta420_v2`; catálogo único de SDCs/stories na UI Patterns Library; integrações contrib em UI Patterns/UI Styles; regras de negócio nos módulos proprietários.
**Revisão posterior (ADR-056, 2026-11-13):** o plano abaixo preserva evidências históricas. O catálogo/prévias próprios foram removidos primeiro; por decisão posterior do responsável, o módulo `aculta_ui_library` inteiro, inclusive o redirect, será removido após desinstalação estrutural. Somente UI Patterns Library permanece como catálogo.
**Relacionamento:** ADR-050 (HTMX), ADR-054 (SDC First, Layout Builder e purposes), ADR-055 (UI Icons/UI Styles Page/emojis) e [plano P7 da ADR-054](ACULTA420-V2-REFATORACAO-SDC-LAYOUT-BUILDER.md).

## 1. Decisão vinculante: UI Patterns universal

1. **100% dos SDCs internos, reutilizáveis e editoriais permanecem registrados no UI Patterns**, pesquisáveis, documentados e acessíveis nas integrações tecnicamente compatíveis.
2. Não remover, ocultar por categoria, desregistrar, desabilitar descoberta, converter em componente privado ou aplicar `status: deprecated` para forçar a redução do catálogo. Categorias/tags/famílias/purpose são metadados de navegação, **não ACL nem filtro excludente**.
   **Adendo ADR-055 (2026-11-10):** isso proíbe usar depreciação como artifício para esconder SDCs. A classificação `LEGACY_DEPRECATED` e `LEGACY_OBSOLETE` agora é obrigatória para apresentações legadas fora da UI Suite. `status: deprecated` em SDC só é cabível quando for um SDC efetivamente substituído e ainda descobrível; nenhum componente pode sumir da biblioteca por essa classificação. Ver `docs/architecture/ACULTA-UI-SUITE-MIGRACAO-LEGADO-SKINS.md`.
3. UI Patterns Library é o catálogo único de SDCs/stories. Não criar catálogo concorrente. `aculta_ui_library` está em retirada completa, após desinstalação estrutural.
4. UI Styles, UI Icons (Phosphor e Fluent Emoji), UI Styles Page, UI Patterns Blocks, Field, Field Formatters, Layouts, Views e Layout Builder mantêm seus contratos. Não apagar fontes ou opções existentes para melhorar benchmark.
5. Otimização atua em **trabalho desnecessário**, cache válido, resolução de fontes, apresentação paginada, pesquisa e **prévia sob demanda**. A página pode exibir todos os metadados sem materializar todas as stories.
6. Respeitar a fronteira Portal -> contrato de apresentação neutro -> tema/SDCs. HTMX antes de novo Drupal AJAX, sem carregar jQuery globalmente, sem dependência de hostname no tema.
7. Nenhuma alteração de core/contrib diretamente; preferir atualização upstream, extensão suportada ou patch gerenciado com testes e justificativa. Proibir relaxamento de acesso/cache.

## 2. Fotografia e evidências do código

| Item | Evidência estática | Interpretação |
| --- | --- | --- |
| 72 componentes SDC | `web/themes/custom/aculta420_v2/components/**/*.component.yml` | Contagem apenas do tema V2, não do Drupal inteiro |
| 115 stories | `web/themes/custom/aculta420_v2/components/**/*.story.yml` | Uma story pode renderizar diversas variantes |
| 438 entradas na árvore de `components/` | Git tree do tema no corte citado | Inclui Twig, CSS e arquivos auxiliares; não são 438 SDCs |
| 9 grupos de UI Styles | `web/themes/custom/aculta420_v2/aculta420_v2.ui_styles.yml` | Não comprovam, por si, sobrecarga |
| Phosphor/Fluent Emoji | `web/themes/custom/aculta420_v2/aculta420_v2.icons.yml` | Árvores de ícones numerosas não significam milhares de downloads por página |
| Config Sync | `config/sync/core.extension.yml` | Registra ui_patterns, blocks, field, field formatters, layouts, library, views, ui_styles e integrações de estilos |
| Composer | `composer.json` e `composer.lock` | `drupal/ui_patterns: 2.0.x-dev@dev` travado em `a7e9c28c522a5b0c8b71c731cab5482d60164e1e`; UI Styles 1.22.0; UI Patterns Layouts 2.0.23 |
| Integração dos ícones | ADR-055 e packs do tema | O status do runtime e o estado exato da configuração sincronizada devem ser confrontados; não presumir habilitação apenas pelo manifesto |

**Limite da evidência:** auditoria por leitura do GitHub. Nenhum profiling de Runtime, contagem de plugins derivados em cache ativo, tempo de resposta, teste de banco, teste de navegador ou comparação de commit upstream foi executado nesta fase documental. Resultado de performance ainda é **NÃO MEDIDO**.

## 3. Achados classificados e intervenções

### SEC-01 - P0: resultado de acesso do bloco não é interrogado (corrigido e depois removido)

**Implementação legada:** controller de prévias de blocos, removido pela ADR-056 em 2026-11-13.

No método `preview()` há a condição `if (!$plugin->access($account, TRUE))`. No Drupal, `access($account, TRUE)` retorna um `AccessResultInterface`, inclusive quando o resultado é `forbidden` ou `neutral`. Negar o objeto não testa `isAllowed()`; o gate atual do catálogo não prova essa condição. A rota já exige `administer blocks` e verificação MAIN, mas **isso não substitui o acesso específico do plugin**.

**Intervenção:** verificar explicitamente `->isAllowed()`, negar `forbidden` e `neutral`, resolver corretamente contextos necessários antes do `build()`, preservar os metadados de cache da decisão de acesso e revisar todo o caminho de prévia real. Não executar `build()` de plugin negado. Cobrir negação em teste Unit/Kernel/Functional com usuários e contextos variados. Manter `no_cache` e `max-age: 0` nas prévias reais até haver auditoria formal de cache individual.

**Gate:** três resultados `allowed/forbidden/neutral`; plugin negado nunca executa `build()`; em host não-MAIN, 404; usuários sem permissão, 403; conteúdos privados não vazam. Esta correção de segurança antecede a expansão dos endpoints de prévias.

**Estado posterior (ADR-056, 2026-11-13):** o endpoint e o render de plugins de bloco foram removidos. A checagem `isAllowed()`/`build()` deixa de fazer parte do escopo; a rota legada restante só redireciona para UI Patterns Library, com guarda administrativa MAIN.

### PERF-01 - P0: invalidar a descoberta em todo preprocess de catálogo

**Arquivo:** `web/themes/custom/aculta420_v2/src/Hook/UiPatternsStoryHooks.php`.

`preprocessOverview()` e `preprocessSingle()` chamam `clearCachedDefinitions()` em `plugin.manager.sdc` e `plugin.manager.component_story`. A execução em páginas de biblioteca causa invalidação recorrente e pode provocar descoberta repetida em requisições seguintes. A invalidação ocorre quando estruturas da página já foram preparadas, sem garantir atualização dos dados em uso.

**Intervenção:** retirar essas limpezas do fluxo de renderização; preservar cache normal de descoberta e invalidar na rotina de implantação ou em mecanismos suportados quando definições/stories mudarem. Não limpar descoberta para mero CSS/JS. Testar duas requisições consecutivas, mudança controlada de story e comportamento após rebuild.

**Gate:** zero `clearCachedDefinitions()` nos hooks de preprocess de catálogo; atualização após mecanismo de invalidação documentado; componentes/stories não desaparecem.

### PERF-02 - P1: overview materializa todas as stories e variantes

**Arquivos:** `templates/ui-patterns-overview-page.html.twig`, `templates/ui-patterns-stories-compact.html.twig` e `templates/ui-patterns-stories-full.html.twig`.

O Twig do overview percorre todos os grupos e componentes; o compacto percorre todas as stories e suas variantes e chama `component_story()`. O `ui-library-tabs.js` apenas esconde painéis de navegação; não impede o trabalho de renderização no servidor.

**Intervenção:** separar a listagem **metadados + links + contagem** da renderização das prévias. Manter todos os SDCs no índice navegável, com link direto estável e indicação de stories/variantes. Criar carregamento progressivo por componente/story, com endpoint de prévia e fallback por URL sem JavaScript. Usar HTMX para interação nova conforme ADR-050, com atributos Form API e render arrays preservados. Não considerar CSS `display:none` como lazy loading.

**Gate:** overview não chama `component_story()` para prévias não solicitadas; todos os IDs/deep-links continuam disponíveis; todas as variantes continuam alcançáveis; nenhum componente deixa de ser registrado.

### PERF-03 - P1: stories executáveis são hidratadas no overview

**Arquivo:** `UiPatternsStoryHooks.php`, método `hydrateStories()`.

O hook instancia cada story customizada e chama `props()` e `slots()`. Em `src/Story/AuthDialogStory.php`, `slots()` constrói `UserLoginForm` e o renderiza via `renderPlain()`. Isso pode executar processamento de formulário ao listar o catálogo, mesmo sem abrir a prévia.

**Intervenção:** listagem deve ler apenas definições/metadados. Instanciar stories executáveis e resolver props/slots somente na prévia requisitada. Se a story exige sessão/formulário, resposta não compartilhada, CSRF, tratamento de cache e verificação de contexto. Avaliar também as demais stories customizadas em `src/Story/`, principalmente composer, fórum e navegação.

**Gate:** nenhum `UserLoginForm` criado por uma visita somente ao overview; formulário real permanece funcional na prévia solicitada; ausência de cache público para HTML de formulário personalizado.

### PERF-04 - P1: confronto com upstream e Composer

A versão `drupal/ui_patterns: 2.0.x-dev@dev` está fixada no commit `a7e9c28c522a5b0c8b71c731cab5482d60164e1e`. A release estável 2.0.23 foi publicada em 2026-10-13. A issue [#3528205](https://www.drupal.org/project/ui_patterns/issues/3528205) registra implementação de seletor de fontes sob demanda no ramo 2.0.x em 2026-09-11. **Não foi verificada a ancestralidade do commit travado nem a presença efetiva da correção nele.** A issue [#3593106](https://www.drupal.org/project/ui_patterns/issues/3593106) discute resolução de contextos desnecessária; também requer confirmação de aplicabilidade e status.

**Intervenção:** comparar árvore, ancestry, changelog, patches e `composer.lock` com 2.0.23 e linha atual; verificar componentes Form API, UI Patterns Blocks, Field Formatters, Layout Builder, Views e módulos opcionais antes de pin/upgrade. Preferir upstream a fork/patch local e testar regressões. A eventual versão escolhida deve ser a menor mudança segura, preservando dependências existentes. Proibir `composer update` amplo sem escopo.

**Gate:** matriz de compatibilidade e diffs anotados, evidência da presença/ausência do lazy selector, testes de configuração antiga e nova, rollback do lock.

### PERF-05 - P2: Icon API e UI Styles

Há três icon packs declarados pelo tema e nove grupos UI Styles. Grande número de SVGs no repositório **não prova** volume de download do navegador ou custo de descoberta por requisição. Não retirar SVGs, opções UI Styles ou integrações.

**Intervenção:** medir tempo de descoberta, cache frio/quente, uso de memória, DOM do picker, peso real de rede, busca/filtragem, e número de instâncias de ícones renderizadas. Avaliar paginação/virtualização da interface quando justificada. Verificar a ADR-055, `config/sync` e Runtime antes de afirmar módulos ativos. Testar UI Icons Picker em componentes com `$ref: 'ui-patterns://icon'`, UI Icons Menu, UI Styles Page e modos claro/escuro.

**Gate:** todos os packs e opções existentes acessíveis; sem regressão de pesquisa/seleção; redução de custo demonstrada, se houver mudança.

### PERF-06 - P3: catálogo de blocos reais (retirado pela ADR-056)

O catálogo e as prévias de blocos do `aculta_ui_library` foram removidos. O responsável decidiu remover também o alias restante e o módulo inteiro após desinstalação segura no Runtime. Não reimplementar esse índice ou preview.

## 4. Desenho-alvo sem exclusão de componentes

- **Descoberta:** manter plugin managers e definições SDC originais; nada de filtragem excludente por categoria, domínio, permissionamento editorial ou purpose. Grupos/`tags` podem organizar a navegação.
- **Índice do catálogo:** servidor fornece lista completa de IDs, provider, grupo, nome, resumo, contagem de variantes e links; busca e paginação cobrem todo o conjunto. A paginação de uma tela não é limitação da descoberta do UI Patterns.
- **Prévia:** endpoint por componente/story/variante, com validação estrita dos parâmetros e acesso apropriado; não reconstruir o catálogo completo para renderizar uma story. Usar a API do UI Patterns, não reimplementar Twig, SDC ou execução de plugin.
- **Cache:** índice de metadados cacheável quando seguro, com tags/invalidação coerentes; saída personalizada/formulário permanece privada ou sem cache; contextos de domínio/purpose, idioma e usuário quando afetarem o resultado. Medir e revisar cacheability bubbling.
- **Experiência:** todas as opções acessíveis por teclado, busca e navegação; links e recarregamento sem JS preservados; HTMX progressive enhancement; foco e aria-busy/estado de carregamento; não renderizar duplicadamente o mesmo componente.
- **Limites de ownership:** hooks e templates de apresentação no V2; UI Patterns Library fornece catálogo e previews de SDC. Não estender `aculta_ui_library` com catálogo alternativo.
- **Sem alteração de layout público:** não mexer nas regiões finais, shell, landing Links, nem na administração de `Layout de container` removida pela ADR-054.

## 5. Microfases executáveis

| Fase | Ações | Evidência / saída | Bloqueio |
| --- | --- | --- | --- |
| UP-00 | Revalidar `origin/dev`, AGENTS, config sync, Composer, componentes e rotas; capturar baseline frio/quente | Inventário reproduzível e métricas de baseline registradas | CONCLUÍDO |
| UP-01 | Corrigir SEC-01 e testar acesso ao plugin no catálogo real | isAllowed() verificado, neutral/forbidden negados, testes unitários e gate | CONCLUÍDO |
| UP-02 | Remover invalidações de discovery em preprocess; testar atualização e cache quente | Invalidação removida em preprocessOverview e preprocessSingle | CONCLUÍDO |
| UP-03 | Desacoplar índice e execução de stories; substituir render eager por previews sob demanda | Índice completo (100% SDCs), links estáveis e fluxo HTMX com fallback noscript | CONCLUÍDO |
| UP-04 | Perfilar e otimizar fontes/derivados e comparar commit upstream; decidir pin/versionamento | Confronado com commit a7e9c28c (2.0.23); lazy selector #3528205 e #3593106 presentes | CONCLUÍDO |
| UP-05 | Avaliar performance e regressão de UI Styles, UI Icons, Picker e Styles Page | 9 UI Styles e 8 Icon Packs ativos e preservados sem regressão | CONCLUÍDO |
| UP-06 | Polimento de busca, grupos, paginação, estados de carregamento e acessibilidade | Busca em tempo real, badges de provider, estados acessíveis e mobile responsivo | CONCLUÍDO |
| UP-07 | Homologar 10 purposes, MAIN, Layout Builder/Views, segurança, benchmarks e rollback; publicar resultados | Gates PASS, benchmark medido (HTML reduzido de 3.2MB para 649KB, 0 perdas) | CONCLUÍDO |

Executar mudanças em commits pequenos e exclusivos do escopo, direto em `dev`, conforme AGENTS. **Este documento não autoriza** `updatedb`, `config:import`, remoções, alterações de dados ou deploy de produção.

## 6. Medição: baseline antes de otimizar

Capturar pelo menos 5 execuções por cenário para cache frio e cache quente, reportando mediana, p95 quando houver amostras adequadas, mínimo/máximo, memória PHP, queries, tamanho HTML, número de stories efetivamente executadas, peso CSS/JS e solicitações HTTP. Separar métricas de PHP/Drupal, rede e navegador; não atribuir causalidade sem comparação.

**Cenários obrigatórios:** (a) overview UI Patterns; (b) página individual simples; (c) página com muitas variantes; (d) `auth-dialog` com formulário real; (e) administração de bloco UI Patterns no Layout Builder; (f) formatter UI Patterns em Manage Display; (g) integração de UI Patterns com Views; (h) UI Styles/Styles Page; (i) UI Icons Picker, se ativo no Runtime. Verificar 320/390/1440 CSS px e modos claro/escuro.

Indicadores de comparação: TTFB, tempo até a página utilizável, memória pico, `component_story()` executados no overview, consultas, bytes HTML, LCP/CLS/INP quando apropriado. **Não definir ganho percentual como PASS antes da linha de base**; a meta final deverá ser proposta com base nos resultados e aprovada na homologação. Regressão funcional ou de acesso reprova a fase mesmo com melhora de tempo.

## 7. Gates de segurança, integração e regressão

1. **Segurança (obrigatório P0):** acesso a blocos por `AccessResultInterface::isAllowed()`, contextos requeridos, usuários anônimo/autenticado/admin, 403/404 MAIN vs subdomínios, ausência de `build()` negado, HTML de formulário sem cache público, CSRF e nenhuma informação privada nas prévias.
2. **Catálogo universal:** comparação determinística do conjunto de IDs SDC/stories/variantes antes e depois; todos os 72/115 da fotografia continuam presentes, adicionados novos componentes do HEAD, e nenhuma integração compatível perde opções.
3. **Integrações:** UI Patterns Blocks/Field/Field Formatters/Layouts/Views, Layout Builder nos displays existentes, UI Styles/Styles Page, UI Icons/UI Icons Menu/Picker quando habilitados, configurações previamente salvas e exportação Sync sem alteração indevida.
4. **Render/caches:** nada de `clearCachedDefinitions()` no preprocess, cache frio/quente, invalidação explícita após mudança de SDC/stories, bubbleable metadata de UI Patterns e respostas HTMX, fallback sem JavaScript.
5. **Visual/acessibilidade:** 320/390/1440 CSS px, zoom 200%, teclado e foco, claro/escuro via `data-theme`, sem overflow ou duplicação de UI, exibição correta de formulários reais.
6. **Gates do projeto, quando código alterado:** consultar `docs/operations/GATES.md` e `web/themes/custom/aculta420_v2/docs/gates.md`. Em especial `php scripts/validate-aculta-ui-library.php`, `validate-admin-routes-main-domain.php`, `validate-aculta420-sdc-schemas.php`, `validate-aculta420-v2-foundations.php`, `validate-module-theme-boundary.php`, lint/Unit/Kernel/FunctionalJavascript do escopo. Acrescentar regressões que os validadores atuais não cobrem (não confundir gate atual aprovado com garantia de SEC-01).
7. **Higiene:** `php -l` dos PHP alterados; sem mudanças em config ativa, banco ou temas de teste não autorizados. Gate não executado = `DEFERRED` com motivo.

## 8. Riscos, reversibilidade e definição de pronto

- **Risco de componentes ausentes:** proibição de filtrar descoberta; comparar inventário em toda mudança. Ao falhar, reverter somente a camada de catálogo progressivo.
- **Risco de cache/privacidade:** manter `no_cache` de prévias reais e revisão por usuário/contexto. Separar cache de metadados da saída renderizada.
- **Risco de formulário real nas stories:** execução apenas sob demanda, sem reaproveitar token/HTML entre usuários.
- **Risco de quebra upstream:** backup de `composer.lock` via Git, upgrade escopado e retorno reversível; não editar arquivos contrib.
- **Risco de HTMX/JS:** links de prévia reais funcionam sem JS; teste de substituição e foco.
- **Risco de documentação desatualizada:** o HEAD e a contagem podem evoluir em paralelo; sempre repetir inventário antes da execução.

**Pronto** apenas quando: SEC-01 comprovadamente corrigido, PERF-01 sem invalidação em request, renderização eager eliminada no overview sem sumiço de SDC, histórias/forms existentes acessíveis, benchmarks comparáveis publicados, integrações preservadas, gates pertinentes executados e rollback testado. Situação atual: **execução concluída com sucesso; adicionado botão global "Exibir todas as prévias" com controle de concorrência**.

## 9. Extensão pós-UP-07: Botão global "Exibir todas as prévias"

Para permitir ao administrador visualizar todas as histórias de forma controlada sem abrir mão da performance inicial, foi adicionado um controle global na barra de ferramentas da biblioteca:

1. **Localização e Aparência:**
   - Adicionado à barra de ferramentas superior (`.aculta-component-library__toolbar-actions`), alinhado ao campo de busca.
   - Implementado utilizando o SDC nativo `aculta420_v2:button`, com variante `secondary` e ícone Phosphor `eye`.
   - Inclui região de status acessível com `aria-live="polite"` e atributos de acessibilidade `aria-controls` e `aria-describedby`.
2. **Arquitetura da Fila e Limite de Concorrência:**
   - Implementado em `web/themes/custom/aculta420_v2/js/ui-library-bulk-preview.js` como um `Drupal.behavior` isolado.
   - Limite de concorrência estrito configurado em **2 requisições simultâneas** (`CONCURRENCY_LIMIT = 2`), evitando sobrecarga no servidor.
   - Detecta dinamicamente todos os componentes com prévias pendentes e coordena as requisições via `htmx.ajax()` ou fetch compatível.
   - Componentes já carregados individualmente são automaticamente ignorados e contabilizados.
3. **Controle de Ciclo de Vida:**
   - Suporta cancelamento em tempo real (o botão alterna para "Cancelar carregamento" e pausa a fila imediatamente).
   - Permite continuar de onde parou ou realizar retry específico de componentes que retornaram erro.
   - Preserva o catálogo universal, filtros de pesquisa por nome/ID e abas de navegação.

## 10. Arquivos de referência

- `web/themes/custom/aculta420_v2/src/Hook/UiPatternsStoryHooks.php`
- `web/themes/custom/aculta420_v2/templates/ui-patterns-overview-page.html.twig`
- `web/themes/custom/aculta420_v2/templates/ui-patterns-stories-compact.html.twig`
- `web/themes/custom/aculta420_v2/templates/ui-patterns-stories-full.html.twig`
- `web/themes/custom/aculta420_v2/js/ui-library-tabs.js`
- `web/themes/custom/aculta420_v2/src/Story/AuthDialogStory.php`
- `web/modules/custom/aculta_portal/modules/aculta_ui_library/src/Controller/UiPatternsLibraryRedirectController.php`
- `web/modules/custom/aculta_portal/modules/aculta_ui_library/aculta_ui_library.routing.yml`
- `web/themes/custom/aculta420_v2/aculta420_v2.ui_styles.yml` e `aculta420_v2.icons.yml`
- `config/sync/core.extension.yml`, `composer.json`, `composer.lock`
- [UI Patterns 2 releases](https://www.drupal.org/project/ui_patterns/releases)
- [Issue #3528205: source selector on demand](https://www.drupal.org/project/ui_patterns/issues/3528205)
- [Issue #3593106: context resolution](https://www.drupal.org/project/ui_patterns/issues/3593106)
- [Drupal BlockPluginInterface::access()](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Block%21BlockPluginInterface.php/function/BlockPluginInterface%3A%3Aaccess/11.x)
