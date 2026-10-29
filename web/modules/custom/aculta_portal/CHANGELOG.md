## 0.4.1-dev.45 — completar inventário de helpers de imagem no Status (2026-10-29)

- O catálogo administrativo do Portal agora documenta `aculta_image.capabilities`
  e `aculta_image.pdf_thumbnail`, mantendo o registro alinhado às tags ativas.
- Gate Portal Drupal 11+ (1.163 checks), índice de status, PHP lint e PHPCS
  Drupal/DrupalPractice passaram. Nenhum dado ou configuração Runtime mudou.

## 0.4.1-dev.44 — reparar referências documentais do Status e do checkout (2026-10-29)

- Os catálogos de helpers e implementações apontam à ADR-032 vigente para
  resolver checkout, contextos, política e fechamento das rotas globais.
- O gate Portal Drupal 11+ passou (1.162 verificações); Unit Portal 323/974 e
  Kernel Portal 80/1.530 passaram, com 96 depreciações Core/contrib.
- A entrada mantém referência explícita ao padrão Drupal 11+. Nenhuma
  configuração ou dado Runtime mudou.

## 0.4.1-dev.43 — URLs do sitemap por ambiente no Deployer (F7) — 2026-10-28

- `DeployerEnvironment` lê o host de cada Domain em `domain_transition.entries`; `DomainPurposeManager` gera URLs de página/caminho para o ambiente ativo. As URLs canônicas de produção existentes permanecem separadas.
- O sitemap usa os hosts do ambiente para raízes, caminhos públicos, conteúdo e eventos. No teste, canonical e sitemaps usam hosts de teste e mantêm `noindex`.
- Kernel e Unit: sitemap 7 testes/13 assertions, 46 depreciações; Portal DomainPurposeManager 8/47, 1 depreciação; DeployerEnvironment 4/9. PHPCS Drupal/DrupalPractice, PHP lint e validação HTTP passaram.

## 0.4.1-dev.41 — exceção de confirmação de e-mail no checkout (2026-10-27)

- Decisão do responsável: contas autenticadas podem comprar no checkout Commerce sem confirmar o e-mail. Permanecem obrigatórios pedido próprio, loja publicada, pedido com itens, permissão `access checkout` e aceite dos termos. A decisão não concede papel nem marca de e-mail confirmado.
- Pedido 131 foi inspecionado por metadados read-only no Runtime: `default`, `draft`, carrinho ativo com um item, proprietário ativo uid 1, loja publicada, sem fluxo/etapa persistidos; os termos estão aceitos e a marca de confirmação de e-mail está ausente. O 403 era da política anterior.
- `ProtectedRouteAccessCheck` mantém o acesso nativo Commerce como fonte de verdade e combina somente o aceite dos termos para autenticados; a rota de entrada comum preserva a permissão nativa e checkout anônimo. A etapa inicial agora seleciona somente panes visíveis à sessão, evitando `login` após autenticação.
- Após `drush cr`, `access_manager.checkNamedRoute(commerce_checkout.form, order 131, uid 1)` retornou `allowed` com `email_confirmed=false` e termos aceitos. Regressões cobrem checkout autenticado sem confirmação (mantendo termos), checkout anônimo, pane login visível/oculto e etapa persistida não visível. Unit Portal antes do teste de leitura sem escrita: 280 testes / 763 assertions PASS (6 depreciações PHPUnit); Guest Checkout: 7/64; CheckoutStepResolver: 4/11; ProtectedRouteAccessCheck: 7/20. PHPCS Drupal/DrupalPractice nos arquivos alterados PASS. Login visual na sessão do usuário ainda não foi verificado.

## 0.4.1-dev.40 — etapa válida do checkout após autenticação (2026-10-27)

