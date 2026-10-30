# Plano - ingressos gratuitos e pagos em Eventos

Status: **plano revisado, sem código.** A v1 contém somente campos informativos; RSVP gratuito com emissão e venda interna não estão implementados. D-29 a D-43 continuam sendo base para a trilha paga. Esta revisão propõe o fluxo gratuito derivado de D-33/D-36/D-37/D-40, sem autorizar coleta ou venda pública. Ver ADR-036.

> **Atenção:** a emissão de documento fiscal (ponto A, D-35) está **sem decisão**. Nenhuma venda pode abrir antes do
> parecer fiscal e jurídico. Este plano não substitui esse parecer.

## 1. O que o OpenCulturas tem

- **Não tem venda de ingressos.** O `openculturas.info.yml` não depende de módulo de comércio ou pagamento.
- Campos informativos em `node.date`: `field_pricing`, `field_pricing_info` e `field_ticketing` (link externo).
  Os dois primeiros entram na v1 do ACULTA (D-29).
- Não há código a copiar. Venda de ingressos é funcionalidade nova no ACULTA.

## 2. Base que será reutilizada (sem dependência nova)

Regra do responsável: usar o `aculta_commerce` e o `aculta_pagamentos_mercadopago` atuais, sem nenhum módulo ou
pacote novo.

| Peça | Uso no plano |
| --- | --- |
| `aculta_commerce` | Pedido, linhas de pedido e ajustes. Novo tipo de pedido `ingresso`, sem novo módulo |
| `aculta_pagamentos_mercadopago` | Checkout Pro, webhooks assinados, estorno com lock e reconciliação |
| `aculta_portal` (ADR-032) | Registro do contexto em `CheckoutSettings`, carrinho integrado e política de CPF |
| `aculta_statistics` | Métricas de pedidos pagos, segmentadas por tipo |
| Conta única do ACULTA | Recebe todas as vendas. Repasse é manual (D-32) |

Limite conhecido: o gateway não faz repasse automático nem emite documento fiscal.

## 3. Regras de negócio decididas

| Tema | Regra | Decisão |
| --- | --- | --- |
| Repasse | Manual, por evento, com total de vendas e estornos fechado | D-32 |
| Taxa | Percentual e fixa. Administrador define mínimos e quais modalidades ficam ativas. Organizador escolhe valor acima do mínimo e a modalidade | D-33 |
| Quem paga a taxa | Padrão: percentual é pago pelo comprador; fixa pelo organizador. O organizador pode escolher | D-33 |
| Evento gratuito | Permitido, sem cobrança. Pede nome e e-mail | D-33, D-40 |
| Estorno | Opções administrativas: ativar ou desativar, e prazo do estorno integral. Sem valor padrão fixado | D-34 |
| Documento fiscal | Pendente. Nenhuma venda antes da decisão | D-35 |
| Dados do comprador | Nome e e-mail. Retenção padrão de 1 ano após o evento, com controle administrativo | D-36 |
| CPF | Obrigatório **apenas em evento pago**, pela política do `aculta_portal` | D-40 |
| Checkout | Próprio, com a mesma separação de Loja, LMS e Doações. Sem quinta aba administrativa | D-38 |
| Carrinho integrado | Eventos entra na barra, com as regras atuais: um pedido por carrinho, fluxo mais exigente. Precedência LMS > Loja > Eventos > Doações | D-39 |
| Check-in | Organizador, administrador e pessoas cadastradas com acesso, pelo celular, por categoria de operador. O organizador convida e revoga o acesso por evento | D-37 |
| Frete e pagamento | Eventos não tem frete. Checkout sem painéis de envio, com pagamento na etapa final do Mercado Pago (Checkout Pro). Item de evento não entra no cálculo de frete | D-42 |

## 4. Integração com `aculta_commerce` e `aculta_portal`

Contratos que o `aculta_events` precisa, com a regra da ADR-032 de não classificar pedido só pelo domínio.

