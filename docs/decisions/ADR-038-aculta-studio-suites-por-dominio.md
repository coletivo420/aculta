# ADR-038 - Suites do ACULTA Studio distribuídas por domínio

- **Status:** Aceita; paths e bloqueio das rotas antigas implementados. O Domain Wiki canônico permanece `wiki420`, conforme correção do responsável em 2026-10-30; não há migração de host Wiki neste escopo. Grants e fluxos completos ainda requerem homologação.
- **Data:** 2026-10-30.
- **Decisor:** responsável pelo produto.
- **Contexto:** As experiências do Studio estão concentradas no host `conta` sob `/studio/*`, apesar de pertencerem a domínios funcionais distintos.
- **Decisão:** Marley `account:/marley`; Ganjalab `courses:/ganjalab`; WE SATIVA `wiki:/wesativa`; Green Stash `shop:/greenstash`; Culturas `eventos:/culturas`. Hosts seguem o Domain ativo por ambiente.
- **Retirada do prefixo:** `/studio` e todas as rotas públicas `/studio/*` deixam de existir após a migração e respondem 404, sem aliases/redirects. A marca ACULTA Studio e o módulo `aculta_studio` permanecem como plataforma/registry, sem hub URL sob `/studio`.
- **Conta e Perfil:** seguem independentes das Suites. A raiz `account` será uma página de entrada da Conta e não reescreve para `/studio`; `/conta-legada` continua. Rotas atuais `/studio/conta/*`, `/studio/perfil/*`, `/studio/salvos` e `/studio/conta/mercado-pago/planos` migram para caminhos próprios sem `/studio`, preservando owners e separação Conta/Perfil.
- **Ownership:** `aculta_lms`, `aculta_wiki`, `aculta_commerce` e `aculta_events` mantêm dados, formulários, ACL e workflows. `aculta_studio` compõe interfaces; não duplica seus dados ou operações.
- **Revista no Marley:** o trabalho editorial de não administradores autorizados ocorre em `/marley/revista/*`, purpose `account`, no domínio de Marley. `/painel-administrativo/revista` permanece no purpose `main`, exclusivo para configuração e supervisão administrativa. O owner `aculta_magazine` mantém o bundle `revista`, formulários, workflow e comentários; o Studio compõe as jornadas e delega operações às APIs do owner, sem escrita cross-domain.
- **Discussão contextual da Revista:** `field_aculta_discussion` e o tipo Comment `aculta_discussion_post` permanecem associados diretamente ao Node `revista`. `aculta_forum` fornece contexto, apresentação, access checks e políticas compartilhadas; isso não converte a discussão em `ForumPost`, não cria tópico público automático e não inclui a Revista na fila Growroom.
- **Host Wiki:** o host canônico é o Domain configurado `wiki420` (`wiki420.aculta.*`). A referência a `wiki.aculta.*` no pedido inicial foi corrigida pelo responsável; preservar a configuração atual, sem novo Domain, DNS, TLS ou corte de host.
- **Fronteira:** escrita ocorre somente no purpose da Suite; host errado responde 404, inclusive POST. Navegação usa URL absoluta via `DomainPurposeManager`, sem POST cross-domain.
- **Novas Suites:** toda implementação futura escolhe domínio/purpose e caminho próprios; não criar novo `/studio/{suite}`.
- **Plano:** [contrato da plataforma](../architecture/ACULTA-STUDIO-EXPERIENCE-PLATFORM.md), [sitemap](../architecture/ROUTES-SITEMAP.md), [front paths](../portal/DOMAIN-FRONT-PATHS.md) e roadmap `aculta_studio`.

## Fases

1. Inventariar rotas `/studio*`, route IDs, links, callbacks, destinos de login, canonical/cache/sitemap e ownership entre módulos.
2. Definir caminhos de Conta, Perfil, Salvos e planos Mercado Pago fora de `/studio`.
3. Mover rotas/providers às cinco purposes, mantendo IDs quando possível; Ganjalab passa de `account` para `courses`.
4. Retirar reescrita da raiz `account` para `/studio` e criar sua entrada de Conta.
5. Confirmar que links para o purpose `wiki` usam o Domain ativo `wiki420`, resolvido pelo `DomainPurposeManager`.
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

Os nomes de host não são parte das rotas: links absolutos entre purposes devem ser gerados pelo `DomainPurposeManager`. O propósito `wiki` usa o Domain configurado `wiki420` em cada ambiente. Não renomear o host para `wiki.aculta.*`.

## Estado da execução (2026-10-30)

- **Implementado no código:** caminhos das cinco Suites; rotas separadas de Conta, Perfil, salvos e Mercado Pago; raiz do purpose `account` como entrada da Conta; bloqueio global de `/studio` e `/studio/*` sem alias/redirect. A configuração exportada de front path da Conta aponta para `/conta`.
- **Verificado no servidor de desenvolvimento:** após rebuild de cache, `/studio` e amostras de `/studio/*` responderam 404; a matriz de GET/POST retornou 404 nos purposes errados; os caminhos canônicos resolveram no purpose correto. A rota Culturas autenticada foi validada em Firefox depois da correção publicada em `95314a5a7`.
- **Não concluído:** auditoria integral dos consumidores residuais, testes Kernel POST/AJAX em `aculta_simpletest`, grants/fluxos completos das Suites, validação de escrita para loja/eventos e homologação dos cinco Domains.
- **Validação do host Wiki:** `wiki420.aculta.toca.net.br/wesativa` foi acessado por HTTPS em 2026-10-30 e retornou 403 anônimo (rota protegida; TLS e host alcançados). Firefox autenticado capturou WE SATIVA em `/wesativa`. `wiki420.aculta.org` não resolveu por DNS a partir deste ambiente de desenvolvimento; disponibilidade de produção não foi verificada. Nenhuma troca de host está planejada.

Os commits de implementação e correção de Culturas estão publicados na branch da PR #299. Os detalhes e saídas dos comandos da sessão devem ficar no registro operacional, distinguindo validação local de homologação externa.
