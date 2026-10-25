# Contrato de rotas e domínios editoriais — `revista`

**Status:** MAG 0.5-B.7 — validação de rotas e domínio. Este documento descreve
o comportamento observado no Core e na configuração desta branch. Nenhuma rota,
opção de rota, purpose, permissão ou configuração foi alterada para produzi-lo.

**Data da verificação:** 2026-10-22, branch `docs/aculta-forum-0.1.0-dev.1`,
base `bb67917b`, Runtime local `aculta_runtime` (simulação do servidor de testes).

## 1. Fontes

| Fonte | O que foi verificado |
| --- | --- |
| `web/core/modules/node/src/EventSubscriber/NodeAdminRouteSubscriber.php` | marca `_admin_route` em toda rota com `_node_operation_route` quando `node.settings:use_admin_theme` é verdadeiro |
| `config/sync/node.settings.yml` | `use_admin_theme: true` — o assinante está ativo |
| `web/core/modules/node/src/Entity/NodeRouteProvider.php` e `node.routing.yml` | quais rotas recebem `_node_operation_route` |
| `web/modules/custom/aculta_portal/src/Domain/ContentPurposeResolver.php` | precedência de `requiredPurpose()` e `canonicalContentPurpose()` |
| `web/modules/custom/aculta_portal/src/EventSubscriber/DomainPurposeRequestSubscriber.php` | aplicação do purpose, 302/404 e cabeçalhos |
| `web/modules/custom/aculta_portal/src/Domain/DomainPurposeManager.php` | geração de URL adaptada ao ambiente e URL canônica de produção |
| `config/sync/domain.record.*.yml` e `domain_alias.*.yml` | hosts de produção e aliases por ambiente |
| Tabela `router` e `router.route_provider` no Runtime | opções efetivas de cada rota |

As opções foram lidas do Runtime, não inferidas do YAML: a tabela `router`
confere com `router.route_provider` para as rotas abaixo.

## 2. Como o purpose de uma rota é decidido

`ContentPurposeResolver::requiredPurpose()` aplica, nesta ordem:

1. `_admin_route` verdadeiro **ou** `_aculta_domain_purpose === 'main'` → `main`;
2. Grupo de curso LMS → `courses`;
3. `node.add` de `wiki_entry` → `wiki`;
4. Rotas de Node listadas em `WIKI_NODE_ROUTES` cujo Node é `wiki_entry` → `wiki`;
5. Caso contrário, o valor de `_aculta_domain_purpose` (pode ser `NULL`).

Duas consequências que valem para o Studio:

- Uma rota marcada `_admin_route` pelo Core pertence ao purpose `main`, mesmo que
  o conteúdo pertença à Revista. A marcação não vem do Magazine: vem de
  `NodeAdminRouteSubscriber` + `use_admin_theme`, e alcança toda rota de Node
  marcada com `_node_operation_route`.
- Rotas sem `_admin_route` e sem `_aculta_domain_purpose` não têm purpose
  exigido. Elas respondem no host em que forem pedidas e não são redirecionadas.

## 3. Tabela rota × purpose (verificada no Runtime)

| Rota | Caminho | `_admin_route` | Purpose exigido |
| --- | --- | --- | --- |
| `node.add_page` | `/node/add` | `TRUE` | `main` |
| `node.add` | `/node/add/{node_type}` | `TRUE` | `main` |
| `entity.node.edit_form` | `/node/{node}/edit` | `TRUE` | `main` |
| `entity.node.delete_form` | `/node/{node}/delete` | `TRUE` | `main` |
| `entity.node.version_history` | `/node/{node}/revisions` | `TRUE` | `main` |
| `node.revision_revert_confirm` | `/node/{node}/revisions/{node_revision}/revert` | `TRUE` | `main` |
| `node.revision_revert_translation_confirm` | `/node/{node}/revisions/{node_revision}/revert/{langcode}` | `TRUE` | `main` |
| `node.revision_delete_confirm` | `/node/{node}/revisions/{node_revision}/delete` | `TRUE` | `main` |
| `entity.node.canonical` | `/node/{node}` e alias Pathauto | `NULL` | nenhum (purpose canônico do conteúdo) |
| `entity.node.latest_version` | `/node/{node}/latest` | `NULL` | nenhum |
| `entity.node.revision` | `/node/{node}/revisions/{node_revision}/view` | `NULL` | nenhum |
| `entity.node.preview` | `/node/preview/{node_preview}/{view_mode_id}` | `NULL` | nenhum |

Leitura da tabela:

