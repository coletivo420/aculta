# Política de domínio administrativo

Status: **implementada antes da ACULTA420 0.2-B.3; validação Runtime pendente**.

## Regra canônica

A administração Drupal da plataforma ACULTA é única e pertence ao purpose
`main`.

```text
MAIN / aculta.org
└── /painel-administrativo/**

ACCOUNT / conta.aculta.org
SUPPORT / apoio.aculta.org
MAGAZINE / coletivo420.aculta.org
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
- rotas técnicas explicitamente administrativas já conhecidas pelo Portal.

Essa classificação não muda access/permissão. Core/contrib continuam decidindo
se o usuário pode acessar a rota.

## Enforcement HTTP

`DomainPurposeRequestSubscriber` distingue dois tipos de wrong-purpose.

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

### Administração no purpose errado

Para navegação segura `GET`/`HEAD`:

```text
https://wiki420.aculta.org/painel-administrativo/conteudo
                           ↓
DomainPurposeManager::pathUrl('main', ...)
                           ↓
https://aculta.org/painel-administrativo/conteudo
```

Path e query string são preservados. O hostname nunca é montado manualmente.

Para métodos mutáveis como `POST`, `PUT`, `PATCH` e `DELETE`, não há
redirect cross-domain. O request falha fechado com 404. Um formulário
administrativo normal deve ter sido carregado originalmente em MAIN e,
portanto, submeter no mesmo host; repetir um request mutável em outro Domain
introduziria risco de semântica, sessão e CSRF.

## Edição e exclusão de conteúdo a partir do painel

- O painel é MAIN. A listagem de conteúdo gera o link "Editar" para o host de origem do conteúdo (revista, fórum, wiki,
  loja, links). Esse host não tem o formulário de administração: a navegação GET/HEAD é canonicalizada para MAIN.
- **Motivo da regra:** o Core (`RedirectResponseSubscriber`) substitui a URL de qualquer redirecionamento pelo parâmetro
  `destination` da requisição, resolvido no host atual. Por isso o Portal remove `destination` da requisição antes de
  canonicalizar e leva o retorno já absoluto para MAIN (`DomainPurposeRequestSubscriber::canonicalMainNavigationResponse`).
- Métodos que mudam estado (POST de exclusão) em host errado respondem 404 e não são redirecionados.
- A autoria nas listagens não linka para `/identidade/{usuário}` (página pública da Conta). O item "Editar perfil" do
  painel aponta para `aculta_portal.admin_self_edit` (`/painel-administrativo/perfil/editar`), que leva ao formulário do
  próprio usuário em MAIN.

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
- usar a canonicalização administrativa para contornar permission/access.

## Gate

A política deve provar:

1. todas as rotas `_admin_route` pertencem a `main`;
2. todas as rotas sob `/painel-administrativo/**` pertencem a `main`;
3. o subscriber possui uma única política de canonicalização administrativa;
4. URL MAIN é construída por `DomainPurposeManager`;
5. GET/HEAD podem redirecionar;
6. métodos mutáveis permanecem fail-closed;
7. query string é preservada;
8. reset Core permanece explicitamente excepcionado;
9. wrong-purpose público continua 404;
10. ACULTA420 não participa da política.
