# Layouts por purpose e consumo dos componentes

> **Inventário histórico em revisão (2026-11-13):** o corte de overrides tornou
> várias linhas abaixo obsoletas, mas consumidores não migraram todos para um
> único fallback: alguns usam displays/Views/UI Patterns e outros mantêm
> presenters do módulo. Os nomes da tabela são hipóteses antigas, não evidência
> atual. Refaça cada linha por meio de código e Config Sync segundo ADR-056. Em
> 2026-11-14 o checkout passou a compor `accordion` diretamente no preprocess
> do view mode full de `aculta_faq` e `EventPageController::page()` passou a compor
> `event-page-layout`; a renderização destas composições no Runtime segue pendente.

Inventário de 2026-11-05: quais templates de página cada purpose usa e quais
componentes SDC eles consomem. Serve de base para a adoção dos componentes
visuais, purpose a purpose, sem repetir a regressão de `91549bc7`.

Regra: um componente só entra num template se o resultado for igual ou melhor
que o markup atual, com CSS adaptado e captura antes/depois nos dois modos.

## Estado reconciliado com o checkout e Config Sync (2026-11-14)

A tabela abaixo corrige o inventário de 2026-11-05. Ela descreve somente
composições encontradas nos arquivos atuais; não afirma que a configuração foi
importada ou que a saída foi homologada no Runtime. A fila de remoções dos
templates está em [roadmap.md](roadmap.md), seção P3, com um registro por arquivo.

| `site_key` | Composição encontrada no código/Sync | Situação e próximo passo |
| --- | --- | --- |
| `main` | Shell único em `templates/layout/page.html.twig`, SDC `layout-page`, cinco regiões no `aculta420_v2.info.yml`; displays de conteúdo e placements de bloco usam SDC/UI Patterns em Config Sync. | Não criar shell por domínio. Tratar `layout/html.html.twig` e `layout/page.html.twig` na fila P3 como infraestrutura Drupal; retirar decisões de domínio do page hook antes de avaliar o segundo. |
| `account` | Portal monta conta por render arrays; o tema compõe `account-shell` e o editor de foto em SDCs. Studio mantém rotas privadas e presenters próprios em `aculta_studio`. | Core injeta `#slots` nos blocks de `author-card`/`profile-details`; o validator do tema passou e cobre o avatar de `author-card`. Preservar acesso/cache e homologar conteúdo autenticado no Runtime. |
| `support` | Formulário `/apoio` em Form API dentro de `support-form`; checkout/retorno de doação compõe `donation-presentation` pelo módulo `aculta_donation`. | A apresentação está em SDC; verificar consumidores e remover somente CSS/adapter que a matriz P2 provar sem uso. |
| `magazine` | `node.revista.full` no Sync usa ComponentLayout `revista-publication`; `PagePresentationHooks::composeRevistaFull()` cobre compatibilidade. Teasers usam `revista-teaser`; Views usam `revista-listing`/UI Patterns. O template específico do view da Revista ainda existe. | Core injeta slots em blocks tanto nos render arrays quanto nas stories da Library. Aferir style ativo no Runtime e avaliar corte do template específico preservando título, filtros, pager, attachments, feed e links contextuais. |
| `wiki` | `node.wiki_entry.full` no Sync usa ComponentLayout `wiki-entry`; teasers passam por `composeWikiTeaser()`; Views de entradas e categorias usam UI Patterns Views/layout-grid no Sync. | Homologar acesso, cache e render no Runtime; não há template de página Wiki específico no tema atualmente. |
| `shop` | `product-card` é composto por `preprocess_commerce_product` no view mode `catalog`; catálogo e galerias usam `layout-grid` em Views no Sync. | Corrigir a afirmação histórica de que não existe consumidor de produto. Confirmar os displays ativos no Runtime; preço e dados de produto continuam no owner Commerce. |
| `courses` | Views `courses` e `courses_catalog` usam `layout-grid` no Sync; controladores/formulários LMS compõem SDCs próprios, incluindo `lms-lesson-page`. | Não há template de página próprio do LMS no tema; conferir diferença de configuração ativa antes de qualquer corte e homologar rotas autenticadas. |
| `forum` | Tópicos usam `composeForumTopic()`; categorias, tópicos públicos e busca têm UI Patterns Views em Config Sync. Permanecem os adapters de Views e os dois adapters de comentários com consumidores Core. | `forum-comment` usa overrides `{% embed %}` e slots diretos da Library seguem o mesmo mecanismo Core. Não migrar por incompatibilidade inexistente; comparar Config Sync com Runtime e preservar agrupamento, threading, acesso, cache, ações e pager ao avaliar cortes de templates. |
| `eventos` | `eventos_publicos` usa Views/UI Patterns; `EventPageController::page()` compõe `event-page-layout`. | A composição de detalhe é código atual; a saída em Runtime, mapas e estados ainda precisam de homologação. |
| `links` | `LinksLandingController` e `LinkPagePublicController` são owners das páginas; o tema compõe `social-links-page` por preprocess. | Templates em `aculta_linkweed` são templates do módulo, não overrides do tema. Considerar sua remoção apenas como mudança da fronteira do owner e com consumidor substituto comprovado. |

