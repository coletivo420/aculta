# Layouts por purpose e consumo dos componentes

Inventário de 2026-11-05: quais templates de página cada purpose usa e quais
componentes SDC eles consomem. Serve de base para a adoção dos componentes
visuais, purpose a purpose, sem repetir a regressão de `91549bc7`.

Regra: um componente só entra num template se o resultado for igual ou melhor
que o markup atual, com CSS adaptado e captura antes/depois nos dois modos.

## 0. Decisões do responsável (2026-11-05)

- **Primeiro passo:** adotar `card-cultural` em Revista e Eventos, com CSS adaptado e captura antes/depois.
- **Precedência da faixa de acento:** o administrador (UI Styles) prevalece sobre o `accent` do Portal (Opção A, registrada na F4).
- **Studio e conta:** módulo `aculta_studio`, rotas `/meu-perfil`.
- **Loja:** entra nesta rodada só com componentes sem preço (cartão e estado vazio). `price` fica fora.

## 0.1 Decisões de execução e mapeamento (2026-11-05)

- **Revista, origem do accent:** administrador pelo UI Styles (Opção A). Pendente: o `card_accent` atual em `aculta420_v2.ui_styles.yml` lista só `card-cultural--accent-*`. Para a Revista, é preciso estender a opção e fazer o `node--revista--teaser` ler a escolha do administrador. Não implementado: exige validar o plugin do UI Styles num Runtime.
- **Eventos:** a raiz do purpose Eventos serve listagem pública (HTTP 200; título “Eventos da ACULTA”). A View pública nova consome IDs e dados preparados por `EventCatalogueController`/`EventCatalogue`: mantém Eventos publicados e cancelados com ocorrência futura, expansão de recorrências, revalidação `view` por entidade, ordem e filtros por tipo/período. A View usa tabela base `aculta_event`, handlers próprios de cartão/ordenação e o template V2 `card-cultural`; ela não substitui a seleção de ocorrências do catálogo. Validação visual pós-View em Firefox ESR: dois cards, claro/escuro, 320/1440 px, foco visível e zero overflow. Capturas em `$SUITE_OUT/layouts-purpose-events-cards-20261106-r2/`. A listagem não tinha baseline antes de existir; portanto a implementação está validada funcionalmente e visualmente, mas não há comparação antes/depois da listagem.
- **Studio (`aculta_studio`):** a rota `/conta` desativa o route normalizer por atributo default; o fallback preserva `data-portal-ui` e o tema mapeia esses tokens para classes. PHPUnit focal passou (8 testes, 296 assertions), gate de renderização SDC passou, e Firefox ESR no Runtime atual passou 65/65 checks, 22 ignorados, zero falhas. `/conta` e `/meu-perfil` foram capturados autenticados em claro/escuro, 320/1440 px, sem overflow e com foco visível; alvos de ações do perfil medem 44 px. Capturas pareadas em `$SUITE_OUT/layouts-purpose-studio-routes-20261106/studio-account-routes-{before,after}/`; suíte em `$SUITE_OUT/layouts-purpose-studio-current-20261106-r2`. Os mapeamentos existentes `context-tabs`→`tabs` e `empty-state`→`empty-state` permanecem; a captura real antes/depois do estado vazio está em `$SUITE_OUT/layouts-purpose-studio-empty-state-esr-20261105/{before,after}/`. `context-tabs` ainda precisa de comparação visual focal. `profile-*` não foi mapeado para SDC e segue pendente.
- **Loja (`shop`):** o V2 não tem template de produto nem de listagem. Só o V1 tem `commerce-product--produto-loja.html.twig`. Não há cartão de loja no V2 para adotar `card-cultural` ou `empty-state`. Antes de adotar, é preciso criar o template de produto do V2, o que é escopo novo, não adoção.

## 0.2 Estado da execução com Runtime (2026-11-05)

