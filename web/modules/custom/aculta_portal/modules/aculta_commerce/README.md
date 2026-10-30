# ACULTA Commerce — 0.1.0-dev.14

## Green Stash Suite (ADR-038)

The management entry is `/greenstash` in purpose `shop`, including its
product and shipment operations. `/studio/green-stash` and children are
removed without compatibility routes. Commerce remains owner of products,
orders and shipment state; cross-purpose links use `DomainPurposeManager`.

Submódulo proprietário das integrações da loja, em estado Dev. O motor de envios ACULTA está integrado aqui, sem módulo adicional ou alteração em contrib. O Config Sync versionado aponta para `aculta_shipment`, os plugins próprios e o pane `aculta_shipping_information`; `drupal/commerce_shipping` foi removido de `composer.json`/`composer.lock` e `drupal/physical` é dependência explícita.

**Evidência reexecutada nesta PR (2026-10-26):** Unit Commerce 55 testes/272 asserções PASS; `ShippingEngineKernelTest` 7/237 PASS; Unit Melhor Envio 85/204 PASS; `MelhorEnvioWiringKernelTest` 11/57 PASS em MariaDB dedicado, sem rede externa; instalação limpa Standard + `aculta_commerce` + `aculta_commerce_melhorenvio` em prefixo descartável PASS, com provider descoberto e `commerce_shipping` ausente; `drush cr` e `updatedb:status` PASS. PHPCS Drupal/DrupalPractice nos quatro arquivos PHP integrados do Melhor Envio: zero erros, avisos de linhas longas; PHP lint PASS. Checkout funcional, Firefox ESR e validação externa no sandbox permanecem pendentes.

O Runtime compartilhado de testes foi consultado somente para leitura: `commerce_shipping` ainda está habilitado e seu field target ativo continua `commerce_shipment`. Nenhuma alteração foi aplicada a esse Runtime nem houve pedido, frete, pagamento ou chamada externa. O cutover operacional desse Runtime permanece pendente de aplicar o código da PR ao ambiente e executar a sequência segura; este README distingue essa pendência da instalação limpa isolada já verificada. Checkout visual e homologação funcional em navegador permanecem pendentes; módulo segue Dev.

## COM-SHIP — escopo e segurança

O motor próprio vive dentro de `aculta_commerce`, sem editar `contrib` ou criar outro módulo. Os dados legados de shipping só podem ser descartados em banco de testes comprovadamente descartável, conforme [reset controlado](docs/SHIPPING-RESET-TEST-ONLY.md); não aplicar a produção, não importar Config Sync no Runtime e não realizar pedidos/logística reais. Antes de qualquer futuro cutover, revisar configuração ativa e dependências, validar a instalação limpa e registrar rollback. Consulte o [contrato do Melhor Envio](docs/MELHOR-ENVIO-CONTRACT.md), a [matriz do porte](docs/SHIPPING-PORT-MATRIX.md) e o [diagnóstico e plano do port](docs/COMMERCE-SHIPPING-PORT.md).

## Integração de avisos da Loja — contrato vigente

`aculta_commerce` publica eventos de pedidos e seleciona destinatários segundo
as regras da Loja. Consumers, avisos no site, armazenamento Message, central,
deduplicação, preferências, digest, e-mail direto e workers são centralizados
no `aculta_messenger`. O site é obrigatório; e-mail pode ser desligado, direto
ou digest/resumo pelo membro e pelas políticas administrativas. O `aculta_mailer`
mantém transporte e templates. O recibo Commerce permanece para convidados e
fallback; não há consumer ou entrega legado no submódulo. Consulte
[`docs/ROADMAP.md`](docs/ROADMAP.md).

## Fronteiras imutáveis

- **Loja:** Drupal Commerce é proprietário das entidades de produto, variação, pedido, carrinho, pagamento e demais transações da loja; `aculta_commerce` coordena serviços específicos da loja.
- **Doações:** `aculta_donation` é proprietário de campanhas, checkout, doações e assinaturas. Green Stash não absorve essa experiência nem altera os pedidos de doação.
- **LMS:** `aculta_lms` é proprietário de matrículas, acesso a cursos, ensino e suas regras. Uma venda de acesso educacional depende de contrato explícito com o LMS.
- **Pagamento compartilhado:** `aculta_pagamentos_mercadopago` é o gateway central de **loja, LMS e doações**. Não duplicar o plugin nem implementar gateways adicionais em `aculta_commerce`.
- **Portal:** `aculta_portal` conserva o shell transversal, contratos de domínio e identidade; as integrações comerciais atuais só podem mudar de dono após caracterização e migração auditada.
- **Estatísticas:** `aculta_statistics` conserva eventos e agregações; Green Stash apenas consome relatórios autorizados.
- **Studio:** `aculta_studio` integra a área comercial **ACULTA STUDIO GREEN STASH** em `/studio/green-stash`, junto às áreas oficiais ACULTA STUDIO Ganjalab, ACULTA STUDIO WE SATIVA, Conta e Perfil.
- **Tema:** `aculta420` é o dono da implementação visual SDC/UI Suite e preserva o cabeçalho multidomínio.

