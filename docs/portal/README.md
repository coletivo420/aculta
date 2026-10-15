# Documentação do Portal

Índice do `aculta_portal`. Só documentos normativos e de referência vigentes.

## Normas

- [DRUPAL-11-STANDARDS.md](DRUPAL-11-STANDARDS.md) — padrão Drupal 11+ e regras de código.
- [GUARDRAILS.md](GUARDRAILS.md) — regras de Fórum, Revista, Loja, Wiki, Conta e AJAX.
- [IMPLEMENTATIONS.md](IMPLEMENTATIONS.md) — inventário de implementações, helpers e integrações aplicadas pelo Portal.
- [EMAIL-TRANSPORT.md](EMAIL-TRANSPORT.md) — envio de e-mail pelo Mailer Plus (M0 a M2 concluídas; validado no runtime de teste).
- [SOURCE-OF-TRUTH.md](SOURCE-OF-TRUTH.md) — fonte de verdade de cada dado.
- [ARCHITECTURE.md](ARCHITECTURE.md) — papel do módulo e fronteiras.
- [AGENT-TOKEN-ECONOMY.md](AGENT-TOKEN-ECONOMY.md) — roteamento de modelos para agentes.
- [ADR-009 — sitemap multidomínio](../decisions/ADR-009-sitemap-multidominio.md) — submódulo `aculta_portal_sitemap` (linha 0.1.x, opt-in).
- [ACULTA Associado ID](../modules/ACULTA-ASSOCIADO-ID.md) — escopo de dados dos associados, compatibilidade e migração controlada; Runtime DEFERRED.
- [ACULTA Gallery — arquitetura](../../web/modules/custom/aculta_portal/modules/aculta_gallery/docs/ARQUITETURA.md) — álbuns e galerias incorporadas (Media Gallery, PhotoSwipe, Entity Embed): decisões, limitações comprovadas e contratos (linha 0.1.x, submódulo opcional).
- [ACULTA Messenger: documentação do projeto](MESSENGER.md) — módulo, submódulo, rotas, hooks, classes e contratos com o tema.
- [Tipos de conversa do Messenger: grupos, fórum, LMS, retenção e integrações](MESSENGER-CONVERSATION-KINDS.md) — base preparada, decisões pendentes e fases.
- [Auditoria do Messenger](MESSENGER-AUDIT.md) — separação entre módulo, submódulo e tema, com os achados.
- [ACULTA Messenger](../../web/modules/custom/aculta_portal/modules/aculta_messenger/README.md) — integração de mensagens, modais, avisos persistentes segmentados e estado de leitura individual.

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
- [WIKI.md](WIKI.md) — conteúdo, autoria, permissões e apresentação da Wiki420.

## Contratos

- [DOMAIN-PRESENTATION-CONTRACT.md](DOMAIN-PRESENTATION-CONTRACT.md) — contrato Portal → tema.
- [COMPONENT-DESIGN-SYSTEM.md](COMPONENT-DESIGN-SYSTEM.md) — componentes e Bootstrap.
- [AJAX.md](AJAX.md) — política de AJAX e interatividade.
- [FRONTEIRA-MODULO-TEMA.md](FRONTEIRA-MODULO-TEMA.md) — fronteira entre `aculta_portal` (função) e ACULTA420 (design); contrato `data-portal-ui`.

## Estado e pendências

- [RELATORIO-ESTADO-APOS-PR-238-239.md](RELATORIO-ESTADO-APOS-PR-238-239.md) — estado corrente após os merges #238 e #239, com verificações locais e limites de Runtime.
- [historico/RELATORIO-ESTADO-2026-10-12.md](historico/RELATORIO-ESTADO-2026-10-12.md) — snapshot histórico anterior às PRs #238 e #239.
- [ROADMAP.md](ROADMAP.md) — fases de saneamento e de produto.

## Testes e pendências

- [TESTES-NAVEGADOR.md](TESTES-NAVEGADOR.md) — rodadas de teste no navegador com o responsável: hosts, contas e resultados.
- [historico/PENDENCIAS-PROXIMA-RODADA.md](historico/PENDENCIAS-PROXIMA-RODADA.md) — pendências abertas do checkout, pagamento e Minha conta.

## Roadmap

- [ROADMAP.md](ROADMAP.md) — saneamento e features de produto.

## Projetos aprovados — 2026-10-15

- [ACULTA Fórum / Growroom420](../../web/modules/custom/aculta_portal/modules/aculta_forum/README.md)
  — reconstrução do acervo, autores individuais no memorial coletivo, prefixo
  `growroom420`, conversas atuais vinculadas e integrações do Portal.
- [ACULTA Game](../../web/modules/custom/aculta_portal/modules/aculta_game/README.md)
  — conquistas e missões sem ranking ou benefícios financeiros; exibição pública
  por padrão com privacidade e destaques; pesquisa de módulos e licenças.

Fórum permanece documental. Game é instalável desde G1 e integra o backend de
conquistas do User. Seus READMEs separam o estado do código, as decisões aprovadas
e as fases de implementação.
