# ACULTA420 V2 - Plano de refatoracao SDC First, Layout Builder e CSS multidominio

**Estado:** arquitetura decidida; implementacao por pacotes e homologacao pendentes. Decisao: ADR-054. Branch dev. Referencia inicial: 2026-11-09.

## 1. Objetivo e limites

Um so tema ACULTA420 V2, com cinco regioes, shell compartilhado, componentes SDC reutilizaveis, bibliotecas CSS/JS seletivas, Layout Builder nos displays compativeis, landing pages especificas para dez purposes e catalogo de SDCs/blocos Drupal com previas reais apenas para administradores. Migracao paralela no mesmo tema e sem criar nova branch/PR. A funcao administrativa 'Layout de container' deixa de existir; interpretacao de configuracoes antigas persiste apenas ate o corte.

O Portal e dono de dominio, acesso, URL, usuario, permissionamento, dados e cache. O tema recebe contrato neutro (aculta_purpose/data-purpose, data-theme, render arrays e props). Nao introduzir comparacoes de hostname no tema.

## 2. Matriz de dominios

| Purpose | Domain ID | Frente principal |
| --- | --- | --- |
| main | aculta_org | Portal e painel administrativo MAIN |
| account | conta_aculta_org | Conta e Marley |
| support | apoio_aculta_org | Apoio e doacoes |
| magazine | revista420_aculta_org | Revista |
| wiki | wiki420_aculta_org | Wiki e WE Sativa |
| shop | loja_aculta_org | Loja e Green Stash |
| courses | cursos_aculta_org | Cursos e GanjaLab |
| forum | forum_aculta_org | Forum / Growroom420 |
| links | links_aculta_org | Links / LinkWeed: PILOTO |
| eventos | eventos_aculta_org | Eventos e Culturas |

Resolver hosts por DomainPurposeManager e aliases registrados; esta tabela nao define hostnames fixos. Nao supor que uma funcionalidade tenha apenas um hostname: componentes forum, mapas, comentarios, carrinho e mensageria podem aparecer transversalmente.

## 3. Estado de partida

- Nove regioes no info.yml: institution, header, navigation, highlighted, help, breadcrumb, content, sidebar_first, footer.
- Cinco regioes finais: institution, header, content, sidebar_first, footer.
- Trinta e seis blocos block.block.aculta420_v2_* exportados inicialmente, entre eles cinco em highlighted e seis em header. Recontar no runtime e conferir overrides por Domain antes da migracao.
- Domain Config e a ponte aculta_domain_config ja foram introduzidos no MAIN; ler docs/portal/domain-config/LAYOUT-BLOCOS.md e TESTES.md. A ponte cobre overrides da configuracao de blocos, nao o Layout Builder automaticamente.
- O Config Sync lista layout_builder, ui_patterns_layouts, ui_patterns_blocks e ui_patterns_library habilitados, mas a adocao de Layout Builder deve ser validada display a display.
- Existem SDCs, UI Styles, stories e bibliotecas com CSS especifico carregado globalmente, como studio.css, forum.css e portal-account.css.
- O painel 'Layout de container' sera retirado de theme-settings.php; nao remover schema aculta_purpose_container, PurposeContainerLayout nem CSS antigo antes do corte.
- Compatibilidade e testes: Drupal 11.5/Domain 4 alpha e UI Patterns 2.x de desenvolvimento devem ser verificados nas versoes efetivamente instaladas, sem atualizacao automatica.

## 4. Pacotes de implementacao

### P0 - Inventario e baseline

Ler AGENTS.md da raiz/tema, ADR-054 e documentos do Domain Config, inspecionar git status e origin/dev, Drupal instalado e config:status. Produzir inventario auditavel de blocos (id, plugin, region, weight, status, visibility, base/override, cache, destino), de displays (bundle/view_mode, layout atual, permissao, dono da renderizacao, compatibilidade com Layout Builder), de CSS/JS (arquivo -> library -> pagina/SDC -> purpose) e das rotas/landing pages. Capturar screenshots e transferencia real de rede antes de alterar layout. Preservar snapshots e rollback.

