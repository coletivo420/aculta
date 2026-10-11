# Testes interativos no navegador (Portal, loja e checkout)

Documento vivo. Atualizar a cada rodada de teste: seções F (resultado) e G (decisões) mudam; A a E mudam só se o fluxo ou os hosts mudarem.

## A) Fluxo de trabalho

Dois agentes e uma pessoa responsável (Felipe):

- **Agente do repositório** (Claude Code, neste repositório): altera código e configuração, roda gates, publica no servidor de testes e diz o **passo exato** a executar.
- **Agente de navegador** (Claude in Chrome / Cowork): executa o passo no navegador logado na conta de teste e reporta **etapa, URL, pedido, erro e requisição** (com o texto da mensagem, se houver). Não corrige nada.
- **Felipe**: faz o login quando o agente de navegador não pode digitar senha, digita cartão e CPF, e decide o que é mudança de segurança.

Regra: o agente do repositório só afirma que algo funciona depois de verificar por HTTP ou API. Depois que o navegador confirma, o resultado vai para a seção F.

## B) Hosts e URLs (servidor de testes)

Os hosts do servidor de testes são `*.aculta.toca.net.br`. `*.aculta.org` é produção; não usar produção nos testes.

| Host | Papel | Caminhos principais |
| --- | --- | --- |
| `aculta.toca.net.br` | Principal (MAIN). Carrinho e checkout pertencem sempre a este host. | `/carrinho`, `/checkout/{pedido}/order_information`, `/checkout/{pedido}/review`, `/checkout/{pedido}/payment` |
| `conta.aculta.toca.net.br` | Conta (ACCOUNT). Raiz `/` é a visão geral (logado) ou o login (anônimo). | `/` (visão geral), `/entrar`, `/dados`, `/dados/endereco`, `/pedidos`, `/pagamentos`, `/aceite-termos` |
| `loja.aculta.toca.net.br` | Loja (SHOP). | `/loja-da-aculta`, produto `/loja/produto/bloco-sativa/camiseta-abada-bloco-sativa-2026-regata` (ou `/product/1`, que redireciona 301) |

Caminhos que **não** existem ou não são públicos:

- `/cart`: não existe. O carrinho é `/carrinho`.
- `/conta-interna`: removido em 0.3.0-dev.13. Visitante recebe 404; logado é levado para `/` pela regra geral de caminho inexistente da Conta (decisão pendente, ver F).
- `/minha-conta`: caminho técnico interno da visão geral (rota `aculta_portal.dashboard`). Direto responde 404. Não usar como link.

O domínio de Conta usa a coleção de configuração `domain.conta_aculta_org`, que define o `front` (`/minha-conta`). É isso que faz `/` servir a visão geral.

## C) Contas

- **Admin `piradopirata`**: uid 1, papéis `administrator` e `authenticated`. O e-mail não está confirmado, e a política de checkout (`aculta_portal.access.protected_route`) nega o checkout. Não confirmar e-mail direto no banco sem decisão do responsável.
- **Usuário de teste `teste.comum`** (uid 5): e-mail `teste.comum@example.invalid` (domínio reservado, não recebe mensagens). Papéis `authenticated` e `email_confirmed`, sem admin. Termos aceitos e confirmação de e-mail registrados. Criado pelo agente do repositório no banco de testes.
- **Limpeza ao final de cada rodada**: excluir o usuário `teste.comum`, os pedidos de teste (rascunhos e concluídos), perfis de cobrança e endereços criados no teste. Não deixar resíduos. Só executar com o pedido do responsável.

Login de teste sem digitar senha: o agente do repositório gera um link único com `drush uli --uri=<host de conta> --uid=<uid>`. Sessão da Conta vale também para o principal e a loja se o cookie de domínio for compartilhado; confirmar sempre pelo estado da página, não supor.

## D) Limites do agente de navegador

- Não cria contas e não digita senhas em hosts que não sejam locais. Quem faz o login é o Felipe, ou o agente do repositório cria a conta de teste.
- Não digita número de cartão nem CPF. Para na primeira tela do Mercado Pago, e o Felipe assume.
- Dados fictícios de nome e endereço são permitidos.
- Mudanças que enfraquecem segurança (por exemplo, confirmar e-mail do admin direto no banco) podem ser bloqueadas e exigem decisão do Felipe.
- `resize_window` não funciona nesta ferramenta. O teste de largura de 390 px (T10) é manual.
- Abas podem ser fechadas por outra sessão. Reabrir a URL e conferir o estado antes de concluir um passo.

