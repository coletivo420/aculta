# Execução contínua ACULTA — PR #298

## Mercado Pago — microfase de cancelamento e reconciliação (2026-10-27)

- Base de trabalho: `cd49917f6`. O serviço de pagamento usa a mesma chave de lock do webhook; consulta e cancelamento recarregam o pagamento Commerce, validam ID remoto e não aceitam total/moeda divergentes. Repetição após timeout reconcilia `cancelled` localmente sem nova chamada de cancelamento.
- Cancelamento valida ID e estado da resposta do provedor; somente estados `pending`, `in_process` e `authorized` são aceitos, conforme a [referência oficial](https://www.mercadopago.com.br/developers/pt/reference/online-payments/checkout-api-payments/create-cancellation/put). Falhas de transporte usam mensagens/logs sem conteúdo privado.
- Regressões cobrem cancelamento permitido/negado, reconciliação após timeout, lock ocupado, ID remoto divergente, total/moeda incorretos e falha de transporte. Unit MP: 150 testes / 464 assertions PASS; PHP lint e PHPCS Drupal/DrupalPractice dos PHP alterados PASS.
- Kernel não executado (`SIMPLETEST_DB` ausente). Sandbox de cancelamento e pagamentos reais não utilizados. No checkout servido, `drush cr` PASS, Drush resolveu `aculta_pagamentos_mercadopago.payment_service` para a classe real e MAIN/Loja responderam HTTP 200. Nenhum `updatedb`, import de config ou escrita em pagamento foi feito. A alteração ainda não foi publicada.

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
