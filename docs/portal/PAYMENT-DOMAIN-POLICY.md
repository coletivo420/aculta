# Política do domínio de checkout e pagamentos

Status: **política implementada e gates estruturais PASS; fluxo funcional Commerce bloqueado pela configuração atual do Homelab**.

## Regra canônica

Origem comercial e domínio de pagamento são responsabilidades diferentes.

COURSES, SHOP e SUPPORT podem iniciar uma intenção de compra/apoio, mas todo
carrinho, checkout e pagamento Drupal Commerce pertence ao purpose `main`.

```text
COURSES → intenção de comprar curso ─┐
SHOP    → intenção de comprar item  ─┼→ MAIN → carrinho → checkout → pagamento → conclusão
SUPPORT → intenção de apoiar        ─┘
```

Os subdomínios não mantêm checkout ou processador de pagamento próprio.

Em produção, MAIN resolve para `aculta.org`. Em Homelab, resolve para o alias
MAIN do ambiente via `DomainPurposeManager`. Nenhum hostname é hardcoded.

## Rotas centrais

`DomainRoutePolicy::isCentralTransactionRouteName()` é a fonte única para famílias
de rotas centrais de carrinho/checkout/pagamento que pertencem a MAIN:

- `commerce_cart.*`;
- `commerce_checkout.*`;
- `commerce_payment.checkout.*`;
- `commerce_payment.notify`.

O fluxo de apoio e assinatura (`aculta_donation.*`) pertence a SUPPORT (ver "Donation Flow").
A política é reutilizada por:

- `DomainRouteSubscriber`, para classificar as rotas e anotar navegação browser-facing com `_aculta_cross_domain_canonical_purpose=main`;
- `PortalHooks`, para reescrever links renderizados diretamente para MAIN.

`DomainRoutePolicy` também classifica as rotas transacionais como `noindex,
nofollow` e remove canonical/OG URL dessas páginas. `commerce_payment.*` e as
rotas de entidade de order são classificadas para SEO privado sem repetir
prefixos Commerce no hook de metatags.

`ContentPurposeResolver` e `DomainPurposeRequestSubscriber` não reavaliam nomes `commerce_*`: eles consomem a metadata já gravada na rota. Isso mantém classificação e enforcement desacoplados e segue o padrão Drupal de alterar metadata de rotas em `RouteSubscriberBase`.

Não duplicar listas de rotas em outros arquivos.

## Entrada no checkout

Links de checkout/pagamento devem apontar diretamente para MAIN quando o Portal
os conhece como `Url` roteada.

Isso evita:

```text
SUPPORT/SHOP/COURSES
      ↓
checkout relativo no subdomínio
      ↓
302 corretivo
      ↓
MAIN
```

e prefere:

```text
SUPPORT/SHOP/COURSES
      ↓
link gerado para MAIN pelo Portal
      ↓
MAIN checkout
```

O redirect de canonicalização continua como defesa para URLs antigas, links
contrib que escapem da reescrita ou acesso manual.

Parâmetros de rota e query string são preservados. Host completo recebido do
usuário nunca é reaproveitado.

## Requests mutáveis

O checkout e a UI de gerenciamento do carrinho devem começar em MAIN antes de seus formulários mutáveis.

Isso não proíbe o `Add to cart` nativo na página de produto/curso em SHOP/COURSES: essa mutação faz parte da seleção comercial e usa a API/Form API do Commerce para atualizar a mesma order de carrinho.

Se um `POST`, `PUT`, `PATCH` ou `DELETE` de uma rota central `commerce_cart.*`, `commerce_checkout.*` ou payment chegar em outro purpose, ele **não é redirecionado**. O request falha fechado.

Isso também vale quando o método não é aceito pela própria rota. O Portal consulta
os candidatos de path do Route Provider e usa a metadata de ownership já aplicada
à rota; em purpose incorreto, responde `404` com `Cache-Control: private,
no-store`. Não mantém uma segunda lista de famílias Commerce no request subscriber.

Não usar 307/308 para transferir corpo/método entre hosts.

Formulários carregados corretamente em MAIN submetem ao próprio MAIN por fluxo
normal do Drupal Commerce/Form API.

## Retorno/cancelamento de gateway

Rotas browser-facing de gateway, incluindo
`commerce_payment.checkout.return` e
`commerce_payment.checkout.cancel`, pertencem a MAIN.

Como o formulário de pagamento é construído em MAIN, Commerce/gateway deve
gerar return/cancel URLs a partir do contexto MAIN. O Portal não substitui a
lógica de gateway; apenas garante o ownership de Domain.

Retornos externos legítimos podem usar GET conforme contrato do gateway e
continuam no fluxo Commerce nativo.

## Webhook

`commerce_payment.notify`:

- pertence a MAIN;
- aceita somente POST;
- é machine-to-machine;
- nunca é redirecionado cross-domain;
- wrong-host falha fechado;
- assinatura continua validada pelo `WebhookGuard` antes do contrib.

Webhook não é navegação de usuário e não participa da canonicalização GET/HEAD.

## Carrinho e catálogo

O carrinho é centralizado em MAIN junto com checkout e pagamento.

SHOP e COURSES mantêm descoberta, catálogo e página de produto/curso nos seus
purposes. O formulário nativo `Add to cart` continua podendo ser submetido na página do produto/curso, pois no Drupal Commerce ele é um formulário do order item que cria/atualiza a mesma `commerce_order` de carrinho. Depois disso, ver/editar o carrinho navega para MAIN.

