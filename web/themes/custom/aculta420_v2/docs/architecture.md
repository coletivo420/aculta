# Arquitetura do tema ACULTA420 V2

> **Atualização SDC-first (2026-11-13):** os aliases `--aculta-studio-fidelity-*`
> foram removidos do CSS do `studio-shell`; valores sem consumidor foram
> descartados e os restantes agora usam diretamente tokens/valores nas regras.
> Título Marley e painel de Conta pertencem aos SDCs
> `studio-section-header` e `account-experience-dashboard`, respectivamente.
> Validação autenticada destas superfícies segue pendente.

> **Remoção de pontes Twig do Studio (2026-11-13):** a Conta, cartões de
> experiência, calendário e conexões agora são render arrays dos SDCs existentes;
> os quatro theme hooks e seus templates de módulo/tema foram removidos. O
> controller/provider preserva props, slots, acesso e metadados de cache.

> **Corte de overrides de apresentação (2026-11-13):** 35 Twig overrides de
> conteúdo, Views, Fórum, Revista, Wiki, Loja, Doação, Eventos, Links e Portal
> foram removidos para retornar aos displays/Views e fallbacks dos módulos/Core.
> Os mapas e descrições de consumers logo abaixo são históricos e não provam o
> estado atual. Os dois templates de comentário foram mantidos por preservarem
> atributos, permalinks, estados e acessibilidade do Core. Configuração de todos
> os displays/Views ainda não foi migrada; consulte
> `docs/architecture/RELATORIO-CORTE-TEMA-UI-SUITE-2026-11-13.md`.

> **Revisão focal (2026-11-11):** correções de contraste institucional,
> orientação da sidebar em `layout-page--aside`, variantes de fallback
> dos shells e token `--aculta-surface-raised`. Detalhes, limites
> e pendências em
> `docs/architecture/ACULTA420-V2-AUDITORIA-CSS-LAYOUT-2026-11-11.md`.
> A validação em Runtime ainda está pendente.

> **Atualização normativa (2026-11-13):** ADR-054, complementada pela ADR-056, determina o corte direto da apresentação legada e a adoção de SDC/UI Suite. Esta arquitetura descreve a implementação atual; planos de migração paralela são históricos. O painel de container foi retirado e os valores antigos não selecionam variantes do shell.

Este documento descreve a arquitetura do tema `1.5.0` conforme `aculta420_v2.info.yml`; alterações locais ainda não publicadas devem ser confirmadas no checkout. Para decisões, veja os ADRs em `docs/decisions/`; para status, veja [roadmap.md](roadmap.md).

## 1. Camadas

Dependência de cima para baixo. Uma camada não importa de baixo sem passar pelo contrato.

```text
Tokens primitivos (paleta)        → só dentro de src/styles/tokens.css
Tokens semânticos                 → usados pelos componentes
Fundações                         → tipografia, espaçamento, foco, movimento, reflow
Componentes SDC                   → apresentação, props com schema estrito
Shell                             → barra institucional, navegação, regiões da página
Templates de página e conteúdo → somente adaptadores necessários; display Drupal/UI Patterns quando disponível
```

Hoje estão entregues as três primeiras camadas, os componentes do shell e a navegação SDC. Há adapters específicos para Revista, Wiki, Fórum, FAQ, evento, Links e aula LMS; outros bundles mantêm o template Core para renderizar os campos definidos pelo display Drupal.

## 2. Fronteira com o Portal

O tema não conhece hostnames, domínio Drupal ou regras de acesso. Ele recebe variáveis de página preparadas pelo `aculta_portal`.

| Variável | Origem | Uso no tema |
| --- | --- | --- |
| `domain_presentation.layout.institution_bar_account` | Portal | marca a barra como barra de conta |
| `domain_presentation.layout.show_utility_bar` | Portal | exibe ou oculta as utilidades da barra |
| `domain_presentation.regions.showcase` | Portal | links da vitrine de portais (`multidomain_network_links`) |
| `aculta_site_key` | Portal (`AppearanceHooks::preprocessHtml`) | identidade funcional neutra aplicada como `data-site-key`; não determina cores |
| `aculta_skin_id` | Portal (`DomainSkinResolver`) | identidade visual resolvida por Domain ID e aplicada como `data-skin` |
| `domain_presentation.accent` | Portal; **pendente (D1)** | valor neutro `green`, `yellow` ou `red` para o SDC `card-cultural`; ausente no contrato atual, então consumers não devem enviar/mapeamento permanece DRAFT |
| `aculta_domain_sites` | Portal (`DomainPresentationBuilder`) | links dos sites publicados, com site key, nome, URL e sinalização do atual |
| `aculta_account_name` | Portal (`aculta_user`) | apelido aparado da pessoa autenticada; `NULL` sem apelido |
| `aculta_portal_menu` | Portal (render array de `system_menu_block:account`) | slot de itens do menu de conta; o PHP do tema o encaixa no SDC |
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

- Os módulos owners podem compor SDCs existentes por render arrays quando o contrato visual é explícito; não colocam regra de domínio no componente nem passam entidades/usuários como props. O tema não resolve acesso nem regra de domínio.
- Links que atravessam sites chegam como URL absoluta, gerada pela API funcional `DomainSiteManager` no Portal.
- O modo de cor vem da preferência do usuário e vira `data-theme`. UI Skins publica cores por `data-skin` e `data-theme`; `site_key` permanece funcional e não escolhe cores. As configurações e os tokens visuais antigos de purpose foram removidos do tema.

