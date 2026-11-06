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

- **Revista, origem do accent:** administrador pelo UI Styles (Opção A), que prevalece sobre o accent do Portal. O módulo owner `aculta_magazine` agora armazena a escolha em `field_revista_accent`, usa o widget oficial UI Styles e aplica a classe ao wrapper do teaser. A allowlist limita a escolha às variantes green/yellow/red. Widget no formulário administrativo confirmado por Firefox ESR; opções green/yellow/red presentes. Teste visual transitório da classe red na listagem confirmou a faixa e as cores computadas em claro/escuro a 320/1440 px, sem overflow. A captura usa a classe somente no DOM. Separadamente, em uma transação Drupal revertida, a opção do widget foi consumida pelo builder do campo nativo e o HTML do teaser renderizado recebeu a classe no `<article>`; o campo voltou vazio após rollback. O submit visual no navegador e a persistência após salvar permanecem pendentes.
- **Eventos:** a raiz do purpose Eventos serve listagem pública (HTTP 200; título “Eventos da ACULTA”). A View pública nova consome IDs e dados preparados por `EventCatalogueController`/`EventCatalogue`: mantém Eventos publicados e cancelados com ocorrência futura, expansão de recorrências, revalidação `view` por entidade, ordem e filtros por tipo/período. A View usa tabela base `aculta_event`, handlers próprios de cartão/ordenação e o template V2 `card-cultural`; ela não substitui a seleção de ocorrências do catálogo. Validação visual pós-View em Firefox ESR: dois cards, claro/escuro, 320/1440 px, foco visível e zero overflow. Capturas em `$SUITE_OUT/layouts-purpose-events-cards-20261106-r2/`. A listagem não tinha baseline antes de existir; portanto a implementação está validada funcionalmente e visualmente, mas não há comparação antes/depois da listagem.
- **Studio (`aculta_studio`):** `/conta`, `context-tabs`→`tabs`, `profile-identity`→`author-card`, `profile-bio`/`profile-social`→`profile-details` validados no Runtime. A comparação focal de bio/redes passou 97/97 verificações (22 ignoradas, zero falhas), com títulos associados, slots preservados, foco visível e sem overflow nos dois modos a 320/1440 px. Capturas em `$SUITE_OUT/layouts-purpose-studio-profile-details-paired-20261106/{before,after}/`. A comparação anterior da identidade segue em `$SUITE_OUT/layouts-purpose-studio-profile-identity-paired-20261106/{before,after}/`; rotas em `$SUITE_OUT/layouts-purpose-studio-routes-20261106/studio-account-routes-{before,after}/`; `context-tabs` e `empty-state` mantêm as evidências previamente registradas.
- **Loja (`shop`):** o V2 não tem template de produto nem de listagem. Só o V1 tem `commerce-product--produto-loja.html.twig`. Não há cartão de loja no V2 para adotar `card-cultural` ou `empty-state`. Antes de adotar, é preciso criar o template de produto do V2, o que é escopo novo, não adoção.

## 0.2 Estado da execução com Runtime (2026-11-06)

