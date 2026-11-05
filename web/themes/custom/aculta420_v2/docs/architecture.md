# Arquitetura do tema ACULTA420 V2

Este documento descreve como o tema está montado hoje (`1.2.0-dev.2` mais o menu de conta não versionado). Para decisões, veja os ADRs em `docs/decisions/`; para status, veja [roadmap.md](roadmap.md).

## 1. Camadas

Dependência de cima para baixo. Uma camada não importa de baixo sem passar pelo contrato.

```text
Tokens primitivos (paleta)        → só dentro de src/styles/tokens.css
Tokens semânticos                 → usados pelos componentes
Fundações                         → tipografia, espaçamento, foco, movimento, reflow
Componentes SDC                   → apresentação, props com schema estrito
Shell                             → barra institucional, navegação, regiões da página
Templates de página e de conteúdo → Revista migrada; outros tipos ainda a fazer
```

Hoje estão entregues as três primeiras camadas, os componentes do shell e a navegação SDC. Os templates e SDCs da Revista já adaptam as listagens, teasers, artigos e autoria; os demais tipos de conteúdo seguem pendentes (ADR-046, Fases 4–5).

## 2. Fronteira com o Portal

O tema não conhece hostnames, domínios, purposes ou regras de acesso. Ele recebe variáveis de página preparadas pelo `aculta_portal`.

| Variável | Origem | Uso no tema |
| --- | --- | --- |
| `domain_presentation.layout.institution_bar_account` | Portal | marca a barra como barra de conta |
| `domain_presentation.layout.show_utility_bar` | Portal | exibe ou oculta as utilidades da barra |
| `domain_presentation.regions.showcase` | Portal | links da vitrine de portais (`multidomain_network_links`) |
| `domain_presentation.identity.purpose` | Portal | chave neutra do purpose atual |
| `domain_presentation.accent` | Portal; **pendente (D1)** | valor neutro `green`, `yellow` ou `red` para o SDC `card-cultural`; ausente no contrato atual, então consumers não devem enviar/mapeamento permanece DRAFT |
| `aculta_domain_sites` | Portal (`DomainPresentationBuilder`) | links dos sites publicados, com purpose, nome, URL e sinalização do atual |
| `aculta_account_name` | Portal (`aculta_user`) | apelido aparado da pessoa autenticada; `NULL` sem apelido |
| `aculta_account_menu` | Portal (render array de `system_menu_block:account`) | slot de itens do menu de conta; o PHP do tema o encaixa no SDC |
| `aculta_account_authenticated` | tema (`PagePresentationHooks`, via `current_user`) | escolhe o estado visitante ou autenticado do menu |
| `aculta_account_avatar` | Portal (`aculta_user`, AvatarResolver) | slot do avatar do menu de conta; ausente → ícone neutro |
| `aculta_cart` | Portal (Commerce) | gatilho e painel do carrinho |
| `aculta_messenger` | Portal (`aculta_messenger`) | gatilho e painel de mensagens |
| `aculta_alerts` | Portal | gatilho e painel de avisos |
| `aculta_search_utility` | Portal (`aculta_portal/search-preview`) | gatilho de busca |
| `aculta_brand_home` | Portal | destino do logotipo |
| `aculta_color_mode` | Portal | `light` ou `dark` (demais valores viram `light`) |
| `aculta_user_author_card` | `aculta_user` | render array cacheável de identidade pública; o template do tema o encaminha ao SDC `author-card` |

Regras:

- O Portal não instancia componentes do tema. O tema não resolve regra de domínio.
- Links que atravessam purpose chegam como URL absoluta, gerada pelo `DomainPurposeManager` no Portal.
- A chave neutra de purpose (Fase 3) ainda não existe no contrato. Quando existir, o tema aplica `data-purpose` no `<html>` e ajusta tokens de destaque, sem conhecer o domínio.

## 3. Modo de cor

- `SurfaceSettingsHooks::preprocessHtml()` define `data-theme` no `<html>` a partir de `aculta_color_mode`. Valor diferente de `dark` vira `light`.
- `src/styles/tokens.css` define `:root` (claro) e `:root[data-theme="dark"]`.
- Não há detecção automática do sistema. Um valor legado `auto` resolve para claro até o contrato mudar.
- Componentes não conhecem o modo. Eles usam tokens.