- **Revista — pendente.** Firefox ESR visível confirmou a listagem pública (`/revista`) em claro/escuro, 320/1440px, sem overflow e com foco visível. Baselines: `$SUITE_OUT/layouts-purpose-baseline-20261105/revista-baseline-{light,dark}-{320,1440}.png`. UI Styles instalado no Runtime não fornece seleção por linha/nó: o módulo `ui_styles_views` estiliza display, exposição e pager; `ui_styles_entity_status` nem está instalado e, pelo próprio código, só aplica classes a entidades não publicadas. Logo Opção A não pode ser implementada lendo a seleção do administrador por nó pelo mecanismo existente. Nenhuma classe ou precedência foi simulada; requer uma decisão/mecanismo suportado.
- **Eventos — View pública validada, sem baseline de listagem anterior.** A raiz do purpose responde HTTP 200 e renderiza dois `card-cultural` pela View pública. Kernel Events: 155 testes/3.616 assertions PASS (33 depreciações Core/contrib); capturas Firefox ESR (listagem e detalhe) em `$SUITE_OUT/layouts-purpose-events-cards-20261106-r2/`: claro/escuro, 320/1440 px, foco visível e zero overflow. A página de detalhe foi capturada como navegação/layout, sem alegar uso de SDC nela. A baseline de 2026-11-05 foi preservada; como a listagem foi criada nesta entrega, não existe captura válida “antes” dela. `config:status` não mostra diferença para a View exportada; divergências anteriores de blocos administrativos, CAPTCHA/Turnstile e `core.extension` permanecem.
- **Studio — rota e estilos de fallback validados; adoção SDC parcial.** `drush router:debug` não existe no Drush 13.8.0; o matcher direto encontrou `/conta` e `/meu-perfil`. `_disable_route_normalizer: TRUE` está nos defaults de `/conta`, impedindo que o front path seja normalizado para `/`. Após `drush cr`, navegação autenticada no Runtime carregou `/conta` na seção de dados e `/meu-perfil` na página de perfil. O fallback preserva `data-portal-ui`, mapeado para classes pelo tema. PHPUnit focal: 8 testes/296 assertions; gate SDC: 40 componentes renderizados; Firefox ESR: 65/65 verificações aplicáveis, 22 ignoradas, zero falhas. Capturas pareadas em claro/escuro, 320/1440 px, foco e medições sem overflow: `$SUITE_OUT/layouts-purpose-studio-routes-20261106/studio-account-routes-{before,after}/`; relatório da suíte: `$SUITE_OUT/layouts-purpose-studio-current-20261106-r2`. O mapeamento `empty-state` já tem captura focal de consumer real antes/depois em `$SUITE_OUT/layouts-purpose-studio-empty-state-esr-20261105/{before,after}/`; `context-tabs` ainda requer comparação visual focal. Famílias `profile-*` não foram mapeadas.
- **Loja — pendente por autorização.** Não foi encontrada autorização registrada para criar templates de produto/listagem do V2. O componente `price` permanece fora do escopo.

## 1. Consumo atual por componente

Componentes com zero consumidores no repositório (medido por `grep` em templates
do tema e módulos custom):

| Componente | Uso esperado | Observação |
| --- | --- | --- |
| `card-cultural` | cartão com faixa de acento | Consumidor necessário para a F4 (UI Styles de faixa de acento) |
| `tabs` | abas de Studio e conta | `context-tabs` do Portal mapeado para `aculta420_v2:tabs` por preprocess |
| `data-table` | tabelas de Views | Removido com `views-view-table` (revertido) |
| `progress` | progresso de curso e formulário | Removido com `progress-bar` (revertido) |
| `empty-state` | listagens vazias | `empty-state` do Portal mapeado por preprocess; captura de consumer real pendente |
| `form-field` | campos de formulário | Removido com `form-element` (revertido) |
| `navigation-bar` | navegação do domínio | Sem consumidor |
| `account-menu` | menu de conta | Sem consumidor na página; o shell usa outro markup |
| `hero-banner` | topo de página editorial | Sem consumidor |
| `filter-chips` | filtros de listagem | Sem consumidor |
| `lms-tour` | tour de aula | Sem consumidor (consumidor citado no relatório não está no repositório) |
| `audio-player` | áudio | Sem consumidor |