| # | Contrato | Como |
| --- | --- | --- |
| I-1 | Tipo de pedido `ingresso` | Novo tipo de pedido. **Não reatribuir** tipos de pedido existentes (`default`, `curso`, `doacao`), como exige a ADR-032 (item 6) |
| I-2 | Tipo de item | Novo tipo de item de pedido para o lote. Lote vinculado a `aculta_event_date`. Forma exata (variação de produto Commerce ou entidade própria) a decidir na P2 |
| I-3 | Classificação | Pedido de Eventos é reconhecido pelo **tipo de pedido e tipo de item**, nunca pelo domínio `eventos` (ADR-032, item 5; ADR-015) |
| I-4 | Registro no Portal | `CheckoutSettings` recebe o contexto de Eventos (tipo de pedido, checkout e rota), sem alterar os valores atuais de Loja, LMS e Doações |
| I-5 | Carrinho integrado | `CartSite` mapeia `ingresso` a um separador próprio (**Eventos**). `CartConsolidator` inclui Eventos na fusão, com a precedência LMS > Loja > Eventos > Doações |
| I-6 | Pagamento | Mesmo gateway (`aculta_mercadopago_checkout_pro`), na etapa final do Mercado Pago. Webhook e retorno no MAIN, idempotentes por ID remoto, como hoje |
| I-12 | Fluxo de checkout | Fluxo próprio, modelado no de doação: sem painéis de envio, sem endereço de entrega, com pagamento direto no Checkout Pro (D-42) |
| I-13 | Item não envio | Item de lote é marcado como não envio. Não entra no cálculo de frete nem exige endereço, mesmo em pedido fundido com a Loja |
| I-7 | Emissão do ingresso | Ao pagamento concluído, como `CourseOrderEnrollmentManager` faz para matrícula: lê pagamentos do pedido, valida estado e emite uma vez |
| I-8 | Estorno | Ingresso **não entra** na lista fixa de bloqueio de `RefundEligibilitySubscriber`. O estorno segue a configuração administrativa (D-34) |
| I-9 | Identidade fiscal | CPF exigido só em ingresso pago, pela política do `aculta_portal` (ADR-032, adendo de 2026-10-23, item 2) |
| I-10 | Estatísticas | `aculta_statistics` agrega receita de Eventos separada da Loja. A receita total não é receita da loja (aviso do `aculta_commerce`) |
| I-11 | Green Stash | **Fora.** Green Stash é administração de loja. A gestão de ingressos fica no `aculta_events` |

### Riscos e restrições da integração

- **Rotas globais (B-01).** `CartCheckoutRouteSubscriber` pode afetar checkout global de Loja e LMS. Antes de qualquer
  mudança, caracterizar o comportamento atual e provar o alcance; a alteração deve ser isolada e reversível.
- **Regressão obrigatória.** Carrinho da barra, checkouts de Loja, LMS e Doações, e webhooks do gateway. Não mudar
  comportamento existente sem teste de equivalência.
- **Ordem de precedência.** Confirmar no código de `CartConsolidator` como a ordem LMS > Loja > Doações está
  implementada, antes de inserir Eventos entre Loja e Doações.
- **Frete em pedido fundido.** Quando Eventos e Loja estão no mesmo pedido, o fluxo é o da Loja (precedência). Ingresso não pode gerar frete nem exigir endereço. Confirmar na P4 que o painel de frete considera só os itens de envio.
- **Gate do ADR-032.** O aditivo de 2026-10-28 foi registrado. A implementação segue as fases P-CART (caracterização,
  contratos, integração, transferência, administração, hardening e homologação), com teste no homelab.

## 5. Escopo proposto - trilha paga original

A lista a seguir permanece como escopo da venda paga, complementada e reordenada pelo aditivo normativo da seção 9. A trilha gratuita não cria pedido ou pagamento.