### Placeholders e Pedidos ao Portal (Fórum Growroom420)

Conforme a seção 5 e 6 do plano visual Growroom420 (`GROWROOM420-VISUAL-PLANO.md`), o tema não consulta regras de domínio, banco de dados ou entidade. Foram estabelecidos contratos e os seguintes dados permanecem como pedidos pendentes ao Portal:
- Papel do autor e variante de faixa (`stripe: brand | highlight | member`).
- Contagem de posts do autor e total de visualizações por tópico.
- Prévia do último post do tópico.
- Lista de moderadores e participantes da comunidade.
- Estado "seguindo" e "salvo" por usuário para os tópicos.

Enquanto esses dados não existirem nos contratos de variáveis ou views fornecidos pelo Drupal/Portal, o tema renderiza:
- Valores textuais e contadores ausentes como "—" acompanhados de `<span class="visually-hidden">Dado indisponível</span>`.
- Ações como "Seguir", "Salvar" e lista de moderadores com atributo `disabled` e `aria-disabled="true"` com pílula ou rótulo "Em breve".

### Componentes e Telas da Entrega 2 (Growroom420)

Na entrega 2 foram incorporados:
- `forum-sidebar-panels`: painéis laterais em coluna com faixas alternadas vermelha/amarela (`stats`, `featured`, `moderators`, `rules`, `related`). Moderadores e campos não entregues pelo backend operam com placeholder neutro e desabilitados com "Em breve".
- `forum-composer-guidelines`: diretrizes de publicação em duas colunas no desktop e uma coluna no mobile, faixa amarela no topo e detalhes nativos recolhíveis.
- A story `DiscussionTopicComposerStory` demonstra o formulário de criação dentro do SDC `discussion-topic-composer`. O template `node-add-form--aculta-forum-topic.html.twig` foi removido após confirmar que não existe hook `node_add_form`: o Core registra `node_edit_form`; o módulo mantém Form API, handlers, validação e acesso. Não considerar a story evidência de composição na rota de produção.
- Nova rota e template da View `/categorias/{termo}` (`page_category_term` em `views.view.aculta_forum_public_topics.yml`), integrando `forum-category-header`, `forum-tabs`, lista de tópicos e `forum-sidebar-panels`.

- O CSS do widget CAPTCHA/Turnstile pertence ao `aculta_portal` (biblioteca `captcha-presentation`, anexada por `CaptchaPresentationHooks`). O tema não cita CAPTCHA; a regra de fronteira é verificada pelo gate de fundação.

## 3. Modo de cor

- `SurfaceSettingsHooks::preprocessHtml()` define `data-theme` no `<html>` a partir de `aculta_color_mode`. Valor diferente de `dark` vira `light`.
- `src/styles/tokens.css` define `:root` (claro) e `:root[data-theme="dark"]`.
- Não há detecção automática do sistema. Um valor legado `auto` resolve para claro até o contrato mudar.
- Componentes não conhecem o modo. Eles usam tokens.

`src/Hook/SurfaceSettingsHooks.php` aplica `data-theme`, `data-site-key` e
`data-skin` no `<html>` e classes neutras no `<body>` derivadas
das configurações de raio, sobrescrita de acento e visibilidade da faixa. No
hook, o Core fornece o atributo do `<body>` como `attributes`; os valores vêm de
`ThemeSettingsProvider`, com fallback aos defaults e validação dos enums. O hook
implementa `ContainerInjectionInterface` para resolver dependências mesmo quando
o mapa de hooks armazenado antecede uma compilação do container. O Portal fornece
o Domain ID convertido em skin; UI Skins publica os valores CSS por scopes. O
tema não resolve domínio nem acesso. Instruções operacionais estão em
[`configuracao-do-tema.md`](configuracao-do-tema.md).

## 4. Tokens e fundações

`src/styles/tokens.css` contém:

- durações de movimento `--motion-instant`, `--motion-fast`, `--motion-base` e `--motion-slow`, usadas em transições e animações; opacidades de cor são feitas com `color-mix()` sobre tokens de cor;
- cores semânticas de texto, superfície, marca, destaque, borda, estado e foco;
- a base visual herdada da V1 (verde e vermelho editorial, mídia, sombra elevada), com valores para o modo escuro;
- escalas de tipografia (Inter e Oswald), espaçamento, raios e sombras;
- escala de títulos `--heading-h1-*` a `--heading-h6-*` (tamanho `clamp()` com mínimo mobile, peso e altura de linha) e espaçamentos `--heading-space-*`, consumidos pelo SDC `heading`; os valores móveis atendem 320/390 px e os máximos da referência de 1280/1920 px;
- `--tap-target-min: 44px`;
- aliases de compatibilidade (`--color-primary`, `--border-radius-*`, `--shadow-*` antigos), mantidos apenas enquanto houver consumidores.