- **Toda operação de escrita editorial** (`add`, `edit`, `delete`, histórico,
  reverter, excluir revisão) é rota administrativa e, portanto, pertence a
  `main`. No ambiente de testes isso é `https://aculta.toca.net.br`; em produção,
  `https://aculta.org`.
- **Leitura pública e de revisão** (`canonical`, `latest-version`, `revision`,
  `preview`) não fixa purpose. A canônica resolve o purpose pelo próprio
  conteúdo (`field_domain_source`; tópicos do Fórum → `forum`); as demais
  respondem no host em que forem pedidas, sempre sob Entity Access/Revision
  Access.
- `_admin_route` também troca o tema para o administrativo nessas páginas. É
  comportamento do Core, não um desvio local.

## 4. Semântica de host errado

`DomainPurposeRequestSubscriber` aplica o purpose exigido antes e depois do
roteamento:

| Situação | Resultado |
| --- | --- |
| Host com purpose correto | segue para o roteamento normal |
| Host errado, purpose exigido `main`, rota administrativa ou canônica cross-domain, método `GET`/`HEAD` | `302` para a mesma rota no host `main` |
| Host errado, purpose exigido `main`, método diferente de `GET`/`HEAD` | `404` |
| Host errado, purpose exigido diferente de `main` (ex.: `account`, `forum`) | `404` |

O `302` de navegação canônica:

- carrega `Cache-Control: private, no-store` e
  `X-Robots-Tag: noindex, nofollow`;
- reescreve `?destination=` para o host `main`, para que o formulário volte ao
  contexto correto;
- preserva a sessão, porque o cookie de sessão é emitido para
  `.aculta.toca.net.br` no servidor de testes e para o domínio registrável em
  produção. Verificado com sessão autenticada: o `302` da Revista chega ao host
  `main` já autenticado.

O `404` para métodos não `GET`/`HEAD` é intencional: um `POST` nunca é
redirecionado, para não perder corpo de formulário, token CSRF ou semântica de
método. **Um consumidor que envie formulários precisa montar a URL no host
`main` diretamente; não pode postar no host da Revista esperando o `302`.**

## 5. Regras de URL para consumidores (Studio)

1. **Links de operação** (`add`, `edit`, `delete`, histórico, reverter) usam
   `DomainPurposeManager::routeUrl('main', $routeName, $parameters)`. Isso
   devolve o host do ambiente ativo (`aculta.org` em produção,
   `*.aculta.toca.net.br` no servidor de testes), nunca um host escrito à mão.
2. **Links de leitura** (`canonical`, `latest-version`, `revision`, `preview`)
   usam `routeUrl($purposeDoConteudo, …)` ou a URL canônica do Node. Não é
   necessário forçar `main`: essas rotas não exigem purpose.
3. **URL canônica de identidade/SEO** (para metatag, compartilhamento, sitemap)
   usa `canonicalRouteUrl()`/`canonicalPathUrl()`, que restauram o host de
   produção a partir de `domain.record.*`, independentemente do alias do
   ambiente. URL canônica **não** é link de navegação: no servidor de testes ela
   aponta para `aculta.org`.
4. **Nunca gerar URL fora de uma requisição** com `pathUrl()`: sem requisição, o
   ambiente de alias é `default` e o resultado cai em `http://default/...`.
5. **Não depender do `302`** para operações: além do custo de um salto, `POST`
   recebe `404`. Gerar a URL de `main` direto é o contrato.
6. **Não deduzir purpose pelo conteúdo**: o purpose de uma rota é decidido pela
   opção da rota no Core/Portal, não pelo bundle `revista`.
7. Um link de edição pode ser mostrado apenas quando
   `$node->access('update')` for permitido para a conta atual (ver
   `EDITORIAL-ACCESS-MATRIX.md`). O host correto não substitui Entity Access.

## 6. Evidência de Runtime

Requisições HTTPS locais via `--resolve host:443:127.0.0.1`, contra
`aculta_runtime`; nenhuma configuração foi importada.

### 6.1 Anônimo

| Requisição | Resultado |
| --- | --- |
| `https://revista420.aculta.toca.net.br/node/90` | `301` para o alias → `200` |
| `https://revista420.aculta.toca.net.br/noticias/novo-exame-…` | `200` |
| `https://revista420.aculta.toca.net.br/node/90/latest` | `403` (sem `view latest version`) |
| `https://revista420.aculta.toca.net.br/node/90/revisions` | `302` → `https://aculta.toca.net.br/node/90/revisions` |
| `https://revista420.aculta.toca.net.br/node/90/edit` | `302` → `https://aculta.toca.net.br/node/90/edit` |
| `https://revista420.aculta.toca.net.br/node/add/revista` (GET) | `302` → `https://aculta.toca.net.br/node/add/revista` |
| `https://revista420.aculta.toca.net.br/node/add/revista` (POST) | `404` |
| `https://aculta.toca.net.br/node/90` | `404` (purpose canônico é `magazine`) |
| `https://aculta.toca.net.br/node/90/edit` e demais rotas administrativas | `403` (Entity Access para anônimo) |