**Escopo das evidências:** `config/sync`, PHP e templates deste checkout. O
Runtime tinha nove configurações divergentes na última leitura datada no
roadmap; nenhum status acima deve ser lido como confirmação de igualdade ativa.

## 0. Decisões do responsável (2026-11-05)

> **Arquivo histórico:** as decisões, ordem de adoção, escopo e pendências das
> seções seguintes são o plano de 2026-11-05. Não autorizam mudanças nem
> descrevem o estado atual; para este, use a matriz reconciliada de
> 2026-11-14 acima e a fila vigente de `roadmap.md`. Em particular, os itens
> antigos sobre Loja/`price`, Revista e Fórum não substituem a auditoria de
> consumidores e Config Sync.

- **Primeiro passo:** adotar `card-cultural` em Revista e Eventos, com CSS adaptado e captura antes/depois.
- **Precedência da faixa de acento:** o administrador (UI Styles) prevalece sobre o `accent` do Portal (Opção A, registrada na F4).
- **Studio e conta:** módulo `aculta_studio`, rotas `/meu-perfil`.
- **Loja:** entra nesta rodada só com componentes sem preço (cartão e estado vazio). `price` fica fora.

## 0.1 Decisões de execução e mapeamento (2026-11-05)

- **Revista, origem do accent:** escolha administrativa única no UI Styles, global à Revista, com precedência sobre o accent do Portal (Opção A). `aculta420_v2.settings` guarda `revista_accent`; o preprocess aplica `revista-teaser--accent-{green|yellow|red}` ao teaser. O campo legado `field_revista_accent` não participa da escolha nem do render e permanece instalado para evitar remoção destrutiva. Submit visual, classe em listagem pública após submit e verificação no banco continuam pendentes nesta rodada.
- **Eventos:** a raiz do purpose Eventos serve listagem pública (HTTP 200; título “Eventos da ACULTA”). A View pública nova consome IDs e dados preparados por `EventCatalogueController`/`EventCatalogue`: mantém Eventos publicados e cancelados com ocorrência futura, expansão de recorrências, revalidação `view` por entidade, ordem e filtros por tipo/período. A View usa tabela base `aculta_event`, handlers próprios de cartão/ordenação e o template V2 `card-cultural`; ela não substitui a seleção de ocorrências do catálogo. Validação visual pós-View em Firefox ESR: dois cards, claro/escuro, 320/1440 px, foco visível e zero overflow. Capturas em `$SUITE_OUT/layouts-purpose-events-cards-20261106-r2/`. A listagem não tinha baseline antes de existir; portanto a implementação está validada funcionalmente e visualmente, mas não há comparação antes/depois da listagem.
- **Studio (`aculta_studio`, registro de 2026-11-06):** a comparação focal anotou 97/97 verificações (22 ignoradas, zero falhas), títulos associados, foco visível e sem overflow nos dois modos a 320/1440 px. A auditoria atual confirmou que Drupal Core injeta `#slots` nos blocks Twig de `profile-details`; a revisão não identifica defeito de saída por esse mecanismo. As capturas em `$SUITE_OUT/layouts-purpose-studio-profile-details-paired-20261106/{before,after}/` são evidência visual histórica, não homologação do Runtime atual. A comparação da identidade e das rotas permanece evidência histórica.
- **Loja (`shop`):** o V2 não tem template de produto nem de listagem. Só o V1 tem `commerce-product--produto-loja.html.twig`. Não há cartão de loja no V2 para adotar `card-cultural` ou `empty-state`. Antes de adotar, é preciso criar o template de produto do V2, o que é escopo novo, não adoção.

