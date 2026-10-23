# Changelog — aculta_commerce

## 0.1.0-dev.3 — alinhar fixture após conciliação do branch (2026-10-23)

- O teste de negação por Entity Access fornece o resolvedor de loja única
  exigido pelo construtor atual de `GreenStashProductReader`.
- Links da navegação expõem o token semântico `data-portal-ui`, removendo a
  classe visual rejeitada pelo gate de fronteira módulo/tema.
- A conciliação anterior preservou o provider de checkout e os serviços Green
  Stash no mesmo YAML. Nenhuma instalação ou operação de checkout foi executada.
- O submódulo Melhor Envio recebeu seu status documental obrigatório, sem
  alterar sua versão, capacidades ou dependências.

- Regressão Unit: Commerce 20 testes / 116 assertions (9 depreciações PHPUnit), Melhor Envio 29/78 e CEP 9/36 passaram.

## 0.1.0-dev.2 — provider de checkout da Loja habilitado (P-CART C) — 2026-10-22

- `ShopCheckoutProvider` declara ao Portal o contexto Loja (tipo de pedido configurado em `aculta_portal.checkout_settings`, padrão `default`, checkout comum e flow efetivo). Somente leitura.
- O módulo passa a constar em `config/sync/core.extension.yml` e foi habilitado no runtime de testes. Não cria configuração, esquema nem hooks. Rollback: `drush pm:uninstall aculta_commerce` e remover a linha do sync. RUNTIME STATUS: verificado por API no runtime local (diagnóstico lista a Loja, sem conflitos); navegador pendente.
