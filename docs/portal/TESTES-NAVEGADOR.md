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
| `conta.aculta.toca.net.br` | Conta (ACCOUNT). Raiz `/` é a visão geral (logado) ou o login (anônimo). | `/` (visão geral), `/entrar`, `/dados`, `/dados/endereco`, `/pedidos`, `/aceite-termos` |
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
2. **Página restrita com popup:** abrir `https://conta.aculta.toca.net.br/dados` logado (e também `/node/add/forum` ou a edição de um verbete do wiki, quando houver conteúdo de teste nesses hosts). Esperado: a página carrega na mesma URL, com o popup "Aceite dos termos" por cima e o fundo desfocado. O popup é o bloqueio. O carrinho não tem popup (é público).
3. **Popup não fechável:** com o popup aberto, testar: Esc, clique fora da caixa, Tab (o foco deve ficar dentro). Esperado: o popup continua aberto. Não existe botão de fechar.
4. **Links sem perder o popup:** clicar em "Termos de Uso" e em "Política de Privacidade". Esperado: abrem em nova aba; o popup continua na aba original.
5. **Aceite:** marcar o checkbox e clicar em "Aceitar e continuar". Esperado: a mesma URL (`/carrinho`) recarrega liberada, com o carrinho.
6. **Aceite vale em outros hosts:** abrir `https://loja.aculta.toca.net.br/loja-da-aculta` e `https://conta.aculta.toca.net.br/`. Esperado: sem popup.
7. **Cadastro pelo formulário não vê o popup:** um usuário criado pelo formulário de cadastro (checkbox marcado) deve entrar e navegar sem popup. Esse teste exige criar conta; o agente do repositório cria a conta de teste.
8. **Conteúdo não entregue:** em `/carrinho` com o popup, o navegador não deve mostrar itens nem valores do carrinho.

Pendências desta rodada: a camada visual atrás do popup (cabeçalho) e o aceite pelo navegador com JavaScript (passos 3 e 5).

**Mercado Pago (compra simulada):** o gateway está ligado com token de conta de teste (`test_user`). Antes de pagar, o navegador deve estar **deslogado** do Mercado Pago ou logado no comprador de teste (`TESTUSER…`). Nunca usar a conta real do responsável. Se o domínio exibido não for o de teste, parar e avisar.

## K) Checkout: resumo, número e CEP (0.3.0-dev.16)

Pré-requisito: `teste.comum` com aceite dado (o popup não bloqueia o checkout depois do aceite), e gateway de pagamento ligado com token de teste.

1. Abra `https://aculta.toca.net.br/checkout/5/order_information`. Esperado: bloco "Endereço de cobrança" com CEP, Logradouro, Complemento, Bairro, Cidade, Estado, País, campo **Número** (obrigatório) e rótulo "Empresa" (no lugar de "Company").
2. Digite o CEP `01310100`. Esperado: aparece **01310-100** (máscara), e a rua, o bairro, a cidade e o estado são preenchidos.
3. Deixe o Número vazio e clique em "Continuar para a revisão". Esperado: erro pedindo o número, sem avançar.
4. Digite `1000` no Número e continue. Esperado: a revisão mostra o logradouro "Avenida Paulista, 1000".
5. No resumo do pedido, o item deve mostrar o nome "Camiseta Abadá Bloco Sativa 2026 – Regata", o tamanho M e o preço R$ 30,00.

**Gateway:** ligado. Se a etapa mostrar "Não existem gateways de pagamento disponíveis", o token de teste não está sendo lido: avisar antes de continuar.


## L. Migração módulo x tema (2026-10-12)

Objetivo: confirmar que a migração de design para o ACULTA420 não mudou a Minha conta e que o módulo continua
funcionando sem o tema (fallback).

- **Marcação da Minha conta (HTTP, sem navegador):** 8 páginas (Minha conta, Dados, Endereço, Conexões, Segurança,
  Meus cursos, Configurações, Meu apoio) capturadas antes e depois da migração. Após normalizar as diferenças
  intencionais (`data-portal-account-*` acrescentados, `is-active`→`aria-current`, `is-enhanced`→atributo) e os valores
  de runtime, **8 de 8 idênticas**.
- **Roteiro de aceitação** (CEP por XHR, Minha conta, Dados > Endereço com Número/CEP, checkout informações e revisão
  com endereço único e pagamento só Mercado Pago, adicionar ao carrinho por AJAX com caixa e contador): **10/10 com
  ACULTA420** e **10/10 com Olivero** (tema padrão trocado temporariamente; o shell veio do fallback do módulo, sem
  classes de design). Tema padrão restaurado para `aculta420` e Olivero desinstalado.
- **Rodada 2 (2026-10-12, migração de design para o ACULTA420):** 9 páginas da conta e 3 do checkout/loja comparadas
  com a linha de base (antes da migração), após normalização de ordem de atributos e classes e de valores de runtime:
  **12 de 12 idênticas**. Roteiro de aceitação: 10/10 com ACULTA420 e 10/10 com Olivero como tema padrão.
- **Não executado:** captura de tela no navegador. O ambiente não tem ferramenta de navegador conectada; a comparação
  acima é de marcação. Falta a conferência visual humana na Minha conta.