## 0.2 Estado da execução com Runtime (2026-11-06)

- **PHPUnit:** bootstrap cria `web/sites/simpletest/browser_output` relativamente à raiz do projeto antes da suíte. Tema V2: 12 testes/56 assertions PASS; `phpunit.xml` não declara suítes globais, então invocar PHPUnit sem diretório/arquivo selecionado exibe uso e não executa uma suíte geral.
- **Configuração Runtime:** `aculta420_v2.settings` corresponde ao Sync; CAPTCHA global permanece desabilitado (0), displays e `core.extension` foram reconciliados. `config:status` lista nove entradas `default_admin` apenas no DB (oito blocos e `default_admin.settings`), duas definições do campo legado apenas no Sync e `turnstile.settings` como diferente. As opções não sensíveis do widget coincidem; somente campos de chave de teste divergem. Não há diferença exportável de tema/módulo do projeto; `drush cex` foi omitido para não incluir configurações locais nem dados sensíveis. Os itens não exportados e motivos estão no handoff.
- **F2 — shell:** Firefox ESR passou 120/120 verificações em dez purposes, claro/escuro e 320/1440 px; shell sem alteração. O probe agora compõe o fundo efetivo quando o cabeçalho é transparente. A navegação tem contraste AA no fundo real (12,40:1 em superfície clara; 7,84:1 no link ativo amarelo), portanto não houve alteração de tokens. Capturas e manifest em `tests/browser/capturas/shell/2026-11-06/`; aceite visual do responsável continua pendente.
- **F3 — evidência histórica:** Firefox ESR verificou o sistema de paletas anterior em 2026-11-06. O mecanismo foi removido no corte para UI Skins; a captura antiga não conta como aceite visual atual. Revalidar depois de ativar UI Skins.
- **Botões:** foundations PASS (73 pares de texto, 6 faixas e tokens de botão); no runtime, primário/contorno consumiram `--button-*`, com `filter: none`. Bootstrap contrib permanece instalado nesta fase de validação do tema V1.
- **Editor de conteúdo:** `tests/browser/transversal/aculta-editor/access.py` passou integralmente no Firefox ESR. O ULI em `conta` cria cookie de domínio compartilhado entre subdomínios; `/node/add/revista` é rota administrativa do host principal, e o host da Revista retorna 404 por política de domínio. UID 15 tem `create revista content`; no host principal o formulário responde 200, exibe CKEditor com `revista_editorial` e sem `full_html`. Suite completa: 8 grupos e 12 viewports CSS (1280/390/320), sem overflow; inclui as verificações administrativas seguintes. Capturas `before/` e `after/` em `tests/browser/capturas/aculta-editor/2026-11-06/`. Nenhuma configuração do editor foi alterada.

