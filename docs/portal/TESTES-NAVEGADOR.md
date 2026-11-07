# Testes interativos no navegador (Portal, loja e checkout)

Regras e roteiro de execução. Não registre rodadas aqui: o histórico datado (seções F a V, até 2026-11-05) está em `docs/history/portal/TESTES-NAVEGADOR-RODADAS.md`. A a E mudam só se o fluxo ou os hosts mudarem.

## A) Fluxo de trabalho

Dois agentes e uma pessoa responsável (Felipe):

- **Agente do repositório** (Claude Code, neste repositório): altera código e configuração, roda gates, publica no servidor de desenvolvimento e diz o **passo exato** a executar.
- **Agente de navegador** (Claude in Chrome / Cowork): executa o passo no navegador logado na conta de teste e reporta **etapa, URL, pedido, erro e requisição** (com o texto da mensagem, se houver). Não corrige nada.
- **Felipe**: faz o login quando o agente de navegador não pode digitar senha, digita cartão e CPF, e decide o que é mudança de segurança.

Regra: o agente do repositório só afirma que algo funciona depois de verificar por HTTP ou API. Depois que o navegador confirma, o resultado vai para a seção F.

## B) Hosts e URLs (servidor de desenvolvimento)

Os hosts do servidor de desenvolvimento são `*.aculta.toca.net.br`. Não há host de produção ainda; os testes rodam só nesses hosts.

| Host | Papel | Caminhos principais |
| --- | --- | --- |
| `aculta.toca.net.br` | Principal (MAIN). Carrinho e checkout pertencem sempre a este host. | `/carrinho`, `/checkout/{pedido}/order_information`, `/checkout/{pedido}/review`, `/checkout/{pedido}/payment` |
| `conta.aculta.toca.net.br` | Conta (ACCOUNT). Raiz `/` é a visão geral (logado) ou o login (anônimo). | `/` (visão geral), `/entrar`, `/dados`, `/dados/endereco`, `/pedidos`, `/aceite-termos` |
| `loja.aculta.toca.net.br` | Loja (SHOP). | `/loja-da-aculta`, produto `/loja/produto/bloco-sativa/camiseta-abada-bloco-sativa-2026-regata` (ou `/product/1`, que redireciona 301) |

Caminhos que **não** existem ou não são públicos:

- `/cart`: não existe. O carrinho é `/carrinho`.
- `/conta-interna`: removido em 0.3.0-dev.13. Visitante recebe 404; logado é levado para `/` pela regra geral de caminho inexistente da Conta (decisão pendente, ver F).
- `/minha-conta`: caminho técnico interno da visão geral (rota `aculta_portal.dashboard`). Direto responde 404. Não usar como link.

O domínio de Conta usa a coleção de configuração `domain.conta_aculta_org`, que define o `front` (`/minha-conta`). É isso que faz `/` servir a visão geral.

## C) Contas

- **Admin `piradopirata`**: uid 1, papéis `administrator` e `authenticated`. O e-mail não está confirmado. Decisão do responsável em 2026-10-27: checkout é exceção e não exige essa confirmação; continuam valendo propriedade do pedido, permissões Commerce e aceite dos termos. Não marcar e-mail confirmado diretamente no banco.
- **Usuário de teste `teste.comum`** (uid 5): e-mail `teste.comum@example.invalid` (domínio reservado, não recebe mensagens). Papéis observados no Runtime de teste em 2026-10-21: `authenticated`, `email_confirmed`, `gerente_loja`, `content_editor`, `lms_teacher` e `estudante`. Sem papel administrativo (`administrator`); as rotas `/painel-administrativo/*` respondem 403 para esta conta. Os papéis extras vieram de testes de LMS e de Revista e são mantidos como estão até decisão de conta (ver DT-K01 no `docs/operations/DEBT-REGISTER.md`). Termos aceitos e confirmação de e-mail registrados. Criado pelo agente do repositório no banco de testes.
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


## F) Registro de rodadas

Cada rodada de navegador é registrada em `docs/history/portal/TESTES-NAVEGADOR-RODADAS.md`, em seção nova com a data. Registre etapa, URL, resultado e, se houver, o passo que falhou. Não copie resultados para este arquivo.