1. **Tipo de pedido `ingresso` e tipo de item de lote** (I-1, I-2).
2. **Lotes de ingresso** por data: nome, preço, quantidade e período de venda.
3. **Taxa** com modalidade e mínimos (D-33).
4. **Checkout próprio de Eventos** (D-38) e entrada no carrinho integrado (D-39).
5. **Compra** pelo fluxo de pedido existente, com limite de quantidade por pedido.
6. **Ingresso emitido**: código único, com QR para check-in.
7. **Check-in** pelo celular, com registro de entrada e bloqueio de reuso.
8. **Estorno** pela configuração administrativa (D-34).
9. **Repasse manual** por evento, com registro (D-32).

## 6. Pontos ainda abertos

| # | Ponto | Depende de |
| --- | --- | --- |
| A | Documento fiscal da venda: quem emite e em que regime | Parecer fiscal (D-35) |
| B | Base legal do tratamento dos dados do comprador (retenção de 1 ano já decidida, D-36) | Parecer de LGPD |
| C | Valores dos mínimos de taxa e prazo de estorno, sem valor inicial | Configuração do administrador |
| D | Detalhe técnico da categoria de operador (nome, tela de convite e revogação) | Implementação em `aculta_events` |
| E | Incidência de taxa sobre o valor do ingresso e contabilidade do repasse | Parecer fiscal |
| F | Forma do item de lote (I-2): variação de produto Commerce ou entidade própria | Decisão técnica na P2 |

## 7. Fases propostas - histórico,,As fases P0-P7 abaixo registram o plano original de venda paga. Para a sequência vigente, critérios de aceite e fluxo gratuito, prevalece a seção 9 (ADR-036), que amplia este escopo sem declarar implementação.,## 7. Fases propostas

| Fase | Entrega | Pré-requisito |
| --- | --- | --- |
| P0 | Parecer fiscal e de LGPD (pontos A, B e E) | Decisão do responsável |
| P1 | Caracterização do carrinho integrado, dos três checkouts e das rotas globais (B-01), com testes | Aditivo à ADR-032 (feito) |
| P2 | Tipo de pedido `ingresso`, tipo de item, `CheckoutSettings` e lotes, sem venda aberta (resolve F) | P1 |
| P3 | Checkout próprio de Eventos com gateway de teste (credenciais em `secrets/`) | P2 |
| P4 | Inclusão de Eventos no carrinho integrado, com regressão da barra e dos checkouts existentes | P3 |
| P5 | Ingresso com QR, check-in e bloqueio de reuso | P3 |
| P6 | Estorno pela configuração administrativa | P3 |
| P7 | Repasse manual por evento, com registro | P0 e P3 |

## 8. Restrições de execução

- Testes de pagamento só com credenciais de teste em `secrets/`. Nunca produção sem RC.
- Credenciais de produção só no banco, criptografadas, provisionadas pelo `aculta_deployer`.
- O prefixo do token não identifica o ambiente (`APP_USR-` pode ser de teste). Decidir pelo modo configurado e pelo
  `live_mode` do pagamento.
- Produção só recebe deploy quando todos os módulos estiverem em RC.
- Testes de navegador com Firefox ESR em `DISPLAY=:0`.
- Commits na branch atual. Sem criação de branch pelo agente.
- Homologação de P4 no homelab, com registro de PASS e FAIL e plano de rollback.


## 9. Aditivo vigente - integração de ingressos gratuitos e pagos (proposta ADR-036)

## Ingressos gratuitos e pagos - proposta de integração (ADR-036)

O estado atual continua sem ticketing interno: `field_gratuito`, `field_pricing` e `field_pricing_info` são informativos; `field_ticketing` é link externo. Não há RSVP com emissão, venda Commerce, lote/estoque ligado a Data, emissão pós-pagamento ou check-in integrado.

