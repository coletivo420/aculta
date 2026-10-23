# ACULTA Commerce — 0.1.0-dev.3

Submódulo de integração comercial da **loja**, em estado Dev. O provider de configuração do checkout e a experiência Green Stash estão em código. A orquestração de checkout pertence ao `aculta_portal`, conforme ADR-013. As seções abaixo registram a evolução por fase; afirmações sobre ausência de serviços ou rotas descrevem a fundação histórica. A cadeia completa de Green Stash ainda exige homologação no Runtime.

Na conciliação dev.3, Unit Commerce 20/116 (9 depreciações PHPUnit), Melhor Envio 29/78 e CEP 9/36 passaram. O gate de endereço passou; o gate global módulo/tema mantém cinco violações anteriores em Studio, LMS e Wiki, sem violações restantes em Commerce. Nenhum fluxo de pagamento ou serviço externo foi executado nesta validação.

## Fronteiras imutáveis

- **Loja:** Drupal Commerce é proprietário das entidades de produto, variação, pedido, carrinho, pagamento e demais transações da loja; `aculta_commerce` coordena serviços específicos da loja.
- **Doações:** `aculta_donation` é proprietário de campanhas, checkout, doações e assinaturas. Green Stash não absorve essa experiência nem altera os pedidos de doação.
- **LMS:** `aculta_lms` é proprietário de matrículas, acesso a cursos, ensino e suas regras. Uma venda de acesso educacional depende de contrato explícito com o LMS.
- **Pagamento compartilhado:** `aculta_pagamentos_mercadopago` é o gateway central de **loja, LMS e doações**. Não duplicar o plugin nem implementar gateways adicionais em `aculta_commerce`.
- **Portal:** `aculta_portal` conserva o shell transversal, contratos de domínio e identidade; as integrações comerciais atuais só podem mudar de dono após caracterização e migração auditada.
- **Estatísticas:** `aculta_statistics` conserva eventos e agregações; Green Stash apenas consome relatórios autorizados.
- **Studio:** `aculta_studio` hospeda a experiência **Green Stash**, distinta de GanjaLab, GanjaLab420 e WE SATIVA.
- **Tema:** `aculta420` é o dono da implementação visual SDC/UI Suite e preserva o cabeçalho multidomínio.

## Fundação (histórico)

Não há `aculta_commerce` na árvore anterior desta branch; há `ShopController`, checkout panes, relatórios e integração com Mercado Pago no Portal. Esta primeira entrega **não move entidades, não altera checkout, não registra rotas nem habilita serviços**. O próximo passo é caracterização completa, classificação de ownership e migração incremental.

Documentos:
- [Roadmap e auditoria inicial](docs/ROADMAP.md).
- [Contrato de indexação do catálogo](docs/CONTENT-INDEX.md).

## Gate de habilitação no servidor de testes

Validar compatibilidade das dependências do manifesto com os módulos instalados, `drush pm:list`, `drush en aculta_commerce -y` apenas no runtime isolado, `drush cr`, `drush updatedb:status` e `php scripts/validate-module-theme-boundary.php`. Não ativar nem instalar automaticamente nesta fase.

## Integração com a carteira/carrinhos do Portal

`aculta_commerce` fornece o contexto **Loja** ao gerenciador transversal do Portal; o Portal administra as abas **Comum | Loja | LMS | Doações**, seleciona modalidade de checkout por carrinho/pedido e preserva o checkout específico de cada módulo. A aba Comum não significa fusão física de pedidos. O Green Stash permanece a experiência Studio de administração da loja. Ver [roadmap](docs/ROADMAP.md) e [ADR-013](../../../../../../docs/decisions/ADR-013-portal-multi-cart-checkout.md). Integração ainda **não implementada**.

## RETIFICAÇÃO ADR-013 — Abas de administração, não de compra

O `aculta_commerce` fornecerá configuração do checkout da **Loja** à orquestração administrativa do `aculta_portal`. **Comum | Loja | LMS | Doações** são exclusivamente abas internas administrativas; não criar uma interface de carrinho público. O carrinho da barra multidomínio e o seletor de checkout atuais são preservados. Green Stash continua gestão da loja no Studio. O Mercado Pago continua gateway transversal. Ver [ADR-013](../../../../../../docs/decisions/ADR-013-portal-multi-cart-checkout.md).

## Green Stash — fundação 0.1.0-D