`base.css` e `layout.css` definem reset, foco visível, movimento reduzido, prevenção de overflow em conteúdo longo e a estrutura da página. `wiki.css` adapta os wrappers de listagem, busca e estruturas de proposta da Wiki420; é carregada pela biblioteca contextual `wiki` quando o Portal fornece `site_key=wiki`. A biblioteca `global-styling` carrega as fundações, as duas fontes externas do Google Fonts e, desde 2026-11-12, `components/heading/heading.css`: o H1 de toda rota é renderizado pelo SDC `heading` via `templates/page-title.html.twig`, e o CSS precisa existir mesmo quando o componente é incluído por template (o que não anexa automaticamente o asset do SDC).

A biblioteca `linkweed-admin` carrega apenas em `aculta_linkweed.social_links_settings`, para a composição do formulário dinâmico de botões. Os links públicos e o rodapé usam os assets de `social-links-page` e `site-footer`.

A biblioteca `statistics` carrega `src/styles/statistics.css` e depende de `global-styling`. É anexada por `PagePresentationHooks::preprocessPage` somente nas rotas `aculta_statistics.dashboard` e `aculta_statistics.area`. Os seletores de layout que estavam em `portal-ui-map.css` foram movidos para cá; a home não baixa mais essas regras (verificado por requisição real).

As rotas `aculta_studio.*` envolvem o render array de conteúdo no SDC `studio-shell`, depois da composição dos SDCs filhos. O antigo `studio.css` agora é asset local do componente, dispensando biblioteca CSS separada; a folha só acompanha as páginas que têm a casca. O antigo `src/styles/studio-identity.css` foi removido em 2026-11-12: nenhum código do V2 aplica `html.studio-identity`, então suas regras nunca eram alcançadas. As regras do Studio que estavam em `layout.css` para a biblioteca de componentes foram movidas para `component-library-mode.css`, que só é carregado nas rotas da UI Patterns Library. A home não baixa CSS do Studio.

As demais folhas de aplicação também são contextuais: `portal-account` (purpose `account`, pelo contrato neutro), `public-profile-page` (SDC da rota `aculta_user.perfil_publico`), `event-organization` e `events` (`aculta_events.*`) e `wiki` (purpose `wiki`). A antiga biblioteca e folha `forum` foram removidas: as Views de listagem, categoria e busca carregam assets pelos SDCs `forum-list-page`, `forum-category-page` e `forum-search-page`; os cartões/comentários carregam os estilos de thread por seus componentes. A autoria editorial usa o SDC `article-authors`; seu CSS antes carregado globalmente agora é anexado somente quando o módulo `aculta_magazine` compõe a caixa de autores. `messenger-drawer.css` também saiu do global e é asset do SDC `messenger-trigger`, incluído apenas quando há URL de mensagens na barra institucional. `search.css` é anexado apenas à rota `aculta_portal.search`; os estilos do campo de prévia são do SDC `search-preview`. O antigo `public-profile` saiu da biblioteca contextual: `PagePresentationHooks` envolve a saída do perfil no SDC `public-profile-page`, que anexa o CSS sem mudar DOM ou cache. O antigo `support-page` saiu da biblioteca contextual: `PagePresentationHooks` envolve o formulário na rota `aculta_portal.support_form` no SDC `support-form`, que anexa seu CSS sem alterar o Form API. `global-styling` fica com fundações, formulários, superfícies e os mapas `portal-ui-*` compartilhados.

O relatório administrativo de requisitos é envolvido pelo SDC `portal-requirements`; a apresentação das seções fica no componente e o Portal mantém cálculo, autorização, dados, cache e biblioteca `system/admin`.

O shell único é definido por `templates/layout/page.html.twig`, que compõe o SDC `layout-page` com as regiões vigentes do tema. A grade e as áreas contextuais ficam em `layout.css`; não há biblioteca ou seletor de variante de shell.