- **Ownership:** `aculta_events` é dono das ofertas/lotes, inscrições/participantes, emissão e check-in. `aculta_commerce` mantém pedidos e order items pagos; `aculta_pagamentos_mercadopago` mantém o gateway; `aculta_portal` coordena os tipos/contextos no carrinho já existente. Sem gateway, carrinho ou storage financeiro paralelo.
- **Gratuito (proposta):** RSVP sem cobrança, nome e e-mail (D-33/D-40), sem CPF, pedido Commerce, carrinho ou Mercado Pago. Confirmar capacidade atomicamente e emitir credencial consumível uma vez no check-in. Não coletar dados reais em produção antes da revisão LGPD/base legal/aviso/retenção.
- **Pago (proposta):** lote ligado a Evento e Data; pedido Commerce `ingresso`; Checkout Pro; somente confirmação de pagamento validada e idempotente emite o ingresso. Sem frete. Somente pedidos pagos participam do carrinho integrado e seguem a precedência D-39. Em pedidos mistos, o ingresso não exige endereço nem compõe frete.
- **Credencial compartilhada:** RSVP e compra paga convergem em participante/credencial e check-in móvel, preservando estados financeiros distintos. QR leva apenas token opaco assinado; validar no servidor, exigir autorização contextual, gravar uma entrada e bloquear reuso. Não indexar pedido, PII ou token em `aculta_index`.
- **Domínios e interface:** inscrições e compras iniciadas em `eventos`; escrita validada no servidor e nunca intermediada pelo purpose `account`. Administração técnica/global fica no MAIN. Gestão do organizador pertence ao ACULTA Studio Culturas planejado no purpose `eventos`.
- **Gates financeiros:** venda paga bloqueada até parecer fiscal/jurídico (D-31/D-35), políticas de taxa, estorno e repasse (D-32 a D-34), LGPD e testes em sandbox. Repasse manual por evento; sem marketplace/split na primeira versão.
- **Compatibilidade:** preservar os campos informativos atuais sem migração destrutiva. Quando houver lotes internos, definir precedência de exibição e migração antes de ativar; não inferir uma transação do booleano de gratuidade.

### Referência funcional - The Events Calendar / Event Tickets