## Fundação (histórico)

Não há `aculta_commerce` na árvore anterior desta branch; há `ShopController`, checkout panes, relatórios e integração com Mercado Pago no Portal. Esta primeira entrega **não move entidades, não altera checkout, não registra rotas nem habilita serviços**. O próximo passo é caracterização completa, classificação de ownership e migração incremental.

Documentos:
- [Roadmap e auditoria inicial](docs/ROADMAP.md).
- [Contrato de indexação do catálogo](docs/CONTENT-INDEX.md).

## Gate de habilitação no servidor de testes

Validar compatibilidade das dependências do manifesto com os módulos instalados, `drush pm:list`, `drush en aculta_commerce -y` apenas no runtime isolado, `drush cr`, `drush updatedb:status` e `php scripts/validate-module-theme-boundary.php`. Não ativar nem instalar automaticamente nesta fase.

## Conta de visitante e identificação do checkout

### Conta criada pelo Commerce Core no checkout

Os flows de Loja/LMS (`default` e `shipping`) e Doações (`doacao` e
`assinatura_flow`) permitem concluir como visitante. O subscriber nativo do
Commerce Core cria a conta em background ao concluir o checkout e envia o
aviso com link para definir senha. O checkout não cria contas pelo formulário
inline. `guest_order_assign` permanece desativado: um e-mail coincidente não
autoriza anexar um pedido a uma conta existente. O pedido e o módulo dono do
produto continuam sendo a fonte de verdade; liberar matrícula ou reconhecer
pagamento depende do estado confirmado do pedido/gateway.

No pane de identificação, o Portal mostra a entrada Google antes do e-mail.
Após o blur, o AJAX apresenta a senha para um e-mail ligado a conta ou revela o
cadastro silencioso de visitante para um novo e-mail. A consulta é limitada
por flood control; a autenticação e o bloqueio de tentativas continuam no Core.
Adicionar itens, atualizar/remover carrinho, cotar frete e checkout ficam sem
CAPTCHA em todos os canais. A exceção também cobre IDs por produto e todos os
flows (inclusive panes de endereço e frete), removendo o elemento inserido
pelo fallback global do Captcha mesmo com Turnstile global ativo. O smoke visual
confere produto, carrinho, cadastro AJAX e formulário de endereço/frete; não
envia o endereço nem solicita cotação ao transportador.

Teste Unit de contrato: `GuestCheckoutRegistrationConfigTest` verifica todos
os flows e suas associações aos tipos de pedido. Rodar com:
`php vendor/bin/phpunit -c web/core web/modules/custom/aculta_portal/modules/aculta_commerce/tests/src/Unit/GuestCheckoutRegistrationConfigTest.php`.

## Integração com a carteira/carrinhos do Portal

`aculta_commerce` fornece o contexto **Loja** ao gerenciador transversal do Portal por `ShopCheckoutProvider`; o Portal administra as abas **Comum | Loja | LMS | Doações**, seleciona modalidade de checkout por carrinho/pedido e preserva o checkout específico de cada módulo. A aba Comum não significa fusão física de pedidos. **ACULTA STUDIO GREEN STASH** é a área integrada de gestão comercial em `/studio/green-stash`. A travessia visual Loja→MAIN e o estado de identificação do checkout foram comprovados; submissão como visitante, conta após checkout, e-mail de senha e gateway permanecem pendentes de configuração e homologação. Ver [roadmap](docs/ROADMAP.md) e [ADR-032](../../../../../../docs/decisions/ADR-032-portal-multi-cart-checkout.md).

## RETIFICAÇÃO ADR-013 — Abas de administração, não de compra

O `aculta_commerce` fornece o contexto e as configurações de checkout da **Loja** à orquestração administrativa do `aculta_portal`. **Comum | Loja | LMS | Doações** são exclusivamente abas internas administrativas; não criar uma interface de carrinho público. O carrinho da barra multidomínio e o seletor de checkout atuais são preservados. **ACULTA STUDIO GREEN STASH** integra a gestão da loja ao ACULTA Studio em `/studio/green-stash`. O Mercado Pago continua gateway transversal. Ver [ADR-032](../../../../../../docs/decisions/ADR-032-portal-multi-cart-checkout.md).