- Corrigido o carrinho para selecionar uma etapa realmente visível no fluxo Commerce da sessão atual. Um pedido com etapa persistida `login` não volta mais ao pane de identificação depois que o comprador já está autenticado; visitantes anônimos permanecem no fluxo de identificação.
- Portal declara `commerce_checkout` como dependência direta e registra `aculta_portal.checkout_step_resolver` no catálogo interno de serviços.
- Regressões Unit verificam etapa `login` visível para visitante, etapa de identificação ignorada para usuário autenticado, resolução pelo fluxo do pedido e fluxo sem etapas. Testes Portal: CheckoutStepResolver 4/8, ProtectedRouteAccessCheck 5/19; contrato Guest Checkout do Commerce 6/62 PASS. PHP lint/PHPCS focais pendentes.

## 0.4.1-dev.39 — links de produto do carrinho no domínio SHOP (2026-10-27)

- O formatter `product_id` do modo Commerce `cart` emitia link canônico relativo ao host do carrinho MAIN; a rota de produto pertence ao propósito SHOP e retornava 404 no MAIN.
- Hook do Portal reescreve o link para SHOP por `DomainPurposeManager`, com cache context `domain`; se SHOP não estiver configurado, apresenta apenas o título e evita link quebrado.
- Kernel `CartProductLinkHooksKernelTest`: 1 teste / 7 assertions PASS. No Runtime, render do modo `cart` produziu o URL do produto no host SHOP (HTTP 200); o mesmo path no MAIN retorna HTTP 404. PHPCS Drupal/DrupalPractice e PHP lint PASS. Nenhum carrinho ou pedido foi criado.

## 0.4.1-dev.38 — dependência opcional do Mercado Pago

- Portal deixa de depender do módulo Mercado Pago. Doação e LMS mantêm a dependência direta declarada; o Portal resolve somente contratos opcionais `aculta_portal.payment_provider.*`.
- Os metadados de Doação/LMS declaram a dependência direta do MP; a prova de instalação limpa segue pendente, pois o Drush não aplicou o prefixo isolado esperado e a execução foi interrompida.

## 0.4.1-dev.37 — contratos neutros para provedores de pagamento

- Portal define interfaces de status de assinatura, ações de assinatura, operações de pagamento e política de estorno; os serviços opcionais usam IDs `aculta_portal.payment_provider.*` registrados pelo módulo provedor. Sem o provedor, relatórios/ações correspondentes desaparecem ou negam acesso.
- Consumidores do Portal deixaram de referenciar classes e IDs de serviço do Mercado Pago. `SalesPaymentCancelForm` e `SalesPaymentRefreshForm` usam a exceção neutra; nenhum detalhe cru da API chega à UI.
- Unit Portal: 272 testes / 748 assertions PASS (6 depreciações PHPUnit). Formulários opcionais: 3/3 assertions.

## 0.4.1-dev.36 — comprovar referências de arquivo em revisões e rich text (2026-10-26)

- Novo `FileReferenceInspectorKernelTest` usa Entity API e MariaDB isolado para provar que o Portal detecta um File referenciado somente por revisão histórica e outro UUID de File embutido em campo de texto. Não executa remoção.
- Kernel: 2 testes / 19 assertions PASS. Unit focais de `UnusedFileCleaner` e `UnusedMediaCleaner`: 8 testes / 31 assertions PASS (duas depreciações PHPUnit).
- Não houve exclusão de File/Media nem acesso ao Runtime. A validação operacional do cleanup segue pendente.

## 0.4.1-dev.35 — checkout LMS dispensa identidade fiscal para curso gratuito (2026-10-26)

- `FiscalIdentityPolicy` mantém a exigência da Loja e dos cursos pagos do LMS;
  pedidos LMS só dispensam CPF/CNPJ quando todos os itens são variações `curso`
  reconhecidas com preço base zero. O preço base evita que desconto/cupom
  transforme um curso pago em matrícula gratuita. Pedido vazio ou composição
  ambígua exige documento (fail-closed).
