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
- **Eventos:** a raiz do purpose Eventos serve listagem pública (HTTP 200; título “Eventos da ACULTA”). O catálogo existente usa `EventCatalogueController`/`EventCatalogue`: inclui Eventos publicados e cancelados com ocorrência futura, expande recorrências, revalida `view` por entidade e aplica cache/filtros por tipo e período. O entity `aculta_event` não declara handler `views_data`; `aculta_event_date` expõe Views data e a relação ao Evento, mas uma View simples sobre datas não reproduz a seleção de próxima ocorrência nem prova a mesma ACL. A adoção `card-cultural` fica pendente até integração que preserve esses contratos e passe comparação visual.
- **Studio (`aculta_studio`):** `PagePresentationHooks::preprocessPortalComponent()` mapeia `context-tabs` para o SDC existente `tabs` e `empty-state` para `empty-state`, preservando props/slots e o fallback genérico nos demais casos. PHPUnit e renderização focal no Drupal passaram. A suíte visual ampla teve 34/48 checks OK, 22 skipped e 14 falhas, inclusive composição e a checagem legada de modo escuro; falta captura real de um consumer de `empty-state`. As outras famílias (`card`, `context-header`, `content-grid`, `profile-*`) continuam sem mapeamento.
- **Loja (`shop`):** o V2 não tem template de produto nem de listagem. Só o V1 tem `commerce-product--produto-loja.html.twig`. Não há cartão de loja no V2 para adotar `card-cultural` ou `empty-state`. Antes de adotar, é preciso criar o template de produto do V2, o que é escopo novo, não adoção.

## 0.2 Estado da execução com Runtime (2026-11-05)

- **Revista — pendente.** Firefox ESR visível confirmou a listagem pública (`/revista`) em claro/escuro, 320/1440px, sem overflow e com foco visível. Baselines: `/home/piradopirata/aculta-suite-out/layouts-purpose-baseline-20261105/revista-baseline-{light,dark}-{320,1440}.png`. UI Styles instalado no Runtime não fornece seleção por linha/nó: o módulo `ui_styles_views` estiliza display, exposição e pager; `ui_styles_entity_status` nem está instalado e, pelo próprio código, só aplica classes a entidades não publicadas. Logo Opção A não pode ser implementada lendo a seleção do administrador por nó pelo mecanismo existente. Nenhuma classe ou precedência foi simulada; requer uma decisão/mecanismo suportado.
- **Eventos — adoção pendente.** `https://eventos.aculta.toca.net.br/` respondeu HTTP 200 e contém a listagem pública com links para dois eventos. `https://eventos.aculta.toca.net.br/eventos` respondeu HTTP 404 na inspeção anterior. A página `oficina-de-ceramica-teste` respondeu 200. Baselines existentes permanecem em `/home/piradopirata/aculta-suite-out/layouts-purpose-baseline-20261105/`; nenhuma captura nova válida foi produzida. A listagem da raiz é um ponto real para avaliar a nova View, sem tocar nas tabelas administrativas.
- **Studio — mapeamento focal validado; adoção visual ampla pendente.** Após `drush cr`, `/meu-perfil` respondeu HTTP 200 e `/conta` redirecionou à raiz do dashboard. `context-tabs` usa o SDC `tabs`; `empty-state` usa o SDC homônimo. Na página real `/group/131`, usuário de teste autenticado e autorizado, o curso não tem aulas publicadas. Firefox ESR confirmou a diferença visual antes/depois, presença do SDC e mensagem em claro/escuro, 320/1440 px; scroll width igual ao viewport e foco de 3 px. Capturas: `$SUITE_OUT/layouts-purpose-studio-empty-state-esr-20261105/{before,after}/course-131-{light,dark}-{320,1440}.png`; foco: `$SUITE_OUT/layouts-purpose-studio-empty-state-esr-20261105/after/course-131-light-320-focus.png`. O diretório anterior com perfil/conta/Marley permanece intacto. A suíte ampla autenticada em `/marley` teve 34/48 checks OK, 22 skipped e 14 falhas; outras famílias Studio seguem sem mapeamento.
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
