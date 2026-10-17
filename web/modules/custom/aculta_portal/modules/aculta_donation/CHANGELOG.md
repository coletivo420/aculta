# Changelog — aculta_donation

## 0.1.0-dev.5 — tipos de pedido próprios e convite para criar conta — 2026-10-17

- O fluxo de doação não estava sendo usado: o tipo de pedido `default` (da loja) não tinha fluxo associado e o pedido de
  doação caía no fluxo da loja. Criados os tipos `doacao` (fluxo `donation_flow`) e `assinatura` (fluxo `assinatura_flow`).
  O item de doação passa a usar `doacao`, e a configuração do fluxo aponta para `doacao`. A loja não muda.
- Convite para criar conta no painel de doação, só para visitante: link que abre o cadastro em modal
  (`aculta_portal.register_popup`) e volta para a etapa atual. O cadastro em modal não usa Turnstile (ver o CHANGELOG do
  aculta_portal).
- RUNTIME STATUS: APLICADO no Runtime de testes (tipos de pedido, configuração e convite verificados por HTTP).

## 0.1.0-dev.4 — estorno, testes de Kernel e checkout com e sem visitante — 2026-10-17

- Doações e assinaturas não têm estorno (decisão do responsável). Bloqueio no servidor: `DonationRefundAccess` soma uma
  checagem à rota `entity.commerce_payment.operation_form` (operação `refund-payment`), e a tabela de vendas não mostra o
  link para pedidos com item de doação. A política da loja (produtos) não muda. Assinatura terá cancelamento, não estorno.
- Teste de Kernel `DonationPortKernelTest` (4 testes, passam no banco `aculta_simpletest`): plugins, blocos, criação do
  pedido de doação e regra de estorno.
- Correções achadas pelo teste: widget `commerce_donation_flow_level_widget` (a configuração optional tinha o nome
  renomeado por engano); tipos anuláveis explícitos nas fábricas dos painéis (Core 11.4).
- Checkout: a doação avulsa continua aceitando visitante (`donation_flow` como estava). Novo fluxo
  `assinatura_flow` (mesmo plugin) exige conta: login e cadastro na etapa de cobrança, sem visitante e sem painel de
  e-mail (o e-mail vem da conta). Nenhum endereço é pedido em nenhum dos dois, porque o gateway não coleta cobrança.
- Pendente: vincular o `assinatura_flow` ao tipo de pedido de assinatura quando a F3 existir.

## 0.1.0-dev.3 — port do commerce_donation_flow — 2026-10-17

- Port de `commerce_donation_flow` 1.2.0 para o submódulo. Contrib removido do `composer.json` e do lock; `commerce_cart` e
  `commerce_checkout` passam a ser dependências diretas (mesma versão 3.3.10).
- Namespace, serviços, rotas e hooks de tema renomeados para `aculta_donation`. Nomes de configuração e IDs de plugin usados
  pela configuração gravada (`commerce_donation_flow.settings`, `donation_checkout_flow`, `commerce_donation_pane`,
  `commerce_donation_flow_level_widget`, `commerce_donation_summary_formatter` etc.) foram mantidos.
- Anotações de plugin convertidas para atributos PHP 8 (Block, CommerceCheckoutFlow, CommerceCheckoutPane, FieldWidget,
  FieldFormatter), no padrão Drupal 11.
- Blocos portados e renderizados pelo tema: valores rápidos (SDC `donation-quick`), "Doar" e "Voltar ao site".
- Componentes SDC de dedicatória, notificação, painel de doação e resumo movidos para o tema ACULTA420 (`donation-*`).
- Classes de design do contrib trocadas por tokens `data-portal-ui` (`donation-*`), traduzidos no tema.
- Configuração do fluxo fundida ao painel de Doações, com abas (Campanhas, Valores padrão, Fluxo de doação) e rota em
  `/painel-administrativo/configuracoes/aculta/doacoes/fluxo`. Correções: a chave do tipo de pedido do `/donate` agora
  bate com a gravada; a checagem de "duas rotas" não usa `empty()` sobre expressão; campos obrigatórios só quando visíveis;
  opção "As duas rotas" só aparece com tipos distintos.
- Regras do responsável: doações e assinaturas não podem ser estornadas.
- Pendente: assinatura recorrente (portar o necessário do `commerce_recurring` e integrar com o Mercado Pago, F3); testes
  de Kernel do fluxo; conferência visual no navegador.
- RUNTIME STATUS: APLICADO no Runtime de testes (`aculta.toca.net.br`, `aculta_runtime`). Backup antes do corte:
  `estados/aculta_runtime-antes-port-donation-20261017-1345.sql.gz` (local, fora do Git). Os 14 itens de doação foram
  preservados. A desinstalação removeu `commerce_donation_flow.settings`; restaurada com os valores do backup.