- `FiscalIdentityPolicyTest`: 13 testes / 30 assertions PASS;
  FiscalIdentityPaneKernelTest: 12 testes / 343 assertions PASS em MariaDB
  dedicado (72 depreciações upstream). PHPCS Drupal/DrupalPractice e PHP lint
  dos arquivos alterados PASS.
- Esta decisão implementa a política vigente do projeto: matrícula gratuita não
  exige documento fiscal; não altera pedidos nem configuração do Runtime.

## 0.4.1-dev.34 — cobrir o inspetor global de referências

- FileReferenceInspectorTest cobre referências estruturadas e embeds em texto nas revisões anteriores, além de retorno fail-closed quando a consulta de um campo falha.
- Suíte Unit Portal: 353 testes / 914 assertions PASS; seis depreciações PHPUnit. Sem alterações de código de Runtime ou escrita no banco.

### Validação das suítes Portal — 2026-10-25 (sem alteração runtime)

- `AccountOtherDataStudioReturnTest` fornece `path.validator` para o caminho
  Drupal real de `Url::fromUserInput()` e restaura o container global anterior
  ao terminar; não altera o comportamento runtime do Portal.
- Portal Unit: 350 testes / 878 assertions PASS; seis depreciações PHPUnit.
  Portal Kernel em MariaDB isolado: 53 testes / 831 assertions PASS; 90
  depreciações de Drupal/Core/contrib. Runtime, Functional/browser e RC seguem
  pendentes.

## 0.4.1-dev.32 — limpeza global ignora tipos sem armazenamento instalado (2026-10-25)

- `FileReferenceInspector` deixa de tratar como `Unverifiable` os tipos de
  entidade registrados sem tabela base instalada: sem armazenamento não há
  linhas capazes de referenciar o alvo. O fail-closed permanece para tipos com
  tabela base cuja consulta falhe e para storages não persistentes que não
  sejam `ContentEntityNullStorage`.
- Corrige os dois cenários de limpeza de imagem da Wiki que falhavam quando
  `media_gallery` estava registrado sem schema e `entity_embed_fake_entity`
  usava null storage. Nenhuma exclusão nova é autorizada: apenas tipos sem
  linhas persistidas são ignorados; referências reais continuam retendo.
- Kernel Wiki `WikiLeadImageLifecycleKernelTest` 28 testes / 1.611 asserções
  PASS; Unit Wiki 82/448 PASS; PHPCS Drupal/DrupalPractice PASS.

## 0.4.1-dev.31 — caracterização de documentos S-R3-B1 (2026-10-25)

- Novos testes Unit para AccountOtherDataForm: owner ausente, chave indisponível, campo cifrado com leitura mascarada e desabilitada, ausência de fingerprint, erro de decifração não revelado, entrada Form API, validação genérica e dado canônico no FormState.
- Teste de returnUrl cobre prioridade de checkout, bloqueio de destinos externos e retorno separado Studio/legado; expectativa de URL base alinhada ao Drupal 11.
- Código runtime do Portal inalterado. **Testes não executados**; Kernel/Functional/Runtime ainda necessários.

## 0.4.1-dev.30 — S-R3-B2: dados cadastrais sem apelido em Studio (2026-10-25)

- `PortalHooks::formAlter` separa por route ID: `/studio/conta/dados` reutiliza `PortalController::myData()` e Profile Form API para nome e WhatsApp privados, sem `field_nickname`. O legado `/dados` conserva o formulário anterior.
- `PortalFormCallbacks::accountDataRedirect()` conserva o retorno na Conta Studio, enquanto a sincronização de nomes com Commerce continua no mesmo owner. Nenhum schema, checkout ou shell foi removido.
- Testes Unit de roteamento/delegação escritos, mas não executados; lint, gates, Kernel, Functional, Runtime e Firefox ESR/X11 pendentes.

## 0.4.1-dev.29 — retorno contextual do documento CPF/CNPJ no Studio (2026-10-24)