- Efeito colateral dos testes: o carrinho do teste.comum recebeu itens de teste (pedidos de rascunho 5, 7 e 8). Limpar
  depois dos testes.

## M. Página LINKS — inspeção visual mobile (2026-10-12)

- Atualização posterior: CTA “Visite nosso Site” aponta para a raiz MAIN; Instagram segue no rodapé. O ajuste de
  espaçamento vertical reduz as margens externas e o preenchimento do pattern. HTTP 200 e o destino foram conferidos;
  a captura visual após compactar o espaçamento ficou pendente.

- **Host:** `https://links.aculta.toca.net.br/`, acesso anônimo. Navegador Chromium headless local com viewport de
  390 × 900 CSS px; não houve sessão com o responsável nem uso de conta.
- **Claro:** perfil com logo existente do tema, canal Instagram, card de projeto, controles/dots do VVJB, barra
  institucional e rodapé próprio visíveis; cabeçalho e rodapé padrão ausentes.
- **Escuro:** mesmo DOM e geometria. O logo e o ícone Instagram ficaram claros; superfícies, texto e controles
  acompanharam os tokens escuros do ACULTA420.
- **Projetos:** a View existente apresentou quatro cards `project` publicados. O carrossel mostrou um card por vez,
  com controles anterior/próximo, pausa e quatro indicadores.
- **Limites:** inspeção de render visual; não incluiu autenticação administrativa, envio do formulário, interação
  por toque físico ou teclado no navegador. A rota administrativa foi verificada por Drupal API/Form Builder e
  responde 403 a acesso anônimo.

## N. Recorte de foto e envio (aculta_user, rodada de 2026-10-13)

Objetivo: confirmar no navegador o fluxo de foto do perfil: envio, recorte em popup próprio, retorno ao popup da foto
e salvamento. Antes desta rodada, o popup de recorte não abria ao escolher o arquivo.

### Pré-condições
- Conta de teste: `teste.comum` (uid 5), como na seção C. O login é feito pelo agente do repositório, com link único
  gerado por `drush uli --uid=5 --uri=https://conta.aculta.toca.net.br` (não colar o link neste documento).
- Imagem de teste: `Bloco Sativa 2026 - Capa 2.png`, que está no computador do Windows do Felipe (pasta Downloads).
  O caminho típico é `C:\Users\<usuário do Windows>\Downloads\Bloco Sativa 2026 - Capa 2.png`. Conferir o nome exato do usuário.
- Tamanho: o campo aceita no máximo **2 MB**. Antes do teste, conferir o tamanho da imagem no Windows (propriedades do arquivo). Acima disso, o envio é recusado e o popup mostra a mensagem de tamanho.
- Limite: página não consegue escolher um arquivo local sozinha. Se o agente não puder anexar arquivo pelo navegador,
  o Felipe escolhe a imagem no seletor e o agente segue a partir do passo 3.

### Roteiro
1. Abrir `https://conta.aculta.toca.net.br/` logado como `teste.comum`. Esperado: painel "Minha conta", com o avatar
   (foto do acervo ou uma foto já salva).
2. Clicar no avatar. Esperado: popup "Alterar foto do perfil", com a visualização, o botão "Sortear outro avatar" e o campo de arquivo "Foto".
3. Escolher a imagem `Bloco Sativa 2026 - Capa 2.png` no campo "Foto". Esperado: o envio termina e a visualização mostra a imagem nova.
   **Esperado principal:** o modal "Recortar foto" abre sozinho. **Se não abrir:** registrar isso e clicar em
   "Recortar foto" dentro do popup. Anotar o que aparece na tela e no console do navegador.
4. No modal de recorte, ajustar o quadrado sobre a imagem e clicar em "Aplicar recorte". Esperado: a página volta ao
   painel e o popup "Alterar foto do perfil" reabre sozinho, com a imagem recortada na visualização.
5. Clicar em "Salvar foto do perfil". Esperado: mensagem "Foto do perfil salva." e o avatar do painel mostra a imagem recortada.
6. Reabrir o popup e clicar em "Sortear outro avatar". Esperado: só a imagem do avatar muda; a URL não muda e a página
   não recarrega (conferir pelo histórico do navegador ou por um marcador no DOM).
7. Remover a foto personalizada (botão "Remover", disponível só para foto personalizada) e salvar. Esperado: o avatar
   volta a ser um avatar do acervo sorteado.
8. Em todos os passos, anotar mensagens de erro na tela e no console (filtro "Erro").

### Registro do resultado
| Passo | Esperado | Resultado (OK / falhou) | Observação e mensagem de erro |
| --- | --- | --- | --- |
| 1 | painel com avatar | | |
| 2 | popup com visualização e botões | | |
| 3 | modal de recorte abre sozinho ao escolher o arquivo | | |
| 4 | recorte aplicado, volta ao popup da foto | | |
| 5 | foto salva e exibida | | |
| 6 | sorteio sem recarregar a página | | |
| 7 | remoção volta ao avatar do acervo | | |

Não encerrar a rodada sem registrar o resultado de cada passo. Nenhum dado de conta ou senha deve ser colado aqui.