- **Revista — adapter e CSS verificados parcialmente no runtime.** Firefox ESR confirmou o widget administrativo e as opções green/yellow/red no formulário de edição. Na raiz pública do purpose, uma classe red transitória no DOM mudou a faixa computada de `rgb(107, 125, 112)` para `rgb(181, 47, 34)` no modo claro e `rgb(231, 188, 184)` no escuro; `innerWidth` e `scrollWidth` foram 320/320 e 1440/1440. Capturas antes/depois e com o campo nativo temporariamente salvo em `$SUITE_OUT/revista-accent-esr-20261106/` (`persisted-red-{light,dark}-{320,1440}.png`). O submit visual e a captura do estado salvo continuam pendentes; F4/F5 seguem parciais.
- **Eventos — View pública validada, sem baseline de listagem anterior.** A raiz do purpose responde HTTP 200 e renderiza dois `card-cultural` pela View pública. Kernel Events: 155 testes/3.616 assertions PASS (33 depreciações Core/contrib); capturas Firefox ESR (listagem e detalhe) em `$SUITE_OUT/layouts-purpose-events-cards-20261106-r2/`: claro/escuro, 320/1440 px, foco visível e zero overflow. A página de detalhe foi capturada como navegação/layout, sem alegar uso de SDC nela. A baseline de 2026-11-05 foi preservada; como a listagem foi criada nesta entrega, não existe captura válida “antes” dela. `config:status` não mostra diferença para a View exportada; divergências anteriores de blocos administrativos, CAPTCHA/Turnstile e `core.extension` permanecem.
- **Studio — adoção SDC parcial, com bio/redes validadas.** O Drush não oferece o comando `router:debug`; o `router.route_provider` encontrou `/meu-perfil`, `/conta` e `/marley`. A suíte Firefox ESR autenticada confirmou que `/meu-perfil` renderiza. `profile-bio` e `profile-social` agora usam `profile-details`; comparação pareada focal passou 97/97 checks (22 ignorados, zero falhas) com foco e zero overflow em claro/escuro a 320/1440 px. Capturas em `$SUITE_OUT/layouts-purpose-studio-profile-details-paired-20261106/{before,after}/`. Identidade, abas e rotas conservam as evidências acima.
- **Loja — pendente por autorização.** Não foi encontrada autorização registrada para criar templates de produto/listagem do V2. O componente `price` permanece fora do escopo.

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
`discussion-comment`, `empty-state`, `event-page-layout`, `forum-category`,
`forum-topic`, `icon`, `layout-page`, `lms-lesson-page`, `map-popup`,
`messenger-trigger`, `multidomain-bar`, `pagination`, `purpose-showcase`,
`revista-listing`, `revista-publication`, `revista-teaser`, `site-footer`,
`social-links-page`, `status-messages`, `tabs`, `wiki-entry`.

## 2. Páginas por purpose

| Purpose | Templates localizados | Componentes em uso | Situação |
| --- | --- | --- | --- |
| `main` | `templates/layout/page.html.twig` (`layout-page`), `site-footer`, `multidomain-bar` | `layout-page`, `site-footer`, `multidomain-bar` | Shell usa componentes; página interna de conteúdo sem `hero-banner` |
| `account` | Não localizado no repositório como template de página; Studio usa `context-tabs` e os componentes de perfil | `tabs`, `author-card` e `profile-details` consumidos para abas, identidade, bio e redes | `form-field` e `progress` sem consumer de Studio |
| `support` (Apoio) | `aculta_donation`: `aculta-donation-donate`, `-summary`, `-quick`, `-back`, `-summary-container` | Nenhum SDC | Templates próprios do módulo, sem componente |
| `magazine` (Revista) | `content/node--revista--full`, `content/node--revista--teaser`, `views/views-view--revista-sessoes--page-revista` | `revista-publication`, `revista-teaser`, `revista-listing`, `author-card` | Principal candidato a `card-cultural` com faixa de acento |
| `wiki` | `content/node--wiki-entry--full`, `content/node--wiki-entry--teaser` | `wiki-entry`, `breadcrumb` | Aguarda confirmação da F2 para faixa de acento da Wiki |
| `shop` (Loja) | Não localizado no repositório do tema | Nenhum | Aguarda autorização da Loja (ver `price`) |
| `courses` (Cursos/LMS) | `lms-lesson-page` (SDC); `aculta_lms` sem templates próprios localizados | `lms-lesson-page`, `progress` (sem consumo) | Falta `progress` e `tabs` |
| `forum` | `content/node--aculta-forum-topic`, `views/views-view-fields--aculta-forum-*`, `views-view-unformatted--aculta-forum-categories--page-categories` (módulo `aculta_forum`) | `forum-topic`, `forum-category`, `pagination` | Falta `empty-state` e `data-table` |
| `eventos` | View `eventos_publicos` na raiz; `aculta-event-page.html.twig` para detalhe | `card-cultural` na listagem; `event-page-layout` no detalhe | Listagem pública validada; estado vazio ainda sem consumer real |
| `links` (LinkWeed) | `aculta-links-page.html.twig` (tema e módulo), `aculta-links-landing.html.twig`, `social-links-page` | `social-links-page` | Candidato a `hero-banner` na landing |

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