`portal-account.css` foi portado do V1 em 2026-11-05 (cobre a Minha Conta: navegação, bio, redes, cursos e foto). As variáveis `--aculta-*` e `--bs-*` foram trocadas por tokens semânticos do V2 (ex.: `--aculta-text-primary` → `--color-text-primary`); as regras `.form-control` e `.table-responsive` do Bootstrap não foram portadas. Estado: pendente de captura visual (320 e 1440 px, claro e escuro); DEFERRED. `messenger-drawer.css` foi portado do V1 pelo mesmo critério (gavetas e conversas do Mensagens). Regras só do Bootstrap (`.btn`, `.bi`) saem dos seletores; regras do Mensagens são mantidas. Mesmo estado: DEFERRED. `public-profile.css` foi portado do V1 com o mesmo critério (perfil público): `--aculta-border-*` vira `--color-border`; `--aculta-interactive-*` vira `--color-primary`; `--aculta-surface-*` vira `--color-canvas`. Estado: DEFERRED (sem captura visual). `utilities.css` (classes `aculta-u-*`, `aculta-main-canvas`, `aculta-panel`/`aculta-card`) foi portado do V1 com o mesmo critério e **removido em 2026-11-12**: os utilitários `aculta-u-*` do mapa de tokens deram lugar a classes semânticas por domínio (`aculta-donation__*`, `aculta-shop-*`, `aculta-location-*`, `studio__actions`, `aculta-lms-*`, `aculta-we-sativa__*`), definidas nos CSS dos módulos; `.aculta-panel` foi para folhas de componente e `.aculta-u-block-start` (UI Styles de bloco configurado em `block.block.*`, fora do mapa) para `layout.css`. As equivalências Bootstrap do mapa (`.text-muted`, `.small`, `.table`, `.nav`, `.list-unstyled`, `.pager-*`) foram apagadas junto com os tokens que as usavam; `.container-inline` permanece sob o estilo nativo de `system/base`; o mapa de CSS do tema foi eliminado em 2026-11-13. `studio.css` foi portado do V1 (2110 linhas): os aliases `--aculta-studio-fidelity-*` continuam nos mesmos seletores de escopo do V1, mas agora apontam para tokens semânticos do V2 (ex.: `primary` → `--color-brand-primary`, `input-focus` → `--color-focus-ring`). Os valores de escala (fontes, espaçamento, raios) foram mantidos. Seletores só do Bootstrap foram removidos (2, ambos `.btn` sob atributo `data-portal-ui`). `--color-shadow-tint` foi adicionado a `tokens.css` para a sombra do Studio. Estado: DEFERRED (sem captura visual). `event-organization.css` (organização de eventos, 270 linhas) foi portado do V1 com o mesmo critério. `--aculta-border-subtle` virou `--color-border`, sem tom mais claro no V2, e `--aculta-status-warning-*` virou `--color-status-warning-surface` e `--color-warning`. Nenhum seletor do Bootstrap foi encontrado. Estado: DEFERRED (sem captura visual). Em 2026-11-05 houve uma correção de mapeamento: `--aculta-interactive-bg` e `--aculta-interactive-border` viram `--color-brand-highlight`, e `--aculta-interactive-text` vira `--color-on-highlight`, como no V1 (amarelo e verde-escuro); o mapeamento anterior, que usava a cor primária e a superfície, estava errado. Por isso `portal-account`, `messenger-drawer`, `public-profile` e `studio` foram regenerados a partir do V1 com o script de port. Seletores do Bootstrap (`.btn`, `.bi`, `.form-control`, `.close`) foram removidos; seletores com `:not()` foram mantidos. Nos portes de `buttons` e `search`, as propriedades locais `--aculta-btn-*` e `--aculta-button-*` substituíram as `--bs-btn-*`. Portados nesta rodada, com o mesmo critério: `search.css`, `statistics.css`, `studio-identity.css` (camada ativada por `html.studio-identity`) e `buttons.css`. Estado de todos: DEFERRED (sem captura visual). `portal-ui-extras.css` reúne só as regras do V1 que atendem as 36 classes mapeadas em `PortalUiHooks::TOKENS` que não tinham CSS no V2 (login, diálogos, consentimento, Revista, conta, suporte, notificações do Mensagens, painel de vendas e endereço). Origem de cada bloco indicada por comentário. Em 2026-11-05 a dívida foi resolvida: os nomes Bootstrap saíram do mapa do V2 (`bi` e `bi-*`, `breadcrumb`, `breadcrumb-item`, `btn`, `btn-link`, `form-control`, `btn-primary`, `btn-outline-primary`, `btn-outline-secondary`, `btn-sm`) e as 189 classes restantes receberam estilo mínimo por componente, com tokens semânticos. Os seletores `.btn`, `.btn-link` e `.form-control` também foram removidos dos CSS portados. O gate `validate-aculta420-mapped-classes.php` exige CSS ou exceção documentada em `docs/classes-sem-css-excecoes.md` e recusa nomes Bootstrap no mapa; PASS (453 classes, 0 exceções). O desenho visual completo por componente continua como evolução, não como dívida. `content.css` (conteúdo e cartões, 221 linhas do V1) foi portado com o mesmo critério. As propriedades `--bs-card-*` do V1 não foram portadas, porque o V2 não usa Bootstrap. `--aculta-space-5` virou `1.25rem` literal, sem token equivalente no V2. `--aculta-motion-fast` virou `180ms` e `--aculta-ease-standard` virou `ease`, como no V1. Estado: DEFERRED (sem captura visual). Os demais grupos de classes sem CSS no V2 continuam pendentes.

Atualização P7 (2026-11-12): a gaveta, estilos de conversa e agrupamento de avisos foram movidos de `src/styles/messenger-drawer.css`/`portal-ui-extras.css` para o asset SDC `components/messenger-trigger/messenger-trigger.css`. `multidomain-bar.twig` inclui o gatilho só com `messages_url`; portanto a biblioteca global não envia esses estilos a visitantes nem páginas sem a integração. A folha continua disponível em rotas autenticadas que renderizam o SDC. Captura autenticada da gaveta: DEFERRED.

O bloco `aculta_index_related_content` agora compõe o SDC `related-content` e passa a `item_list` como slot. O CSS dos itens saiu de `content.css` global para o CSS do SDC; consulta, título, entidades autorizadas e cache continuam no módulo. Story e gate de render isolado verificam a composição.

Em 2026-11-13 o formatter da categoria editorial passou a compor o SDC `editorial-category`, preservando como slot o link produzido pelo Entity Reference Formatter. As variantes de cor migraram de `content.css` para o asset local do SDC e `content.css` saiu do `global-styling`.

O estado visual de `htmx-indicator` é local: a UI Library o carrega com `ui-library-admin`, e as buscas de conexão com o CSS do SDC `studio-connections-page`. `base.css` não controla mais esses estados específicos.

