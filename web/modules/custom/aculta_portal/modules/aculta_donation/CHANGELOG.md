# Changelog — aculta_donation

## 0.1.0-dev.15 — checkout da doação no domínio principal — 2026-10-17

- As etapas de checkout da doação são geradas no MAIN (`routeUrl('main', …)`, redirecionamento confiável); a escolha do
  valor fica no SUPPORT.
- Rotas de entrada e de etapas marcadas como `no_cache` (cada visita tem o próprio pedido e a própria sessão).
- Tipo de pedido e item de cursos (`curso`) criados, para o separador de cursos do carrinho único.
- RUNTIME STATUS: APLICADO no Runtime de testes.

## 0.1.0-dev.14 — fluxo de doação no domínio de apoio — 2026-10-17

- O botão "Doar" da página inicial e os valores do bloco apontam ao domínio de apoio (`routeUrl('support', …)`).
- RUNTIME STATUS: APLICADO no Runtime de testes.

## 0.1.0-dev.13 — cartões compactos e quebra automática — 2026-10-17

- Os valores e o "Doar" usam o mesmo componente SDC `donation-card`: cartão compacto, com quebra automática do texto
  e linhas que quebram quando não cabem na largura.
- O "Doar" da página inicial ocupa só a largura do texto (botão avulso).
- RUNTIME STATUS: APLICADO no Runtime de testes (verificado por captura estreita e larga na página de apoio e por captura
  da página inicial).

## 0.1.0-dev.12 — título do bloco de valores — 2026-10-17

- O bloco de valores da página de apoio tem título visível, "Escolha um valor" (H2, tipografia dos títulos de seção).
- RUNTIME STATUS: APLICADO no Runtime de testes (título verificado no HTML e por captura do topo da página de apoio).

## 0.1.0-dev.11 — fonte única dos valores — 2026-10-17

- O bloco de doação rápida é a única lista de valores da página de apoio. Lê a campanha vigente ou os "Valores padrão"
  do painel. O campo de valores manual saiu do bloco; o botão "Outro" aparece quando o painel permite valor personalizado.
- Links de doação do bloco apontam direto ao domínio principal (`DomainPurposeManager`).
- RUNTIME STATUS: APLICADO no Runtime de testes.

## 0.1.0-dev.10 — sem duplicação na página de apoio — 2026-10-17

- O bloco de valores sai da página de apoio: os valores aparecem só nos cartões do formulário de apoio (sem repetição).
  O bloco continua disponível para outros lugares, se for colocado de novo.
- A opção "Outro valor" passa a se chamar "Outro", em todos os formatos.
- RUNTIME STATUS: APLICADO no Runtime de testes (página de apoio verificada: 5 valores, uma vez cada).

## 0.1.0-dev.9 — cartões de valor do bloco de apoio — 2026-10-17

- Os valores do bloco de doação rápida viram cartões (não botões), no mesmo estilo dos cartões da página de apoio. A
  opção "Outro valor" tem borda tracejada, por ser um campo digitável. O formato compacto continua com "Outro".
- Estilo do componente `donation-quick` em CSS próprio, com tokens semânticos do tema (superfície, borda, texto, foco e
  movimento), inclusive para o modo de redução de movimento.
- RUNTIME STATUS: APLICADO no Runtime de testes (cartões verificados por captura do topo da página de apoio).

## 0.1.0-dev.8 — variante compacta do bloco de valores — 2026-10-17

- Opção "Espaço reduzido" no bloco de valores: até 3 sugestões e a opção curta "Outro", para locais pequenos. Sem a opção,
  4 valores e "Outros valores".
- Testes visuais (Firefox headless, topo das páginas): a versão compacta cabe bem; a versão grande precisava de margem
  em relação ao texto de introdução. O componente ganhou espaçamento vertical (`my-3`).
- RUNTIME STATUS: APLICADO no Runtime de testes. Instância de teste removida.

## 0.1.0-dev.7 — valores do bloco de apoio — 2026-10-17

- O bloco de valores rápidos mostra R$ 5, 10, 20 e 50, e "Outros valores" leva ao passo de doação, onde o visitante
  digita o valor (campo "Outro valor" do widget). Sem valores salvos, o bloco usa esses mesmos valores.
- A configuração padrão do bloco saiu: a mesclagem com a configuração salva duplicava os valores.
- RUNTIME STATUS: APLICADO no Runtime de testes (página de apoio verificada por HTTP; campo de digitação verificado).

## 0.1.0-dev.6 — blocos posicionados — 2026-10-17

- "Doar" (pequeno) na página inicial do domínio principal, na região de conteúdo, antes da seção de apoio.
- "Valores de doação rápida" (grande) no topo da página inicial do domínio de apoio (região de destaque). Valores padrão
  R$ 5, 10, 20, 50 e 100, os mesmos da página de apoio.
- Os links de valor no apoio apontam para `/apoiar/{valor}/agora`; a visita no apoio é redirecionada ao domínio principal
  (política de transação).
- RUNTIME STATUS: APLICADO no Runtime de testes (HTML verificado nos dois hosts; capturas do headless não mostram seções
  animadas pelo tema).

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