- `AccountOtherDataForm` continua usando o mesmo
  `AccountDocumentInterface` para validar, cifrar e salvar uma única
  vez o CPF/CNPJ. Apenas o destino padrão após submit considera a
  rota `aculta_studio.account_documents`; o legado segue voltando
  para `aculta_portal.my_data_other`.
- Destino de checkout internamente permitido mantém precedência e
  proteção original; não foram criados novos redirect aliases,
  entidades, campos ou acesso público a documentos.
- Teste Unit `AccountOtherDataStudioReturnTest` criado; sem execução
  PHPUnit/Kernel/Runtime nesta edição.

## 0.4.1-dev.28 — decisão da guarda do hub na raiz da Conta em FrontPathPolicy (2026-10-24)

- A regra do hub do Studio na raiz do ACCOUNT (404 fora de `/`, login para anônimo) sai de
  `DomainPurposeRequestSubscriber` e passa a `FrontPathPolicy::acaoDoHubNaConta`, função pura e testada
  (`DomainPurposeRootGuardTest`). O subscriber só traduz a ação em resposta.
- Gate `validate-aculta-portal-conta-root` atualizado para a nova forma. Mesmo comportamento HTTP.

## 0.4.1-dev.27 — editor de endereço do Studio recebe país, Número e S/N (2026-10-24)

- `PortalHooks::formAlter` aplicava o bloco de endereço (país padrão, Número/S-N, botão "Salvar endereço",
  remoção de campos redundantes) só quando o caminho era `/dados/endereco`. Em `/studio/conta/endereco` o
  bloco não rodava. Agora a condição usa a identidade da rota (`$isAddressRoute`), que cobre o Portal e o
  Studio com o mesmo Profile Commerce.
- Gate `scripts/validate-account-address.php` passa. Teste em Kernel/Firefox pendente.

## 0.4.1-dev.26 — raiz da Conta serve o hub do Studio; Minha Conta original em /conta-legada (2026-10-24)

- `PurposeFrontPathProcessor::FRONT_PATHS` ganha `account => /studio`. A raiz `/` do Domain ACCOUNT serve
  o hub do Studio (`aculta_studio.hub`) por reescrita interna, sem redirect. `/studio` direto responde 404.
- `aculta_portal.dashboard` (Minha Conta original) passa a `/conta-legada`. Controller, shell, formulários e
  controles de acesso não mudaram. `/minha-conta` responde 404 sem redirect.
- Visitante anônimo na raiz da Conta vai ao login do host de conta (302).
- Gate `scripts/validate-aculta-portal-conta-root.php` atualizado para o novo contrato. Gate de endereço
  (`PortalHooks`, país/Número/S-N) continua com FAIL pré-existente, fora deste commit.

## 0.4.1-dev.25 — mesmos formulários de endereço em Conta Studio e legado (2026-10-24)

- `PortalHooks` agora reconhece o ID de rota
  `aculta_studio.account_address` além de
  `aculta_portal.my_data_address`, preservando ocultação de campos
  redundantes, país BR, Número/S-N, CEP, builders e o mesmo Profile
  Commerce. Não mudou entidade, schema nem checkout.
- `PortalFormCallbacks::addressRedirect` retorna ao editor pelo qual
  o usuário salvou, sem mudar o retorno original da Conta legada.
- Gate `scripts/validate-account-address.php` ampliado aos dois
  editores; teste Unit do callback criado. **Execução desses gates,
  Kernel/Runtime/Firefox pendente** nesta edição GitHub.

## 0.4.1-dev.24 — política global sem aliases temporários (2026-10-24)