Os metadados de publicação e os links de compartilhamento entregues pelo `aculta_magazine` usam `revista-publication.css`, declarado como asset do SDC. O template de nó não anexa uma biblioteca sem definição; o CSS acompanha o view mode full via componente.

Piloto de realocação (2026-11-12): as regras de `portal-ui-map.css` cujos seletores são exclusivos de `aculta_events` foram movidas para a biblioteca `aculta_events/events-portal-ui`, anexada pelo próprio módulo via `#attached`. O gate de classes mapeadas agora lê também `aculta_portal/modules/*/css`. Estado: captura visual DEFERRED.

Migração dos utilitários (2026-11-12): nenhum token de `PortalUiHooks::TOKENS` usa mais `aculta-u-*`. Cada token recebeu classe semântica própria ou passou a reutilizar a classe do componente (`studio__actions`, `aculta-search-preview__footer`, `aculta-lms-class-roster`). O token novo `events-organization-admin-table` cobre o `<small>` de slug do painel administrativo de organizações, antes marcado com `text-muted` direto no `#markup`. O mapa agora tem 470 classes, sem nomes Bootstrap e sem exceções; `validate-portal-ui-docs.php` foi corrigido para não abortar quando o tema V1 `aculta420` não existe no checkout. `utilities.css` saiu de `global-styling`. Homologação: matriz `page-matrix.py` 100/100 nos dez hosts, 320–1440 px, claro/escuro; axe 22/22 sem violações. A classe `.aculta-u-block-start` mantém o nome porque é configuração de UI Styles em `block.block.*`, não utilitário de token; agora mora em `layout.css`.


O antigo `portal-ui-map.css` foi removido em 2026-11-13. `.container-inline` usa apenas o CSS de `system/base`; o tema não sobrescreve mais o layout de formulários do Core/contrib. O marcador editorial `aculta-content-status--unpublished` está em `unpublished-preview.css`, anexado pelo hook `entity_view` somente quando a entidade está não publicada e UI Styles selecionou o marcador.

## 5. JavaScript

O JavaScript legado é predominantemente vanilla com `Drupal.behaviors` e `core/once`. ADR-054 autoriza jQuery 4 via `core/jquery` sob demanda em módulos e SDCs; HTMX permanece padrão para novas requisições conforme ADR-050.
HTMX por `core/drupal.htmx` é o padrão obrigatório das interações assíncronas;
Drupal AJAX (`core/drupal.ajax`, `#ajax`, `use-ajax`) é legado e não deve ser
adicionado ao tema. Migrações preservam links e formulários nativos como
fallback e documentam qualquer compatibilidade antiga ainda necessária.

| Arquivo | Papel |
| --- | --- |
| `components/multidomain-bar/multidomain-bar.js` | sincroniza a altura da barra com o espaço da página e com a barra administrativa; abre os popups de login e de busca via HTMX integrados ao Portal, em `<dialog>` nativo |
| `components/navigation-bar/navigation-bar.js` | drawer de navegação mobile: abre, fecha com Escape e devolve o foco |
| `components/data-table/data-table.js` | marca como região nomeada/focável somente a tabela com overflow; observa resize e atualizações Drupal e limpa observers no detach |
| `js/utility-dialogs.js` | diálogos utilitários com `data-aculta-utility-drawer`: abre por `showModal()`, contém Tab/Shift+Tab, fecha por botão, Escape ou clique no fundo e atualiza `aria-expanded` dos gatilhos |
| `js/messenger-drawers.js` | configura attrs HTMX nos links e formulários dos fragmentos de mensagens/avisos, trata loading/error, confirmação, chips de destinatários e rolagem de conversa; `hx-sync="this:replace"` cancela respostas antigas |
| `js/counters.js` | atualiza contadores após `htmx:afterSettle`, com zero oculto |
| `js/alerts-dismiss.js` | dispensa avisos e atualiza o contador |
| `js/studio-htmx.js` | em `htmx:afterSettle`, move o foco para `[data-aculta-focus]` ou `h1`, atualiza `document.title` por `data-aculta-title` e anuncia a troca em região `aria-live` quando o alvo ainda não tem uma; em swaps `outerHTML`, resolve o novo alvo pelo id antes de focar |

O corte de presenters é feito por consumidor. Em 2026-11-14, após remoções
anteriores de Revista, Wiki, documentos e catálogo Commerce, o template de FAQ
também foi removido: `PagePresentationHooks` compõe o SDC `accordion` com o
título e o render array do campo body para `aculta_faq` no view mode `full`.
O template Core mantém o wrapper e os metadados Drupal do node. As Views do
Fórum e da Revista e os formulários ainda têm presenters específicos porque
seus contratos de rows, contextual links, filtros e Form API não foram
substituídos integralmente. O inventário e a fila atual estão no `roadmap.md`.

O override genérico `node--teaser.html.twig` foi removido porque aplicava um
cartão cultural a bundles sem esse contrato e podia ocultar campos como o
arquivo de um `document`. Loja (catálogo/produto) permanece aguardando autorização
por tarefa; os módulos Layout Builder e UI Patterns Layouts estão habilitados, mas os displays só passam a usar Layout Builder mediante migração e testes explícitos conforme ADR-054.

