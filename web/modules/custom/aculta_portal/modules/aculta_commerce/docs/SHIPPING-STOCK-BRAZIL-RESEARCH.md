# Commerce — Pesquisa frete brasileiro, estoque e operação de loja

Data: 2026-10-22. **Planejamento e fontes revisadas, sem instalar módulos.** Proteger carrinho na barra multidomínio, checkout atual e consolidação do Portal.

## Inventário real desta branch

- `composer.json` já requer `drupal/commerce_shipping:^3.0` e `drupal/commerce:^3.3`.
- `config/sync/commerce_shipping.commerce_shipment_type.default.yml` existe.
- `aculta_portal/src/Plugin/Commerce/CheckoutPane/AcultaShippingInformation.php` deriva de `ShippingInformation`, usa o endereço único/cópia do billing e preserva escolha de serviço e remessas.
- Portanto: **não criar motor paralelo de envio, perfil de endereço ou checkout**, nem alterar o cabeçalho. O gateway `aculta_pagamentos_mercadopago` continua compartilhado.

## Comparativo de módulos e decisões

| Projeto | Estado público em 2026-10-22 | Decisão |
|---|---|---|
| [Commerce Shipping](https://www.drupal.org/project/commerce_shipping) | 3.0.5 estável, Drupal 10.3/11/12; já requerido | Base obrigatória; aproveitar plugins ShippingMethod, shipments e APIs |
| [Melhor Envio Shipping](https://www.drupal.org/project/melhor_envio) | 1.0.3, Drupal 10/11/12; adoção pequena, sem security advisory | **Auditar fonte/licença/testes e portar/adaptar somente lógica útil**: cliente HTTP, autenticação, cotação, erros; não instalar automaticamente |
| [Correios](https://www.drupal.org/project/correios) | Projeto antigo de 2010, sem suporte moderno | Não instalar; escrever novo adaptador sobre API atual mediante documentação, credenciais sandbox e contratos oficiais |
| [Commerce Stock](https://www.drupal.org/project/commerce_stock) | 3.0.0 estável Drupal 11; transações, multi-loja e locais, enforcement opcional | **Instalar contrib, não portar/reinventar**; testar cuidadosamente Commerce 3, concorrência e consolidador |
| [Commerce Simple Stock](https://www.drupal.org/project/commerce_simple_stock) | release publicada indica Drupal <=10 | Não adotar como principal em Drupal 11 |
| [Commerce Shipping Tracking](https://www.drupal.org/project/commerce_shipping_tracking) | 1.0.3 suporta Drupal 11 | Opcional para experiência de rastreio |
| [Commerce Shipping Label](https://www.drupal.org/project/commerce_shipping_label) | 2.0.3 suporta Drupal 11 | Opcional para integração com emissão de etiqueta |
| [Commerce Pricelist](https://www.drupal.org/project/commerce_pricelist) | 2.16 suporta Drupal 11 | Somente se houver necessidade real de atacado/tabela por cliente |
| [Commerce Reporting](https://www.drupal.org/project/commerce_reports) | RC para Drupal 11 | Preferir `aculta_statistics` (já centraliza métricas); apenas copiar ideias, sem métricas duplicadas |
| [Commerce Recipe Shipping](https://www.drupal.org/project/commerce_recipe_shipping) | 1.1.0, demonstra frete fixo e endereços | Referência de configuração e testes; não aplicar receitas às cegas |

## Arquitetura de frete proposta

`aculta_commerce` mantém apenas adaptadores brasileiros para **Commerce Shipping**; cada integração deve ser implementada como ShippingMethod/plugin/serviço alinhado à API instalada, não endpoint paralelo de checkout.

Contrato proposto, sujeito a API real: `RateQuote` imutável (operadora, serviço, valor Price, prazo, validade, quote ID); cliente HTTP via `http_client`, credenciais via Key/Secret e nunca config exportável, timeouts, limites/rate limit, retries controlados, validação de CEP e normalização de dimensões/peso. Distinguir cotação de compra de etiqueta, rastreio e cancelamento. Persistir escolha da cotação no shipment/order conforme Commerce, sem preços vindos do browser. Invalidar cotação quando CEP, itens ou quantidades mudarem.

**Melhor Envio:** auditar source upstream e licença antes de portar; preferir SDK/HTTP de baixo acoplamento e testes com respostas fixtures, recusas, cotação sem serviço, produtos sem dimensões, timeout, CEP inválido e credencial ausente.

**Correios:** não aproveitar API legada de 2010. Checar requisitos oficiais atuais e contratos antes de implementar cliente.

**Frete fixo/retirada:** disponibilizar fallback configurável no próprio Commerce Shipping quando cotador indisponível, sem forçar preço zero nem concluir pedido sem opção válida.

## Estoque

Preferir `commerce_stock:^3.0` com módulos Stock API/Field/Local Storage/UI e, se escolhido, Enforcement. Habilitar **somente para variações físicas da Loja**, não para cursos, doações ou assinaturas. Verificar evento de decremento, devolução ao cancelar, reversão, estoque em múltiplos locais, reservas em concorrência, reprocessamento e checkout consolidado `CartConsolidator`. Não tratar estoque como tabela de produtos pública nem indexá-lo no Content Index. Green Stash administra transações/autorização, sem duplicar fonte.

## Roadmap por fases

- **COM-SF 0.1-A:** inventário real no servidor de tipos/variações shippable, dimensões, CEP/endereço, métodos Commerce Shipping ativos, estado de estoque e preços; baseline de carrinho/checkout.
- **COM-SF 0.1-B:** baixar código do Melhor Envio, conferir licença/API/contrib e mapear quais partes portar; testar mock HTTP e comparar contra fallback de frete fixo.
- **COM-SF 0.1-C:** preparar plugin/cliente de cotação brasileira com fixtures e serviços DI, sem ativá-lo; integração ao Commerce Shipping e critérios de cache/erro.
- **COM-SF 0.2-A:** prova Commerce Stock 3 no homelab: singles e multicarrinho consolidado, oversell, cancelamentos, devolução e localizações.
- **COM-SF 0.2-B:** operação Green Stash: saldos, movimentos, reposição, locais, custo e relatórios por autorização real; telas SDC/UI Suite.
- **COM-SF 0.3-A:** emissão etiqueta/rastreio, cancelamento e reconciliação de fretes, segundo carrier escolhido.
- **COM-SF 0.3-B:** ofertas e cupons nativos Commerce; catálogo paginado, busca via Content Index se API estiver disponível; relatórios `aculta_statistics`.
- **COM-SF T:** homologação de preços, impostos, CEP, frete, estoque concorrente, checkout, pagamento, webhook, privacidade, domínio e acessibilidade 320px no servidor.

## Gates e não escopo

Nenhum `composer require`, `drush en`, mudança de config de pedidos, store ou gateway nesta pesquisa. Não inventar status do servidor. Preservar checkout unificado existente (com prioridade de pedidos), assinatura segregada, barra multidomínio e seleção de checkout. Requer backup e aprovação operacional para ativar estoque/enforcement em runtime. Exigir testes antes de introduzir qualquer plugin de taxa real.

## Dívidas novas

- **DT-COM-14** executar inventário de Commerce Shipping efetivamente instalado, métodos/cotações/endereços/dimensões.
- **DT-COM-15** auditoria de código upstream Melhor Envio e contrato oficial Correios, SDK/API, licença, credenciais sandbox.
- **DT-COM-16** prova de conceito Stock 3 em banco de testes e compatibilidade com consolidator, sem alterar dados reais.
- **DT-COM-17** decidir transportadoras, contrato comercial, CEP de origem, seguros e etiquetas.