`src/Hook/SurfaceSettingsHooks.php` é a implementação única de `preprocess_html`
do tema: aplica `data-theme` no `<html>` e classes neutras no `<body>` derivadas
das configurações de raio, sobrescrita de acento e visibilidade da faixa. No
hook, o Core fornece o atributo do `<body>` como `attributes`; os valores vêm de
`ThemeSettingsProvider`, com fallback aos defaults e validação dos enums. O hook
implementa `ContainerInjectionInterface` para resolver dependências mesmo quando
o mapa de hooks armazenado antecede uma compilação do container. Não consulta
domínio nem purpose. Instruções operacionais estão em
[`configuracao-do-tema.md`](configuracao-do-tema.md).

## 4. Tokens e fundações

`src/styles/tokens.css` contém:

- cores semânticas de texto, superfície, marca, destaque, borda, estado e foco;
- a base visual herdada da V1 (verde e vermelho editorial, mídia, sombra elevada), com valores para o modo escuro;
- escalas de tipografia (Inter e Oswald), espaçamento, raios e sombras;
- `--tap-target-min: 44px`;
- aliases de compatibilidade (`--color-primary`, `--border-radius-*`, `--shadow-*` antigos), mantidos apenas enquanto houver consumidores.

`base.css` e `layout.css` definem reset, foco visível, movimento reduzido, prevenção de overflow em conteúdo longo e a estrutura da página. `wiki.css` adapta os wrappers de listagem e busca da Wiki420. A biblioteca `global-styling` carrega essas folhas e as duas fontes externas do Google Fonts.

## 5. JavaScript

Todo o JavaScript é vanilla, com `Drupal.behaviors` e `core/once`. Não há jQuery.

| Arquivo | Papel |
| --- | --- |
| `components/multidomain-bar/multidomain-bar.js` | sincroniza a altura da barra com o espaço da página e com a barra administrativa; abre o popup de login (`/entrar/janela`) e o popup de busca (`/busca/popup`) por Fetch, em `<dialog>` nativo |
| `components/navigation-bar/navigation-bar.js` | drawer de navegação mobile: abre, fecha com Escape e devolve o foco |
| `components/data-table/data-table.js` | marca como região nomeada/focável somente a tabela com overflow; observa resize e atualizações Drupal e limpa observers no detach |
| `js/utility-dialogs.js` | diálogos utilitários com `data-aculta-utility-drawer`: abre por `showModal()`, contém Tab/Shift+Tab, fecha por botão, Escape ou clique no fundo e atualiza `aria-expanded` dos gatilhos |
| `js/messenger-drawers.js` | conteúdo de mensagens/avisos em gavetas, filtros por fragmentos, estados loading/error, abort de resposta antiga, novo contato e ações de conversa |
| `js/counters.js` | atualiza contadores das utilidades, com zero oculto |
| `js/alerts-dismiss.js` | dispensa avisos e atualiza o contador |

Os adapters de tema `templates/content/node--revista--teaser.html.twig` e
`node--revista--full.html.twig` passam os campos renderizados do nó para os
slots de `revista-teaser` e `revista-publication`. A View
`templates/views/views-view--revista-sessoes--page-revista.html.twig` encaminha
suas linhas já renderizadas para `revista-listing`. A folha dos SDCs preserva a
grade editorial, capa 16:9, destaque da primeira publicação em telas amplas e
coluna de leitura do artigo. O adapter
`templates/aculta-user-author-card.html.twig` converte a API visual
`aculta_user_author_card` para `author-card` sem ler entidades, perfil ou foto.

Os adapters `templates/content/node--wiki-entry--teaser.html.twig` e
`node--wiki-entry--full.html.twig` encaminham campos e ações renderizados pelo
Drupal ao SDC `wiki-entry`; o componente aplica a composição visual da V1 para
cartões e verbetes. `src/styles/wiki.css` trata apenas as listagens, busca,
categorias e mudanças recentes fornecidas pelo owner da Wiki. O Domain Header
continua sob o contrato do shell.