Componentes com consumo: `accordion`, `alerts-trigger`, `author-card`, `avatar`,
`badge`, `breadcrumb`, `button`, `cart-offcanvas`, `dialog`, `discussion-comment`,
`event-page-layout`, `forum-category`, `forum-topic`, `icon`, `layout-page`,
`lms-lesson-page`, `map-popup`, `messenger-trigger`, `multidomain-bar`,
`pagination`, `purpose-showcase`, `revista-listing`, `revista-publication`,
`revista-teaser`, `site-footer`, `social-links-page`, `status-messages`,
`wiki-entry`.

## 2. Páginas por purpose

| Purpose | Templates localizados | Componentes em uso | Situação |
| --- | --- | --- | --- |
| `main` | `templates/layout/page.html.twig` (`layout-page`), `site-footer`, `multidomain-bar` | `layout-page`, `site-footer`, `multidomain-bar` | Shell usa componentes; página interna de conteúdo sem `hero-banner` |
| `account` | Não localizado no repositório como template de página; Studio/perfil usa `author-card` e `avatar` | `author-card`, `avatar`, `account-menu` (sem consumo) | Falta consumo de `tabs`, `form-field` e `progress` no Studio |
| `support` (Apoio) | `aculta_donation`: `aculta-donation-donate`, `-summary`, `-quick`, `-back`, `-summary-container` | Nenhum SDC | Templates próprios do módulo, sem componente |
| `magazine` (Revista) | `content/node--revista--full`, `content/node--revista--teaser`, `views/views-view--revista-sessoes--page-revista` | `revista-publication`, `revista-teaser`, `revista-listing`, `author-card` | Principal candidato a `card-cultural` com faixa de acento |
| `wiki` | `content/node--wiki-entry--full`, `content/node--wiki-entry--teaser` | `wiki-entry`, `breadcrumb` | Aguarda confirmação da F2 para faixa de acento da Wiki |
| `shop` (Loja) | Não localizado no repositório do tema | Nenhum | Aguarda autorização da Loja (ver `price`) |
| `courses` (Cursos/LMS) | `lms-lesson-page` (SDC); `aculta_lms` sem templates próprios localizados | `lms-lesson-page`, `progress` (sem consumo) | Falta `progress` e `tabs` |
| `forum` | `content/node--aculta-forum-topic`, `views/views-view-fields--aculta-forum-*`, `views-view-unformatted--aculta-forum-categories--page-categories` (módulo `aculta_forum`) | `forum-topic`, `forum-category`, `pagination` | Falta `empty-state` e `data-table` |
| `eventos` | `aculta-event-page.html.twig` (tema e módulo `aculta_events`) | `event-page-layout` | Falta `card-cultural` e `empty-state` |
| `links` (LinkWeed) | `aculta-links-page.html.twig` (tema e módulo), `aculta-links-landing.html.twig`, `social-links-page` | `social-links-page` | Candidato a `hero-banner` na landing |

## 3. Ordem sugerida de adoção

1. **`card-cultural` em Revista e Eventos.** Destrava a F4 (faixa de acento).
   Exige decisão da regra de precedência, que já está registrada na F4 (Opção A).
2. **`empty-state` em listagens de Fórum e Revista.** Substitui o estado vazio
   sem alterar a listagem.
3. **`progress` e `tabs` no Studio e no curso.** Exige CSS adaptado, porque o
   markup do core de abas e progresso é usado pelo CSS atual.
4. **`hero-banner` na landing de Links e na home de Revista.** Depende de
   decisão de conteúdo.
5. **`data-table` e `form-field`.** Por último, porque o core de formulário e de
   Views é o mais usado pelo tema e a regressão foi nesse ponto.

## 4. O que fica de fora

- Loja (`shop`) e `price`: aguardam autorização.
- Faixa de acento da Wiki: aguarda confirmação da F2.
- Pagamento (`aculta_pagamentos_mercadopago`): não é tema.
- Módulos de Donation: os templates próprios do módulo ficam como estão até
  decisão do responsável.

## 5. Pendências

- Capturar, por purpose, antes e depois de cada adoção, nos modos claro e escuro,
  em 320 px e 1440 px.
- Confirmar, para Studio e Loja, os templates reais (não localizados aqui).