- **Revista — accent global validado no Runtime.** Firefox ESR autenticou UID 1, confirmou as três opções UI Styles e submeteu Verde pelo formulário. Os quatro teasers da listagem pública receberam a classe no wrapper do node e no SDC, em claro/escuro a 320/1440 px, sem overflow; a escolha global foi restaurada vazia ao final. Consulta confirmou cinco nós Revista, zero accents próprios e node 602 vazio. Capturas em `tests/browser/capturas/revista-accent/2026-11-06/`.
- **Eventos — View pública validada, sem baseline de listagem anterior.** A raiz do purpose responde HTTP 200 e renderiza dois `card-cultural` pela View pública. Kernel Events: 155 testes/3.616 assertions PASS (33 depreciações Core/contrib); capturas Firefox ESR (listagem e detalhe) em `$SUITE_OUT/layouts-purpose-events-cards-20261106-r2/`: claro/escuro, 320/1440 px, foco visível e zero overflow. A página de detalhe foi capturada como navegação/layout, sem alegar uso de SDC nela. A baseline de 2026-11-05 foi preservada; como a listagem foi criada nesta entrega, não existe captura válida “antes” dela. CAPTCHA, displays e extensão foram reconciliados nesta rodada; `config:status` ainda lista blocos locais do tema administrativo, Turnstile sensível e o campo legado apenas no Sync.
- **Studio — adoção SDC parcial (registro de 2026-11-06):** o Firefox confirmou que `/meu-perfil` renderizava e a comparação pareada marcou 97/97 checks (22 ignorados, zero falhas), foco e zero overflow. A auditoria atual confirma a semântica Core de slots em blocks; as capturas em `$SUITE_OUT/layouts-purpose-studio-profile-details-paired-20261106/{before,after}/` continuam históricas e não substituem nova homologação Runtime. Identidade, abas e rotas conservam as evidências registradas.
- **Loja — pendente por autorização.** Não foi encontrada autorização registrada para criar templates de produto/listagem do V2. O componente `price` permanece fora do escopo.

## 0.3 Decisão da Revista (2026-11-06)

O accent da Revista é uma escolha única para a Revista inteira, feita pelo UI Styles nas configurações do ACULTA420 V2. `aculta420_v2.settings` é a origem; o hook `preprocess_node` converte a opção em classe no wrapper do node teaser e no SDC, com precedência sobre o accent do Portal, e adiciona a dependência de cache da configuração. O campo legado permanece sem widget, allowlist ou leitura no builder; suas configurações ficam preservadas no Sync até uma remoção planejada, sem migração destrutiva. Submit e listagem pública foram validados no Firefox ESR em claro/escuro, 320/1440 px, sem overflow. A configuração global foi restaurada vazia; consulta confirmou cinco nós Revista, nenhum com valor próprio, e node 602 vazio.

## 1. Consumo atual por componente

Componentes com zero consumidores no repositório (medido por `grep` em templates
do tema e módulos custom):

| Componente | Uso esperado | Observação |
| --- | --- | --- |
| `data-table` | tabelas de Views | Removido com `views-view-table` (revertido) |
| `progress` | progresso de curso e formulário | Removido com `progress-bar` (revertido) |
| `form-field` | campos de formulário | Removido com `form-element` (revertido) |
| `navigation-bar` | navegação do domínio | Sem consumidor |
| `account-menu` | menu de conta | Sem consumidor na página; o shell usa outro markup |
| `hero-banner` | topo de página editorial | Sem consumidor |
| `filter-chips` | filtros de listagem | Sem consumidor |
| `lms-tour` | tour de aula | Sem consumidor (consumidor citado no relatório não está no repositório) |
| `audio-player` | áudio | Sem consumidor |

Componentes com consumo: `accordion`, `alerts-trigger`, `author-card`, `avatar`,
`badge`, `breadcrumb`, `button`, `card-cultural`, `cart-offcanvas`, `dialog`,
`empty-state`, `event-page-layout`, `forum-category`,
`forum-topic`, `icon`, `layout-page`, `lms-lesson-page`, `map-popup`,
`messenger-trigger`, `multidomain-bar`, `pagination`, `purpose-showcase`,
`revista-listing`, `revista-publication`, `revista-teaser`, `site-footer`,
`social-links-page`, `status-messages`, `tabs`, `wiki-entry`.

