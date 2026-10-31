# Execução contínua ACULTA — PR #298

## PR #299 — recuperação de rota 500 durante migração de Suites — 2026-10-30

- Servidor `toca`; checkout servido `/home/piradopirata/projetos/aculta`, branch `refactor/opencultures-codigo-visual`, HEAD `8ec034700`; Drush confirmou Drupal 11.4.10, URI `http://default`, root `web` e Runtime `aculta_runtime`.
- A inspeção do watchdog encontrou erro em 14:40:17: `Route "aculta_events.organizer_dashboard" does not exist.` A rota já estava definida no código da migração, mas ainda não estava na coleção/cache ativo. Reconstruído o cache Drupal com `drush cache:rebuild` (sem `updatedb`, `config:import` ou escrita de conteúdo/configuração).
- Pós-rebuild, a montagem do controller da entrada da Conta em contexto autenticado de leitura retornou render array; HTTP: MAIN `/` 200, Conta `/entrar` 200, Conta `/studio` 404, Marley anônimo 403 e `/culturas` anônimo 403 (rotas presentes e acesso negado, sem 500). Não foi possível inspecionar o log do PHP-FPM por falta de permissão de leitura; nenhuma falha 500 foi reproduzida nos endpoints verificados.
- Host novo da Wiki não validado nesta microfase; o Domain ativo permanece `wiki420`. Nenhum update de banco, importação ou mudança de dados Runtime foi executado.
- A captura autenticada posterior reproduziu HTTP 500 em `/culturas`: `EventOrganizerDashboardController` chamou `createAccess()` no modo padrão e tentou executar `isAllowed()` no booleano retornado. Drupal 11 exige `createAccess($bundle, $account, $context, TRUE)` para retornar o `AccessResultInterface` cacheável. Corrigido em `95314a5a7`; `drush cr` PASS; `/culturas` anônimo responde 403 (sem 500) e Firefox ESR autenticado confirmou página 200 em 1440/390 px.
- Matriz de purpose pelo Firefox/HTTP: `DomainPurposeManager::environmentPathUrl()` resolveu os cinco hosts de teste; os cinco caminhos retornaram 403 anônimo no purpose próprio e os 20 GET/POST cross-purpose retornaram 404. `/studio`, `/studio/marley` e `/studio/conta` retornaram 404 em GET/POST nos cinco hosts. Baseline Firefox autenticado: 20 capturas (home OpenCulturas + nove páginas × 1440/390), 0 documentos vazios, 0 overflow; duas capturas Green Stash são estado de acesso negado para a conta de teste sem grant e não contam como homologação de acesso.
- Unit focado: 89 testes / 653 assertions PASS. Alias validator, Conta Root, Domain Front Paths e Project Status Index PASS. Kernel `EventDomainBoundaryKernelTest` não executado: `SIMPLETEST_DB` não está configurada nesta sessão. A suíte Unit ampla (457 testes / 1.949 assertions) ainda tem uma falha existente em `GuestCheckoutRegistrationConfigTest`: o checkout do tipo `curso` está configurado como `curso`, teste espera `default`; arquivo não foi alterado nesta migração.
- Novos commits publicados por fast-forward em `origin/refactor/opencultures-codigo-visual`: `faefae9ee` (rotas/purposes) e `95314a5a7` (correção do HTTP 500). Nenhum `updatedb`, `config:import`, escrita de conteúdo ou dado Runtime foi executado.

## PR #299 — atualização de contratos e segunda auditoria de rotas — 2026-10-30

- HEAD e remoto sincronizados em `78e1f2403` na branch `refactor/opencultures-codigo-visual`. O commit documental `docs(studio): align route migration status with ADR-038` consta no remoto; a operação de push concorrente rejeitou porque o HEAD remoto já continha o mesmo SHA. Sem force-push, nova PR ou alteração de outro commit.
- Microfase documental alinhou ADR-035/038, sitemap, paths multidomínio, roadmaps/READMEs de Studio, Conta, LMS, Wiki, Commerce, Mercado Pago e Eventos; as referências pre-ADR-038 foram marcadas históricas. README de status regenerado pelo comando oficial.
- Gates executados: `validate-project-status-index.php` PASS (48 componentes); `validate-aculta-portal-drupal11.php` PASS (1.188 checks); `validate-no-temporary-route-aliases.php` PASS (37 arquivos); `validate-domain-front-paths.php --live` PASS (26 checks); `validate-admin-routes-main-domain.php` PASS (37 arquivos/145 rotas); Studio Unit PASS (59 testes/535 assertions); `git diff --check` PASS. O primeiro passe do índice falhou por tabela README desatualizada; `--write` oficial regenerou-a e o gate passou.
- Sondagem HTTP sem sessão nos hosts retornados pelo `DomainPurposeManager` (`conta`, `cursos`, `wiki420`, `loja`, `eventos`): 260 checks, 0 falhas. Os cinco caminhos canônicos não deram 404/redirect/5xx; 60 GET/POST/AJAX nos purposes errados e 195 GET/POST/AJAX em 13 rotas antigas/arbitrárias `/studio*` responderam 404 sem redirect.
- Nova auditoria do Portal Root: `validate-aculta-portal-conta-root.php` PASS (1.848 checks) após alinhar o validador à entrada `/conta` e o export de front path. A configuração `system.site` no Runtime segue diferente do Sync; `config:status` produziu 455 linhas, incluindo `system.site Different`; não houve import.
- Banco/ambiente: `hostname=toca`; Drush Drupal 11.4.10, root `/home/piradopirata/projetos/aculta/web`, URI `http://default`, conectado a `aculta_runtime`; `updatedb:status`: `[success] No database updates required.` Kernel não executado porque `SIMPLETEST_DB` está unset. Nenhuma escrita de Runtime.
- `validate-module-theme-boundary.php` permanece vermelho por seis ocorrências de classes no módulo `aculta_ugc` (dois `button`, `aculta-admin-status-filters`, `action-links`, `aculta-admin-purpose-filters`, `action-links`), fora do escopo desta frente. As alterações de configuração/script/browser ainda não foram publicadas enquanto houver gate de publicação vermelho.
- Host Wiki: Domain atual `wiki420`; `wiki.aculta.toca.net.br` não resolve no DNS verificado. Domain/Deployer, DNS, TLS, vhost/proxy/CDN e homologação controlada seguem bloqueios externos. Não houve import global nem desativação do host antigo.

## QR Code — smoke do Runtime e correção raster candidata — 2026-10-29

- Ambiente verificado: host `toca`, checkout servido em `/home/piradopirata/projetos/aculta`, Drupal 11.4.10, URI Drush `http://default`, banco `aculta_runtime`; antes da fixture havia 607 tabelas e zero entidades `aculta_qr_code`.
- Criada pelo agente, com autorização do responsável, a entidade temporária ID 2, título `[TESTE] QR Code de homologação`, destino reservado `https://example.invalid/qa-qr`. Não houve chamada ao destino.
- Firefox ESR visível: painel e fixture presentes; prévia SVG, download SVG, PDF, redirecionamento público e reflow 1280/390/320 passaram. PNG e WebP retornaram 500. O log Drupal atribuiu ambos a `FileSystem::prepareDirectory()` recebendo string literal por referência.
- Corrigido o candidato em `aculta_qrcode`: caminho local passado por variável e `FileExists::Replace` no `saveData()`. Kernel focal 1/9, Unit 46/75 e Kernel do módulo 18/136 passaram sem depreciações no banco `aculta_simpletest`.
- Limpeza: antes da remoção, banco e contagem confirmados como `aculta_runtime`/607 tabelas; título e código da fixture conferidos pelo ID. A entidade ID 2 foi removida; contagem final de QR Codes = 0. Não foram encontrados arquivos temporários `public://aculta-qrcode` registrados. Nenhum `updatedb`, importação ou `drush cr` executado.
- O checkout servido continua no HEAD `355e48a89`, anterior ao candidato. Portanto, PNG/WebP ainda não foram revalidados contra o código corrigido no Runtime.

## Aliases dos documentos institucionais — PR #299 — 2026-10-28

- Operação autorizada pelo responsável: mover os Termos de Uso e a Política de Privacidade para `/sobre/termos-de-uso` e `/sobre/politica-de-privacidade`, sem manter compatibilidade temporária. Host `toca`; checkout `/home/piradopirata/projetos/aculta`; branch `refactor/opencultures-codigo-visual`; HEAD no início `cdc462295`; Drush URI `http://default`; root `/home/piradopirata/projetos/aculta/web`; Drupal 11.4.10; banco `aculta_runtime` com 605 tabelas (igual ao registro anterior).
- Antes da escrita, snapshot do banco Runtime: `/home/piradopirata/aculta-backups/aculta-runtime-terms-paths-20261028/aculta_runtime-before-terms-paths.sql.gz`, 5.675.908 bytes, SHA-256 `8d7ee79168a436593b422824a1d11f5f1dd8e1bb82fbed2613c737ffc4ac08a3`.
- Atualizados via Entity API os aliases de `/node/11` (Política) e `/node/41` (Termos), incluindo as variantes `pt-br` e `und` existentes; a referência interna à Política nos Termos foi atualizada em nova revisão do nó 41 (revisão 723), sem mudança substantiva do texto. Três redirects exatos criados pelo módulo Redirect para os aliases antigos foram removidos após conferir origem/destino. `drush cr` concluiu.
- HTTP no host MAIN de desenvolvimento: `/sobre/termos-de-uso` 200; `/sobre/politica-de-privacidade` 200; `/termos-de-uso` 404; `/politica-de-privacidade` 404. Não houve `updatedb`, `config:import`, escrita em produção ou mudança de texto legal substantivo.

## Mercado Pago — planos no Studio e alinhamento do gateway no Sync (2026-10-28)

- Base publicada: `770ec1bc4`; commit `fda2c2d3d` (`feat(mercadopago): manage sandbox plans in Studio`) foi enviado por fast-forward para `origin/chore/aculta-m0-integridade`. `git fsck --full --no-reflogs --no-dangling` terminou sem erros antes do commit e do push. HEAD servido/pós-publicação: `fda2c2d3d` (destacado); branch remota confirmada pelo push.
- O commit de sincronização `3b29d5d87` (`fix(mercadopago): sync eligible order types`) foi publicado por fast-forward sobre `130964b29`, que já continha o commit separado do agente Calendário/Eventos. O delta desse commit contém apenas `config/sync` do gateway MP, status/documentação MP e índice do README; nenhum arquivo daquele agente foi incluído. Não houve `fetch` adicional imediatamente antes desse push; o próprio servidor confirmou o avanço fast-forward `130964b29..3b29d5d87`.
- No HEAD publicado, `vendor/bin/drush status` confirmou `toca`, Drupal 11.4.10, raiz `web/` e banco `aculta_runtime`. `updatedb:status`: `No database updates required.` Antes do alinhamento, `config:status` mostrou 178 diferenças globais; o gateway `mercado_pago` estava ativo em `test` nos dois lados, mas os bundles em Sync eram diferentes dos ativos do Runtime. Somente os nomes dos bundles ativos foram comparados; nenhuma credencial/valor secreto foi lido ou impresso.
- `config/sync/commerce_payment.commerce_payment_gateway.mercado_pago.yml` foi alinhado aos bundles ativos `curso`, `default` e `doacao`. Pós-alinhamento, `config:status --prefix=commerce_payment.commerce_payment_gateway.mercado_pago` informou `No differences between DB and sync directory`; o total global caiu para 177 diferenças de outros componentes. Não houve snapshot, `updatedb`, `config:import`, escrita em `aculta_runtime` nem alteração de credenciais, porque a comparação focal passou sem exigir operação persistente e o drift global restante está fora do escopo autorizado Mercado Pago.
- No HEAD `3b29d5d87`, o gatilho read-only retornou `No database updates required.`, 177 diferenças globais de configuração e nenhuma diferença no gateway. `drush cr` PASS; MAIN e Loja responderam HTTP 200. Gates: índice de status 43 componentes PASS; gate Portal Drupal 11 1.155 checks PASS; fronteira módulo/tema 3.132 PASS; SDC 661 checks PASS; Design Foundations 2.263 PASS e 157 fixtures PASS; Browser Validators 21 PASS; ACULTA420 Foundation 345 PASS; Shell Contract 11 PASS e fixtures 19 PASS.
- Unit Mercado Pago: 258 testes / 1.156 assertions PASS (1 depreciação PHPUnit); Unit Studio 62/502 PASS; PHPCS Drupal/DrupalPractice e PHP lint focal PASS. O Kernel `CheckoutProTest` tinha evidência anterior 3/135, mas não foi repetido nesta sessão: o banco existente `aculta_simpletest` não existe no servidor. Nenhum banco foi criado. Firefox ESR visível confirmou formulário/Studio ACULTA420, acessibilidade básica e reflow; atualização de plano sandbox existente e consulta remota PASS. Nenhuma cobrança/assinatura foi iniciada. Login do comprador retornou `manual_security_challenge`; pagamentos e estornos continuam não homologados.
- Permanecem 177 diferenças globais de configuração fora do escopo Mercado Pago, e o browser do comprador segue bloqueado pelo desafio do provedor. A criação do banco SimpleTest não foi feita.

## Mercado Pago — microfase de gateway de teste, credenciais e homologação (2026-10-27)

- Base: `38c4e1bd1`; `git fsck --full --no-reflogs --no-dangling` terminou sem erros.
- Antes da alteração no `aculta_runtime`, foi salvo snapshot em `/home/piradopirata/aculta-backups/aculta-runtime-before-mp-20261027/runtime.sql`; SHA-256 `2309a8c43627c6396947e98ff3ca97655ddcaf4f5d800a46f0a24b20ef0b45b8`. O gateway `mercado_pago` ficou habilitado em `test`; o modo do gateway e o ambiente permanecem de teste. Campos de credencial foram removidos do config persistido. O pre-save agora limpa valores de credencial dos gateways MP antes de persistir, com teste Unit.
- `drush cr` foi executado; endpoints MAIN e SHOP responderam HTTP 200. O status de configuração do gateway MP corresponde ao `config/sync`; nenhuma atualização de banco do módulo MP está pendente.
- O comprador de teste não teve sessão autenticada confirmada: o provedor devolveu HTTP 403 durante o acesso do navegador. Não foram iniciados pagamentos, estornos ou preferências externas; compra, estorno e webhook sandbox seguem sem homologação.
- A jornada Firefox da Loja foi exercitada sem conta Drupal: produto público carregado em `loja.aculta.toca.net.br`, item adicionado ao carrinho e checkout central aberto em `aculta.toca.net.br`; a etapa seguinte exige login/cadastro Drupal (`/finalizar-compra/{id}/entrar`). As execuções podem ter deixado carrinhos anônimos em estado `cart` no Runtime; nenhum cadastro, preferência MP ou pagamento foi submetido. Revisar esses carrinhos antes de qualquer tentativa de limpeza.
- Incidentes de saída local: uma inspeção anterior exibiu dados da credencial do comprador; uma consulta Drush em formato completo também incluiu um campo de conexão que pode conter segredo. Nenhum valor é reproduzido neste registro. Recomenda-se trocar a senha de teste se reutilizada e avaliar rotação do segredo de banco com o responsável de infraestrutura; nenhuma rotação foi feita.
- Unit: 153 testes / 482 assertions PASS. Kernel em `aculta_simpletest`: 1 teste / 50 assertions PASS, com 77 depreciações Core/contrib. PHPCS Drupal/DrupalPractice, PHP lint e sintaxe JavaScript/Python PASS. Fronteira módulo/tema (3062 verificações) e aliases de rotas (27 arquivos) PASS.
- O índice de status foi regenerado pelo validador oficial após alinhar manifest e status para `0.1.0-dev.20`.
- `updatedb:status` ainda aponta 17 hooks de outros componentes e `config:status` 132 diferenças globais. Não houve `updatedb` nem import global; o escopo de configuração autorizado foi somente Mercado Pago.

## Mercado Pago — microfase de falhas de transporte em callbacks (2026-10-27)

- Base local: `a061c7ac3`. `onNotify()` agora retorna 503 para qualquer falha HTTP/transporte ao consultar pagamento ou merchant order, permitindo reentrega; `onReturn()` falha fechado com erro seguro. Ambos deixam detalhes da exceção fora de logs e mensagens.
- Testes adicionados simulam timeout com conteúdo privado: webhook 503 e retorno `PaymentGatewayException` segura. Unit MP: 152 testes / 480 assertions PASS; lint e PHPCS Drupal/DrupalPractice dos PHP alterados PASS.
- Kernel aguarda ambiente `SIMPLETEST_DB` apontando a `aculta_simpletest`; a opção foi liberada pelo responsável, mas a variável não está presente nesta sessão e o arquivo de configuração em `secrets/` não foi lido. Sandbox financeiro segue sem autorização para credenciais/transações.
- Nenhuma alteração de checkout, banco, credenciais ou serviço externo ocorreu nesta microfase.

## Mercado Pago — microfase de cancelamento e reconciliação (2026-10-27)

- Base de trabalho: `cd49917f6`. O serviço de pagamento usa a mesma chave de lock do webhook; consulta e cancelamento recarregam o pagamento Commerce, validam ID remoto e não aceitam total/moeda divergentes. Repetição após timeout reconcilia `cancelled` localmente sem nova chamada de cancelamento.
- Cancelamento valida ID e estado da resposta do provedor; somente estados `pending`, `in_process` e `authorized` são aceitos, conforme a [referência oficial](https://www.mercadopago.com.br/developers/pt/reference/online-payments/checkout-api-payments/create-cancellation/put). Falhas de transporte usam mensagens/logs sem conteúdo privado.
- Regressões cobrem cancelamento permitido/negado, reconciliação após timeout, lock ocupado, ID remoto divergente, total/moeda incorretos e falha de transporte. Unit MP: 150 testes / 464 assertions PASS; PHP lint e PHPCS Drupal/DrupalPractice dos PHP alterados PASS.
- Kernel não executado (`SIMPLETEST_DB` ausente). Sandbox de cancelamento e pagamentos reais não utilizados. No checkout servido, `drush cr` PASS, Drush resolveu `aculta_pagamentos_mercadopago.payment_service` para a classe real e MAIN/Loja responderam HTTP 200. Nenhum `updatedb`, import de config ou escrita em pagamento foi feito.
- Commit `19cb9266c` publicado por fast-forward de `cd49917f6` para `origin/chore/aculta-m0-integridade`; checkout destacado nesse head. Pós-troca, `drush status`, `updatedb:status` e `config:status` tiveram exit 0; as pendências preexistentes continuam listadas. Cache rebuild PASS; MAIN e Loja HTTP 200. Nenhum update/import foi executado.

## Mercado Pago — microfase de estorno idempotente (2026-10-27)

- Base local desta microfase: `be4dbfa28`; branch `chore/aculta-m0-integridade`. `CheckoutPro::refundPayment()` passa a criar o cliente pela fábrica do gateway ativo, serializar por ID remoto, recarregar o pagamento Commerce dentro do lock e enviar `x-idempotency-key` estável pelo total acumulado e moeda.
- O status Commerce só é atualizado se o Mercado Pago retornar `approved` e, quando presente, o `payment_id` coincidir. Falhas são convertidas em mensagem segura; corpo e mensagem privada do SDK não são registrados. Testes cobrem parcial e total, repetição com a mesma chave, concorrência, resposta `in_process` sem persistência e erro privado sem vazamento.
- Unit MP: 143 testes / 435 assertions PASS; PHP lint/PHPCS dos PHP alterados PASS; status-index PASS (43 componentes). Foundation 345, shell-contract 11, fixtures 19, SDC 628, design-foundations 2.232/157 fixtures, browser validators 21 e module-theme-boundary 3.061: PASS.
- Firefox ESR/geckodriver em `DISPLAY=:0`: `scripts/check-browser-hosts.sh` carregou MAIN, Loja e Apoio (3/3, códigos 0). É somente smoke público, não valida ação de reembolso nem sandbox. Kernel não executado (`SIMPLETEST_DB` ausente); nenhum segredo lido e nenhum pagamento externo foi realizado.
- `aculta_runtime` não recebeu escrita; sem `updatedb`, `config:import` ou alteração de dados. A microfase ainda aguarda homologação sandbox de estorno parcial/total.
- Commit `43c1201bf` publicado em `origin/chore/aculta-m0-integridade` por fast-forward após `git fetch` e confirmação de ancestralidade; checkout destacado nesse SHA. Pós-troca: `drush status`, `updatedb:status` e `config:status` exit 0; Runtime `aculta_runtime` conectado e listas de updates/config ainda pendentes. `drush cr` PASS; MAIN e Loja HTTP 200. Nenhum update/import, snapshot ou dado foi alterado.

## Mercado Pago — microfase de falhas de API no Checkout Pro (2026-10-27)

- Base publicada: `ad74f6fb5`; callback de notificação devolve 503 após erro remoto e o retorno lança `PaymentGatewayException` sem persistir pagamento.
- `CheckoutProNotificationTest` injeta `MPApiException`/`MPResponse` simulados e confirma nenhuma leitura de pedidos nem lock quando o webhook não consegue consultar o provedor.
- Unit MP: 138 testes / 369 assertions PASS; PHPCS Drupal/DrupalPractice e PHP lint do teste PASS. Nenhuma chamada real ao Mercado Pago.
- Sandbox e Kernel continuam pendentes; `SIMPLETEST_DB` ausente e credenciais não foram lidas.
- Commit `ad74f6fb5` publicado por fast-forward. Checkout destacado nesse HEAD; status Drush pós-troca confirmou novamente o Runtime e pendências preexistentes somente leitura; sem update/importação/configuração persistente.

## Mercado Pago — microfase de recorrência (2026-10-27)

- Base publicada: `e9fcd6258`; branch `chore/aculta-m0-integridade`; aprovação/retorno Checkout Pro têm regressão Unit dedicada.
- `MercadoPagoSubscriptionRecorder` valida resposta da API pelo ID consultado e compara a assinatura remota ao ID guardado no pedido. Cobrança aprovada exige pagamento vinculado, ID financeiro, total e moeda; lock por gateway/ID remoto envolve deduplicação e gravação; IDs já associados a outro pedido são recusados.
- Testes simulam ID de fatura/assinatura divergente, IDs-array, lock ocupado, duplicidade, valor/moeda divergentes e payment ID ligado a outro pedido. Regressões CheckoutPro verificam aprovação remota da autorização e retorno aprovado idempotente com filtro order/gateway/remote ID. Unit MP: 136 testes / 358 assertions PASS; lint e PHPCS focais 0/0. SDC/design-foundations/browser validators/module-theme boundary/Foundation/shell-contract e fixtures: PASS.
- Atualizado para MP dev.16 o changelog, contrato, plano de recorrência, debt register, status e índices. Referência oficial de `GET /authorized_payments/{id}` confirmada e registrada.
- Kernel não executado: `SIMPLETEST_DB` ausente nesta sessão; sem leitura de secrets ou credenciais privadas. `updatedb:status` e `config:status` no `aculta_runtime` seguem com pendências; nenhum update, config import, schema ou escrita de entidade ocorreu. `drush cr` só recompilou cache/container e o bootstrap DI do serviço passou.
- Commit `8873a7a6f` publicado por fast-forward após fetch/ancestralidade confirmada; checkout de teste destacado no mesmo HEAD. Após a troca, `drush status` confirmou `toca`, Drupal 11.4.10, URI `http://default` e `aculta_runtime`; `updatedb:status` e `config:status` somente leitura repetiram pendências preexistentes. Não foram feitos snapshot, update, importação ou escrita Runtime. Sandbox e retorno financeiro autorizado continuam pendentes.
- Commit de testes `e9fcd6258` publicado por fast-forward; checkout destacado nesse HEAD. Status Drush pós-troca repetidos em leitura apenas confirmaram novamente pendências de update/config anteriores; não houve alteração de schema/config/dados.

## Mercado Pago — microfase de confirmação financeira (2026-10-27)

- HEAD da pasta de teste: `4dccab365`, branch `chore/aculta-m0-integridade`; servidor `toca`, URI Drush `http://default`, banco `aculta_runtime`.
- Atualizado o webhook para só concluir após consulta remota, referência inequívoca, valor/moeda integrais e lock compartilhado com o retorno. O retorno também compara o total, valida associação ao merchant order e grava o status obtido pela API.
- Novos testes `CheckoutProNotificationTest`: payload malformado (incluindo valor-array), pagamento remoto pendente, referências divergentes e retorno que mente `approved`; Unit MP 126 testes / 297 assertions PASS, sem depreciações PHPUnit. PHPCS dos dois PHP alterados, lint e `git diff --check` PASS.
- `drush cr` PASS; HTTP no checkout: MAIN `/` 200; SHOP `/` 200; webhook POST sem assinatura 503. Firefox ESR/geckodriver visível em `DISPLAY=:0`: smoke MAIN, SHOP e APOIO 3/3.
- O smoke visual é de páginas públicas e não exercita pagamento. Kernel não executado porque `SIMPLETEST_DB` não está configurada nesta sessão; credenciais externas e arquivos de segredos não foram lidos. Homologação sandbox continua pendente.
- Snapshot, `updatedb` e `config:import`: não executados. O índice global agora passa após os status Commerce/User e README terem sido reconciliados em commits próprios. Nenhum pagamento ou credencial foi acionado.
- Commit `a7a158490` publicado por fast-forward em `origin/chore/aculta-m0-integridade` após fetch e confirmação de ancestralidade; checkout de teste destacado no mesmo HEAD. Sem troca de árvore e sem gatilho de banco adicional.

**Branch:** `chore/aculta-m0-integridade`  
**Último checkpoint remoto verificado antes desta atualização:** `c26f27c76` (`docs(game): record ForumPost award hook coverage`), enviado por push normal a `origin/chore/aculta-m0-integridade`.
**Estado:** PARTIAL — PHPCS PR-wide, Kernel H5P, gate Domain administrativo e suíte Kernel parcial LMS têm evidências PASS. M-0 continua parcial por instalação/importação de acervo em ambiente efêmero ainda não exercida, divergência ativa dos grants Wiki e Config Sync por purpose. Melhor Envio foi confirmado como extensão fora do Sync. Os defaults estruturais de Messenger foram versionados conforme decisão do responsável; overrides ativos e updates operacionais ainda exigem preflight. M-1–M-5 seguem em execução; nenhum componente foi promovido.
**Runtime:** Drush read-only confirmou Drupal 11.4.10/PHP 8.4.27 no MAIN `https://aculta.toca.net.br`, banco `aculta_runtime`, root `/home/piradopirata/projetos/aculta/web`. Nenhum write foi executado.

**Domain/purpose:** em 2026-10-26, `DomainPurposeManager::getCurrentPurpose()` resolveu os nove hosts MAIN, ACCOUNT, SUPPORT, MAGAZINE, WIKI, SHOP, COURSES, FORUM e LINKS ao purpose correspondente (9/9 PASS). As seis coleções Domain ativas têm nomes idênticos ao Sync; cinco também coincidem em conteúdo. O front da Conta foi corrigido no Sync de `/studio` para `/minha-conta`, conforme Runtime e contrato; o acesso HTTP direto ao path segue 404. Na coleção `domain.revista420_aculta_org`, o Runtime ainda diverge em `system.site.name` e em `metatag.metatag_defaults.front` (`tags.title`, `tags.og_title`); o Sync mantém o contrato editorial `Revista 420`. Nenhum valor ativo foi alterado. Entity Access por purpose não está homologado.

## Ponto de retomada

1. LMS: os harnesses Kernel parciais agora passam com classes reais dos owners; permanece separado um teste de instalação limpa da closure e de toda a configuração Portal/Messenger/Studio.
2. Editor/CKEditor: 17 IDs e nove configs reconciliados; delta de módulos permanece só `aculta_lms_messenger`, shell que requer uninstall controlado. Importação isolada e Browser seguem pendentes.
3. Wiki: leitura de `wiki420` confirmou os quatro grants de proposta ausentes dos papéis ativos `email_confirmed` e `content_editor`, apesar do Sync correto; Entity Access falha fechado. Só aplicar depois de autorização de escrita, com rollback dos dois roles.
4. Manter bloqueados os cinco hooks e três post-updates pendentes do Messenger até a janela/autorização; o plano read-only registra cinco filas de conquista sem dados pessoais e snapshot/rollback integral em `ACULTA-MESSENGER-UPDATE-PLAN.md`.
5. Próxima unidade independente: projetar exportação do acervo Growroom e importador Drupal idempotente usando fixtures sintéticas e banco efêmero, sem dados privados no Git. O Runtime de testes é o destino desejado, mas a importação persistente aguarda prévia, backup/restauração e rollback. Os 616 tópicos de vida interna e 17 itens marcados `revisar` ficam excluídos por padrão; inclusão exige seleção manual (F-D46).
6. Manter os resultados delegados nos oito componentes atualizados na matriz. O agente dessas frentes concluiu seu trabalho; comparação por caminhos confirmou que os incrementos estão na PR. O agente 3 entregou commits do Index em worktree própria; revisar diffs e evidências antes do cherry-pick individual. Não editar o Index antes da integração coordenada.
7. Manter a branch/PR #298 como único destino; sem merge, deploy ou escrita Runtime. Import persistente no ambiente de testes somente após pipeline, prévia e rollback reproduzíveis. Último checkpoint remoto: `6898024b9`.

## M-0 — revalidação de Config Sync Messenger (2026-10-26)

- `drush status` read-only confirmou novamente Drupal 11.4.10/PHP 8.4.27 e conexão MAIN `aculta_runtime` no checkout compartilhado `/home/piradopirata/projetos/aculta/web`; nenhum comando de escrita foi executado.
- Comparação focal entre a configuração ativa e o `config/sync` desta branch imprimiu somente nomes/caminhos divergentes (sem valores): `aculta_messenger.settings` tem 12 caminhos sob `email_modes`; os metadados `description` de `field_aculta_messenger_attempts` e `label`/`description` de `field_aculta_messenger_source_fr` também diferem. Config Sync possui cinco controles estruturais enquanto o default instalável define ainda políticas de e-mail, eventos da Revista e âncora de digest.
- Naquele checkpoint, a decisão sobre a fonte canônica de `email_modes`/`magazine_events` ainda estava pendente e nenhuma configuração ativa foi alterada. Depois, o responsável aprovou versionar defaults estruturais e preservar overrides ambientais; a implementação seletiva está registrada na seção M-1 de Config Sync abaixo. A pergunta não está mais aberta; os valores ativos continuam fora de uma importação global.
- Inspeção estrutural read-only de capturas privadas confirmou formatos distintos: IPS4 usa blocos `post_block`/`post_body`/`entry-content`; no THW antigo, o permalink aparece como query `postid=` em `<a href>` dentro de uma tabela sem classes semânticas (linha com duas células e ancestrais `table/tr/td/font`). `postid` não é atributo HTML; a anotação anterior foi corrigida. Os scripts atuais não exportam corpos/posts; ainda é necessário um parser versionado com fixtures sintéticas e saída privada antes de qualquer importação. Nenhum conteúdo ou dado pessoal foi impresso.
- A amostra estrutural não foi convertida em código de importação. Naquele checkpoint, a seleção dos 616 tópicos internos e 17 títulos comerciais em revisão ainda aguardava decisão; o responsável definiu depois exclusão padrão com seleção manual explícita (F-D46). O código de importação continua pendente e nenhum conteúdo foi gravado no Drupal.
- No HEAD `dc5561f23`, `php scripts/validate-project-status-index.php` passou (41 componentes) e `php scripts/validate-own-module-packages.php` passou (41 verificações). Foram executados no worktree da PR; os resultados não implicam aprovação dos demais gates nem homologação Runtime.

## M-4 — fechar o harness Kernel focal de Doações (2026-10-26)

- `DonationPortKernelTest` foi reproduzido inicialmente com 4 erros de setup/0 assertions: o teste instalava `aculta_donation` sem o Messenger exigido pelo contrato. Instalar o módulo Messenger inteiro expôs sua closure transitiva e não é necessário para este escopo focal.
- O fixture agora injeta, no container de teste, as implementações reais `MessengerNoticePublisher` e `OrderTypeCheckoutFlows`; também resolve `DonationNoticeEventPublisher` e `DonationCheckoutProvider` reais. A configuração completa do módulo não é instalada porque instancia panes externos ao cenário e requer serviços de recorrência; a suite cobre discovery dos plugins/blocos, criação do pedido de valor inicial zero e negação de estorno.
- Kernel com código da branch e MariaDB dedicado: 5 testes / 193 assertions PASS, com 88 depreciações upstream. PHPCS Drupal/DrupalPractice e PHP lint do teste PASS. Nenhum stub de produto, acesso Runtime, SMTP ou operação financeira externa.
- `aculta_donation` avançou `0.1.0-dev.25` → `.26`; README, CHANGELOG, status e matriz atualizados. Continua Dev; Runtime, sandbox financeiro, revisão integral e RC pendentes.
- A suíte Unit completa do submódulo foi executada no root isolado com código da branch: 24 testes / 84 assertions PASS, sem depreciações. O Kernel 5/193 também foi reexecutado antes da atualização de versão e passou com 88 depreciações upstream.

## M-1 — reclassificar Config Sync operacional do Messenger (2026-10-26)

- Runtime MAIN foi consultado somente por Drush read-only. No arquivo ativo, `aculta_messenger.settings.email_modes` existe e difere dos defaults instaláveis em 15 caminhos; o Sync não contém essa árvore. `magazine_events` e `private_message_digest_anchor` existem nos defaults, mas não estão nem no ativo nem no Sync.
- `updatedb:status` listou cinco hooks (10008–10012) e três post-updates pendentes; entre eles `private_message_email_preferences`, que migra preferências legadas para Messenger. Nenhum update foi executado. Essa migração e as diferenças operacionais tornam inseguro exportar defaults ou copiar o ativo para o Sync sem decidir a fonte canônica.
- Metadados de `field_aculta_messenger_attempts` e `field_aculta_messenger_source_fr` no ativo correspondem aos defaults instaláveis; somente o Sync estava diferente. Os dois arquivos Sync foram corrigidos seletivamente sem alterar campos, mensagens ou preferências ativas. `aculta_messenger` avançou `.400` → `.401`; posteriormente, o responsável decidiu versionar defaults estruturais de `email_modes`/`magazine_events` e manter overrides ambientais. Essa decisão não autoriza importar os valores ativos globalmente.

## Evidência histórica preservada — revalidar antes de tomar decisão

- `6daa7542f test(h5p): repair course access kernel contract`: formatter de acesso e test harness usam o plugin Group real. PHPCS Drupal/DrupalPractice, PHP lint e diff check passam nos dois arquivos H5P alterados.
- Kernel isolado H5P: 3 testes/134 assertions PASS; 41 deprecações Core/contrib. Foram validados Group membership, educador, estudante, usuário sem vínculo, referência de recurso e revogação; nenhum serviço de produto fictício foi adicionado.
- PHPCS Drupal/DrupalPractice passou nos três arquivos H5P alterados após corrigir o widget e documentar duas exceções pontuais para os nomes de classe `aculta_H5P*`, preservados por contrato de plugin/cache.
- Gate Domain administrativo executado via bootstrap Drupal/Drush: `php vendor/drush/drush/drush.php --root=web --uri=https://aculta.toca.net.br php:script scripts/validate-admin-domain-policy.php` PASS (3.498 checks). Drush status confirmou Drupal 11.4.10, DB `aculta_runtime`, root do checkout compartilhado; é verificação read-only do Runtime/código ativo e não valida o diff da PR. Execução direta `php scripts/validate-admin-domain-policy.php` não é válida por depender de container Drupal.
- Commits do agente 2 revisados e cherry-picked: `6483dddbe` Deployer profile scope, `81b03581a` teste Kernel de filtro editorial e `85db52888` reconciliação documental dos módulos sob seu ownership.
- Deployer suite: PASS após tornar neutra a descrição de menu do Portal que acoplava nome de módulo opcional; builds de ensaio continuaram em `/tmp`, e `DEP-0003` permanece bloqueador operacional de páginas públicas do host de teste.
- Editor Kernel, com MariaDB dedicado e código da branch: 7 testes/90 assertions PASS; 3 deprecações. O teste prova que `revista_editorial` aplica `filter_html` e remove `<script>`/atributo de evento.
- Links: status YAML atualizado para `0.4.0-dev.32`, manifesto e changelog alinhados; índice de 41 componentes regenerado e PASS.
- CPF/CNPJ (`aculta_id_cpf`): `AccountDocumentStore` agora possui restrição SQL única em `profile__field_documento_indice`; update `10002` verifica duplicatas com contagem agregada e falha sem imprimir fingerprints; o hook de instalação aplica o contrato em instalações novas. Unit 47/107, runner de domínio 248 checks e Kernel MariaDB 2/5 PASS. `updatedb` não foi executado no Runtime.
- `aculta_user` revalidado sem alterações de código: Unit 129/492 PASS (uma depreciação PHPUnit); Kernel 25/361 PASS (cinco depreciações de Core/contrib). Homologação visual e Runtime permanecem pendentes.
- CEP/Endereço (`aculta_portal_cep`): Unit 9/36 PASS; `scripts/validate-account-address.php` PASS; serviço único é consumido pelo endpoint e pelos adaptadores PHP do Melhor Envio, sem persistência de endereço. O módulo está habilitado em MAIN (read-only). PHPCS do baseline inalterado: 105 erros/36 warnings em 12 arquivos, não introduzidos por esta PR; homologação Browser/X11 ainda pendente.
- Portal: suíte Unit completa 350/878 PASS (6 depreciações PHPUnit) e Kernel com MariaDB isolado 53/831 PASS (90 depreciações Drupal/Core/contrib). O harness de `AccountOtherDataStudioReturnTest` injeta `path.validator` e restaura o container global anterior; código funcional inalterado. Runtime foi somente leitura; rotas por purpose e homologação visual pendentes.
- Runtime inventory read-only por `moduleHandler` em URI MAIN: 35/40 módulos habilitados; cinco desabilitados (`aculta_activity`, `aculta_deployer`, `aculta_commerce_melhorenvio`, `aculta_commerce_messenger`, `aculta_wiki_messenger`). `updatedb:status` mostra 5 hooks e 3 post-updates pendentes de `aculta_messenger`; não executados por falta de autorização para escrita. Outros purposes compartilham o estado global de módulos, mas Config Domain ainda não foi comparada por host.

## Status por frente

| Frente | Estado | Próxima ação |
|---|---|---|
| P0 PHPCS | PASS para erros; avisos baseline permanecem | Seis PHP focais: `origin/main` 143 erros/54 avisos; após correção, 0 erros/49 avisos. Avisos restantes são linhas longas antigas e chamadas de tradução dinâmica. Não se afirma que o repositório inteiro esteja sem avisos. |
| P0 H5P | PASS | Kernel H5P 15/388 e Unit 24/73 em runner isolado; sem stub de serviço do produto |
| P0 admin Domain | PASS no Runtime read-only | 3.498 checks via Drush com URI explícito; não valida diffs não implantados da branch |
| M-0 inventário individual | Parcial | Inventário 41 itens preservado; faltam Runtime/Config Sync completo e evidência por componente |
| M-1 a M-5 | Em execução | CPF Unit 48/116 e Kernel 2/5, FiscalIdentityPolicy 10/24; avançar em contratos Messenger/Commerce/LMS. Integração Profile/Form API, functional/browser e homologações por propósito continuam parciais |

## Atualização após HEAD `d82181d74`

- Portal Unit e Kernel completos passaram conforme evidência acima; o Kernel usou `/tmp/aculta-m0-kernel-test-root` e `/home/aculta/secrets/simpletest-db.env` sem expor credenciais e sem tocar Runtime.
- Resultado contínuo segue PARTIAL: depreciações upstream, Browser/Functional e auditoria Config Sync/Domain por purpose permanecem pendentes.
- Próxima unidade: continuar os contratos de Messenger e confirmar no Runtime read-only os states pendentes de updates; identificar Browser smoke somente quando houver sessão/superfície segura.

## M-1 — Mailer, Messenger e Confirmador (2026-10-26)

- O runner padrão resolve `web/core` e `vendor` para o checkout compartilhado. Para validar esta branch, todas as suítes foram reexecutadas usando `/tmp/aculta-m0-kernel-test-root/web/core/phpunit-m0.xml` e seu autoloader explícito para os módulos do worktree. Unit Portal: 350/878 PASS (seis depreciações PHPUnit); Unit Mailer: 162/343 PASS; Unit Messenger: 382/4.186 PASS.
- Kernel combinado de `aculta_mailer`, `aculta_messenger`, `aculta_mail_confirmer` e `aculta_mail_confirmer_user`: 112 testes / 1.504 assertions PASS em MariaDB isolado, com módulos da branch. Foram reportadas 11 depreciações e quatro notices upstream/PHPUnit.
- README do Messenger estava defasado em `.384` diante do manifesto/status `.396`; versão e totais Unit foram reconciliados. Contagens históricas dos snapshots antigos continuam identificadas como históricas.
- Status e matriz de fechamento registram evidência combinada; confirmação/troca de e-mail em Browser, Runtime mutável e envio SMTP continuam pendentes. Nenhuma fila ou mensagem externa foi processada.

## M-1 — CPF/CNPJ, colisão concorrente (2026-10-26)

- `AccountDocumentStore` agora descarta a exceção SQL encadeada ao converter a violação da chave única em erro genérico. A mensagem subjacente do driver pode carregar o fingerprint duplicado; a API de domínio não a expõe a handlers/logs que recebam a `LogicException`.
- Novo `AccountDocumentStoreUniqueConflictTest` simula o erro de persistência pós-preflight e confirma mensagem genérica sem causa anterior: Unit do módulo 48/116 PASS; Kernel da restrição SQL/update 10002 2/5 PASS em MariaDB isolado. PHPCS e lint dos dois arquivos alterados PASS.
- Manifesto e status avançaram para `0.3.0-dev.3`; Runtime permanece sem `updatedb`. A política de `email_modes`/`magazine_events` foi decidida depois: defaults estruturais versionados no Sync, preservando overrides ambientais; nenhuma importação global foi executada. Próximo: exercitar update com perfis legados representativos e integração real Profile/Form API em Kernel.

## M-0.2 — Config Sync / Runtime (2026-10-26)

- Read-only Drush por `https://aculta.toca.net.br` confirmou Document Viewer, Document Viewer Group, Entity Print e LMS Resources ativos e listados no `core.extension` ativo.
- O `core.extension.yml` exportado omitia essas quatro extensões apesar de dependências em manifests/config. Entradas adicionadas no repositório; `php scripts/validate-config-extension-dependencies.php` PASS (1.558 referências, 1.425 arquivos) para dependências de configuração e manifests próprios habilitados.
- Revisão read-only repetida em 2026-10-26 confirmou 218 módulos ativos e 199 no Sync. `google_tag` estava ativo sem contêiner; no Runtime, `use_collection=false` e `default_google_tag_entity` vazio coincidiam com os defaults instaláveis. A extensão e o settings vazio foram versionados; após isso restam 18 módulos ativos fora do Sync: `aculta_editor`, `aculta_lms_messenger` e 16 módulos CKEditor Plugin Pack. A ausência do shell é intencional para novas instalações; a cópia ativa no Homelab aguarda uninstall operacional autorizada. Sem `cim` ou write no Runtime.
- A comparação ativa também mostra `aculta_editor` habilitado no Runtime enquanto ausente do export; tarefa encaminhada ao agente 2 por ser módulo sob seu ownership. `validate-module-theme-map.php` passou com 196 checks após completar sete linhas documentais ausentes, incluindo os seis custom modules e o provider contrib Entity Print.

## Restrições / deferred

- Nenhuma escrita no Runtime; sem migração, importação de config ou processamento de filas.
- Operações financeiras externas, deploy, merge e remoção de adaptadores permanecem fora da autorização.
- Mudanças que alterem ownership de dados, membership persistida ou política de produto serão isoladas e submetidas ao responsável quando houver decisão necessária.

## Atividade do agente 2

- Ownership concedido exclusivamente: Melhor Envio, Deployer, Patcher, Sitemap, QR Code, Links, Activity e Editor. Commits incorporados: `6483dddbe`, `81b03581a`, `85db52888`.
- Agente não operou no Runtime. Os diretórios devem permanecer sob esse ownership em novas correções até coordenação explícita.


## Content Index e coordenação delegada — 2026-10-26

- Runtime MAIN consultado somente em leitura: Index habilitado, uma geração ativa válida, 145 entradas e 110 relações; queue vazia e auditoria completa sem achados. `aculta_index` adicionado ao Config Sync versionado; sem `cim`, rebuild ou operação persistente. Delta ativo/Sync passa de 20 a 19, ainda sem intenção documentada para os módulos remanescentes.
- Unit Index 51/262 PASS. Kernel full anterior encontrou falhas reproduzíveis no harness: paths de serviço apontavam para o checkout compartilhado; teste de acesso e integração owner não inicializavam Document Viewer Group real; hooks transversais Flag e History precisavam de schemas no fixture. Kernel focal após correção: Media 5/101 PASS; Group/owner/Wiki 10/361 PASS (272 depreciações upstream/Core). Kernel completa concluída: 111 testes / 1.852 assertions PASS, sem erros/falhas; 280 depreciações; 54 notices deprecation PHPUnit reportados separadamente.
- Revisado e cherry-picked o commit do agente 2 `6acf336da` para Editor, Sitemap, QR e Patcher; branches/diretórios delegados continuam sem modificações por esta execução. Agent2 registra estado read-only e lacunas de instalação/config de Editor para coordenação posterior.


## M-1 — Regressão User e CEP (2026-10-26)

- User Unit reexecutado no código da branch: 129 testes / 492 assertions PASS, uma depreciação PHPUnit. CEP Unit: 9 testes / 36 assertions PASS. Nenhuma escrita em Runtime; Kernel CEP/User não iniciado enquanto o Kernel Index usava o banco MariaDB dedicado.


## Gate de Domain administrativo — 2026-10-26

- Runtime identificado antes da consulta: Drupal 11.4.10, URI `https://aculta.toca.net.br`, banco `aculta_runtime`, root `/home/piradopirata/projetos/aculta/web`.
- `validate-admin-domain-policy.php` executado corretamente pelo Drush com bootstrap e arquivo de script do worktree; PASS, 3.498 checks. A falha histórica `Class "Drupal" not found` era da execução direta em PHP CLI, não da política.

## Regressão de gates globais — 2026-10-26

Após integrar o commit documental do agente 2 e reconciliar Index no Config Sync, os gates foram reexecutados: índice de status 41 componentes PASS; dependências Config Sync 1.559 checks/1.425 arquivos PASS; mapa módulo-tema 196 PASS; fronteira módulo-tema 2.879 PASS; Drupal 11+ 1.160 PASS; pacotes próprios 41 PASS; aliases temporários 25 arquivos PASS; slugs públicos 387 rotas PASS (17 violações contrib na baseline); endereços 4.238 arquivos PASS. A execução específica do Domain administrativo passou via Drush com 3.498 checks. Estes resultados cobrem os gates listados, não a suíte total de qualidade dos 40 módulos.

- Index Kernel completo em MariaDB dedicado, após correções focais: 111 testes / 1.852 assertions PASS; sem falha/erro. O PHPUnit reportou 280 depreciações e 54 notices deprecation PHPUnit separadamente. Os cinco providers Media passaram 5/101; Course Document/Owner/Wiki passaram 10/361 focalmente antes da suíte completa.

## Acceptance guardrail and PHPCS review — 2026-10-26

- `validate-single-acceptance.php` initially failed because the root `AGENTS.md` omitted the existing unique-acceptance contract. Added a concise pointer/rule to `docs/portal/ACEITE-UNICO.md`; gate now PASS. `validate-single-use-links.php` also PASS (24-hour contract).
- User regressions on this branch: Unit 129/492 PASS (1 PHPUnit deprecation); Kernel 25/361 PASS (5 Core/contrib deprecations). WikiController Unit 13/74 PASS after its test comment cleanup. CEP Unit 9/36 and account-address gate PASS.
- Focused PHPCS for the two edited global gates and WikiController test passes. The PR-wide PHPCS scan identified remaining violations in delegated Deployer and Editor files; those were referred to agent 2 and remain unclosed until its tested commits are reviewed. Pre-existing warnings in the theme file are outside the changed lines.


## M-3 — Wiki proposals/RBAC (2026-10-26)

- Consultado Runtime read-only em `https://wiki420.aculta.toca.net.br`: Drupal 11.4.10, DB `aculta_runtime`. `email_confirmed` não tem grants de submit/view own; `content_editor` não tem view any/moderate, enquanto os quatro grants constam no Config Sync versionado. A divergência impede envio/fila no Runtime. Os acessos seguem fail-closed; não houve evidência de exposição.
- `WikiLeadImageLifecycleKernelTest::testProposalSubmissionUsesPrivateRevisionedEntityAndAccess`: PASS, 1/216 em MariaDB isolado. Foram reportadas 256 depreciações Core/contrib e duas PHPUnit. `validate-aculta-user-roles.php` e `validate-role-hierarchy.php` PASS.
- `DEFERRED`: reconciliar os papéis ativos requer autorização operacional; preparar diff limitado aos dois roles, export do estado para rollback e verificar pós-apply no purpose Wiki. Nenhuma configuração persistente foi alterada.


## M-2 — Course Document access contract (2026-10-26)

- `CourseDocumentAccessTest` passou no MariaDB isolado: 1 teste / 48 assertions, 144 depreciações Core/contrib. Harness inclui closure real, serviço Portal/User e schemas dos hooks; Media Type/field fixture corresponde ao contrato `config/sync`. Verificados membership, relação removida, não membro e revogação na URL privada Core. PHPCS e PHP lint do teste PASS.
- A documentação antiga classificava o Kernel como bloqueado por ledger ausente; era setup incompleto. Persistem a dependência histórica do bundle Media no PDF Group e a confirmação de instalação por purpose. Nenhuma migração nem operação Runtime executada.


## PHPCS PR-wide e ownership agente 2 — 2026-10-26

- Revisão do agente 2 confirmou que as violações PHPCS originais em `aculta_deployer/src/Transform.php`, `aculta_deployer/tests/run.php` e `aculta_editor/tests/src/Kernel/AcultaEditorFormatsKernelTest.php` antecediam a PR. Formatação/PHPDoc corrigidos sem alterar comportamento; Deployer runner completo, Editor Kernel (7/90), PHP lint e PHPCS Drupal+DrupalPractice PASS.
- O literal `vendor/` no fixture `tests/run.php` acionava o `AcultaDeployer\Boundary::check()` lexical, embora o arquivo fosse criado apenas em diretório temporário para o teste. O fixture agora monta a mesma rota com `DIRECTORY_SEPARATOR`, preserva o arquivo e teste, sem adicionar exceção nem reduzir o scan; boundary PASS. Commits integrados: `b560219a4`, `a36f33553`, `a0b824ae9` (originais do agente: `61bbce049`, `ed3d48cdf`, `8deed7b50`).
- Reexecução PHPCS Drupal+DrupalPractice em todos os PHP alterados pela PR: 0 erros / 0 avisos. Os 12 avisos preexistentes de comentário no `ThemeHooks.php` foram apenas quebrados em linhas; lint e fronteira passaram.
- Agente 2 confirmou no Runtime read-only que `aculta_editor` está ativo embora ausente do Sync; oito templates `config/install` não têm homólogos exportados. Kernel 7/90 PASS. Decisão sobre o conteúdo dos templates e revisão de acesso `aculta_adm` seguem pendentes; não alteramos Config Sync nem Runtime para Editor.

## Ponto de controle — 2026-10-26

- Gate set reexecutado no HEAD local após atualizações de status: PROJECT STATUS 41 PASS; Config Extension Dependencies 1.559/1.425 PASS; Module Theme Map 196 PASS; Module Theme Boundary 2.879 PASS; Drupal 11+ 1.160 PASS; packages 41 PASS; aliases 25 PASS; public slugs 387 PASS (17 contrib baseline); environment addresses 4.238 PASS; secrets PASS; session cookie 6 PASS; user roles PASS; role hierarchy PASS; single acceptance PASS; single-use links PASS.
- PHPCS Drupal+DrupalPractice no conjunto PR-wide de PHP alterado: PASS, zero erros/avisos. Lint do ThemeHooks PASS. As 12 warnings de comentário antigos desse arquivo foram somente quebrados em linhas, sem mudança funcional.
- Último conjunto testado: Content Index Kernel 111/1.852 PASS; User Kernel 25/361 PASS; Document Viewer Group Kernel 1/48 PASS; Wiki proposta Kernel 1/216 PASS; Deployer runner PASS; Editor Kernel 7/90 PASS; Unit Portal 350/878, Mailer 162/343, Messenger 382/4.186, User 129/492, CEP 9/36, Index 51/262, WikiController 13/74 PASS; Portal Kernel 53/831 e comunicação Kernel 112/1.504 PASS (evidências anteriores nesta execução).
- Commits M-4 `68a7b6e9b` e `6518a986e` publicados na branch da PR #298. Trabalho H5P em andamento; Runtime continua somente leitura. M-0 permanece PARTIAL enquanto 19 extensões ativas não estiverem reconciliadas no Sync, roles Wiki divergirem e homologações por propósito faltarem.

## M-4 — regressão focal de Commerce, LMS e pagamentos — 2026-10-26

- LMS Unit: 87 testes / 447 assertions PASS; 8 depreciações PHPUnit. Commerce Unit: 21/125 PASS; Donation Unit: 24/84 PASS; Mercado Pago Unit: 11/22 PASS.
- Mercado Pago Kernel: 1/50 PASS após as alterações. Corrigida assinatura nullable de `refundPayment()` para `?Price`; annotation do gateway convertida para `CommercePaymentGateway` attribute com metadata equivalente; teste declara processo separado. O total caiu de 78 para 75 depreciações; as três próprias (annotation, nullability e processo do Kernel) foram removidas. PHPCS Drupal+DrupalPractice baseline da classe: 24 erros/52 avisos antes, 22/52 depois; teste Kernel 0 erros/1 aviso. Versão dev.8.
- Donation Kernel: quatro testes / zero assertions seguem DEFERRED por erro de compilação. Reprodução atual: sem aculta_messenger, a compilação acusa ausência de aculta_messenger.notice_event_publisher; com o módulo real habilitado, avança e acusa a dependência real private_message.service ausente. São etapas da closure de módulos do harness, não defeitos nos testes de autorização; nenhum stub de produto foi criado e nenhum Runtime foi alterado.
- Commerce README/status atualizados para dev.5 com a suíte corrente e limitação do Kernel. Nenhum checkout/pagamento, fila real, credencial ou integração externa utilizado.
- Runtime permanece somente leitura. M-4 não promoveu módulos a RC: faltam Kernel funcional de Doações, validação de checkout/estorno e homologação visual/sandbox autorizada.

- LMS Kernel agregado reexecutado: 29 testes / 50 assertions, 23 erros no setup. A primeira falha concreta é `aculta_lms.course_order_enrollment` depender do serviço ausente `aculta_portal.checkout_settings` porque os harnesses habilitam LMS sem Portal, apesar da dependência obrigatória. O Portal possui uma cadeia extensa de serviços; sem fixture/teste de instalação que carregue seu fechamento real, a suíte agregada segue DEFERRED. Unit LMS continua 87/447 PASS; focais prévios não equivalem à validação integral.

## M-5 — regressão Statistics — 2026-10-26

- Statistics Unit: 108 testes / 438 assertions PASS. Kernel no MariaDB dedicado: 47/319 PASS. Functional `StatisticsDashboardFunctionalTest` executado contra server local e banco dedicado; 2 erros, 0 assertions porque BrowserTestBase tentou criar `sites/simpletest/<id>/settings.php` sob o checkout compartilhado, resolvido pelo Core symlinkado. Não houve acesso ao Runtime; o server foi encerrado e os paths temporários confirmados ausentes. Browser visual segue pendente.
- Revalidado Runtime com `drush status` antes da consulta: Drupal 11.4.10, URI MAIN `https://aculta.toca.net.br`, banco `aculta_runtime`, root `/home/piradopirata/projetos/aculta/web`; `pm:list` confirmou `aculta_statistics` Enabled. Consultas somente leitura; nenhuma credencial externa foi usada.
- README, changelog, status e matriz atualizados: removida afirmação obsoleta de que ativação no Runtime estava pendente. Consultas reais GA4/Search Console e homologação visual continuam pendentes.

## M-0 — status por purpose — 2026-10-26

- `drush status --uri` read-only consultado em MAIN, ACCOUNT, SUPPORT, MAGAZINE, WIKI, SHOP, COURSES, FORUM e LINKS. Todos usam o mesmo `sites/default`, DB `aculta_runtime`, Drupal root e `config/sync`; portanto module enablement é global, não uma lista diferente por host.
- Isso não comprova equivalência dos overrides Domain Config por purpose. A auditoria de configuração ativa por host continua pendente; nenhum `cim`, update ou write ocorreu.

## M-4 — fechamento Kernel H5P — 2026-10-26

- A classe de acesso prioritária passou 3 testes / 134 assertions; Unit H5P passou 24/73. A primeira suíte Kernel agregada falhou em 12 testes antes de assertions por carregar `aculta_lms` sem o serviço obrigatório `aculta_portal.checkout_settings`.
- Corrigidos os harnesses de descoberta e pacote para não habilitar `aculta_lms` em cenários que verificam apenas serviços/Hooks H5P e o installer. A reexecução da pasta Kernel passou 15/388, incluindo a classe completa de acesso; 41 depreciações Core/contrib.
- `aculta_lms_h5p` avançou a `0.4.0-dev.149`; status e matriz atualizados. Não foram removidas assertions nem adicionados stubs do produto. Ainda não há teste Kernel de instalação limpa da cadeia completa LMS/Portal/Studio; isso permanece separado da cobertura focal H5P.

## M-1 — reteste de CPF e Config Sync do Editor (2026-10-26)

- CPF Unit reexecutado com o bootstrap explícito do worktree: 48 testes / 116 assertions PASS. CPF Kernel no MariaDB dedicado: 2/5 PASS, confirmando constraint única, idempotência e recusa sem exposição de fingerprint em migração duplicada; não houve write no Runtime.
- O primeiro comando Unit usou o PHPUnit padrão e combinou o teste da branch com código do checkout compartilhado (`web/core` e `vendor` symlinkados em `/home/aculta`); falhou nesse código desatualizado. A suíte foi repetida com `/tmp/aculta-m0-kernel-test-root/web/core/phpunit-m0.xml`, que autocarrega explicitamente os módulos do worktree, e passou. Para validar branch/worktree, usar o bootstrap isolado registrado nesta execução.
- Editor: Runtime MAIN tem `aculta_editor` e suas 16 dependências obrigatórias ativos; oito templates instaláveis não têm cópia em Sync. A revisão do Drupal 11 mostra que `cim` agenda install/uninstall segundo o delta de `core.extension` e usa Sync como source dos defaults durante instalação. A reconciliação dos 17 IDs foi adiada até os oito templates serem reconciliados e testados em ambiente efêmero; não houve alteração de arquivos do Editor nem importação. A evidência detalhada está em `ACULTA-M0-AUDIT.md`.


- Revalidação focal M-1: `FiscalIdentityPolicyTest` do Portal passou 10 testes / 24 assertions no bootstrap do worktree. Cobre exigência de documento para pedidos de Loja/LMS, dispensa em Doações, tipos configuráveis e estados fail-closed quando armazenamento/crédito criptográfico falha. Não foram realizadas operações de pedido ou checkout.
- Reprodução do bloqueio LMS em worktree: `ClassDashboardKernelTest` falhou no container por `aculta_portal.checkout_settings` ausente (1 erro/0 assertions). O módulo LMS declara Portal como dependência; o harness não o inicializa. Nenhum serviço de produto foi criado para encobrir o setup, e a correção aguarda a decisão de arquitetura do Kernel.

## M-1 — fila de updates Messenger no Runtime (2026-10-26)

- Reconfirmado em consulta read-only: host `toca`, checkout Runtime `/home/piradopirata/projetos/aculta` (branch compartilhada `docs/aculta-forum-0.1.0-dev.1`; branch da PR separada em `/tmp/aculta-m0-integridade`); Drush conectou Drupal 11.4.10, URI MAIN, banco `aculta_runtime`. Nenhuma operação de escrita ocorreu.
- `updatedb:status` mostra hooks 10008–10012 (marcador de entrega privada, marcador de amizade, estado de digest, estado de leitura de amizade e bundle de aviso de conquista) e post-updates `private_message_email_preferences`, `shared_email_preferences` e `game_achievement_notices` pendentes.
- A suíte Kernel combinada de Messenger/Mailer/Confirmador passou 112/1.504 na MariaDB isolada; testes focais de migração de preferências e de conquistas existem. Isso valida código e fixtures, não o conteúdo real de dados pendentes no Runtime.
- Estado operacional **DEFERRED**: não executar `updatedb`. Próxima ação requer snapshot/backup recuperável, inventário privado somente leitura das contagens/estados afetados, plano de rollback e autorização específica; filas externas/e-mail permanecem sem processamento.
- Em 2026-10-26, contagem agregada read-only encontrou cinco filas legadas de conquistas em cinco contas e zero linhas nas quatro chaves antigas de preferências consultadas. Sem UID ou valores individuais. O plano de preflight, execução isolada, janela e rollback integral está em [`ACULTA-MESSENGER-UPDATE-PLAN.md`](ACULTA-MESSENGER-UPDATE-PLAN.md); a atualização no Runtime permanece bloqueada por autorização operacional.

## M-4 — cobertura de acesso da Loja (2026-10-26)

- A suíte Unit `aculta_commerce` passou 25 testes / 160 assertions (9 depreciações PHPUnit) com o runner isolado da branch. `GreenStashProductAccessTest` usa as interfaces reais Commerce e verifica acesso de entidade, acesso de atualização à única loja, produto de outra loja, store sem permissão `update` e configuração ambígua.
- O teste alterado passou PHPCS Drupal/DrupalPractice sem violações e PHP lint. Versão avançou `0.1.0-dev.5` → `0.1.0-dev.6`; README, CHANGELOG, status e matriz foram reconciliados. Nenhum pedido, produto, loja, Runtime ou serviço externo foi alterado.
- Ainda faltam Form/Kernel/Functional para criação e operações Green Stash, fluxos comerciais completos e homologação visual; não há promoção RC.


## M-3/M-5 — auditoria de ACULTA Game (2026-10-26)

- Unit do Game passou 11 testes / 51 assertions. Kernel `GameAchievementNoticePersistenceKernelTest` no Messenger passou 3/30 no MariaDB dedicado: revalida fonte contra concessões revogadas, migra fila legada uma vez e conserva o cursor/fila quando publisher falha. Duas depreciações de `@EntityType` vêm de Message/Message Template no Core/contrib.
- Documentação identifica o corte de ownership dev.17 (Game publica, Messenger persiste/lê, Mailer transporta). A nota histórica de que faltava teste de chamada ao hook foi superada pelo commit `0e9e0bbcb`: `BadgeEvaluationHooksTest` agora invoca `forumPostSave` e comprova uma concessão única em insert/update com policy e BadgeService reais e storage simulado. Reexecução atual da Unit Game: **12 testes / 73 assertions PASS**. Kernel com ForumPost real e concessão Runtime permanecem pendentes; nenhuma conquista foi concedida no Runtime.

## Retomada PR #298 — integração dos commits delegados (2026-10-26)

- Branch local `chore/aculta-m0-integridade`; commits revisados e cherry-picked do agente 2: `5c1b86fe0` (Sitemap README), `50e83290b` (Patcher impede symlink escape), `4deedb366` (status Sitemap/QR/Links/Deployer), `e38f48a1e` (limpeza segura de fixtures Deployer). Nenhum merge ou operação de Runtime ocorreu.
- Revisão focal própria: `vendor/bin/phpcs --standard=Drupal,DrupalPractice` nos três PHP alterados (Patcher exporter/test e runner Deployer) PASS; runner Sitemap PASS (13 verificações); gates Patcher 2 testes/9 assertions PASS, índice 41 componentes PASS após regenerar README, pacotes 41 PASS e Config Extension Dependencies 1.560 checks/1.426 arquivos PASS. O runner Deployer do agente concluiu PASS sem avisos; suíte combinada dos quatro componentes reportada pelo agente: 91 testes/566 assertions PASS. Esta execução não repetiu a suíte combinada.
- O README raiz foi regenerado pelo gerador de status após os novos status estruturados; essa alteração gerada ainda precisa ser incluída no próximo commit. PHPCS aqui cobre somente arquivos PHP alterados pelos commits incorporados, não o baseline integral dos módulos delegados.
- Próxima tarefa sob ownership central: revisar o resultado do Kernel H5P já registrado em 15/388 com Unit 24/73, retomar o harness LMS/Portal sem serviço fictício, então avançar os contratos centrais independentes (Portal, User, CPF, CEP, Messenger, Commerce, LMS, Statistics e Index). P0-H5P e gate Domain já têm resultados PASS documentados; M-0 global continua PARTIAL por Config Sync/Runtime, autorização e homologações pendentes.

## M-5 — dependência da instalação limpa Statistics/Portal (2026-10-26)

- Functional Statistics reproduzido no MariaDB dedicado: falha com 2 erros/0 assertions ao instalar o dependency closure real porque `aculta_portal/config/install/search_api.server.aculta_database.yml` requer `search_api_db`, mas o manifesto Portal não o requeria. O Core e as extensões Search API/Search API DB estão disponíveis no código; `search_api_db` declara Search API como dependência transitiva.
- Corrigido o contrato no proprietário: Portal `.33` declara `search_api:search_api_db`. Runner de Functional em raiz efêmera, com Composer autoload e `index.php` apontando ao test root, passou da instalação do Search API; a cadeia Portal exige `aculta_user` na mesma instalação devido aos serviços de consentimento, apesar de User depender de Portal. Com User + fixture Domain MAIN, houve 2 testes/11 assertions e 2 falhas no helper de login antes das verificações do dashboard; isso permanece FAIL/deferred, sem classificar o dashboard como defeituoso. O router usado inicialmente sem `index.php` também produziu 404 de ambiente; corrigido no runner local. Sem operação Runtime.


## M-3 — reconciliar estado do Magazine (2026-10-26)

- Auditoria encontrou status estruturado obsoleto: dizia não haver publisher/inscrições ou evento implementado, contradizendo manifesto dev.10, README e changelog. A árvore contém publishers para publicação/republicação/atualização e transições editoriais; `seguir_publicacao` é Flag, não storage paralelo de Messenger. Autores/coautores elegíveis recebem os avisos conforme contrato; envio para revisão/resultados já estão descritos na dev.10.
- Retestes no runner isolado: Magazine Unit 24/96 PASS (3 depreciações PHPUnit) e Kernel 12/500 PASS no MariaDB dedicado. Runtime e SMTP não testados. Status, matriz e changelog agora distinguem implementação, teste e homologação pendente.

## M-1 — validação de apelido nos formulários Profile (2026-10-26)

- Auditoria do User encontrou o caminho restante de escrita: `/dados` preserva `field_nickname` no Profile Form via Portal e não consultava a regra owner de `aculta_user`. Adicionado hook do User que aplica `AccountNames` e `NicknamePolicy` a formulários Profile de papéis com o campo; apelido legado inalterado continua aceito. Não há nova dependência Portal → User nem armazenamento.
- User Unit: 132/504 PASS (1 depreciação PHPUnit); User Kernel: 25/361 PASS em MariaDB dedicado (5 depreciações Core/contrib); teste focal do hook incluído nas Units 3/12 PASS; PHP lint e PHPCS Drupal/DrupalPractice nos dois arquivos alterados PASS.
- A unicidade ainda usa preflight de consulta; não há constraint transacional e não foi alegada garantia contra corridas simultâneas. Próxima ação: avaliar constraint/index compatível com Field API e dados atuais sem storage paralelo, com teste real de concorrência. Nenhuma alteração Runtime.
- Atualização documental de status: removidos resíduos de aspas em Studio/tema e, por cherry-pick do agente 2, Activity/Melhor Envio; índice de 41 componentes valida. Commit delegado incorporado: `ebfdf3833`.

## M-4 — Kernel focal do painel de turma LMS (2026-10-26)

- A primeira tentativa de habilitar Portal/Studio completos confirmou que esse Kernel passava a instalar configuração alheia ao cenário e exigia a grande closure de serviços Commerce/Domain. O teste foi reduzido ao contrato focal permitido: mantém Group/LMS/`lms_classes` reais, carrega e instancia as classes reais de `aculta_lms` e o compositor real de Studio, sem importar rotas/configuração Portal ou adicionar serviços fictícios.
- `ClassDashboardKernelTest`: 1 teste / 48 assertions PASS; 39 depreciações Core/contrib/Group/LMS; PHPCS e PHP lint PASS. As verificações de educator/student, membership, curso/turma, cache e remoção da relação seguem ativas. Isso não valida instalação limpa da cadeia total.
- LMS avançou `0.1.0-dev.89` → `0.1.0-dev.90`. Kernel agregado reexecutado: 29 testes / 98 assertions, 22 erros de setup, todos os harnesses parciais falhando no serviço obrigatório `aculta_portal.checkout_settings`; nenhum erro de assertion. Resolver exige carregar Portal completo ou adequar os cenários com fixture focal que use classes reais, sem serviço falso. Nenhuma escrita Runtime.

## M-4 — continuação de paginação Green Stash (2026-10-26)

- Corrigido `GreenStashProductReader`: ao esgotar cinco lotes de 100 após filtrar produtos que o operador não pode gerenciar, a página agora retorna cursor após o último ID examinado. No caso de lookahead gerenciável, o cursor continua antes do item para que ele apareça na página seguinte. Entity Access, permissão de atualização do produto e autorização da loja permanecem obrigatórios.
- A fixture modela candidatos visíveis no Entity Query, mas sem permissão de atualização; isso corresponde ao filtro de gestão explícito do leitor.
- Nova regressão `GreenStashProductReaderPaginationTest` atravessa 500 produtos visíveis sem permissão de atualização e recupera o produto mais antigo gerenciável na página seguinte. O teste também verifica que um probe de um ID não emite cursor quando a sequência termina no limite: 2/11 PASS. Unit Commerce completa: 27/171 PASS (10 depreciações PHPUnit). PHPCS Drupal/DrupalPractice, PHP lint e `git diff --check` dos arquivos alterados PASS; índices e gates globais descritos abaixo passaram.
- `aculta_commerce` avançou `0.1.0-dev.6` → `0.1.0-dev.8`; README, changelog, status, índice raiz e matriz de fechamento reconciliados. Nenhum Runtime, pedido, pagamento ou serviço externo foi alterado. Kernel de Doações e homologações Green Stash continuam pendentes.
- Próxima tarefa: retomar o fechamento seguro do harness Kernel LMS/Doações ou avançar outra lacuna testável de Commerce/Portal; manter o módulo em Dev até Kernel integrado, Functional e homologações comerciais estarem concluídos.

## M-4 — regressões do publisher e cursor Commerce (2026-10-26)

- Reexecutar a Unit Commerce com o autoloader explícito do worktree (o PHPUnit padrão usa Core/vendor compartilhados e carregava código Commerce do checkout comum). A regressão de paginação tinha fixture incoerente: o candidato gerenciável estava dentro do lote varrido e o probe apontava para outro ID. Ajustados os IDs para 500 candidatos inacessíveis seguidos de um produto mais antigo; nenhum código de produção foi alterado.
- `CommerceOrderNoticePublisherTest` agora confirma que pedidos de visitantes mantêm o recibo nativo e que falha ao publicar no Messenger não marca `skip_order_receipt`; o logger recebe apenas a classe da exceção, nunca seu texto. PHP lint/PHPCS nos dois testes PASS; suíte Unit Commerce 29 testes / 183 assertions PASS, 10 depreciações PHPUnit.
- Nenhum pedido, pagamento, fila, Runtime ou serviço externo foi alterado. Commerce continua Dev até Kernel/Functional, checkout e homologações de loja única.

## M-0 — Config Domain e acesso `full_html` (2026-10-26)

- O Runtime MAIN foi consultado read-only, com host/URI e banco confirmados. `DomainPurposeManager::getCurrentPurpose()` resolveu os nove hosts dos purposes (9/9). As seis coleções Domain têm os mesmos nomes de configuração ativos/exportados; cinco coleções tinham conteúdo idêntico. A home de Conta estava divergente (`/studio` no Sync, `/minha-conta` no Runtime e contrato) e foi corrigida no Sync. Na Revista, permanecem três campos ativos divergentes (`system.site.name`, `metatag.front.tags.title`, `og_title`); o Sync mantém `Revista 420`, coerente com o contrato de apresentação. Nenhum valor da Revista foi sobrescrito e nenhum `cim` ocorreu.
- `validate-domain-front-paths.php` PASS (16), `validate-public-slugs.php` PASS (395, 17 baseline contrib), aliases PASS (25), dependências Config Extension PASS (1.559/1.426), índice status PASS. `validate-domain-presentation-contract.php` direto no PHP falhou sem bootstrap; reexecutado corretamente via Drush no MAIN: PASS (162). A falha direta foi harness, não produto.
- Commit delegado Editor `80dfe0549` revisado: adiciona apenas um teste Kernel de autorização. Cherry-pick local `261b52bc6`; Kernel Editor 8/127 PASS, PHPCS Drupal/DrupalPractice e lint PASS, três depreciações contrib. No Runtime, apenas `administrator` tem `use text format full_html`; o Kernel confirma os papéis de Config Sync e acesso efetivo ao FilterFormat. Nenhuma permissão Runtime alterada.
- Wiki RBAC read-only foi repetido em `wiki420`: os cinco grants de proposta não aparecem nos papéis ativos `email_confirmed` e `content_editor`; o Config Sync contém os quatro grants desses papéis. Kernel focal de submissão/revisão privada passou 1/216 em MariaDB dedicado (256 depreciações upstream); portanto o bloqueio observado é drift ativo-versus-Sync, não falha do handler. Aplicar os grants exige diff prévio e autorização operacional; nenhum papel ativo foi alterado.

## M-2 — compartilhar assets do motor PDF (2026-10-26)

- Comparação byte-a-byte confirmou cópias idênticas de `pdf-viewer.mjs`, `pdf-viewer-wrapper.mjs`, `viewer-wrapper.html` e CSS nos módulos PDF e Document Viewer. Document Viewer passou a depender do módulo PDF e anexar sua biblioteca canônica; seu builder, configuração e verificações de Media/File continuam próprios. Os quatro assets duplicados foram removidos sem alterar o arquivo upstream PDF.js.
- Unit Document Viewer: 42/225 PASS. Kernel da branch foi executado em MariaDB isolado: 5 erros antes de assertions ao instalar a closure obrigatória Portal, por dependências `search_api.server.aculta_database` (`search_api_db`, `search_api`) e `views.view.courses_admin` (`system.menu.admin`) ausentes no harness. Resultado DEFERRED, sem declarar instalação limpa aprovada. PHP lint, dependências Config Extension (1.560/1.426), pacote próprio (41), índice status (41) e diff check PASS. PHPCS completo ainda reporta baseline em linhas não alteradas (builder 31/1, Kernel 4 FQN); nenhuma violação recai nas linhas modificadas.
- Runtime não foi alterado. Próxima ação é corrigir o harness de instalação limpa com closure Drupal real e confirmar a integração de formatter/Media Embed; manter Browser/Office e homologações Runtime pendentes.

## P0 — PHPCS e regressões focalizadas (2026-10-26)

- No HEAD `cccca20a9`, o scan inicial dos seis PHP com findings registrou 143 erros/54 avisos; comparação dos findings com `origin/main` confirmou zero erros/avisos novos. PHPCBF e correções focais removeram todos os erros e cinco avisos: PHPCS atual tem 0 erros/49 avisos. Os avisos restantes são linhas longas preexistentes no builder PDF e no gateway, além de traduções dinâmicas do Mercado Pago; nenhuma limpeza ampla de formatação foi feita.
- H5P: nomes dos testes alinhados à convenção Drupal; os quatro round-trips dos formulários passam `allowed_classes` com somente a classe concreta de Form API, preservando a cobertura de restauração de dependências. Unit 24/73 PASS; Kernel completo 15/388 PASS, 41 depreciações Core/contrib.
- Document Viewer: Unit 42/225 PASS e PHPCS sem erros (um aviso de linha longa preexistente). Kernel no MariaDB dedicado: 5 erros de setup/0 assertions. Portal requer `aculta_user.aculta_user_agreement_ledger`, mas o harness não habilita User; habilitá-lo experimentalmente revelou `path_alias` e dependência `system.menu.admin` em configuração LMS. A fixture experimental foi removida; não foi criado serviço fictício, e a instalação limpa permanece DEFERRED.
- Mercado Pago: Unit 11/22 PASS; Kernel 1/50 PASS, 75 depreciações Core/contrib. PHPDoc documenta os parâmetros reais e a exceção de `createPaymentMethod`; removida variável de iteração sem uso. Nenhuma transação ou chamada externa executada.
- PHP lint dos seis arquivos PASS. Nenhuma operação Runtime ocorreu. Os resultados antigos de PHPCS listados acima neste documento são históricos e ficam supersedidos por esta medição.

## M-3 — teste direto do gatilho Game/ForumPost (2026-10-26)

- A lacuna do status `aculta_game` foi reproduzida: o Kernel do Fórum cobria apenas `ForumParticipationPolicy::isEligiblePost()`, sem invocar `BadgeEvaluationHooks::forumPostSave()`.
- Novo teste focal chama o hook real em duas passagens (insert/update), com `ForumParticipationPolicy`, `DomainPurposeManager`, `BadgeService`, `AchievementDefinitions` e `BadgeCatalog` reais; entidades, config e storage são doubles Unit. O ID de Domain usado é o canônico `forum_aculta_org`. O serviço faz uma única gravação do badge existente após segunda passagem idempotente.
- Unit Game completa: 12 testes / 73 assertions PASS. PHPCS Drupal/DrupalPractice e PHP lint dos dois arquivos novos PASS. Status YAML, README, CHANGELOG e índice de 41 componentes foram reconciliados.
- Limite: não equivale a Kernel com `ForumPost` real, nem homologa privacidade de perfil, visual, Runtime ou concessões persistentes. Nenhuma configuração ou dado Runtime foi alterado; módulo continua Dev `0.1.0-dev.17`.

## M-0 — reconciliação Config Sync do Editor (2026-10-26)

- Antes da mudança, Drush status foi confirmado read-only no host de testes `toca`, banco `aculta_runtime`, Drupal 11.4.10/PHP 8.4.27; as consultas usaram URI MAIN explícita. Nenhuma escrita ocorreu.
- Ativo versus `config/sync` da PR: 218 extensões ativas e 200 no Sync; após o ajuste, 217 IDs coincidem e o único ID ausente do Sync é `aculta_lms_messenger`, shell legado cuja desinstalação requer janela/autorização. Não se removeu o shell nem se alterou Runtime.
- As 17 extensões ativas de Editor/CKEditor foram adicionadas ao `core.extension.yml`. Os oito templates têm conteúdo igual aos `config/install` locais (campos de conteúdo normalizados; UUID/hash runtime ignorados); o nono objeto `ckeditor5_plugin_pack.settings` confere com seu default contrib versionado. Os 9 objetos ativos de `editor.*`, `filter.*` e `ckeditor5_plugin_pack*` agora existem no Sync. Arquivos de módulos/contrib não foram alterados.
- Core `ConfigImporter::processExtension()` redireciona `ConfigInstaller` para a fonte Sync; por isso os defaults foram versionados junto com as extensões. O import não foi executado no Runtime. `validate-config-extension-dependencies.php` PASS, 1.589 checks/1.435 arquivos; índice 41 e pacotes 41 PASS.
- Permanece DEFERRED: ensaiar o ciclo de instalação/importação em banco efêmero e fazer Browser autenticado dos formatos/templates. `full_html` continua permitido somente a `administrator`. O shell Messenger requer desinstalação controlada e rollback antes de qualquer retirada.


## M-0 — verificação read-only de grants Wiki (2026-10-26)

- Consultados os papéis ativos em `wiki420.aculta.toca.net.br` por Drush (`config` read-only): os quatro grants de proposta (`submit wiki proposals`, `view own wiki proposals`, `view any wiki proposals`, `moderate wiki proposals`) estão ausentes dos papéis `email_confirmed` e `content_editor`; os YAML versionados declaram os grants esperados.
- O `WikiProposalAccessControlHandler` retorna acesso negado quando faltam grants; o efeito observado é indisponibilidade das propostas para contribuintes e editores, sem evidência de exposição indevida. Kernel focal existente de submissão/revisão privada: 1/216 PASS.
- Nenhuma permissão Runtime foi alterada. Corrigir o drift requer operação persistente/importação e revisão do diff por purpose, portanto permanece DEFERRED até autorização operacional; depois, repetir verificação por papéis e fluxos em ambiente isolado.


## M-1 — reconciliar estado de entrega do Messenger (2026-10-26)

- A comparação read-only de 1.435 arquivos Sync e 129 defaults Messenger encontrou o campo `field_aculta_msg_delivery` com três valores no Sync, mas cinco (`pending`, `sent`, `failed`, `failed_permanent`, `cancelled`) no default e no Runtime ativo. Os workers já persistem os dois estados terminais.
- Atualizado somente o Config Sync do campo para incluir `failed_permanent` e `cancelled`; nenhuma fila, mensagem, configuração Runtime ou update hook foi executado. Messenger avançou `0.1.1-dev.396` → `.397`; README/status/matriz foram reconciliados.
- Validação de escopo: três testes Unit dos workers passaram 32/608; comparação da configuração ativa/default, YAML, dependências Config Extension (1.589/1.435), status index (41), pacote próprio (41) e `git diff --check` PASS. A importação fica DEFERRED, embora a alteração seja aditiva e reflita os valores já ativos.
- Auditoria global encontrou 28 configs ativos iguais aos defaults e ausentes do Sync; 30 configs ativos sem Sync com diferenças frente aos defaults; 9 configs presentes no Sync com conteúdo diferente do Runtime. Não houve export global; essas famílias exigem avaliação individual. `flag.flag.seguir_publicacao` existe no Sync e não está ativo. Omitidos/antigos do tema Olivero e integrações contrib requerem classificação por owner.


## M-1 — ocultar evento técnico nos avisos de amizade (2026-10-26)

- A comparação de `core.entity_view_display.message.aculta_msg_friendship.mail_body` e `mail_subject` confirmou que o campo `field_aculta_friendship_event` (`requested`/`accepted`) é oculto no Runtime MAIN e em `config/install`, mas não nos dois arquivos Sync. O texto de template é genérico; permitir o campo no display poderia expor o estado técnico no corpo/assunto.
- Sync passou a declarar a dependência do campo e ocultá-lo nos dois displays, alinhando-os com o Runtime e o default. Nenhuma mensagem, preferência ou configuração ativa foi alterada. Messenger avançou `.397` → `.398`.
- YAML, Config Extension Dependencies (1.589/1.435), status index (41), pacote próprio (41) e `git diff --check` PASS; PHPCS não aplicável (nenhum PHP foi alterado). Os testes focais de amizade/entrega passaram 35/644.
- Permanecem 9 configs Messenger ativos, presentes no Sync e com diferenças de conteúdo após normalizar UUID/hash; `aculta_messenger.settings` e `message.template.aculta_msg_friendship` exigem a decisão pendente sobre fonte canônica. As 32 configs ativas fora do Sync que divergem dos defaults também não foram sobrescritas.


## M-1 — ocultar controles Entity Print em displays (2026-10-26)

- Runtime MAIN read-only confirma `entity_print` ativo e os campos extras EPUB/PDF/DOCX ocultos nos sete displays de avisos/e-mails. Defaults Messenger e Sync não os ocultavam.
- Corrigi os sete `config/install` e sete arquivos Sync para manter os três controles ocultos; teste Unit novo compara ambos os conjuntos e também verifica o evento de amizade oculto e sua dependência. `MessengerMailDisplayConfigTest` 2/68 PASS; conjunto focal de amizade/entrega 37/712 PASS; PHPCS/PHP lint do teste PASS.
- Nenhum usuário, mensagem, configuração ativa ou saída Entity Print foi alterado. Messenger avançou `.398` → `.399`; status, changelog, README, índice e matriz reconciliados.
- Reclassificação determinística ativa/default/Sync: 9 configs Messenger ainda divergentes (settings, templates e campos do evento de amizade); Config Sync global agora tem 81 nomes ativos ausentes e um nome exportado inativo.


## M-1 — schema e Config Sync do contador de tentativas (2026-10-26)

- Auditoria de `field_aculta_enqueue_attempts` encontrou propriedades de instância (`min`, `max`, `prefix`, `suffix`) no YAML de field storage Integer. Drupal espera `unsigned` e `size` no storage; `field.field` conserva settings da instância. O `FieldStorageConfig` ativo no MAIN já havia normalizado o storage para `unsigned: false`, `size: normal`.
- Corrigi o default instalável e versionei seletivamente no Sync os dois objetos ativos (storage e field instance), preservando UUID/dependencies exportados; nenhum conteúdo de Message foi lido ou alterado. Update 10009 continua pendente e é idempotente pela instalação de defaults ausentes.
- Testes focal: `MessengerMailDisplayConfigTest` 3/80 e suite amizade/entrega 38/724 PASS; PHPCS Drupal/DrupalPractice, PHP lint, YAML, Config Extension Dependencies, status, pacotes e diff check PASS.
- Inventário global atualizado: 81 configs ativos ausentes do Sync, uma config exportada inativa; Messenger tem 30 defaults ativos fora do Sync que divergem em conteúdo e 9 configs versionadas com diferenças ativas. A transição `string`→`list_string` de `field_aculta_friendship_event` continua sem mudança até decisão e plano de migração.


## M-1 — cache de consulta CEP separado por provedor (2026-10-26)

- Revisão do contrato de fontes configuráveis mostrou que a chave de cache externa usava somente CEP. ViaCEP poderia preencher a chave que depois seria consultada ao selecionar BrasilAPI (ou vice-versa), ignorando a fonte selecionada.
- A chave agora inclui o ID do provedor. Unit focal `CepLookupServiceTest`: 10/43 PASS; caso novo comprova lookup e cache da BrasilAPI sem chamar ViaCEP. PHP lint, PHPCS Drupal/DrupalPractice focal e `git diff --check` PASS.
- `aculta_portal_cep` avançou `0.3.0-dev.10` → `.11`; atualização refletida no CHANGELOG, README/contrato de cache, status e índice gerado. Sem chamadas externas reais ou alteração no Runtime.


## M-1 — evidência de unicidade do apelido (2026-10-26)

- Leitura somente leitura no MAIN confirmou 14 valores atuais em `profile__field_nickname`, sem valor nulo/vazio nem colisões por `LOWER(TRIM(value))`; a coluna usa collation `utf8mb4_general_ci` e não possui índice único. Nenhum nome/UID foi listado.
- A política atual exclui perfis do próprio UID nas verificações de colisão; o contrato permite manter o mesmo apelido em múltiplos bundles Profile da mesma pessoa. O Runtime consultado não tem hoje UID com múltiplas linhas de apelido, mas isso não define o contrato futuro.
- Garantia por índice único do valor seria transacional, mas também proibiria duplicar o mesmo apelido entre bundles do mesmo usuário. Uma chave composta (UID + slug canônico) não cabe nos dados atuais do field table sem novo índice/storage. A implementação fica sem alteração até decisão sobre esse contrato; teste/fix de escrita concorrente será definido em seguida.


## M-4 — corrigir AccessResult na criação Green Stash (2026-10-26)

- Revisão de `GreenStashProductCreateForm::assertCreateAccess()` encontrou uso de objetos `AccessResultInterface` como booleanos nos checks de acesso à experiência e `commerce_product.createAccess()`. Objetos de resultado negado são truthy, então os checks não interrompiam a criação.
- Ambos os resultados agora exigem `isAllowed()`. `GreenStashProductCreateAccessTest` prova que negação da experiência para antes do handler Commerce e negação de `createAccess()` para antes de resolver a loja. Suíte Unit Commerce completa: 31 testes / 192 assertions PASS; 11 depreciações PHPUnit. PHPCS Drupal/DrupalPractice e PHP lint nos dois arquivos PHP alterados PASS.
- Commerce avançou `0.1.0-dev.8` → `.9`; CHANGELOG, README, status e matriz atualizados. Nenhuma chamada ao Runtime ou persistência externa.


## M-0 — regressão de gates e setup Kernel (2026-10-26)

- Gates reexecutados no HEAD `9e707b6df`: status 41 componentes, packages 41, Config Extension 1.590/1.437 arquivos, fronteira módulo–tema 2.887 verificações, Drupal 11+ 1.160, slugs 396 rotas, aliases 25 arquivos, endereços 4.254 arquivos, contrato de segredos, papéis (53 permissões editoriais), hierarquia, aceite único, links de 24h, Profile Links 1.835 arquivos, mapa Domain front 16 checks, cross-domain 18, admin Domain 3.498 e payment Domain 44 — PASS. Scripts que usam Drupal foram executados por `drush php:script` com `--root=web` e `--uri=https://aculta.toca.net.br`; invocação PHP direta não é válida para esses gates.
- `validate-portal-commerce-security.php` não foi executado contra o Runtime: inspeção do script confirmou que ele altera temporariamente o status do gateway de pagamento via Config API. Fica DEFERRED até haver Runtime efêmero/isolado com restauração garantida.
- DonationPortKernelTest no MariaDB dedicado aculta_simpletest: 4 setup errors / 0 assertions; cinco depreciações Core/contrib. Após um experimento temporário que incluiu aculta_messenger real, a falha avançou para private_message.service ausente. O harness ainda precisa carregar a closure de dependências real; alteração de experimento revertida.

## M-4 — negar AccessResult no formulário Green Stash (2026-10-26)

- `GreenStashProductCreateForm` avaliava `AccessResultInterface` como booleano nos gates da experiência e do Commerce; resultado negado continuava truthy. Corrigido para `isAllowed()` em ambos os checks e adicionados dois testes que asseguram parada antes do handler/lookup subsequente.
- Unit Commerce completa: 31/192 PASS, com 11 depreciações PHPUnit; PHPCS e PHP lint focais PASS. `aculta_commerce` dev.8 → dev.9. Sem Runtime ou pagamentos externos.


## M-2 — cobertura fail-closed do FileRemover (2026-10-26)

- Adicionado `FileRemoverFailClosedTest` para confirmar que referências File API e Entity Usage bloqueiam exclusão; falha na consulta Entity Usage propaga antes de acessar a storage de arquivos. O teste não opera sobre arquivos reais.
- Teste focal: 3/23 PASS; suíte Unit Image: 25/103 PASS (9 depreciações PHPUnit); PHPCS Drupal/DrupalPractice e PHP lint PASS. `aculta_image` avançou `0.1.0-dev.31` → `.32`; README, CHANGELOG, status e matriz reconciliados.
- A auditoria não executou `ImageCleanup` em Runtime nem alterou arquivo/conteúdo; limpeza real continua fora do escopo desta missão.

## M-2 — Image: quarentena usa coordenador global — 2026-10-26

- A auditoria confirmou que ImageQuarantine fazia a verificação local somente em File API Usage e Entity Usage e removia diretamente pelo FileRemover, sem a inspeção Portal de referências em campos/revisões/texto formatado.
- ImageQuarantine agora só move um arquivo depois de UnusedFileCleaner avaliar o estado como Available e só apaga item vencido via removeIfUnused; sem o serviço coordenador, falha fechada. Image continua proprietário da remoção física pelo contrato já existente. Nenhuma operação de arquivo foi executada.
- ImageQuarantineOwnershipTest: 2 testes/8 assertions PASS; suíte Unit Image: 27/111 PASS (9 depreciações PHPUnit); UnusedFileCleaner Portal: 5/22 PASS (1 depreciação). PHPCS Drupal/DrupalPractice e PHP lint dos arquivos alterados PASS. Gate aculta_image PASS (25 estilos), pacotes próprios PASS (41), dependências Config Extension PASS (1590/1437), fronteira módulo-tema PASS (2889).
- PHPUnit foi executado com bootstrap temporário que prioriza classes do worktree porque vendor/core são compartilhados e o autoloader padrão seleciona cópias do checkout principal. Um primeiro run sem essa priorização carregou a assinatura antiga de ImageQuarantine; esse erro de harness não foi contado. Bootstrap temporário está em /tmp, fora do repositório.
- Manifesto, README, changelog, plano e status de Image atualizados para dev.33. Estado permanece Dev; Kernel/install-clean e homologação operacional ainda pendentes. Nenhuma alteração no Runtime.

## M-1 — Portal: caracterizar inspetor global de referências — 2026-10-26

- FileReferenceInspectorTest cobre campos de referência estruturada, embeds em texto formatado, pesquisa em todas as revisões de entidades revisionáveis e falha fechada como Unverifiable quando uma query não pode ser concluída.
- Suíte Unit completa do Portal: 353 testes / 914 assertions PASS, seis depreciações PHPUnit; teste focal novo 3/36 PASS; PHPCS Drupal/DrupalPractice e PHP lint do teste PASS.
- Gates após bump documental de Portal 0.4.1-dev.34: índice 41 PASS, pacotes próprios 41 PASS, Config Extension 1590 checks/1437 YAML PASS, Drupal 11+ 1160 PASS. Runtime permaneceu inalterado.
- A verificação encontrou cobertura direta ausente do FileReferenceInspector, não um defeito de produto reproduzido. Instalação limpa e Functional continuam pendentes conforme status anterior.


## M-1 — CPF/CNPJ: cobertura completa do update de unicidade — 2026-10-26

- A inspeção read-only do Runtime MAIN foi precedida por Drush status (Drupal 11.4.10, root compartilhado /home/piradopirata/projetos/aculta/web, banco aculta_runtime). O código ativo em /home/aculta é aculta_id_cpf 0.3.0-dev.1, com schema 10001; a branch da PR tem update 10002 e não foi executada no Runtime.
- Consulta agregada sem selecionar dados pessoais: Profile.associado tem 1 documento cifrado e 1 fingerprint, zero documentos sem índice, zero grupos duplicados e nenhum índice SQL único. Config ativa não possui bundle participante; campo atual é associado.
- O update 10002 agora falha fechado se faltar documento de origem ou fingerprint de algum documento ativo associado; só cria a restrição depois de cobertura e duplicatas serem verificadas. A checagem não exibe CPF, ciphertext ou fingerprint.
- Kernel MariaDB isolado: 3 testes / 8 assertions PASS (idempotência, rejeição de grupos duplicados e de documento sem fingerprint); Unit: 48/116 PASS; PHPCS Drupal/DrupalPractice e PHP lint dos arquivos alterados PASS.
- Manifesto/README/CHANGELOG/status/matriz reconciliados para 0.3.0-dev.4 e bundle associado. O update não foi aplicado; permanece dependente de ensaio com dados sintéticos, plano de rollback e autorização operacional.


## M-1 — dispensa fiscal de curso LMS gratuito (2026-10-26)

- Corrigida a política compartilhada: Loja exige documento; LMS exige para variações `curso` de preço base maior que zero. Todos os itens precisam ser reconhecidos como cursos gratuitos para dispensar; pedido vazio, item ausente ou composição ambígua permanece fail-closed. Descontos não tornam curso pago gratuito para esta regra.
- `FiscalIdentityPolicyTest`: 13 testes / 30 assertions PASS. `FiscalIdentityPaneKernelTest`: 12 testes / 343 assertions PASS em MariaDB dedicado (72 depreciações upstream). PHPCS Drupal/DrupalPractice e PHP lint dos dois arquivos PHP PASS. Uma invocação anterior do Kernel sem `SIMPLETEST_DB` terminou em setup (12 errors/0 assertions); repetição com o ambiente isolado passou.
- Atualizados manifesto `aculta_portal` 0.4.1-dev.35, README, changelog, contratos CPF/checkout, roadmaps Commerce/LMS, status e matriz. README raiz regenerado; índice 41 componentes PASS. Nenhuma operação Runtime/checkout foi executada.
- Próxima ação: revisar diff, retomar decisão do harness Portal/User para LMS/Doações e auditar o próximo contrato central independente.


## M-2 — limpeza de imagem sempre coordenada pelo Portal (2026-10-26)

- `ImageCleanup` ainda chamava `FileRemover` diretamente quando `aculta_portal.unused_file_cleaner` estava indisponível, divergindo do contrato em `aculta_image/docs/PLANO.md`. Removido o fallback: agora retorna `Unverifiable`, não deleta e não remove o item da fila; o lease expira e permite tentativa posterior. Dependência direta de `FileRemover` removida do serviço/construtor. Quarentena já exigia coordenador.
- Novo `ImageCleanupCoordinatorTest` verifica ausência de exclusão física e retenção da fila sem Portal: Unit Image completa 28/118 PASS (9 depreciações PHPUnit); PHPCS Drupal/DrupalPractice e PHP lint dos arquivos alterados PASS; `validate-aculta-image.php` PASS (25 estilos catalogados, zero usos externos).
- Manifesto/status avançaram para Image `0.1.0-dev.34`; README/changelog/AGENTS, matriz e execução atualizados. Nenhum arquivo real foi limpo e nenhum Runtime foi alterado. Próxima ação: completar Kernel/instalação limpa e seguir consumers de cleanup do Portal.


## M-1/M-2 — limpeza de avatar integrada ao coordenador (2026-10-26)

- `AvatarAssetCleanup` do User excluía temporários e avatares órfãos diretamente via `FileRemover`, após checagem local de `user_picture`/File Usage; isso não cobria referências estruturadas e embeds globais. Migrado para `aculta_portal.unused_file_cleaner`, preservando a retenção e retornando contadores apenas para status `Deleted`. O User já requer Portal; não foi criada dependência circular nem storage paralelo.
- `AvatarAssetCleanupCoordinatorTest` usa UnusedFileCleaner real, com banco, File Usage e ReferenceInspector simulados: 1 teste/8 assertions PASS. User Unit completo: 133/512 PASS (1 depreciação PHPUnit). Nenhum arquivo real ou Runtime alterado.
- User avançou `0.4.0-dev.77`; status, README, CHANGELOG e matriz atualizados. PHPCS Drupal/DrupalPractice e PHP lint nos dois arquivos User PASS; User Kernel completo 25/361 PASS (5 depreciações contrib); YAML PASS; index 41, pacotes 41, Config Extension 1.590, User Roles, Drupal 11+ 1.160 e fronteira módulo-tema 2.891 PASS. Próxima ação: commit e push normal.


## M-1 — preflight de unicidade de apelido (2026-10-26)

- Drush status read-only reconfirmou Host `toca`, MAIN `https://aculta.toca.net.br`, DB `aculta_runtime`, Drupal 11.4.10/PHP 8.4.27 e root `/home/piradopirata/projetos/aculta/web`. Consultas agregadas à `profile__field_nickname` não leram valores pessoais: 14 linhas ativas/14 perfis, zero linhas deletadas, nenhum grupo duplicado entre UIDs ou repetição entre perfis do mesmo UID, sem índice único. Nenhum write ocorreu.
- Não aplicar unique key simples: a tabela Field API não contém UID e `NicknamePolicy` exclui perfis do mesmo UID, permitindo apelidos repetidos entre perfis legados da mesma conta. A opção priorizada para protótipo é lock distribuído por apelido normalizado em hooks de persistência, com reconsulta protegida; fallback é índice derivado por UID se algum caminho não puder ser coberto. Evidência, riscos, teste concorrente e rollback estão em `aculta_user/docs/NICKNAME-UNIQUENESS.md`.
- Status M-1 segue PARTIAL; próxima tarefa é protótipo Kernel/MariaDB com dois processos concorrentes, sem execução no Runtime.


## M-4 — remoção H5P integrada à limpeza global (2026-10-26)

- `aculta_H5PManagedFileStorage` removia assets depois apenas dos marcadores H5P/Image; agora entrega assets de imagem e não-imagem a `aculta_portal.unused_file_cleaner` após liberar os próprios markers. Status diferente de `Deleted` falha fechado, preservando os arquivos que ainda tenham referência.
- Unit H5P completo: 25 testes / 81 assertions PASS. Kernel completo em MariaDB dedicado: 15 testes / 388 assertions PASS, 41 depreciações Core/contrib. O harness registra classes de limpeza reais do Portal e do Image sem instalar o Portal inteiro, mantendo os Kernel focais isolados. PHPCS focal e lint PASS.
- PHPCS sobre todo `aculta_lms_h5p/src` e `tests` continua FAIL por erros preexistentes em múltiplas classes portadas, PHPDoc e estilo; nenhum desses arquivos alheios ao incremento foi formatado. Nenhuma operação de limpeza ou Runtime ocorreu.
- Manifesto/status H5P avançaram para `0.4.0-dev.150`. Próximo: fechar os gates focais e seguir a auditoria de componentes centrais independentes.


## M-0 — varredura PHPCS das alterações da PR (2026-10-26)

- `git diff origin/main...HEAD -- '*.php'` selecionou 78 arquivos PHP; PHPCS Drupal/DrupalPractice terminou com zero erros e 49 warnings. Os warnings listados estão nos arquivos tocados de Document Viewer e Mercado Pago (linhas extensas e traduções dinâmicas), sem falhas bloqueantes de erro; a revisão de causa permanece ligada aos módulos owners. A varredura não equivale a PHPCS limpo de todo o código legado de cada módulo.
- Os sete PHPs tocados no incremento H5P também passaram PHPCS sem erros nem warnings. Nenhum warning foi suprimido ou gate enfraquecido.


## M-1 — normalização no Profile legado (2026-10-26)

- `ProfileNicknameValidationHooks` aparava o valor para validar formato e colisão, mas a extração do widget continuava contendo o texto original. Agora o valor aparado aprovado é gravado em Form API antes de `ContentEntityForm` copiá-lo à entidade; entrada vazia ou valor legado inalterado preserva o comportamento anterior.
- Teste focal: 4/18 PASS; User Unit completo 134/518 PASS (uma depreciação PHPUnit); Kernel completo 25/361 PASS (cinco depreciações Core/contrib). PHPCS e lint dos arquivos alterados PASS. Runtime somente leitura, sem alteração.
- User avançou a `0.4.0-dev.78`; a corrida de unicidade permanece aberta. A próxima ação dessa trilha é provar uma garantia concorrente compatível com perfis múltiplos do mesmo UID, sem constraint simples incompatível com a tabela Field API.


## M-1/M-0 — reconciliar contrato do Confirmador e login (2026-10-26)

- Auditoria cruzada confirmou que a política vigente permite login à conta ativa ainda não confirmada e restringe ações específicas; `verify_mail: false`, `EmailConfirmationPolicy` e `PortalFormCallbacks` sustentam essa decisão. O `user_login` do submódulo registra confirmações configuradas, não bloqueia o login. Confirmação de cadastro não inicia sessão; confirmação de troca pode iniciar sessão somente para visitante anônimo/conta ativa.
- Corrigidos textos errados “bloqueio de login” e “hash sem expiração” em manifestos, READMEs, changelogs, política, mapa módulo–tema e relatório de incorporação. O hash usa TTL de 24 h (`hash_expiration: 86400`). Histórico do comportamento do contrib foi preservado e marcado como substituído pelo contrato ACULTA.
- Testes no runner isolado: Confirmador + troca 7 testes/53 assertions PASS (uma depreciação Core/contrib); `LoginBlockedAccountTest` + `LoginValidatorSwapTest` 9/22 PASS; `EmailConfirmationPolicyKernelTest` 3/16 PASS, agora afirmando que o usuário sem marca permanece ativo. Nenhum SMTP externo, Browser/Runtime ou operação persistente foi usado.
- `aculta_mail_confirmer` e submódulo avançam para `0.1.0-dev.3`; Browser autenticado para confirmação/troca e capacidades restritas permanece pendente. A prova automatizada é parcial e não substitui homologação.


## Checkpoint M-1 — confirmação de e-mail e próxima análise de perfil (2026-10-26)

- Commit `35da56a09` reconciliou o contrato do Confirmador: conta ativa pode autenticar antes da confirmação, ações específicas continuam restritas e o token expira em 24 h. Inclui texto de formulário e asserção Kernel; PHPCS Drupal/DrupalPractice e lint focais passaram; `EmailConfirmationPolicyKernelTest` passou 3/16. Índice de status (41) e pacotes próprios (41) passaram.
- Kernel foi executado com as credenciais dedicadas em `/home/aculta/secrets/simpletest-db.env`. Uma tentativa anterior com credencial genérica falhou no setup; não conta como resultado de produto. Nenhuma operação Runtime persistente, SMTP externo ou Browser foi realizada.
- **Registro histórico substituído por F-D44 em 2026-10-26:** a hipótese inicial de sufixar rotas de perfil legado foi rejeitada. Autores Growroom não possuem perfil individual/rota `/u/`; apenas cartões de autoria em conteúdo do Fórum exibem `-gr`. Perfis por papel, rotas privadas `/user/{uid}` e `/identidade/{user}` permanecem fora dessa regra.
- Drush status read-only no URI MAIN reconfirmou Drupal 11.4.10, DB `aculta_runtime` e root compartilhado `/home/piradopirata/projetos/aculta/web`.


## Regressão de gates e investigação do Kernel Document Viewer (2026-10-26)

- Na HEAD `2901b16d4`, a varredura PHPCS Drupal/DrupalPractice dos PHPs da PR continua em 0 erros e 49 avisos, concentrados em um aviso preexistente do PDF Viewer e 48 no CheckoutPro de Mercado Pago. Fronteira módulo–tema (2.892), Drupal 11+ (1.160), aliases (25), slugs (396; 17 violações contrib baseline), endereços (4.260), status (41) e próprios packages (41) passaram.
- Kernel completo `aculta_document_viewer` reproduz 5 erros de setup/0 assertions: o Portal ativa serviços que exigem `aculta_user` e a cadeia declarada pelo Portal; o harness atual não habilita essa closure. Tentativa focal com `aculta_user` e seus módulos-base reduziu o primeiro erro, mas expôs `key.repository` ausente. Não foi mantida alteração de harness, não foi criado serviço fictício e nenhuma instalação Runtime ocorreu. A instalação isolada completa deve carregar a closure real de 72 módulos declarada transitivamente, ou o harness precisa ser redesenhado com contratos reais mínimos; classificar como falha de setup, sem alegar defeito funcional do viewer.
- O worktree de missão foi restaurado sem alterações após a investigação. Próximo passo para esse Kernel: avaliar a viabilidade de instalar a closure Portal real em test root efêmero; manter Unit 42/225 PASS como evidência separada, sem promover o componente.

## M-3 — proteger grants de propostas Wiki no Config Sync (2026-10-26)

- O Runtime Wiki consultado read-only mantém ausentes os grants de proposta de `email_confirmed` e `content_editor`, então submissão e moderação permanecem indisponíveis pelo handler fail-closed. Nenhuma configuração ativa foi alterada.
- Adicionado `WikiProposalRolePermissionsConfigTest`, que verifica a distribuição dos quatro grants entre os dois papéis versionados e a dependência de `aculta_wiki`: 1 teste / 8 assertions PASS. PHPCS Drupal/DrupalPractice, lint, índice de status (41), pacotes próprios (41) e `git diff --check` PASS.
- Wiki avança para `0.1.0-dev.118`; README, roadmap, changelog, status, matriz e índice foram atualizados. Importação dos grants ativos e homologação dos fluxos permanecem DEFERRED até autorização operacional.


## M-1 — autoria histórica Growroom (2026-10-26)

- Decisão do responsável: autores importados do Growroom não recebem User/Profile nem página individual `/u/`; `/u/lendashistoricas` é o memorial coletivo e exibe nomes sem sufixo. `-gr` é reservado ao nome de exibição do cartão histórico no conteúdo do Fórum.
- `aculta_user 0.4.0-dev.80`: `AuthorCardBuilder::buildLegacyForum()` aplica o marcador apenas nos adapters Fórum; o método genérico mantém o nome e comentários de contextos não-Fórum não recebem `-gr`; `#profile_url` continua `NULL`. Unit User completa 135/519 PASS (uma depreciação PHPUnit); PHPCS Drupal/DrupalPractice e PHP lint nos arquivos alterados PASS. Sem alteração Runtime. Código em `db389cd5e`; evidência e documentação atualizadas em `80aff8803`, ambos publicados em `origin/chore/aculta-m0-integridade`.
- F-D43/F-D44 foram registrados. A revisão de consumidor encontrou que o builder genérico também atende Comments compartilhados; dev.80 restringe o sufixo aos adapters de Fórum. O documento de importação agora substitui como desatualizado o bloqueio da remodelagem #297 e a decisão de manter o acervo ausente como estado final. O mapeamento dry-run atual não é importação; o repositório ainda não contém um exportador final/importador Drupal idempotente e o mapa não mantém IDs estáveis de autoria. Próxima tarefa: desenhar/testar exportador versionado com fixtures sintéticas, preservando IDs e hashes, sem copiar dados privados ao Git. Importação persistente no Runtime fica DEFERRED até pipeline, banco efêmero, prévia, backup e rollback.

- Regressão dev.80: suíte Unit completa User 135 testes/519 assertions PASS, uma depreciação PHPUnit. O Kernel isolado anterior não existe mais em `/tmp`, portanto não foi repetido; nenhum Runtime foi usado.


## M-3 — manifesto estável de autoria Growroom (2026-10-26; commit `83c6f1856`)

- Decisão F-D45: autores sem ID de origem recebem chave `nome-<sha256>` do nome normalizado; IDs IPS4/THW são prioritários. Homônimos normalizados serão agrupados. Autoria sem nome utilizável fica com estado `revisar`, não pode ser importada.
- `scripts/growroom/build_mapping.py --so-mapeamento` regenerou os mapas no storage privado Homelab, fora do Git e do Drupal Runtime. `autor-origem.json`: 9.608 registros (741 IPS4, 2.751 THW, 6.116 por nome), sem pendências; 211.388 referências a tópicos. Dez e-mails continuam omitidos.
- `verificar_importacao.py --rapido`: C1–C10 PASS; C10 validou IDs, nomes e URLs em tópicos selecionados; C8 sem e-mails. C1/C3 em modo rápido verificaram existência/tamanho, não hashes/marcador final. A integridade completa dos bytes do acervo não foi recalculada.
- Testes sintéticos `python3 -m unittest tests/scripts/test_growroom_origin_ids.py`: 10 testes PASS; `py_compile` PASS. Nenhum importador Drupal de posts/assets existe ainda. Próxima unidade: projetar exportação versionada dos posts/arquivos, seguida por importação idempotente em banco efêmero; importação no Runtime não ocorreu.


## M-4 — regressão focal Commerce, Doações e Mercado Pago (2026-10-26)

- Commerce Unit: 31 testes / 192 assertions PASS; 11 depreciações PHPUnit. Mercado Pago Unit: 11/22 PASS. Nenhum pagamento, sandbox externo ou credencial foi usado.
- Kernel da branch, MariaDB `aculta_simpletest`: `CheckoutProTest` 1/50 PASS, com 76 depreciações Core/contrib e 2 PHPUnit. Um primeiro runner carregou código do checkout compartilhado e foi descartado; a execução válida usou o caminho absoluto do worktree e autoloader prioritário da PR.
- Registro histórico supersedido: uma tentativa anterior do `DonationPortKernelTest` tinha 4 erros de setup / 0 assertions sem publisher; na dev.26 o harness passou a registrar as implementações reais `MessengerNoticePublisher` e `OrderTypeCheckoutFlows` sem instalar configuração/closure irrelevante. Resultado atual documentado pelo proprietário: 5 testes / 193 assertions PASS em MariaDB isolado, 88 depreciações upstream. Sem Runtime ou integração financeira externa.

## M-2 — validar referências físicas em revisões e rich text (2026-10-26)

- Unit focais existentes de `UnusedFileCleaner`/`UnusedMediaCleaner`: 8 testes / 31 assertions PASS, com duas depreciações PHPUnit.
- Novo `FileReferenceInspectorKernelTest` usa Node, File e campos reais em MariaDB isolado: 2 testes / 19 assertions PASS. O Entity Query com `allRevisions()` encontrou um arquivo que só permanece em revisão antiga; a consulta UUID encontrou outro referenciado por campo text_long. Nenhuma exclusão foi invocada.
- PHPCS Drupal/DrupalPractice, PHP lint e `git diff --check` PASS. `aculta_portal` avançou `.35` → `.36`, com README, changelog, status, índice e matriz atualizados. Group/Media compartilhada, tradução/revisão multilingue e validação operacional do cleaner permanecem em auditoria; Runtime continua somente leitura.


## M-5 — revalidação Functional de Statistics (2026-10-26)

- Fixture da `StatisticsDashboardFunctionalTest` explicita `aculta_user`, `search_api` e `search_api_db`: serviços do Portal dependem do ledger User e o backend da entidade Search API instalada exige esses providers. Domain MAIN `aculta_org` foi configurado como `127.0.0.1`; o PHP server deve ser iniciado com cwd no `web/` Drupal para resolver serviços relativos.
- BrowserKit passou a primeira assertion de acesso anônimo (403). Login e a segunda rota falham no helper `drupalLogin()`; painel autenticado, exportação e 404 não foram exercitados. Um experimento não versionado criou Domains `main.localtest.me` e `account.localtest.me` com cookie compartilhado no site efêmero; ainda falhou no helper, então a causa de sessão/login não foi isolada. Resultado agregado: 2 testes / 11 assertions / 2 falhas. Uma tentativa Selenium/Firefox iniciou, mas esse driver não fornece status HTTP e não valida esta classe. Nenhuma execução é PASS funcional ou revisão visual.
- PHP lint e PHPCS Drupal/DrupalPractice do teste PASS após corrigir estilo legado. Nenhum produto, Runtime ou dado persistente foi alterado.
- Próxima ação: observar a resposta da submissão de login e o cookie/registro de sessão no banco SimpleTest isolado, sem imprimir credenciais nem dados pessoais; corrigir a fixture ou o contrato real conforme evidência, preservando `403/200/404/CSV`. Statistics permanece Dev.

## Growroom — bloquear inclusão automática de itens em revisão (2026-10-26)

- Auditoria do conjunto encontrou divergência comprovada: o classificador marcava 17 URLs como `revisar`, mas o gerador filtrava somente `comercial_forte`. O conjunto anterior incluía sem seleção manual itens nessa revisão, em conflito com o roteiro de triagem. `build_mapping.py` agora conserva níveis de marcação e exige seleção explícita para `comercial_forte` e `revisar`; C9 aplica e reporta o mesmo contrato.
- Testes `python3 -m unittest tests.scripts.test_growroom_origin_ids`: 13/13 PASS, cobrindo bloqueio de revisão sem seleção, aceitação após seleção e regressão no C9. `py_compile` e `git diff --check` PASS.
- A geração foi executada somente no storage privado documentado (`sudo -n python3 -B -I scripts/growroom/build_mapping.py --so-mapeamento`). Conjunto: 31.585 tópicos; 28.134 de fóruns incluídos e 1.946 exceções temáticas; 651 marcados excluídos por falta de seleção. Nenhuma ampliação foi feita.
- Verificadores rápido e completo passaram C1–C10 após regeneração. Completo: 68.666 registros sem arquivo ausente/SHA divergente; 2.151 grupos duplicados sem problema; 228 truncamentos sem problema; 3.941 URLs de perfil permitidas com captura; C9 sem violações; C10 9.607 autorias sem duplicatas, inválidos, pendências, referências ausentes/fora do conjunto ou e-mails. Relatório do acervo permanece privado.
- Sem escrita no Runtime e sem importação de conteúdo. Próxima tarefa: desenvolver exportação a partir do conjunto aprovado e testar o pipeline de importação idempotente em banco efêmero; a importação persistente no Homelab exige prévia, destino e backup/rollback validados. Ver `aculta_image/docs/IMPORTACAO-GROWROOM.md` para decisão e critérios operacionais atualizados.

## Runtime — nova leitura por purpose e adaptação do inventário (2026-10-26)

- Antes da consulta, `drush status --uri=https://growroom420.aculta.toca.net.br` identificou host `toca`, Drupal 11.4.10, DB `aculta_runtime` e root compartilhado `/home/piradopirata/projetos/aculta/web`. O checkout publicado por `public_html -> web` é `/home/aculta`, não o worktree `/tmp/aculta-m0-integridade`; nenhum código da branch foi servido ou testado pelo Apache.
- `moduleHandler()->moduleExists()` confirmou 36/40 módulos próprios habilitados e quatro desabilitados: `aculta_activity`, `aculta_deployer`, `aculta_commerce_messenger`, `aculta_wiki_messenger`. O shell `aculta_lms_messenger` está habilitado. `aculta_commerce_melhorenvio` aparece habilitado, ao contrário do snapshot MAIN anterior; motivo não verificado. Tema/entidades foram consultados em leitura; o Fórum tem 5 tópicos, 5 posts e zero autorias legadas.
- Sem importação ou mutação no Runtime. Para testar código da PR, servir um docroot efêmero da branch ligado a banco isolado; não usar o symlink `public_html` nem presumir que o Runtime executa este worktree.

## Config Sync — decisão sobre Melhor Envio (2026-10-26)

- Leitura read-only no Runtime Growroom: `core.extension` possui 219 módulos; `config/sync/core.extension.yml` da PR lista 217. Os dois runtime-only são `aculta_commerce_melhorenvio` e `aculta_lms_messenger`.
- O config ativo `aculta_commerce_melhorenvio.settings` contém caminhos adicionais (`contact_email`, `environment`, `keys`, `redirect_uri`, `scopes`) que não existem no schema/default versionado desta branch. Valores não foram lidos nem registrados; só `origin.country_code` foi identificado como não vazio na seção de origem. Nenhum conteúdo operacional foi exportado.
- Decisão do responsável: manter Melhor Envio fora do Sync. Nenhum arquivo `core.extension` ou config do módulo foi alterado. Isso não autoriza desinstalar a extensão ativa; qualquer `cim` futuro exige preview explícito da remoção da extensão/config e rollback aprovado. A comparação funcional de código/schema fica encaminhada ao owner sem edição do diretório.

## M-4 — compatibilizar Mercado Pago com Webhooks assinados (2026-10-26)

- Auditoria cruzada reproduziu contradição: `RedirectCheckoutForm` acrescentava `source_news=ipn` à URL, enquanto o `WebhookGuard` do Portal aceita notificações Webhook assinadas e rejeita payload IPN legado (`topic`/`id`). A documentação oficial identifica esse parâmetro como seleção exclusiva de IPN, que não permite validar origem por assinatura.
- Removido `source_news=ipn`; o endpoint de Commerce passa a receber Webhooks modernos para validação/normalização pelo Portal antes de `onNotify()`. Unit `MercadoPagoWebhookFailClosedTest` + `MercadoPagoSubscriptionWebhookTest`: 13 testes / 19 assertions PASS, incluindo assinatura correta, assinatura forjada, recusa de IPN e adaptação das notificações suportadas. Nenhuma chamada de rede, credencial, pagamento ou Runtime.
- `aculta_pagamentos_mercadopago` avançou `0.1.0-dev.8` → `.9`; README, CHANGELOG, status e matriz reconciliados. PHP lint PASS; PHPCS Drupal/DrupalPractice no formulário: zero erros e seis avisos antigos de linhas longas. Sandbox externo, checkout, estorno e homologação visual permanecem pendentes.

## M-4 — corrigir setup da suíte Kernel LMS (2026-10-26)

- Kernel agregado inicialmente reproduziu erros de setup: testes parciais ativavam LMS sem sua closure, primeiro faltava `aculta_portal.checkout_settings`, depois a relação obrigatória com Messenger e a rota referenciada `aculta_studio.ganjalab_controller`. Nenhuma assertion de segurança havia falhado; a causa era a composição incompleta do container de teste.
- `AcultaLmsDependencyHarnessTrait` fornece somente definições para classes reais de Portal, Messenger, LMS e Studio com os argumentos do wiring de produção. Não cria classes fictícias nem instala configuração alheia aos cenários. `AcultaLmsInstallTest` também verifica a classe real CheckoutSettings, o padrão `curso` e o controller de rota real.
- Kernel LMS agregado no MariaDB dedicado: 29 testes / 835 assertions PASS. PHPCS Drupal e DrupalPractice, PHP lint dos arquivos alterados e `git diff --check` PASS. Uma depreciação upstream vem de `webform_score` contrib. Isso não prova a instalação limpa com a configuração completa da closure.
- `aculta_lms` avançou para `0.1.0-dev.91`; README, CHANGELOG, status e matriz de fechamento atualizados. Runtime permaneceu somente leitura.

## M-1 — corrigir os links do rodapé de avisos (2026-10-26)

- `MessengerBarHooks` gerava `all_url` das gavetas de avisos e mensagens com `canonicalRouteUrl('account', ...)`, que força URL absoluta pelo hostname de produção. Ambos agora usam `Url::fromRoute()` sem a opção `absolute`, preservando caminho relativo e retirando a dependência do hook em `DomainPurposeManager`.
- Messenger avançou `.401` → `.402`; README, CHANGELOG, status, matriz de fechamento e índice raiz foram sincronizados. `PersistentNoticeAccessTest`: 5 testes / 11 assertions PASS com autoloader prioritário do worktree. PHP lint, DrupalPractice, índice de 41 componentes, pacote de 41 componentes e `git diff --check` PASS.
- PHPCS Drupal nos dois arquivos alterados ainda aponta erros antigos de PHPDoc/comentários/uso não relacionado e warnings de linhas longas; o teste focal passou. Nenhuma validação foi declarada para a interface no navegador. Não houve operação Runtime.
- Próxima ação: retomar Config Sync do Messenger com a decisão de versionar defaults estruturais e preservar overrides ambientais; provar o mecanismo de override sem importar configuração no Runtime.

## M-1 — versionar defaults estruturais do Messenger (2026-10-26)

- Conforme decisão do responsável, `config/sync/aculta_messenger.settings.yml` agora inclui `email_modes` e `magazine_events` com valores idênticos a `config/install`. `private_message_digest_anchor` continua fora do Sync por ser operacional. A documentação descreve overrides em `settings.php`/`settings.local.php` e exige mapeamento das diferenças ativas antes de importação.
- `MessengerModuleInstallConfigTest` agora compara ambas as árvores instaláveis/exportadas e protege a ausência da data operacional no Sync. Em runner/autoloader da branch, o teste de contrato e `MessengerSettingsFormTest` passaram: 5 testes / 53 assertions. YAML, PHP lint, PHPCS Drupal/DrupalPractice no teste, Config Extension (1.590 checks/1.437 arquivos), pacote próprio (41) e índice (41) PASS.
- Messenger avançou `.402` → `.403`; README, CHANGELOG, status, matriz e README raiz foram sincronizados. Nenhuma configuração ativa foi lida em valores, modificada ou importada. O Runtime tem diferenças ativas já observadas somente por nomes de caminhos; preservar essas políticas requer override local conferido antes de qualquer `cim`.

## M-3 — corrigir autoria da última atividade do Fórum (2026-10-26)

- A revisão de F-D44 encontrou `PublicForumLastActivityAuthorHooks` usando `buildLegacy()` enquanto cartões de tópicos e posts usam `buildLegacyForum()`. Corrigido para exibir `-gr` também no cartão da última atividade. Autores históricos continuam sem User/Profile e sem rota individual; `/u/lendashistoricas` mantém os nomes sem sufixo.
- A omissão contradiz o contrato de apresentação do candidato RC.2, portanto o status foi reclassificado para Dev `0.1.0-dev.323`; RC.2 permanece histórico. README, CHANGELOG, roadmap, checklist RC, status, matriz e índice raiz atualizados. `LegacyAuthorDisplayNameTest` 1/1 PASS; PHPCS Drupal/DrupalPractice, PHP lint, índice de 41, pacote de 41 e diff check PASS. Não houve migration, Runtime write ou revisão visual.
- Próxima verificação: teste renderizado/Firefox ESR com fixture de autoria histórica em ambiente local isolado; em paralelo, completar o importador idempotente e ensaio efêmero Growroom antes de qualquer importação no Homelab.

## M-0 — retirar exceções contrib resolvidas do gate de slugs (2026-10-26)

- Reexecução no HEAD `e6ecf899` indicou que as 17 rotas contrib listadas na baseline DT-P20 deixaram de violar a política; o gate exige que cada correção seja removida da lista. As 17 entradas foram eliminadas sem alterar regras/rotas contrib.
- `validate-public-slugs.php`: PASS, 44 rotas, zero violações contrib na baseline. `validate-environment-addresses.php`: PASS, 4.265 arquivos. Status (41) e pacotes (41) também PASS; diff check PASS. Esse resultado substitui os snapshots históricos que ainda listavam 17 exceções.

## M-5 — isolar o bloqueio Functional de Statistics (2026-10-26)

- O primeiro ensaio BrowserKit serviu por engano a raiz do worktree, que não inclui `web/core`, e retornou 500; foi descartado como harness inválido. O teste foi repetido com Drupal 11.4.10 físico em docroot efêmero, código da PR por symlinks e banco `aculta_simpletest`; esse ambiente não usa o checkout `public_html` nem o Runtime.
- Ambos os métodos do `StatisticsDashboardFunctionalTest` falham durante compilação do container, antes de assertions: `aculta_commerce_melhorenvio.shipment_address_binder` injeta `MelhorEnvioAddressMapper`, mas o submódulo só registra o serviço `aculta_commerce_melhorenvio.address_mapper`. Evidência enviada ao owner Melhor Envio; não modifiquei diretórios de ownership delegado. Statistics permanece sem PASS Functional; README/status/matriz/CHANGELOG atualizados. Server PHP local encerrado, sem arquivo criado no `sites/simpletest` compartilhado e sem Runtime write.

## M-1 — fechar PHPCS nos arquivos alterados do Messenger (2026-10-26)

- Após corrigir os links relativos das gavetas, o PHPCS Drupal apontou PHPDoc e comentários longos preexistentes nos dois arquivos tocados. Os docblocks e comentários foram regularizados sem alteração funcional. Drupal e DrupalPractice passaram sem erros ou avisos nos arquivos; `PersistentNoticeAccessTest` passou com 5 testes / 11 assertions e PHP lint/diff check passaram.
- Commits `8b5e31043` (estilo) e `b7c806859` (links relativos) publicados em `origin/chore/aculta-m0-integridade`. Runtime não foi alterado.

## M-4 — corrigir injeção do endereço Melhor Envio (2026-10-26)

- O erro reproduzido ao compilar o container para Statistics vinha de um alias ausente: `ShipmentAddressBinder` solicita `MelhorEnvioAddressMapper` por tipo, mas o serviço de produção tinha apenas o ID `aculta_commerce_melhorenvio.address_mapper`. O owner adicionou alias de classe para o ID existente e teste que compila o YAML de serviços real; sem serviço fictício e sem mudança de Config Sync.
- Commit do owner `0f6e61ad5` foi revisado e incorporado como `4ae4229e4`. Na branch integrada, a suíte Unit completa Melhor Envio passou: 30 testes / 79 assertions; o teste novo de autowiring passou 1/1; PHP lint e PHPCS Drupal/DrupalPractice do novo teste passaram. Nenhuma chamada externa ou escrita Runtime.
- A Functional Statistics ainda não foi reexecutada depois do alias; o status permanece pendente até testar o harness isolado e todas as rotas/assertions. OAuth, cotação, contratação e etiqueta permanecem fora do incremento funcional executado.

## M-1 — rejeitar CEPs com caracteres contaminados (2026-10-26)

- `CepLookupService::normalize()` anteriormente removia qualquer caractere não numérico e aceitava entradas como `abc01310100xyz`. Agora aceita oito dígitos contínuos ou a forma com hífen/espaços prevista e rejeita letras/separadores extras; o teste cobre essas regressões.
- `CepLookupServiceTest`: 10 testes / 45 assertions PASS; gate `validate-account-address.php`, PHP lint, PHPCS Drupal/DrupalPractice focal, status index (41), packages (41) e diff check PASS. `aculta_portal_cep` avançou `0.3.0-dev.11` → `.12`; README, CHANGELOG, status, índice e closeout foram sincronizados. Sem consulta externa ou alteração Runtime.
- Commit `66e9d4d06` foi publicado em `origin/chore/aculta-m0-integridade`.

## M-5 — reexecutar Functional Statistics após alias do Melhor Envio (2026-10-26)

- O teste de wiring corrigido foi integrado; a primeira repetição com Core físico duplicado no docroot voltou a falhar antes de assertions por dupla declaração de `system_hook_info()`. A tentativa seguinte usou o Core compartilhado read-only, mas o PHPUnit ficou sem avançar por mais de 40 segundos após exibir o cabeçalho; servidor e runner foram encerrados. Não houve resultado funcional. A documentação de Statistics e a matriz agora distinguem o erro de serviço resolvido do bloqueio atual do harness, e não registram PASS.
- Não houve uso de Firefox ou validação visual. Banco dedicado isolado apenas; nenhum Runtime, dado de produção ou configuração persistente tocados.

## M-1 — revalidar o Confirmador com transporte de teste (2026-10-26)

- `AcultaMailConfirmerKernelTest` foi reexecutado no MariaDB dedicado: 5 testes / 38 assertions PASS. A suíte usa o mail collector do Core e confirma estados pendente/confirmado/cancelado/expirado, hash inválido, texto aprovado de e-mail e fila de reenvio sob cooldown; nenhuma mensagem foi entregue externamente.

## M-1 — proteger a rota de reenvio de confirmação privada (2026-10-26)

- A rota `/aculta-mail-confirmer/reenviar/{confirmation}` tinha apenas `_permission: access aculta mail confirmer`, sem invocar o handler de acesso da entidade. Isso permitia à permissão genérica acionar IDs que não pertencem à conta. A rota agora exige `_entity_access: confirmation.update` e continua exigindo `_csrf_token`; o handler de confirmações privadas limita ao proprietário ou administrador.
- Kernel lê a definição YAML da rota, cria a Route/RouteMatch e executa o checker genérico de Entity Access com entidades reais: titular permitido e outra conta negada. Confirmador 6 testes/45 assertions e User Change 2/15 PASS no MariaDB dedicado; PHPCS Drupal/DrupalPractice, PHP lint, YAML, `validate-no-temporary-route-aliases.php`, status index (41), packages (41) e diff check PASS.
- `aculta_mail_confirmer` avançou `0.1.0-dev.3` → `.4`; README, CHANGELOG, status, closeout e índice sincronizados. Nenhum SMTP externo ou Runtime foi usado.

## M-1 — revalidar CPF/CNPJ com autoload da branch (2026-10-26)

- Uma primeira Unit executada com bootstrap genérico carregou classes pelo checkout compartilhado e apresentou erro no teste de colisão SQL; não foi classificada como falha do produto. Novo bootstrap efêmero priorizou os namespaces Portal/CPF da PR e manteve Core 11.4.10 e contrib instalados: Unit CPF 48 testes / 116 assertions PASS e Kernel CPF 3 / 8 PASS em MariaDB dedicado. Nenhuma alteração de código, atualização 10002 ou Runtime write.

## Checkpoint de integridade pós-correções (2026-10-26)

- No HEAD após as correções de Messenger, Melhor Envio, CEP, Confirmador e documentação Statistics: status index 41 PASS; pacotes 41 PASS; fronteira módulo/tema 2.897 PASS; Drupal 11+ 1.160 PASS; aliases transitórios 25 arquivos PASS; slugs públicos 44 rotas/0 violações PASS; endereços 4.266 arquivos PASS; Config Extension Dependencies 1.590 checks/1.437 configs PASS.
- Esse checkpoint não encerra M-0: comparações completas de Config Sync/Runtime, RBAC Wiki, Functional Statistics, Browser e homologações por propósito continuam abertas. Módulos permanecem Dev quando suas evidências RC não estão completas.

## M-2 — revalidar a política de referências File/Media (2026-10-26)

- Com bootstrap PSR-4 explícito da branch, a suíte Unit de `aculta_portal/tests/src/Unit/Cleanup` passou 11 testes / 67 assertions (2 depreciações PHPUnit) e `FileReferenceInspectorKernelTest` passou 2 / 19 no MariaDB dedicado. A verificação cobre referências estruturadas em revisões antigas e UUID de File em rich text. Não foi chamada rotina de exclusão; Runtime permaneceu somente leitura.

## M-1 — reservar `-gr` para cartões históricos de Fórum (2026-10-26)

- Decisão do responsável: o apelido é identidade por UID; UIDs distintos não compartilham apelido; perfis por papel do mesmo UID exibem o mesmo apelido; renomear libera o anterior imediatamente; referências internas permanecem por UID; nenhum apelido de conta pode terminar em `-gr`.
- `AccountNames::isValid()` e `publicRouteSlug()` agora recusam `-gr` em qualquer caixa. O marcador continua apenas nos adapters de autoria de conteúdo do Fórum; autores Growroom não recebem User/Profile nem rota própria. `aculta_user` avançou `.80` → `.81`. User Unit 136/523 PASS (uma depreciação); PHPCS Drupal/DrupalPractice e PHP lint focais PASS. O Kernel foi encerrado antes de concluir porque o banco SimpleTest estava em uso pelo agente do Index; portanto Kernel fica pendente.
- A garantia concorrente foi separada: o responsável escolheu índice SQL derivado slug→UID, preservando Profile como fonte de verdade; implementação e testes concorrentes ainda não estão feitos. Não houve alteração Runtime.


## M-1 — convenção de apelido automático e slug público (2026-10-26)

- O responsável confirmou que colisões automatizadas podem exibir `nome#2` com slug `/u/nome-2`. Também esclareceu que `#N` pode ser digitado manualmente, desde que o slug esteja disponível; o formulário valida e rejeita colisão, sem gerar sufixo em nome do usuário. O mapeamento só reconhece sufixo numérico final (`#2` ou maior), nunca deixa `#` literal na rota. `nome#2` e `nome-2` ocupam o mesmo identificador público.
- `AccountNames`, `NicknamePolicy`, geração inicial de Profile e lookup da rota foram alinhados; OAuth recupera a parte local do e-mail quando o Social Auth precisou numerar o username. Os nomes Growroom `-gr` seguem proibidos para conta.
- AccountCreationHooks também confirma conversão de username OAuth `nome-2` em apelido `nome#2`; testes focais: 9 testes / 67 assertions PASS; suíte Unit User completa: 140 testes / 542 assertions PASS, com uma depreciação PHPUnit. PHPCS Drupal/DrupalPractice e PHP lint dos arquivos tocados PASS. O Runtime não foi alterado. A projeção SQL escolhida para concorrência, sincronização de Profiles de um UID e Kernel/MariaDB seguem pendentes; não alegar unicidade concorrente até essa fase.


## M-1 — projeção única de apelidos por UID (2026-10-26)

- `aculta_user` dev.83 adiciona tabela owner-managed `uid PRIMARY KEY, slug UNIQUE`, hook Profile transacional, sincronização de Profiles por UID, liberação imediata ao renomear, leitura de slug pela rota pública e update/rebuild 10013 validado antes de substituir o índice. Rebuild bloqueia conflitos e publica somente contagens agregadas. Runtime não foi alterado.
- User Unit completo: 140 testes / 543 assertions PASS (uma depreciação PHPUnit); PHPCS Drupal/DrupalPractice, PHP lint, gates de pacote/status, fronteira módulo/tema, Drupal 11+, aliases, slugs, endereços e dependências Config Sync PASS.
- Kernel `NicknameRegistryKernelTest`: 6 testes / 44 assertions PASS em SQLite `:memory:` e 6 / 44 PASS em MariaDB dedicado. Cobertos claims, rename/liberação imediata, chave SQL única, rebuild idempotente/conflitos e sync no mesmo UID. O fixture foi ajustado para habilitar `filter` após o primeiro erro de setup. Falta cenário de dois escritores simultâneos em processos separados e rollback transacional de falha no save. Update 10013 não foi executado em Runtime.


## M-1 — corrida concorrente da claim de apelido (2026-10-26)

- O Kernel `NicknameRegistryKernelTest` foi ajustado para carregar explicitamente os fontes do módulo no worktree isolado e evitar resolver classes de uma versão antiga do checkout compartilhado. Fixture de processo carrega o Core e o driver MariaDB instalados; não usa SQLite.
- O teste abre dois processos, ambos leem que o slug está livre, e só então libera as inserções concorrentes. Resultado MariaDB dedicado: **7 testes / 55 assertions PASS**, incluindo exatamente uma claim vencedora e uma rejeitada pela constraint. PHPCS Drupal/DrupalPractice, PHP lint dos dois arquivos e `git diff --check` PASS. As 11 depreciações reportadas vêm de Core/contrib já carregados pelo Kernel.
- `aculta_user` avançou `0.4.0-dev.83` → `0.4.0-dev.84`. O update 10013 não foi aplicado a Runtime; rollback de falha de save e instalação efêmera do update permanecem pendentes.
- A primeira invocação antes do ajuste do harness falhou porque a branch carregava classes ausentes no checkout principal e o subprocesso procurava Core no caminho errado; não é contada como resultado de produto.


## M-1 — revalidação do lookup de CEP (2026-10-26)

- O autoloader PHPUnit foi montado para apontar o namespace `Drupal\aculta_portal_cep` aos fontes do worktree. `CepLookupServiceTest`: **10 testes / 45 assertions PASS** em PHP 8.4.27; `scripts/validate-account-address.php`: **PASS** (23 checks).
- PHPCS Drupal/DrupalPractice do módulo inteiro continua **FAIL**, 144 erros/28 warnings em 11 arquivos (inclui 72 erros no JS); a varredura só de `src`/`tests` encontra 72/28 em 10 arquivos. Nenhum arquivo do módulo foi alterado por esta verificação; não reduzi cobertura nem reformatei baseline legado.
- O script Browser/X11 existente usa login por link único e submete o formulário, gravando endereço na conta. Como não foi confirmado um usuário descartável em banco/Runtime isolado, a homologação visual fica DEFERRED. Nenhuma mudança ou gravação no Runtime.
- Uma tentativa inicial de chamar `tests/run.php` no módulo CEP foi inválida: esse arquivo não existe nesse componente; não é contabilizada como teste.


## M-1 — conformidade PHP do CEP (2026-10-26)

- Os dez arquivos PHP de `aculta_portal_cep` foram revisados após a varredura PHPCS identificar PHPDocs ausentes/incompletos e formatação de arrays. A alteração preserva os contratos e a lógica; a descrição do formulário foi encurtada para caber no padrão sem fragmentar texto traduzível.
- PHPCS Drupal/DrupalPractice com extensões PHP explícitas: **PASS**, sem erros ou warnings em todo o módulo. PHP lint: PASS. `CepLookupServiceTest`: **10/45 PASS** no autoloader do worktree. `validate-account-address.php`: **PASS, 24 checks**.
- Correção da medição anterior: a varredura genérica que percorreu o módulo inteiro incluiu `cep-address.js` como PHP e gerou falsos erros (72 ocorrências); não é um resultado válido de PHPCS PHP. O arquivo JS continua coberto pelo gate funcional de endereço.
- `aculta_portal_cep` avançou `0.3.0-dev.12` → `0.3.0-dev.13`; README do proprietário não precisava de alteração. Sem Runtime write. Browser visual permanece DEFERRED: o script existente salva o endereço da conta e requer identidade descartável em ambiente isolado.

- Smoke Firefox ESR visível (`DISPLAY=:0`): `https://example.com/` carregou; `https://conta.aculta.toca.net.br/entrar` retornou erro WebDriver. Consulta read-only do resolver local e `curl` confirmou que o hostname não resolve (curl exit 6); sem sessão autenticada ou escrita. Isso não é reprovação do módulo nem aprovação visual; Browser fica DEFERRED por DNS indisponível e falta de identidade descartável.


## M-1 — reconciliação da projeção de apelidos (2026-10-26)

- O responsável definiu que, em divergência entre Profiles do mesmo UID, vence o não vazio mais recentemente alterado e seu apelido é copiado para os demais; empate de `changed` usa maior ID como desempate estável. Perfis sem apelido não apagam valor explícito. Colisão entre UIDs continua bloqueando antes de substituir o índice.
- `NicknameRegistryKernelTest`: **10 testes / 88 assertions PASS** em MariaDB dedicado. Inclui a corrida entre dois processos, rollback de save, criação do schema/update 10013, sincronização do apelido mais recente e preservação de claim existente quando UIDs diferentes colidem. User Unit: **140 testes / 543 assertions PASS**; PHPCS Drupal/DrupalPractice e lint focais PASS.
- Read-only MAIN: 26 Profiles de papel, 15 UIDs com apelido, 11 vazios; zero conflitos entre Profiles do mesmo UID e zero slugs duplicados entre UIDs. O update não foi executado no Runtime. Instalação efêmera completa com dataset sintético continua pendente. `aculta_user` avançou para `0.4.0-dev.86`.

## M-1 — preflight da projeção de apelidos (2026-10-26)

- A solução escolhida mantém Profile como fonte de verdade e usa `aculta_user_nickname` como índice derivado transacional `UID → slug`, com slug único, liberação no rename e sincronização entre Profiles do mesmo UID. A disputa concorrente e rollback da gravação têm evidência Kernel/MariaDB registrada no status `aculta_user`.
- Drush read-only em MAIN confirmou 26 Profiles de papel: 15 UIDs com apelido, 11 sem apelido, zero divergências de apelido entre Profiles do mesmo UID e zero slugs ocupados por UIDs distintos. Saída apenas agregada; nenhum valor de apelido foi impresso. Runtime não recebeu escrita.
- A política para divergência de mesmo UID foi respondida: vence o Profile não vazio mais recentemente alterado e todos os Profiles de papel recebem esse valor no rebuild. O Kernel/MariaDB cobre a chamada do update 10013 com reconciliação e preservação de claims em colisão cross-UID; nenhum update foi executado no Runtime. Instalação efêmera completa continua pendente.

## Growroom — seleção padrão e teste sintético dos filtros (2026-10-26)

- Decisão do responsável registrada como F-D46: os 616 tópicos de vida interna e os 17 marcados `revisar` ficam fora por padrão; inclusão exige seleção manual explícita. O acervo de origem não é alterado.
- `build_mapping.py` extraiu a decisão para `topico_incluivel()`; testes sintéticos cobrem exclusão da vida interna, reentrada por seleção explícita, exceções temáticas e bloqueio padrão de `revisar`. Documentação do Fórum e do plano Growroom foi reconciliada com F-D17/F-D43–F-D46; autores são entidades `aculta_legado_autor`, sem User/Profile/rota própria.
- O exportador de conteúdo/assets, importador Drupal idempotente, validação em banco efêmero e prévia/rollback ainda faltam. Nenhum acervo privado foi lido nesta unidade; nenhuma entidade ou arquivo Runtime foi criado.

## Growroom — alinhar sequência ao ownership atual (2026-10-26)

- `aculta_forum/docs/RECONSTRUCAO.md` agora descreve a sequência Drupal vigente: `aculta_legado_autor` sem Users/Profiles, tópicos ACULTA, aberturas/respostas em `aculta_forum_post`, arquivos por APIs proprietárias, reconciliação e relatório. A lista antiga de Users/Comments foi substituída sem alterar o acervo nem alegar que o importador já existe.
- Validação desta documentação: `git diff --check` PASS. Próxima etapa funcional continua sendo especificar/implementar o exportador e importador idempotente para o ambiente efêmero.


## M-4 — incorporar commits do motor `aculta_shipping` (2026-10-26)

- Cherry-pick dos 23 commits de `feat/aculta-commerce-shipping-port` na branch da PR #298, sem conflitos; nenhum commit paralelo foi reescrito. A funcionalidade foi incorporada como motor próprio de `aculta_commerce`, com entidades/plugins de envio, cotação fail-closed, pane de checkout e configuração versionada ACULTA. `aculta_commerce_melhorenvio` declara dependência do motor e permanece fora do Config Sync, conforme decisão do responsável.
- Verificação nesta sessão: Unit completa de Commerce **49 testes / 230 assertions PASS** (15 depreciações PHPUnit); `ShippingEngineKernelTest`: **6 skipped / 0 assertions** porque a classe interrompe o setup deliberadamente; lint PHP dos arquivos de shipping PASS; PHPCS Drupal/DrupalPractice nos arquivos novos PASS após corrigir o PHPDoc do provider. A verificação ampla também detectou 18 erros/17 warnings PHPCS preexistentes em `aculta_portal/src/Plugin/Commerce/CheckoutPane/BillingAddressPane.php`; o port apenas ajustou uma referência no PHPDoc desse arquivo e não provocou essas regras.
- Os commits de origem relatam um rehearsal de instalação limpa/migração em banco descartável, mas ele não foi repetido nesta integração. `composer.json`/`composer.lock` ainda incluem `drupal/commerce_shipping`; o Runtime não recebeu importação nem desinstalação, e o Config Sync ativo não foi comparado nesta sessão. A documentação foi atualizada para deixar explícitos esses limites; instalação integrada, Functional/Firefox e revisão de configuração ativa permanecem pendentes antes de qualquer cutover.
- Próxima ação: criar fixture de instalação Kernel funcional sem o skip, validar instalação limpa em banco isolado e revisar contrato do provider Melhor Envio; depois rodar preflight read-only Runtime/Sync.


## M-2 — integrar commits publicados do agente 3 (2026-10-26)

- 14 commits da branch `feat/aculta-index-fechamento` foram revisados e cherry-picked. Incluem revalidação de Entity Access por página, cursor vinculado ao inventário de providers, auditoria de gerações em descarte, fence de início de rebuild, fixture sintética representativa, testes de integração Statistics e benchmarks com caveats. Os cinco arquivos ainda não commitados no worktree do agente foram preservados e não incorporados.
- Nesta integração: Unit Index 51/282 PASS; PHP lint dos 15 arquivos alterados e PHPCS Drupal/DrupalPractice PASS. O worklog da branch reporta Kernel 118/2.001 PASS. A primeira repetição apontou erro de descoberta de módulos porque usou Core/root do checkout principal; a segunda, com docroot isolado e fontes da PR, foi limitada a 240 segundos e chegou a 63/118, sem erro reportado, mas sem resultado final. Portanto, o Kernel desta HEAD permanece não confirmado por esta execução.
- Runtime local só leitura descrito pelo agente na origem não foi repetido; nenhum rebuild, ativação, importação ou atualização foi feito. Status mantém a versão do manifesto `.175` e Dev; a revisão do WIP, Kernel integral e validação operacional permanecem pendentes.


## Integração das frentes independentes e Melhor Envio (2026-10-26)

- Incorporei por fast-forward à branch autorizada os 84 commits publicados em `feat/aculta-frentes-independentes`, preservando seus SHAs e os commits da PR; os conflitos de README/configuração foram resolvidos no worktree de ensaio. Também integrei 23 commits de `feat/aculta-commerce-shipping-port` e os 14 commits publicados do Index em pontos anteriores desta PR. Push normal confirmado para `origin/chore/aculta-m0-integridade`, HEAD `9603a5649e469bb8f1f33e1ad94d25771d0f8e00`.
- Adaptei o provider Melhor Envio ao `AcultaShippingMethod`, à remessa e à tarifa owner-managed de `aculta_commerce`; removi `commerce_shipping` da dependência Drupal do submódulo, mas mantive o pacote Composer instalado conforme decisão pendente. O submódulo segue fora do Config Sync; nenhuma alteração persistente ou chamada externa foi feita.
- A suíte Unit inteira de Melhor Envio passou nesta branch: **74 testes / 181 assertions**. PHPCS Drupal/DrupalPractice dos arquivos de provider/factory e dos testes alterados passou; PHP lint dos arquivos alterados e gates de índice (41), pacotes (41) e aliases (26 arquivos) passaram. Corrigi também o teste de definição do serviço para processar os argumentos explícitos que o YAML já declara.
- Uma primeira execução de Unit sem autoloader da branch carregou `ShippingRate` do checkout principal/contrib e falhou; ela não é contabilizada. A repetição com bootstrap temporário que prioriza os fontes da PR passou. O Kernel do provider ainda requer uma instalação de teste que carregue esta branch no discovery Drupal; permanece pendente, sem PASS presumido. Trabalho não commitado do agente Index permaneceu em seu worktree.


## M-4 — conciliação do motor Shipping na PR #298 (2026-10-26)

- Comparei `origin/chore/aculta-m0-integridade` (`541b58239`) e `origin/feat/aculta-commerce-shipping-port` (`37d56355d`); os commits-base já estavam patch-equivalentes na PR. Cherry-pick dos nove commits faltantes, com resolução de histórico no CHANGELOG: suite Kernel, transições Green Stash, revalidação do checkout, provider mock, remoção do pacote Composer, dimensões, `getCreatedTime()`, teste Config Sync e fallback de workflow.
- Reexecutado no worktree da PR: Unit Commerce **55/272 PASS**; `ShippingEngineKernelTest` **7/237 PASS** (182 depreciações Core/contrib); Unit Melhor Envio **74/181 PASS**; `ShippingConfigSyncTest` incluído; PHP lint e PHPCS Drupal/DrupalPractice dos PHP alterados PASS; Composer validate válido com avisos de constraints exatas; gates de pacote e status **41/41 PASS**. Nenhum skip permanece na suíte Shipping.
- `MelhorEnvioWiringKernelTest` teve inicialmente 9 erros de setup/0 assertions quando o harness omitia `aculta_studio`; o agente 2 adicionou a cadeia real e removeu `commerce_shipping` do fixture.
- Instalação limpa Standard com `aculta_commerce` e Melhor Envio foi executada em `aculta_simpletest`, prefixo `shipport_`: `commerce_shipping` ausente; entidades e plugins próprios e provider Melhor Envio descobertos; `drush cr` PASS e nenhum update pendente. Limpeza dos 299 nomes lógicos de tabela via Drupal Schema API; verificação final: banco de teste sem tabelas e site/prefixo descartável removidos.
- Runtime `aculta_runtime` na URI MAIN foi somente leitura: `commerce_shipping=enabled`, `aculta_commerce=enabled`, Melhor Envio habilitado, `physical=enabled` e field target ativo `commerce_shipment`. Nenhuma importação, atualização, uninstall, pedido, pagamento ou serviço externo foi executado no Runtime.
- Composer e Config Sync versionados estão alinhados ao motor próprio. Documentos Commerce/status/matriz atualizados; estado segue Dev. Firefox ESR não foi executado porque este worktree ainda não está servido no VirtualHost; checkout visual e cutover Runtime ficam pendentes.
- Limite de segurança: `site:install` recriou apenas prefixo dedicado na base de testes; no fim a base `aculta_simpletest` inteira foi limpa com autorização explícita do responsável. As credenciais do site descartável foram removidas com o `settings.php`; nenhuma credencial foi registrada em documentação/log.
- Próxima tarefa: revisar e corrigir o harness Kernel Melhor Envio no worktree do agente 2; em paralelo, preparar uma janela coordenada para aplicar a branch ao servidor de testes e executar cutover com verificação e rollback, preservando pedidos LMS/Doações.

- Gates globais repetidos nesta revisão: Config Extension Dependencies **PASS (1.578 checks/1.425 YAMLs)**; status e pacote **41/41 PASS**; aliases **26 arquivos PASS**; Admin Domain **PASS (3.504 checks)** e Payment Domain **PASS (44 checks)** quando executados por Drush com bootstrap no Runtime de testes. `validate-module-theme-boundary` **FAIL (1/3.022)** em classes de apresentação no `aculta_commerce_melhorenvio` e `aculta_links` (encaminhar ao owner Agent 2). `validate-aculta-portal-drupal11` **FAIL** porque o contrato do gate espera 14 tags `event_subscriber` no Portal (owner Portal; fora do porte). A chamada direta de gates dependentes de Drupal via PHP CLI não é resultado válido; o gate de Portal Commerce Security não foi reexecutado porque seu registro documenta efeitos temporários no gateway.

## Gate Portal — subscribers de rotas (2026-10-26)

- Revisão do commit `fd0c2199b` confirmou que `SocialAuthCallbackRouteSubscriber` implementa a regra documentada de retorno OAuth em `/integracoes/{network}/retorno`; a contagem anterior de 14 no gate estava desatualizada.
- `validate-aculta-portal-drupal11.php` agora fixa 15 subscribers e verifica a presença do serviço, classe e caminho canônico. Resultado reexecutado com Composer autoload disponível: **PASS (1.164 checks)**; índice derivado: **PASS (41 componentes)**; PHP lint e `git diff --check` PASS.


## Reexecução final do adaptador Melhor Envio na PR #298 (2026-10-26)

- Cherry-pick `e74396c00` corrigiu o harness com a cadeia real do Studio/Messenger e removeu classes do tema do controller. Commit publicado como `355c352f5`.
- Kernel `MelhorEnvioWiringKernelTest` reexecutado nesta PR no MariaDB dedicado, com Core/vendor físicos do worktree: **9 testes / 45 assertions PASS**, sem rede externa; 129 depreciações de Core/contrib. Unit reexecutada no root isolado pelo agente 2: **74 testes / 181 assertions PASS**.
- As contagens 9/44 e 73/179 vieram de execuções inválidas cujo `web/core` symlinkado resolveu o bootstrap para `/home/piradopirata/projetos/aculta`; não são falhas nem divergências do código e foram substituídas pelos resultados com root correto.
- PHPCS nos dois arquivos de código alterados: **0 errors**, com 12 avisos de linha longa preexistentes; PHP lint e `git diff --check` PASS. A fronteira módulo–tema continua falhando apenas em `aculta_links`, responsabilidade do agente 2.
- Nenhuma chamada externa, gravação ou operação de Runtime foi realizada nesta verificação.


## Reconciliar aculta_secrets_manager na PR #298 — 2026-10-26

- Integrados os 15 commits da branch `agent/aculta-secrets-manager` no conteúdo da PR. O commit `chore(claude): desativa coautoria` também foi incluído; nenhum commit da frente ficou exclusivo.
- Reexecução na PR com autoloader que prioriza os módulos do worktree: Unit Portal + Secrets 384/1.008 PASS; consumidores Mailer, Statistics, CPF, Mercado Pago e Melhor Envio 403/1.100 PASS; `SecretsRoutesKernelTest` 1/4 PASS em MariaDB dedicado. PHPCS Drupal/DrupalPractice e lint do submódulo PASS.
- Gate de contrato de segredos PASS; aliases PASS; admin Domain PASS em 3.504 checks via Drush na URI MAIN read-only. O Runtime MAIN reporta módulo Enabled `0.1.0-dev.1`; nenhuma gravação foi executada.
- PHPCS detectou inicialmente 66 erros e 20 avisos nos arquivos integrados; PHPCBF e correções manuais resolveram todos sem mudar comportamento. PHPCS final passou com zero findings.
- A revisão visual Firefox ESR e HTTP autenticado vieram da branch de origem e não foram reexecutados nesta integração. Functional, instalação limpa e rollback continuam pendentes; componente permanece Dev e não elegível a RC.
- Um comando PHPUnit padrão carregou a implementação fiscal do checkout compartilhado e falhou em dois casos gratuitos de LMS; descartado como teste da PR. Runner com namespaces do worktree foi usado e passou 384/1.008, incluindo os mesmos casos.



## Integração de Index e LMS Resources na PR #298 — 2026-10-26

- Branch `feat/aculta-index-fechamento` verificada em `98f3937e54`: 27 commits; 14 patches já equivalentes e 13 exclusivos integrados. Index avançou para `0.1.0-dev.176`, LMS Resources para `0.1.0-dev.18`; `aculta_index_file` leva o inventário a 42 módulos + tema (43 componentes).
- Testes reexecutados na PR: Unit 17/155; Kernel bridge Statistics 3/55, LMS course/video 5/259 e Index Rebuilder 21/295 PASS (46 testes / 764 assertions). O índice de status e gate de pacotes passaram com 43 componentes.
- `aculta_index_file` é opt-in, não entrou no Config Sync nem foi habilitado no Runtime. O update LMS `8001` está versionado e não foi executado. Nenhuma gravação Runtime ocorreu.
- O worktree Index da origem foi preservado. Instalação/update limpa, Browser, catálogo representativo/grants e PHPCS/lint focal pós-integração continuam pendentes; ambos os módulos seguem Dev.


## Conciliação e limpeza remota de branches — 2026-10-26

- Relatório verificável: [`ACULTA-BRANCH-RECONCILIATION-2026-10.md`](ACULTA-BRANCH-RECONCILIATION-2026-10.md).
- Removidas do remoto após equivalência e ownership verificados: `feat/aculta-frentes-independentes`, `feat/aculta-commerce-shipping-port`, `feat/aculta-me-conciliacao`, `feat/aculta-index-fechamento` e `agent/aculta-secrets-manager`. As branches locais/worktrees foram mantidos.
- `feat/aculta-melhorenvio-integracao` foi removida do remoto após a equivalência ser confirmada e as alterações locais do Kernel serem incorporadas em `cedd5cebd`. O worktree e a branch locais foram preservados; `main` e a branch da PR permanecem protegidas.
- PR #298 após integração do Index, versão/status dos componentes e limpeza: HEAD remoto confirmado `9dfbd31bed190ad6c00416201655688c2cfe16a4`; suíte de gates estáticos de status/pacotes/Config Extension Dependencies passa com 43 componentes.


## Fechamento de `feat/aculta-melhorenvio-integracao` e documentação de Shipping — 2026-10-26

- Comparei a ponta remota `da55ae5c7209f4483d27839b4a02b9130bac6c90` à PR por `git cherry` e revisão de patches. Cherry-picks publicados na PR: `7e7d04f78` (precisão monetária), `f212258dc` (testes do cliente/webhook e pendências), `22870f814` (evidência de HMAC e retries). O commit `dc460b02d` foi substituído por roadmap/status verificados porque registrava versões e estado antigos.
- Dois métodos de teste OAuth que estavam apenas como diff local em `MelhorEnvioWiringKernelTest.php` foram revistos e integrados em `cedd5cebd`: callback com state válido sem código e replay de state. A suíte completa passou na PR: Kernel **11 testes / 57 assertions PASS**, MariaDB dedicado, sem rede; Unit **85 / 204 PASS**. PHP lint passou nos quatro arquivos PHP afetados. PHPCS Drupal/DrupalPractice: zero erros, com avisos residuais de linha longa.
- Excluí apenas a ref remota `feat/aculta-melhorenvio-integracao`; `git push --delete`, fetch e `git ls-remote` confirmaram ausência. O worktree `/mnt/disco1/tmp/aculta-melhorenvio-int` e a branch local foram preservados, incluindo seu estado de trabalho.
- Alinhei manifesto e versão para `0.1.0-dev.4`; README/CHANGELOG/roadmap/pendências do adaptador, status YAML, matriz de fechamento, README/CHANGELOG/roadmap Commerce, matriz do porte, relatório de branches e este log. Atualizei as contagens e removi a afirmação de que o Kernel do Melhor Envio falha no setup.
- O provider usa o motor do `aculta_commerce`; não foi criada dependência de `commerce_shipping`. O adaptador segue fora do Config Sync. Nenhuma configuração foi importada, nenhuma escrita foi feita no Runtime e nenhuma chamada externa ou contratação de envio foi executada. Ambos os projetos permanecem Dev; checkout Functional/Browser e validação sandbox estão pendentes.
- Próximo passo: homologar o checkout e a seleção de frete no servidor de testes com Firefox ESR e sandbox sem contratar etiqueta, conforme os roadmaps Commerce e Melhor Envio.
- Limpeza local após o fechamento: removidos os artefatos próprios `aculta-me-kernel-root` (cópia isolada de Core/vendor, ~248 MB), `aculta-me-kernel-lite`, `aculta-me-kernel-out` e `aculta-me-callback-tests.patch` de `/mnt/disco1/tmp`. O worktree fonte `/mnt/disco1/tmp/aculta-melhorenvio-int` e diretórios temporários sem ownership confirmado foram preservados; nenhum Runtime ou dado de projeto foi afetado.

## Cifragem em repouso dos tokens Melhor Envio (0.1.0-dev.5) — 2026-10-26

- Base: `origin/chore/aculta-m0-integridade` em `c1e210934`, que já inclui a frente 2 (`feat/aculta-fronteira-links-secrets`). A cifragem foi reaplicada sobre essa base; não foi feito push nesta execução.
- Tokens OAuth passam a ser gravados no State API como `encrypted_v1`, com `MelhorEnvioTokenCipher` (libsodium `secretbox`, nonce novo por gravação). A chave vem do contrato central pelo Key ID `aculta_melhorenvio_token_encryption` (`ACULTA_MELHORENVIO_TOKEN_ENCRYPTION_KEY`, 64 caracteres hex, gerada com `openssl rand -hex 32`).
- Tipo de Key: `authentication` com valor hex, e não `encryption`. Motivo: o tipo `encryption` do módulo Key valida o tamanho em bytes brutos (32), enquanto o valor precisa ser lido igual pelo provedor `env` e pelo fallback de arquivo, que não decodifica base64.
- Falha fechada: chave ausente, adulteração ou formato inválido devolvem "sem tokens" (reautorização). Nenhum texto puro é devolvido nem gravado.
- `update_8001` normaliza nomes legados em Key IDs e não altera nada sem nomes legados. `update_8002` cifra registros legados uma vez, é idempotente e falha se a chave faltar (atualização pendente).
- `SecretsManager::valueForKeyId()` resolve valores pelo Key ID estável; ID ambíguo falha fechado.
- Verificação: Unit cipher 9/13 PASS; Unit `SecretsManagerTest` 15/48 PASS; Kernel `MelhorEnvioWiringKernelTest` 15/69 PASS em `aculta_simpletest` com 130 deprecações; `validate-secrets-contract.php` PASS; `validate-module-theme-boundary.php` PASS; PHPCS sem erros nem avisos nos arquivos novos.
- Divergência anterior, fora deste escopo: `validate-project-status-index.php` já falha na base `c1e210934` (`aculta_links` 0.4.0-dev.32 vs manifest 0.4.0-dev.33; `aculta_secrets_manager` 0.1.0-dev.1 vs 0.1.0-dev.3; `aculta420` 0.7.0-dev.3 vs 0.7.0-dev.4). Correção pertence à frente 2 e não foi feita aqui.
- Provisionamento: `ACULTA_MELHORENVIO_TOKEN_ENCRYPTION_KEY` em teste e produção; `MELHORENVIO_PRODUCTION_CLIENT_ID` e `MELHORENVIO_PRODUCTION_CLIENT_SECRET` em produção. Trocar a chave invalida os tokens guardados.
- Pendente: rotação de chave e migração automatizada; checkout e browser no servidor de testes.
## Troca do checkout do servidor de testes para a PR integrada — 2026-10-26

- Docroot de teste: `/home/aculta/public_html` é o checkout `/home/piradopirata/projetos/aculta` (o `web/` dele). Os testes X11 (`DISPLAY=:0`) rodam contra esse checkout.
- Autorização: mensagem do responsável do projeto na sessão de 2026-10-26, citada literalmente: "Autorizo 3, pode trocar, documente a troca na documentação e istruções de agentes". O Agente Web não autorizou a troca. O checkout saiu de `8499180a6` (detached) e foi para `c1e210934` (PR integrada, detached).
- Preservação antes da troca: branch `backup/checkout-antes-pr-8499180a6` no HEAD anterior; alteração não commitada de `docs/operations/ACULTA-MODULES-CLOSEOUT.md` guardada em `git stash` e em patch em `/home/piradopirata/worktrees/backup-checkout-20261026-1730/`; `package.json` e `package-lock.json` (não rastreados) copiados para a mesma pasta. Os não rastreados `node_modules/` e `web/modules/custom/aculta_portal/src/Plugin/Field/` foram mantidos no lugar.
- Após a troca: `drush cr` (rebuild de cache). Drush conecta em `aculta_runtime`; nenhuma atualização de banco ou configuração foi feita.
- Ressalva: `vendor/`, `web/core/` e `web/modules/contrib/` continuam os do checkout anterior (lock diferente da PR, que remove `commerce_shipping`).
- Testes visuais X11 (Firefox ESR, `DISPLAY=:0`):
  - Micropágina de links: 2/2 PASS (1280 px e 390 px).
  - Painel de segredos `/painel-administrativo/aculta/segredos`: `painel-visual.py` 16/16 PASS; `popups-visual.py` 7/7 PASS.
- Dados de teste: a micropágina `pagina-teste-mobile` foi criada por Drush (com link e item marcados `TESTE VISUAL`) e removida ao final; nenhum resíduo.
- Correção de teste: seletor da tabela de `painel-visual.py` passou a usar o atributo `data-portal-ui`, como o módulo já marca (antes acusava falso negativo).
- Pendência: o teste do painel não cobre foco visível em todas as abas; manter em rodada própria se for exigido.

## Kernel integral do Index e decisão B do ciclo Portal ↔ Mercado Pago — 2026-10-26

- **Kernel integral de `aculta_index`** no commit `c1e210934` (antes da frente 3), banco dedicado `aculta_simpletest`: 119 testes, 1685 asserções, **110 PASS, 0 SKIPPED, 0 F, 9 E**, com 56 deprecações PHPUnit e 39 de Drupal/Core.
- Os 9 erros são todos `LogicException: Circular module dependency detected at aculta_portal`:
  - `ContentIndexOwnerIntegrationKernelTest::testOwnerAdaptersAreDiscoveredByThePortalIndex`
  - `ContentIndexOwnerIntegrationKernelTest::testRealWikiOwnerBootstrapRevalidatesEntityAccess`
  - `ContentIndexWikiAccessKernelTest::testRealModulesDiscoverWikiAndMediaProviders`
  - `ContentIndexWikiAccessKernelTest::testPendingRevisionDoesNotReplacePublishedDefaultProjection`
  - `ContentIndexWikiAccessKernelTest::testWikiIncrementalUpdateDeleteAndProviderFingerprint`
  - `ContentIndexWikiAccessKernelTest::testAccessFilterFailsClosedWhenWikiDomainIsMissing`
  - `ContentIndexWikiAccessKernelTest::testFilterUsesWikiEntityAccessInCanonicalDomain`
  - `ContentIndexWikiAccessKernelTest::testRelatedEntriesRevalidateSourceAndTargetsInWikiDomain`
  - `ContentIndexWikiAccessKernelTest::testCategoryAndImageRelationsRevalidateTargetAccess`
- Não são erro de permissão nem de exec; não vêm do NTFS.
- **Decisão do responsável: opção B.** Extrair a integração do Mercado Pago para o submódulo, em fase própria. A linha 34 de `aculta_portal.info.yml` **não** foi alterada nesta execução. Causa verificada: o Portal usa o submódulo em tempo de execução (`MercadoPagoClientFactory` referencia `CheckoutPro`; `RefundEligibilitySubscriber` usa `RefundEligibilityEvent::NAME` em `getSubscribedEvents()`, chamado pelo dispatcher do Kernel), então a opção A (remover a linha e tornar os consumidores opcionais) não seria segura sem a extração.
- Sequência: o Local 3 faz o inventário B0; a fase B1 é executada depois da revisão. Após B1, o Local 3 reexecuta os 9 testes.
- Tentativa descartada: teste de boot com e sem o submódulo, com lista mínima de módulos. Falhou por dependência de serviço não declarada (`aculta_image.file_remover`), problema de declaração já existente no Portal. Não foi commitado.
- Ref remota `feat/aculta-fronteira-links-secrets`: excluída após confirmação do coordenador (Local 2 em `feat/aculta-secrets-claro`); `git cherry` vazio e ref ancestral da PR, verificados antes da exclusão.

## Integração de três entregas aprovadas e verificação do Claro — 2026-10-26

**Merges, nesta ordem, sobre `d74a6779d` (PR #298):**

- `9751b494f`: `feat/melhorenvio-token-cipher` @ `0c95bc711` (cifragem dos tokens, migração 8002 com malformado apagado e chave ausente interrompendo).
  - Testes: Unit cipher 11/19 PASS; Unit `SecretsManagerTest` 15/48 PASS; Kernel `MelhorEnvioWiringKernelTest` 15/69 PASS (130 deprecações); `validate-secrets-contract` PASS.
- `dfd3421ef`: `feat/aculta-secrets-claro` @ `f4474d5d4` (sem conflito).
  - Gates: `validate-module-theme-boundary` PASS (3036); `validate-aculta420-sdc-schemas` PASS (45 componentes, 628 checagens); `validate-aculta420-design-foundations` PASS; Foundation via Drush PASS (345); shell-contract via Drush PASS (11).
  - Testes: Unit `aculta_secrets_manager` 13/56 PASS; `SecretsManagerTest` 15/48 PASS; Kernel `aculta_secrets_manager` 1/4 PASS (62 deprecações).
- `78f5bb199`: merge de `docs/aculta-mp-extracao-b0` @ `bc1458284`, só docs (`docs/portal/MERCADOPAGO-EXTRACAO.md`).
- Commit `bcac5eab0`: versões dos status de `aculta_secrets_manager` (0.1.0-dev.4) e `aculta420` (0.7.0-dev.5), com nota de integração.

**Verificação Firefox no Claro, checkout servido em `78f5bb199`** (backup da branch `backup/checkout-antes-78f5bb1` no HEAD `c1e210934`; sem alterações rastreadas; `drush cr`):

- `claro-foco-alvos.py`: 25/28 PASS em 1280, 390 e 320 px.
- Foco por Tab alcança revelar, alterar e apagar, com foco visível. A aba recebe foco. Alvos de toque ≥ 44 px.
- **Falha:** cor do ícone de apagar `rgb(35, 36, 41)`, esperado `rgb(179, 38, 30)`. Causa verificada: `--aculta-status-error-text` e `--color-danger` estão vazias na raiz do documento do Claro. Correção para o Local 2: definir o token de erro na página do Claro. Não corrigido aqui.

**Exclusão de refs remotas:**

- Excluídas: `feat/aculta-index-validacao` (ancestral da PR; Local 3 encerrado) e `feat/melhorenvio-token-cipher` (integrada nesta execução). Verificadas com `git cherry` vazio antes da exclusão.
- Mantidas: `feat/aculta-secrets-claro` (Local 2) e `docs/aculta-mp-extracao-b0` (Local 3, em revisão de B1). `git cherry` vazio nas duas, mas o encerramento dos donos não está confirmado.

**Pendência bloqueante de implantação:** `ACULTA_MELHORENVIO_TOKEN_ENCRYPTION_KEY` **não está provisionada** no servidor de testes (nome ausente em `secrets/aculta.secrets.env` e no ambiente do shell, verificado sem imprimir valores). Nenhum `drush updatedb` pode rodar até a provisão. O `update_8002` falharia sem a chave, e a atualização ficaria pendente sem perda de dados.

**Ressalva de status:** `validate-project-status-index.php` segue FAIL por `aculta_links` (0.4.0-dev.32 contra manifest 0.4.0-dev.33), fora desta entrega. A tabela do README raiz também está desatualizada.

## Correções de cor, status e exclusão de refs; estado do Melhor Envio no servidor de testes — 2026-10-26

- **Cor do apagar no Claro:** o fallback `--color-danger` não existe no tema Claro. Trocado por `--color-maximumred`, definida em `web/core/themes/claro/css/base/variables.css` (linha 18, `var(--color-red-500)` = `#dc2323`, linha 67). A cadeia `var(--aculta-status-error-text, var(--color-maximumred, currentColor))` é mantida, sem cor literal. `SecretsTabsTest` não fixa nomes de cor, então não precisou de mudança.
- **Verificação Firefox no Claro** (`claro-foco-alvos.py`, checkout servido em `c0beca205`): **28/28 PASS** em 1280, 390 e 320 px. Cor do apagar `rgb(220, 35, 35)` nas três larguras.
- **Módulo `aculta_secrets_manager` 0.1.0-dev.5**, com CHANGELOG e status YAML atualizados.
- **Status de `aculta_links`:** `docs/operations/status/aculta_links.yml` alinhado a `0.4.0-dev.33` (igual ao `info.yml`). Índice regenerado com `php scripts/validate-project-status-index.php --write`: **PASS, 43 componentes**. A regeneração também sincronizou linhas de outros componentes com versões que já estavam nos seus status YAML (`aculta_lms_resources` 0.1.0-dev.19; `aculta_secrets_manager`, `aculta420`).
- **Exclusão de refs remotas** (após `git cherry` vazio e ancestralidade confirmada, com confirmação do coordenador de que Local 2 está na fase B e Local 3 no Messenger): `feat/aculta-secrets-claro` e `docs/aculta-mp-extracao-b0` excluídas. Antes, `feat/aculta-index-validacao` e `feat/melhorenvio-token-cipher` já tinham sido excluídas nesta execução.
- **Arquivos reservados ao Local 2 (fase B):** não editados nesta rodada. Foram tocados só arquivos de segredos, status, README e docs.
- **Servidor de testes e checkout:** o checkout servido foi para `c0beca205` (mesma autorização e mesmo procedimento de backup registrados antes: branch `backup/checkout-antes-c0beca2`, sem alterações rastreadas). `drush cr` executado.

### Melhor Envio no servidor de testes (estado registrado, sem ação)

- **Código servido:** a cifragem dos tokens (`encrypted_v1`) já está em `c0beca205`, portanto em execução no servidor de testes.
- **Consequência verificada no código:** registro legado em texto puro não é lido. `StateMelhorEnvioTokenStore::load()` devolve `NULL` (falha fechada), então o Melhor Envio aparece sem autorização até o `update_8002` cifrar o registro. Não há leitura de texto puro.
- **Gravação de novos tokens:** `save()` lança exceção sem a chave de cifragem. Uma nova autorização OAuth falha até a chave ser provisionada. Nada é gravado em texto puro.
- **Pendência bloqueante, sem ação nesta execução:** `ACULTA_MELHORENVIO_TOKEN_ENCRYPTION_KEY` **não provisionada** no servidor de testes, e `drush updatedb` **não rodado**. Os dois exigem autorização explícita do responsável. Não foram executados.
- **Ordem para quando for autorizado:** provisionar a chave no ambiente do servidor, depois `drush updatedb`, que roda o `update_8002`.

## Integração do ensaio parcial dos updates do Messenger — 2026-10-26

- Integrado por merge `feat/aculta-messenger-update-ensaio` @ `4f8694853` (só documentação: `docs/operations/ACULTA-MESSENGER-UPDATE-PLAN.md` e `docs/operations/status/aculta_messenger.yml`). Aprovado pelo Agente Web como ensaio parcial. Sem código, sem Runtime e sem alteração de banco.
- **Janela de updates do Messenger no Runtime exige snapshot integral de `aculta_runtime` antes.** Os post-updates de preferências removem chaves legadas de `users_data` e criam `avisos_email_mode_*`. A reversão exige restaurar o snapshot integral, sem rollback parcial e sem reversão por SQL improvisada. Antes da janela: snapshot consistente, restauração provada em banco isolado e cron/workers parados. O ensaio local não substitui o snapshot.
- **O ensaio fiel ao código antigo depende da fase B.** Enquanto a fase B não termina, o ensaio parcial não reproduz o estado de código anterior, por isso não serve de prova de rollback nem de janela.
- Exclusão de `feat/aculta-messenger-update-ensaio`: pendente de confirmação com o Local 3, que não foi obtida nesta execução.

## Provisão de `ACULTA_MELHORENVIO_TOKEN_ENCRYPTION_KEY` no servidor de testes — 2026-10-26

- **Autorização:** responsável, mensagem de 18:23 (apenas a chave; `updatedb` proibido). Acesso de escrita concedido pela mensagem seguinte ("Conceder acesso de escrita rodando sudo"), com `sudo setfacl` sobre o arquivo.
- **Estado de partida:** arquivo `secrets/aculta.secrets.env`, dono `aculta:aculta`, modo 640, ACL com leitura para `piradopirata` e `toca`. Variável ausente (contagem 0). Carregado por `web/sites/default/aculta.secrets.php`.
- **Backup:** `/home/piradopirata/worktrees/backup-secrets-20261026-1830/aculta.secrets.env`, fora do Git e do docroot, modo 600. Dono `piradopirata`, porque não se preserva `aculta` sem ser root; conteúdo idêntico (cmp).
- **Gravação:** uma linha acrescentada com `printf ... "$(openssl rand -hex 32)" >>`. O valor não foi exibido, não entrou em log e não ficou no histórico. Arquivo terminava em quebra de linha.
- **Incidente e correção:** após a escrita, a máscara da ACL deixou o modo 660 e o loader recusou o arquivo ("permissões muito amplas"). Corrigido: ACL do meu usuário removida, máscara `r--`, e leitura `piradopirata:r--` restaurada. Estado final igual ao original: `aculta:aculta 640`, ACL `user:piradopirata:r--`, `user:toca:r--`, `mask::r--`.
- **Verificação:** Drush, `SecretsManager::valueForKeyId('aculta_melhorenvio_token_encryption')`: comprimento 64, hexadecimal PASS. Web: aba Testes do painel de segredos mostra a linha como "preenchida", status OK (sem valor).
- **Não feito:** `drush updatedb` (proibido), autorização OAuth e qualquer alteração em tokens de State. Credenciais sandbox do Melhor Envio seguem pendentes.
- **Rollback:** restaurar o backup (exige escrita concedida ao dono) e `drush cr`.
- **Efeito:** trocar a chave invalida os tokens cifrados; reautorização obrigatória. Registros legados só são cifrados quando o `update_8002` rodar.

## Inventário de `secrets/`, decisões do responsável e integração do ensaio do `user` — 2026-10-26

- **Integração do ensaio do `user`:** merge de `feat/aculta-user-update-ensaio` @ `3c810a78a`. Só documentação (o commit toca apenas `docs/`; o resultado contra a PR altera uma linha de status de `aculta_user`). Sem código, sem Runtime, sem banco.
- **Exclusão das refs de ensaio:** `feat/aculta-messenger-update-ensaio` e `feat/aculta-user-update-ensaio` **não excluídas**. Exigem confirmação do Local 3, que não foi obtida nesta execução.
- **Barreira do Mercado Pago (somente leitura):** ambiente resolvido pelo deployer `test`; modo configurado `test`; modo efetivo do gateway `test`; variáveis resolvidas `MERCADOPAGO_TEST_PUBLIC_KEY` e `MERCADOPAGO_TEST_ACCESS_TOKEN`; credenciais de runtime presentes. Nenhum valor foi exibido. Nenhum sinal de resolução para `PRODUCTION`.
- **Permissões em `secrets/`:** `chmod 600` não aplicado (zeraria a máscara da ACL e cortaria `www-data`, `toca` e `aculta`, com processos ativos). Aplicado `674` → `670` em `credenciais_de_teste_mercadolivre` e `system`. Dono, conteúdo e ACL preservados. Estado final: `670 piradopirata:piradopirata` nos três arquivos (incluindo `producao-ssh.env`, já em `670`).
- **Verificação após a mudança:** `drush status` com bootstrap OK; loader lê a chave (comprimento 64, hex); painel de segredos 16/16 PASS no Firefox.
- **Decisões do responsável (18:32):** credenciais de produção do Mercado Pago permanecem no `secrets/aculta.secrets.env` do servidor de testes, como exceção explícita à regra "teste nunca usa chave de produção". Credencial SSH de produção (`producao-ssh.env`) permanece, sem rotação. Registrado em `SECRETS.md` e em `melhorenvio-secrets.md`.
- **Regra de escrita em `secrets/`:** somente como o dono do arquivo (`sudo -u aculta`), sem ACL de escrita concedida ao usuário de trabalho.
- **Incidente da provisão (18:25):** a ACL de escrita fez o modo ficar `660`, e o loader recusou o arquivo. Duração: alguns minutos, entre a gravação às 18:25:32 e a restauração. Modo final `640`, ACL original. **Acesso ao site na janela não verificado**: logs do Apache não legíveis pelo usuário de trabalho. Confirmação pendente pelos logs.

## Incidente: alterações não autorizadas no checkout servido e YAML inválido — 2026-10-26

- **Sintoma relatado pelo Local 3:** `web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/aculta_pagamentos_mercadopago.services.yml` inválido no checkout servido (erro de parse na linha 1: a chave `aculta_pagamentos_mercadopago.environment_override` aparece sem o cabeçalho `services:`).
- **Impacto medido:** `aculta.toca.net.br` e `loja.aculta.toca.net.br` responderam HTTP 200 antes e depois da restauração. O impacto real é de **risco**: a configuração ativa referencia serviços que só existem no arquivo quebrado (`aculta_pagamentos_mercadopago.webhook_guard`, `.webhook_subscriber`). Uma reconstrução de container (`drush cr` ou `updatedb`) em qualquer momento derrubaria o site. Nenhum erro de página foi observado.
- **Estado encontrado:** `HEAD` em `c0beca205`; sete arquivos tracked modificados e um não rastreado (o YAML). Mtimes: arquivos tracked entre 18:24:37 e 18:25:50, e o YAML às 18:25:50, todos em 2026-10-26. **Não consegui identificar o autor pelo sistema de arquivos**: a janela coincide com a provisão da chave (18:25:32), e o conteúdo é trabalho de extração do Mercado Pago (renomeação para `aculta_pagamentos_mercadopago.*`, remoção de `aculta_portal.mercado_pago_environment_override`), que é da fase B do Local 2, mas também há reformatação de comentários, típica de formatador. Autoria a confirmar pelo Local 2.
- **Preservação antes da restauração:** `/home/piradopirata/worktrees/backup-checkout-20261026-1837/` com `tracked.patch` (13 KB), `arquivos-tracked.txt` e a cópia do YAML não rastreado (`nao-rastreados/`, idêntica, verificada por `cmp`). Além disso, as alterações tracked foram guardadas em **`git stash`** com mensagem (`stash@{0}`), recuperáveis com `git stash pop`/`git stash show`.
- **Restauração:** `git stash push` apenas nos arquivos tracked modificados (sem `-u`, sem reset de histórico); YAML não rastreado removido do checkout depois da cópia verificada; `drush cr`; `drush status` com bootstrap OK; HTTP 200 nas duas URIs. Arquivos pré-existentes e não relacionados (`node_modules/`, `package*.json`, `Plugin/Field/`) não foram tocados.
- **Estado final:** `HEAD` `c0beca205`, árvore limpa nos tracked. O trabalho da fase B que estava no checkout servido **não está mais servido**: está no stash e no backup, para o Local 2 decidir como reaplicar em branch.
- **Pendências:** confirmar com o Local 2 a autoria e se a extração deve ser retomada em branch; não reaplicar no checkout servido sem revisão. Confirmar, pelos logs (não legíveis neste usuário), se houve erro de container na janela.

## Incidente de exposição de senha do Runtime e decisão de não rotacionar — 2026-10-26

- **O que aconteceu:** durante a inspeção de `secrets/aculta-runtime-db.php`, um `grep` casou a linha inteira e a senha do usuário MariaDB `aculta_runtime` apareceu na saída da sessão do Local 3 (por volta de 18:3x). O valor não foi copiado para commit, documento ou log de aplicação, e o arquivo não é versionado.
- **Decisão explícita do responsável (18:38):** a senha **não será trocada**. A rotação foi retirada dos pré-requisitos da janela de update do Runtime.
- **Regra mantida:** leitura de credenciais somente por PHP, com option-file de modo 600, sem impressão de valores. Nenhum comando de inspeção pode usar `grep` sobre arquivos de credenciais.
- **Registro:** `docs/operations/RUNTIME-UPDATE-WINDOW.md` atualizado (item de incidente, pré-requisitos e retomada). O snapshot-ensaio (`docs/aculta-runtime-snapshot-ensaio`, `24d2e0a0e`) foi integrado como documentação, sem código e sem alteração de banco.
- **Pendência:** o responsável provisiona o banco descartável (sem `aculta_simpletest`) antes de qualquer ensaio de restauração.

## Decisão do responsável sobre o modo de `secrets/` (18:39) — 2026-10-26

- Decisão explícita: `credenciais_de_teste_mercadolivre`, `system` e `producao-ssh.env` permanecem em **670**. Não aplicar 640.
- A regra de escrita em `secrets/` (somente como o dono, via `sudo -u aculta`) é procedimento dos agentes, não restrição de permissão.
- Registrado também em `docs/operations/SECRETS.md`.

## Encerramento do E0 do simulador de frete — 2026-10-26

- **E0 encerrado.** Estudo aprovado com ajustes e integrado na PR por merge de `docs/aculta-frete-simulador-e0` @ `488268aca` (só documentação: `FRETE-SIMULADOR.md`, seção 8). Sem código.
- **Ref remota `docs/aculta-frete-simulador-e0` excluída** depois de `git cherry` vazio e ancestralidade confirmada.
- **E1 depende do cutover do frete.** O porte assume o motor próprio (`ShippingOrderManager`, `ShipmentManager`, `plugin.manager.aculta_shipping_method`) e não pode importar `commerce_shipping`. Enquanto o checkout usar o contrib, o simulador fica atrás de feature flag. O cutover está em `feat/aculta-commerce-cutover` (`c8f1f8bbf`), ainda sem ensaio no banco restaurado.
- **Critério de aceite da E1 (8.7):** `canUse()` exige `validUntil`, e o default `emitido + 600 s` é preenchido em `aculta_flat_rate`, `aculta_flat_rate_per_item` e `aculta_melhorenvio` no mesmo commit, com teste Kernel de cotação no checkout para cada provedor.
- **Decisão pendente 8.6:** "exibido + revalidado" versus "persistido no carrinho". Até a decisão, a especificação assume "exibido + revalidado", sem persistir a cotação no carrinho.

## Classificação dos 101 commits do backup `backup/checkout-antes-pr-8499180a6` — 2026-10-26

Método: `git cherry -v origin/chore/aculta-m0-integridade backup/checkout-antes-pr-8499180a6`. Para cada commit com `+`, comparou-se, linha a linha, o conteúdo adicionado com o arquivo atual da PR, e conferiu-se o documento `ACULTA-BRANCH-RECONCILIATION-2026-10.md` quanto a substituições declaradas (SHAs existentes no repositório).

- **Total:** 101. **Equivalentes (`-`):** 82. **Sem equivalente por patch-id (`+`):** 19.
- **(a) Equivalentes:** 14 (o conteúdo já está na PR, com ≥ 90% das linhas ou lógica presente com texto adaptado; ex.: `f812ff495`, `8cb2fa6aa` (92%), `41edddefe`, `140ac2003`).
- **(b) Substituídos ou adaptados, documentados:** 3. `ff92ca0aa` → `698499396` e `9603a5649` (documento de reconciliação); `0945a7001` → `ba3116cb6` (documento); `8cb2fa6aa` → `a91600359` (documento).
- **(c) Exclusivos, sem equivalente nem substituição documentada:** 2, ambos só documentação, sem código faltando:
  - `d4c11717f` — `docs(melhorenvio): atualizar README, roadmap e CHANGELOG`. Arquivos: `docs/operations/ACULTA-AGENT2-WORKLOG.md`; `aculta_commerce_melhorenvio/CHANGELOG.md`, `README.md` e `docs/ROADMAP.md`. Presença no conteúdo: 84%.
  - `1b782798b` — `docs(secrets): resultados com Olivero e verificação HTTP com sessão real`. Arquivo: `docs/operations/SECRETS-SM9-INTEGRACAO.md`. Presença no conteúdo: 66%. Os resultados foram reexecutados depois e estão registrados nesta execução, mas o documento de reconciliação não os cita como substituídos.

Conclusão: nenhum commit do backup contém código exclusivo fora da PR. Os dois (c) são registros de documentação, que não entraram nos documentos atuais.

## Decisão 8.6 do simulador de frete (19:03) — 2026-10-26

- **Decidido pelo responsável:** o frete do simulador é **exibido e revalidado no checkout**. Nada é persistido no carrinho nem no pedido antes do checkout. A cotação fica só no tempstore da sessão, com expiração pelo `valid_until`.
- Consequência: o pane `aculta_shipping_information` revalida a cotação no checkout e mostra a mudança de valor antes do pagamento.
- `FRETE-SIMULADOR.md` seção 8.6 atualizada de "pendente" para "decidido".

## Conciliação local (Homelab) e cutover do frete — 2026-10-26

| Item | Classificação | Ação |
|---|---|---|
| Branches locais integrados na PR, de minha autoria (`docs/*` de 8.6, 670, incidentes, E0, Kernel do Index; `integra/*`; `fix/secrets-claro-cor-apagar`; `feat/aculta-index-validacao`) | (i) integrado | `git branch -d`, ou `-D` com verificação de ancestral da PR e `git cherry` vazio (dois casos) |
| `feat/aculta-fronteira-links-secrets` | (i) integrado | branch local apagada; worktree removido incompleto (ver abaixo) |
| Worktrees `frente3-merge-teste` e `aculta-frete-simulador-e0` | (i) integrado, limpo | `git worktree remove` sem `--force` |
| `/mnt/disco1/tmp/aculta-fronteira-links-secrets` | (i) integrado, mas remoção incompleta | **Parado.** 7.532 arquivos (376 MB) restam no diretório. Decisão pendente |
| `/mnt/disco1/tmp/aculta-token-cipher` | (iv) alterações pendentes | **Parado** (18 alterações; regra de parar). Não removido |
| `backup/checkout-antes-pr-8499180a6` | (iii) backup | Bundle verificado; branch mantida até o fim da janela de updates |
| Stashes de Fórum, Messenger, mailer (`stash@{2..9}`) | (iii) backup | Patch salvo em `backup-stashes-20261026/`; nenhum drop nesta rodada |
| `stash@{0}` (fase B do checkout servido) | (iv) conteúdo exclusivo do Local 2 | Mantido até o Local 2 confirmar a recuperação |
| Branches `agent/*`, `feat/aculta-secrets-claro`, `docs/aculta-mp-extracao-b0`, `docs/aculta-runtime-snapshot-ensaio`, `feat/aculta-messenger/user-update-ensaio` | (ii) ativo de outro agente | Não tocados |
| `d4c11717f` (backup, c) | (c) exclusivo | Descartado: README obsoleto, já classificado |
| `1b782798b` (backup, c) | (c) exclusivo | Incorporado em `SECRETS-SM9-INTEGRACAO.md` (verificação HTTP 9/9), por commit de docs |

**Cutover do frete (`feat/aculta-commerce-cutover` @ `975761aff`, publicado para revisão, sem PR):**
- `update_8001` instala as entidades do motor (idempotente).
- `post_update_migrate_shipping_methods` copia FlatRate para `aculta_flat_rate` (e `flat_rate_per_item`), preservando valores, lojas, status e peso; o original não é apagado.
- `post_update_retarget_shipments` remove só a referência de remessa do pedido, apaga remessas legadas e reaponta o campo. Pedidos não são apagados.
- A desinstalação do `commerce_shipping` é **passo explícito da janela**, não post-update: `blockersForUninstall()` vazio e depois `drush pm:uninstall commerce_shipping`.
- Kernel: 4 testes e 112 asserções PASS. PHPCS: 0 erros nos arquivos novos.
- **Não provado no Kernel:** reapontamento com campo instalado e com dados. O harness falha ao salvar pedido com remessa legada. A prova fica para o ensaio no banco restaurado do snapshot (Local 3).
- Ordem obrigatória documentada em `RUNTIME-UPDATE-WINDOW.md`: updatedb com o vendor atual → conferir → `drush pm:uninstall` → só então `composer install` com o lock da PR.

## Limpeza de branches e estado da PR — 2026-10-27

### Estado da PR

- Head de `chore/aculta-m0-integridade` antes deste registro: `37e6cc4fbe837a04973daf4a690bee0a3aca1503`.
- `650f473dc122a3474970a80422eb53501c24c133`: **não é ancestral** do head da PR.
- `b9d3ed39d0d1026143a3a48b14cb3950068f81a6`: **não é ancestral** do head da PR.
- Conclusão: a integração desses commits **ainda não aconteceu**. Nada foi integrado nesta etapa.

### Branches locais excluídas (13)

Critério: `git merge-base --is-ancestor` ou `git cherry` sem linhas `+`, e exclusão com `git branch -d`
(nunca `-D`). Remotas não foram tocadas nesta etapa.

agent/aculta-status-cleanup, backup/checkout-antes-78f5bb1, backup/checkout-antes-c0beca2,
backup/pre-sync-aculta-runtime-788154eef, docs/aculta-forum-0.1.0-dev.1, docs/ensaio-geral-resultado,
docs/ensaio-geral-rodada-2, docs/incorpora-1b782798b, docs/integra-bancos-5b5a18f,
docs/integra-snapshot-30c5450, docs/ordem-cutover-e-limpeza, docs/regra-copia-runtime, integra/fase-b-mp.

### Branches recusadas pelo `-d` (não forçadas)

- **Worktree em uso** (o `-d` recusa; as worktrees pertencem a outras frentes e não foram removidas):
  agent/aculta-magazine-a2, agent/aculta-magazine-completion-20261026, agent/aculta-me-precision-tests,
  agent/aculta-melhorenvio-a6, agent/aculta-melhorenvio-kernel, agent/aculta-mercadopago-closeout,
  agent/index-a6, agent/statistics-focal, agent/melhorenvio-service-fix, docs/aculta-mp-extracao-b0,
  feat/aculta-document-viewer-fechamento, feat/aculta-index-fechamento, feat/aculta-messenger-update-ensaio,
  feat/aculta-mp-extracao, feat/aculta-secrets-claro, feat/aculta-user-update-ensaio,
  feat/melhorenvio-token-cipher, feat/melhorenvio-token-cipher-c1e2109.
- **"not fully merged"** (o `-d` compara com o HEAD atual, destacado em `c0beca205`; a reavaliar com o
  head da PR): agent/aculta-m0-phpcs, agent/aculta-m0-phpcs-current, agent/aculta-m0-side-modules,
  agent/aculta-m0-status-audit, agent/aculta-status-current2, backup/pre-xk-c1-sync-20261022.

### Branches remotas mantidas (commits sem equivalente na PR)

docs/aculta-runtime-snapshot-ensaio (2), feat/aculta-commerce-cutover (8), feat/aculta-user-10013-contagem (2),
fix/alinhamento-sync-runtime (11), fix/barra-multidominio-alinhamento (1), fix/validador-seguranca-allowlist (3).
Remota excluída nesta rodada (critério a, ancestral da PR): `feat/aculta-mp-extracao`.

### Relatado pelo responsável (NÃO verificado pelo agente)

- Escrita de teste no Runtime: aviso sintético enviado à teste.c (mensagem 515, conversa 314), feito pelo
  procedimento de usuários de teste.
- Erro 500 no perfil do teste.d por ausência de `aculta_user_nickname` no Runtime; pendente da janela de updatedb.

Nenhum desses dois itens foi executado ou observado pelo agente nesta execução.

## Exclusão autorizada de bancos locais — 2026-10-27

Por decisão explícita do responsável, foram excluídos **sem backup** somente os quatro
bancos listados abaixo. A autorização delimitou o escopo; nenhum outro banco foi alterado.
Execução administrativa pelo comando `sudo -u mysql mariadb`, em 2026-10-27, por Local 2.

| Banco | Tabelas imediatamente antes | Resultado |
|---|---:|---|
| `aculta_id_cpf_test` | 0 | `DROP DATABASE` executado |
| `aculta_install_b2` | 0 | `DROP DATABASE` executado |
| `aculta_shipclone_20261026_a6` | 588 | `DROP DATABASE` executado |
| `aculta_runtime_copia` | 598 | `DROP DATABASE` executado |

O responsável decidiu expressamente não fazer backups prévios. A sequência foi nominal,
um banco por comando, com `SHOW DATABASES` após cada exclusão. As listas resultantes
foram, respectivamente:

```text
aculta_install_b2, aculta_runtime, aculta_runtime_copia, aculta_shipclone_20261026_a6,
aculta_simpletest, bdtgn, information_schema, mysql, performance_schema, sys, toca

aculta_runtime, aculta_runtime_copia, aculta_shipclone_20261026_a6, aculta_simpletest,
bdtgn, information_schema, mysql, performance_schema, sys, toca

aculta_runtime, aculta_runtime_copia, aculta_simpletest, bdtgn, information_schema,
mysql, performance_schema, sys, toca

aculta_runtime, aculta_simpletest, bdtgn, information_schema, mysql,
performance_schema, sys, toca
```

O `REVOKE ALL PRIVILEGES ON aculta_id_cpf_test.*` solicitado retornou `ERROR 1141`:
não havia grant correspondente naquele momento. A leitura administrativa posterior
mostrou somente `GRANT ALL PRIVILEGES ON aculta_runtime.*` para
`aculta_runtime@localhost` (o hash de senha foi omitido). Nenhum grant de outro usuário
foi alterado.

Verificação final: `SHOW DATABASES` confirmou `aculta_runtime` e `aculta_simpletest`;
`aculta_runtime` permaneceu com 588 tabelas e `aculta_simpletest` com 0. Não houve
alteração nesses dois bancos. `toca`, `bdtgn` e os bancos de sistema permaneceram na
lista e fora do escopo desta exclusão.

## Exclusão autorizada do banco local `bdtgn` — 2026-10-27

Por decisão explícita do responsável, `bdtgn` foi excluído sem backup, usando
`sudo -u mysql mariadb`. Antes do comando, a consulta administrativa confirmou o schema
`bdtgn` com 176 tabelas. `SHOW FULL PROCESSLIST` mostrou somente a própria consulta
administrativa, com `db` nulo; nenhuma sessão ativa usava `bdtgn`.

Foi executado um único comando nominal: `DROP DATABASE bdtgn;`. A verificação posterior
confirmou `bdtgn` ausente. `SHOW DATABASES` listou `aculta_runtime`, `aculta_simpletest`,
`information_schema`, `mysql`, `performance_schema`, `sys` e `toca`. As contagens finais
foram `toca` 137, `aculta_runtime` 588 e `aculta_simpletest` 0. Nenhum desses bancos foi
alterado.

## Gates verdes no head da PR — falhas pré-existentes e correções (2026-10-27)

Head da PR antes: `301441ada`. Depois dos commits de código: `14037de34`. Publicado com fast-forward, sem force.

### Falhas encontradas

1. **Foundation** (`scripts/validate-aculta420-foundation.php`): exceção do container
   `Class "Drupal\aculta_portal\Commerce\MercadoPago\MercadoPagoEnvironmentOverride" not found`.
   - Causa: cache compilado antigo do container no checkout servido. Nenhum arquivo versionado
     referencia esse namespace; o `services.yml` de `aculta_pagamentos_mercadopago` já aponta
     para `Drupal\aculta_pagamentos_mercadopago\...`, correto no commit.
   - Correção: `drush cr` no checkout servido, autorizado pelo responsável. Sem commit de código.
     Após o cache: `345 ACULTA420 Foundation checks passed.`
   - A correção de namespace prevista ("commit A") não era necessária: não havia o que corrigir.

2. **Shell-contract** (`scripts/validate-aculta420-shell-contract.php`): `Interface
   "Drupal\Core\Security\TrustedCallbackInterface" not found` em `ThemeHooks.php:26`.
   - Causa: o script usa `\Drupal::service()` sem iniciar o kernel. Carregar só o autoload
     do core não basta: falha com `ContainerNotInitializedException`.
   - Correção: commit `14037de34`. Chamado com `php` direto, o script delega ao `drush scr`,
     como o gate de Foundation. Não altera `web/themes/custom/aculta420/`.

### Correção de phpcs

- Commit `3ee8c730b`: quebra de linhas longas de comentários em `PortalUiHooks.php`.
  Zero erros e zero avisos com `Drupal,DrupalPractice`. Código conferido com `php -w`: idêntico.

### Lista de gates no head `14037de34` (saídas resumidas)

| Gate | Resultado |
|---|---|
| `git diff --check 301441ada..HEAD` | OK |
| `php -l` nos 2 PHP alterados | OK |
| `phpcs` (Drupal, DrupalPractice) | 0 erros, 0 avisos, rc 0 |
| `node tests/js/messenger-counter.test.js` | `PASS: 6 atualizações de contador e rótulo` |
| PHPUnit `UtilityBarRenderTest` (a partir do checkout no head) | `OK (1 test, 41 assertions)` |
| `validate-aculta420-sdc-schemas.php` | PASS (45 componentes, 628 checks) |
| `validate-aculta420-design-foundations.php` | rc 0 |
| `scripts/tests/validate-aculta420-design-foundations-test.php` | PASS (157 fixtures) |
| `validate-browser-validators.php` | PASS (21 checks) |
| `validate-module-theme-boundary.php` | PASS (3058 verificações) |
| `validate-aculta420-foundation.php` | `345 ACULTA420 Foundation checks passed.` |
| `validate-aculta420-shell-contract.php` | `ACULTA420 B.4 SHELL CONTRACT: PASS (11 checks)` |
| `scripts/tests/validate-aculta420-shell-contract-test.php` | PASS (19 fixtures) |

### Observação

O head da PR recebeu commits só de docs de outra origem enquanto esta sequência rodava
(`03244e611` e `301441ada`, ambos de registro de exclusão de bancos). Nenhum deles altera código.

## Limpeza de branches no head c0e51bf48 — 2026-10-27

Head da PR (`chore/aculta-m0-integridade`) usado como referência: `c0e51bf4819ac2ca0e375c42db94d4351a62d991`.
Critérios: (a) `git merge-base --is-ancestor B <head>`; (b) `git cherry <head> B` sem linhas `+`.
Exclusão somente com `git branch -d` (nunca `-D`) e `git push origin --delete` (nunca `--force`).

### a) EXCLUÍDAS

Nenhuma.

- Locais que passaram pelo critério (b), sem worktree, mas recusadas pelo `git branch -d` com
  "not fully merged" (os commits são equivalentes na PR, porém não são ancestrais do HEAD):
  agent/aculta-m0-phpcs, agent/aculta-m0-phpcs-current, agent/aculta-m0-side-modules,
  agent/aculta-m0-status-audit, agent/aculta-status-current2, backup/pre-xk-c1-sync-20261022,
  fix/phpcs-portal-ui-hooks-650, fix/phpcs-sobre-03244e611. Mantidas.
- Remota `origin/fix/barra-multidominio-alinhamento` (critério a): mantida, porque a branch local de
  mesmo nome tem worktree em uso.
- Locais integradas (critério a ou b) com worktree em uso: mantidas, sem remoção de worktree.

### b) MANTIDAS

Contagem: `git branch -r` = 9 linhas (incluindo `origin/HEAD` e `origin/main`);
`git branch` = 42 branches e 1 linha de `HEAD` destacado.

Integradas, mantidas por worktree em uso (locais):

| Branch | Worktree | Último commit |
|---|---|---|
| agent/aculta-me-precision-tests | sim | 75e90acb2 docs(aculta): record remote branch reconciliation |
| agent/aculta-melhorenvio-a6 | sim | f092c359d docs(melhorenvio): record PR test evidence |
| agent/aculta-melhorenvio-kernel | sim | e74396c00 fix(melhorenvio): complete kernel wiring and portal UI contract |
| agent/aculta-magazine-a2 | sim | ea7930cd7 test(sitemap): remove unavailable shipping module from kernel |
| agent/aculta-magazine-completion-20261026 | sim | b6c09d5ba docs(magazine): align notice contracts with implementation |
| agent/aculta-mercadopago-closeout | sim | f092c359d docs(melhorenvio): record PR test evidence |
| agent/index-a6 | sim | f092c359d docs(melhorenvio): record PR test evidence |
| agent/melhorenvio-service-fix | sim | 0f6e61ad5 fix(melhorenvio): alias mapper for binder autowiring |
| agent/statistics-focal | sim | f57b2ec8d fix(statistics): corrige cabeamento do logger e dívida PHPCS na auditoria focal |
| docs/aculta-mp-extracao-b0 | sim | bc1458284 docs(mercadopago): inventário B0 para extrair a integração do Portal |
| feat/aculta-document-viewer-fechamento | sim | 71b96d264 fix(commerce): fall back from legacy shipment workflow |
| feat/aculta-index-fechamento | sim | 98f3937e5 docs(index): record final LMS, file and runtime commits |
| feat/aculta-messenger-update-ensaio | sim | 4f8694853 docs(messenger): registra ensaio local dos updates 10008-10012 |
| feat/aculta-mp-extracao | sim | 4cadfd0e2 test(index): install nickname schema in kernel harnesses |
| feat/aculta-secrets-claro | sim | f4474d5d4 docs(portal): record Claro admin styling exception |
| feat/aculta-user-update-ensaio | sim | 3c810a78a docs(user): registra ensaio local do update 10013 |
| feat/melhorenvio-token-cipher | sim | f2bdf8fbe docs(aculta): record local test artifact cleanup |
| feat/melhorenvio-token-cipher-c1e2109 | sim | 0c95bc711 fix(melhorenvio): migração 8002 apaga legado malformado e exige chave |
| fix/barra-multidominio-alinhamento | sim | 650f473dc Corrige alinhamento dos controles da barra multidomínio |

Com commits sem equivalente na PR (mantidas, todas com worktree em uso, exceto a de backup):

| Branch | Commits sem equivalente | Worktree | Último commit |
|---|---|---|---|
| agent/aculta-secrets-manager | 2 | sim | b024f5bc4 test(secrets): revelação com valor fictício |
| backup/checkout-antes-pr-8499180a6 | 19 | não | 8499180a6 test(secrets): revelação com valor fictício |
| docs/aculta-runtime-snapshot-ensaio | 2 | sim | 1b71c12a3 docs(ops): corrige registro da exclusão em aculta_install_b2 |
| docs/barra-multidominio-janelas | 4 | sim | a7eee4caa Registra validação da barra com contas de teste |
| ensaio/geral-local | 14 | sim | 15e7c7b5c Merge remote-tracking branch 'origin/fix/alinhamento-sync-runtime' |
| feat/aculta-commerce-cutover | 8 | sim | 5f139df4d fix(commerce): reaponta referência de remessa por SQL |
| feat/aculta-commerce-shipping-port | 3 | sim | 37d56355d fix(commerce): fall back from legacy shipment workflow |
| feat/aculta-frentes-independentes | 12 | sim | 9f7a09f8e fix(checkout): não gravar o pedido ao copiar o endereço de cobrança |
| feat/aculta-me-conciliacao | 4 | sim | d410ef313 fix(melhorenvio): binder autowired e alias do mapeador |
| feat/aculta-melhorenvio-integracao | 1 | sim | da55ae5c7 test(melhorenvio): cobrir falhas da cotação e o controller do webhook |
| feat/aculta-user-10013-contagem | 2 | sim | 46dffd76b docs(runtime): corrige frase sobre gravação em perfis do 10013 |
| fix/alinhamento-sync-runtime | 11 | sim | 596fa9d65 docs(operations): registra remoção esperada da view do contrib no uninstall |
| fix/validador-seguranca-allowlist | 3 | sim | f9eef8472 test(security): remove segunda checagem de estado fixo do gateway MP |

Remotas mantidas (commits sem equivalente na PR): `origin/docs/aculta-runtime-snapshot-ensaio` (2),
`origin/feat/aculta-commerce-cutover` (8), `origin/feat/aculta-user-10013-contagem` (2),
`origin/fix/alinhamento-sync-runtime` (11), `origin/fix/validador-seguranca-allowlist` (3).

## Exclusão da remota origin/fix/barra-multidominio-alinhamento — 2026-10-27

- Head da PR usado como referência: `bc17fff3e5419eeec9adf37448979f79b964e08d`.
- Critério: (a) `git merge-base --is-ancestor origin/fix/barra-multidominio-alinhamento <head>`
  → ancestral: sim (o commit era `650f473dc`).
- Comando: `git push origin --delete fix/barra-multidominio-alinhamento` (sem force).
- Verificação: `git ls-remote --heads origin | grep -c fix/barra-multidominio-alinhamento` → `0`.
- Não houve remoção de worktree nem alteração de arquivos nesta etapa.

## Classificação das remotas no head df444b820 — 2026-10-27

Head da PR usado: `df444b820a064629145b420bdc1847e573bed9b1`.
Critério: (a) `git merge-base --is-ancestor origin/R <head>`, ou (b) `git cherry <head> origin/R` sem linhas `+`.
Nenhuma remota passou pelo critério: **nenhuma excluída nesta etapa**. `origin/HEAD` e `origin/main` não foram analisados.

| Remota | Commits sem equivalente na PR | Último commit |
|---|---|---|
| origin/docs/aculta-runtime-snapshot-ensaio | 2 | 1b71c12a3 docs(ops): corrige registro da exclusão em aculta_install_b2 e documenta instalação bloqueada |
| origin/feat/aculta-commerce-cutover | 8 | 5f139df4d fix(commerce): reaponta referência de remessa por SQL, sem salvar o pedido |
| origin/feat/aculta-user-10013-contagem | 2 | 46dffd76b docs(runtime): corrige frase sobre gravação em perfis do 10013 (reconciliação) |
| origin/fix/alinhamento-sync-runtime | 11 | 596fa9d65 docs(operations): registra remoção esperada da view do contrib no uninstall |
| origin/fix/validador-seguranca-allowlist | 3 | f9eef8472 test(security): remove segunda checagem de estado fixo do gateway MP |

Verificação: `git ls-remote --heads origin` lista as branches remotas mantidas e `chore/aculta-m0-integridade`.

## Publicação do candidato a9bfe7820 e limpeza de branches — 2026-10-27

### Publicação

- Head antes: `6b28136d3`. Publicado: `a9bfe7820` (fast-forward, sem force). `6b28136d3` é ancestral de `a9bfe7820`.
- Checkout servido: `a9bfe7820`.
- Descrição da PR 298: seção "Estado do candidato publicado (a9bfe7820)" acrescentada, sem alterar o conteúdo existente. Atualização via API REST, porque `gh pr edit` falhou com rc 1 por aviso de Projects clássicos.
- Conflitos de cherry-pick: somente em `docs/operations/RUNTIME-UPDATE-WINDOW.md`, resolvidos mantendo os dois lados (3 ocorrências).

### Gates no candidato a9bfe7820

Vermelhos (pendentes de decisão do responsável):

- `validate-portal-commerce-security.php` (via `drush scr`): `No test or real Commerce orders remain.`, rc 1. Pedidos reais ou de teste no Runtime de teste: `doacao` 14 rascunhos; `default` 1 rascunho; 1 pedido sem flow.
- phpcs (Drupal, DrupalPractice) nos 6 PHP alterados: 112 erros e 23 avisos no validador de segurança; avisos em `LegacyShippingCutover.php` (24), `ShippingCutoverKernelTest.php` (16), `ShippingMethodBase.php` (1), `NicknameRegistry.php` (1), `NicknameRegistryKernelTest.php` (2). Não corrigido.

Verdes:

- `git diff --check 6b28136d3..a9bfe7820`: limpo.
- `php -l` nos 6 PHP alterados: sem erros.
- `messenger-counter.test.js`: `PASS: 6 atualizações de contador e rótulo`.
- PHPUnit `UtilityBarRenderTest` (configuração do core): `OK (1 test, 41 assertions)`.
- `validate-aculta420-sdc-schemas.php`: `PASS (45 components, 23 include calls, 628 checks)`.
- `validate-aculta420-design-foundations.php`: rc 0; teste: `PASS (157 expected outcomes)`.
- `validate-browser-validators.php`: `PASS (21 checks)`.
- `validate-module-theme-boundary.php`: `PASS (3060 verificações)`.
- `validate-aculta420-foundation.php`: `345 ACULTA420 Foundation checks passed.`
- `validate-aculta420-shell-contract.php`: `PASS (11 checks)`; teste: `PASS (19 fixtures)`.

Não executados:

- Testes Kernel alterados (`ShippingCutoverKernelTest`, `NicknameRegistryKernelTest`): sem `SIMPLETEST_DB` no ambiente. A credencial fica em `secrets/`, que não foi lida.

### Gatilho de banco: não executado

- `drush updatedb:status` (somente leitura): 20 pendências, incluindo `aculta_commerce_melhorenvio` 8001/8002, `aculta_id_cpf` 10002, `aculta_lms_resources` 8001, Messenger 10008 a 10012, `aculta_user` 10013 e post-updates de remessas e de Messenger.
- `drush config:status` (somente leitura): 209 itens. 100 só no banco, 76 diferentes, 33 só no `config/sync`. Um `config:import` apagaria itens presentes só no banco; não foi feito.
- Snapshot, `updatedb`, `config:import`, `pm:uninstall` e `DROP`: não executados.
- Escrita de cache: `drush cr` no checkout servido, em etapa anterior e autorizada.

### Verificação do conteúdo das branches remotas no head publicado

Critério: ancestral do head, ou arquivos alterados pela branch idênticos no head. Para `RUNTIME-UPDATE-WINDOW.md`, os títulos de seção da branch precisam existir no head.

| Remota | Ancestral | Arquivos da branch | Resultado |
|---|---|---|---|
| origin/docs/aculta-runtime-snapshot-ensaio | não | 1 (doc) | 12 títulos presentes: contida |
| origin/feat/aculta-commerce-cutover | não | 7 (código) | idênticos: contida |
| origin/feat/aculta-user-10013-contagem | não | 6 | 5 idênticos; doc com 14 títulos presentes: contida |
| origin/fix/validador-seguranca-allowlist | não | 1 | idêntico: contida |
| origin/fix/alinhamento-sync-runtime | não | 10 | 9 idênticos; doc com título "## Estado do ensaio: BLOQUEADO no passo 2" ausente no head: **MANTIDA** |

### Exclusões

- **Remotas excluídas** (`git push origin --delete`, sem force): `docs/aculta-runtime-snapshot-ensaio`, `feat/aculta-commerce-cutover`, `feat/aculta-user-10013-contagem`, `fix/validador-seguranca-allowlist`.
- **Remotas mantidas:** `fix/alinhamento-sync-runtime` (título ausente no head, arquivo `RUNTIME-UPDATE-WINDOW.md`).
- **Locais excluídas:** nenhuma. As ancestrais do head e as branches da lista têm worktree, e a regra manda não removê-las. A única sem worktree e não contida, `backup/checkout-antes-pr-8499180a6`, fica.
- **Locais mantidas com worktree em uso (ancestrais do head):** `agent/aculta-me-precision-tests`, `agent/aculta-melhorenvio-a6`, `agent/aculta-mercadopago-closeout`, `agent/index-a6`, `analise-integracao`, `feat/aculta-document-viewer-fechamento`, `feat/aculta-mp-extracao`, `feat/melhorenvio-token-cipher`.
- **Locais mantidas com worktree em uso (não contidas):** `agent/aculta-magazine-a2`, `agent/aculta-magazine-completion-20261026`, `agent/aculta-melhorenvio-kernel`, `agent/aculta-secrets-manager`, `agent/melhorenvio-service-fix`, `agent/statistics-focal`, `docs/aculta-runtime-snapshot-ensaio`, `docs/barra-multidominio-janelas`, `ensaio/geral-local`, `feat/aculta-commerce-cutover`, `feat/aculta-commerce-shipping-port`, `feat/aculta-frentes-independentes`, `feat/aculta-me-conciliacao`, `feat/aculta-melhorenvio-integracao`, `feat/aculta-user-10013-contagem`, `fix/alinhamento-sync-runtime`, `fix/validador-seguranca-allowlist`.
- Worktrees: nenhuma removida nesta etapa. Esta registro usa uma worktree temporária, removida ao final.

### Contagem final

- `git branch -r`: 4 linhas (`origin/HEAD`, `origin/chore/aculta-m0-integridade`, `origin/fix/alinhamento-sync-runtime`, `origin/main`).
- `git branch`: 28 branches locais, mais a linha de `HEAD` destacado do checkout servido.

## Incidente de worktrees em /mnt/disco1 — identificação e recuperação (2026-10-27)

- Gate de processo: lsof +D /mnt/disco1/worktrees foi interrompido após demora na travessia NTFS; a consulta somente leitura fuser -vm /mnt/disco1/worktrees não encontrou PID de processo usuário. A saída listou somente root mount /mnt/disco1 e kernel.
- Local 3 identificou as seis pastas antigas abaixo. Dono em todas: piradopirata:piradopirata. Os tamanhos são bytes somados dos arquivos presentes; as contagens incluem dependências instaladas. Extras fora da árvore da branch: vendor/ (90.974 arquivos) e web/core/ (42.398) em Melhor Envio A6, Melhor Envio Kernel e Magazine; nenhum extra em Mercado Pago closeout e Index; web/core/ (42.398) em Statistics. Não foram encontrados extras de código/configuração, logs, dados, build ou anotação. As árvores de dependências são regeneráveis pelo Composer/Core fixados no projeto.
- Busca textual nos registros worktrees/out/ e backup-* por estes diretórios e /mnt/disco1/worktrees não encontrou menções. Não foi encontrada cópia com mesmo nome em /home/piradopirata/worktrees/. O bundle backup-bundles/checkout-antes-pr-8499180a6.bundle (SHA-256 43fff26f19ff6f7f70d14b3d934aacf4fb05494e59d1da107147fbc9e2678196) não contém o histórico de nenhuma das seis refs. Patches de checkout/stash existentes não são cópias completas das pastas.
- Comparação somente por nomes: cada pasta antiga estava sem os mesmos 1.392 caminhos rastreados de sua árvore; a lista comum às seis está em RECUPERACAO-WORKTREES-20261027-MISSING-PATHS.txt, SHA-256 da lista c514ea7b4a287112ebb307e2fcfbb142bc5e17c94ae5d06e889e85b78a2bd0fe. A maior parte (1.382) está sob config/. Nenhum arquivo antigo foi copiado para as novas worktrees.
- As seis worktrees foram recriadas em ext4 a partir das refs locais, sem composer install; validação por worktree confirmou git status limpo, 8.887 caminhos em git ls-tree -r e HEAD igual à branch. Nenhum conteúdo de /mnt/disco1/worktrees/ foi alterado.

| Branch | Worktree recuperada | HEAD | Rastreados | Status | Pasta antiga | Arquivos presentes antigos | bytes antigos | mtime antigo | Extras antigos |
|---|---|---|---:|---|---|---:|---:|---|---|
| agent/aculta-melhorenvio-a6 | /home/piradopirata/worktrees/recuperada/aculta-melhorenvio-a6 | f092c359dfc1e4970174b43a0359f6d7cdce5451 | 8.887 | limpa | /mnt/disco1/worktrees/aculta-melhorenvio-a6 | 140,867 | 1,055,580,266 | 2026-10-27 09:36:45 -0300 | vendor 90974; web/core 42398 |
| agent/aculta-melhorenvio-kernel | /home/piradopirata/worktrees/recuperada/aculta-melhorenvio-kernel | e74396c0084a7561b0c261cfa35172c4e854dd7c | 8.887 | limpa | /mnt/disco1/worktrees/aculta-melhorenvio-kernel | 140,867 | 1,055,577,749 | 2026-10-27 09:36:50 -0300 | vendor 90974; web/core 42398 |
| agent/aculta-magazine-completion-20261026 | /home/piradopirata/worktrees/recuperada/aculta-magazine-completion-20261026 | b6c09d5ba52e1c37509142599f4d27d1aa5bb3f3 | 8.887 | limpa | /mnt/disco1/worktrees/aculta-magazine-completion | 140,867 | 1,055,580,601 | 2026-10-27 09:36:58 -0300 | vendor 90974; web/core 42398 |
| agent/aculta-mercadopago-closeout | /home/piradopirata/worktrees/recuperada/aculta-mercadopago-closeout | f092c359dfc1e4970174b43a0359f6d7cdce5451 | 8.887 | limpa | /mnt/disco1/worktrees/aculta-mercadopago-closeout | 7,495 | 376,358,492 | 2026-10-27 09:37:08 -0300 | nenhum |
| agent/index-a6 | /home/piradopirata/worktrees/recuperada/index-a6 | f092c359dfc1e4970174b43a0359f6d7cdce5451 | 8.887 | limpa | /mnt/disco1/worktrees/aculta-index-a6 | 7,495 | 376,358,492 | 2026-10-27 09:37:20 -0300 | nenhum |
| agent/statistics-focal | /home/piradopirata/worktrees/recuperada/statistics-focal | f57b2ec8dc6bf8b0ef04d4f2dcb5e32df2378017 | 8.887 | limpa | /mnt/disco1/worktrees/aculta-statistics-focal | 49,893 | 543,539,365 | 2026-10-27 09:37:25 -0300 | web/core 42398 |

## Compra sem CAPTCHA em produto, checkout e frete — 2026-10-27

- HEAD de trabalho: `73ccf6ad1`, branch `chore/aculta-m0-integridade`; sem troca de HEAD, publicação ou importação de configuração.
- Código: a exceção do CAPTCHA agora inclui IDs dinâmicos de add-to-cart, carrinho e todas as etapas `commerce_checkout_flow_*`; ativar Turnstile mantém os pontos de compra desligados. A continuação de visitante para novo e-mail é renderizada mesmo com configuração de checkout ativa antiga divergente.
- Testes: `GuestCheckoutRegistrationConfigTest` 6/62 PASS; `TurnstileToggleKernelTest` 3/11 PASS em `aculta_simpletest` (5 deprecações do contrib CAPTCHA/Key); PHPCS Drupal/DrupalPractice, PHP lint, `validate-turnstile-exceptions.php` (11 exceções) e índice do README PASS.
- Firefox ESR `DISPLAY=:0`: produto, carrinho, e-mail/cadastro AJAX e pane de endereço/frete sem CAPTCHA. O fluxo avançou até `/finalizar-compra/139/informacoes-do-pedido`. Não foram enviados endereço ou pedido, nem solicitada cotação do transportador ou iniciado pagamento. Os testes criaram carrinhos Commerce anônimos de rascunho (incluindo pedidos 138 e 139); nenhum foi removido.
- `drush cr` foi executado (escrita somente em cache). Gatilhos Runtime somente leitura: `updatedb:status` continua com 17 pendências; `config:status` continua divergente em aproximadamente 132 itens. Nenhuma atualização de esquema, importação ou limpeza dos pedidos de teste foi executada.

## Sanitização de diagnósticos Mercado Pago — 2026-10-27

- Regressões primeiro falharam ao encontrar mensagem de exceção privada nos logs de ação recorrente; após a correção, a suíte MP inteira passou.
- Checkout de preferência remove `payer` dos logs de debug e registra falhas somente pelo status HTTP ou classe genérica de transporte. Ações de assinatura não registram mensagem/corpo e não encadeiam a exceção remota no erro neutro.
- Unit MP: 156 testes / 502 assertions PASS; PHPCS Drupal/DrupalPractice e PHP lint dos PHP alterados PASS. Nenhuma credencial foi lida, chamada externa ou escrita Runtime executada nesta microfase.

## Gateway ativo no teste Kernel Mercado Pago — 2026-10-27

- `CheckoutProTest` passou a salvar `mercado_pago` com `status: true` em modo `test`. Chaves sintéticas existem somente no processo do teste; `EntitySaveHooks` as remove antes da persistência, verificado pelas assertions.
- Kernel `CheckoutProTest`: 1 teste / 53 assertions PASS em `aculta_simpletest`, 77 depreciações upstream; PHPCS Drupal/DrupalPractice e PHP lint PASS. Nenhuma chamada ao SDK ou serviço externo.

## Terminologia de Webhooks Mercado Pago — 2026-10-27

- `CheckoutPro` descreve os callbacks modernos assinados como Webhooks; IPN só é citado ao explicar a recusa do protocolo legado.
- Unit MP 156/502, PHPCS Drupal/DrupalPractice nos PHP alterados e PHP lint PASS. Nenhuma chamada externa.

## Limpeza autorizada dos carrinhos do Runtime — 2026-10-27

- Antes da ação, `hostname` = `toca`; Drush confirmou Drupal 11.4.10, URI `http://default`, raiz `/home/piradopirata/projetos/aculta/web` e banco `aculta_runtime`. HEAD `f56136243`, branch `chore/aculta-m0-integridade`.
- Integridade do banco: 588 tabelas. Antes: 9 pedidos, todos carrinhos `default/draft`; 9 itens em carrinhos; 0 pagamentos associados; nenhum pedido fora de carrinho.
- Snapshot anterior à escrita: `/home/piradopirata/aculta-backups/aculta-runtime-before-cart-cleanup-20261027-153416/runtime.sql`, 93.332.258 bytes, modo `0600`, diretório modo `0700`; SHA-256 `82f517076f078f0c12f625f85b3b47662aef6a0c3899eba30ee9a46b2dea9edd`.
- Ação autorizada: `commerce_cart.cart_manager->emptyCart()` em 9 pedidos de rascunho; removeu 9 itens, mantendo os pedidos vazios. Nenhum pagamento, pedido não-carrinho ou estado de pagamento foi alterado.
- Verificação posterior: 9 carrinhos, 0 carrinhos com itens, 0 itens, 0 pagamentos associados; 9 pedidos totais e 0 pedidos fora de carrinho. `aculta_runtime` não teve updates de esquema nem importação de configuração nesta ação.

## Reconciliação do head da PR no checkout autorizado — 2026-10-27

- O único checkout usado foi `/home/piradopirata/projetos/aculta`, na branch `chore/aculta-m0-integridade`; nenhum worktree adicional foi criado ou alterado.
- Antes da reconciliação, `git fsck --full --no-reflogs --no-dangling` terminou sem erros. As mudanças locais anteriores já estavam commitadas; a branch local foi limpa e estava 11 commits à frente e 1 atrás da referência remota local.
- A referência remota local continha `79009df60`, com avanços de Avatar/LMS/User. O merge sem conflitos preservou os commits locais de Commerce/Mercado Pago e produziu `f37450251`. Estado observado: 12 commits à frente; nenhuma publicação foi feita.
- No Runtime `toca` (Drupal 11.4.10, URI `http://default`, banco `aculta_runtime`), `updatedb:status` não indicou updates pendentes. `config:status` segue divergente em frentes não reconciliadas; por isso nenhum `updatedb` ou `config:import` foi executado.
- `drush cr` foi executado no checkout servido. A verificação HTTP via hostname `default` falhou por resolução DNS local (`curl: Could not resolve host: default`); não houve requisição bem-sucedida a MAIN/Loja nesta etapa. O status do Drush confirmou raiz, URI e conexão Runtime esperados.

## Renomeação do host da Revista — 2026-10-28

- Decisão do responsável: produção `revista.aculta.org`; Homelab `revista.aculta.toca.net.br`. O Cloudflare de produção permanece a cargo do responsável. O Domain técnico `revista420_aculta_org` foi preservado; os IDs dos aliases passaram a refletir seus novos hosts.
- A PR #298 recebeu `e1a704cbf` (documentação das variáveis já declaradas no contrato de segredos, necessária para o gate do Deployer) e `b2874bc79` (configuração e consumidores do novo domínio). O head remoto avançou depois para `e34bc2c06` com documentação da frente OpenCulturas, sem conflito com esta alteração.
- Checkout servido: host `toca`, raiz `/home/piradopirata/projetos/aculta`, URI `http://default`; HEAD anterior `d16ebd063`, HEAD após a atualização `b2874bc79` (destacado). Antes da troca, os seis arquivos modificados do outro agente foram copiados para `/var/backups/aculta-domain-change-20261028/root-worktree.patch` (SHA-256 `b80a18d77ea3c76bff73a0ebc85e4ec9b7d0cd7bb5b8b660b80940a37995260a`); a referência `backup/pre-revista-domain-rename-20261028` preserva `d16ebd063`. As mudanças permaneceram visíveis e não foram incluídas nos commits desta frente. Arquivos que o outro agente adicionou depois também permaneceram intactos.
- Banco: `aculta_runtime`, 607 tabelas. `updatedb:status`: `[success] No database updates required.` `config:status` mostrou divergências preexistentes em outras áreas, portanto nenhum `config:import` global foi executado. Snapshot anterior às escritas: `/var/backups/aculta-domain-change-20261028/aculta_runtime.sql`, SHA-256 `318644fb01e5e016197b9e1696f1dda66904e67b7fddfb76d7d6156474dbb225`.
- Pela API de entidades do Drupal, o Domain `revista420_aculta_org` passou a `revista.aculta.org`; os aliases ativos foram renomeados para `revista.aculta.toca.net.br` (homelab) e `revista.aculta.test:8080` (local). Os três valores ativos foram comparados com `config/sync` e coincidem. `drush cr` foi executado; não houve update de esquema nem importação de configuração.
- No Virtualmin, o alias legado `coletivo420.aculta.toca.net.br` foi renomeado para `revista.aculta.toca.net.br` com `modify-domain`; os vhosts próprios `revista420-aculta-http` e `revista420-aculta-ssl` foram desativados. O certificado Let's Encrypt multi-SAN de `aculta.toca.net.br` foi emitido novamente e inclui `revista.aculta.toca.net.br`, válido até `2027-01-26 09:43:33 UTC`. O certificado exclusivo antigo foi salvo no backup e removido do Certbot. `apache2ctl configtest`: `Syntax OK`; Apache recarregado.
- `settings.local.php` teve apenas `trusted_host_patterns` ajustado: entradas de `coletivo420` e `revista420` removidas, `revista.aculta.toca.net.br` adicionada. Cópia anterior em `/var/backups/aculta-domain-change-20261028/settings.local.php.pre`, SHA-256 `64cd6074cb02bb4a1632d76d614c827156d9d5889869787b1eca5a236801abef`.
- Verificações HTTP após a mudança: Revista `200`, TLS verificado (`ssl_verify=0`) e `X-Robots-Tag: noindex, nofollow, noarchive`; MAIN `200` e Loja `200`, também com TLS verificado. O Domain Negotiator selecionou `revista420_aculta_org` no host novo.
- Gates focais: Deployer `tests/run.php` PASS; `validate-domain-front-paths.php` PASS (16 checks); `validate-project-status-index.php` PASS (43 componentes); YAML dos três configs e JSON válidos; PHP lint e `git diff --check` PASS.
- Manifesto SHA-256 dos 61 arquivos de backup do banco, Virtualmin, Apache, certificados e configuração local: `/var/backups/aculta-domain-change-20261028/manifest.sha256`, hash `1e088d9ded64667c526068b80dcf23d04bc2091c5a097366f5139a27ffa00b76`.
- O outro agente sincronizou o checkout servido até `e34bc2c06`. Antes de atualizar para o head da PR `dcaa565ad`, o patch do trabalho local foi novamente salvo em `/var/backups/aculta-domain-change-20261028/root-worktree-before-dcaa.patch` (SHA-256 `b80a18d77ea3c76bff73a0ebc85e4ec9b7d0cd7bb5b8b660b80940a37995260a`), a referência `backup/pre-dcaa-pr-docs-20261028` preservou `e34bc2c06`, e um objeto stash de backup foi criado sem limpar o checkout. O HEAD passou a `dcaa565ad`; os seis arquivos modificados continuaram visíveis e fora dos commits desta frente.
- Após essa segunda troca de head, o banco continuou com 607 tabelas. `updatedb:status`: `[success] No database updates required.` `config:status` manteve divergências preexistentes, sem linhas para o Domain ou aliases da Revista; nenhum import foi executado. `drush cr` completou. Revista, MAIN e Loja responderam HTTPS 200, `ssl_verify=0`; a Revista manteve `X-Robots-Tag: noindex, nofollow, noarchive`. `apache2ctl configtest`: `Syntax OK`.

## Ativação seletiva das Views E2 e do OSM E10 — 2026-10-28

- Autorização do responsável: preparar e executar ativação seletiva após revisar o diff. Checkout servido em `toca`, branch `refactor/opencultures-codigo-visual`, URI Drush `http://default`, root `/home/piradopirata/projetos/aculta/web`; HEAD antes da ativação `cb577ab21`, commit de sync após a revisão `0f1f384d8`. Não houve troca de checkout.
- Banco confirmado antes da operação: `aculta_runtime`, 605 tabelas. `drush updatedb:status`: `[success] No database updates required.` `config:status` global tinha 338 linhas de diferenças, em grande parte fora desta frente. O filtro focal listou somente as duas Views de eventos ausentes no Runtime. Cinco configs Key OSM no sync usam provedor `env` e `key_input_settings.key_value` vazio; nenhuma foi importada.
- O diff parcial continha somente `views.view.eventos_locais` e `views.view.eventos_datas_relacionadas`, ambos do `config/sync`. O comando `drush config:import --partial --source=<diretório temporário com os dois YAMLs> --diff` mostrou a prévia e prosseguiu com a importação; `--diff` não foi um modo de simulação. A importação criou apenas essas duas Views. **Não havia snapshot antes dessa importação.** Registro explícito da falha de sequência; os campos de conteúdo das Views ficaram mínimos e os testes negam acesso anônimo.
- Antes da ativação do submódulo OSM, foi criado snapshot do estado Runtime já contendo as Views: `/home/piradopirata/aculta-backups/aculta-runtime-opencultures-activation-20261028/aculta_runtime-before-osm-enable.sql`, tamanho 53.901.807 bytes, SHA-256 `ac1e159d26a7a78794980a43aa0a886a129cbe72b739205937172536514d6c01`. Esse snapshot não representa o estado anterior à importação das Views.
- `drush pm:enable aculta_events_openstreetmap -y` concluiu com sucesso; instalou `field_osm_id` e `field_osm_version` em `aculta_event_location`. Não foi necessário `updatedb`. `drush cr` completou. `updatedb:status`: sem pendências. Configuração focal das Views passou a `[notice] No differences between DB and sync directory.` após sincronizar os UUIDs e a forma exportada em `0f1f384d8`; o status global continuou divergente (334 linhas), sem importação global.
- Verificado no Runtime: submódulo habilitado, campos instalados, rotas `/painel-administrativo/eventos/locais`, `/painel-administrativo/eventos/datas-relacionadas`, `/integracoes/openstreetmap/configuracao` e `/integracoes/openstreetmap/importar` presentes. Acesso anônimo HTTP: Views e configuração OSM `403`. MAIN `https://aculta.toca.net.br/` e Loja `https://loja.aculta.toca.net.br/` responderam `200`.
- SecretsManager consultado sem exibir valores: `OSM_TEST_CLIENT_ID`, `OSM_TEST_CLIENT_SECRET` e `ACULTA_OSM_TOKEN_ENCRYPTION_KEY` ausentes. Nenhuma conexão de escrita ou envio OSM foi feita. Produção não foi acessada.
- Gates no banco `aculta_simpletest`: Events `62 testes, 672 assertions, 27 depreciações`; OSM `4 testes, 49 assertions, 12 depreciações`; PHP lint `65 arquivos`; `git diff --check` passou.

## Configuração explícita da API ativa do OSM no checkout de teste — 2026-10-28

- Autorização: D-45, registrada em `docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md`, permite explicitamente ao checkout de teste usar a API ativa do OSM; as decisões anteriores já exigiam segredos fora do Git. Não foi solicitado nem executado envio de edição nesta etapa.
- Ambiente verificado antes da ação: hostname `toca`; Drush URI `http://default`; raiz `/home/piradopirata/projetos/aculta/web`; banco `aculta_runtime`; HEAD `aad9608f4`. Nenhuma troca de checkout, atualização de banco ou importação de configuração ocorreu.
- Em `secrets/aculta.secrets.env` (fora do Git, proprietário `aculta:aculta`, modo `0640`) foram provisionados o ID e o segredo da aplicação OAuth da API ativa, além de `ACULTA_OSM_TOKEN_ENCRYPTION_KEY` aleatória. Valores deliberadamente omitidos deste registro.
- Em `web/sites/default/settings.local.php` foi definida explicitamente a seleção `production` para `aculta_openstreetmap_environment`, limitada ao checkout local de teste e não versionada. `drush cr` concluiu.
- Verificação read-only via Drush: ambiente do site `test`; API selecionada `production`; base de API `https://api.openstreetmap.org/api/0.6`; OAuth `https://www.openstreetmap.org`; ID, segredo e chave de cifra presentes. Nenhum valor foi impresso. Nenhum OAuth foi concluído e nenhuma escrita no mapa foi feita.

## Canonical e sitemaps de teste via ACULTA Deployer (F7 / D-44) — 2026-10-28

- Base antes da regeneração: checkout `/home/piradopirata/projetos/aculta`, branch `refactor/opencultures-codigo-visual`, HEAD `d7c125aa5`; hostname `toca`; Drupal 11.4.10; URI Drush `http://default`; banco `aculta_runtime`; root `/home/piradopirata/projetos/aculta/web`; `SHOW TABLES` retornou 606 tabelas. `updatedb:status`: `[success] No database updates required.`
- `config:status` foi somente leitura e listou divergências preexistentes em várias áreas; nenhuma importação foi feita. A lista não foi reduzida nem aplicada.
- Após revisar a regeneração de sitemap, `drush cr` concluiu. `SUITE_OUT=/home/piradopirata/aculta-suite-out drush -l https://eventos.aculta.toca.net.br scr scripts/validate-final-sitemap.php` regenerou a fila/backend e retornou literalmente `7 official HTTPS sitemap URLs generated using the native backend API: OK`. Artefato `final-sitemap-urls.json` ficou fora do repositório em `$SUITE_OUT`.
- HTTP verificado: `https://aculta.toca.net.br/sitemap.xml` e `https://aculta.toca.net.br/eventos/sitemap.xml` retornaram 200; o index listou 7 filhos no host principal de teste, o sitemap filho listou 7 URLs somente em `eventos.aculta.toca.net.br`. O mesmo filho funcionou sob o host de eventos. Todos retornaram `X-Robots-Tag: noindex, nofollow, noarchive`. Página de evento: HTTP 200 e canonical `https://eventos.aculta.toca.net.br/eventos/oficina-de-ceramica-teste`.
- Verificações de código: sitemap Kernel/Unit 7 testes e 13 assertions (46 depreciações); `DomainPurposeManagerKernelTest` 8/47 (1 depreciação); `DeployerEnvironmentTest` 4/9. `validate-final-sitemap.php`, PHPCS Drupal e DrupalPractice, PHP lint e `git diff --check` passaram. Nenhum `updatedb`, `config:import` ou deploy foi executado.

## Ativação de módulos editoriais no Runtime — 2026-10-28

- **Solicitação do responsável:** ativar no Runtime os módulos necessários já implementados. Ambiente verificado: hostname `toca`; Drupal 11.4.10; URI Drush `http://default`; raiz `/home/piradopirata/projetos/aculta/web`; banco `aculta_runtime`; branch `refactor/opencultures-codigo-visual`; HEAD `18ab97ecb` antes da ação.
- **Inventário prévio:** `aculta_events`, `aculta_calendar`, `aculta_events_openstreetmap`, `aculta_maps`, `aculta_index` e `aculta_user` já estavam habilitados. `aculta_faq` e `aculta_content_admin` estavam desabilitados; o plano marca ADM4/ADM5 implementados. A aprovação editorial e cadastro da minuta FAQ continuam pendentes; a ativação não publicou conteúdo editorial.
- **Banco antes da ação:** consulta `SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()` retornou `605`. `drush updatedb:status`: `[success] No database updates required.` `drush config:status` mostrou divergências preexistentes, entre elas `core.extension`; nenhuma importação global foi executada.
- **Snapshot antes da escrita:** `/home/piradopirata/aculta-backups/aculta-runtime-enable-modules-20261028/aculta_runtime-before-enable.sql`, 45.921.673 bytes, modo `0600`; SHA-256 `06dd6a37926a56bb38d2b8ba192c7487d38284843cbdb3e150d30a78f0471773`.
- **Ação autorizada:** `php vendor/drush/drush/drush.php pm:enable aculta_faq aculta_content_admin -y`; ambos instalaram com sucesso. `drush cr`: `[success] Cache rebuild complete.`
- **Verificação posterior:** Drush lista ambos os módulos como `Enabled`; banco continua com 605 tabelas e `updatedb:status` continua sem atualizações. HTTP anônimo: `/painel-administrativo/faq` `403`, `/painel-administrativo/conteudo` `403`, `/perguntas-frequentes` `200`. Nenhum item de FAQ foi criado ou publicado.
- **Não executado:** `updatedb`, `config:import`, deploy ou operação em produção. A configuração global do Runtime continua divergente e não foi reconciliada.

## Gate de Runtime para acesso D-15 — 2026-10-28

- Ambiente: hostname `toca`; checkout `/home/piradopirata/projetos/aculta`; branch `refactor/opencultures-codigo-visual`; HEAD `54b9a2790` durante o gate, com alterações de código locais da microfase D-15; Drush confirmou Drupal 11.4.10, URI `http://default`, root `/home/piradopirata/projetos/aculta/web` e `aculta_runtime`.
- Antes/depois: consulta somente leitura retornou `605` tabelas; `drush updatedb:status`: `[success] No database updates required.` Não houve troca de HEAD, update ou importação de configuração.
- `drush cr`: `[success] Cache rebuild complete.` Consulta de leitura por eventos publicados com `field_turma` não retornou linhas no Runtime; os casos matriculado/não matriculado foram cobertos apenas no `aculta_simpletest`.
- Código depois commitado e publicado em `1b932d29c`; os testes completos de Events e LMS Agenda passaram antes da publicação. Nenhuma escrita de conteúdo ou chamada externa foi feita.

## Verificação do HEAD D-15 publicado — 2026-10-28

- Checkout servido em `270a37de9`, branch `refactor/opencultures-codigo-visual`; hostname `toca`; Drupal 11.4.10; URI `http://default`; root `/home/piradopirata/projetos/aculta/web`; banco `aculta_runtime`.
- `updatedb:status`: `[success] No database updates required.` Contagem somente leitura: 605 tabelas. `config:status` retornou 358 linhas; a divergência global persiste e nenhuma importação foi feita.
- `drush cr`: `[success] Cache rebuild complete.` Página principal `https://aculta.toca.net.br/` e loja `https://loja.aculta.toca.net.br/` responderam HTTP 200.
- Nenhum snapshot ou update foi necessário: não há updates de banco pendentes; nenhuma troca de schema ou configuração ocorreu neste gate.

## ADM8, F0 e ativação seletiva E8 — 2026-10-28

- **Publicação:** commits `1a423c042` (ADM8), `dc032d9e0` (F0/D-46) e `da3fd99db` (E8) publicados por fast-forward, cada microfase em seu próprio commit. Branch remota `refactor/opencultures-codigo-visual`; sem branch nova.
- **Checkout servido:** antes `03483aa44`, limpo e destacado; depois `/home/piradopirata/projetos/aculta` destacado em `da3fd99db`. `hostname` = `toca`; Drupal 11.4.10; URI `http://default`; root `/home/piradopirata/projetos/aculta/web`; Drush confirmou `aculta_runtime` conectado.
- **Gatilho antes da importação:** `updatedb:status` retornou `[success] No database updates required.` A contagem em `information_schema.tables` foi 605. `config:status` mostrou divergências antigas em várias áreas; no escopo E8, os três novos itens (storage, field e form display) estavam apenas no sync e os displays Revista full/teaser divergiam. Nenhuma divergência alheia foi importada.
- **Snapshot antes da escrita:** `/home/piradopirata/aculta-suite-out/aculta-runtime-before-e8-da3fd99db.sql`, 51.200.724 bytes, modo `0600`, diretório `0700`; SHA-256 `a2869c5ffd8e3122e7888d81cde2c60a69997bc1ae069912cddf7509c8d859ef`.
- **Importação autorizada e revisada:** `drush config:import --partial --source=/home/piradopirata/aculta-suite-out/e8-config-da3fd99db --diff -y`; fonte continha somente os cinco YAML versionados de E8. Resultado: `[success] The configuration was imported successfully.` Nenhum `updatedb` ou import global foi executado.
- **Pós-importação:** `drush cr` retornou `[success] Cache rebuild complete.`; `updatedb:status` seguiu sem pendências; contagem passou a 607 tabelas por causa do field storage novo. O plugin `aculta_editorial_category_color` foi descoberto. Os cinco termos existentes de `editorial_category` continuam sem cor; nenhum conteúdo foi alterado. A checagem focal apontou apenas diferença de ordem serializada no display teaser, alinhada em `config/sync` no follow-up E8; não há diferença semântica pendente no display.
- **HTTP:** `https://aculta.toca.net.br/` e `https://loja.aculta.toca.net.br/` retornaram HTTP 200.
- **Firefox ESR visível:** script `tests/browser/revista-color/visual.py`, com amostras DOM sintéticas, verificou os quatro tokens em 1280 px e 320 px, sem overflow e com as cores computadas esperadas. Capturas/JSON em `/home/piradopirata/aculta-suite-out/e8-category`. O teste não alterou configuração, conteúdo ou termos.

## Verificação visual de Eventos (F6 parcial) — 2026-10-28

- Checkout servido atualizado de `da3fd99db` para `6f282312b` (documentação/teste E8). O estado Git antes da troca estava limpo; checkout destacado, sem branch nova ou stash. `hostname=toca`; Drush confirmou `aculta_runtime`, Drupal 11.4.10 e URI `http://default`.
- `updatedb:status`: `[success] No database updates required.` A divergência global do `config:status` continua preexistente. Snapshot antes de `drush cr`: `/home/piradopirata/aculta-suite-out/aculta-runtime-before-head-6f282312b.sql`, 50.563.276 bytes, modo `0600`, SHA-256 `bc1173de8d5567d3d52718c70a8521e9d3c7bcc2462efa63617d9eacd78b7f13`. Nenhuma atualização de banco ou importação foi executada.
- `drush cr`: `[success] Cache rebuild complete.` HTTP `https://aculta.toca.net.br/` e `https://loja.aculta.toca.net.br/`: 200.
- Firefox ESR visível: `tests/browser/eventos/agenda-embed.mjs` passou **129/129 verificações**, 0 ignoradas, 0 falhas em 1280/390/320 px e modo escuro. O Runtime tem eventos/datas e local com coordenadas; o README anterior que dizia não haver conteúdo está desatualizado. A captura `evento_320.png` mostra o título duas vezes. Nenhum código de Eventos foi alterado; o arquivo de controller é da frente paralela. Teclado e zoom de 200% seguem sem validação, portanto F6 permanece parcial. Artefatos em `/home/piradopirata/aculta-suite-out/f6-events`.


## Verificações de FAQ temporária e `aculta_lms_resources` — 2026-10-29

- Por autorização do responsável, foram criados dois rascunhos temporários `aculta_faq` (NIDs 581 e 582) no `aculta_runtime`, com perguntas e respostas de teste. A resposta continha `<script>` e `javascript:` para exercitar `aculta_editorial`; `check_markup()` removeu ambos e manteve a formatação segura. Os dois rascunhos foram removidos no `finally`; consulta posterior por título com o marcador de teste não retornou linhas. A contagem de tabelas imediatamente antes da operação não foi registrada; na inspeção posterior, o Runtime tinha 607 tabelas. Nenhum conteúdo editorial aprovado foi alterado.
- Runtime confirmado: hostname `toca`; checkout servido `/home/piradopirata/projetos/aculta`; URI Drush `http://default`; Drupal 11.4.10; banco `aculta_runtime`. `aculta_lms_resources` está habilitado em `0.1.0-dev.19`; o field `group.field_lms_videos`, bundle `remote_video` e provider Content Index de vídeo foram confirmados por leitura. `updatedb:status`: `[success] No database updates required.` Não houve update, `config:import` ou mudança de configuração.
- Na worktree `refactor/opencultures-codigo-visual`, a suíte de `aculta_lms_resources` passou: Unit + Kernel, **20 testes / 375 assertions**, com 53 depreciações upstream. O PHPUnit exibiu aviso de que `sites/simpletest/browser_output` não é gravável; a suíte terminou sem falhas. A revisão visual integrada não foi executada nesta sessão.
- A tentativa visual X11 do campo `field_lms_videos` confirmou `DISPLAY=:0` e HTTP 200 em `https://cursos.aculta.toca.net.br/`. O script criou um Group `lms_course` e um Media `remote_video` temporários (IDs 87/270), mas `drush uli --uid=1` excedeu 60 segundos antes da navegação. A limpeza foi confirmada: SQL retornou 0 Group ID 87, 0 Media ID 270, e 607 tabelas; `updatedb:status` retornou `[success] No database updates required.` Firefox/geckodriver da sessão encerrou; nenhuma captura foi gerada. Não houve configuração, schema ou conteúdo remanescente.

## Bloqueio da tentativa de teste Growroom — 2026-10-29

- No `aculta_simpletest`, o cenário Kernel focal de Comment com autoria histórica passou isoladamente: 1 teste / 28 assertions, 5 depreciações. A suíte integral do Fórum terminou com 344 testes / 4.233 assertions, 1 erro, 42 depreciações e 6 skips. O erro: `PublicForumLastActivityAuthorKernelTest::testLatestPostUsesHistoricalAuthorCard` tentou criar `legacy_author` como field storage após o novo hook do fixture compartilhado declará-lo como campo base.
- A tentativa foi removida do working tree após o gate vermelho; os arquivos originais do fixture e do teste foram restaurados. Nenhuma migração Growroom foi executada e nenhum dado de Runtime foi alterado. Uma próxima tentativa requer fixture Kernel dedicada; nesta rodada não se alteraram as regras do teste existente.

## Publicação do gate de fronteira e painéis Image/Leitor — 2026-10-29

- Publicações fast-forward em `origin/refactor/opencultures-codigo-visual`: `355e48a89..20cbc8e3e` (`fix(theme): remover dependência visual dos módulos`), `20cbc8e3e..d0ad722db` (`feat(aculta-image): incluir processamento no painel ACULTA`) e `d0ad722db..2c0ee315e` (`feat(document-viewer): ampliar opções administrativas`). Os três comandos `git push origin HEAD:refactor/opencultures-codigo-visual` terminaram com sucesso; sem force push. A cadeia anterior de 100 commits já autorizada foi publicada junto com o primeiro avanço.
- Gates executados na worktree candidata: `validate-module-theme-boundary` PASS (3397 verificações); `validate-module-theme-map` PASS (214); `validate-portal-ui-docs` PASS (380 tokens, 300 usados, 149 atributos); SDC PASS (54 componentes, 734 checks); Design Foundations e fixtures PASS (2379 checks; 157 resultados); Browser Validators PASS (21); aliases temporários PASS (36 arquivos); índice de status PASS (50 componentes). `git diff --check`, PHP lint e PHPCS dos PHP alterados passaram. Foundation via Drush PASS (347) e Shell Contract via Drush PASS (11) foram executados no Runtime ainda em `355e48a89`, não no candidato publicado; fixtures Shell PASS (19) foram estáticos na worktree.
- Testes em `aculta_simpletest`: Events 55/669 PASS (26 depreciações), Calendar 117/399 PASS (2), Maps 10/39 PASS (2), Document Viewer 52/575 PASS (147 depreciações Core/contrib e 1 PHPUnit), Image 33/169 PASS (4). JavaScript alterado passou em `node --check`. A tentativa adicional de PHPCS da árvore inteira de Document Viewer não é evidência de regressão: o comando também verificou dois arquivos JS preexistentes como PHP e encontrou ainda dois avisos em `aculta_document_viewer_group`; nenhum desses arquivos foi alterado. PHPCS focal nos PHP alterados passou.
- D-47/D-48 documentadas. `aculta_image` e `aculta_document_viewer` continuam Dev. Image agora navega por ACULTA → Imagens → Processamento e formatos; Leitor por ACULTA → Site → Leitor de documentos. Config Sync do Leitor foi alterado, mas não importado; conversão do acervo existente não executada. Revisão visual autenticada e FPM Hostinger continuam pendentes.
- **Checkout servido não sincronizado:** em `/home/piradopirata/projetos/aculta`, `HEAD` permanece `355e48a89` destacado. A única alteração rastreada local é `docs/operations/status/aculta_image.yml` (`0.1.0-dev.36`), que não pertence a esta frente; o novo head modifica o mesmo arquivo. Para preservar essa alteração de outra sessão, não houve checkout, stash, `drush cr`, `updatedb` ou importação. Por consequência, `updatedb:status` e `config:status` pós-publicação não foram executados; o gatilho do Runtime fica pendente até o conflito no arquivo ser resolvido pelo responsável.

## OCS-F0 / THEME-MF0 — publicação e sincronização do checkout de desenvolvimento (2026-10-29)

- Frente B publicou por fast-forward `ded8a6181..e9fa8111e` em `origin/refactor/opencultures-codigo-visual` (PR #299), commit `docs(theme): audit OpenCulturas fidelity and mobile-first baseline`. `git ls-remote` confirmou o remoto no pai `ded8a6181` antes do push; push terminou com sucesso. Foram incluídos 13 caminhos próprios de inventário/README/roadmap, duas correções dos validadores de tema e o script reutilizável de captura. Nenhum arquivo dos agentes de CEP/Eventos foi staged; suas alterações permanecem no worktree da branch.
- Checkout servidor: `toca`, `/home/piradopirata/projetos/aculta`, Drupal root `/home/piradopirata/projetos/aculta/web`, URI `http://default`; banco `aculta_runtime`; Drupal 11.4.10. HEAD anterior `ded8a6181`, posterior `e9fa8111e` (detached). A referência preexistente `backup/pre-rebase-20261029-120038` apontava para o HEAD anterior. O diff `ded8a6181..e9fa8111e -- config/sync` estava vazio.
- Alterações locais próprias foram preservadas antes da troca em patch `/home/piradopirata/suite-out/aculta-ocs-f0/pre-head-switch.patch`, SHA-256 `c38b47c1884e2cd95b8a87d852c75451e5279c5b13c35efe82d9664d145a5bc4`; stash `backup OCS-F0 before dev checkout advances to e9fa8111e` permanece disponível. Não havia outros caminhos locais modificados no checkout servidor.
- Banco confirmado: `aculta_runtime`, 607 tabelas. `updatedb:status`: `[success] No database updates required.`; Drush reportou entradas de schema para `aculta_document_viewer_group`, `aculta_pdf_viewer` e `aculta_pdf_viewer_group` sem módulos ativos correspondentes. `config:status`: 397 linhas de saída e aviso `Undefined array key "rename"`; drift global já observado antes do commit, sem delta no `config/sync` entre os heads. **Nenhum `updatedb` nem `config:import` foi executado.**
- Snapshot anterior à escrita de cache: `/home/piradopirata/suite-out/aculta-ocs-f0/aculta_runtime-before-e9fa8111e.sql`, 55.593.129 bytes, modo `0600`, SHA-256 `b96b5a1cbabda1910de74314160d93561ad3145731787d457c85c09962c8a7f8`.
- Após a troca: `drush cr` retornou `[success] Cache rebuild complete.`; HTTP MAIN `https://aculta.toca.net.br/` = 200 e Loja `https://loja.aculta.toca.net.br/` = 200.
- Gates executados no checkout servido antes da publicação: `validate-project-status-index.php` PASS (47 componentes); `validate-module-theme-boundary.php` PASS (3366); `validate-aculta420-sdc-schemas.php` PASS (54 componentes, 734 checks); `validate-aculta420-design-foundations.php` PASS (2379); fixtures Design Foundations PASS (157); `validate-aculta420-foundation.php` PASS (347); `validate-aculta420-shell-contract.php` PASS (11); fixtures Shell PASS (19); `validate-browser-validators.php` PASS (21); `git diff --check`, PHP lint dos validadores e sintaxe Python PASS. Firefox ESR visível capturou a homepage OpenCulturas e oito URLs Studio em 1440/390; detalhes e limitações estão nos arquivos OCS-F0.
- Gate diagnóstico adicional `validate-module-theme-map.php`: **FAIL (1 falha em 208 checks)** porque `docs/portal/MAPA-MODULO-TEMA.md` cita o caminho removido `web/modules/custom/aculta_portal/modules/aculta_document_viewer/modules/aculta_document_viewer_group`. Esse mapa/módulo não pertence ao ownership visual; Pede-A ao owner para reconciliar o caminho com o plugin legado atual e reexecutar o validador. O gate obrigatório `validate-module-theme-boundary.php` passou.
- THEME-MF0 e OCS-F0 continuam parciais. Não houve implementação de CSS/JS visual, homologação de fidelidade ou avanço de MF3/F11. Bloqueios: Hub `/studio` retorna documento vazio; `/studio/ganjalab` mostra Minha Conta em vez do painel LMS; a conta sintética não tem ACL Green Stash. A captura não cobriu todos os purposes nem todos os breakpoints.

## THEME-MF1/MF2 e OCS-F1 — implementação parcial (2026-10-29)

- Publicações fast-forward em `origin/refactor/openculturas-codigo-visual` (PR #299): `625503fc9` (matriz pública de purposes), `175b106f3` (fundação global mobile-first), `ff0e53956` (tokens de fidelidade Studio) e `63be6b8d7` (migração mobile-first de `content-grid`). `git ls-remote` confirmou `63be6b8d7abb92cdc7c97a837f315aae0feac229`; nenhum force push.
- No worktree da PR passaram, após as alterações visuais: `validate-aculta420-design-foundations.php` (2431 checks), `validate-aculta420-sdc-schemas.php` (54 componentes, 24 includes, 734 checks), `validate-module-theme-boundary.php` (3371 checks) e `git diff --check`. `content-grid` agora usa 1 coluna na base, 2 a partir de 36rem e 3 a partir de 62rem; consumidores encontrados em Marley, LMS e Wiki. O SDC `context-tabs` existente foi reutilizado para a navegação Studio. Nenhum PHP, controller, config ou contrato funcional foi alterado.
- CSS lint: não verificado; `stylelint` não está instalado e o tema não declara `package.json` próprio. Firefox, zoom e teclado pós-alteração ainda não haviam sido executados quando os commits visuais foram publicados; a rodada posterior está registrada abaixo.
- Checkout servidor antes da troca: `75f049fd1`, destacado e limpo; esse commit antigo segue alcançável pela branch da PR. Após a publicação, checkout destacado passou para `63be6b8d7`. Runtime identificado por `drush status`: host `toca`, Drupal 11.4.10, URI `http://default`, banco `aculta_runtime` conectado. MAIN e Loja responderam HTTP 200.
- Gatilho somente leitura: `updatedb:status` encontrou update pendente `aculta_events` 10005 (“Instala campos estruturados de acessibilidade, modo e segurança (ARQ-04)”). `config:status` terminou com código 0, 397 linhas e warning `Undefined array key "rename" ConfigCommands.php:274`; o texto integral foi preservado, modo 0600, em `/home/piradopirata/suite-out/aculta-ocs-f0-config-status-63be6b8d7.txt`. A saída inclui `node.type.aculta_faq`, `core.entity_form_display.node.aculta_faq.default`, `core.entity_view_display.node.aculta_faq.default` e campos de FAQ **apenas no DB**. Não houve snapshot, `updatedb`, `config:import` nem `drush cr`; assim, nenhuma configuração ou cache Runtime foi escrita.
- **Pede-A — reconciliação necessária antes do import/global gate:** adicionar ao `config/sync` o módulo/bundle/campos e displays canônicos `aculta_faq` que devem ser mantidos, e reconciliar as demais extensões/configurações runtime-only contra o head. O Sync de `63be6b8d7` ainda não representa a decisão do responsável de manter FAQ e configs; importá-lo agora poderia desativar módulos e remover configuração de conteúdo. Preservar os seis nós no bundle atual se esse for o contrato confirmado, sem migração desnecessária. Depois da reconciliação, revisar o diff, snapshot e executar o gatilho conforme procedimento.
- MF1, MF2 e OCS-F1 permanecem parciais. THEME-MF3 e OCS-F11 não passaram; OCS-A1–A4 não iniciaram. A matriz pública de 90 capturas é baseline anterior às mudanças, não evidência pós-implementação.

## Evidência Firefox pós-MF1/OCS-F1 — 2026-10-29

- No checkout servido `0b0531aa0`, `drush cr` retornou `[success] Cache rebuild complete.`. Não houve atualização de configuração ou conteúdo; o drift FAQ/Sync e o update pendente `aculta_events 10005` permaneceram sem escrita.
- `DISPLAY=:0`, Firefox ESR 153.5.1esr e geckodriver visível. `tests/browser/aculta-studio/run-visual.sh` terminou com 29 PASS, 1 SKIP (sem publicação própria para testar a edição), 0 FAIL. A navegação cobriu tabs/AJAX/`aria-current`, teclado Enter, links nomeados, erros JS, modo light/dark e overflow em 320/390/768/1024/1440. Contraste calculado mínimo de 7,05:1 claro e 11,51:1 escuro. A preferência da conta voltou a `light`, confirmado por leitura Drush. PNG e `results.json` em `/home/piradopirata/suite-out/aculta-ocs-mf2-post-63be6b8d7/`.
- `ocs-baseline.py` capturou 27 screenshots em Firefox: OpenCulturas pública e Marley/WE Sativa autenticados, nove viewports 320/360/390/599/600/768/1024/1280/1440. Nas 18 páginas Studio, scroll width coincidiu com viewport; nenhuma rota foi negada/vazia. Em Marley, inspeção de PNG em 599/600/768 confirmou a variante `content-grid--2` em duas colunas; 320/360/390 estavam empilhadas. WE Sativa não exibiu `data-aculta-studio`, então essa captura não conta como validação de consumer Studio; LMS, Wiki e variante de três colunas não foram exercitados. Artefatos em `/home/piradopirata/suite-out/aculta-ocs-mf2-grid-20261029/`.
- Essas capturas são pós-alteração somente para as rotas/estados listados. MF1/MF2/OCS-F1 permanecem parciais, MF3/F11 não aprovados e OCS-A1–A4 não iniciados.

## THEME-MF2 — `content-section`, evidência de consumer e novo head (2026-10-29)

- Commit `dd37b35d5` removeu o override móvel redundante do gap em `content-section.css`, atualizou as cinco stories e a documentação MF2; publicado por fast-forward em `origin/refactor/openculturas-codigo-visual` (PR #299). Nenhum código de módulo foi alterado.
- Gates estáticos no worktree: Design Foundations PASS (2431), SDC schemas PASS (54 componentes, 24 includes, 734 checks), module-theme boundary PASS (3371) e `git diff --check` PASS. `npm run lint:css` foi tentado e não pôde rodar porque `stylelint` não está instalado. Não foi instalado pacote.
- Checkout de desenvolvimento mudou de `3ce988da0` para `dd37b35d5` após backup local `backup/dev-before-dd37b35d5`; `hostname=toca`, Drush identificou Drupal 11.4.10, URI `http://default`, banco `aculta_runtime`. `updatedb:status` encontrou `aculta_events 10005`; `config:status` retornou 397 linhas com warning `Undefined array key "rename"` e FAQ (bundle, form/view displays e fields) somente no DB. Saídas privadas (0600) em `/home/piradopirata/suite-out/aculta-ocs-f0-status-dd37b35d5/`. Conforme decisão do responsável de manter FAQ e configs no Sync, não houve snapshot/import/update enquanto o owner não reconciliar o Sync; Pede-A anterior continua aberto.
- `drush cr` PASS; Foundation por Drush PASS (347), Shell Contract PASS (11); HTTP MAIN e Loja 200. Firefox ESR visível executou `tests/browser/aculta-wiki/we-sativa.py`: 34/34 PASS, incluindo workspace Wiki autenticado e sem overflow em 320/360/390/640/768/1024/1280/1440 px; não submete formulário. PNG/JSON em `/home/piradopirata/suite-out/aculta-mf2-content-section-dd37b35d5/`. Esse smoke não mede o gap do SDC diretamente, nem valida zoom real, foco/teclado nesse formulário ou WCAG completa.
- `THEME-MF2` continua parcial; `THEME-MF3` e `OCS-F11` não foram aprovados. `OCS-A1–A4` permanecem não iniciadas.

## THEME-MF2 — `author-card` e correção do breakpoint do Fórum (2026-10-29)

- Commit `f2ae3bcad` publicou a base mobile do SDC compartilhado `author-card`, mantendo variantes `sm`/`md`/`lg`, layouts editorial/empilhado e contratos. Firefox ESR visível executou o consumer público `https://growroom420.aculta.toca.net.br/discussoes/novas`: 30/32 verificações passaram nas larguras 320–1440; alinhamento responsivo passou em todos os tamanhos, mas o documento tinha overflow de 1px a 576 e 600px.
- A inspeção visual do screenshot e das regras do tema mostrou que a data da última atividade na coluna da View mantinha `white-space: nowrap`. Removi essa restrição em `css/components/forum-index.css`, permitindo quebra natural. O script reutilizável `tests/browser/aculta-theme-mobile-first/author-card.py` foi criado para conferir consumer, breakpoint e overflow. A rodada focal após a correção ainda precisa ocorrer com o checkout servido sincronizado.
- Validators no worktree candidato: Design Foundations PASS (2431), SDC schema PASS (54 componentes/734 checks), fronteira tema/módulo PASS (3371), Python `py_compile` e `git diff --check` PASS. Stylelint permanece ausente; lint CSS DEFERRED.
- Após publicar `892f803d5`, checkout de desenvolvimento foi sincronizado com backup do head anterior, `updatedb:status`/`config:status` foram repetidos (mesmo update 10005 e 397 linhas de drift, logs 0600 em `/home/piradopirata/suite-out/aculta-ocs-f0-status-892f803d5/`) e `drush cr` foi executado. MAIN e Fórum responderam HTTP 200. Firefox ESR visível passou **32/32** no script novo com consumer presente e alinhamento `flex-start` até 575px / `center` a partir de 576px; zero overflow em 320/360/390/575/576/600/768/1024/1280/1440. Screenshots e `summary.json` em `/home/piradopirata/suite-out/aculta-theme-author-card-892f803d5/`.
- Após `npm ci --ignore-scripts --no-audit --no-fund` usando apenas o `package-lock.json` existente, `npm run lint:css` e `npm run lint:js` passaram. `package.json` e lockfile permaneceram inalterados; não foi adicionada dependência.

## THEME-MF2 — navegação mobile-first do shell de Conta (2026-10-29)

- O menu Accordion e a coluna única do shell autenticado eram overrides `max-width: 767.98px`; convertidos em estilos-base. `min-width: 48rem` reativa lista lateral, sticky e layout de duas colunas. A apresentação do hover do ícone de foto também usa aprimoramento de desktop/touch. Template, JS, atributos, rotas, cache e formulários permaneceram intactos.
- Antes da alteração, o Firefox visível passou o smoke baseline da rota autenticada `/configuracoes`: 33/33 checks em 320/360/390/599/600/767/768/1024/1280/1440, Institutional Bar 1/1 e sem overflow. O teste reutilizável `tests/browser/aculta-theme-mobile-first/account-shell.py` gera ULI em memória para a conta sintética autorizada e não submete o Form API.
- Com a folha candidata injetada no DOM sobre a mesma tela/runtime, o Firefox passou novamente 33/33 checks, mesmos dez viewports, seções de menu desktop/sanfona equivalentes, breakpoint 767/768 correto, Institution Bar presente e zero overflow. PNG/JSON em `/home/piradopirata/suite-out/aculta-account-shell-candidate2/`. A verificação do CSS agregado sem override ainda é necessária após commit e `drush cr`.
- `portal-account.css` foi a única folha funcional alterada. As duas regras `max-width` que convertem tabelas de pedidos para cartões permanecem intocadas nesta microfase.
- Pós-publicação, checkout servido alinhado a `8768825cf`; `updatedb:status` e `config:status` repetidos (update `aculta_events 10005`, 397 linhas, saídas 0600 em `/home/piradopirata/suite-out/aculta-ocs-f0-status-8768825cf/`), sem import/update. `drush cr` PASS. Firefox ESR visível sem CSS override passou **33/33** checks nas dez larguras e zero overflow; MAIN/Loja/Fórum responderam HTTP 200. PNG/JSON em `/home/piradopirata/suite-out/aculta-account-shell-8768825cf/`.
- `npm run lint:css`, `npm run lint:js`, Design Foundations (2431), SDC schemas (54/734), boundary (3371), Python `py_compile` e `git diff --check` passaram. Não houve alteração no `package.json` nem lockfile.

## THEME-MF0 — baseline público SUPPORT e publicação 7058e3a00 (2026-10-29)

- Antes da microfase, a worktree visual estava em `b27a67391`; `git fetch` confirmou o mesmo SHA em `origin/refactor/opencultures-codigo-visual`. `git pull --rebase` não prosseguiu porque a worktree contém alterações não staged de outra frente; nenhuma foi incluída ou modificada. HEAD e remoto iguais demonstraram que não havia commits a rebasear.
- Commit `7058e3a007e305b63539d7aaec309d97bb91c4f5` adicionou teste Firefox reutilizável e atualização de catálogo/auditoria/roadmap; push fast-forward confirmou `b27a67391..7058e3a00` em `origin/refactor/opencultures-codigo-visual` (PR #299). Só cinco caminhos próprios foram incluídos; nenhum CSS/JS/template funcional foi alterado.
- Firefox ESR 153.5.1esr e geckodriver 0.37.1, visíveis com `DISPLAY=:0`, passaram 36/36 verificações no purpose `https://apoio.aculta.toca.net.br/`, em 320/360/390/480/481/599/600/768/1024/1280/1440px. O formulário e cinco cartões do SDC `donation-quick` renderizaram, Institution Bar presente, zero overflow; Domain Header ausente e anotado no Pede-A. Artefatos fora do Git em `/home/piradopirata/aculta-suite-out/aculta-support-mobile-first-20261029b/`. Não foram validados Form API submetido, zoom, teclado, WCAG, claro/escuro ou reduced motion.
- Checkout servido mudou de `b27a67391` para `7058e3a00`, após estado limpo confirmado e criação do backup local `backup/dev-before-7058e3a0`; `hostname=toca`, Drupal 11.4.10, URI `http://default`, banco `aculta_runtime`. `updatedb:status` exit 0 ainda lista `aculta_events_update_10005`; `config:status` exit 0 lista 197 configurações, incluindo o tipo, campos e displays `aculta_faq` somente no DB. Saídas modo 0600 em `/home/piradopirata/aculta-suite-out/aculta-ocs-f0-status-7058e3a00/`. Nenhum snapshot, `updatedb` ou `config:import` foi executado: o Pede-A de reconciliação do Sync continua aberto para preservar FAQ e configs.
- `drush cr` retornou `[success] Cache rebuild complete.`; MAIN e Loja responderam HTTP 200. A auditoria SUPPORT segue baseline parcial; THEME-MF0/MF2 permanecem em andamento, THEME-MF3 não aprovado, OCS-F11 não aprovado e OCS-A1–A4 não iniciadas.

## THEME-MF2 — navegação global e publicação 614e2cf26 (2026-10-29)

- Worktree visual estava em `0ed71ecf7`; após `git fetch`, remoto e HEAD coincidiam. `git pull --rebase` não pôde prosseguir devido a alterações não staged de outra frente; nenhum caminho alheio foi staged, alterado ou descartado.
- Commit `614e2cf268513d4a6e70460b8f61c1a35a9e349c` moveu as regras da navegação responsiva para a base mobile e restaurou menu horizontal a partir de 1100px; inclui teste Firefox reutilizável e documentação. Push fast-forward confirmou `0ed71ecf7..614e2cf26` em `origin/refactor/opencultures-codigo-visual` (PR #299).
- Firefox ESR visível, CSS candidata injetada, passou 28/28 checks em 320/360/390/575/576/599/600/768/1024/1099/1100/1280/1440px na home MAIN. O menu abriu nas larguras móveis, manteve 7 links e ambos os headers, sem overflow; fallback sem JavaScript visível em 320/768/1099/1100/1440. Após o checkout correto e `drush cr`, a mesma suíte sem override também passou 28/28. PNG/JSON em `/home/piradopirata/aculta-suite-out/aculta-navigation-integrated-614e2cf26/`.
- Checkout servido: antes `0ed71ecf7`, depois `614e2cf26`; HEAD anterior preservado em `backup/dev-before-614e2cf26`. Runtime identificado como `toca`, Drupal 11.4.10, URI `http://default`, banco `aculta_runtime`; contagem de tabelas antes da escrita: 607.
- `updatedb:status` indicava apenas `aculta_events_update_10005`. Foi inspecionado o hook: instala seis campos base de evento/data. Snapshot anterior à escrita em `/home/piradopirata/aculta-suite-out/aculta-runtime-before-614e2cf26/aculta_runtime.sql`, 56.778.874 bytes, modo `0600`, SHA-256 `f04f85f8e8150035a9c893434715ffa9a5f1e209d475e6c2bb93109a3b247f99`. `drush updatedb -y` executou esse hook enquanto o checkout ainda estava em `0ed71ecf7` (mesmo código de update); instalou `aculta_event.field_classificacao_etaria`, `field_acessibilidade`, `field_seguranca` e `aculta_event_date.field_modo`, `field_link_virtual`, `field_abertura_portas`. Depois do checkout `614e2cf26`, `updatedb:status` informou `[success] No database updates required.`
- `config:status` continua com 197 configurações divergentes; 5 itens do FAQ (`node.type`, form/view display e 2 fields) aparecem apenas no DB. Nenhum `config:import` foi executado: o Sync ainda não contém o bundle/configuração FAQ que o responsável pediu para manter. Pede-A de reconciliação do Sync permanece bloqueando o import global.
- `drush cr` PASS no head `614e2cf26`; Foundation via Drush PASS (347), Shell Contract PASS (11), MAIN/Loja/Fórum HTTP 200. Stylelint, ESLint, Design Foundations (2431), SDC schema (54 componentes/734 checks), module-theme boundary (3371), sintaxe Python e `git diff --check` PASS. Sem validação de teclado completo, zoom, claro/escuro, reduced motion ou WCAG 2.2 AA; MF3 e F11 continuam não aprovados.

## THEME-MF2 — Institution Bar global (2026-10-29)

- A worktree estava no `c6d8e89b55210415922ef1ee09c04cb1dc093772`, igual ao remoto após `git fetch`; `git pull --rebase` não foi possível devido a alterações não staged de outra frente. Os arquivos alheios permaneceram intactos e fora do staging.
- A regra mobile-first foi movida para a base CSS e desktop restaurado em `min-width: 768px`. O seletor passou a cobrir o `<ul class="nav flex-row">` realmente renderizado na Institution Bar de Conta, preservando também `.navbar-nav`; Twig, JS, comportamento do shell e contratos de purpose não mudaram.
- Firefox ESR visível comparou o baseline servido e a folha candidata: ambos passaram 84/84 checks em MAIN, Conta, SUPPORT, Wiki, Loja e Fórum, nas larguras 320/374/375/600/767/768/1440px, sem overflow. A Institution Bar foi única nas 42 combinações. Capturas e `results.json` em `/home/piradopirata/aculta-suite-out/aculta-institution-bar-baseline/` e `/home/piradopirata/aculta-suite-out/aculta-institution-bar-candidate/`. Domain Header ausente em rotas de Conta, SUPPORT, Loja e Fórum permanece Pede-A.
- Stylelint e ESLint do tema, Design Foundations (2431), SDC schema (54 componentes/734 checks), module-theme boundary (3371), sintaxe Python e `git diff --check` passaram. Zoom, teclado completo, claro/escuro, reduced motion e WCAG 2.2 AA não foram validados. MF2 permanece parcial; MF3/F11 não aprovados.
- Baseline contém CSS servido antes deste commit; o teste candidate injetou apenas a folha modificada da worktree. Teste reutilizável e limites documentados em `tests/browser/aculta-theme-mobile-first/README.md`.

## THEME-MF2 — todos os domínios e contrato Domain Header (2026-10-29)

- Usuário determinou que a Revista e os dez domínios/purposes atuais façam parte da auditoria, que a tabela de host considere a migração Alpha e que toda regra de rota/purpose do Domain Header pertença a `aculta_portal`. O tema permanece consumidor visual do contrato Portal. Não foram alterados controllers, serviços ou rotas da Frente A.
- `institution-bar.py` passou a derivar os dez hosts de `aculta_deployer/config/deploy.json`, com `INSTITUTION_BAR_DOMAIN_ENV=test|production`; a Revista usa `revista.aculta.toca.net.br` em teste e `revista.aculta.org` em produção. Firefox ESR visível, sem override, após `drush cr` no head `4d8993645`: 140/140 checks, 10 purposes × 7 larguras (320/374/375/600/767/768/1440), uma Institution Bar por página e zero overflow. LINKS omite utilitários pelo contrato Portal e não exige menu. Evidência final: `/home/piradopirata/aculta-suite-out/aculta-institution-bar-integrated-all-domains-4d8993645-final/`.
- A mesma rodada viu `.aculta-header` somente em MAIN e Wiki; está ausente em Conta, SUPPORT, Revista, Loja, Cursos, Fórum, Links e Eventos. Pede-A registrado em `web/themes/custom/aculta420/docs/mobile-first-audit.md`: owner Portal deve confirmar/aplicar o contrato de disponibilidade para os dez purposes, manter URLs ligadas a `DomainPurposeManager`/Deployer e testar host de teste e produção durante Alpha. O Twig não contém nomes de rota/purpose; ele consome `domain_presentation.layout.show_site_header`.
- Checkout servidor: `toca`, `/home/piradopirata/projetos/aculta`, Drupal root `web/`, URI `http://default`, DB `aculta_runtime`, Drupal 11.4.10; HEAD `c6d8e89b5` → `4d8993645` (detached), backup ref `backup/dev-before-4d8993645`. Antes da troca, mudanças de outras frentes foram copiadas para `/home/piradopirata/aculta-suite-out/server-head-backup-before-4d8993645/worktree.patch` (SHA-256 `522998dd7e1c215bfef6a30c2988cfcb1ad861134ce7494476f79aa8370c31ac`) e guardadas no `stash@{0}` `backup server work before PR head 4d8993645`; não foram incluídas em commit. A worktree do servidor continua exibindo alterações alheias, preservadas e fora do staging.
- Runtime identificado: 607 tabelas. Após checkout, `updatedb:status`: `[success] No database updates required.`. `config:status`: 397 linhas de saída com diferenças inesperadas (197 itens de configuração); cinco itens de `aculta_faq` (`node.type`, dois displays e dois fields) continuam somente no DB. Snapshot antes da escrita de cache: `/home/piradopirata/aculta-suite-out/aculta-runtime-before-4d8993645/aculta_runtime.sql`, 60.251.799 bytes, modo `0600`, SHA-256 `a31300c73b4de2accaeb53c66dd77c085f56ee59125f83b26d7651b8971e720f`. Não executado `updatedb` (sem pendências) nem `config:import` (diff não exato; Sync sem FAQ). Pede-A permanece: owner deve colocar/reconciliar o tipo e configurações FAQ no Sync e resolver diferenças globais antes de um import seguro; preservar NIDs 567–572.
- `drush cr`: `[success] Cache rebuild complete.`. HTTP root nos dez test hosts: MAIN 200, Conta 302 (redirect de entrada), e SUPPORT/Revista/Wiki/Loja/Cursos/Fórum/Links/Eventos 200. `drush scr scripts/validate-domain-presentation-contract.php`: `DOMAIN PRESENTATION CONTRACT: PASS (169 checks)`. CSS/JS lint do tema, Browser Validators (21), Design Foundations (2431), SDC schema (54 componentes/734 checks), module-theme boundary (3371), sintaxe Python e `git diff --check` passaram. Não houve gate de zoom, teclado completo, WCAG 2.2 AA, dark mode nem reduced motion; THEME-MF3/F11 continuam pendentes.

## Checkout do head 567818b22 (2026-10-29)

- PR branch avançou fast-forward `4d8993645..567818b22`. Servidor de desenvolvimento passou de `4d8993645` a `567818b22` destacado; backup `backup/dev-before-567818b22`. Alterações de outras frentes foram novamente preservadas no patch `/home/piradopirata/aculta-suite-out/server-head-backup-before-567818b22/worktree.patch` e no `stash@{0}` `backup server work before PR head 567818b22`; staging da Frente B continuou restrito.
- Runtime: 607 tabelas; `updatedb:status`: `[success] No database updates required.`; `config:status`: 397 linhas, ainda com 197 diferenças inesperadas e cinco itens de config `aculta_faq` só no DB. Snapshot antes do cache rebuild: `/home/piradopirata/aculta-suite-out/aculta-runtime-before-567818b22/aculta_runtime.sql`, 58.644.733 bytes, modo `0600`, SHA-256 `21067b119790ca6ff2548ac16825de6fc72f18f0096563aab5d75b9add19b2e3`. Nenhum `updatedb` ou `config:import` foi executado.
- `drush cr`: `[success] Cache rebuild complete.`. HTTP MAIN/SUPPORT/Revista/Wiki/Loja/Cursos/Fórum/Links/Eventos: 200; raiz ACCOUNT: 302 para entrada. O smoke visual 140/140 dos dez purposes já havia sido executado com o mesmo CSS publicado em `4d8993645`; `567818b22` não alterou código CSS/tema.

## OCS-Fix — sticky shell com Navigation top-bar (2026-10-29)

- Commit visual `5e1cdedf5` foi publicado por fast-forward em `origin/refactor/opencultures-codigo-visual` (PR #299), após `cf541c637`. O commit subsequente de log teve push rejeitado quando ARQ-09 avançou o remoto para `b18f058e5`; nenhum force push foi usado. Backup do commit documental local `backup/frontb-sticky-log-ccd39`; alterações paralelas protegidas em `/home/piradopirata/aculta-suite-out/backup-frontb-before-arq09-fastforward-20261029/` (patch SHA-256 `5e326a80e8b66dedb329934873dd29d1bf710561e3fcdb3e917b5e1a64089143`, arquivos não rastreados SHA-256 `0dfac98caf9dc5505b3cbd59909e310182adb22e57a04e149f692a78afe24d57`) e stash `foreign work before ARQ-09 safe fast-forward 2026-10-29`. Stash mantido sem reaplicação: o commit ARQ-09 passou a rastrear alguns caminhos que antes eram não rastreados localmente; não houve resolução de conflito de outra frente.
- O problema reproduzido e sua correção, que não alteram o código do módulo Navigation, foram validados em Firefox ESR visível sem injeção: 137/137 checks, dos quais 99 anônimos nas dez homes e 38 autenticados na MAIN. Artefatos: `/home/piradopirata/aculta-suite-out/aculta-navigation-top-bar-integrated-5e1cdedf5/`; detalhes técnicos e limites em `web/themes/custom/aculta420/docs/mobile-first-audit.md`.
- O checkout servido foi sincronizado diretamente ao commit visual `5e1cdedf5` e o Runtime recebeu somente `updatedb` das duas migrações ARQ-05, após snapshot descrito acima. O commit de ARQ-09 (`b18f058e5`) chegou após o primeiro cache rebuild; o checkout passou de `b18f058e5` para `4fd69952a` (documentação operacional), preservando o estado limpo e criando `backup/dev-before-4fd69952a-actual-20261029` para o HEAD anterior. No gatilho repetido, `updatedb:status` retornou `[success] No database updates required.`; `config:status` permaneceu com 397 linhas de drift e aviso `Undefined array key "rename"`, sem import. O Runtime tinha 609 tabelas após as duas migrações ARQ-05. Snapshot antes do novo `drush cr`: `/home/piradopirata/aculta-suite-out/aculta-runtime-before-4fd69952a-20261029/aculta_runtime.sql`, 92.969.746 bytes, modo 0600, SHA-256 `5059a197d70fd1a17e6c1e95050188a035b1bf6d1e9a2cf1ab285c736e2174fa`. `drush cr` PASS; MAIN e Loja HTTP 200. O commit `4fd69952a` não alterou código Drupal/tema em relação a `5e1cdedf5`; a validação visual integrada 137/137 acima continua sendo a evidência do fix.

## Runtime e decisão do formulário unificado (2026-10-29)

- **Decisões relatadas pelo responsável:** o Runtime deve ser trabalhado/alinhado ao `config/sync`; preservar os seis nós FAQ e manter o bundle e suas configurações no Sync; uma ordem direta para trabalhar no Runtime conta como autorização explícita para as escritas necessárias, respeitados snapshot e limites operacionais. A decisão arquitetural do formulário único de endereço está registrada como D-54.
- **Ambiente verificado:** `hostname=toca`, checkout servido `/home/piradopirata/projetos/aculta`, URI `http://default`, Drupal 11.4.10, banco `aculta_runtime`, HEAD `76d9f66bf`; o Runtime tem 609 tabelas. `updatedb:status`: `[success] No database updates required.`
- Comparação por Config Storage API: 53 configurações somente no DB, 14 somente no Sync e 115 diferentes. `config:status` emitiu 397 linhas, incluindo warning `Undefined array key "rename"`. O Runtime tem 227 nomes de módulos em `core.extension`; o Sync tem 213. Os 14 módulos adicionais são `aculta_calendar`, `aculta_commerce_melhorenvio`, `aculta_content_admin`, `aculta_document_viewer_group`, `aculta_events`, `aculta_events_openstreetmap`, `aculta_faq`, `aculta_lms_agenda`, `aculta_lms_messenger`, `aculta_maps`, `aculta_pdf_viewer`, `aculta_pdf_viewer_group`, `aculta_secrets_manager` e `geofield`. Quatro estão sem extensão descobrível no checkout (`aculta_document_viewer_group`, `aculta_pdf_viewer`, `aculta_pdf_viewer_group`, `geofield`). O FAQ tem seis nós, verificado por contagem agregada (`node_field_data`, tipo `aculta_faq`), IDs 567–572 conforme registro anterior.
- Antes de alterações de conteúdo/configuração foi criado snapshot integral `/home/piradopirata/aculta-suite-out/runtime-alignment-e4597a133/aculta_runtime-before-reconcile.sql`, 79.446.252 bytes, modo 0600, SHA-256 `b9f7f4be7eb68df12ea3cf6067a39a2d9ad28c35102e914b51e4d9a31020ce69`. Snapshot externo não versionado; arquivo permanece preservado.
- **Feito:** Drupal respondeu a `drush cr` com `[success] Cache rebuild complete.` no HEAD `76d9f66bf`; MAIN e Loja responderam HTTP 200. A exportação de configuração do Runtime foi guardada temporariamente fora do repo, modo restrito, para comparar os bundles FAQ.
- O commit `5a105ac0f` colocou `aculta_faq` em `core.extension` e os dez YAMLs ativos de `node.type.aculta_faq`, fields, displays, bloco editorial e filtro no `config/sync`. Os dez arquivos foram copiados do export ativo e comparados byte a byte. No head servido `5a105ac0f`, `config:status --prefix=<cada um dos dez nomes>` retornou `No differences between DB and sync directory` nas verificações executadas; a configuração `core.extension` ainda difere pelo conjunto global de módulos. Contagem após o checkout: 6 nós FAQ preservados. Não foi necessário importar esses dez itens, pois o DB já tinha os mesmos valores.
- `drush cr`: `[success] Cache rebuild complete.`; MAIN e Loja HTTP 200. Nenhuma escrita em conteúdo/configuração, `updatedb`, `config:import`, `pm:uninstall` ou DROP foi executada nesta reconciliação. O snapshot permanece preservado.
- **Pendente:** a importação global permanece sem execução. O diff ainda envolve 53 configurações somente no DB, 14 somente no Sync e 115 diferentes, inclusive Commerce/perfis e módulos ativos no DB; quatro extensões referenciadas no Runtime não têm código descobrível. A ordem relatada de alinhamento amplo está registrada, mas a importação global requer reconciliar o código ausente e o plano de retenção/migração dos dados não-FAQ que seriam atingidos. Não há alteração destrutiva implícita na reconciliação focal de FAQ.
- **Próxima implementação autorizada:** D-55 determina Brasil fixo e CEP central nos formulários de Mapas e Eventos; iniciar depois da conclusão da microfase do formulário unificado, mantendo o storage das entidades existentes.

## D-54/D-55 — adaptador de endereço para Events/OSM (2026-10-29)

- **Decisão registrada e aplicada nesta microfase:** o formulário central `AccountAddressForm` em `aculta_portal`, integrado à biblioteca `aculta_portal_cep/cep-address`, é o único contrato de captura/edição completa de endereço no site. Abrange Maps, Events e a revisão humana do importador OSM; nesses locais o país é `BR`, sem seletor, e os dados permanecem nas entidades próprias. Não duplicar formulário, cliente ou endpoint de CEP.
- Implementação no commit `19bceb02d6ec33b03d2adc6b18de140b8990fbf3`, publicado por fast-forward em `origin/refactor/opencultures-codigo-visual` após `309551ee0b99f786452245dfeecfd92977b9323f`. `AccountAddressForm` agora adapta `EventLocation.field_endereco`; a revisão OSM usa o widget Address central, mantém a conversão das tags OSM (incluindo número alfanumérico) e anexa a biblioteca CEP. Dados/eventos não foram salvos nem escritos à API OSM durante a validação.
- Gates no worktree do commit: `scripts/validate-account-address.php` PASS (inclui D-54/D-55), PHP lint PASS, PHPCS Drupal e DrupalPractice PASS, YAML parse PASS, PHPUnit `AccountAddressFormTest` PASS (3 testes/9 assertions), Python `py_compile` PASS e `git diff --check` PASS. Na rodada inicial, Firefox ficou DEFERRED por `DISPLAY` ausente; após a sessão X11 ficar disponível, o smoke corrigido executou em Firefox ESR visível e passou **13/13** no checkout servido `012522288`: rota administrativa canonicalizada no MAIN, leitura de tags OSM, revisão pelo widget Address central, país BR oculto/sem seletor, biblioteca CEP e autopreenchimento de Município/UF, confirmação de salvamento não acionada e sem overflow em 320/390/768/1440 px. ULI foi emitido para o purpose Conta; artefatos em `/home/piradopirata/aculta-suite-out/aculta-maps-events-address-012522288/`. Não houve criação/edição de entidade ou escrita na API OSM.
- Checkout servido mudou de `125483829` (HEAD destacado) para `19bceb02d`; foi criada a referência local `backup/dev-before-19bceb02d`. O working tree tinha arquivos paralelos modificados/não rastreados, preservados no `stash` `preserve shared work before PR head 19bceb02d` e no patch externo `/home/piradopirata/aculta-suite-out/dev-checkout-before-19bceb02d.patch`; não foram descartados nem incluídos no commit da Frente B.
- Identidade Runtime verificada após a troca: `hostname=toca`, Drupal 11.4.10, URI `http://default`, banco `aculta_runtime`; contagem verificada de 610 tabelas. `updatedb:status`: literalmente `[success] No database updates required.`. `config:status` exit 0, 377 linhas, warning `[warning] Undefined array key "rename" ConfigCommands.php:274`, com diferenças DB/Sync incluindo `aculta_maps.settings`; nenhum `updatedb` nem `config:import` foi executado porque não havia update e o diff não era exato/esperado.
- Snapshot integral anterior à limpeza de cache: `/home/piradopirata/aculta-suite-out/runtime-head-19bceb02d/aculta_runtime-before-cache-rebuild.sql`, SHA-256 `be5556da8b20e455bbabcc84b910cf09415c1f48b3bb9ef793ed359b1c94e1b5`, modo 0600. Registros literais de status: `updatedb-status.txt` e `config-status.txt` no mesmo diretório, modo 0600. `drush cr`: `[success] Cache rebuild complete.`; HTTP simples redireciona MAIN/Loja com 301 para HTTPS; requisições HTTPS locais com `--resolve` retornaram MAIN 200 e Loja 200.
- Estado ainda pendente: Firefox validou a revisão visual/CEP do assistente OSM, mas não houve criação/edição persistida de local. Config Sync global continua divergente e sem import; preservar esse bloqueio até a reconciliação documentada anteriormente.

## Plano futuro — busca por nome de rua no endereço (2026-10-29)

- **Correção de escopo confirmada pelo responsável:** o formulário de local usado por Maps deve preencher rua/bairro/cidade/UF a partir do CEP, igual ao formulário geral; essa integração já está ativa via formulário central e `aculta_portal_cep`. A consulta CEP → endereço existe. O plano novo é somente para busca pelo nome da rua/logradouro, capacidade diferente e ainda não implementada; não usar o termo genérico “busca de endereço” como se a consulta por CEP estivesse pendente.
- Plano registrado em `docs/portal/CEP-ADDRESS-SEARCH-PLAN.md`, com referências no README do `aculta_portal_cep`, no contrato do endereço e nos documentos de Maps. O formulário único continua em `aculta_portal`; Maps/Events o consomem com seus modelos próprios. Busca por nome de rua futura pertence ao serviço compartilhado `aculta_portal_cep`, enquanto geocodificação para coordenadas permanece função distinta de `aculta_maps`.
- Só documentação foi alterada nesta microfase; provedor/termos/privacidade/limites permanecem por decidir, sem UI nova, endpoint ou código funcional implementado.
- Para acompanhar a publicação do teste browser `91d40c76c`, o checkout servido foi sincronizado de `012522288` para `91d40c76c`; backup `backup/dev-before-91d40c76c`. Alterações paralelas de SavedContentQuery foram preservadas no stash `preserve shared SavedContentQuery work before PR head 91d40c76c` e patch `/home/piradopirata/aculta-suite-out/dev-checkout-before-91d40c76c.patch`.
- Runtime verificado no novo head: 610 tabelas; `updatedb:status` `[success] No database updates required.`; `config:status` exit 0, 377 linhas, ainda divergente do Sync; nenhum update/import. Snapshot antes do cache rebuild: `/home/piradopirata/aculta-suite-out/runtime-head-91d40c76c/aculta_runtime-before-cache-rebuild.sql`, SHA-256 `a905ae8813118438994c1fa918b18f264c8f2e900445fe10b5dfd8690dcbc3ff`, modo 0600. `drush cr` PASS; HTTPS local MAIN/Loja HTTP 200.

## THEME-MF2 — repetição sticky/offset no head `487596bb0` (2026-10-29)

- Publicação documental `487596bb0` avançou o checkout servido de `91d40c76c` para `487596bb0`; backup local `backup/dev-before-487596bb0`. Mudanças paralelas `SavedContent*` foram preservadas no stash `preserve shared work before PR head 487596bb0`; não foram incluídas em commits desta frente.
- Runtime verificado: host `toca`, Drupal 11.4.10, URI `http://default`, DB `aculta_runtime`, 610 tabelas. `updatedb:status`: `[success] No database updates required.`; `config:status` exit 0, 377 linhas e diferenças ainda presentes; sem `updatedb`/`config:import`. Snapshot pré-cache em `/home/piradopirata/aculta-suite-out/runtime-head-487596bb0/aculta_runtime-before-cache-rebuild.sql`, SHA-256 `a24fb4eacecaebc42915594c0562446f92b6d7f8e4e7e7a02e1bb8814ec0746f`, modo 0600. `drush cr`: `[success] Cache rebuild complete.`; HTTPS local MAIN e Loja HTTP 200.
- CSS servido sem override: Firefox ESR 153.5.1esr e geckodriver 0.37.1 passaram 99/99 checks anônimos nas dez homes, 320/390 px, e 38/38 checks com UID administrativo MAIN em 320/390/1023/1024/1280 px. Sem salto ao carregar; Institution Bar sticky no scroll, respeitando a top-bar administrativa mobile em fluxo e o offset 64px quando desktop/fixa; breakpoint testado sem reload. Evidências em `/home/piradopirata/aculta-suite-out/institution-bar-scroll-anon-487596bb0/` e `.../institution-bar-scroll-admin-487596bb0/`. Sem override ou mudança de código nesta rodada; MF3 segue pendente.


## THEME-MF2 — tabelas de pedidos da Conta (2026-10-29)

- Worktree da Frente B atualizada por rebase até `868f18e22`, igual ao remoto antes da fase. A worktree `revista-editorial/` não rastreada permaneceu intacta e fora do stage. O checkout servido estava destacado em `868f18e22`, limpo; Runtime identificado por Drush como Drupal 11.4.10, URI `http://default`, DB `aculta_runtime`. `updatedb:status`: sem updates; `config:status`: 377 linhas de diferenças conhecidas.
- `portal-account.css` moveu tabelas de pedido/detalhe para regra-base mobile e restaura em `48rem`; somente CSS do tema, teste browser e documentação foram alterados. Nenhum SDC, módulo ou dado do Runtime foi modificado.
- `npm run lint:css`, `git diff --check` e compilação Python PASS. Firefox ESR visível no Runtime, UID autorizado sem pedidos não-rascunho: folha CSS candidata injetada passou 22 verificações em 320/360/390/599/600/767/768/1024/1280/1440px, incluindo estado vazio real, linha sintética rotulada, overflow zero e igualdade desktop de largura/padding/borda/alinhamento. A emissão de `data-label` em linha real ficou DEFERRED porque a conta só mostra o estado vazio; não houve gravação/submissão. JSON em `/home/piradopirata/aculta-suite-out/account-orders-868f18e22-final2/`.
- Antes da publicação, a rodada de candidato não alterou Runtime/cache. Depois do fast-forward, checkout servido mudou de `868f18e22` para `11eb83644`; o ref local `refactor/opencultures-codigo-visual` permaneceu como referência do HEAD anterior. `hostname=toca`, Drupal 11.4.10, URI `http://default`, DB `aculta_runtime`; `updatedb:status` sem updates e `config:status` exit 0 com 377 linhas de diferenças, sem import. Snapshot integral anterior a `drush cr`: `/home/piradopirata/aculta-suite-out/runtime-head-11eb83644/aculta_runtime-before-cache-rebuild.sql`, SHA-256 `f4e59d9eb256ea3c8676a6dae39ec24952948ee07bc2ab518a33234d8a726804`, modo 0600. `drush cr` PASS, MAIN/SHOP HTTP 200. Foundation 348, Shell Contract 11, Design Foundations 2434 e SDC schema 734 checks PASS. Firefox ESR com CSS servida sem override repetiu 22 PASS/0 FAIL e uma DEFERRED para linha real com dados, em `/home/piradopirata/aculta-suite-out/account-orders-11eb83644-integrated/`. THEME-MF2 segue parcial; MF3 e OCS-F11 não estão aprovados. Zoom, teclado completo, modo claro/escuro, reduced motion, leitores de tela e WCAG 2.2 AA permanecem pendentes.

## THEME-MF2 — composição institucional mobile-first (2026-10-29)

- A microfase foi feita no worktree `opencultures-f0-adm8-e8`, inicialmente em `79741f1ae`; o remoto avançou para `7908ea166` com três commits Studio. Após verificar que nenhum tocava nos caminhos desta fase, rebaseei apenas meu trabalho sobre esse HEAD. O commit `d3212acc6` da Revista apareceu como pai local durante a preparação; removi-o da cadeia enviada, sem alterar ou publicar esse commit.
- `institutional.css` passou a usar layout móvel-base para CTAs do hero, grade `aculta-areas` e pares de detalhes institucionais; `min-width: 36rem`/`48rem` restaura as composições largas. A exceção da View de projetos continua limitada ao mobile porque o SDC `content-grid` controla suas variantes desktop. Commit próprio `25972aa32 style(theme): migrate institutional layout to mobile first`, enviado por fast-forward `7908ea166..25972aa32` para `origin/refactor/opencultures-codigo-visual` (PR #299); não contém commits de outras frentes.
- Firefox ESR visível comparou CSS anterior e candidato em fixture sintético em 320/360/390/575/576/599/600/767/768/1024/1280/1440px: 24/24 PASS, sem overflow e PNGs idênticos. Limite: não prova o render de conteúdo institucional real. Evidências em `/home/piradopirata/aculta-suite-out/aculta-theme-institutional-layout-final/`. `npm run lint:css`, SDC schema 54/734, Design Foundations 2434, Browser Validators 21, índice de status 48 componentes e Foundation via Drush 348 PASS; `git diff --check` PASS. Sem mudança de JS/PHP, então ESLint/PHPUnit/PHPCS não aplicáveis.
- Runtime identificado: `hostname=toca`, Drupal 11.4.10, URI `http://default`, banco `aculta_runtime`, 610 tabelas. Foundation foi executado em modo somente leitura pelo Drush do checkout servido, apontando para os arquivos candidatos do worktree. Nenhum `updatedb`, `config:import`, escrita de conteúdo ou `drush cr` ocorreu nesta fase. Após a publicação, o checkout servido permaneceu limpo/destacado em `7908ea166`, enquanto `origin/refactor/opencultures-codigo-visual` avançou para `25972aa32`; a pedido do responsável, deixei a sincronização do docroot com seu owner. Revisão visual das páginas servidas após sincronização está pendente; MF2 parcial, MF3 e OCS-F11 não aprovados.

## LOC/IDX-LOC - planejamento documental na PR #299 (2026-10-29)

- Pedido do responsável: publicar plano de implementação de Locais, ajustar roadmaps/documentação e incluir aculta_index. Base remota lida: 3250e4b4f940a9a33008206885bdb6e33125f7a2; publicação por fast-forward com expected_sha e nova conferência de HEAD, sem nova branch/PR, merge ou force-push. O SHA final do commit é identificável pelo histórico Git deste registro.
- Plano canônico: [LOC-00 a LOC-08](../architecture/PLANO-LOCAIS-ACULTA-EVENTS-INDEX.md). Integra Events, Calendar, Maps, Studio, User/Portal e Index, com contrato IDX-LOC de projeção, canônicos, relações via Datas, fila, consultas, grants/cache e manutenção.
- Alterações exclusivamente documentais: plano, roadmaps/contratos/readmes, status e README raiz gerado. Events: escopo ampliado Dev; RC anterior preservado como evidência histórica, manifesto inalterado. Demais versões e evidências preservadas.
- Verificação desta sessão: diff --check, links locais dos documentos alterados e consistência YAML/manifestos/tabela gerada conferidos localmente. A geração/conferência da tabela usa transcrição do algoritmo de validate-project-status-index.php em Python; o comando PHP oficial não foi executado, pois PHP/Composer não estão disponíveis neste ambiente. Nenhum teste funcional foi executado ou apresentado como aprovado.
- Checkout servido, Drush, banco ativo, gatilho de banco e HTTP: **não verificados nesta sessão**. Nenhuma sincronização do docroot, cache rebuild, updatedb/config:import, migração ou operação do Index foi executada. Sincronização/verificação do checkout servido permanece para o owner local conforme AGENTS.md; não presumir que a publicação GitHub alterou o servidor.

## LOC-04 / IDX-LOC - conclusão da integração de Locais com Content Index na PR #299 (2026-10-30)

- Conclusão da integração de Locais do ACULTA Events com o `aculta_index` conforme o plano `docs/architecture/PLANO-LOCAIS-ACULTA-EVENTS-INDEX.md` e checklist `IDX-LOC` em `docs/portal/ACULTA-CONTENT-INDEX.md`.
- Contratos e arquivos alterados:
  - `web/modules/custom/aculta_portal/modules/aculta_events/src/Entity/Event.php` e `EventLocation.php`: implementação de `toUrl('canonical')` e `toCanonicalUrl(?DomainPurposeManager $purposes)` gerando URLs canônicas no propósito `'eventos'` via rotas `aculta_events.page` e `aculta_events.location_canonical`.
  - `web/modules/custom/aculta_portal/modules/aculta_events/src/ContentIndex/EventContentIndexProvider.php` e `LocationContentIndexProvider.php`: injeção de dependência via container (remoção de chamadas estáticas `\Drupal::`), URLs canônicas via DomainPurposeManager, allowlist restrita para Locais (sem endereço exato, coordenadas ou contatos privados), validação estrita de `field_status === 'publicado'`, dedução de relações `event_location` a partir de datas físicas com checagem de visibilidade anônima.
  - `web/modules/custom/aculta_portal/modules/aculta_events/src/Hook/EventDateHooks.php` e `aculta_events.services.yml`: DI para injeção de `EntityTypeManagerInterface`, `ContentIndexWorkManager` e `ContentIndexProviderRegistry`; hooks para enfileirar eventos afetados e invalidar tags de cache na inserção, atualização ou exclusão de `aculta_event_location`.
  - `web/modules/custom/aculta_portal/modules/aculta_events/src/Form/EventForm.php` e `EventDateCreateForm.php`: validação no Entity API de existência de `local_id`.
  - `web/modules/custom/aculta_portal/modules/aculta_index/src/Query/ContentIndexRelationQuery.php`: junção SQL ajustada para suportar alvos de relação sem idioma específico (`und`), preservando a resolução de idioma padrão para alvos traduzíveis.
- Testes automatizados executados no ambiente local de desenvolvimento com banco isolado `SIMPLETEST_DB` (`aculta_simpletest` via MariaDB local):
  - `EventContentIndexProviderKernelTest.php`: 6 testes, 141 asserções, 0 falhas (100% PASS). Cobre metadados, URLs canônicas, deduplicação de relações `event_location`, filtros de modo/visibilidade, consulta via API com isolamento de propósito e restauração de domínio, resiliência da fila/backfill sem exclusão da fonte, comportamento com Index desativado/sem geração e autocomplete/seleção imediata.
  - `EventLocationManagementKernelTest.php`: 7 testes, 148 asserções, 0 falhas (100% PASS).
  - Suíte completa `ContentIndexRebuilderKernelTest.php`: 21 testes, 295 asserções, 0 falhas (100% PASS).
  - Gate de rotas: `php scripts/validate-no-temporary-route-aliases.php` PASS (35 arquivos inspecionados).
  - Qualidade de código: `git diff --check` PASS e `php -l` em todos os arquivos modificados PASS.
- Runtime `aculta_runtime`: **não modificado**. Nenhum `rebuild`, `updatedb`, `config:import` ou alteração de gerações/auditorias no Runtime foi executado.

## Incidente HTTP 500 — hook legado de Atividades no checkout de desenvolvimento (2026-10-30)

- Diagnóstico no checkout servido em `toca`: o container cacheado referenciava a classe removida `ActivityLocationFormHooks`; o código local em andamento registrava `ActivityRetirementHooks`, cujo terceiro argumento de `entity_create_access` estava tipado como `string`, embora o Drupal forneça `null` em algumas verificações. O estado de Git do checkout servido já continha alterações não commitadas em `aculta_events`; elas foram preservadas.
- Antes de qualquer escrita Runtime, confirmação read-only: checkout `/home/piradopirata/projetos/aculta`, HEAD `36afeec45`, detached; URI `http://default`; banco `aculta_runtime`; 612 tabelas; `updatedb:status` sem atualizações. Snapshot integral: `/home/piradopirata/aculta-suite-out/runtime-500-fix-20261030-102130/aculta_runtime.sql`, 97.948.486 bytes, SHA-256 `006256b577525280f22c1cba545b0526ad1f1ffded2b7b0772952e4ed93ca587`.
- Correção emergencial local: assinatura do hook passou a aceitar `?string`; PHP lint PASS; `drush cr` PASS. A alteração foi aplicada ao arquivo ainda não rastreado no checkout servido, cuja cópia anterior está preservada no diretório do snapshot. O commit remoto `29961db75`, publicado durante o diagnóstico, passou a rastrear o mesmo arquivo e a mesma assinatura (hashes SHA-256 iguais entre Runtime e Sync); não houve `updatedb`, importação de configuração ou alteração de conteúdo.
- Verificação após rebuild, por HTTPS local com Host correto: MAIN `/` 200; Wiki `/` 200; `/node/add/activity` 404; erros anônimos em `/dados/endereco` 403. Após esses pedidos, a consulta de watchdog não encontrou novos erros das classes antiga/nova do hook. O acesso autenticado não foi exercitado. A correção emergencial permanece no arquivo local não rastreado e precisa ser incorporada ao commit do trabalho de Eventos antes de substituir o checkout servido.
- A atualização local inicialmente integrou `36afeec45` e revelou conflito revisado em `EventLocationModalWidget.php`; a resolução preservou o token de apresentação e a rota gerada por `Url::fromRoute()`. O remoto avançou depois a `29961db75`; rebase adicional concluído sem conflito e sem push. A sessão X11 ficou disponível e Firefox foi executado no checkout servido, mas não no HEAD da branch, que não pode substituir o checkout com alterações/untracked de Eventos ainda presentes.
- Após o cache rebuild, Firefox ESR visível (`DISPLAY=:0`) executou `purpose-matrix.py` anonimamente nas dez raízes e nove viewports 320/360/390/599/600/768/1024/1280/1440: 90/90 capturas, zero deferred e zero overflow medido; Institution Bar 90/90, Domain Header 18/90. Evidência fora do Git: `/home/piradopirata/aculta-suite-out/runtime-500-purpose-matrix-20261030/`. A matriz testou o checkout servido `36afeec45` com alterações locais não commitadas, não o HEAD da branch. A inspeção confirmou a duplicação do rótulo “Próxima data” em Eventos e a homepage da Revista sem conteúdo editorial/H1; Cursos e Apoio também não apresentaram H1 no snapshot. A correção da data está em `PortalUiHooks` no HEAD local e tem Unit PASS (32 testes/239 assertions na suíte Unit focal), mas permanece sem confirmação visual servida; MF3/F11 continuam sem aprovação.
- A prevenção do TypeError foi registrada em `ActivityRetirementHooksTest`: bundle nulo segue neutro e `activity` negado; 2 testes/4 assertions PASS. PHP lint, PHPCS Drupal/DrupalPractice e `git diff --check` PASS.
- Observação pós-publicação do commit remoto `29961db75`: checkout servidor detached em `29961db75`, com alteração local preservada em `ActivityRetirementHooks.php`; Runtime `aculta_runtime`, 612 tabelas; `updatedb:status` sem updates e `config:status` ainda com 199 diferenças globais (nenhum import executado). Snapshot anterior mantido; `drush cr` PASS. Smokes HTTPS nas dez raízes: nove HTTP 200; Conta 302 para login; consulta de watchdog não encontrou novos erros dos hooks. Nenhum `updatedb`, import, uninstall ou mudança de conteúdo. O código rastreado em `29961db75` inclui a assinatura nullable que elimina o TypeError; alterações locais adicionais de menu em `ActivityRetirementHooks.php` foram preservadas e não foram incorporadas ao commit desta sessão.

## Reincidência HTTP 500 e verificação de rotas administrativas (2026-10-30)

- **Relato do responsável:** o site voltou a apresentar HTTP 500 e pediu correção independente de autoria. **Verificado:** host `toca`; checkout servido `/home/piradopirata/projetos/aculta`, HEAD destacado `29961db75`; URI `http://default`; Drupal 11.4.10; banco `aculta_runtime`, 612 tabelas. `updatedb:status`: sem atualizações; `config:status`: 199 diferenças, sem import global.
- Watchdog apontou `ServiceNotFoundException` para `aculta_events.permission_policy` na visão de Eventos, embora a definição já estivesse no arquivo local não commitado `aculta_events.services.yml`. Foi criado snapshot antes do rebuild: `/home/piradopirata/aculta-suite-out/runtime-500-recovery-20261030-1051/aculta_runtime.sql.gz`, SHA-256 `61ebb3ee794408ebe5f685f9bc91d5c28c4ae3cb7caae1353893c17eef56cc38`. `drush cr` PASS. Depois do rebuild, requisições completas pelo HttpKernel com UID 1 às rotas `/painel-administrativo/eventos` e `/painel-administrativo/eventos/configuracoes` retornaram HTTP 200; anônimo recebeu 403, preservando o requisito de acesso. Raízes públicas dos dez purposes: nove 200 e Conta 302 para login.
- A página `message.settings` falhava porque a configuração ativa não existia e `purge_methods` ficava `NULL`. Foi criado novo snapshot antes da correção direcionada: `/home/piradopirata/aculta-suite-out/runtime-500-recovery-20261030-1051-config/aculta_runtime.sql.gz`, SHA-256 `e7264a1f0807841e919b9c6ea8f0042d7ffdb8f1ae9273b58aff526fc81cc242`. Adicionado `config/sync/message.settings.yml` com os valores padrão do módulo Message instalado e aplicado somente esse objeto por `drush config:set`; nenhuma importação global. A rota de configurações Message retornou HTTP 200 no HttpKernel com UID 1. A origem Sync e o ambiente Runtime agora têm o mesmo objeto.
- O gate de dependências do Sync estava vermelho porque seis itens referiam `aculta_events` ou `aculta_lms_agenda`, ausentes de `core.extension.yml`. A leitura do Runtime confirmou 224 módulos habilitados. Foram adicionados ao Sync os 11 módulos ativos que faltavam (`aculta_calendar`, `aculta_commerce_melhorenvio`, `aculta_content_admin`, `aculta_events`, `aculta_events_openstreetmap`, `aculta_lms_agenda`, `aculta_lms_messenger`, `aculta_maps`, `aculta_secrets_manager`, `aculta_ugc`, `geofield`); não houve desinstalação. Comparação posterior: zero módulos habilitados no Runtime ausentes do Sync; `standard` é o único item Sync-only e é o perfil de instalação. `validate-config-extension-dependencies.php` PASS (1671 checks); não foi executado `config:import`.
- Uma requisição administrativa da Galeria falhou no HttpKernel com incompatibilidade de tipo: o list builder declarava `Drupal\Core\Path\AliasManagerInterface`, enquanto `path_alias.manager` implementa `Drupal\path_alias\AliasManagerInterface`. Correção e teste Unit foram feitos na branch desta frente; **a correção de código ainda não está no checkout servido**. O acesso HTTP autenticado real não foi verificado porque o ULI disponível respondeu 404; não declarar a Galeria resolvida no Runtime.
- `/painel-administrativo/aparencia/ui/components` também falhou no HttpKernel de teste: a chamada `block(dropdown_menu)` do SDC `bootstrap:dropdown` passava um render array como nome de bloco. Foi criado SDC substituto compatível via `replaces: bootstrap:dropdown`, mantendo schema/props/slots, com story e documentação. Schema SDC, compatibilidade Core e renderização Twig isolada PASS; o componente ainda não está servido no Runtime, então a rota requer reteste após a mudança chegar ao checkout.
- A verificação de H5P pelo HttpKernel/Drush executou como `piradopirata`, sem o grupo `aculta`; o diretório privado `.../h5p` é legível e gravável por `www-data` (grupo `aculta`). Não foi alterado permissões. A resposta H5P por HTTP com sessão autenticada não foi verificada. As duas falhas de agregados CSS registradas durante a limpeza apontavam para hashes antigos; os arquivos atuais e as folhas CSS referenciadas pela home responderam HTTP 200. As demais falhas listadas no watchdog vieram das requisições internas de diagnóstico e não devem ser tratadas como smokes HTTP externos.
- Nenhum `updatedb`, import global, desinstalação, alteração de conteúdo, mudança de ACL ou código rastreado no checkout servido foi executado. Os arquivos paralelos não commitados de `aculta_events` foram preservados. O snapshot 10:51 precede o ajuste direcionado de configuração e está fora do Git.

## Publicação e estado do checkout servido — 2026-10-30

- HEAD da branch antes do push: `f007768cd`; HEAD remoto anterior: `f6b5bdb45`. `git fetch` confirmou que o remoto era ancestral, com divergência `0 44`. Push fast-forward executado: `f6b5bdb45..f007768cd` em `refactor/opencultures-codigo-visual` (PR #299). Nenhum force push ou merge.
- Gates executados no HEAD publicado: lint CSS e JS; schemas SDC (55 componentes, 756 checks); design foundations (2452 checks); dependências de extensão (1671 checks); aliases de rota (35 arquivos); índice de status (48 componentes); Unit `AcultaGalleryListBuilderTest` (1 teste, 3 assertions); `git diff --check`. Todos passaram. O aviso de diretório `sites/simpletest/browser_output` sem escrita apareceu no início do PHPUnit, mas o teste terminou PASS. `validate-aculta420-foundation.php` em Runtime permanece adiado porque o checkout servido ainda não contém o SDC novo.
- O checkout servido continua em `f6b5bdb45`, detached, com alterações paralelas não commitadas e arquivos novos em `aculta_events`. A inspeção confirmou conflito de caminho com o head publicado; nenhum stash, backup de branch/ref, checkout, cache rebuild ou alteração no checkout foi executado para preservar esse trabalho concorrente. Portanto, as correções Gallery e dropdown ainda não foram verificadas no site servido.
- Rechecagem somente leitura no Runtime: `updatedb:status` retornou `[success] No database updates required.`; `config:status` continua mostrando diferenças amplas e alerta `Undefined array key "rename"` (sem import). As requisições HTTP tentadas sem host resolvível retornaram 301 para `https://default/`, e o host `default` não resolveu; home e loja HTTP 200 do checkout servido não verificados nesta rodada. Não foi feita escrita no banco nem snapshot novo.

## Continuação: confirmação HTTP e correção do mapa (2026-10-30)

- Sem trocar o checkout servido (`f6b5bdb45`), usei os hosts de teste declarados em `aculta_deployer/config/deploy.json`, resolvidos para `127.0.0.1` por `curl --resolve` sobre HTTPS. Respostas anônimas: `aculta.toca.net.br/` 200; `loja.aculta.toca.net.br/` 200; `eventos.aculta.toca.net.br/` 200; `/painel-administrativo/eventos` no host Eventos 302 para autenticação. Assim, a home, Loja e Eventos não reproduzem 500 nesta verificação pública; demais rotas administrativas não foram consideradas autenticadas.
- Corrigido gate documental `validate-module-theme-map.php`: `geofield` estava habilitado no Sync, mas ausente do mapa módulo-tema. Agora o gate passa (210 checks). A alteração documenta o uso do campo geográfico sem atribuir apresentação própria ao tema.
- A tentativa de rodar `validate-aculta420-foundation.php` e `validate-aculta420-shell-contract.php` a partir da worktree candidata encontrou Drupal sem container inicializado nesse contexto Drush; processos foram interrompidos ao detectar recursão do wrapper. Não contabilizar esses gates como PASS. O gate de ownership (`validate-module-theme-boundary.php`) passou (3432 checks); seu gate irmão mapa passou após a correção.

## Smokes adicionais de todos os purposes — 2026-10-30

- No checkout servido `f6b5bdb45`, Firefox/browser não foi necessário para a checagem HTTP pública; `curl --resolve` usou os dez `test_host` do Deployer. Resultado de `/`: MAIN 200, SUPPORT 200, Conta 302 para login, Revista 200, Wiki 200, Loja 200, Fórum 200, Cursos 200, Links 200 e Eventos 200. Nenhuma home de purpose reproduziu HTTP 500.
- Pelo Drush do checkout servido, o contrato de shell executado sobre os arquivos da candidata passou (`ACULTA420 B.4 SHELL CONTRACT: PASS (11 checks)`). O Foundation executado no checkout servido falhou porque o allowlist daquele HEAD antigo não contém `aculta-event-page.html.twig`; esse template já existe no HEAD servido desde `fe0fd8897` e consta no validator candidato. A falha não aponta erro de renderização e deixa explícita a necessidade de avançar o checkout para validar o Foundation do head atual.

## Rechecagem do checkout servido após a publicação (2026-10-30)

- `origin/refactor/opencultures-codigo-visual` e a worktree de integração estão em `9c30dc52a`; o checkout servido permanece em `f6b5bdb45` (HEAD destacado). A leitura do status confirmou alterações paralelas extensas em `aculta_events`, inclusive arquivos de rotas, serviços, formulários, acesso e novos arquivos de configuração/teste. Não foi feito stash, checkout, cache rebuild ou edição desses arquivos.
- Gatilho somente leitura no checkout servido: Drupal 11.4.10, root `/home/piradopirata/projetos/aculta/web`, URI `http://default`; `updatedb:status` retornou `[success] No database updates required.`; `config:status` mantém diferenças amplas e o aviso de chave `rename`. Não houve importação ou escrita no Runtime.
- Foundation candidata executada pelo Drush servido falhou na descoberta de `aculta420:dropdown`, ausente do registry do HEAD servido antigo. Isso demonstra que a candidata ainda não está carregada no Runtime; não invalida os gates estáticos SDC/Twig já executados no commit publicado. A validação visual/admin de Gallery e UI Patterns continua pendente até o checkout servido avançar sem sobrescrever a edição paralela.

## Reexecução de gates focais na branch publicada (2026-10-30)

- Reexecução correta no HEAD `73afdee49`: lint CSS/JS PASS; SDC (55 componentes, 756 checks) PASS; Design Foundations (2452 checks) PASS; Browser Validators (21) PASS; `validate-module-theme-boundary.php` (3432) PASS; `validate-module-theme-map.php` (210) PASS; dependências de extensão (1671; 1523 arquivos) PASS; `git diff --check` PASS.
- PHPUnit focal PASS: Gallery (1 teste/3 assertions), Studio Hub (4/12), WE Sativa (4/4) e `PortalUiHooksTest` (17/106). PHP lint e PHPCS Drupal/DrupalPractice da Gallery PASS. PHPUnit emitiu somente o aviso de diretório HTML de browser output não gravável; as suítes finalizaram com código zero.
- Uma primeira chamada não executou o validator de browser porque o nome do script foi digitado incorretamente; repetida com `scripts/validate-browser-validators.php`, que passou. Nenhuma falha válida ficou aberta nessa rodada estática.

## Kernel focal após carregar a configuração de teste (2026-10-30)

- `WikiLeadImageLifecycleKernelTest` executado com `SIMPLETEST_DB` carregado de `/home/aculta/secrets/simpletest-db.env` sem imprimir valores, usando a base dedicada `aculta_simpletest`: PASS, 29 testes e 1758 assertions. Houve 369 depreciações repetidas, principalmente `webform_score_entity_field_access()` (parâmetro nullable implícito), além de avisos de APIs Core/contrib obsoletas. Nenhuma falha ou assertion vermelha.
- `EventLocationModalAndAdminKernelTest`, com a mesma base dedicada: PASS, 5 testes e 75 assertions, 24 depreciações de Core/Address/Symfony. O teste não usou `aculta_runtime`.
- Uma tentativa inicial do Kernel Wiki sem exportar `SIMPLETEST_DB` resultou em 29 erros de harness (“There is no database connection”), zero assertions. Foi repetida pelo procedimento documentado acima e passou; a tentativa inicial não representa falha de código.

## MCP ACULTA — Tool Bridge público de documentação (2026-10-30)

- Em `aculta_runtime` foram confirmados host `toca`, checkout `/home/piradopirata/projetos/aculta`, HEAD `096ecbfae`, Drupal 11.4.10, URI `http://default` e 620 tabelas antes da instalação. `updatedb:status` não apontava updates; `config:status` tinha divergências globais existentes, sem importação.
- Snapshot íntegro antes de habilitar `aculta_mcp_client`: `/home/piradopirata/aculta-backups/aculta-runtime-install-aculta-mcp-client-20261030/aculta_runtime-before-install.sql`, SHA-256 `fe370053c8447deafd314dca711df3fa70ef6bf1bfcc04d26084d778493e5db6`, 60.037.880 bytes, modo 0600. Módulo habilitado pelo Drush e cache reconstruído; não houve `updatedb` nem `config:import`.
- `aculta_mcp_client` declara quatro plugins Tool API e quatro MCP Tool Configurations: busca Mercado Pago, busca Melhor Envio, detalhes de endpoint e lista de endpoints. Endpoints e ferramentas remotas têm allowlist fixa, limites globais e saída limitada; a conexão é pública e as ferramentas não aceitam dados de conta, pedidos, endereços, pagamentos, etiquetas ou outras operações financeiras/logísticas.
- O Drupal MCP Client contrib não foi instalado: requer `mcp/sdk ^0.7.1`, incompatível com o MCP Server instalado (`^0.9`). O módulo ACULTA adapta somente o fluxo HTTP de `Mcp\\Client`/`HttpTransport`, usando o SDK já presente; usa a Key existente para autenticar a consulta de documentação do Mercado Pago, sem expor o valor em configuração ou logs.
- Smoke ponta a ponta pelo endpoint público `https://aculta.toca.net.br/mcp`: `tools/list` anunciou as quatro ferramentas; busca Mercado Pago (Checkout Pro) retornou 33.863 bytes, busca Melhor Envio (`frete`) 765 bytes e listagem Melhor Envio 5.039 bytes. Todas retornaram `isError=false`. Corrigido também import de classes ausentes na classe-base que causava erro genérico do Tool Bridge.

## MCP ACULTA — Resource público de capacidades (2026-10-30)

- Implementado no módulo Drupal próprio `aculta_mcp_client` o Resource Provider `aculta_mcp_client:public_capabilities`. O URI `aculta://public/mcp/capabilities` serve um documento estático Markdown com as ferramentas MCP públicas e as restrições de segurança. Não consulta entidades nem conteúdo do site.
- Runtime preflight no host `toca`: checkout `/home/piradopirata/projetos/aculta`, HEAD `096ecbfae`, URI `http://default`, banco `aculta_runtime`, 620 tabelas; `updatedb:status` sem pendências. `config:status --prefix=mcp_server` inicialmente mostrou o novo objeto `mcp_server.resource_providers` apenas no Sync e configurações MCP Tool Bridge existentes somente no DB; nenhuma configuração não relacionada foi importada.
- Snapshot imediatamente antes da escrita focal no Runtime: `/home/piradopirata/aculta-backups/aculta-runtime-mcp-resource-20261030/aculta_runtime-before-mcp-resource.sql`, SHA-256 `bdce4ec80b99ef25260e2aecd083fd39e4e8c50e9417071e5dfe17fa02cc6fb7`, 103.388.620 bytes, modo 0600.
- Configurado pelo plugin manager e Configuration API do MCP Server, habilitando somente `aculta_mcp_client:public_capabilities`; configuração alinhada a `config/sync/mcp_server.resource_providers.yml`. `drush cr` PASS; `config:status --prefix=mcp_server` não mostra diferença para esse objeto. Nenhum `updatedb` nem `config:import` executado.
- Smoke remoto MCP público: `resources/list` retornou o URI com MIME `text/markdown`; `resources/read` leu 1.321 bytes e confirmou a descrição do escopo público e a restrição a dados financeiros. Acesso ao recurso é anônimo por decisão de arquitetura, limitado ao texto fixo no código.

## MCP ACULTA — DHTMLX, instruções e catálogo público (2026-10-30)

- Ampliado `aculta_mcp_client` com os clientes HTTP MCP `Search` e `Inference` do DHTMLX, em endpoint fixo `https://docs.dhtmlx.com/mcp`. São seis plugins Tool API no total; os dois novos têm limite combinado de 15 chamadas/minuto, perguntas de até 300 caracteres e sem credencial. O DHTMLX informa que consultas podem ser registradas; descrições e Resources alertam para não enviar dados pessoais/confidenciais.
- O MCP Resource Provider passou a publicar três documentos Markdown estáticos: `aculta://public/mcp/capabilities`, `aculta://public/aculta/overview` e `aculta://public/aculta/integrations`. Conteúdo curado a partir dos READMEs, contratos de integração e implementação pública; sem conteúdo de entidades, config Runtime, credenciais, rotas internas ou dados operacionais.
- O hook `mcp_server_instructions_alter` fornece orientações de segurança e uso no handshake MCP. `osm-mcp` e `aculta-drupal` permanecem conexões locais de Codex/Claude; OSM não é proxied por envolver consultas de localização e transporte stdio, e o MCP Drupal local informa metadados operacionais.
- Fontes de implementação verificadas: os schemas de DHTMLX `Search` e `Inference` foram lidos via MCP e chamadas de teste a ambos retornaram sucesso. Política oficial: https://docs.dhtmlx.com/suite/guides/mcp-server/ (consultas podem ser registradas). Configuração atual do Codex e `.mcp.json` já incluíam `aculta-drupal`, DHTMLX e OSM.
- Runtime preflight no host `toca`, HEAD `da02bc467`, URI `http://default`, banco `aculta_runtime` com 620 tabelas; `updatedb:status` sem pendências. Antes de criar as configurações MCP DHTMLX, snapshot `/home/piradopirata/aculta-backups/aculta-runtime-mcp-dhtmlx-20261030/aculta_runtime-before-dhtmlx-tools.sql`, SHA-256 `21e03c6e08305e48a8df20bbb8f2bc301d078119debfb840560413a5ae62aa63`, 99.003.006 bytes, modo 0600.
- Criadas pela Entity API do MCP Server duas MCP Tool Configurations enabled: `aculta_dhtmlx_documentation_search` e `aculta_dhtmlx_documentation_answer`. Total: seis configs Tool Bridge; `aculta_mcp_client` e o Resource Provider já estavam habilitados. `config:status --prefix=mcp_server` mostra apenas drift anterior de `mcp_server.settings` e configs Tool Bridge existentes no DB; o Resource Provider segue alinhado ao Sync. Sem `updatedb` e sem `config:import`.
- Smoke ponta a ponta pelo endpoint MCP público: `tools/list` anunciou os dois tools DHTMLX; `resources/list` anunciou os três Resources; handshake trouxe a instrução de ler o catálogo de capacidades. `Search` respondeu 2.945 bytes e `Inference` 1.160 bytes; as leituras dos três Resources retornaram 2.470, 898 e 1.377 bytes. `drush cr` PASS; páginas MAIN e Loja HTTP 200.

## MCP ACULTA — Prompts públicos com Resources incorporados (2026-10-30)

- Criados três MCP Prompt Configs: `aculta_consultar_plataforma` inclui o Resource de visão geral; `aculta_planejar_integracao_publica` inclui o catálogo de integrações; `aculta_pesquisar_documentacao_mcp` inclui capacidades e ferramentas disponíveis. Cada prompt tem argumento obrigatório e mensagens com um bloco `resource` MCP embutido e uma instrução contextual.
- Os modelos deixam claro que não executam ferramentas automaticamente, não inventam conteúdo, não devem receber dados pessoais/financeiros/confidenciais e devem tratar material dos provedores como referência não confiável. Prompts não adicionam acesso a entidades ou operações no Drupal.
- Os YAMLs de `config/install` permitem a instalação/reprodução do módulo; a documentação do módulo registra o vínculo dos anexos com os Resources para evitar divergência em futuras edições. Referência de formato: especificação MCP oficial de prompts e documentação do SDK MCP sobre incorporação de Resources (`https://ts.sdk.modelcontextprotocol.io/v2/servers/prompts`).
- Runtime preflight no host `toca`, checkout `/home/piradopirata/projetos/aculta`, HEAD `91243060f`, URI `http://default`, banco `aculta_runtime` com 620 tabelas; único update pendente era `aculta_mcp_client_update_10001`. `config:status --prefix=mcp_server` mostrou prompts e configurações MCP anteriores apenas no DB, sem importação.
- Snapshot antes do update focal: `/home/piradopirata/aculta-backups/aculta-runtime-mcp-prompts-20261030/aculta_runtime-before-prompts.sql`, SHA-256 `29d8aee8f6bcc918d82072ddd088e14100094fdaf48fc3638b41aa2adc85292a`, 98.559.332 bytes, modo 0600. Executado apenas `drush updatedb` para instalar os três prompts faltantes e `drush cr`; depois, `updatedb:status` sem pendências e banco com 620 tabelas. Nenhum `config:import` foi executado.
- Smoke MCP anônimo em `https://aculta.toca.net.br/mcp`: `prompts/list` anunciou três prompts; `prompts/get` para cada um retornou o argumento preenchido e o Resource embarcado com URI correspondente e texto completo (898, 1.377 e 2.466 bytes). Sem erros MCP.

## THEME-MF0/OCS-F0 — unicidade do shell multidomínio (2026-10-30)

- Publicado na PR #299 o commit `579e67c53` (`test(theme): assert unique shell headers across purposes`), por fast-forward de `d5bf8782a`. Arquivos próprios: o probe visual `tests/browser/aculta-studio/purpose-matrix.py`, seu README, `mobile-first-audit.md` e `visual-cross-product-roadmap.md`.
- Firefox ESR visível (`DISPLAY=:0`) capturou 90 combinações (dez raízes públicas × 320/360/390/599/600/768/1024/1280/1440 px), sem capturas DEFERRED, com Institution Bar e Domain Header exatamente uma vez em 90/90 e zero overflow horizontal medido. Artefatos: `/home/piradopirata/aculta-suite-out/domain-header-purpose-matrix-20261030/`. Limites: somente raízes anônimas; não cobre rotas internas, sessões autenticadas, zoom, teclado ou WCAG.
- Verificado antes da matriz: checkout servido no HEAD `44946347d`, host `toca`, URI Drush `http://default`, Drupal 11.4.10, banco `aculta_runtime`. O checkout servido só mudou depois da execução, segundo o reflog.
- Verificado após a publicação: PR #299 passou a HEAD `3b193b714`; o checkout servido também está nesse HEAD destacado. `updatedb:status` informou literalmente `[success] No database updates required.` `config:status` emitiu warning `Undefined array key "rename" ConfigCommands.php:274` e listou diferenças entre Runtime e Sync. Nenhum snapshot, `updatedb`, `config:import` ou `drush cr` foi executado por esta frente nesta checagem.
- O checkout servido contém alterações rastreadas e arquivos não rastreados de trabalho paralelo em arquivos de configuração, `aculta_user` e `aculta_ugc`; foram preservados sem limpeza ou substituição. Não foi verificado HTTP 200 depois da troca de HEAD. Esta microfase não escreveu no banco.

## PR #299 — conclusão dos caminhos ADR-038 e host Wiki canônico — 2026-10-30

- **Correção do responsável:** `wiki420` é o host canônico da Wiki; a indicação `wiki.aculta.*` no pedido inicial foi um erro. Nenhum Domain, DNS, TLS, vhost ou configuração de produção foi alterado para renomear a Wiki. `DomainPurposeManager` resolve os hosts a partir da configuração por ambiente.
- **Estado de código e documentação verificado:** os cinco paths canônicos são Marley `account:/marley`, Ganjalab `courses:/ganjalab`, WE SATIVA `wiki:/wesativa`, Green Stash `shop:/greenstash` e Culturas `eventos:/culturas`. `/studio` e paths antigos não têm aliases/redirects. A entrada da Conta funciona em `/` e `/conta`; Conta/Perfil ficam separados; `/salvos`, Mercado Pago e `/conta-legada` preservam seus caminhos/owners. Arquivos de rotas, consumidor, docs, guardrails e configuração exportada foram revisados.
- **Fronteira HTTP, verificada em 2026-10-30:** `DomainPurposeManager::environmentPathUrl()` resolveu `conta.aculta.toca.net.br`, `cursos.aculta.toca.net.br`, `wiki420.aculta.toca.net.br`, `loja.aculta.toca.net.br` e `eventos.aculta.toca.net.br`. A matriz focal fez 275 requisições GET/POST/AJAX: 275 respostas esperadas, com 404 para purpose incorreto e todos os paths antigos testados; cada caminho canônico no purpose correto chegou à autorização anônima (403). O gate `validate-domain-front-paths.php --live` também passou (26 checks).
- **Firefox ESR visível:** `ocs-baseline.py` capturou 20 páginas em 1440/390 px (homepage OpenCulturas e nove destinos ACULTA, incluindo a raiz de Conta, as cinco Suites, Perfil e planos). Nenhum documento vazio nem overflow foi medido. `/greenstash` exibiu acesso negado à conta de teste sem grant, portanto a captura não valida operação da Loja. `/culturas` carregou autenticada após a correção `95314a5a7`. Evidências PNG/JSON: `/home/piradopirata/suite-out/aculta-studio-adr038-20261030/`.
- **Wiki verificada:** HTTPS em `wiki420.aculta.toca.net.br/wesativa` foi alcançado (403 anônimo por acesso protegido); Firefox autenticado capturou a página. `wiki420.aculta.org` não resolveu por DNS a partir do ambiente de desenvolvimento; produção não foi homologada e isso não altera a decisão de manter `wiki420` como slug canônico.
- **Gates executados:** PHPCS Drupal/DrupalPractice e PHP lint dos PHP alterados PASS; Studio Unit 59/535; `PortalUiHooksTest` 17/110; aliases 37 arquivos; Conta raiz 1.848 checks; domínio live 26 checks; rotas admin MAIN 37 arquivos/145 rotas; fronteira módulo/tema 3.453; Portal Drupal 11+ 1.188; status index 48 componentes; SDC 55 componentes/756 checks; foundation 351; shell 13; design foundations 2.418; browser validators 21; `git diff --check` PASS. PHPUnit avisou que `sites/simpletest/browser_output` não é gravável, sem falhar os testes.
- **Kernel:** `SIMPLETEST_DB` estava unset; os testes Kernel de Events não foram executados e não houve criação de banco. Toda validação de escrita autorizada/negada da matriz HTTP foi anônima; grants de escrita da Loja e demais operações autenticadas ainda precisam homologação.
- **Revalidação Kernel em 2026-10-30:** `EventDomainBoundaryKernelTest` e `EventLocationModalAndAdminKernelTest` passaram no banco existente `aculta_simpletest`: 15 testes, 373 assertions, 29 depreciações upstream. A execução exigiu ACL temporária de leitura/travessia para o usuário OS `mysql` em `sites/default` e travessia de `/home/piradopirata`; o `trap` removeu ambas ao terminar. Nenhuma escrita foi feita em `aculta_runtime`.
- **Política de acesso — testes focais adicionais:** 30 testes/391 assertions passaram para `StudioIdentityRoutingTest`, Conta/Perfil e as políticas, navegação, leitura e criação da Green Stash. Isso verifica os requisitos declarados pelos serviços e rotas; não substitui a homologação de escrita no navegador com grants ativos para cada perfil de operador.
- **Runtime depois da publicação:** host `toca`, checkout servido `/home/piradopirata/projetos/aculta`, HEAD destacado `0bdbc4994`, URI `http://default`, Drupal 11.4.10, `aculta_runtime`, 622 tabelas. `updatedb:status`: `[success] No database updates required.` `config:status` retornou 455 linhas com diferenças globais entre Runtime e Sync; não houve snapshot/import porque nenhuma escrita de configuração foi autorizada. `drush cr`: `[success] Cache rebuild complete.` HTTP: MAIN `/` 200, Loja `/` 200, Conta `/` 302 para `/entrar`, Wiki `/wesativa` 403 anônimo.
- **Publicação:** na branch existente `refactor/opencultures-codigo-visual`, sem PR nova e sem force-push. Commits desta sequência: `fd1048bc6` (implementação adicional de Eventos e atualização de scripts/config exportada), `412f22af5` (índice e documentação ADR-038), `4f26e7c28` (limpeza PHPCS em Events) e `0bdbc4994` (tokens de apresentação de Culturas no tema). O HEAD da PR #299 e do checkout servido é `0bdbc4994`.
- **Pendente:** homologação autenticada de grants e escrita para as cinco Suites (a conta de teste não possui grant de Green Stash), cobertura de zoom/teclado/acessibilidade além do baseline 1440/390 e validação DNS/HTTP de produção `wiki420.aculta.org`. Os Kernels listados acima passaram em `aculta_simpletest`. Nenhum import global foi executado.
- **Fechamento após validação adicional:** o sitemap removeu o falso bloqueio de “host Wiki novo” e registra `wiki420` como canônico; README/roadmap dos owners Studio, Events, LMS e Commerce e o roadmap do Portal agora distinguem os paths ADR-038 das notas históricas e registram os gates executados. Os status gerados foram alinhados aos resultados Kernel. As alterações são apenas documentais e não incluem código ou documentação paralela de Events.

## OCS-F0 — reconciliar referências pré-ADR-038 — 2026-10-30

- **Escopo:** a matriz e o roadmap vigentes já apontavam para ADR-038, mas o plano normativo e o inventário visual upstream ainda apresentavam `/studio*` como caminho corrente. Ajustei-os para identificar explicitamente os paths antigos como históricos e remeter ao mapa de rotas/matriz atuais. Sem mudança de código, rotas ou banco.
- **Validação:** `git diff --check` PASS; os três links relativos revisados existem. Nenhum teste PHP/CSS/JS, Runtime ou Firefox executado, pois a alteração é exclusivamente documental.
- **Estado Git:** worktree da branch `refactor/opencultures-codigo-visual`; HEAD remoto e local antes do commit `220664160`. O primeiro `git pull --rebase` foi recusado porque as edições documentais já estavam no worktree; após fetch, remoto ainda correspondia ao HEAD local, sem rebase ou conflito. Commit de conteúdo `5a38f05a5`; registro desta publicação está neste commit documental seguinte. Push fast-forward pendente neste instante.
- **Resultado:** atualização de consistência OCS-F0; F0 e gates de fidelity permanecem parciais. O inventário upstream preserva evidência histórica e não representa homologação.

## Checkout de desenvolvimento após publicação — 2026-10-30

- **Estado antes da troca:** host `toca`; checkout servido `/home/piradopirata/projetos/aculta`, HEAD destacado `1a4de33b6`, worktree sem alterações rastreadas ou arquivos não rastreados; URI `http://default`; Drupal 11.4.10; DB `aculta_runtime`, 622 tabelas. A referência local de segurança `backup/dev-before-298351f50-20261030` preserva esse HEAD.
- **HEAD publicado aplicado:** `298351f50`, mesmo HEAD remoto da PR #299. `drush status` confirmou o mesmo host, checkout, URI e banco.
- **Gatilho somente leitura:** `drush updatedb:status` respondeu `[success] No database updates required.` `drush config:status --format=json` emitiu `[warning] Undefined array key "rename" ConfigCommands.php:274` e encontrou 225 diferenças: 42 somente no Sync, 69 somente no DB e 114 diferentes. O banco permaneceu em 622 tabelas.
- **Sem escrita no Runtime:** não foi executado snapshot, `updatedb`, `config:import` nem `drush cr`; nenhum comando de mutação do banco foi iniciado. O diff global não foi tratado como aprovação de import. O rebuild de cache ficou adiado com o gate de configuração pendente.
- **Smoke HTTP antes da troca:** no HEAD `1a4de33b6`, MAIN, Revista, Loja, Eventos, Wiki e Cursos responderam 200; Conta respondeu 302 para login, sem erro 500. Não é validação do HEAD `298351f50`, pois o cache não foi reconstruído.
- **Publicação deste registro:** esta própria alteração será publicada na mesma branch e, conforme o procedimento do servidor de desenvolvimento, o checkout será alinhado ao HEAD que contém este registro. O gatilho de configuração continua somente leitura enquanto o diff amplo não for reconciliado.

## THEME-MF0 — rastreio de bibliotecas e referências de consumer — 2026-10-30

- **HEAD antes da microfase:** `fe5bc07b7`, igual ao remoto. O worktree da Frente B estava limpo antes das alterações; nenhum arquivo fora de tema/scripts/documentação foi tocado.
- **Artefatos:** criado `scripts/audit-aculta420-library-consumers.php` e gerado `web/themes/custom/aculta420/docs/theme-library-consumer-inventory.tsv`; `mobile-first-audit.md` e `visual-cross-product-roadmap.md` agora registram cobertura, exemplos e limites do scanner.
- **Evidência estática:** 42 bibliotecas customizadas, 2.474 arquivos de código varridos fora de testes/docs/vendor/node_modules e 54 referências literais. O mapa inclui default library, anexos Twig, render arrays e declarations SDC detectáveis; não prova execução de caminhos condicionais nem resolve anexos dinâmicos.
- **Gates executados:** PHP lint PASS; PHPCS Drupal/DrupalPractice PASS; regeneração da saída padrão comparada byte a byte ao TSV PASS; `git diff --check` PASS. CSS/JS lint, SDC Runtime, Unit/Kernel/Functional, Firefox e HTTP não se aplicam ao artefato estático desta microfase; a configuração Runtime continua com 225 diferenças e seu gate de integração/cache permanece adiado.
- **Publicação/servidor:** o commit com este registro será publicado por fast-forward na PR #299; em seguida o checkout servido será alinhado ao HEAD que o contém. Após a troca, repetir apenas status Drupal de leitura; nenhuma escrita no Runtime será feita enquanto o diff global não estiver reconciliado.

## Revalidação do shell multidomínio nos dez purposes — 2026-10-30

- **HEAD da PR antes deste registro:** `9ad2b86b9`, igual ao remoto. O checkout servido estava destacado nesse commit. `docs/architecture/ROUTES-SITEMAP.md` e arquivos de `aculta_events`/`aculta_user` listados no `git status` do checkout servido tinham alterações locais não commitadas de outra frente; esta frente não os editou nem os incluiu.
- **Runtime identificado:** host `toca`; checkout `/home/piradopirata/projetos/aculta`; Drupal 11.4.10; URI Drupal padrão `http://default`; banco `aculta_runtime`; contagem registrada: 622 tabelas. Os hosts HTTPS canônicos resolvidos por `curl --resolve` foram MAIN `aculta.toca.net.br`, Conta, Apoio, Revista, Wiki420, Loja, Cursos, Growroom420, Links e Eventos.
- **Gatilho read-only atual:** `updatedb:status` listou `aculta_events 10013` e `aculta_user 10015`, cuja implementação existe nas alterações não commitadas do checkout servido, não no HEAD `9ad2b86b9` da PR. `config:status --format=json` terminou com código 0, aviso `[warning] Undefined array key "rename" ConfigCommands.php:274` e 225 entradas de diferença. Por não ser possível atribuir esses updates ao código publicado e pelo diff global inesperado, não foi executado `updatedb` nem `config:import`; nenhum snapshot foi necessário, pois não houve escrita de banco/configuração. O `drush cr` após a troca de HEAD anterior retornou `[success] Cache rebuild complete.`
- **Smoke HTTP read-only:** MAIN e Loja responderam HTTP 200. A matriz adicional Firefox ESR visível `purpose-matrix.py`, usando o checkout servido e sessão anônima, completou 90 capturas em 320/360/390/599/600/768/1024/1280/1440px: 90/90 com exatamente uma Institution Bar e um Domain Header; zero overflow horizontal, zero erro 500 e zero deferred. Evidências fora do Git: `/home/piradopirata/suite-out/domain-header-purpose-9ad2b86b9/`; `purpose-matrix.json` SHA-256 `397b9dbc8407431db53f112aa9d8b715588078281597e55b23c6fc9bf604257a`.
- **Contrato Portal:** `DomainPresentationBuilderKernelTest.php` passou com 2 testes/74 assertions e uma depreciação upstream de EntityType. A presença dos dois headers nas dez raízes fica resolvida; páginas internas, autenticação, zoom, teclado, WCAG e THEME-MF3 permanecem pendentes.
- **Estado de publicação:** este registro acompanha a atualização documental da auditoria mobile, matriz visual e roadmap Studio. A próxima troca do checkout servido permanece condicionada à preservação das alterações paralelas mencionadas acima; não se deve restaurar stash alheio nem publicar seus arquivos.

## Revalidação mobile e sincronização focal ARQ-05 no Runtime — 2026-10-30

- **HEAD corrente:** o remoto da PR #299 avançou por fast-forward para `4eb116482` com implementação de ARQ-05 em Events/User/Index. A Frente B atualizou sua worktree por `git pull --ff-only`; o checkout servido também já estava destacado em `4eb116482`, limpo, e a referência `backup/dev-before-4eb116482-20261030` preserva o HEAD anterior `02541f50c`. Nenhum arquivo funcional de Events/User/Index foi incluído pelo agente visual.
- **Preflight Runtime:** host `toca`; checkout `/home/piradopirata/projetos/aculta`; Drupal 11.4.10; URI `http://default`; banco `aculta_runtime`; 622 tabelas antes das migrations. `updatedb:status` apresentou exatamente `aculta_events 10013` (instala entidade `aculta_event_organization` e campos de coorganização) e `aculta_user 10015` (instala `can_manage_members`/`can_publish`). A revisão dos hooks confirmou que instalam schema/campos, sem uninstall, remoção ou migração destrutiva de conteúdo.
- **Snapshot antes da escrita:** `/home/piradopirata/aculta-backups/aculta-runtime-before-4eb116482-updb-20261030.sql`; SHA-256 `ec1283c758c1ca1682baa58379defa36253ba2dd24fc0c2346381f7bea4eeee7`; 51.926.367 bytes; modo 0600; dono `piradopirata:piradopirata`.
- **Atualização focal:** `drush updatedb -y` completou `aculta_events_update_10013` e `aculta_user_update_10015`; `drush cr` concluiu. `updatedb:status` posterior respondeu `[success] No database updates required.`; contagem final de tabelas: 624.
- **Configuração:** antes e depois, `config:status --format=json` terminou com código 0, aviso `Undefined array key "rename" ConfigCommands.php:274` e 225 diferenças. Como o diff global não corresponde ao commit focal nem foi revisado como alvo exato, não houve `config:import`. Nenhum módulo foi desinstalado.
- **HTTP:** MAIN, Loja e Eventos responderam HTTP 200 após o rebuild; a home Eventos havia retornado 500 antes de executar os hooks pendentes e passou a responder 200 depois das migrations.
- **Gates visuais:** Firefox ESR visível, `institution-bar-scroll.py`: 142/142 checks; dez purposes anônimos em 320/390px e cenário administrativo autenticado em 320/390/1023/1024/1280px. Sem salto (`maxTopChange=0`); no mobile a Institution Bar acompanha a Navigation top-bar em fluxo e assume o topo após scroll; em 1024px/desktop respeita o offset fixo de 64px. Evidências em `/home/piradopirata/suite-out/institution-bar-scroll-4eb116482/`.
- **Matriz de shell:** `purpose-matrix.py` pós-migration no Firefox ESR: 90/90 capturas (10 purposes × 9 viewports), Institution Bar e Domain Header exatamente uma vez em todas, zero overflow, zero HTTP 500 e zero deferred. Evidência: `/home/piradopirata/suite-out/domain-header-purpose-4eb116482-post-updb/`.
- **Kernel focal:** `EventOrganizationLifecycleKernelTest` passou em `aculta_simpletest`: 7 testes/196 assertions; 24 depreciações Core/contrib, sem falhas. Não foi usado o Runtime para PHPUnit.
- **Publicação documental:** esta atualização, a matriz de shell e a auditoria mobile registram evidência do HEAD publicado; somente documentação própria da Frente B será commitada. THEME-MF3 e OCS-F11 permanecem pendentes.


## Revista — capa para inspeção humana no Runtime — 2026-10-30

- No checkout servido `/home/piradopirata/projetos/aculta`, `hostname=toca`;
  Drush confirmou Drupal 11.4.10, URI `http://default` e banco
  `aculta_runtime`. O HEAD testado foi `0c049fe76` (`feat(theme): add
  responsive magazine cover layout`). `drush cr` e smoke HTTP nas homes MAIN/SHOP
  passaram (200). `updatedb:status` não tinha updates; `config:status` indicou
  224 diferenças globais e não houve import global.
- O responsável autorizou criar publicações de teste e mantê-las no Runtime.
  Quatro nós `revista` publicados (NIDs 599–602), quatro mídias (271–274) e
  quatro arquivos (760–763) usam conteúdo fictício marcado
  `[TESTE VISUAL 20261030]`, sem dados pessoais ou recursos do upstream. Capas
  originais estão em `public://revista-test-20261030/`. Snapshot íntegro anterior
  à criação: `/home/piradopirata/aculta-backups/aculta-runtime-revista-visual-20261030/aculta_runtime-before-revista-visual.sql`;
  SHA-256 `89d3f4441e0ab5d5bfdec3100b0ea01b98aa31de4cd2b5b473fdd5b85c130aba`.
  O conteúdo fica intencionalmente no Runtime para inspeção humana.
- A conta administrativa sintética de teste `aculta_visual_admin` (UID 257,
  papel administrator; email reservado `example.test`) foi criada por ordem do
  responsável. Credencial gerada aleatoriamente fica apenas no arquivo local
  ignorado `secrets/aculta_visual_admin.env`, com modo 0600; esse arquivo não
  é versionado nem anexado a commits. Não reutilizar a conta como associação de
  loja ou bypass de grants. Snapshot pré-criação:
  `/home/piradopirata/aculta-backups/aculta-runtime-test-admin-20261030/aculta_runtime-before-test-admin.sql`;
  SHA-256 `d793cdea5fd874f1610eed0cc60c36c0940fd581453323532b91d954b62df814`.
- Foi aplicada somente a configuração focal do display de teaser da Revista,
  já representada no Sync, para mostrar imagem, subtítulo e resumo. Uma tentativa
  de import parcial foi rejeitada por dependências globais de módulos ausentes;
  nada foi aplicado por essa tentativa. A configuração focal foi então alinhada
  sem desativar extensões nem executar `pm:uninstall`.
- Firefox ESR visível: `purpose-matrix.py` capturou Magazine `/` e `/node/599`
  em 320/360/390/599/600/768/1024/1280/1440 (18 capturas, zero overflow,
  headers únicos e H1 da listagem presente). Revisão manual em 390/600/1440.
  Evidência em `/home/piradopirata/suite-out/aculta-revista-capa-visual-20261030/runtime-after-layout/`.
  CSS lint, parser Twig e `git diff --check` passaram. Não foram executados
  zoom 200/400%, keyboard completo, WCAG 2.2 AA, modo escuro, reduced motion
  nem a matriz completa de propósitos; THEME-MF3 e OCS-F11 continuam pendentes.
- Implementação visual própria publicada no commit `0c049fe76`; a documentação
  desta rodada será registrada em commit separado. Nenhum arquivo de Eventos de
  outra frente foi incluído.


### Revista — reteste de fidelidade responsiva (2026-10-30)

- A home Magazine foi repetida no Firefox ESR visível em claro e em escuro com
  reduced motion nos nove viewports do gate: 9/9 em cada conjunto, sem overflow;
  Institution Bar e Domain Header presentes uma vez por captura. O probe mede
  preferências/tokens, não atesta por si só contraste ou comportamento de todas
  as animações.
- `keyboard-focus.py` percorreu Tab e Shift+Tab em 320/390/600/1024/1440px: 120
  observações, 115 alvos focáveis, 115 com `:focus-visible` e outline; cinco
  retornos esperados ao body.
- `revista-browser-zoom.py` usou Firefox ESR visível e o atalho Ctrl+plus via
  `xdotool`. Em janela de 1280px, a página passou a 640 CSS px (200%) e 320 CSS
  px (400%); ambas sem overflow, com quatro capas, H1 e shell institucional
  únicos. Capturas/JSON estão em
  `/home/piradopirata/suite-out/aculta-revista-zoom-final-20261030/`.
- O script novo foi compilado com `python3 -m py_compile`; os artefatos ficam
  fora do Git. Esses testes são apenas da home da Revista: no-JS, leitor de tela,
  WCAG 2.2 AA, outros estados e outros purposes continuam pendentes; THEME-MF3
  não está aprovado.

## OCS-F2 — alvos de navegação e validação real no Studio — 2026-10-30

- **Checkout/Runtime verificados:** worktree `/home/piradopirata/worktrees/opencultures-f0-adm8-e8`, branch `refactor/opencultures-codigo-visual`; servidor `toca`, docroot `/home/piradopirata/projetos/aculta/web`, Drupal 11.4.10, URI `https://conta.aculta.toca.net.br`, banco `aculta_runtime`, 624 tabelas. HEAD servido `2493d7535`; HEAD do worktree `5dcdb7b5c`, sem commits remotos novos no fetch (32 commits locais à frente). Alterações locais de Events no docroot servido foram preservadas e não tocadas.
- **Configuração pontual autorizada:** `aculta_magazine.settings` estava ausente no Runtime, embora o módulo estivesse ativo e o arquivo `config/sync/aculta_magazine.settings.yml` definisse papéis de autoria/revisão `content_editor` e comentários `administrator`. A importação parcial com apenas esse YAML foi abortada pela validação de dependências globais ausentes (`aculta_pdf_viewer` e `aculta_document_viewer_group`); a importação não gravou configuração. Seguindo a autorização de alinhar essa configuração focal, o mesmo YAML foi aplicado por `drush config:set --input-format=yaml`; `config:get` confirmou os valores do Sync. Nenhum módulo foi desinstalado e nenhum conteúdo foi alterado. O diff global de configuração continua inesperado; nenhuma importação global foi iniciada.
- **Snapshots:** antes da configuração focal, `/home/piradopirata/aculta-suite-out/runtime-snapshot-magazine-settings-20261030.sql`, SHA-256 `ee0489daa5da15ea4b3d49f9414409a38adb23ee4ceb9120962edafa8f1b167c`, 59.421.951 bytes. Antes do reteste visual, com as 624 tabelas e a configuração focal já conferidas, `/home/piradopirata/aculta-suite-out/runtime-before-studio-visual-20261030.sql`, SHA-256 `d64b25647626bcb2663372c35b3391e3ccb207ebac3c95349decb8ee687575f0`, 59.485.387 bytes.
- **Reteste Firefox ESR visível:** a suíte `tests/browser/aculta-studio/run-visual.sh` injetou somente a CSS candidata do worktree sobre rotas/markup servidos. Resultado final: 79 checks PASS, zero FAIL, um SKIP justificado porque a conta de teste não tem publicação própria publicada para exercitar edição. Foram exercitados `/marley`, os cinco estados de Publicações, Nova publicação/Enter, Galerias, fila do Fórum e `/conta/endereco`. Alvos contextuais da Conta mediram pelo menos 44×44px em 320/390/768/1280px; Marley não teve overflow em 320/360/390/599/600/767/768/1024/1280/1440px. Contraste textual mínimo medido de 6,59:1 no claro e 11,51:1 no escuro; geometria igual. A preferência de cor foi restaurada pelo `trap` do wrapper. Artefatos revisados em `/home/piradopirata/aculta-suite-out/studio-visual-candidate-20261030e/`.
- **Gates estáticos:** Stylelint CSS, ESLint JS, `node --check`, SDC schemas (55 componentes/759 checks), Design Foundations (2.429 checks), module-theme boundary (3.488 checks) e `git diff --check` PASS. `npm run lint:css` foi tentado na raiz e não se aplica porque o `package.json` fica em `web/themes/custom/aculta420`; o comando correto com `--prefix` passou. Kernel/Functional, zoom 200/400, no-JS, usuários anônimos, grants de publicação, outros purposes e auditoria WCAG completa não foram executados nesta microfase.
- **Estado:** CSS de Conta candidata passou em markup real, mas o override não comprova implantação física no Runtime. Não é aprovação de paridade completa ou dos gates THEME-MF3/OCS-F11; A1–A4 continuam bloqueadas. O commit local desta microfase não será publicado enquanto os gates globais exigidos pelo procedimento continuarem vermelhos.

## OCS-F5 — listagem editorial real, conta proprietária e mobile — 2026-10-30

- **Preflight:** Runtime em `toca`, checkout servido `/home/piradopirata/projetos/aculta`, HEAD `2493d7535`, Drupal 11.4.10, URI Conta HTTPS, banco `aculta_runtime`, 624 tabelas. A conta sintética `aculta_visual_admin` (UID 257), já autorizada e documentada, tem papel `content_editor` e é proprietária das quatro publicações de teste NIDs 599–602. A sessão usou ULI; não foi consultado nem exposto segredo.
- **Snapshots antes das sessões:** `/home/piradopirata/aculta-suite-out/runtime-before-editorial-visual-20261030.sql`, SHA-256 `5dc5f832af4da7c486af5bab8a7584928c294f18974ce69330dd5e5e3b3fe30a`, 60.327.008 bytes; reteste após corrigir o seletor de rota: `/home/piradopirata/aculta-suite-out/runtime-before-editorial-visual-retest-20261030.sql`, SHA-256 `957a517a39f83b048c86b8723a81e1fd9e30e70f46d913282bc7beb5be2b283a`, 60.409.049 bytes; viewport mobile com rota inicial na lista publicada: `/home/piradopirata/aculta-suite-out/runtime-before-editorial-mobile-visual-20261030.sql`, SHA-256 `8019b06f56362f87e19eaf784524ae12abf277c7acb60306a600030309aff85d`, 60.481.790 bytes.
- **Evidência Firefox ESR visível:** com CSS candidata injetada somente no navegador, a conta proprietária passou 80/80 checks, sem FAIL/SKIP. `/marley/revista/publicacoes/publicados` exibiu quatro linhas e quatro links com o caminho canônico `/marley/revista/{id}/editar` no purpose Conta; `/marley/revista/criar/nova-publicacao` abriu via Enter sem enviar conteúdo. A repetição com a listagem como rota inicial passou também 80/80; screenshot móvel 390px revisada e a tabela reteve o scroll no container sem overflow horizontal do documento. Evidências em `/home/piradopirata/aculta-suite-out/studio-editorial-rows-retest-20261030/` e `/home/piradopirata/aculta-suite-out/studio-editorial-mobile-20261030/`.
- **Limites e estado:** só estados Publicados continham itens; os demais estados editoriais são exercitados apenas pela navegação, não por fixtures de conteúdo para cada workflow. Nenhum formulário foi aberto/enviado, nenhum conteúdo/configuração/permissão foi modificado; a preferência de cor da conta foi restaurada. CSS candidata não comprova implantação no Runtime. F5 permanece parcial até cobrir todos os estados/fluxos; THEME-MF3 e OCS-F11 continuam pendentes.

### OCS-F5 — apresentação Fidelity no GET do Form API editorial — 2026-10-30

- **Leitura do markup:** a rota canônica `/marley/revista/{id}/editar` entrega o formulário Drupal com as classes reais `node-revista-form node-form`; o `body` tem apenas `path-marley node--type-revista`, sem classe de rota mais granular. O primeiro teste do seletor hipotético `path-marley-revista-*` foi corretamente refutado pelo navegador (75/76); a regra foi estreitada para `form.node-revista-form`, sem atingir as demais páginas do purpose Marley.
- **Implementação visual candidata:** tokens OpenCulturas de fidelidade aplicados somente ao SDC/wrapper `.studio`, ao editor de perfil existente e ao Form API editorial identificado. `studio.css` estende tipografia, superfície, fieldsets, controles e botões no formulário nativo; nenhum controller, render array, operação, dado, permissão ou shell foi alterado. O contrato de escopo do analyzer de Design Foundations e sua fixture de regressão foram ampliados para aceitar esse consumidor visual específico.
- **Runtime e snapshot:** host `toca`; checkout servido `/home/piradopirata/projetos/aculta`; Drupal 11.4.10; URI `https://conta.aculta.toca.net.br`; banco `aculta_runtime`, 624 tabelas; HEAD servido `2493d7535`. Antes da sessão read-only/ULI, snapshot `/home/piradopirata/aculta-suite-out/runtime-before-editorial-form-browser-20261030.sql`, SHA-256 `862b5952a3097d4814e759945b7ff5165ca6919afbe8d6902e13c100c204e7b7`, 59 MiB. A wrapper salvou e restaurou a preferência de cor; não houve submit ou alteração de conteúdo/configuração.
- **Firefox ESR visível:** a suíte candidata passou 76/76 verificações, sem SKIP/FAIL. A rota real mostrou título, conteúdo, formato de texto e campos secundários; CSS candidata serviu Montserrat, foreground Fidelity e o formulário não excedeu o viewport em 320/390/768/1280px. Foram também cobertos navegação, lista real de quatro itens, alvo de 44px, teclado Enter/foco, modo claro/escuro e contraste textual 6,59:1/11,51:1 nos elementos Studio avaliados. Capturas desktop/móvel inspecionadas e JSON em `/home/piradopirata/aculta-suite-out/studio-editorial-form-fidelity-retest-20261030/`.
- **Gates estáticos:** Stylelint e ESLint do tema, PHP lint do analyzer/fixture, `validate-aculta420-design-foundations.php` (2.429 checks), fixtures (159 outcomes) e `git diff --check` passaram. A primeira execução do browser não passou por falha de seletor e foi repetida após limitar o escopo; não foi enviada qualquer alteração ao Runtime.
- **Limites:** CSS injetada no Firefox, não implantada no Runtime; GET de edição não comprova save, preview, AJAX/no-JS, ACL completa, usuário anônimo, zoom 200/400%, WCAG 2.2 AA ou comparação pareada com formulário upstream. OCS-F5 permanece parcial; THEME-MF3/OCS-F11 não foram homologados e A1–A4 continuam bloqueadas.

### OCS-F5 — título editorial com tokens Fidelity, sem tocar o shell — 2026-10-30

- **Ajuste:** a primeira captura comprovou que a tipografia Fidelity alcançava o formulário, mas o heading “Editar publicação” ainda herdava o Oswald global. Os tokens passaram a ser providos ao `.aculta-main` somente via `:has(form.node-revista-form)`; um fallback direto no form mantém os campos legíveis se o navegador não aplicar `:has`. O CSS tipográfico acompanha o mesmo `main` condicional. Institution Bar e Domain Header são irmãos desse main e não recebem os tokens.
- **Firefox ESR visível:** a suíte em markup/runtime real e CSS candidata passou 78/78 checks, sem SKIP/FAIL. `main`, H1 e formulário computaram Montserrat; H1 ficou em 48px no desktop e refluiu em duas linhas no viewport 320px. Institution Bar e Domain Header apareceram uma vez cada e ambos computaram a propriedade fidelity vazia. Zero overflow em 320/390/768/1280px; capturas revisadas em `/home/piradopirata/aculta-suite-out/studio-editorial-heading-fidelity-20261030/`.
- **Runtime:** snapshot anterior à sessão `/home/piradopirata/aculta-suite-out/runtime-before-editorial-heading-browser-20261030.sql`, SHA-256 `ad3e0784c013fd39c826d140012e2b24216facdb97392e6ab765f7f386ddece3`. A preferência foi restaurada pelo wrapper; GET somente leitura, sem submissão.
- **Estado:** é CSS candidata, não implantada no Runtime; não valida save, preview, fluxo no-JS/AJAX, ACL completa, zoom, WCAG integral nem paridade comparativa com página upstream equivalente. F5 continua parcial e os gates THEME-MF3/OCS-F11 seguem abertos.

### OCS-F5 — GET do formulário proprietário de criação — 2026-10-30

- `/marley/revista/criar/nova-publicacao` é a entrada contextual da seção; o link real de ação leva a `/marley/revista/nova`. A suíte seguiu o `href` renderizado em vez de assumir que a rota da aba é o formulário.
- Firefox ESR visível, usuário proprietário sintético, CSS candidata e GET sem submit: 87/87 checks. O Form API de criação renderizou `node-revista-form`, title input e heading, todos com Montserrat; zero overflow em 320/390/768/1280px. A criação e a edição passaram pelo mesmo tratamento visual de tokens/componente.
- Capturas desktop/mobile e `results.json`: `/home/piradopirata/aculta-suite-out/studio-editorial-create-form-fidelity-retest-20261030/`. Snapshot anterior à sessão ULI: `/home/piradopirata/aculta-suite-out/runtime-before-editorial-create-route-browser-20261030.sql`, SHA-256 `5076f6fb97ea608a0d4707bc0bf1339d7881516eb1f011b83da7d1ccdaa57b9f`. Não houve alteração de publicação, configuração ou preferência permanente.
- Limites mantidos: stylesheet injetada apenas no navegador; nenhum POST/save/preview; gates de no-JS, ACL integral, zoom, WCAG e paridade upstream não foram executados. OCS-F5 permanece parcial e THEME-MF3/OCS-F11 não foram aprovados.

### OCS-F6 — GanjaLab, camada Fidelity candidata — 2026-10-30

- **Implementação visual:** `tokens.css`, `studio.css` e o analyzer de Design Foundations estendem os tokens e acabamento ao dashboard estudantil em `body.path-ganjalab .aculta-main`. Reutiliza o markup/SDCs já fornecidos pelo composer LMS; shell, navegação da Conta, Institution Bar e Domain Header não recebem os tokens. Nenhum código funcional LMS foi alterado.
- **Runtime/evidência:** servidor `toca`, Drupal 11.4.10, `aculta_runtime` com 624 tabelas e HEAD servido `2493d7535`; o snapshot imediatamente anterior ao candidato está em `/home/piradopirata/aculta-suite-out/runtime-before-ganjalab-fidelity-candidate-20261030.sql`, SHA-256 `40be54d793ff9a0a7edb6e6a8cdd86d5c405b0e0feffe5e15912be4bc889aef2`. Firefox ESR visível, conta sintética `aculta_visual_admin` já autorizada, nove larguras (320/360/390/599/600/768/1024/1280/1440), zero overflow; imagens em `/home/piradopirata/aculta-suite-out/ganjalab-fidelity-candidate-20261030/`. Foram inspecionadas capturas 320 e 1440 px. A confirmação antiga de Montserrat retornou `false` porque o coletor consultou antes de aguardar a fonte; o script agora solicita a fonte antes da coleta e inclui os estados `FontFace`, ainda sem nova execução.
- **Bloqueio de cobertura:** o caminho oficial do propósito courses `/ganjalab` renderiza o painel estudantil, mas Wiki `/studio/we-sativa` retorna documento vazio no Runtime `2493d7535`, que ainda registra `/wesativa`. Sem alteração de roteamento nesta frente. F6 parcial; OCS-F11 e THEME-MF3 permanecem não homologados. A1–A4 seguem bloqueadas.

### OCS-F6 — tipografia do estado vazio e carga de Montserrat confirmadas — 2026-10-30

- O Firefox ESR visível mostrou Montserrat carregada em quatro larguras e descobriu que o empty state herdava Inter a partir de 768px. `studio.css` passou a definir a família e line-height Fidelity no `.aculta-empty-state` dentro da rota GanjaLab, preservando o isolamento. O segundo reteste confirmou Montserrat em main, heading e ambos os estados vazios a 320/390/768/1440px, com scroll width igual ao viewport e Institution Bar/Domain Header presentes uma vez; os tokens Fidelity nos dois headers continuam vazios.
- Capturas e JSON: `/home/piradopirata/aculta-suite-out/ganjalab-font-retest2-20261030/`. Snapshot antes da sessão ULI: `/home/piradopirata/aculta-suite-out/runtime-before-ganjalab-font-retest2-20261030.sql`, SHA-256 `4afdf35a7735bd0916ce8091d73113a7f17e891245d7e64b7ebe37b1b35d15c1`, 62.379.368 bytes. Sessão somente GET; nenhum conteúdo, preferência persistente, configuração ou permissão foi alterado.
- O coletor ganhou `OCS_STUDIO_COLOR_MODE=light|dark` para alternar `data-bs-theme` só no documento de teste. Modo claro: dez invariantes passaram em 320/390/768/1440px, com zero falhas; modo escuro: onze invariantes passaram em 320/1440px, incluindo texto `rgb(239, 239, 239)`, superfície escura e zero overflow. JSONs sem falhas em `/home/piradopirata/aculta-suite-out/ganjalab-light-assertion-20261030/` e `/home/piradopirata/aculta-suite-out/ganjalab-dark-assertion-20261030/`. Snapshot antes do reteste claro: `/home/piradopirata/aculta-suite-out/runtime-before-ganjalab-light-assertion-20261030.sql`, SHA-256 `42a86ecff8672f29774ff571e85bcecce3d34e32c6c81483f0dac61eb44423bc`, 58.191.931 bytes; snapshot antes do dark: `/home/piradopirata/aculta-suite-out/runtime-before-ganjalab-assertion-retest-20261030.sql`, SHA-256 `1ed49c24aa0484e36db7ad1e53c163ba3d3a404247f1e9d6fca56dcd424a1157`, 62.427.227 bytes. Não é medição completa de contraste WCAG nem altera o tema persistido da conta. F6 continua parcial, sem homologação de zoom, WCAG, ACL intercontas, dados LMS preenchidos ou regressão global.

### OCS-F7 — consumer Wiki em WE SATIVA, composição candidate — 2026-10-30

- **Base verificada:** o tarball oficial `openculturas-3.1.5.tar.gz` foi baixado novamente para `/home/piradopirata/aculta-suite-out/` e seu SHA-256 conferido como `2cc0a34293e3cd7ccff633d07d61844afc1cec752d506a955c49a319fcbbb406`. Foram conferidos `_teasers.scss`, `templates/node/teasers.scss`, `user-dashboard-views.scss` e `navigation/tabs.scss`; nenhuma folha/markup/asset upstream foi copiado.
- **Implementação:** estilos Fidelity e tokens escopados ao wrapper existente `we-sativa__workspace`, emitido por `PortalUiHooks`; ajustes em headings, content sections, cartões e estados vazios reutilizam os SDCs já consumidos por `WikiContributorWorkspace`. O path oficial da branch é `/studio/we-sativa`, mas o Runtime no HEAD `2493d7535` registra `/wesativa`; o reteste foi feito no caminho legado apenas para validar o consumer servido, sem declarar o path oficial homologado.
- **Firefox ESR visível:** conta sintética autorizada, GET no host Wiki e CSS candidata injetada no browser; 14 checks passaram em 320 e 1440px. Verificou Montserrat e todos os estados/seções, os quatro filtros com um `aria-current`, links de 46px, wrapping, zero overflow, Institution Bar e Domain Header presentes e sem tokens Fidelity. Capturas revisadas em `/home/piradopirata/aculta-suite-out/wesativa-legacy-route-final-20261030/`; JSON sem falhas.
- **Runtime/snapshot:** `toca`, `/home/piradopirata/projetos/aculta`, HEAD servido `2493d7535`, `aculta_runtime`, 624 tabelas. Snapshot antes da ULI: `/home/piradopirata/aculta-suite-out/runtime-before-wesativa-visual-final-20261030.sql`, SHA-256 `324300f03de2e56e4d606990ded74212208bcc0175705ea8450e4b9b9d85a524`, 58.450.930 bytes. Nenhum formulário foi submetido ou conteúdo/configuração foi alterado.
- **Limites:** captura não usa o path canônico, não contém conteúdo Wiki autorizado para demonstrar cartões com dados, nem cobre autoria/revisão/moderação, ACL A/B, no-JS/AJAX, dark, zoom ou WCAG. F7 parcial; F11/MF3 não aprovados e OCS-A1–A4 bloqueadas.

### OCS-F7 — reteste escuro de WE SATIVA no Runtime legado — 2026-10-30

- Firefox ESR visível, stylesheet candidata injetada, `data-bs-theme=dark` apenas no documento; `/wesativa`, 320/1440px. Passaram 15/15 invariantes em ambas as larguras; texto `rgb(239, 239, 239)`, Montserrat, estados vazios e superfícies escuros, shell sem tokens Fidelity, quatro filtros acessíveis e zero overflow. Capturas revisadas em `/home/piradopirata/aculta-suite-out/wesativa-dark-legacy-20261030/`.
- Snapshot pré-ULI `/home/piradopirata/aculta-suite-out/runtime-before-wesativa-dark-20261030.sql`, SHA-256 `635b80adb3aaff58be98a539c52aeddaf9f133a14bf3336f4a7bd6e5b707ca33`, 58.502.572 bytes; Runtime `aculta_runtime`, 624 tabelas, HEAD servido `2493d7535`. Nenhuma preferência persistente ou dado foi alterado.
- F7 permanece parcial: somente o path legado foi servido; `/studio/we-sativa`, autoria/revisão/moderação, fixtures Wiki, grants A/B, zoom, contraste WCAG, no-JS/AJAX e MF3 ainda não homologados.

### THEME-MF2/MF3 — Institution Bar e admin toolbar mobile — 2026-10-31

- **Runtime conferido antes da sessão:** host `toca`; checkout servido `/home/piradopirata/projetos/aculta`; HEAD destacado `2493d7535`; URI `http://default`; banco `aculta_runtime`, conectado, 624 tabelas. O checkout servido contém alterações não commitadas de Eventos; não foram tocadas.
- **Snapshot pré-ULI:** `/home/piradopirata/aculta-suite-out/runtime-snapshots/runtime-before-institution-bar-authenticated-20261031.sql`, SHA-256 `cf23aa72d519615af97eeb001d32879811be9dcaf7d823d3416b9c0addf650c2`, 67.077.419 bytes, modo 0600.
- **Sessão sintética autorizada:** o teste visual usou `aculta_visual_admin` UID 257 via ULI; nenhuma senha foi lida ou exibida. Nenhum conteúdo, configuração, permissão ou preferência persistente foi alterado; ULI criou a sessão Drupal de teste.
- **Firefox ESR visível:** o harness reutilizável foi ampliado para percorrer o sticky em passos de 12px nos sentidos de descida e subida. A versão ampliada passou 40/40 checks administrativos em MAIN (incluindo 55 leituras em cada largura 320/390px) e 130/130 checks anônimos nos dez purposes configurados, 320/390px. A versão anterior passou 38/38 e 112/112, respectivamente. Não se reproduziu salto/posição intermediária. Evidências detalhadas em `web/themes/custom/aculta420/docs/mobile-first-audit.md` e nas pastas externas listadas ali.
- **Snapshot antes da segunda autenticação ULI:** `/home/piradopirata/aculta-suite-out/runtime-snapshots/runtime-before-institution-bar-transition-admin-20261031.sql`, SHA-256 `3747d45e71003b862c5c56da6ac9fc59b7572e2eafe9eaebdfef205a1a70b554`, 67.203.080 bytes, modo 0600. Snapshot realizado após reconfirmar `toca`, HEAD `2493d7535`, URI `http://default`, `aculta_runtime` e 624 tabelas.
- **Limites:** não houve teste em dispositivo móvel físico nem captura da folha candidata local; mantém-se pendente a validação do CSS/JS da branch no Runtime e o gate THEME-MF3. O checkout servido permanece no HEAD `2493d7535`.

### THEME-MF2 — repetição da Institution Bar — 2026-10-31

- **Preflight:** host `toca`; checkout/docroot `/home/piradopirata/projetos/aculta`; HEAD destacado `2493d7535`; Drupal 11.4.10; URI `http://default`; banco `aculta_runtime`, 624 tabelas. O checkout servido já tinha alterações não commitadas de Eventos, que não foram tocadas.
- **Firefox ESR visível, CSS/JS servidos sem override:** nas dez homes anônimas, 130/130 checks em 320/390px; na MAIN autenticada como `aculta_visual_admin` UID 257, 40/40 checks em 320/390/1023/1024/1280px, incluindo scroll contínuo e mudança de largura 320↔1024. Capturas revisadas. Resultados em `/home/piradopirata/aculta-suite-out/institution-bar-followup-20261031/` e `/home/piradopirata/aculta-suite-out/institution-bar-admin-followup-20261031/`.
- **Estado alterado:** a autenticação usou ULI, que criou uma sessão Drupal temporária. Nenhum conteúdo, configuração, permissão ou preferência foi alterado. Nesta repetição não foi feito snapshot imediatamente antes da sessão ULI; por isso, esta execução registra apenas o estado validado e a sessão temporária, sem alegação de snapshot prévio. O preflight consultou o banco e confirmou 624 tabelas.
- **Achado separado:** a raiz anônima de Conta exibiu `documentWidth=328` em viewport 320px; o harness de sticky não considera overflow documental como critério e passou os checks posicionais. A correção desse overflow continua pendente.
- **Limites:** esta repetição focal não aprova THEME-MF3, nem cobre rotas internas, zoom, teclado completo, cores, reduced motion ou WCAG integral.

### THEME-MF3 — corrigir formato dos artigos sintéticos da Revista — 2026-10-31

- **Preflight:** servidor `toca`; checkout servido `/home/piradopirata/projetos/aculta`, HEAD `2493d7535`; URI `https://revista.aculta.toca.net.br`; banco `aculta_runtime`; 624 tabelas. Drush somente leitura confirmou que o formato `revista_editorial` está ativo e que os nós 599–602 são publicações sintéticas da Revista publicadas, com formato `basic_html`. O formato `basic_html` não existe na configuração Runtime/Sync; a renderização pública resultava em `<div class="field--name-body"></div>` apesar do texto armazenado.
- **Snapshot antes da escrita:** `/home/piradopirata/aculta-backups/revista-body-format-20261031/aculta_runtime-before-format-fix.sql`, 106.454.164 bytes, modo 0600, SHA-256 `ceee9081250ce2b7bd28018ec2e7bad19c0322b835c725ea4de0fdcf99ec101e`.
- **Escrita de conteúdo de teste autorizada:** por API de entidade Drupal, somente os nós sintéticos 599–602 tiveram `body.format` alterado de `basic_html` ausente para `revista_editorial` ativo. Título, valores de texto, resumo, mídia, status publicado, ACL e configuração não foram alterados; Drupal gerou revisões correntes 778–781 e timestamps `changed` para essas quatro entidades. Nenhum módulo, grant ou configuração foi alterado; não houve `config:import` nem `updatedb`.
- **Validação posterior:** Firefox ESR visível no Runtime, 100/200/400% reais, homepage e artigo 602: **6/6 checks**, agora incluindo `bodyTextLength > 0`, altura renderizada do corpo, capa carregada, H1 e shell únicos e zero overflow. As capturas foram ajustadas para mostrar H1 e corpo em cada zoom e revisadas em `/home/piradopirata/aculta-suite-out/revista-article-zoom-followup-20261031d/`. Nenhum formulário ou comentário foi enviado.
- **Limite:** esse reparo padroniza apenas conteúdo sintético de homologação para usar um formato editorial válido. Não corrige um problema no Twig/CSS, não altera conteúdo editorial real e não aprova o gate THEME-MF3, a comparação pareada OpenCulturas ou WCAG 2.2 AA.

## 2026-10-31 — captura read-only da área Marley no Runtime

- Ambiente confirmado: `toca`; checkout servido `/home/piradopirata/projetos/aculta`, HEAD destacado `2493d7535`, URI Drush `http://default`, banco `aculta_runtime`, 624 tabelas.
- Antes de abrir sessão autenticada por ULI, `drush sql:dump` gerou snapshot do banco com 624/624 tabelas em `/home/piradopirata/aculta-suite-out/runtime-studiosnapshot-20261031/aculta_runtime.sql`, modo `600`, 64.774.437 bytes. SHA-256: `537a0b95c5023ce8bbdf6ce94bb76209cedff59ba2719b406e40a3e462d0b261`.
- Firefox ESR visível capturou `account:/marley` em 320/360/390/599/600/768/1024/1280/1440px com CSS Fidelity candidata injetada somente na sessão. Capturas e JSON: `/home/piradopirata/aculta-suite-out/studio-marley-canonical-followup-20261031/`.
- Resultado: 9/9 telas capturadas; Institution Bar e Domain Header únicos, sem overflow horizontal, cinco abas com uma ativa e alvos de pelo menos 44px. Nenhum formulário foi enviado nem conteúdo/configuração/permissão alterado. A sessão temporária de ULI foi criada para o teste; credenciais não foram registradas.
- O GET de `/studio` no purpose Conta retornou 404 vazio, como determina ADR-038; não é defeito. A captura comparativa do upstream foi apenas a homepage pública, não um dashboard autenticado. O resultado é candidato e não homologa OCS-F5/F11 ou THEME-MF3.

## 2026-10-31 — Green Stash, baseline legado e rota canônica candidata

- Sessões ULI da conta sintética `aculta_visual_admin` foram abertas após o snapshot integral descrito acima; não houve envio de formulários nem alteração de conteúdo/configuração/permissões.
- No Runtime `2493d7535`, `shop:/greenstash` ainda é a rota servida. Firefox ESR capturou 320/390/1440px: shell único; `documentScrollWidth=330` em viewport 320px (overflow de 10px); 390/1440px sem overflow. Essa página e seus nove links pertencem ao baseline antigo, não ao contrato visual novo. Evidência: `/home/piradopirata/aculta-suite-out/green-stash-runtime-legacy-baseline-20261031/`.
- A rota local canônica `/studio/green-stash` retorna 404 no Runtime porque HEAD remoto mantém `/greenstash`; ela não está servida. Não contar como falha visual da implementação local. A página oficial permanece DEFERRED para homologação de Runtime.
- Na branch, `StudioIdentityRoutingTest`, `GreenStashNavigationTest` e `GreenStashExperienceProviderTest`: **10 testes/252 assertions**. A fixture candidata `green-stash-layout.py` passou 186/186 (320–1440px, claro/escuro, telas de visão geral/produto/detalhe/criação e zoom real); valida somente apresentação, não operação Commerce nem grants por loja. Relatório: `/home/piradopirata/aculta-suite-out/green-stash-layout-followup-20261031/`.

## 2026-10-31 — Ganjalab em claro/escuro, CSS candidata

- Firefox ESR visível abriu a rota oficial `courses:/ganjalab` no Runtime com CSS candidata injetada apenas na sessão, em claro e escuro, 320/390/1440px. Relatórios: `/home/piradopirata/aculta-suite-out/studio-ganjalab-canonical-followup-20261031/` e `/home/piradopirata/aculta-suite-out/studio-ganjalab-dark-canonical-followup-20261031/`.
- Em todas as seis combinações, `ganjalabFidelityStatus=PASS`: Montserrat em main/heading/estados vazios, contexto presente, dois estados vazios, sem overflow, shell único, contraste AA dos links textuais da Institution Bar e tokens Fidelity ausentes do shell; modo escuro também verificou o token de texto.
- `LearnerDashboardComposerTest` e `GanjalabControllerTest`: 2 testes/4 assertions. Nenhum formulário, curso ou matrícula foi alterado. As sessões ULI usaram a conta sintética; o snapshot anterior à sessão permanece registrado na entrada acima.
- **Limite que impede F6:** o markup servido ainda mostra “Minha conta / Área pessoal”; o compositor da branch define “ACULTA Studio Ganjalab / Meus cursos / Acompanhe suas aulas e certificados”. Logo, os checks medem CSS candidata sobre markup antigo, não a tela final da branch. Não homologar OCS-F6/F11/MF3.

### Reteste ampliado Ganjalab — matriz Fidelity (2026-10-31)

Firefox ESR executou novamente `courses:/ganjalab` na grade completa de nove
larguras (320/360/390/599/600/768/1024/1280/1440px) em claro e escuro: **18/18
combinações capturadas**, todas com `ganjalabFidelityStatus=PASS` e sem falhas de
Fidelity. Evidência em `/home/piradopirata/aculta-suite-out/studio-ganjalab-full-light-followup-20261031/`
e `/home/piradopirata/aculta-suite-out/studio-ganjalab-full-dark-followup-20261031/`.
Continua CSS candidata sobre o markup Runtime “Minha conta / Área pessoal”; não
homologa o compositor “Meus cursos” ainda não servido. Sem formulário, matrícula
ou preferência persistente alterados.

## 2026-10-31 — OCS-F4: captura autenticada do Hub e auditoria do SDC

- **Ambiente confirmado antes das sessões:** host `toca`; checkout servido `/home/piradopirata/projetos/aculta`, HEAD destacado `2493d7535`; Drush root `/home/piradopirata/projetos/aculta/web`, URI `http://default`, banco `aculta_runtime`, 624 tabelas.
- **Snapshots integrais anteriores a cada ULI:**
  1. `/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-studio-hub-visual-gate-20261031.sql` — 65.897.048 bytes, SHA-256 `21378d202df8c38edd8d55c8620a47238469bda412ec89d8ffdeecefa1d0a684`.
  2. `/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-studio-hub-root-visible-20261031.sql` — 65.950.830 bytes, SHA-256 `faeb7e158103c0c8fb32c27ea1a827d3f295647514cc7523e19df9a1e488c4b7`.
  3. `/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-studio-hub-card-check-20261031.sql` — 66.006.214 bytes, SHA-256 `2597ebac5be92f8a6c591d2999de1f03b7727083f76aa2ca925850c63dd95083`.
  Todos foram criados por `mysqldump --single-transaction --quick --skip-lock-tables`, modo `0600`. O teste fez GET/ULI em Firefox ESR visível; não enviou formulários nem alterou conteúdo, configurações, permissões ou preferências. A única escrita esperada no Runtime foi a criação das sessões temporárias de autenticação.
- **Resultado visual:** a raiz Account respondeu com o root `.studio.aculta-account-entry` visível em 320/360/390/599/600/768/1024/1280/1440px; cinco experiências disponíveis apareceram, mas nenhum link foi renderizado no SDC `card` e todos mediram 19px. O novo gate registrou `studio_capture_status=FAIL` em nove capturas. CSS Fidelity foi injetada só no Firefox e não executa o hook PHP.
- **Resultado no source branch:** `PortalUiHooksTest` passou 26 testes/148 assertions, incluindo o adaptador Studio Hub para o SDC compartilhado. O Runtime segue no HEAD remoto anterior e não contém esse hook; os arquivos não commitados de `aculta_events` permaneceram intactos. Evidências: `/home/piradopirata/aculta-suite-out/studio-hub-cards-20261031/`. Não houve troca de HEAD, `updatedb`, `config:import` ou escrita editorial.
- **Status:** OCS-F4 parcial; o gate do Hub servido permanece vermelho até o source branch com o preprocess visual ser integrado e validado. THEME-MF3 e OCS-F11 continuam pendentes; OCS-A1–A4 não iniciadas.

## THEME-MF2 — endereço de Conta, reteste responsivo (2026-10-31)

- Runtime confirmado no host `toca`, checkout servido `/home/piradopirata/projetos/aculta`, HEAD destacado `2493d7535`, URI `http://default`/`https://conta.aculta.toca.net.br`, banco `aculta_runtime`, 624 tabelas antes e depois.
- Antes dos ULI usados pelo smoke, snapshot íntegro via `drush sql:dump`: `/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-account-address-visual-followup-20261031.sql`, 66.848.723 bytes, modo 0600, SHA-256 `764fac824376c8f3dfab49b0f5378093c10fa20b21910180ab397ef7400c21fa`.
- Firefox ESR visível abriu `/dados/endereco` por GET com JavaScript e sem JavaScript; passou 50/50 checks, incluindo nove larguras, quatro larguras no fallback, zoom 200/400%, um único formulário, campos contratuais, CEP, país BR oculto e ausência de overflow. Capturas mascaram inputs; nenhuma consulta CEP, submissão ou alteração de endereço foi feita. Apenas sessões ULI foram criadas.
- Evidência: `/home/piradopirata/aculta-suite-out/account-address-visual-followup-20261031/`. Não houve `updatedb`, `config:import`, `pm:uninstall`, `drush cr` nem alteração de configuração/conteúdo.

## OCS-F6 — dashboard Ganjalab e fluxo do aviso ULI (2026-10-31)

- Runtime confirmado: host `toca`, checkout servido `/home/piradopirata/projetos/aculta`, HEAD destacado `2493d7535`, URI `http://default`/`https://cursos.aculta.toca.net.br`, `aculta_runtime`, 624 tabelas antes e depois.
- Três sessões ULI foram usadas para rever o root visual e testar a correção candidata. Antes de cada sessão houve snapshot íntegro via `drush sql:dump`, modo `0600`:
  1. `/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-ganjalab-visual-followup-20261031.sql` — 66.917.705 bytes; SHA-256 `babbffdbb536913277ecd251f0bb21dac4ef909af63a9658cad8d71fbde40f4a`.
  2. `/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-ganjalab-visual-rerun-20261031.sql` — 66.980.746 bytes; SHA-256 `e022c857d3c1e725b2e52a26c3d32d45d5c91abb50e7e85ed7a9edfea9abb3d2`.
  3. `/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-ganjalab-status-flow-candidate-20261031.sql` — 67.035.266 bytes; SHA-256 `5ad61519aa510b2f19252289b0e3ce3ede189198bb7693d3c008162c89bed85d`.
- Firefox ESR capturou `/ganjalab` autenticado em nove larguras. O primeiro comando de teste usou `.studio`, ausente no Runtime antigo; reexecutado com `.aculta-context-header`, resultou `CAPTURED`, zero falhas e zero overflow. A página mostrou LMS, cursos/certificados vazios. O Runtime ainda não tem os marcadores da implementação candidata.
- No baseline o aviso ULI ficou `position: fixed` e cobriu navegação a 320px. Com `content.css` candidata injetada só na sessão, ficou estático no fluxo antes do heading visível (1 PASS); oito larguras posteriores ficaram `SKIP_NO_STATUS_NOTICE` por autoclosure. Não houve criação de curso/matrícula nem alteração de conteúdo/configuração. Apenas sessões ULI foram criadas; sem `updatedb`, `config:import`, `pm:uninstall` ou `drush cr`.
- Evidência: `/home/piradopirata/aculta-suite-out/ganjalab-status-flow-candidate-20261031/`. Candidato não está servido; OCS-F6/F11 e THEME-MF3 continuam pendentes.

## 2026-10-31 — Institution Bar: decisão de primeiro scroll móvel

- **Decisão do responsável:** em mobile, fixar Institution Bar em `top: 0` no primeiro scroll não nulo; ocultar visualmente a Navigation top-bar administrativa até voltar ao topo; preservar ações focadas por teclado acima do shell; manter sticky natural quando JavaScript estiver indisponível.
- **Runtime antes da sessão autenticada:** servidor `toca`; checkout servido `/home/piradopirata/projetos/aculta`, HEAD destacado `00e6149b0`; Drupal 11.4.10; URI `http://default`; `aculta_runtime`, 624 tabelas. Arquivos paralelos de Events no checkout servido foram preservados e não alterados.
- **Snapshot pré-ULI da validação autenticada final:** `/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-institution-bar-focus-layer-validation-20261031.sql`, 68.186.787 bytes, modo 0600, SHA-256 `262ad2d2165b016e95f9311e4daebb5213ee164f6b704b27b2a77906d697fa2b`. Snapshots anteriores das repetições de ULI estão listados em `web/themes/custom/aculta420/docs/mobile-first-audit.md`.
- **Validação:** Firefox ESR visível passou 49/49 checks autenticados nas larguras 320/390/1023/1024/1280, 175/175 anônimos nas dez raízes a 320/390 (dois SKIPs de primeiro scroll porque Links não tem rolagem vertical) e 136/136 sem JavaScript. Capturas foram revisadas. CSS e JS candidatos foram injetados apenas no navegador; o Runtime não recebeu esses arquivos.
- **Efeito no Runtime:** ULI criou uma sessão Drupal temporária. Não houve alteração de conteúdo, configuração, permissões ou preferências; nenhuma troca de HEAD, `updatedb`, `config:import` ou `drush cr` ocorreu. A contagem permaneceu 624 tabelas.
- **Resultado e limite:** os testes verificam a candidata e não a integração servida. THEME-MF3 e OCS-F11 permanecem pendentes. Relatórios/capturas e inventário integral de snapshots: `web/themes/custom/aculta420/docs/mobile-first-audit.md`.

## 2026-10-31 — revalidação estática THEME-MF2

- Branch `refactor/opencultures-codigo-visual`: antes da atualização documental, HEAD local e remoto coincidiam em `056d49dc9`; worktree limpa e sem alterações prévias nos arquivos da fase.
- Passaram lint CSS/JS, schema SDC (55 componentes, 27 includes, 759 checks), fronteira tema/módulo (3.491), Design Foundations (2.429), documentação UI (520 tokens, 421 usados, 149 atributos) e `CaptchaPresentationHooksTest` (3 testes/8 assertions). PHPUnit emitiu aviso de diretório de browser output não gravável, mas a suíte focal concluiu com status zero.
- Nenhum teste browser integrado foi repetido nesta microfase. O Runtime/checkout servido permanece separado, destacado em HEAD anterior e com alterações de outra frente; não foi alterado. A decisão de ocultar a barra administrativa no scroll permanece validada apenas com CSS/JS candidatos injetados no Firefox.
- Limite: gates estáticos da branch aprovados; THEME-MF0 parcial, THEME-MF3 e OCS-F11 pendentes.

## 2026-10-31 — corrigir 500 por hook visual duplicado e validar shell servido

- Checkout de desenvolvimento `toca` atualizado de `00e6149b0` para `b13776abd`, preservando sete alterações paralelas em `/home/piradopirata/aculta-suite-out/runtime-head-switch-b13776abd/parallel-work.patch` (SHA-256 `73bc004e04230aa53056f7b225bedae91e497b096987458b8175fb7ad60ce398`), stash `8595e92bb798bccf4665097d17c2af12b394dffc` e ref local `refs/archive/dev-before-b13776abd`. Após publicação de `f382ee6f9`, o checkout foi destacado nesse HEAD; `refs/archive/dev-before-f382ee6f9` preserva o HEAD `b13776abd`.
- Drush confirmou Drupal 11.4.10, root `/home/piradopirata/projetos/aculta/web`, URI `http://default`, banco `aculta_runtime`, 624 tabelas. `updatedb:status`: `[success] No database updates required.` `config:status`: 227 itens (116 Different, 69 Only in DB, 42 Only in sync dir). Nenhum `updatedb` ou `config:import` ocorreu. O responsável decidiu manter os 14 módulos MCP ativos e adicioná-los ao Sync; a reconciliação de configuração ficou para microfase própria.
- Causa confirmada dos 500 em Apoio, Conta, Wiki e Growroom: `LogicException` do Core porque `InputHooks` e `PortalUiHooks` registravam `preprocess_input`. Commit publicado `f382ee6f9` removeu o atributo duplicado e adicionou teste de unicidade. Gates: Unit 27/149 PASS; Kernel 7/19 PASS em `aculta_simpletest`; PHPCS, PHP lint, lint CSS/JS, SDC 759, Design Foundations 2.429, boundary 3.490, UI docs 520/421/149 e diff-check PASS.
- Antes dos rebuilds de cache foram salvos snapshots modo 0600: `aculta_runtime-before-pr-head-b13776abd-20261031.sql` (69.137.932 bytes, SHA-256 `275dfa6157c1b829a3d621c0875678146ac9bb8d76364201d75f160f018d0af6`) e `aculta_runtime-before-pr-head-f382ee6f9-20261031.sql` (61.251.555 bytes, SHA-256 `b3dd72ed5ac29fbf8c0083c7b18a988647f8a8345789cb1cb325a71c0326b475`). Ambos precederam `drush cr`; contagem ficou em 624.
- Depois da correção, Main, Loja, Revista, Apoio, Conta, Wiki e Growroom retornaram HTTP 200. Firefox ESR visível sem injeções passou 214/214: dez raízes anônimas em 320/390px e Main autenticado em 320/390/1023/1024/1280px; dois SKIPs são Links sem scroll vertical. Snapshot anterior à sessão ULI: `aculta_runtime-before-institution-bar-browser-f382ee6f9-20261031.sql`, 57.971.044 bytes, SHA-256 `c4035ebe2777d8acc9917bc61ab9889dc918c6b7fbdca68c93cad9e93a7f29f1`. Evidência em `/home/piradopirata/aculta-suite-out/institution-bar-runtime-f382ee6f9-20261031/`.
- Limite: resolve a causa específica dos 500 e comprova o shell servido nessa matriz. THEME-MF3/OCS-F11, WCAG global, zoom em páginas internas e comparação pareada permanecem pendentes.

## 2026-10-31 — autenticação responsiva e top-bar administrativa mobile

- **Runtime:** host `toca`, checkout servido `/home/piradopirata/projetos/aculta`, HEAD destacado `8e0091a4a`, Drupal 11.4.10, URI `http://default`, banco `aculta_runtime`. `updatedb:status`: sem atualizações; `config:status`: sem diferenças. Conta `/` respondeu 302 anonimamente conforme esperado. Não houve import de configuração, updatedb, conteúdo ou alteração de permissões.
- **Conta:** Firefox ESR visível, sem injeção de CSS ou marcador, passou 10/10 checks nas larguras 320/360/390/599/600/768/1024/1280/1440px; sessão sem JavaScript também 10/10. A página serviu o marcador Portal `challenge-frame`, o widget coube em 300px e o documento ficou em 320px a 320px, sem sobreposição do H1. Evidências em `auth-served-runtime-8e0091a4a-20261031/` e `auth-served-nojs-runtime-8e0091a4a-20261031/`.
- **Top-bar administrativa mobile:** decisão do responsável desativa a Navigation top-bar em até 1023px, mantendo-a desde 1024px; Institution Bar fixa em `top:0` no primeiro scroll. Firefox autenticado com CSS/JS candidatos injetados passou 47/47 checks nas larguras 320/390/1023/1024/1280px; sem JavaScript e com CSS candidata, 43/43. Capturas revisadas em `admin-topbar-mobile-final-assertion-8e0091a4a-20261031/` e `admin-topbar-mobile-nojs-retest-8e0091a4a-20261031/`. As primeiras tentativas tiveram 7 falhas por especificidade/seletor e foram corrigidas antes do resultado final. Isto não valida integração servida.
- **Snapshots pré-ULI:** cada teste autenticado foi precedido por snapshot integral modo 0600. Arquivos e hashes: `runtime-snapshots/aculta_runtime-before-admin-topbar-mobile-20261031.sql` (`312b81d626ef3f777524164fd10e16e2499b93c6fbf8ab5d0f01b2fb89e24dde`), `...-rerun-20261031.sql` (`92d32264d91f08f52166f64174aee412cb9b1e1a4cb18d05dd016937b5cb6ee9`), `...-final-20261031.sql` (`37f513ffdc1585edf734a478fce80347cd8272f77cf99d794f23ab26ad2e4a7f`), `...-nojs-20261031.sql` (`6e0b243c95d4a8b03f0953282f43af51c3ab0588509d5f2625bb8db6b4c8c7a7`), `...-nojs-debug-20261031.sql` (`9bd899137be1412c3b1ad1dfa124331f0f6202fc73e8b8d9c5aec36bf2c762ec`), `...-nojs-retest-20261031.sql` (`1d6c53fbe7b3433a222b22ec92cab61f110885cea49e596cfe16801877e8171a`), `...-js-retest-20261031.sql` (`41483bc200acc0772f42027abeaa588a364f1fb4d56b6de551d4000f4d39a577`) e `...-assertion-20261031.sql` (`16bd7d715694713cc77f1e6735d0a3873a08e437003a173e092181921c091e35`). Antes dos seis primeiros, o banco tinha 614 tabelas; nas duas últimas sessões, 611.
- **Purga normal Field API observada:** comparado ao snapshot pós-reconciliação `0f382ba9...` (614 tabelas), o schema passou a 611. A diferença é apenas as três tabelas `field_deleted_data_*` que guardavam cópias antigas após a recuperação Messenger; desapareceram pela purga normal, não por `DROP` manual. O documento de reconciliação Sync registra o delta.
- **Limite:** o CSS/JS da top-bar foi candidato injetado no Firefox e ainda precisa ser validado sem overrides no HEAD publicado. Lint CSS/JS, Python compile e `git diff --check` passaram. THEME-MF3 e OCS-F11 continuam pendentes.