Permissão restrita `access aculta green stash`, serviço `aculta_commerce.green_stash_access` e teste de caracterização implementados. **Ainda não há rota, provider Studio, controller nem acesso a entidades**: o gate de entrada não concede operações de Commerce. Ver [plano D](docs/GREEN-STASH-0.1.0-D.md). Não habilitar em produção sem `drush cr`, PHPUnit, validação de permissões e homologação de dependências.

## Green Stash — ponto de entrada Studio D-A/D-B

Criado provider independente `green_stash` e rota `/green-stash` (purpose `account`) protegida por `access aculta green stash`. O registro no `StudioExperienceManager` ocorre pelo hook de container do próprio Commerce, sem fazer Studio depender de Commerce. O conteúdo é **exclusivamente informativo** até a fase de integração de dados com autorização por entidade/store. Ainda não homologado no servidor. Ver [documentação D](docs/GREEN-STASH-0.1.0-D.md).

## Green Stash — navegação inicial GS-0.2-A/B

Criada navegação progressiva com nove áreas: Visão geral, Produtos, Pedidos, Estoque, Envios, Promoções, Clientes, Relatórios e Configurações, acessíveis via `/green-stash/{section}` no purpose `account`, sem operações de negócio. A permissão `access aculta green stash` protege as rotas e a verificação do Studio permanece no controller. Renderização básica de estado vazio, **sem componentes SDC definitivos ou operações comerciais ativas**. Consultar [proposta mobile-first](docs/GREEN-STASH-UX-MOBILE-FIRST.md).

## Rotas PT-BR do Green Stash

Entrada canônica `/estudio/loja`; páginas internas `/estudio/loja/{secao}` com slugs `visao-geral`, `produtos`, `pedidos`, `estoque`, `envios`, `promocoes`, `clientes`, `relatorios` e `configuracoes`. Identificadores internos Drupal continuam estáveis. Registro no Studio pelo `AcultaCommerceServiceProvider`, não por hook container. **A área ainda mostra somente estado de preparação**, sem listas ou ações financeiras. Validar container, roteamento e permissões no homelab.

## Catálogo inicial com busca (GS-0.2-C.2)

A rota `/estudio/loja/produtos` agora aceita parâmetros GET `busca` (título, até 100 caracteres) e `antes` (cursor numérico de paginação), apresenta até 20 produtos autorizados e só gera links nativos Commerce de edição. O leitor aplica acesso da entidade e permissão na loja, sem divulgar totais privados. A interface ainda é provisória e **não substitui** os componentes SDC/UI Suite planejados para o ACULTA420. Testes de integração e homologação permanecem pendentes.

## Contrato obrigatório de edição Green Stash

O Green Stash substitui a interface operacional nativa Commerce para a loja. Produtos, variações, estoque e envios devem ser geridos por fluxos Studio, **não** por links para `edit-form` administrativo. O link nativo anterior foi retirado da listagem até existir a edição própria. Usar Commerce como backend e ACULTA420 SDC/UI Suite na apresentação. Ver [contrato de operações](docs/GREEN-STASH-OPERATIONS-CONTRACT.md).

## GS-EDITOR-0.2 — Detalhe sem painel administrativo

A listagem de produtos agora abre `/estudio/loja/produtos/{produto}`. O controller próprio exibe detalhes mínimos em modo leitura e verifica acessos à entidade, store e experiência Studio. Nenhum link abre o formulário nativo Commerce. A criação e edição próprias ainda são próximas fases; esta tela **não permite alterar produtos**. Ver o [roadmap](docs/ROADMAP.md).

## GS-EDITOR-0.3-A — Novo produto sem painel administrativo

Novo formulário Studio em `/estudio/loja/produtos/novo`: cria rascunho de produto `produto_loja` com nome e loja selecionada (somente lojas editáveis pelo operador). Persiste na entidade Commerce nativa, não cria entidades paralelas e não publica. Link `Novo produto` aparece na listagem. **Ainda não permite variações, preço, imagens ou estoque**; requer homologação antes da habilitação no servidor.

## Loja unica
O Green Stash utiliza somente a loja comercial existente. Novo produto solicita apenas nome e associa automaticamente a unica Commerce store, apos verificar permissao. LMS e Doacoes nao sao novas lojas nem opcoes do formulario. Nenhum ID de loja fixo; configuracoes ambiguas falham fechadas.
