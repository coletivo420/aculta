# Changelog — aculta_commerce

## 0.1.0-dev.2 — provider de checkout da Loja habilitado (P-CART C) — 2026-10-22

- `ShopCheckoutProvider` declara ao Portal o contexto Loja (tipo de pedido configurado em `aculta_portal.checkout_settings`, padrão `default`, checkout comum e flow efetivo). Somente leitura.
- O módulo passa a constar em `config/sync/core.extension.yml` e foi habilitado no runtime de testes. Não cria configuração, esquema nem hooks. Rollback: `drush pm:uninstall aculta_commerce` e remover a linha do sync. RUNTIME STATUS: verificado por API no runtime local (diagnóstico lista a Loja, sem conflitos); navegador pendente.
