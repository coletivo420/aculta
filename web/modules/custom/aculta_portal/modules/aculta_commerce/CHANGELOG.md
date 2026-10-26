## Dev — preparação COM-SHIP (branch isolada, 2026-10-26)

- Diretriz do responsável: substituir Commerce Shipping 3.0.5 por código próprio **dentro de `aculta_commerce`**, sem editar contrib ou criar submódulo de Shipping; legado de pedidos/remessas/métodos pode ser descartado apenas em ambiente de desenvolvimento/teste descartável com inventário e backup.
- Criados `ShippingQuote` e `ShippingQuoteSelector` (valores como strings decimais, sem float, sem tarifa fictícia, recusa de quotes expiradas, moedas divergentes e duplicatas); testes Unit preparados, ainda não executados no Homelab.
- Adicionados diagnósticos somente leitura de Config Sync e Runtime e runbook de reset limpo.
- O porte do upstream, alteração de entidades/checkout, alteração de Config Sync, exclusão de dados e testes integrados ainda **não foram executados**; continuar Dev.

## 0.1.0-dev.9 — respeitar AccessResult ao criar produto no Green Stash

- O formulário avaliava os objetos `AccessResultInterface` como booleanos. Como esses objetos são truthy mesmo quando negados, os checks de acesso à experiência Studio e `commerce_product.createAccess()` não interrompiam a criação. Agora ambos exigem `isAllowed()`.
- `GreenStashProductCreateAccessTest` cobre os dois caminhos negados e garante que a negação pare antes de consultar a loja. Unit Commerce: 31 testes / 192 assertions PASS (11 depreciações PHPUnit); PHPCS Drupal/DrupalPractice e PHP lint dos arquivos alterados PASS. Sem operação no Runtime.

## 0.1.0-dev.6 — fechar testes de acesso do Green Stash (2026-10-26)

- `GreenStashProductAccessTest` agora cobre o caso permitido com `ProductInterface`/`StoreInterface` reais do Commerce e bloqueia produto ligado a outra loja, store sem permissão `update` ou configuração ambígua. Entity Access view/update e `store.access('update')` permanecem obrigatórios.
- Unit Commerce: 25 testes / 160 assertions PASS (9 depreciações PHPUnit). PHPCS Drupal/DrupalPractice e PHP lint do teste PASS. Nenhum dado, configuração ativa ou fluxo financeiro foi alterado; Functional/Browser e homologação Runtime continuam pendentes.

## 0.1.0-dev.5 — regressão focal da fundação comercial (2026-10-26)

- Unit Commerce no HEAD da PR: 21 testes / 125 assertions PASS. Nenhum checkout, pagamento ou serviço externo executado.
- Kernel `DonationPortKernelTest` permanece falhando no setup do harness: a cadeia Messenger → Portal → Image e contrib requer serviços/módulos adicionais; 0 assertions funcionais. Não foi criado serviço fictício nem aplicada alteração de produto.

## 0.1.0-dev.4 — publicar evento de pedido no Messenger (2026-10-24)

- `aculta_commerce` publica `store.order_placed`; o consumer e a entrega
  centralizados residem em `aculta_messenger`. O recibo nativo permanece para
  convidados e fallback.
- Unit e Kernel MariaDB integrados passaram; Runtime não homologado.

# Changelog — aculta_commerce

## 0.1.0-dev.8 — confirmar continuação do catálogo (2026-10-26)

- Ao atingir o orçamento de varredura, o catálogo faz uma consulta limitada a
  um ID para confirmar se há continuação. Isso mantém o cursor quando produtos
  mais antigos ainda existem e evita um próximo link vazio quando os resultados
  terminam exatamente no limite.
- Unit Commerce: 29 testes / 183 assertions PASS (10 depreciações PHPUnit).
  PHPCS Drupal/DrupalPractice, PHP lint e `git diff --check` PASS nos arquivos
  alterados. Nenhum Runtime ou dado persistente foi alterado.
- A regressão de paginação foi ajustada para testar 500 candidatos não
  gerenciáveis e um ID mais antigo realmente fora dos lotes. A classe do
  publisher agora também verifica pedido de visitante e fallback do recibo
  Commerce quando o Messenger falha.

## 0.1.0-dev.7 — preservar cursor após varredura seletiva (2026-10-26)

- O catálogo Green Stash agora emite um cursor de continuação quando o limite
  de cinco lotes é atingido após filtrar produtos que o operador não pode
  gerenciar. Produtos mais antigos não ficam ocultos por uma página final sem
  cursor; o acesso continua validado por Entity Access e pela loja.
- Regressão Unit: Commerce 26 testes / 168 assertions PASS (10 depreciações
  PHPUnit). PHPCS Drupal/DrupalPractice e PHP lint nos arquivos alterados PASS.
- Nenhum dado, configuração ativa, pedido ou serviço externo foi alterado.

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