## Green Stash — fundação 0.1.0-D

Registro histórico da fundação 0.1.0-D: naquele ponto havia somente permissão e serviço de acesso, sem rotas ou operações. A experiência evoluiu nas fases abaixo e no código atual; este parágrafo preserva o estado da fase original, não descreve o estado presente.

## Green Stash — ponto de entrada Studio D-A/D-B

Registro histórico da fase D-A/D-B: o provider e a rota então eram somente informativos. A composição atual usa `AcultaCommerceServiceProvider` e rotas `/studio/green-stash`; consultar o contrato COM-SHIP e o roadmap para o estado vigente. O caminho histórico `/estudio/loja` foi removido sem redirect em 2026-10-30.

## Green Stash — navegação inicial GS-0.2-A/B

Na entrega inicial foi criada navegação progressiva com nove áreas acessíveis no purpose `account`; naquela fase eram estados vazios, sem operações de negócio. A permissão `access aculta green stash` protege as rotas e a verificação do Studio permanece no controller. Hoje Produtos possui catálogo, detalhe e cadastro de rascunho; as outras áreas continuam estados preparatórios, sem ações comerciais. Consultar [proposta mobile-first](docs/GREEN-STASH-UX-MOBILE-FIRST.md).

## Rotas PT-BR do Green Stash

Entrada canônica `/studio/green-stash`; páginas internas `/studio/green-stash/{secao}`. Identificadores internos Drupal continuam estáveis. Registro no Studio pelo `AcultaCommerceServiceProvider`, não por hook container. Produtos têm listagem, detalhe e criação de rascunho; Envios tem leitura e transição de remessas conforme permissões. Pedidos, Estoque, Promoções, Clientes, Relatórios e Configurações ainda não oferecem operações funcionais. Container, roteamento, permissões e catálogo precisam de homologação no Runtime.

## Catálogo inicial com busca (GS-0.2-C.2)

A fase inicial aceitou `busca` e `antes`, mostrava até 20 produtos autorizados e ainda apontava para edição nativa. Esse link foi removido na fase seguinte; a rota atual abre o detalhe Green Stash, sem formulário nativo. O leitor aplica acesso da entidade e permissão na loja, sem divulgar totais privados. A interface ainda é provisória e **não substitui** os componentes SDC/UI Suite planejados para o ACULTA420. Testes Functional e homologação permanecem pendentes.

## Contrato obrigatório de edição Green Stash

O Green Stash substitui a interface operacional nativa Commerce para a loja. Produtos, variações, estoque e envios devem ser geridos por fluxos Studio, **não** por links para `edit-form` administrativo. O link nativo anterior foi retirado da listagem até existir a edição própria. Usar Commerce como backend e ACULTA420 SDC/UI Suite na apresentação. Ver [contrato de operações](docs/GREEN-STASH-OPERATIONS-CONTRACT.md).

## GS-EDITOR-0.2 — Detalhe sem painel administrativo

A listagem de produtos abre `/studio/green-stash/produtos/{produto}`. O controller próprio exibe detalhes mínimos em modo leitura e verifica acessos à entidade, store e experiência Studio. Nenhum link abre o formulário nativo Commerce. A criação própria de rascunho foi implementada em GS-EDITOR-0.3-A; a edição, variações, preço, imagens e estoque continuam pendentes. Ver o [roadmap](docs/ROADMAP.md).

## GS-EDITOR-0.3-A — Novo produto sem painel administrativo

Novo formulário Studio em `/studio/green-stash/produtos/novo`: cria rascunho de produto `produto_loja` com nome e loja selecionada (somente lojas editáveis pelo operador). Persiste na entidade Commerce nativa, não cria entidades paralelas e não publica. Link `Novo produto` aparece na listagem. **Ainda não permite variações, preço, imagens ou estoque**; requer homologação antes da habilitação no servidor.

## Loja unica
O Green Stash utiliza somente a loja comercial existente. Novo produto solicita apenas nome e associa automaticamente a unica Commerce store, apos verificar permissao. LMS e Doacoes nao sao novas lojas nem opcoes do formulario. Nenhum ID de loja fixo; configuracoes ambiguas falham fechadas.

## Green Stash em domínio próprio - ADR-038

Destino futuro: `loja.aculta.*/greenstash`, purpose `shop`. As rotas atuais `/studio/green-stash...` serão removidas no corte, sem redirects/aliases. Commerce mantém ownership de produtos, remessas, permissões e workflows; `aculta_studio` somente compõe a experiência.