### P1 - Regras e documentacao

Publicar ADR-054, atualizar instrucoes de agentes, README, docs/configuracao-do-tema, docs/architecture e docs/roadmap. Decidir CSS por quatro camadas: foundation global minima, tokens/identidade por purpose, bibliotecas de aplicacao para comportamento compartilhado e CSS/JS encapsulados no SDC. jQuery 4 via core/jquery sob demanda em modulos e SDC; Drupal.behaviors + once; HTMX conserva papel do ADR-050 em requisicoes. Nenhuma duplicacao ou jQuery global por padrao.

### P2 - Remover painel legado e isolar compatibilidade

Remover do theme-settings.php a secao aculta_container_purpose, seus selects e o caminho de escrita administrativa de aculta_purpose_container. Preservar a leitura dos valores antigos no renderizador atual para evitar regressao da arquitetura ativa. Identificar os usos remanescentes de PurposeContainerLayout: separar isComponentLibraryRoute da parte de largura antes de eliminar a classe. Marcar docs anteriores sobre o painel como historicos/superados. Testar que o formulario de cores por purpose continua funcionando e nao apresenta Layout de container.

### P3 - Shell paralelo e migracao das regioes

Criar novo shell/adaptador opt-in sem substituir imediatamente page.html.twig ativo. Manter institution/header/content/sidebar_first/footer, mais placeholders page_top/page_bottom. Preservar o bloco institucional aculta_institution_bar no institution segundo ADR-053. Mapear navigation para header ou composicao, highlighted para renderizacao contextual (mensagens, acoes, abas, quick apoio), help/breadcrumb para SDCs da area interna. Migrar cada instancia block.block e seus overrides antes de remover regiao do info.yml. Nao duplicar H1, menu, breadcrumb ou mensagens. Validar todas as regioes em anonimato e autenticado.

### P4 - Biblioteca de layouts SDC e Layout Builder

Adicionar ou adaptar SDCs de layout com slots e schemas estritos: 1 coluna, 2 colunas, conteudo+aside, full, grid e section; thumbnails para seletor, stories, CSS local e responsividade. Reusar layout-page atual quando possivel. Integrar ui_patterns_layouts, ui_patterns_blocks, ui_styles e Layout Builder. Habilitar Layout Builder em **todos os displays compativeis por lotes**, nao de modo indiscriminado. Quando um display exigir controlador/template proprio, manter fallback ate um adapter seguro ser validado. Separar escolha de layout de renderizacao de entidades (conteudo) da selecao de regioes do shell (Block Layout).

### P5 - Dez landing pages independentes e Domain Config

Layouts padrao compartilhados nos displays; landing page especifica por purpose, com fonte de conteudo e roteamento explicitos. Nao usar Domain Config como se fosse automatico para core.entity_view_display ou layout_builder__layout; estudar a estrategia de pagina-inicial por purpose e validar cache/domain/aliases. Piloto: Links/LinkWeed, mantendo o rodape social e a UI institucional existentes. Expandir para os outros nove somente apos aceite do piloto. Nao usar URLs absolutas hardcoded nem mutar o dominio ativo para compor preview.

### P6 - Blocos do Drupal na UI Library

Catalogar plugins nativos system_main_block, page_title_block, system_breadcrumb_block, system_messages_block, help_block, local_tasks_block, local_actions_block, system_branding_block, system_menu_block e blocos Views/Block Content existentes. Preservar os plugins originais e suas configuracoes/cache/acesso; UI Patterns adapta visualmente. Para previas reais: rota somente de administrador, validacao tambem de acesso ao bloco, a entidade e o contexto do dominio, render array e cache metadata preservados; nenhum conteudo privado em cache publico. Nao usar fixtures no preview operacional. Se nao existir contexto apropriado ou acesso, informar 'Previa indisponivel'. Evitar executar efeitos colaterais via plugin build.

### P7 - Otimizacao de CSS, JS e biblioteca de componentes