## E) Dicas de operação

- **Select de tamanho**: alterar clicando no select, seta para baixo e Enter. `form_input` não dispara o AJAX de variação, e o item entra como P (o primeiro tamanho).
- **Carrossel de imagens do produto**: muda quando o AJAX de variação dispara. Se não mudar, conferir se o select foi alterado pelo teclado ou pelo clique.
- **Mensagem de erro do checkout**: fica no bloco "Mensagem de erro" da página. Copiar o texto exato no relato.
- **Pedido de teste**: anotar o número do pedido (`/checkout/{n}/...`) em cada relato.

## F) Resultado desta rodada (2026-10-11)

- **F3 (busca):** T1 a T9 OK (PR #211, reportado pelo Felipe).
- **Pedido 4 (admin):** bloqueado pela política de e-mail não confirmado. Descartado pelo Felipe (carrinho esvaziado pelo navegador).
- **Reteste com `teste.comum`:**
  1. Aceite do acordo em `/aceite-termos`: OK; retorno para `https://conta.aculta.toca.net.br/` com a visão geral.
  2. `/conta-interna` (logado): redireciona para `/` (regra geral de caminho inexistente da Conta). Decisão pendente.
  3. **Falha:** ao abrir o produto na loja, o agente foi mandado para `/aceite-termos` no host da loja e pediu aceite de novo. Após aceitar, voltou para `/loja-da-aculta` em vez do produto pedido. Causa: destino fixo do acordo (corrigido em 0.3.0-dev.14: `settings.destination` vazio). O aceite repetido não foi reproduzido por HTTP com sessão logada; registrar nova reprodução se ocorrer.
  4. Produto: R$ 30,00; tamanho M selecionado por clique no select; "M adicionado(a) ao seu carrinho": OK.
  5. Carrinho `/carrinho`: 1 item, M, R$ 30,00: OK. **Falha:** a coluna Item mostra só preço e tamanho, sem o nome do produto (corrigido em 0.3.0-dev.15 com o display `cart` do bundle `camiseta_tamanho`; a confirmação visual está pendente).
  6. "Fechar pedido" criou o pedido 5. `/checkout/5/order_information` mostrava só o resumo, sem campos de nome, endereço ou cobrança. **Causa:** `billing_countries` da loja vazio, `billing_information` removido pelo Commerce Payment, e o Mercado Pago exige perfil de cobrança com endereço (ver `CHECKOUT-BILLING.md`).
  7. `/checkout/5/review`: contato `teste.comum@example.invalid`, pagamento Mercado Pago, total R$ 30,00: OK.
  8. **Falha:** "Pagar e concluir a compra" levou a `/checkout/5/payment` com "Billing information is required to proceed.". Sem redirecionamento ao Mercado Pago. Console sem erros.

## G) Decisões do Felipe

1. **Endereço com CEP no checkout**: para quem não tem endereço cadastrado, o passo de dados do pedido mostra o endereço com o autocomplete por CEP de "Meus endereços", sem abas e só o endereço. O perfil salvo vira o perfil de cobrança exigido pelo Mercado Pago. Implementado pelo pane `aculta_billing_address` (`src/Plugin/Commerce/CheckoutPane/BillingAddressPane.php`), com a biblioteca `cep_autocomplete/viacep`.
2. **Instalação de módulos do Commerce**: autorizada (contrib via Composer, registrada em `config/sync`, CHANGELOG e documentação). Nenhum módulo foi instalado nesta rodada.
3. **CPF/CNPJ**: estudar um módulo que combine com as informações fiscais do Commerce (tax number no perfil de cobrança, `commerce_tax`), comparando opções contrib com módulo próprio: validação de dígitos, máscara, armazenamento e envio ao Mercado Pago (`payer.identification`). Recomendação antes de implementar. **Pendente.**
4. **Pedido 5**: pode ser reaproveitado.
5. **Traduções** "Payment" e "Quantity": feitas.

## H) Mudanças do agente do repositório nesta rodada

| Commit / estado | Versão | O que mudou | Causa |
| --- | --- | --- | --- |
| `4d488b4` (publicado) | 0.3.0-dev.12 | Checkout e cursos com a regra original + e-mail confirmado (`ProtectedRouteAccessCheck`); cadastro de pagamento só com gateway que guarda cartões (`PaymentMethodAddAccessCheck`); rótulos de endereço em pt-br (`translations/address.pt-br.po`); destino do aceite | Regra de checkout substituída por e-mail confirmado (risco de acesso a pedido alheio); botão de pagamento sem gateway; rótulos em inglês |
| `5e7b3cf` (publicado) | 0.3.0-dev.13 | Visão geral em `/minha-conta` (sem `/conta-interna`); gate de conta por caminho exato; docs atualizadas | `<front>` da Conta era `/conta-interna`; a rota não pode usar `/` porque o Core reserva `/` para `<front>` |
| `0e8f00e` (publicado) | 0.3.0-dev.14 | Documentação do incidente (`CHECKOUT-BILLING.md`); `collect_billing_information` do Mercado Pago; acordo sem destino fixo | Erro de cobrança no pedido 5; destino fixo levava à home da loja |
| Pendente de commit | 0.3.0-dev.15 | Pane `aculta_billing_address` no fluxo `default`; `billing_information` trocado no `config/sync`; flag do Mercado Pago de volta a `false`; traduções "Payment" e "Quantity"; display `cart` do bundle `camiseta_tamanho` | Passo de endereço com CEP (G1); nome do produto no carrinho (F5); tradução (F8) |

Estado no runtime (servidor de testes):

- Loja 1: `billing_countries = [BR]` (dado de entidade; não está em `config/sync`. Replicar em produção).
- Pane `aculta_billing_address` registrado e presente em `order_information`; página do pedido 5 renderiza o campo `postal_code` e carrega `cep_autocomplete/viacep`.
- Não verificado ainda: envio do passo (gravação do perfil de cobrança) e a passagem para o Mercado Pago.

## I) Pendências abertas

- Confirmar por navegador: preenchimento do endereço, revisão, pagamento até o Mercado Pago (pedido 5 ou novo).
- Confirmar por navegador: nome do produto na coluna Item do carrinho.
- Decidir: `/conta-interna` logado (302 para `/` ou 404).
- CPF/CNPJ: estudo e recomendação (G3).
- Reprodução do aceite repetido no host da loja, se voltar a ocorrer.
- Limpeza do usuário `teste.comum` e dos pedidos de teste ao final.

## J) Teste do popup de aceite dos termos (0.3.0-dev.16)

Pré-requisito: usuário `teste.comum` com o aceite **pendente** (o agente do repositório remove o registro do acordo; não é o cadastro pelo formulário).

1. **Página pública não mostra o popup:** abrir `https://loja.aculta.toca.net.br/loja-da-aculta` logado como `teste.comum`. Esperado: página normal, sem popup.
2. **Página restrita com popup:** abrir `https://aculta.toca.net.br/carrinho`. Esperado: a página carrega na mesma URL, com o popup "Aceite dos termos" por cima e o fundo desfocado. O popup é o bloqueio; a página não é negada.
3. **Popup não fechável:** com o popup aberto, testar: Esc, clique fora da caixa, Tab (o foco deve ficar dentro). Esperado: o popup continua aberto. Não existe botão de fechar.
4. **Links sem perder o popup:** clicar em "Termos de Uso" e em "Política de Privacidade". Esperado: abrem em nova aba; o popup continua na aba original.
5. **Aceite:** marcar o checkbox e clicar em "Aceitar e continuar". Esperado: a mesma URL (`/carrinho`) recarrega liberada, com o carrinho.
6. **Aceite vale em outros hosts:** abrir `https://loja.aculta.toca.net.br/loja-da-aculta` e `https://conta.aculta.toca.net.br/`. Esperado: sem popup.
7. **Cadastro pelo formulário não vê o popup:** um usuário criado pelo formulário de cadastro (checkbox marcado) deve entrar e navegar sem popup. Esse teste exige criar conta; o agente do repositório cria a conta de teste.
8. **Conteúdo não entregue:** em `/carrinho` com o popup, o navegador não deve mostrar itens nem valores do carrinho.

Pendências desta rodada: a camada visual atrás do popup (cabeçalho) e o aceite pelo navegador com JavaScript (passos 3 e 5).

**Mercado Pago:** não clicar em "Pagar" em nenhuma tela do Mercado Pago enquanto o token de teste não estiver corrigido (ver CHECKOUT-BILLING.md). O gateway está desligado.
