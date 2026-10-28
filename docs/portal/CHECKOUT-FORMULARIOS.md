# Formulário de checkout por tipo de compra

**Decisão do responsável (2026-10-27).** Cada contexto de compra tem o formulário mínimo que ele exige. Quando o
checkout junta contextos diferentes, vale o formulário com mais dados obrigatórios.

Complementa [CPF-CONSOLIDACAO-E-CHECKOUT.md](CPF-CONSOLIDACAO-E-CHECKOUT.md), que define a identidade fiscal (CPF/CNPJ)
exigida na Loja e no LMS. Este documento trata dos campos de cada formulário.

## 1. Regras

| Contexto | Campos obrigatórios | Fluxo de checkout esperado | Situação atual |
|---|---|---|---|
| Doação (`doacao`) | E-mail e nome | Fluxo `doacao`: formulário simplificado, sem CPF | E-mail confirmado no painel de contato (`double_entry`). O nome vem do painel de doação, a confirmar. |
| LMS, curso pago (`curso`) | E-mail, nome completo e CPF/CNPJ | Fluxo próprio do LMS (`curso`): contato, nome e sobrenome (painel `aculta_personal_name`), identidade fiscal e pagamento. Sem endereço de cobrança e sem envio. | Fluxo criado no servidor de testes (2026-10-28). Falta verificar no navegador com um curso publicado. |
| Loja (`default`) | Identidade fiscal (CPF/CNPJ), endereço de cobrança e envio | Fluxo `default` | Conforme. |
| Carrinho misto (mais de um tipo de pedido no mesmo checkout) | O formulário com mais dados obrigatórios | Fluxo `default` (loja), que é o mais completo | Conforme, por ser o fluxo da loja. |

Assinatura de doação (`assinatura`) segue o fluxo `assinatura_flow`, fora desta decisão.

## 2. Ordem de precedência

1. Loja (fluxo `default`): mais campos, prevalece no checkout misto.
2. LMS (fluxo próprio): intermediário.
3. Doação (fluxo `doacao`): menos campos.

## 3. Pendências

- Criar o fluxo próprio do LMS (e-mail, nome completo, CPF/CNPJ) e mapear o tipo `curso` para ele. Isso é uma
  alteração de configuração do Commerce (`commerce_checkout_flow`) e de mapeamento de tipo de pedido, e precisa de
  autorização do responsável antes da execução.
- Confirmar se o nome da doação é pedido no painel de doação (`DonationCheckoutPane`) e como ele se compara ao nome
  completo do LMS.
- Checkout misto: confirmar a implementação real. Hoje cada tipo de pedido é um pedido separado; o "checkout conjunto"
  só existe se o fluxo unir contextos.

## 4. Como verificar

- Mapeamento tipo → fluxo: `OrderTypeCheckoutFlows::flowId()`.
- Campos do fluxo: `commerce_checkout.commerce_checkout_flow.<fluxo>` (painéis e obrigatoriedade).
- Doação: fluxo `doacao` com painel `contact_information` (e-mail) e painel de doação.
- LMS e loja: fluxo `default` e painel `aculta_fiscal_identity` (CPF/CNPJ).

## 5. E-mail obrigatório no cadastro simplificado (2026-10-27)

- O painel de contato do Commerce marca o e-mail como obrigatório (`#required = TRUE`). Os fluxos da loja (`default`) e da
  doação (`doacao`) têm esse painel ativo, então o e-mail já é exigido nos dois.
- Loja: o painel de login (cadastro simplificado, e-mail primeiro) está ativo, com cadastro permitido.
- Doação: o login está desativado e o cadastro ao concluir (`completion_register`) está desativado (`_disabled`).
  Ativá-lo muda o comportamento da doação e precisa de decisão do responsável.
- Pendência: confirmar se o cadastro simplificado deve ficar ativo também na doação.

## 6. Nome e sobrenome e preenchimento automático (2026-10-28)

- Nome e sobrenome são obrigatórios no LMS (`curso`) e na doação (`doacao`), pelo painel `aculta_personal_name`.
- Quem tem conta vê o nome do perfil principal (Meu Perfil) já preenchido. Quem não tem conta vê o que já informou no pedido.
- O nome vai para o perfil da conta criada na compra só quando o perfil ainda não tem nome.
- CPF/CNPJ: quando já cadastrado na conta, o painel mostra o documento mascarado (já era assim); não há novo preenchimento.
- Loja (`default`): sem painel de nome, porque o endereço de cobrança já pede nome e sobrenome.
