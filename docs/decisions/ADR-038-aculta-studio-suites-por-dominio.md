# ADR-038 - Suites do ACULTA Studio distribuídas por domínio

- **Status:** Aceita; implementação em andamento. A tabela abaixo registra o contrato de migração; rotas e hosts só passam a constar como ativos após validação no Runtime.
- **Data:** 2026-10-30.
- **Decisor:** responsável pelo produto.
- **Contexto:** As experiências do Studio estão concentradas no host `conta` sob `/studio/*`, apesar de pertencerem a domínios funcionais distintos.
- **Decisão:** Marley `account:/marley`; Ganjalab `courses:/ganjalab`; WE SATIVA `wiki:/wesativa`; Green Stash `shop:/greenstash`; Culturas `eventos:/culturas`. Hosts seguem o Domain ativo por ambiente.
- **Retirada do prefixo:** `/studio` e todas as rotas públicas `/studio/*` deixam de existir após a migração e respondem 404, sem aliases/redirects. A marca ACULTA Studio e o módulo `aculta_studio` permanecem como plataforma/registry, sem hub URL sob `/studio`.
- **Conta e Perfil:** seguem independentes das Suites. A raiz `account` será uma página de entrada da Conta e não reescreve para `/studio`; `/conta-legada` continua. Rotas atuais `/studio/conta/*`, `/studio/perfil/*`, `/studio/salvos` e `/studio/conta/mercado-pago/planos` migram para caminhos próprios sem `/studio`, preservando owners e separação Conta/Perfil.
- **Ownership:** `aculta_lms`, `aculta_wiki`, `aculta_commerce` e `aculta_events` mantêm dados, formulários, ACL e workflows. `aculta_studio` compõe interfaces; não duplica seus dados ou operações.
- **Host Wiki:** alvo `wiki.aculta.org` / `wiki.aculta.toca.net.br`. Configuração atual ainda usa `wiki420.aculta.*`; mudança depende de Domain/Deployer, DNS, TLS/vhost e corte testado.
- **Fronteira:** escrita ocorre somente no purpose da Suite; host errado responde 404, inclusive POST. Navegação usa URL absoluta via `DomainPurposeManager`, sem POST cross-domain.
- **Novas Suites:** toda implementação futura escolhe domínio/purpose e caminho próprios; não criar novo `/studio/{suite}`.
- **Plano:** [contrato da plataforma](../architecture/ACULTA-STUDIO-EXPERIENCE-PLATFORM.md), [sitemap](../architecture/ROUTES-SITEMAP.md), [front paths](../portal/DOMAIN-FRONT-PATHS.md) e roadmap `aculta_studio`.

## Fases

1. Inventariar rotas `/studio*`, route IDs, links, callbacks, destinos de login, canonical/cache/sitemap e ownership entre módulos.
2. Definir caminhos de Conta, Perfil, Salvos e planos Mercado Pago fora de `/studio`.
3. Mover rotas/providers às cinco purposes, mantendo IDs quando possível; Ganjalab passa de `account` para `courses`.
4. Retirar reescrita da raiz `account` para `/studio` e criar sua entrada de Conta.
5. Planejar host `wiki.aculta.*` com DNS/TLS/vhost antes do corte.
6. Atualizar navegação, login/retorno, canonical, cache, metatags, sitemap, guardrails e gates.
7. Homologar os cinco hosts, propósitos certos/errados, GET/POST, anônimo/autenticado, owners, acessibilidade, mobile e URLs antigas.

Não fazer corte parcial que deixe links ou rotas graváveis em `/studio`; não marcar concluído sem testes por host e Runtime.

## Correspondência de caminhos aprovada

Os destinos abaixo preservam os IDs de rota quando possível e movem a URL para o purpose dono. As rotas antigas `/studio*` serão removidas sem alias ou redirect.

