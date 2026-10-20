# Multidomínio

Purposes estáveis atuais:

`main`, `account`, `support`, `magazine`, `wiki`, `shop`, `courses`, `links`.

Purpose ativo desde 0.2.0-dev.57:

`forum`.

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
| magazine | revista420.aculta.org | revista420.aculta.toca.net.br | ativo |
| wiki | wiki420.aculta.org | wiki420.aculta.toca.net.br | ativo |
| shop | loja.aculta.org | loja.aculta.toca.net.br | Domain ativo; catálogo futuro |
| courses | cursos.aculta.org | cursos.aculta.toca.net.br | ativo |
| forum | growroom420.aculta.org | growroom420.aculta.toca.net.br | ativo (0.2.0-dev.57) |
| links | links.aculta.org | links.aculta.toca.net.br | Domain/purpose ativo; landing preservadora em `aculta_links`; Runtime de teste HTTP 200/404 confirmado; DNS de produção não provisionado |

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
- DNS do servidor de testes: CNAME `links.aculta.toca.net.br` →
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
