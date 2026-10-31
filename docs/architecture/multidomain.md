# Multidomínio

Purposes estáveis ativos: `main`, `account`, `support`, `magazine`, `wiki`,
`shop`, `courses`, `forum`, `links` e `eventos`.

O purpose `forum` foi implementado com os módulos `forum` e `flag`. A condição
anterior (validação da feature de participação, F3) foi substituída por decisão
do responsável em 2026-10-11, que pediu a raiz do host do fórum servindo `/forum`.
Em 2026-10-19, esse caminho interno foi substituído pela View pública nova em
`/discussoes`; a raiz permanece o endereço canônico, e acesso direto aos dois
caminhos internos responde 404.

Código customizado depende do purpose, não do hostname de ambiente.
`DomainPurposeManager` centraliza Domain ID, URL correta, canonical de produção
e purpose atual. Rotas podem declarar `_aculta_domain_purpose`; o subscriber
central bloqueia hosts incorretos.

A mesma regra vale para apresentação: logo, título, navegação e outros dados de
branding específicos são resolvidos a partir do purpose pelo `aculta_portal` e
entregues ao tema como contexto simples. ACULTA420 não escolhe identidade por
hostname e não recebe entidade `Domain` em Twig/SDC. Ver o contrato planejado em
`web/themes/custom/aculta420/docs/shell.md`.

| Purpose | Produção | Homelab | Estado |
| --- | --- | --- | --- |
| main | aculta.org | aculta.toca.net.br | ativo |
| account | conta.aculta.org | conta.aculta.toca.net.br | ativo |
| support | apoio.aculta.org | apoio.aculta.toca.net.br | ativo |
| magazine | revista.aculta.org | revista.aculta.toca.net.br | ativo |
| wiki | wiki420.aculta.org | wiki420.aculta.toca.net.br | ativo |
| shop | loja.aculta.org | loja.aculta.toca.net.br | Domain ativo; catálogo futuro |
| courses | cursos.aculta.org | cursos.aculta.toca.net.br | ativo |
| forum | growroom420.aculta.org | growroom420.aculta.toca.net.br | ativo (0.2.0-dev.57) |
| links | links.aculta.org | links.aculta.toca.net.br | Domain/purpose ativo; landing preservadora em `aculta_links`; Runtime de teste HTTP 200/404 confirmado; DNS de produção não provisionado |
| eventos | eventos.aculta.org | eventos.aculta.toca.net.br | ativo; ACULTA Studio Culturas e catálogo público |

## Vitrine do MAIN (2026-10-28)

**Comportamento anterior (medido no servidor de desenvolvimento em 2026-10-28):** cada conteúdo
respondia só no próprio host. Pelo `main`, `/verbete/...` da wiki respondia 403, `/noticias`
da revista e `/topico/...` do fórum respondiam 404, e `/apoio` respondia 404 em todos os hosts
(é rota interna). Só a raiz de cada host era acessível e o `main` não apresentava os subdomínios.

**Decisão do responsável (2026-10-28):** “aparecer no main” significa **vitrine**. A home do
`main` apresenta um link para a raiz de cada subdomínio com Domain ativo. O conteúdo de cada
subdomínio continua respondendo somente no próprio host.

**O que muda:**

- `DomainPresentationBuilder` monta `regions.showcase` (lista de `purpose`, `title`, `url`) só
  quando o purpose é `main` e a requisição é a home. Cada URL vem de `DomainPurposeManager::pathUrl`,
  então respeita o ambiente (teste ou produção). Purposes sem Domain ativo são omitidos.
- O tema (`page.html.twig`) renderiza a lista como `nav` com título “Ecossistema ACULTA”.
- Cacheability: cada Domain listado entra como dependência; o contexto `url.path.is_front` já existia.
- O gate `scripts/validate-domain-presentation-contract.php` passou a exigir a chave `showcase` e a
  checar que a vitrine só existe no `main`.

**O que não muda:**

- Acesso e roteamento por purpose (`DomainPurposeRequestSubscriber`, `DomainRoutePolicy`).
- Canonical: cada conteúdo continua com canonical no host dono, em produção.
- Conta, fluxo de apoio e checkout continuam nos seus purposes (decisões registradas acima).
- Sitemap: a vitrine não cria URLs novas.