A biblioteca `utility-drawers` reúne os quatro últimos. Os diálogos nativos usam `data-aculta-utility-drawer` como contrato de comportamento; estilos específicos de cada SDC ficam isolados para que um modal não herde dimensões de gaveta. O popup de login e o de busca não usam `core/drupal.dialog.ajax`: a V2 remove essa dependência do utilitário de busca (`PagePresentationHooks::removeSearchDialogDependency`).

Com JavaScript desligado, os links dos gatilhos continuam válidos: o login e a busca abrem as páginas próprias do Portal.

## 6. Shell

- `templates/layout/page.html.twig` monta a página e inclui a barra institucional (`multidomain-bar`) antes do `page.header`.
- A região `footer` é apresentada pelo SDC `site-footer`; quando o Portal fornece `social_links_page_footer`, usa a variação `social-links` (C4).
- `templates/aculta-links-page.html.twig` adapta o hook de apresentação do submódulo Links ao SDC `social-links-page`, sem resolver dados ou regras de domínio.
- A barra institucional é fixa. A altura medida dela vira `--aculta-institution-bar-height`, e um espaçador (`.aculta-institution-bar-placeholder`) mantém o conteúdo abaixo dela.
- O bloco `system_menu_block:main` é envolvido pelo SDC `navigation-bar` no mesmo peso e posição do cabeçalho. O render array original fica como slot, com access e cache preservados.
- O menu de conta (`account-menu`) fica dentro da barra. Visitante vê "Entrar"; autenticado vê o disclosure com avatar e as ações de conta (ver [components.md](components.md)).
- A barra institucional convive com a barra administrativa Gin e com o toolbar Core: a faixa fica acima das duas camadas, e cada uma começa após a altura medida da faixa.

## 7. Hooks e registro

- `#[Hook]` em classes com injeção de dependência. Não há `\Drupal::` em `src/`.
- `PagePresentationHooks` (`preprocess_page`) mapeia o contrato do Portal para variáveis do tema, move o menu de conta para a barra, envolve o menu principal com `navigation-bar` e remove a dependência de diálogo AJAX do utilitário de busca.
- O mesmo hook define `aculta_layout_variant` por bundle do nó ou nome de rota: wiki/fórum `aside`, revista `full`, home e catálogo/mapa de eventos `full`; demais rotas `stack`. Não consulta hostname nem purpose, e entrega ao SDC somente a variante. Catálogo e produto de loja aguardam autorização explícita de tarefa.
- Dashboard do Studio na raiz `account` e rotas de Minha Conta (`/conta/*`), Perfil, WE Sativa, Ganjalab, aula LMS e Culturas escolhem `aside`; a raiz usa `aculta_studio.dashboard` e `/conta` apresenta Dados cadastrais privados. O Portal bloqueia acesso direto ao path interno do dashboard e aplica a guarda de autenticação.
- `SurfaceSettingsHooks` (`preprocess_html`) aplica o modo de cor e as classes de superfície em uma única implementação.
- Evento: o adapter aculta-event-page.html.twig consome render arrays do módulo no SDC event-page-layout, cuja story é event-page-layout.default.story.yml. FAQ: node--aculta-faq--full.html.twig apresenta cada resposta por accordion; o conteúdo continua vindo do render array do Portal. Aula LMS: na rota aculta_lms.lesson_view, o tema envolve o render array original em lms-lesson-page; o módulo permanece dono de acesso, cache, atividade e conteúdo.
- Evento: o adapter `aculta-event-page.html.twig` consome render arrays do módulo no SDC `event-page-layout`, cuja story é `event-page-layout.default.story.yml`. FAQ: `node--aculta-faq--full.html.twig` apresenta cada resposta por `accordion`; o conteúdo continua vindo do render array do Portal.
- `src/Hook/*` é a única camada PHP do tema. Lógica de negócio não pertence aqui.

## 8. Dependências e compatibilidade

- Drupal `^11.5 || ^12`. O desenvolvimento roda no 11.5 do servidor de desenvolvimento. Compatibilidade com 12 é meta, sem validação ainda (ADR-045).
- Ícones Phosphor (MIT, Core 2.1.1) estão vendorizados em `assets/icons/phosphor/` e são injetados inline pelo componente `icon`.
- Não há UI Styles nem CTools no tema hoje. UI Styles entra na Fase 4; CTools fica no Portal, conforme ADR-044.