Reduzir global-styling a tokens/base/shell necessarios. Fragmentar studio.css, forum.css, messenger-drawer.css, wiki.css e afins por componentes e bibliotecas contextuais. Nao mover CSS para um dominio se ele serve componentes usados em varios dominios; os CSS de purpose devem sobretudo definir identidade e variaveis semanticas. Preservar paletas Skunk Family e data-theme. Auditar @layer somente com teste de precedencia; medir duplicacao, cascade e JS listeners HTMX/jQuery. Fontes/imagens/icons com medicao de rede, nao inferir performance so pelo tamanho do repositorio.

### P8 - Homologacao paralela

Gates de PHP/Twig/SDC/schema/CSS, Kernel e FunctionalJavascript, cobertura de permissionamento, render cache, contexts, configuracao, import/export em staging, operacoes do Domain Config e previas. Capturar antes/depois de todos os dez purposes nos modos claro/escuro e resolucoes 320, 390, 768, 1024, 1440 px, zoom 200%, teclado, leitor de telas quando viavel; testar estados anonimos/autenticados e HTMX. Nos displays, incluir telas de Revista, Forum, Wiki, Eventos, Loja, LMS, Conta e Apoio. Testes negativos: sem acesso, sem renderizacao de dados privados, sem bloco de outro purpose, sem operacoes administrativas fora do MAIN. Medir bytes transferidos, CSS utilizado, LCP/CLS/INP quando viavel. Teste nao executado = DEFERRED.

### P9 - Cutover reversivel e limpeza

Feature flag por purpose/display em desenvolvimento; piloto Links. Aprovar apenas com equivalencia visual/funcional, gates e rollback testados. Depois migrar os demais por lotes. Remover regioes antigas somente quando nenhuma entidade block.block ou override depender delas. Retirar leitura de aculta_purpose_container, schema, data-purpose-layout/style e variaveis CSS apos confirmar os usos. Eliminar legado CSS/JS/templating somente por evidencia de ausencia de consumidores. Nao apagar dados nem rodar drush cim/updatedb sem autorizacao explicita.

## 5. Gates e criterios de aceite

Validadores existentes do tema, conforme docs/gates.md:
~~~sh
php scripts/validate-aculta420-sdc-schemas.php
php scripts/validate-aculta420-design-foundations.php
php scripts/validate-aculta420-v2-foundations.php
php scripts/validate-aculta420-mapped-classes.php
npm run lint:css
~~~
A validacao de shell e renderizacao SDC podem exigir Drush no Runtime; conferir os comandos exatos no documento de gates.

Gates novos requeridos:
1. assert de ausencia do campo administrativo aculta_container_purpose e preservacao do formulario das paletas.
2. inspeção de 100% das entidades Block e overrides apontando somente para regioes existentes apos cutover.
3. comparacao shell atual vs novo e ausencia de H1/menus/mensagens duplicados.
4. teste de composicao Layout Builder por bundle/view_mode, permissao de override e fallback.
5. previas reais visiveis so a administradores, com negacao de acesso a entidades privadas e cache correto.
6. cache cross-domain em ordem normal/inversa, aliases, config import/export em ambiente apropriado.
7. CSS e JS anexados por componente/rota e teste com interacoes apos troca HTMX.
8. rollback que restaura a renderizacao anterior sem perder configuracoes globais ou de dominio.

**Bloqueios:** nenhum bloco orfao, nenhuma mudanca de permissao de conteudo, nenhuma regressao critica, nenhum vazamento de cache ou entidade por dominio, import/export testado no staging e aceitacao visual do piloto. Nao declarar homologacao de codigo alterado so com leitura estatica.

## 6. Operacao de agentes

Branch dev; preservar trabalho paralelo e nao criar novas PRs. Commits atomicos, documentacao e tests por pacote, trailer Claude-Session, push verificado, estado de sessao conforme AGENTS.md. Runtime de desenvolvimento somente, com sync seguro de codigo e drush cr conforme regras; configuracao ativa/dados requerem aprovacao especifica. Registrar por pacote: arquivos, SHA, gates, capturas, riscos, rollback, PASS/FAIL/DEFERRED.

Referencia Domain Config (implementacao ja em andamento): docs/portal/domain-config/; nao confundir esta entrega com a homologacao do Layout Builder por dominio.
