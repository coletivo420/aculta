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

## 0.1 Bloqueios encontrados na execução (2026-11-05)

A decisão de adotar `card-cultural` em Revista e Eventos não pode ser executada como está:

- **Revista:** os cartões são `revista-teaser` (via `node--revista--teaser`). O `revista-teaser` não tem prop de `accent`, e `card-cultural` é um cartão de evento e ponto cultural (data, local, resumo). Trocar um pelo outro muda o conteúdo exibido, não só a aparência.
- **Eventos:** a página de evento já usa `event-page-layout`. As Views de evento (`eventos_datas_relacionadas` e `eventos_locais`, em `config/sync`) são tabelas do painel administrativo (`painel-administrativo/eventos/...`), não listagens públicas. Não há listagem pública de eventos no repositório, então não existe lugar de cartão de evento para trocar por `card-cultural`. Criar um template de Views para essas tabelas seria trocar tabela administrativa por cartão, o que muda a função da tela.
- **Studio e Loja:** nenhum template de página localizado no repositório para `tabs`, `form-field`, `progress`, `empty-state` ou cartão de Loja.
- **Ambiente:** `vendor/` não existe e não há servidor local, então captura visual e os gates que dependem de bootstrap do Drupal não rodam neste ambiente.

Estado após a execução: `revista-teaser` recebeu a prop `accent` e as classes `revista-teaser--accent-*`, com a mesma faixa dos tokens `--accent-*-stripe` do `card-cultural`. Nenhum nó da Revista passa `accent` ainda, então não há mudança visível até a origem do valor ser decidida. Pendente: de onde vem o accent da Revista (campo editorial, administrador por UI Styles ou Portal). Eventos: decisão pendente sobre criar uma listagem pública de eventos com `card-cultural`, ou manter Eventos fora desta rodada.

## 0.2 Decisões de execução e mapeamento (2026-11-05)

- **Revista, origem do accent:** administrador pelo UI Styles (Opção A). Pendente: o `card_accent` atual em `aculta420_v2.ui_styles.yml` lista só `card-cultural--accent-*`. Para a Revista, é preciso estender a opção e fazer o `node--revista--teaser` ler a escolha do administrador. Não implementado: exige validar o plugin do UI Styles num Runtime.
- **Eventos:** criar uma listagem pública com `card-cultural`. Pendente: a view pública, o template e a captura. Não implementado: exige configuração de Views em `config/sync` e cex, que só o agente com Runtime pode gerar e testar.
- **Studio (`aculta_studio`):** as telas usam `aculta_portal_component` com nomes `card`, `context-header`, `content-grid`, `empty-state`, `profile-social`, `profile-identity`, `profile-editor`, `profile-bio` e `context-tabs`. O fallback do Portal (`ComponentFallbackHooks`) monta markup semântico próprio quando o tema não fornece `component`. O tema V2 não mapeia esses nomes para SDCs. Adotar os SDCs exige um preprocess no tema que preencha `component` para `empty-state` e `context-tabs`, com props e slots correspondentes. Não implementado: muda a renderização do Studio, e a verificação exige Runtime.
- **Loja (`shop`):** o V2 não tem template de produto nem de listagem. Só o V1 tem `commerce-product--produto-loja.html.twig`. Não há cartão de loja no V2 para adotar `card-cultural` ou `empty-state`. Antes de adotar, é preciso criar o template de produto do V2, o que é escopo novo, não adoção.

## 1. Consumo atual por componente

Componentes com zero consumidores no repositório (medido por `grep` em templates
do tema e módulos custom):

| Componente | Uso esperado | Observação |
| --- | --- | --- |
| `card-cultural` | cartão com faixa de acento | Consumidor necessário para a F4 (UI Styles de faixa de acento) |
| `tabs` | abas de Studio e conta | Removido com `menu-local-tasks` (revertido) |
| `data-table` | tabelas de Views | Removido com `views-view-table` (revertido) |
| `progress` | progresso de curso e formulário | Removido com `progress-bar` (revertido) |
| `empty-state` | listagens vazias | Removido com `views-view` (revertido) |
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
