# Documentação do ACULTA

Documentação normativa do projeto. Só guardrails e referências vigentes: regras que bloqueiam
regressão, políticas e procedimentos de operação. Histórico de fases, relatórios e planos
concluídos não ficam aqui; estão no Git (ver [DOCUMENTATION.md](DOCUMENTATION.md)).

## Começar

- [AGENTS.md](../AGENTS.md) — instruções para agentes e humanos (raiz do repositório).
- [DOCUMENTATION.md](DOCUMENTATION.md) — política desta documentação: o que é guardrail e o que sai.
- [operations/DEBT-REGISTER.md](operations/DEBT-REGISTER.md) — dívidas e pendências, com evidência.

## Arquitetura e decisões

- [architecture/overview.md](architecture/overview.md) — camadas Core → Portal → tema → Bootstrap.
- [architecture/multidomain.md](architecture/multidomain.md) — purposes, hosts e cookie compartilhado.
- [architecture/environments.md](architecture/environments.md) — Homelab Apache e produção Hostinger LiteSpeed.
- [architecture/data-ownership.md](architecture/data-ownership.md) — quem é dono de cada dado.
- [decisions/](decisions/) — ADRs vigentes.
- [ADR-010](decisions/ADR-010-hostinger-litespeed.md) — LiteSpeed na produção Hostinger Cloud Startup; Apache no Homelab.

## Módulos e integrações

- [modules/README.md](modules/README.md) — referência canônica dos módulos e das regras anti-regressão.
- [modules/LMS-GROUP.md](modules/LMS-GROUP.md) — ownership e inventário do Drupal LMS/Group.
- [modules/ACULTA-LMS-ROADMAP.md](modules/ACULTA-LMS-ROADMAP.md) — fases futuras, critérios de aceite e rollback; F0 documental.
- [ANTI-REGRESSION.md](ANTI-REGRESSION.md) — camadas de anti-regressão.
- [integrations/README.md](integrations/README.md) — integrações externas: autenticação, CAPTCHA e Google.

## Portal

- [portal/README.md](portal/README.md) — índice do Portal.
- [portal/DRUPAL-11-STANDARDS.md](portal/DRUPAL-11-STANDARDS.md) — padrão obrigatório Drupal 11+ para código.
- [portal/GUARDRAILS.md](portal/GUARDRAILS.md) — regras de Fórum, Revista, Loja, Wiki e Conta.
- [portal/ROADMAP.md](portal/ROADMAP.md) — saneamento e, depois, features de produto.
- [portal/CHECKOUT-BILLING.md](portal/CHECKOUT-BILLING.md) — endereço único, envio (Commerce Shipping) e pagamento pelo Mercado Pago.
- [portal/SENHA-CONTA-OAUTH.md](portal/SENHA-CONTA-OAUTH.md) — primeira senha em conta criada com Google.
- [portal/historico/PENDENCIAS-PROXIMA-RODADA.md](portal/historico/PENDENCIAS-PROXIMA-RODADA.md) — pendências abertas.

## Tema

- [../web/themes/custom/aculta420/docs/README.md](../web/themes/custom/aculta420/docs/README.md) — índice do ACULTA420.

## Operação

- [operations/README.md](operations/README.md) — testes, releases, segredos, deploy e dados de teste.
