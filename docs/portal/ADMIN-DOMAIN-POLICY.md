# Política de domínio administrativo

Estado dos componentes: [aculta_portal](../operations/status/aculta_portal.yml) e [ACULTA420](../operations/status/aculta420.yml). Este documento registra o contrato da política.

## Regra canônica

A administração Drupal da plataforma ACULTA é única e pertence ao purpose
`main`.

```text
MAIN / aculta.org
└── /painel-administrativo/**

ACCOUNT / conta.aculta.org
SUPPORT / apoio.aculta.org
MAGAZINE / revista.aculta.org
WIKI / wiki420.aculta.org
SHOP / loja.aculta.org
COURSES / cursos.aculta.org
└── não possuem painel administrativo próprio
```

Em ambientes com Domain Alias, `main` resolve para o alias MAIN daquele
ambiente. Código funcional nunca hardcoda `aculta.org` ou hostname Homelab.

## Classificação de rotas

`DomainRouteSubscriber` é responsável por marcar como `main`:

- toda rota com `_admin_route`;
- toda rota cujo path final seja `/painel-administrativo` ou esteja abaixo
  desse prefixo;
- toda rota cujo path seja `/integracoes` ou esteja abaixo desse prefixo
  (OpenStreetMap, Melhor Envio, Mercado Pago, MCP e integrações futuras);
- rotas técnicas explicitamente administrativas já conhecidas pelo Portal.

Essa classificação não muda access/permissão. Core/contrib continuam decidindo
se o usuário pode acessar a rota.

## Enforcement HTTP

`DomainPurposeRequestSubscriber` distingue dois tipos de wrong-purpose.

Páginas canônicas de conteúdo têm o purpose resolvido pela entidade carregada.
Além de Nodes e termos, `entity.media.canonical` resolve `forum_attachment`
como `forum`; assim, a entidade Media recebe o mesmo 404 fail-closed quando
solicitada em outro purpose. O download privado continua exigindo o access do
Media, do campo e do File.

### Conteúdo/feature pública no purpose errado

Exemplos:

```text
COURSES route em WIKI
WIKI route em MAGAZINE
ACCOUNT route em SUPPORT
```

Resultado:

```text
404
Cache-Control: private, no-store
X-Robots-Tag: noindex, nofollow
```

A política fail-closed existente permanece.

### Administração e integrações no purpose errado

Rotas administrativas e de integração existem somente no MAIN. Em qualquer
outro purpose, elas respondem **404** para todos os métodos, sem redirect:

```text
https://wiki420.aculta.org/painel-administrativo/conteudo      → 404
https://eventos.aculta.org/painel-administrativo/eventos        → 404
https://eventos.aculta.org/integracoes/openstreetmap/autorizar  → 404
```

`DomainPurposeRequestSubscriber` trata `/painel-administrativo/**` e
`/integracoes/**` como áreas administrativas e fail-closed. Não há
canonicalização cross-domain de rota administrativa. O
`canonicalMainNavigationResponse` permanece apenas para navegação MAIN-owned
não administrativa marcada com `_aculta_cross_domain_canonical_purpose`.

## Edição e exclusão de conteúdo a partir do painel

- O painel é MAIN. Links administrativos de edição, exclusão e configuração
  gerados na listagem apontam para o host MAIN; não se gera link
  administrativo para o host de origem do conteúdo.
- Requisições a rotas administrativas em qualquer outro host respondem 404,
  inclusive GET/HEAD e POST.
- O tema não expõe affordance administrativa em subdomínio:
  `AppearanceHooks` remove o toolbar do Navigation e o hook
  `contextual_links_view_alter` remove os links contextuais fora do MAIN.
- A autoria nas listagens não linka para `/identidade/{usuário}` (página
  pública da Conta). O item "Editar perfil" do painel aponta para
  `aculta_portal.admin_self_edit` (`/painel-administrativo/perfil/editar`),
  que leva ao formulário do próprio usuário em MAIN.

## Exceção técnica: reset de senha

Drupal Core reutiliza `entity.user.edit_form`, cuja representação administrativa
é `/painel-administrativo/pessoas/{user}/editar`, durante o fluxo de reset de
senha.

Isso **não transforma ACCOUNT em domínio administrativo**.

A exceção só é aceita quando:

- a rota é `entity.user.edit_form`;
- o purpose corrente é `account`;
- o UID corresponde ao fluxo;
- `AccountRouteSubscriber::isValidCorePasswordResetRequest()` confirma o
  token one-time/session-bound do Core.

A mesma regra é aplicada tanto antes quanto depois do RouterListener para que o
primeiro estágio de wrong-host não mate o fluxo legítimo.

## Separação de responsabilidades

```text
DomainRouteSubscriber
    classifica rota → main

DomainPurposeRequestSubscriber
    aplica canonicalização ou fail-closed

DomainPurposeManager
    resolve URL/alias do purpose MAIN

Core/contrib access
    decide autorização

ACULTA420
    não participa desta política
```

O tema nunca decide domínio administrativo, redirect, access ou hostname.

## Anti-regressão

Nunca:

- publicar um painel administrativo independente em subdomínio;
- hardcodar `aculta.org` em redirects;
- gerar URL MAIN concatenando scheme/host manualmente;
- transformar todo wrong-purpose em redirect;
- redirecionar métodos mutáveis cross-domain;
- remover a exceção de reset sem substituir o fluxo Core;
- mover essa política para Twig, tema ou JavaScript;
- usar canonicalização administrativa para contornar permission/access;
- publicar rota `/integracoes/**` em subdomínio ou canonicalizá-la com 302;
- declarar `_aculta_domain_purpose_authoritative` para rota administrativa;
- renderizar toolbar, links contextuais ou ações editoriais em subdomínio.

## Gate

A política deve provar:

1. todas as rotas `_admin_route` pertencem a `main`;
2. todas as rotas sob `/painel-administrativo/**` pertencem a `main`;
3. o subscriber possui uma única política de canonicalização administrativa;
4. URL MAIN é construída por `DomainPurposeManager`;
5. GET/HEAD podem redirecionar;
6. métodos mutáveis permanecem fail-closed;
7. query string é preservada para navegação não administrativa;
8. reset Core permanece explicitamente excepcionado;
9. wrong-purpose público continua 404;
10. ACULTA420 não participa da política;
11. todas as rotas `/integracoes/**` pertencem a MAIN e respondem 404 fora dele;
12. nenhuma rota administrativa declara purpose diferente de `main` nem o opt-in
    `_aculta_domain_purpose_authoritative`.

Gate: `php scripts/validate-admin-routes-main-domain.php`.
Testes: `EventDomainBoundaryKernelTest` (rotas, 404 e bloco),
`DomainRouteSubscriberIntegrationPurposeTest` (integrações em MAIN) e
`DomainRoutePolicyTest`.