A biblioteca `studio-htmx` depende de `core/drupal.htmx`, `core/drupal` e `core/once`. A biblioteca `utility-drawers` depende de `studio-htmx` e carrega os diálogos, gavetas, contadores e dispensa de avisos. Os diálogos nativos usam `data-aculta-utility-drawer` como contrato de comportamento; estilos específicos de cada SDC ficam isolados para que um modal não herde dimensões de gaveta. As gavetas declaram `data-hx-get`, alvo e `data-hx-sync="this:replace"`; os fragmentos mantêm `href` e POST nativos como fallback sem JavaScript. O login e a busca do shell usam os endpoints HTMX do Portal: o gatilho é um link normal, o JS acrescenta `data-hx-get`, alvo, `select` e `hx-sync` antes de abrir o `<dialog>`. A prévia da busca usa `data-hx-get` no campo, com debounce de 300 ms e `hx-sync="this:replace"` no lugar do `AbortController`. Busca e login não usam `core/drupal.dialog.ajax`; a V2 remove essa dependência do utilitário de busca (`PagePresentationHooks::removeSearchDialogDependency`). A matriz visual e o fallback sem JavaScript da busca e do login estão em `tests/browser/aculta-htmx-f1/`.

Com JavaScript desligado, os links dos gatilhos continuam válidos: o login e a busca abrem as páginas próprias do Portal. Exceção registrada em 2026-11-05: as gavetas de mensagens/avisos são abertas por `<button>` e exigem JavaScript; o Portal não fornece `all_href` para um link nativo de página completa, e o responsável decidiu manter essa exceção documentada nesta fase.

## 6. Shell

- `templates/layout/page.html.twig` é o shell único e compõe o SDC `layout-page`; as regiões válidas são declaradas em `aculta420_v2.info.yml`. A sugestão antiga `page__shell_v2` é mantida temporariamente no hook do tema porque o Runtime compartilhado ainda pode ter o callback em cache; removê-la exige uma janela segura para reconstruir caches.
- O shell único compõe as cinco regiões finais (`institution`, `header`, `content`, `sidebar_first`, `footer`) declaradas no `info.yml`. O template paralelo e a biblioteca CSS do shell foram removidos em 2026-11-13. A configuração de blocos em regiões antigas precisa ser conferida por ambiente antes de qualquer limpeza estrutural.
- `PagePresentationHooks::composeShellTitle()` compõe o título da rota no slot `title` de `layout-page` no shell único, preservando um único H1 no landmark principal.
- Os layouts de composição são SDCs com `group: Layouts`: `layout-page` (variantes stack/aside/full), `layout-section`, `layout-two-columns`, `layout-grid`, `layout-narrow` e `layout-full`. O `ui_patterns_layouts` deriva um plugin de layout para cada SDC com slots e usa o `group` como categoria; por isso os seis aparecem no seletor do Layout Builder sem registro manual. Os demais SDCs com slots também são derivados pelo módulo contrib, mas ficam nas categorias editoriais do próprio componente.
- O planejamento de habilitação do Layout Builder por display é somente leitura (`scripts/audit-aculta420-layout-builder.php`): classifica os 95 displays em compatível, adaptador e incompatível.
- Lote inicial ativado no desenvolvimento com backup e autorização do responsável: **22 dos 24 displays compatíveis** com `allow_custom: false`, somando nós `full` e `default`, oito displays de `block_content` e `taxonomy_term.editorial_author.default`. Cada ativação foi comparada antes/depois em sete páginas; a revalidação por multiconjunto de tokens eliminou falsos positivos de ordenação da Wiki. Excluídos apenas `node.document.full` e `block_content.basic.default`, que perdem conteúdo de forma reproduzível. Gate: `validate-aculta420-layout-builder-batch.php` (170 checks).
- `templates/layout/page.html.twig` monta a página e inclui a barra institucional (`multidomain-bar`) antes do `page.header`.
- Na rota `aculta_studio.account`, `PagePresentationHooks` fornece `aculta_studio_account_dashboard`; `page.html.twig` passa `layout-page--studio-dashboard` ao SDC `layout-page`. Esse modificador reserva o espaço dos SDCs `studio-menu` e `studio-calendar` no desktop. Os componentes mantêm o posicionamento próprio, sem seletores `:has()` sobre ancestrais nem regras corretivas na UI Patterns Library.
- A região `footer` é apresentada pelo SDC `site-footer`; quando o Portal fornece `social_links_page_footer`, usa a variação `social-links` (C4).
- Os adapters de Links, `aculta_section` e aviso de pagamento foram removidos do tema em 2026-11-13; seus render arrays/hooks de módulo continuam sendo owners. A composição visual por UI Suite e o output dos fallbacks ainda precisam de validação.
- A barra institucional é fixa. A altura medida dela vira `--aculta-institution-bar-height`, e um espaçador (`.aculta-institution-bar-placeholder`) mantém o conteúdo abaixo dela.
- O bloco `system_menu_block:main` é envolvido pelo SDC `navigation-bar` no mesmo peso e posição do cabeçalho. O render array original fica como slot, com access e cache preservados.
- O menu de conta (`account-menu`) fica dentro da barra. Visitante vê "Entrar"; autenticado vê o disclosure com avatar e as ações de conta (ver [components.md](components.md)).
- A barra institucional convive com a barra administrativa Gin e com o toolbar Core: a faixa fica acima das duas camadas, e cada uma começa após a altura medida da faixa.
- A UI Patterns Library é o catálogo único de SDCs e stories. O módulo customizado `aculta_ui_library` está em retirada completa, inclusive a rota de redirect. A configuração versionada já não o habilita; sua remoção efetiva no Runtime depende da janela estrutural descrita no roadmap.
- UI Patterns Library (`overview`, `provider` e `single`) é uma exceção administrativa: `ComponentLibraryRoutes::isComponentLibraryRoute()` identifica as rotas e `PagePresentationHooks` usa a variante `full`, sem container legado. A classe `aculta-component-library-page` estiliza cada `.ui_patterns_component` como bloco de largura ampla, sem impor grid às stories; a barra institucional mantém a marca e oculta a faixa de utilidades só nessas rotas. `UiPatternsStoryHooks` instancia stories PHP declaradas pelos componentes e passa props/slots resolvidas ao `component_story`, mantendo a barra e o menu do Studio ligados aos providers reais.
- A overview anexa `ui-library-admin`, que carrega `ui-library-admin.css`, `ui-library-tabs.js` e `ui-library-bulk-preview.js`, com Drupal behaviors, `core/once` e HTMX. A página single usa o template upstream da UI Patterns Library; o carregador extrai `.ui_patterns_component__stories` da resposta para prévias em lote, sem depender de wrapper Twig do tema. As abas selecionam o grupo via `data-group-id`/`data-group-name`; a busca filtra por componente e grupo. O lote busca prévias sob demanda com concorrência limitada. Os cartões formam duas colunas quando a largura útil alcança 60rem; abaixo disso, uma coluna.