### 6.2 Autenticado (`content_editor`, uid 15)

| Requisição | Resultado |
| --- | --- |
| `https://aculta.toca.net.br/node/90/edit` | `200` |
| `https://revista420.aculta.toca.net.br/node/90/edit` | `302` → `https://aculta.toca.net.br/node/90/edit` |
| `https://revista420.aculta.toca.net.br/node/90/edit?destination=/node/90` | `302` com `destination=https%3A//aculta.toca.net.br/node/90` |
| `https://aculta.toca.net.br/node/90/revisions` | `200` |
| `https://revista420.aculta.toca.net.br/node/90/latest` | `403` (não há revisão pendente; regra de `latest-version`) |
| `POST` em `/node/90/edit` e `/node/add/revista` no host da Revista | `404` |

O `403` de `latest-version` está correto: o Node 90 está `published` na revisão
padrão e não tem revisão pendente, e a rota exige revisão pendente.

### 6.3 Login e sessão entre hosts

`user.reset.login` (`/recuperar-acesso/{uid}/{timestamp}/{hash}/entrar`) tem
`_aculta_domain_purpose: account`. Um link `drush uli` gerado com
`--uri=https://revista420.aculta.toca.net.br` aponta para o host da Revista e
recebe `404` por desenho. Para autenticar no Runtime, gere o link com
`--uri=https://conta.aculta.toca.net.br`. O cookie de sessão observado é
`.aculta.toca.net.br`, portanto a sessão acompanha o `302` entre subdomínios.

## 7. Achados operacionais

Registrados para revisão; nenhum foi corrigido nesta microphase.

1. **`node_access` sem grants para `revista`.** O `aculta_forum` implementa
   `node_grants`, o que ativa o sistema de grants do Core. No Runtime local, os
   12 Nodes `revista` não tinham linha em `node_access` e todo conteúdo — até
   `published` — respondia `403` a anônimo. Causa: a ativação do hook não foi
   seguida de `node_access_rebuild()`. Remediação executada **somente** no
   `aculta_runtime` local, após backup em
   `/tmp/aculta_runtime_pre-node-access-rebuild-2026-10-22.sql`: rebuild
   (18 → 33 linhas, todas realm `all`, gid 0) e `drush cr`, após o que a canônica
   passou a `200`. É condição de implantação, não defeito do Magazine: qualquer
   ambiente que ative `node_grants` precisa do rebuild.
2. **IDs de domínio obsoletos em configuração.** `coletivo420_aculta_org`
   permanece em `field.field.node.revista.field_domain_source` (default),
   `field.field.node.editorial_highlight.field_domain_source` (default) e em
   `system.action.domain_source_set_action.revista420_aculta_org`
   (`configuration.domain_id`; o `domain_id` de topo já é
   `revista420_aculta_org`). Não existe `domain.record.coletivo420_aculta_org`.
   A configuração é sensível ao ambiente e a correção exige decisão própria;
   não foi alterada em B.7.
3. **Node 123 (`revista`, publicado) sem `field_domain_source`.** A canônica não
   resolve purpose `magazine` por conteúdo para esse Node; os outros 11 Nodes da
   Revista têm `revista420_aculta_org`. Lacuna de dados, não de código.
4. **Erro de container do Studio não se reproduz.** Um registro de watchdog
   apontava `StudioListingBuilder::__construct()` recebendo 5 argumentos; a
   definição atual em `aculta_studio.services.yml` passa 6 e
   `https://conta.aculta.toca.net.br/studio` responde `200` com sessão
   editorial. Registro obsoleto de container anterior; nenhum defeito atual.

## 8. O que este documento não altera

- Não altera rotas, opções de rota, purposes, aliases, hosts, permissões,
  estados ou transições.
- Não importa configuração, não migra conteúdo e não faz alteração persistente
  além do rebuild de `node_access` já descrito, restrito ao Runtime local.
- Não move propriedade: o Portal continua dono de purpose/domínio; o Magazine
  documenta o contrato; o Studio continua consumidor.
- Não inicia MAG 0.5-C.