O calendário no `main` segue como conteúdo de rota, conforme a decisão D6 de
`aculta_events`; a vitrine não cria atalhos nem canonicals adicionais. A página
de apresentação só reflete mudanças no checkout servido depois do rebuild de
cache dessa revisão.

### Decisão — resolução dos links de domínio no Portal (2026-10-31)

O `aculta_portal` é dono da lista de purposes, dos títulos e da resolução das
URLs absolutas. `DomainPresentationBuilder` usa `DomainPurposeManager` para
montar a vitrine da home MAIN a partir dos Domains configurados no ambiente;
inclui `magazine` (Revista 420) e `eventos` quando seus Domains estão ativos, e
omite Domains ausentes. O tema recebe somente o contrato neutro e apresenta os
links, sem hostname, route ID ou escolha de purpose. No Alpha, os hosts vêm da
configuração ativa do Domain/Deployer; não se troca URL em Twig ou CSS.

O teste Kernel `DomainPresentationBuilderKernelTest` protege a presença dos
dez purposes, URLs resolvidas por Domain e cache tags dos links da vitrine,
incluindo a Revista.

Fixtures de teste usam domínios reservados (`example.invalid`) ou obtêm os
hosts esperados do `DeployerHostDirectory`/`DomainPurposeManager`. Não fixar
hosts de Homelab ou produção em testes; a validação fica em
`scripts/validate-environment-addresses.php`.

Validação em 2026-10-31: o gate Domain Presentation no Runtime MAIN passou 249
checks; a vitrine ficou `DEFERRED` nessa execução porque Drush não cria uma
requisição roteada para a home. `DomainPresentationBuilderKernelTest` cobre a
vitrine com todos os Domains de teste. PHPCS/PHP lint passaram, o validador de
endereços passou em 6.999 arquivos e a suíte combinada passou 41 testes/669
assertions; Drupal/Core/dependências reportaram 92 depreciações, sem falhas.

## Redes sociais (LINKS)

- A URL pública planejada é a raiz `/` em `links.aculta.toca.net.br`. O front interno
  `/redes-sociais` é resolvido para essa raiz pelo `PurposeFrontPathProcessor`.
- Em 2026-10-20, o Runtime não tinha Node `page` com
  `field_domain_source = links_aculta_org` nem alias `/redes-sociais`; a raiz e o
  path direto retornavam 404 antes da restauração. A rota `/redes-sociais` agora
  tem fallback no `aculta_links`; após rebuild, HTTP da raiz confirmou 200 e o
  acesso direto 404. Produção não confirmada. Perfis são salvos em
  `aculta_portal.social_links` e editados em Administração > Configuração >
  ACULTA Portal > Redes sociais (`/painel-administrativo/aculta/redes-sociais`).
- A tela aceita somente URLs HTTPS de `instagram.com`, `youtube.com`/`youtu.be`
  e `facebook.com`/`fb.com`. Valores vazios ocultam o canal. Instagram vem do
  perfil de organizador citado abaixo; YouTube e Facebook ficam vazios até a
  confirmação dos endereços oficiais.
- Em Administração > Configuração > ACULTA Portal > Redes sociais, a mesma
  configuração permite cadastrar botões adicionais:
  cada botão tem texto e link. “Visite nosso Site” permanece fixo no topo; links
  adicionais aceitam HTTPS ou caminho interno começando com `/`.
- A lista de projetos usa a View `aculta_projects`, o teaser editorial existente
  e o estilo `views_vvjb`, com um item por vez em telas móveis.
- O botão principal “Visite nosso Site” aponta à raiz do purpose MAIN, resolvida
  pelo `DomainPurposeManager` para o ambiente atual; os canais sociais continuam
  no rodapé da página.