## 7. Hooks e registro

- `PagePresentationHooks::preprocessPage()` publica `aculta_studio_account_dashboard` somente na rota `aculta_studio.account`; `page.html.twig` passa esse contexto como classe do SDC `layout-page` para reservar as colunas dos componentes de menu e calendário.
- `#[Hook]` em classes com injeção de dependência. Não há `\Drupal::` em `src/`.
- `PagePresentationHooks` (`preprocess_page`) mapeia o contrato do Portal para variáveis do tema, move o menu de conta para a barra, envolve o menu principal com `navigation-bar` e remove a dependência de diálogo AJAX do utilitário de busca. Anexa `linkweed-admin` somente à configuração de links e `composeShellTitle` move o título da rota para o conteúdo no shell único. Nas rotas `aculta_studio.*`, envolve o conteúdo já composto por SDCs no `studio-shell` e cada render array `studio-section-header` no SDC homônimo; os formulários sob `studio-main` recebem o SDC `studio-form`. A criação de curso LMS usa o mesmo SDC para o Form API dentro de sua região `studio-main`. Na rota `aculta_user.perfil_publico`, o render array é envolvido uma vez em `public-profile-page`; seu slot preserva conteúdo autorizado e cache. Na rota `aculta_portal.support_form`, o Form API é envolvido uma vez no SDC `support-form`; o SDC imprime o slot sem acrescentar DOM nem alterar o formulário. O override `aculta-portal-account-terms-modal.html.twig` compõe `account-terms-modal`; o wrapper mantém o ID, `hidden` e `data-dialog-title` consumidos pelo JavaScript do Portal. Na rota `aculta_lms.course_structure`, o mesmo preprocess move o render array `aculta_lms_course_builder_resources`, criado pelo módulo após a composição do controller, ao slot `resources` do SDC `lms-course-curriculum`; o builder preserva acesso, cache e peso. Render arrays, attachments, cache, títulos, ações autorizadas, campos e handlers permanecem no slot do componente. Nas rotas `view.aculta_forum_public_topics.*`, `aculta_page_title` fica vazio porque o SDC `forum-category-header` renderiza o H1 da listagem; a regra evita um segundo H1 oculto por CSS. O título da rota continua sendo renderizado pelo override `templates/page-title.html.twig` com o SDC `heading`.
- Na apresentação do mapa, `PagePresentationHooks` envolve o conteúdo de `aculta_events.map` no slot `content` de `map-canvas`; nas demais páginas, envolve os render arrays que o `MapRenderer` marca como `map-wrapper` ou `map-widget`. Os attachments funcionais e metadados de cache continuam no render array do módulo. O CSS visual do mapa é asset local do SDC.
- A View `eventos_publicos:block_1` usa UI Patterns Views com `layout-grid` e `view_rows`; o card é preparado pelo módulo e renderizado como SDC, preservando os metadados de acesso/cache. A grade não depende mais do template unformatted legado nem de `:has()`. O `card-cultural` apresenta título, metadados e estados de cancelamento apenas por suas classes locais; não exige tokens `data-portal-ui` de estilo. A folha `events` carrega apenas em rotas `aculta_events.*` e nodes do bundle `aculta_event`; os estilos de organização de eventos ficam em `event-organization`, anexada às rotas de widgets, perfis, listagem administrativa e modais. Os grupos de cartões, apoiadores e agenda saíram do mapa global.
- `aculta420_v2_theme_suggestions_page_alter` em `aculta420_v2.theme` ainda contém compatibilidade temporária com o callback em cache no Runtime compartilhado. Depois de uma reconstrução segura dos caches, o hook e a sugestão obsoleta devem ser removidos; a configuração já não seleciona variantes de shell.
- `PagePresentationHooks::preprocessPortalComponent()` mapeia os contratos `empty-state` e `context-tabs` do Portal para os SDCs `empty-state` e `tabs` do tema, preservando props e slots e mantendo o fallback para nomes fora do mapa ou contrato incompleto. `ComponentFallbackHooks` do Portal executa antes do preprocess do tema; para esses dois nomes o tema substitui deliberadamente o render array genérico. O mapeamento focal passou em PHPUnit e renderização Drupal. Firefox ESR comparou o `empty-state` real em `/group/131`, antes/depois, claro/escuro e 320/1440 px; após a mudança o DOM contém `data-component-id="aculta420_v2:empty-state"`, a mensagem correta e nenhuma rolagem horizontal; o foco visível mede 3 px. A suíte visual ampla do Studio segue parcial (34/48 checks OK, 22 skipped, 14 falhas, incluindo composição e o check legado de modo escuro); detalhes em `docs/layouts-por-purpose.md`.
- O mesmo hook define `aculta_layout_variant` por bundle do nó ou nome de rota: wiki/fórum `aside`, revista `full`, home e catálogo/mapa de eventos `full`; demais rotas `stack`. Não consulta hostname nem purpose, e entrega ao SDC somente a variante. Catálogo e produto de loja aguardam autorização explícita de tarefa.
- Dashboard do Studio na raiz `account` e rotas de Minha Conta (`/conta/*`), Perfil, WE Sativa, Ganjalab, aula LMS e Culturas escolhem `aside`; a raiz usa `aculta_studio.dashboard` e `/conta` apresenta Dados cadastrais privados. O Portal bloqueia acesso direto ao path interno do dashboard e aplica a guarda de autenticação.
- `SurfaceSettingsHooks` (`preprocess_html`) aplica o modo de cor e as classes de superfície. Também coloca nonce CSP por resposta no `<style>` inline produzido pelo UI Skins, sem incluir `unsafe-inline` na política global.
- Evento: o checkout atual não contém `aculta-event-page.html.twig` nem consumidor PHP/Twig/config do SDC `event-page-layout`; confirmar a apresentação real do detalhe antes de reutilizar ou remover esse SDC. FAQ: `PagePresentationHooks::composeFaqFull()` compõe o SDC `accordion` no view mode `full`; `node.aculta_faq.default` continua usando Layout Builder, `layout-section` e o formatter `text_default` para o outro display.
- `PortalUiHooks` (`preprocess`, genérico): converte `data-portal-ui` em classes do V2 em todo `*attributes` do theme hook. Em 2026-11-08 os tokens de botão já migrados para o SDC `aculta420_v2:button` saíram do mapa (`aculta-btn-*`, `aculta-button-*`, `surface-list-edit-action`, `lms-answer-review-button`, `wiki-proposal-contribute`, `event-organization-admin-action` e variantes `event-location-*` sem uso). Continuam mapeados os tokens usados por envios de formulário e por controles com comportamento próprio que ainda passam pelo mapa (`social-links-settings-button`, `friendship-action-button`, `notice-read-state-action`, `conversation-action`, `event-*button*`, `event-date-cancel`, `event-date-delete`) e os tokens exigidos pelo `ui_styles` (`button-primary`, `button-secondary`, `button-outline`, `button-ghost`). Tokens fora desse mapa ainda não têm classe no V2 (ver o gate `validate-module-theme-boundary`). Sem número de versão até a validação visual no Runtime; status: pendente, captura visual DEFERRED.
- `MailEnvelopeHooks` (`preprocess_aculta_mailer_wrap`) monta o envelope HTML dos e-mails do `aculta_mailer`: cores claras e escuras lidas de `src/styles/tokens.css` a cada montagem e logo do asset do próprio V2. Não lê configuração da V1.
- `src/Hook/*` é a única camada PHP do tema. Lógica de negócio não pertence aqui.

## 8. Dependências e compatibilidade

- Drupal `^11.5 || ^12`. O desenvolvimento roda no 11.5 do servidor de desenvolvimento. Compatibilidade com 12 é meta, sem validação ainda (ADR-045).
- Ícones Phosphor (MIT, Core 2.1.1) estão vendorizados em `assets/icons/phosphor/` e são injetados inline pelo componente `icon`.
- O Config Sync habilita UI Styles e as integrações de Block, Layout Builder, Page, UI Patterns, Views e Entity Status. Isso confirma a configuração versionada, não a descoberta nem o funcionamento no Runtime. A UI Patterns Library cataloga SDCs/stories. CTools permanece no Portal conforme ADR-044.


## Fórum Growroom420 — placeholders pendentes (seção 5 do plano)

- Visualizações por tópico (coluna da lista).
- Prévia do último post, miniatura do tópico e moderadores.
- Painéis de destaque e de tópicos relacionados.
- Estado "Seguir" e "Salvar" por usuário (botões desabilitados com "Em breve").
- Contrato com o Portal: papel do autor, variante de faixa e contagem de posts.