- Removidos redirects de /minha-conta/* (agora 404), URLs antigas de address-book Commerce e listagem /pagamentos, bem como handlers órfãos. As rotas canônicas /, /dados/endereco e /pedidos permanecem; fluxos funcionais de autenticação, checkout e pagamento não foram alterados. Testes Runtime pendentes.
- Regra transversal: `AGENTS.md` e `scripts/validate-no-temporary-route-aliases.php`.

## 0.4.1-dev.23 — prévias de busca como regiões de navegação

- O comportamento compartilhado mantém o input search nativo e apresenta as
  respostas em regiões `aria-live` nomeadas. Como as respostas são links de
  navegação, remove a semântica combobox e a captura das setas de edição; Tab
  percorre os links. Wiki, busca popup e busca de membros declaram os rótulos
  de suas regiões.
- JavaScript passou `node --check`; markup Wiki passou Unit 4/18; fixture Firefox
  do JS local passou 14/14. Browser no Runtime antigo identificou semântica
  combobox/região antiga e submit fora da viewport (28/35). Nenhum Runtime foi
  alterado.

## 0.4.1-dev.22 — assumir o bundle compartilhado `remote_video`

- `aculta_portal` fornece a configuração default do Media `remote_video`, seu
  campo oEmbed e o provider mínimo do Content Index; a Media Library permanece
  opcional.
- Revista, Wiki, Fórum e LMS ficam documentados como consumidores. Este commit
  não altera conteúdo Runtime nem cria links/relações implícitos entre entidades.

## 0.4.1-dev.21 — normalizar timestamps do provider Media do Content Index

- `MediaContentIndexProvider` converte explicitamente os timestamps da entidade
  para inteiros antes de criar a projeção; o Media real do Core pode retornar
  valores numéricos como strings após load.
- Kernel MariaDB do Index cobre `media:image` em backfill, update e delete
  usando Media/File reais; não habilita `aculta_image` completo nem altera
  Runtime.

## Não lançado — identidade fiscal no checkout comercial (CPF-CHECKOUT-0.2-I) — 2026-10-23

- Política única `FiscalIdentityPolicy` (aculta_portal) para o checkout comercial de Loja e LMS. Doações ficam dispensadas. A consulta usa o contrato `AccountDocumentInterface` de `aculta_id_cpf`, sem copiar o documento para pedido, perfil ou sessão. Falha fechada.
- Pane `aculta_fiscal_identity`: sem campo de entrada. Mostra o documento mascarado, aviso com link para `/dados/outros-dados`, ou mensagem genérica. Valida ao enviar a revisão, no servidor.
- Flow `default` passa a ser o checkout comercial compartilhado. Login obrigatório e cadastro permitido. O tipo `curso` usa o flow `default`; o flow `curso` foi removido. Ativação depende de `config:import` autorizado.
- Preferência pelo serviço do `aculta_associado_id` removida de `PortalController` e `AccountOtherDataForm`.
- Testes Unit: `FiscalIdentityPolicyTest` (7). Kernel pendente. RUNTIME STATUS: DEFERRED.
- Pane de identidade fiscal aceita o primeiro cadastro na própria compra (gravação única, mesmo serviço de Outros Dados). Depois de salvo, o checkout mostra o valor mascarado e não editável. Mensagens de erro genéricas.
- Outros Dados volta ao checkout quando o link traz destino interno sob `/checkout/`.

## 0.4.1-dev.20 — auditoria das alterações de checkout e flow efetivo (P-CART F) — 2026-10-22

- As abas Comum, Loja e LMS registram no canal `aculta_portal_checkout` quem alterou o quê (contexto, valores antes e depois, uid), sem segredos; desligar o checkout comum gera aviso.
- `OrderTypeCheckoutFlows` passa a devolver o flow efetivo, com o mesmo fallback `default` do `DefaultCheckoutFlowResolver` do Commerce quando o tipo de pedido não define flow (antes, vazio). Diagnóstico e campo da aba refletem o que o Commerce usa de fato. RUNTIME STATUS: DEFERRED.

## 0.4.1-dev.19 — interface administrativa Carrinhos e checkouts (P-CART E) — 2026-10-22

- Abas **Comum | Loja | LMS** em `/painel-administrativo/aculta/checkout[/loja|/lms]` (menu ACULTA > Carrinhos e checkouts), com Form API, Config API, abas por tarefas locais (funcionam sem JavaScript) e permissões separadas: `administer aculta checkout common|shop|lms`. Nenhuma dá acesso a pedidos.
- Comum: chave global do checkout comum, diagnóstico dos providers, conflitos e referência dos gateways (sem credenciais). Loja e LMS: tipo de pedido e checkout flow nativo, com validação de conflito entre contextos (`CheckoutMappingValidator`). Salvar reconstrói as rotas.
- Não altera o carrinho da barra multidomínio. Apresentação via tema (SDC/UI Suite) ainda pendente. RUNTIME STATUS: DEFERRED (formulários e acesso verificados por API no runtime local; navegador pendente).

## 0.4.1-dev.18 — regra de estorno centralizada em MercadoPagoPaymentPolicy — 2026-10-22

- `MercadoPagoPaymentPolicy::bloqueiaEstorno()` passa a ser o único lugar da regra "doações, assinaturas e cursos não têm estorno": `assinatura` entra como constante fixa, e os tipos de pedido e de item extras (doação pelo carrinho e tipo de pedido de curso configurado) chegam por parâmetros opcionais. `RefundEligibilitySubscriber` só reúne a configuração e faz uma chamada. Sem mudança de comportamento em relação a dev.17. RUNTIME STATUS: DEFERRED.

## 0.4.1-dev.17 — estorno negado explicitamente para assinatura e doação pelo carrinho — 2026-10-22

- `RefundEligibilitySubscriber` passa a negar estorno também para pedidos `assinatura`, para o `cart_order_type` e itens `cart_item_type` de Doações. Antes, a assinatura só era bloqueada se o tipo do item coincidisse com `donate_item_type`, e a doação feita pelo carrinho (modo `cart`/`both` com tipos próprios) não era coberta. Só acrescenta bloqueio; a regra fixa de `bloqueiaEstorno()` não mudou. Alinha o código a `PAYMENT-DOMAIN-POLICY.md` (sem estorno para doações, assinaturas e cursos). RUNTIME STATUS: DEFERRED.

## 0.4.1-dev.16 — configuração de checkout, chave global e tipo de pedido de curso configurável (P-CART E) — 2026-10-22

- Nova config `aculta_portal.checkout_settings` (schema e padrões que reproduzem o comportamento anterior) e serviço `CheckoutSettings`. `CheckoutPolicyManager` passa a respeitar a chave global `common_checkout_enabled`.
- `CartUtility` e `CartConsolidator` reconhecem o tipo de pedido de curso configurado (e o histórico `curso`); `RefundEligibilitySubscriber` nega estorno também para o tipo configurado (só acrescenta bloqueio). Decisões em ADR-013, adendo de 2026-10-22. RUNTIME STATUS: DEFERRED.

## 0.4.1-dev.15 — seleção das rotas globais de checkout centralizada (P-CART D) — 2026-10-22

- Novo `CommerceCheckoutRouteSubscriber` e `CheckoutPolicyManager::commonCheckoutRoutesAvailable()`: a decisão que o `CartCheckoutRouteSubscriber` do Donation tomava (rotas do Commerce fechadas quando `donation_route` é `donate`) passa a ser do Portal, com comportamento idêntico. `CheckoutContextConfiguration` ganha `gatesCommonCheckout`.
- Sem mudança observável: `donate`, `cart` e `both`, `/carrinho` e o carrinho da barra multidomínio ficam como estavam. RUNTIME STATUS: DEFERRED.

## 0.4.1-dev.14 — providers de checkout e leitura do flow nativo (P-CART C) — 2026-10-22

- Novo `OrderTypeCheckoutFlows`: lê o checkout flow associado ao tipo de pedido no Commerce, sem alterá-lo. Serve aos providers de Loja, LMS e Doações. Sem efeito público. RUNTIME STATUS: DEFERRED.
