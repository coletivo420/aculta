# Documentação do Portal

Índice do `aculta_portal`. Só documentos normativos e de referência vigentes.

## Normas

- [DRUPAL-11-STANDARDS.md](DRUPAL-11-STANDARDS.md) — padrão Drupal 11+ e regras de código.
- [GUARDRAILS.md](GUARDRAILS.md) — regras de Fórum, Revista, Loja, Wiki, Conta e AJAX.
- [EMAIL-TRANSPORT.md](EMAIL-TRANSPORT.md) — migração do envio de e-mail para Mailer Plus (M0 decisão e plano; M1 e M2 pendentes).
- [SOURCE-OF-TRUTH.md](SOURCE-OF-TRUTH.md) — fonte de verdade de cada dado.
- [ARCHITECTURE.md](ARCHITECTURE.md) — papel do módulo e fronteiras.
- [AGENT-TOKEN-ECONOMY.md](AGENT-TOKEN-ECONOMY.md) — roteamento de modelos para agentes.
- [ADR-009 — sitemap multidomínio](../decisions/ADR-009-sitemap-multidominio.md) — submódulo `aculta_portal_sitemap` (linha 0.1.x, opt-in).
- [ACULTA ID CPF](../modules/ACULTA-ID-CPF.md) — submódulo opcional de validação CPF/CNPJ; primeira entrega em draft, Runtime DEFERRED.

## Políticas

- [ADMIN-DOMAIN-POLICY.md](ADMIN-DOMAIN-POLICY.md) — painel administrativo e canonicalização para MAIN.
- [CROSS-DOMAIN-REQUEST-POLICY.md](CROSS-DOMAIN-REQUEST-POLICY.md) — requisições entre purposes.
- [PAYMENT-DOMAIN-POLICY.md](PAYMENT-DOMAIN-POLICY.md) — carrinho, checkout e pagamento no MAIN.
- [FRIENDLY-PORTUGUESE-SLUGS.md](FRIENDLY-PORTUGUESE-SLUGS.md) — URLs e slugs em português.
- [TURNSTILE.md](TURNSTILE.md) — anti-bot: pontos de CAPTCHA, chaves de teste e produção, caixa de ativação.
- [ACCOUNT-MENU.md](ACCOUNT-MENU.md) — menu da Minha conta: lista no desktop e sanfona no celular.
- [CHECKOUT-BILLING.md](CHECKOUT-BILLING.md) — endereço único (cobrança, envio e Mercado Pago), meios de pagamento e campos do endereço.
- [SENHA-CONTA-OAUTH.md](SENHA-CONTA-OAUTH.md) — criação da primeira senha em conta criada com Google (Social Auth).
- [EMAIL-CONFIRMATION-POLICY.md](EMAIL-CONFIRMATION-POLICY.md) — confirmação de e-mail: quando a conta fica bloqueada e como é liberada.
- [REDIRECTS.md](REDIRECTS.md) — redirecionamentos mantidos pelo Portal (rotas antigas e slugs).
- [SEARCH.md](SEARCH.md) — busca pública e do painel por purpose.

## Contratos

- [DOMAIN-PRESENTATION-CONTRACT.md](DOMAIN-PRESENTATION-CONTRACT.md) — contrato Portal → tema.
- [COMPONENT-DESIGN-SYSTEM.md](COMPONENT-DESIGN-SYSTEM.md) — componentes e Bootstrap.
- [AJAX.md](AJAX.md) — política de AJAX e interatividade.
- [FRONTEIRA-MODULO-TEMA.md](FRONTEIRA-MODULO-TEMA.md) — fronteira entre `aculta_portal` (função) e ACULTA420 (design); contrato `data-portal-ui`.

## Testes e pendências

- [TESTES-NAVEGADOR.md](TESTES-NAVEGADOR.md) — rodadas de teste no navegador com o responsável: hosts, contas e resultados.
- [PENDENCIAS-PROXIMA-RODADA.md](PENDENCIAS-PROXIMA-RODADA.md) — pendências abertas do checkout, pagamento e Minha conta.

## Roadmap

- [ROADMAP.md](ROADMAP.md) — saneamento e features de produto.