- O Instagram `coletivo_420` foi cadastrado porque aparece como site do
  organizador no [perfil institucional do Coletivo 420](https://coletivo420.org.br/organizador/coletivo-420/).
  Outros canais só entram após confirmação oficial.
- O ACULTA420 apresenta somente a barra institucional multidomínio, o pattern
  SDC mobile-first e um rodapé próprio desta página; o cabeçalho de domínio e o
  rodapé comuns ficam fora desse shell.
- Abaixo dos canais sociais, a página referencia os nós `project` publicados
  existentes. O Portal verifica acesso e monta URLs para MAIN com
  `DomainPurposeManager`, respeitando o host configurado em cada ambiente.
- DNS do servidor de desenvolvimento: CNAME `links.aculta.toca.net.br` →
  `aculta.toca.net.br`, sem proxy Cloudflare. A produção não recebeu registro DNS
  nesta configuração.
- O host de testes mantém `noindex, nofollow, noarchive` conforme a política de
  indexação do Homelab.

A sessão Drupal é compartilhada entre subdomínios da mesma raiz. Portanto todos
os hosts que recebem o cookie compartilhado pertencem ao mesmo trust boundary.

Canonicals usam produção; aliases Homelab não viram canonical.

Integrações de plataforma, como Google Analytics, devem distinguir
hostname/purpose sem introduzir snippets específicos em cada tema.

## Página de apoio (SUPPORT)

Decisão do responsável: o apoio fica vinculado somente aos subdomínios de apoio
(homologação `apoio.aculta.toca.net.br`, produção `apoio.aculta.org`). O domínio
principal não serve apoio.

- **Página pública:** é a página inicial do subdomínio de apoio, em `/`. Não existe
  página pública em `/apoio`.
- **Mecanismo:** o `front` do domínio de apoio aponta para `/apoio`, a rota interna do
  formulário (`aculta_portal.support_form`). Drupal não indexa rota com caminho `/`, por
  isso a rota é interna. `SupportFrontOnlySubscriber` responde 404 quando a requisição é
  `/apoio` direto, e serve a página somente quando o caminho original é `/`.
- **Configuração:** o `front` do domínio fica na coleção de configuração do domínio
  (`domain.apoio_aculta_org`, objeto `system.site`), exportada em
  `config/sync/domain/apoio_aculta_org/system.site.yml`.
- **Doações e apoio do usuário:** o controle de doações e o apoio de cada pessoa ficam
  em Minha Conta > Meu apoio (`aculta_portal.support_my`, purpose account). Não é uma
  página pública de apoio.
- **Referências a apoio** em qualquer host são encaminhadas para a página inicial do
  subdomínio de apoio (`PortalHooks`, para links internos `/apoio` e `/apoie`).
- **Pagamento:** o carrinho, o checkout e o pagamento continuam no `main` (ver
  `docs/portal/PAYMENT-DOMAIN-POLICY.md`).
- **Redirecionamentos:** não há redirecionamento para caminhos antigos até a versão
  estável.


## Loja (SHOP): onde cada página vive

- Página do produto (`entity.commerce_product.canonical`): purpose `shop`. Teste: `loja.aculta.toca.net.br`; produção: `loja.aculta.org`. No host principal responde 404.
- Carrinho e checkout: purpose `main` (`DomainRoutePolicy`). A compra começa no host da loja e segue para o carrinho do principal.
- Canonical do produto: sempre no host da loja (token absoluto da metatag, na requisição atual).
- Deployer: `domain_transition.shop` em `web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json`.

## ADR-038 - Suites Studio distribuídas por purpose (implementação em andamento)

Marley em `account:/marley`, Ganjalab em `courses:/ganjalab`, WE SATIVA em `wiki:/wesativa`, Green Stash em `shop:/greenstash` e Culturas em `eventos:/culturas`. Os paths e boundaries estão declarados; o módulo `aculta_studio` continua compartilhado e não publica `/studio` ou `/studio/*`. A raiz de `account` apresenta a entrada da Conta sem rewrite para Studio. As rotas nomeadas usam `DomainPurposeManager` para endereços absolutos; hosts não são codificados nas definições de rota.

A tabela acima descreve os hosts configurados por ambiente. O Domain canônico de Wiki permanece `wiki420`; não há troca para `wiki.aculta.*` no ADR-038. O endpoint de desenvolvimento `wiki420.aculta.toca.net.br` foi alcançado por HTTPS em 2026-10-30; o DNS de produção `wiki420.aculta.org` não foi resolvido neste ambiente e sua disponibilidade não está validada. Ver [ADR-038](../decisions/ADR-038-aculta-studio-suites-por-dominio.md).
