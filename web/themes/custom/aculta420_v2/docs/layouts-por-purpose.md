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

- **Revista, origem do accent:** escolha administrativa única no UI Styles, global à Revista, com precedência sobre o accent do Portal (Opção A). `aculta420_v2.settings` guarda `revista_accent`; o preprocess aplica `revista-teaser--accent-{green|yellow|red}` ao teaser. O campo legado `field_revista_accent` não participa da escolha nem do render e permanece instalado para evitar remoção destrutiva. Submit visual, classe em listagem pública após submit e verificação no banco continuam pendentes nesta rodada.
- **Eventos:** a raiz do purpose Eventos serve listagem pública (HTTP 200; título “Eventos da ACULTA”). A View pública nova consome IDs e dados preparados por `EventCatalogueController`/`EventCatalogue`: mantém Eventos publicados e cancelados com ocorrência futura, expansão de recorrências, revalidação `view` por entidade, ordem e filtros por tipo/período. A View usa tabela base `aculta_event`, handlers próprios de cartão/ordenação e o template V2 `card-cultural`; ela não substitui a seleção de ocorrências do catálogo. Validação visual pós-View em Firefox ESR: dois cards, claro/escuro, 320/1440 px, foco visível e zero overflow. Capturas em `$SUITE_OUT/layouts-purpose-events-cards-20261106-r2/`. A listagem não tinha baseline antes de existir; portanto a implementação está validada funcionalmente e visualmente, mas não há comparação antes/depois da listagem.
- **Studio (`aculta_studio`):** `/conta`, `context-tabs`→`tabs`, `profile-identity`→`author-card`, `profile-bio`/`profile-social`→`profile-details` validados no Runtime. A comparação focal de bio/redes passou 97/97 verificações (22 ignoradas, zero falhas), com títulos associados, slots preservados, foco visível e sem overflow nos dois modos a 320/1440 px. Capturas em `$SUITE_OUT/layouts-purpose-studio-profile-details-paired-20261106/{before,after}/`. A comparação anterior da identidade segue em `$SUITE_OUT/layouts-purpose-studio-profile-identity-paired-20261106/{before,after}/`; rotas em `$SUITE_OUT/layouts-purpose-studio-routes-20261106/studio-account-routes-{before,after}/`; `context-tabs` e `empty-state` mantêm as evidências previamente registradas.
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
- **Studio — adoção SDC parcial, com bio/redes validadas.** O Drush não oferece o comando `router:debug`; o `router.route_provider` encontrou `/meu-perfil`, `/conta` e `/marley`. A suíte Firefox ESR autenticada confirmou que `/meu-perfil` renderiza. `profile-bio` e `profile-social` agora usam `profile-details`; comparação pareada focal passou 97/97 checks (22 ignorados, zero falhas) com foco e zero overflow em claro/escuro a 320/1440 px. Capturas em `$SUITE_OUT/layouts-purpose-studio-profile-details-paired-20261106/{before,after}/`. Identidade, abas e rotas conservam as evidências acima.
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
`donation-quick`, `donation-presentation`, `social-links-page`, `status-messages`, `tabs`, `wiki-entry`.

## 2. Páginas por purpose

| Purpose | Templates localizados | Componentes em uso | Situação |
| --- | --- | --- | --- |
| `main` | `templates/layout/page.html.twig` (`layout-page`), `site-footer`, `multidomain-bar` | `layout-page`, `site-footer`, `multidomain-bar` | Shell usa componentes; página interna de conteúdo sem `hero-banner` |
| `account` | Não localizado no repositório como template de página; Studio usa `context-tabs` e os componentes de perfil | `tabs`, `author-card` e `profile-details` consumidos para abas, identidade, bio e redes | `form-field` e `progress` sem consumer de Studio |
| `support` (Apoio) | Hooks neutros do `aculta_donation`; templates do módulo são fallbacks para outros temas | `button`, `donation-quick`, `donation-presentation` | Blocos Doar/Voltar e valores rápidos emitem SDCs; formulários e resumo da doação continuam no módulo |
| `magazine` (Revista) | `content/node--revista--full`, `content/node--revista--teaser`, `views/views-view--revista-sessoes--page-revista` | `revista-publication`, `revista-teaser`, `revista-listing`, `author-card` | Principal candidato a `card-cultural` com faixa de acento |
| `wiki` | `content/node--wiki-entry--full`, `content/node--wiki-entry--teaser` | `wiki-entry`, `breadcrumb` | Aguarda confirmação da F2 para faixa de acento da Wiki |
| `shop` (Loja) | Não localizado no repositório do tema | Nenhum | Aguarda autorização da Loja (ver `price`) |
| `courses` (Cursos/LMS) | `lms-lesson-page` (SDC); `aculta_lms` sem templates próprios localizados | `lms-lesson-page`, `progress` (sem consumo) | Falta `progress` e `tabs` |
| `forum` | `content/node--aculta-forum-topic`; displays de categorias usam row UI Patterns e wrappers padrão de Views | `forum-topic`, `forum-category`, `pagination` | Falta `empty-state` e `data-table` |
| `eventos` | View `eventos_publicos` na raiz; `aculta-event-page.html.twig` para detalhe | `card-cultural` na listagem; `event-page-layout` no detalhe | Listagem pública validada; estado vazio ainda sem consumer real |
| `links` (LinkWeed) | Hook neutro do LinkWeed; templates do módulo são fallback para outros temas | `social-links-page` na micropágina; `layout-section` na landing | Avaliar `hero-banner` na landing após homologar os dois consumidores atuais |

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