A paridade desejada é comportamental, não cópia de código: [Event Tickets oficial](https://github.com/the-events-calendar/event-tickets) documenta RSVP/compra vinculados a eventos e mantém componentes de tickets, pedidos, participantes e gateways. No ACULTA, adaptar o fluxo ao ownership Drupal e às decisões abaixo.

| Capacidade de referência | Adaptação planejada no ACULTA |
| --- | --- |
| RSVP para ingresso gratuito | Inscrição sem cobrança, com nome/e-mail e emissão de credencial, sem Commerce/checkout/gateway; confirmar capacidade e regras de duplicidade. |
| Tipos/lotes de ingresso pago vinculados ao Evento | Lotes associados a Evento e Data; preço transacional, janela, estoque/capacidade e limite de compra. |
| Pedido e confirmação de pagamento | Drupal Commerce + gateway Mercado Pago existente; emissão só após confirmação validada, sem gateway paralelo. |
| Cadastro e consulta de participantes | `aculta_events` é owner dos registros de participante/inscrição; autorização por evento, privacidade e retenção configurada. |
| Validação de entrada | Check-in móvel por QR/código opaco, entrada registrada uma vez e credencial não reutilizável. |
| Gestão editorial pelo organizador | ACULTA Studio Culturas planejado em `eventos`; configuração global e administração técnica ficam no MAIN. |

Não replicar contratos internos do plugin, tabelas, código, gateways ou UI WordPress. Preservar os guardrails e a licença de cada dependência; implementar nativamente em Drupal.

### Decisões ainda abertas

RSVP obrigatório ou opcional; limite por pessoa e prevenção de duplicidade; capacidade por lote versus Data; reserva temporária/expiração/fila; cancelamento e liberação de vaga; abrangência por série; transferência; entrega/acessibilidade do ingresso; retenção/base legal; critérios para migrar links externos.

### Escopo conjunto

1. Modelo de oferta/lote contextual a Evento e Data, com período, preço transacional, capacidade, disponibilidade e limite de quantidade; escolha entre variação Commerce ou entidade de oferta permanece decisão técnica da P2.
2. Registro/participante e credencial com estados explícitos e trilha auditável; QR opaco, assinado e sem PII; check-in móvel compartilhado.
3. RSVP gratuito com nome/e-mail, confirmação de capacidade e emissão da credencial sem criar Commerce order, carrinho, CPF ou chamada de gateway.
4. Ingresso pago como item Commerce em pedido do tipo `ingresso`; Mercado Pago confirma o pagamento antes da emissão. Aplicar idempotência no webhook, retorno, emissão e reentrega.
5. Incluir apenas os pedidos pagos de Eventos no carrinho atual, precedência LMS > Loja > Eventos > Doações. Ingresso é não-envio inclusive em carrinho misto. RSVP gratuito permanece fora do carrinho.
6. Operações de cancelamento, estorno, liberação/retenção de capacidade, taxa e repasse manual; relatórios de ingresso/receita separados por contexto.
7. Gestão do organizador no ACULTA Studio Culturas planejado; configuração global e auditoria no painel administrativo de Eventos no MAIN.

### Nova sequência de implementação

| Fase | Entrega | Gate |
| --- | --- | --- |
| P0 | Resolver regras abertas de RSVP, lotação, duplicidade, reserva, cancelamento, séries, transferência, credencial e política de dados | Decisões registradas; LGPD antes de coleta pública. Parecer fiscal/jurídico antes de venda paga. |
| P1 | Caracterizar carrinho, checkout, gateway, tipos de pedido/itens, webhook, refund e frete | Baseline e rollback; sem alteração funcional. Regressões Loja/LMS/Doações. |
| P2 | Fechar modelo de lotes, inscrição e credencial; implementar type/item `ingresso` e contrato com Portal | Capacidade transacional; não reclassificar tipos existentes; sem vendas. |
| P3 | RSVP/ingresso gratuito e emissão | Sem Commerce, CPF, carrinho ou gateway; nome/e-mail; idempotência, concorrência, cancelamento, privacidade e check-in. |
| P4 | Checkout pago Events no sandbox | Emissão somente após pagamento confirmado; sem frete; gates fiscal/jurídico/LGPD cumpridos. |
| P5 | Integração ao carrinho atual apenas para pedidos pagos | Precedência D-39; pedido misto sem frete para ingressos; regressão de todos checkouts existentes. |
| P6 | Check-in móvel comum a grátis/pago | Token opaco, autorização por evento, uso único, revogação e auditoria. |
| P7 | Taxas, estornos e repasses manuais | Reconciliação pedido-pagamento-ingresso e política fiscal. Sem split/marketplace. |
| P8 | Métricas, retenção e liberação gradual | Receita paga segregada, RSVP fora de receita, expurgo auditável, homelab/sandbox/rollback aprovados. |

Não marcar fase como concluída sem evidência específica. Testes com credenciais somente sandbox; produção bloqueada até pareceres e release. A proposta não habilita transações reais.

### Pontos que agentes não devem presumir

- Se RSVP é obrigatório ou opcional, quantidade máxima por pessoa e deduplicação.
- Se a capacidade limita lote, Data ou ambos; hold temporário, expiração e fila de espera.
- Política para cancelamento gratuito, reabertura de vaga, transferência e ingresso de série.
- Formato acessível da credencial e entrega, operador e dispositivo de check-in.
- Base legal, retenção, aviso, dados mínimos, parecer fiscal e incidência de taxas.
- Precedência entre campos informativos existentes e lotes internos; migração de links externos.

Enquanto as questões estiverem abertas, implementar somente o que for reversível e não coletar/vender no ambiente público.