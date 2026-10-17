# Política de requests e redirects cross-domain

Status: **hardening pré-ACULTA420 0.2-B.3; validação Runtime pendente**.

Este contrato complementa `ADMIN-DOMAIN-POLICY.md` e cobre toda transição
intencional entre purposes ACULTA.

## Princípio

Subdomínios ACULTA são hosts distintos, mesmo quando pertencem à mesma
instalação Drupal. Portanto, um redirect entre purposes é tratado como
cross-host e precisa ser explícito.

```text
request mutável
    └── nunca é replayado por redirect cross-domain

navegação GET/HEAD
    └── pode atravessar purpose quando a política funcional exige

redirect cross-domain intencional
    └── URL criada por DomainPurposeManager
        + TrustedRedirectResponse
```

## Redirects seguros no Drupal

Drupal converte redirects comuns para sua resposta local segura e rejeita
targets externos ao host atual. Quando a aplicação conhece e confia
explicitamente no target, deve usar `Drupal\Core\Routing\TrustedRedirectResponse`.

No ACULTA:

- `Symfony\Component\HttpFoundation\RedirectResponse` não é suficiente para
  redirect intencional entre subdomínios;
- a URL confiável nunca vem diretamente de query/string do usuário;
- o target deve ser produzido por `DomainPurposeManager`;
- `destination` continua aceitando somente path interno, nunca URL externa;
- o purpose de retorno precisa existir no mapa Domain antes de ser salvo/usado.

## Ordem de response subscribers

`DomainPurposeRequestSubscriber::onResponse()` altera o destino depois de
login/OAuth/logout. Essa operação precisa acontecer **antes** do
`RedirectResponseSubscriber` do Core.

A prioridade ACULTA é `1`; o Core usa a prioridade padrão `0`.

Assim o Core recebe uma `TrustedRedirectResponse` já final e pode executar sua
camada de segurança normalmente. Não depender de empate de prioridade/module
weight.

## Conta

`AccountRouteSubscriber` pode encaminhar a rota administrativa genérica de
edição do próprio usuário para a Segurança da Conta.

Como a rota genérica é MAIN e Segurança é ACCOUNT:

- GET/HEAD pode navegar MAIN → ACCOUNT;
- o target é gerado por `DomainPurposeManager`;
- usa `TrustedRedirectResponse`;
- POST/PUT/PATCH/DELETE não atravessam o Domain: falham fechado com 404,
  inclusive quando o Router reconhece o path, mas não aceita aquele método;
- reset one-time válido do Core permanece no fluxo técnico ACCOUNT já
  autorizado.

`PortalController::legacyUserPageRedirect()` também usa
`TrustedRedirectResponse`, pois `user.page` pode ser gerada enquanto o host
atual é MAIN e seu destino público é a raiz ACCOUNT.

## Login e OAuth

`destination` nunca representa um host. O Portal armazena separadamente:

```text
purpose
path
query
```

O path:

- começa com exatamente uma barra;
- não contém scheme/host/user/pass/port/fragment;
- não pode apontar para rotas de login/logout/OAuth/reset bloqueadas.

O purpose precisa resolver para um Domain conhecido.

Depois da autenticação, o URL absoluto é reconstruído por
`DomainPurposeManager::pathUrl()` e aplicado como target confiável. Headers,
cookies e status do redirect original são preservados.

## Sessão compartilhada

A aplicação espera que autenticação continue válida ao navegar entre purposes.

Drupal, por padrão, deriva o cookie de sessão do hostname. Para compartilhar a
mesma sessão entre subdomínios da mesma instalação, cada ambiente deve definir
`session.storage.options.cookie_domain` para seu domínio-base compartilhado.

Exemplos operacionais:

```yaml
# Produção
parameters:
  session.storage.options:
    cookie_domain: '.aculta.org'
    cookie_samesite: Lax
```

```yaml
# Homelab (exemplo; usar o domínio-base real do ambiente)
parameters:
  session.storage.options:
    cookie_domain: '.toca.net.br'
    cookie_samesite: Lax
```

Esse valor é configuração de ambiente; não hardcodar o domínio Homelab em
código funcional.

`SameSite=Lax` permanece o baseline porque permite o retorno top-level GET de
OAuth externo sem abrir a política mais ampla de `SameSite=None`.

## AJAX/fetch

AJAX do Portal permanece **same-origin**. Ele não é mecanismo de integração
entre purposes.

- fetch/XHR não deve transportar formulário ou credenciais para outro purpose;
- link cross-purpose deve fazer navegação normal;
- CORS não deve ser habilitado para contornar a arquitetura;
- mudanças futuras em Account AJAX devem provar explicitamente mesma origin
  antes de interceptar links.

## Commerce, checkout e webhooks

O contrato detalhado está em `PAYMENT-DOMAIN-POLICY.md`.

- COURSES, SHOP e SUPPORT podem originar intenção comercial, mas carrinho/checkout/pagamento pertencem a MAIN;
- `commerce_checkout.*` e `commerce_payment.checkout.*` são ownership MAIN; `aculta_donation.*` é ownership SUPPORT (decisão de 2026-10-17); o gateway (retorno e notificação) é gerado no MAIN;
- links conhecidos pelo Portal devem apontar diretamente para MAIN, evitando redirect corretivo desnecessário;
- formulário mutável só deve ser exibido em MAIN; POST wrong-host falha fechado;
- `commerce_payment.notify` pertence a MAIN e é POST-only;
- assinatura é validada antes do contrib consumir a notificação;
- webhook nunca é canonicalizado/redirecionado para outro host;
- return/cancel browser-facing do gateway permanecem no fluxo Commerce, mas no Domain MAIN.

## Anti-regressão

Nunca:

- usar redirect cross-domain para POST/PUT/PATCH/DELETE;
- aceitar host completo em `destination`;
- montar host por concatenação;
- usar redirect Symfony cru para target Domain-managed em outro host;
- depender de empate de priority entre subscribers;
- habilitar CORS para fazer o Portal conversar entre purposes;
- assumir sessão compartilhada sem validar `cookie_domain`;
- transformar webhooks em redirect;
- mover essas decisões para tema/Twig/JavaScript.
