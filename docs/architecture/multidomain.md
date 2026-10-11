# Multidomínio

Purposes estáveis atuais:

`main`, `account`, `support`, `magazine`, `wiki`, `shop`, `courses`.

Purpose ativo desde 0.2.0-dev.57:

`forum`.

O purpose `forum` foi implementado com os módulos `forum` e `flag`. A condição
anterior (validação da feature de participação, F3) foi substituída por decisão
do responsável em 2026-10-11, que pediu a raiz do host do fórum servindo `/forum`.

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
| magazine | coletivo420.aculta.org | revista420.aculta.toca.net.br | ativo |
| wiki | wiki420.aculta.org | wiki420.aculta.toca.net.br | ativo |
| shop | loja.aculta.org | loja.aculta.toca.net.br | Domain ativo; catálogo futuro |
| courses | cursos.aculta.org | cursos.aculta.toca.net.br | ativo |
| forum | forum.aculta.org | forum.aculta.toca.net.br | ativo (0.2.0-dev.57) |

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