## 2. Páginas por purpose

| Purpose | Templates localizados | Componentes em uso | Situação |
| --- | --- | --- | --- |
| `main` | `templates/layout/page.html.twig` (`layout-page`), `site-footer`, `multidomain-bar` | `layout-page`, `site-footer`, `multidomain-bar` | Shell usa componentes; página interna de conteúdo sem `hero-banner` |
| `account` | Não localizado no repositório como template de página; Studio usa `context-tabs` e os componentes de perfil | `tabs`, `author-card` e `profile-details` consumidos para abas, identidade, bio e redes | `form-field` e `progress` sem consumer de Studio |
| `support` (Apoio) | Blocos `aculta_donation_*`; sem presenter Twig do tema | `donation-presentation`, `button` | Os render arrays dos blocos emitem SDCs diretamente; cálculo e configuração continuam no módulo |
| `magazine` (Revista) | `content/node--revista--full`, `views/views-view--revista-sessoes--page-revista` | `revista-publication`, `revista-teaser`, `revista-listing`, `author-card` | O presenter Twig do teaser foi removido; `PagePresentationHooks` monta `revista-teaser` com fields renderizados e dependências de cache. O full usa ComponentLayout `revista-publication`, field blocks e encaminha os extras cacheáveis `post_meta`/`article_authors` aos slots do SDC; o adapter preserva o layout Drupal anterior durante a transição. Os seis displays da View usam UI Patterns Views style em Sync. Imports e homologação Runtime seguem pendentes. |
| `wiki` | Full via Layout Builder + UI Patterns em `core.entity_view_display.node.wiki_entry.full`; teaser via render array SDC no preprocess do tema | `wiki-entry`, `breadcrumb` | Full usa ComponentLayout; teaser compõe o mesmo SDC com fields formatados e título/link cacheáveis. Runtime e acesso/renderização ainda aguardam homologação |
| `documents` | Teaser de node `document` via render array SDC no preprocess do tema | `document-card`, `layout-grid` | `field_category`, `body` e `field_document` continuam render arrays da Field API nos slots. Runtime e acesso/renderização ainda aguardam homologação |
| `shop` (Loja) | Não localizado no repositório do tema | Nenhum | Aguarda autorização da Loja (ver `price`) |
| `courses` (Cursos/LMS) | `lms-lesson-page` (SDC); `aculta_lms` sem templates próprios localizados | `lms-lesson-page`, `progress` (sem consumo) | Falta `progress` e `tabs` |
| `forum` | Tópico full e formulário de criação usam presenters do tema; listagens públicas, categorias e busca têm overrides Views | `forum-topic`, `forum-opening-post`, `forum-topic-row`, `forum-list-page`, `forum-category-page`, `forum-search-page`, `pagination` | Remoções foram revertidas onde não havia substituto equivalente; Views ainda usam style/row default e exigem migração para UI Patterns Views |
| `eventos` | View `eventos_publicos` na raiz; controller de detalhe compõe `event-page-layout` por render array | `card-cultural`, `event-page-layout` | Listagem UI Patterns configurada; hook/template próprio do detalhe removido. Renderização, acesso, cache, mapa e estados do detalhe aguardam homologação Runtime |
| `links` (LinkWeed) | O módulo emite diretamente o SDC `social-links-page`; não há presenter de página do tema | `social-links-page` | Migração de template concluída; verificar consumer do `hero-banner` separadamente |

## 3. Ordem sugerida de adoção

1. **`card-cultural` em Revista.** Eventos já usa o componente na View pública;
   Revista aguarda seleção por entidade suportada pelo UI Styles (Opção A).
2. **`empty-state` em listagens de Fórum e Revista.** Substitui o estado vazio
   sem alterar a listagem.
3. **`progress` no Studio e no curso.** `tabs` já foi adotado no Studio;
   progresso exige CSS adaptado porque o markup do core permanece em uso.
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