A entidade/order continua sendo do Drupal Commerce; não existe storage paralelo
por purpose. A centralização é de ownership de rota/experiência, não duplicação
de dados.

No futuro, a barra multidomínio poderá exibir um ícone de carrinho em qualquer
purpose. O desenho preferencial é um `CartPresentationBuilder` no Portal baseado em `CartProviderInterface`, preservando o cache context `cart` e as dependências das orders, enquanto `DomainPurposeManager::routeUrl('main', 'commerce_cart.page')` fornece a URL absoluta MAIN. O Cart Block/lazy builder do Commerce pode servir de referência ou renderable interno, mas não deve ser usado cru na barra multidomínio porque sua URL padrão é gerada no host corrente. O Portal entrega um contrato neutro com URL MAIN e estado/contagem autorizada; o tema não consulta Commerce diretamente, não calcula contagem, não resolve hostname e não cria URL do carrinho por conta própria.

Essa futura UI pertence à evolução do shell multidomínio; não é implementada
nesta correção.

## Donation Flow

A página institucional de apoio pode permanecer em SUPPORT. Informado pelo
responsável: a página de apoio fica a cargo do subdomínio da plataforma de doações
(`apoio.aculta.toca.net.br` no homelab, `apoio.aculta.org` em produção); o host
principal não serve a rota de apoio (ver `docs/architecture/multidomain.md`).

Decisão do responsável (2026-10-17): o fluxo inteiro de apoio e assinatura
(`aculta_donation.*`) roda no purpose SUPPORT, no subdomínio de apoio. Doações e
assinaturas não têm estorno; a assinatura terá cancelamento.

Só o gateway fica no MAIN: a notificação do Mercado Pago (`commerce_payment.notify`)
e o retorno e cancelamento do pagamento (`commerce_payment.checkout.*`). A regra
está em `Routing\PaymentDomainPathProcessor`, que gera essas URLs com o domínio
principal, qualquer que seja o host da requisição. A sessão atravessa os dois
hosts pelo `cookie_domain` do ambiente (`services.homelab.yml` no Homelab), e o
retorno do navegador volta ao MAIN com essa sessão.

A loja (carrinho, checkout e pagamento de produtos) continua no MAIN.

## Segurança

A centralização não altera autorização, ownership de order nem validação de
gateway. Core/contrib permanecem fontes de verdade.

Nunca:

- duplicar checkout por purpose;
- persistir pagamento fora do Commerce;
- mover lógica de gateway para Portal/theme;
- usar hostname hardcoded;
- replayar POST entre Domains;
- transformar webhook em redirect;
- confiar em query para escolher hostname;
- habilitar CORS para compartilhar checkout;
- alterar order/payment para “corrigir” Domain.

## Gate

Antes da B.3, validar:

1. todas as rotas reconhecidas pela política têm `_aculta_domain_purpose=main`;
2. `commerce_cart.page`, `commerce_checkout.checkout` e `commerce_checkout.form` existem e são MAIN;
3. return/cancel de pagamento, quando existentes, são MAIN;
4. Donation Flow instalado expõe somente rotas centrais marcadas MAIN;
5. notify é MAIN e POST-only;
6. GET/HEAD de checkout em purpose secundário canonicaliza para MAIN;
7. POST, PUT, PATCH e DELETE em rota central/purpose secundário falham fechado,
   inclusive quando o método seria rejeitado pelo Router;
8. links Portal para checkout apontam diretamente a MAIN;
9. query/route parameters são preservados;
10. checkout normal em MAIN continua funcional;
11. return/cancel externo continua funcional;
12. nenhuma mudança de tema é necessária.

## Resultado operacional do Homelab — 2026-10-08

No Homelab Apache/PHP-FPM, os gates de política, domínio, Foundation,
apresentação e instituição passaram. A matriz HTTP confirmou que GET/HEAD de
`/carrinho` em SHOP redirecionam para MAIN, preservando query string; POST/PUT/PATCH/
DELETE no host errado falham com `404` e `Cache-Control: private, no-store`.
As rotas Commerce inventariadas têm metadata MAIN; `commerce_payment.notify` é
POST-only e não recebe canonicalização browser-facing.

A validação funcional de compra permanece bloqueada pelo estado do Runtime
observado nesta data:

- `commerce_product` está desabilitado; não há entidades de produto/variação nem
  orders para testar Add to cart, identidade do carrinho ou checkout;
- Commerce Donation Flow está configurado em modo `donate`, que desabilita as
  rotas genéricas `/carrinho` (carrinho, antes `/cart`) e `/checkout`; ambas respondem `403` em MAIN;
- a rota `/apoiar` (antes `/donate`) redireciona de SUPPORT para MAIN, mas o acesso anônimo é
  negado pela permissão `make donation`; `/apoiar` responde `403` em MAIN;
- SHOP ainda não apresenta catálogo público e sua raiz responde `404`.

Por isso Add to cart, persistência/continuidade da mesma order, checkout
funcional, pagamento e retorno/cancelamento de gateway não foram comprovados no
Runtime. Esses resultados não são apresentados como PASS. A configuração
comercial não foi alterada nesta validação; antes de declarar o fluxo operacional,
é necessário disponibilizar o catálogo/configuração de compra e aprovar o acesso
público ao fluxo de doação, então repetir os testes de ponta a ponta. Produção
permaneceu intocada.