| Caminho antigo | Caminho/purpose novo | Owner e decisão |
| --- | --- | --- |
| `/studio/marley` | `/marley` · `account` | `aculta_studio`; entrada Marley |
| `/studio/marley/{aba}[/{secao}[/{sub}]]` | `/marley/{aba}[/{secao}[/{sub}]]` · `account` | `aculta_studio`; composição Marley e ações dos owners |
| `/studio/ganjalab` | `/ganjalab` · `courses` | rota `aculta_lms.student_dashboard`; LMS |
| `/studio/we-sativa` | `/wesativa` · `wiki` | `aculta_studio`; workspace WE SATIVA |
| `/studio/we-sativa/inicio` | `/wesativa/inicio` · `wiki` | `aculta_studio`; entrada delegada ao workspace Wiki |
| `/studio/marley/wiki/propostas/{proposal}/decidir` | `/wesativa/propostas/{proposal}/decidir` · `wiki` | `aculta_wiki.proposal_decide`; operação editorial Wiki |
| `/studio/green-stash` | `/greenstash` · `shop` | `aculta_commerce.green_stash`; Commerce |
| `/studio/green-stash/{secao}` | `/greenstash/{secao}` · `shop` | `aculta_commerce.green_stash_area`; Commerce |
| `/studio/green-stash/produtos/novo` | `/greenstash/produtos/novo` · `shop` | `aculta_commerce.green_stash_product_create`; Commerce |
| `/studio/green-stash/produtos/{produto}` | `/greenstash/produtos/{produto}` · `shop` | `aculta_commerce.green_stash_product_detail`; Commerce |
| `/studio/green-stash/envios/{aculta_shipment}/transicao/{transition}` | `/greenstash/envios/{aculta_shipment}/transicao/{transition}` · `shop` | `aculta_commerce.green_stash_shipment_transition`; Commerce |
| `/studio/culturas` | `/culturas` · `eventos` | destino aprovado; `aculta_events`; não havia rota de Suite implementada no HEAD inicial |
| `/studio/conta` | `/conta` · `account` | `aculta_studio`; composição da Conta, sem dados de Perfil |
| `/studio/conta/dados` | `/conta/dados` · `account` | Portal; dados privados |
| `/studio/conta/endereco` | `/conta/endereco` · `account` | Portal/CEP central; endereço privado |
| `/studio/conta/documentos` | `/conta/documentos` · `account` | Portal e owner opcional `aculta_id_cpf` |
| `/studio/conta/seguranca` | `/conta/seguranca` · `account` | Portal; segurança e e-mail |
| `/studio/conta/seguranca/senha` | `/conta/seguranca/senha` · `account` | Portal; senha |
| `/studio/conta/conexoes` | `/conta/conexoes` · `account` | Portal; conexões de autenticação |
| `/studio/conta/pedidos` | `/conta/pedidos` · `account` | Portal/Commerce; pedidos da pessoa |
| `/studio/conta/apoio` | `/conta/apoio` · `account` | Portal; apoio da pessoa |
| `/studio/conta/preferencias` | `/conta/preferencias` · `account` | Portal; preferências |
| `/studio/perfil` | `/perfil` · `account` | `aculta_user`; identidade comunitária, separada da Conta |
| `/studio/perfil/apelido` | `/perfil/apelido` · `account` | `aculta_user`; apelido público |
| `/studio/perfil/privacidade` | `/perfil/privacidade` · `account` | `aculta_user`; visibilidade |
| `/studio/perfil/bio` | `/perfil/bio` · `account` | `aculta_user`; biografia |
| `/studio/perfil/redes/{rede}` | `/perfil/redes/{rede}` · `account` | `aculta_user`; redes sociais |
| `/studio/salvos` | `/salvos` · `account` | `aculta_studio` compõe; consulta compartilhada de salvos |
| `/studio/conta/mercado-pago/planos` | `/conta/mercado-pago/planos` · `account` | `aculta_pagamentos_mercadopago`; gestão de planos da própria conta |
| `/studio` | removida | Hub público retirado; `aculta_studio` continua como framework/registry |
| `/` no purpose `account` | entrada da Conta via front do purpose | Não aponta ao Studio; `/conta-legada` permanece |

Os nomes de host não são parte das rotas: links absolutos entre purposes devem ser gerados pelo `DomainPurposeManager`. O propósito `wiki` conserva o Domain ativo `wiki420` até que `wiki.aculta.*` seja provisionado e validado em DNS, TLS, vhost/Deployer e HTTP.
