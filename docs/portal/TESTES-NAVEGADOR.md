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
2. Clicar no avatar. Esperado: popup "Alterar foto do perfil", com a visualização, o botão "Sortear novo avatar" e o campo de arquivo "Foto".
3. Escolher a imagem `Bloco Sativa 2026 - Capa 2.png` no campo "Foto". Esperado: o envio termina e a visualização mostra a imagem nova.
   **Esperado principal:** o modal "Recortar foto" abre sozinho. **Se não abrir:** registrar isso e clicar em
   "Recortar foto" dentro do popup. Anotar o que aparece na tela e no console do navegador.
4. No modal de recorte, ajustar o quadrado sobre a imagem e clicar em "Aplicar recorte". Esperado: a página volta ao
   painel e o popup "Alterar foto do perfil" reabre sozinho, com a imagem recortada na visualização.
5. Clicar em "Salvar foto do perfil". Esperado: mensagem "Foto do perfil salva." e o avatar do painel mostra a imagem recortada.
6. Reabrir o popup e clicar em "Sortear novo avatar". Esperado: só a imagem do avatar muda; a URL não muda e a página
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

## P. Foto de perfil: regras por estado (aculta_user, 2026-10-13)

Regras e tabela de estados em `web/modules/custom/aculta_portal/modules/aculta_user/docs/FOTO-PERFIL.md`. Gate estático:
`php scripts/validate-aculta-user-photo.php`. Executar este roteiro após qualquer alteração na foto.

- **P1 — avatar do sistema (conta de teste em acervo):** abrir o popup da foto no avatar do painel.
  Esperado: prévia do avatar do acervo, campo "Escolha uma imagem" vazio, botão "Sortear novo avatar" visível,
  **sem** "Remover" e **sem** "Recortar foto".
- **P2 — envio no acervo:** enviar uma imagem PNG/JPG (até 2 MB).
  Esperado: o recorte abre sozinho (modal "Recortar foto", caixa de recorte direta, sem abas "Crop image" nem
  nome do tipo, com barra de rolagem se passar de 560 px). Ao aplicar, a foto da conta é trocada e o painel recarrega
  **uma vez**, sem reabrir o popup e sem repetir requisições a `/avatar/foto` (INV-FOTO-6). Não há "Remover" no
  arquivo enviado.
- **P3 — foto personalizada salva:** a foto recortada já é a foto da conta (aplicar o recorte salva).
  Esperado: painel mostra a foto própria. Abrir o popup: campo com a foto e botão **"Remover"**; **sem** "Sortear
  outro avatar"; **sem** "Recortar foto".
- **P4 — remover:** no popup da foto personalizada, clicar em "Remover foto" (sem "Salvar").
  Esperado: o painel recarrega com um avatar sorteado do acervo; reabrir o popup mostra o estado P1 (campo "Escolha
  uma imagem", "Sortear novo avatar", sem "Remover foto"). Nenhum nome ou link de arquivo aparece no popup (INV-FOTO-7).
- **P5 — recorte só após envio:** com foto personalizada, não há como recortar sem enviar de novo.
  Esperado: nenhum link de recorte no popup; enviar a imagem de novo é o único caminho.
- **P6 — sortear com foto personalizada:** o link "Sortear novo avatar" não aparece (P3). Se a rota
  `/avatar/sortear` for chamada mesmo assim, ela troca a foto (rota técnica; a regra de exibição é a da tela).

- **P7 — sortear só na prévia (INV-FOTO-8):** em acervo, clicar "Sortear novo avatar" no popup.
  Esperado: só a prévia dentro do modal muda; o avatar da visão geral **não** muda. Fechar o modal sem salvar mantém a
  foto atual; reabrir o modal mostra a foto atual. Sortear de novo e clicar "Salvar foto do perfil" aplica a escolha:
  o painel recarrega e o avatar da visão geral mostra a nova foto.

Verificação por HTTP (2026-10-13, após INV-FOTO-7): foto personalizada → popup com "Remover foto", sem sortear, sem
link de arquivo; "Remover foto" → 303 para o painel e foto do acervo; arquivo personalizado apagado.

Verificação automatizada (Chromium headless via DevTools, 2026-10-13): após "Aplicar recorte", um único
`POST /avatar/recortar`, navegação para o painel, nenhuma repetição de `POST /avatar/foto`, e a foto da conta vira o
arquivo enviado (permanente). Resultado anterior por HTTP: verificado em P1 (acervo: sem remover, com sortear, com campo de envio, sem
recortar), P3 (personalizada: com remover, sem sortear, sem recortar) e recusa do recorte de foto já salva ("Envie
uma foto própria para recortar."). **Não executado:** P2, P4 e P5 no navegador (envio por AJAX e modal).

## O. Login: redirecionamento após autenticar (2026-10-13)

- **Sintoma:** após login correto em `https://conta.aculta.toca.net.br/entrar`, o usuário permanecia no formulário de
  login, mesmo com a sessão criada.
- **Causa verificada:** o formulário de `/entrar` estava na regra AJAX de salvamento da Minha conta
  (`FormHooks::accountAjaxSubmit`). O envio por AJAX devolvia o próprio formulário, e `disableAccountRedirect` cancelava
  o redirecionamento do Core (`UserLoginForm::submitForm`). A validação e a sessão estavam corretas.
- **Correção:** `user_login_form` fica fora da regra AJAX; o login volta a ser um POST comum com redirecionamento 303.
- **Roteiro de regressão (servidor de testes):**
  1. Abrir `/entrar`, informar a conta de teste e a senha correta, e enviar.
  2. Esperado: a página sai de `/entrar` e vai para a área logada (`https://conta.aculta.toca.net.br/`); o menu da
     conta aparece.
  3. Senha errada: esperado permanecer em `/entrar` com a mensagem "Nome de usuário ou senha não reconhecidos.".
  4. HTTP de referência: POST com senha correta → `303 See Other`, `Location` para `/?check_logged_in=1`; POST com
     senha errada → `200` com a mensagem.
- **Política de redirecionamento pós-login** (`DomainPurposeRequestSubscriber::onResponse`, respostas de `user.login`):
  5. Entrada direta em `https://conta.aculta.toca.net.br/entrar` → `303` para `https://conta.aculta.toca.net.br/`.
  6. Vindo do link "Entrar" da página principal (`?destination=/&aculta_destination_purpose=main`) → `303` para
     `https://aculta.toca.net.br/`.
  7. Destino da conta (`?destination=/configuracoes/perfil&aculta_destination_purpose=account`) → `303` para
     `https://conta.aculta.toca.net.br/configuracoes/perfil`.
  8. Em cada caso, o `Location` recebe `?check_logged_in=1` (Core, `Authentication/Provider/Cookie.php`) e o destino
     responde `200` com sessão. Verificado por HTTP em 2026-10-13.
- **Não repetir:** qualquer formulário de autenticação ou de sessão (login, cadastro com confirmação, senha) não deve
  entrar em `accountAjaxSubmit`; a regra existe só para salvar dados da Minha conta sem recarregar a página.

## S. ACULTA Messenger com Olivero (2026-10-14)

Escopo executado no diretório local com Apache/HTTPS e resolução de hosts para `127.0.0.1`; isso simula os hosts do teste, não é o servidor Homelab. Olivero foi instalado/ativado temporariamente, definido como tema padrão, e depois removido; `system.theme:default` foi restaurado para `aculta420` e o cache reconstruído.

- HTTP anônimo com Olivero: home MAIN `200`; modal de avisos `/avisos/resumo` `200`; modal de mensagens `/mensagens/resumo` `403`, conforme a exigência de autenticação/permissões.
- Com a conta documentada `teste.comum` (uid 5), o link único do Drush direcionou ao formulário de redefinição de senha one-time; não foi submetida alteração de senha e não se obteve sessão HTTP autenticada. Não declarar os fluxos autenticados como aprovados por navegador.
- Teste local de renderização Drupal com a conta UID 5 passada ao `AccountProxy`: `overview`, `preview`, `alertsPreview`, `notices` e `notifications` foram construídos/renderizados pelo Render API sob o tema padrão temporário Olivero; respectivas saídas renderizadas: 194, 207, 102, 77 e 71 bytes. Nenhum formulário de envio foi submetido e nenhum e-mail foi enviado.
- Pós-teste: Drupal bootstrap Successful, banco conectado, tema padrão ACULTA420 restaurado, Olivero desinstalado. Validadores: fronteira módulo/tema PASS (973), schemas SDC PASS (24 componentes/365 checks), Portal Drupal 11+ PASS (768 checks).
- **Pendente para homologação completa:** sessão humana autenticada no navegador para abrir os modais, navegar inbox/thread e verificar formulários, leitura, responsividade e acessibilidade. O teste HTTP autenticado não foi concluído; não marcar o Messenger como integralmente homologado em outro tema.

## Q. Minha Conta > Atividade em abas (aculta_user 0.3.0-dev.8, 2026-10-13)

- **Q1:** abrir Minha Conta > Atividade. Esperado: abas Wiki, Fórum, Revista, Comentários e Conquistas no mesmo estilo de
  Meus Dados; a aba atual marcada; título da aba como cabeçalho da área de conteúdo.
- **Q2:** trocar de aba pelos links. Esperado: cada aba tem URL própria (`/atividade/forum`, etc.); `/atividade` é a aba Wiki.
- Verificação (2026-10-13): HTTP 200 em todas as abas com a estrutura de abas; captura no Chromium da aba Fórum.

## R. Popup "Entrar" da barra e fundo dos modais (aculta_portal 0.4.0-dev.14)

RUNTIME STATUS: DEFERRED. Ainda não executado.

1. Sem sessão, em MAIN, clique em "Entrar" na barra. Esperado: abre o modal verde do anexo, sem captcha; fundo escurecido.
2. Sem sessão, em `conta.` e em `loja.`, repita o passo 1. Esperado: o modal abre no próprio host.
3. Preencha conta válida e Entrar. Esperado: o modal fecha e a página atual é recarregada, já autenticada.
4. Senha errada. Esperado: mensagem no próprio modal; o modal continua aberto.
5. Abra `/entrar` direto. Esperado: página de login com o desafio do Turnstile (INV-TS-1).
6. Abra um modal existente (busca, Editar senha/segredo, termos). Esperado: o fundo escurece da mesma forma (INV-MODAL-1).
7. Repita 1 e 6 com outro tema ativo (Olivero). Esperado: o login funciona e o modal abre, sem ACULTA420.

## O. Fase 10 da ACULTA Gallery (rodadas de 2026-10-14 e 2026-10-15)

Ambiente: servidor de testes (`*.aculta.toca.net.br`), Firefox por BiDi com ferramentas internas (scripts do agente). Hosts: Revista `revista420.aculta.toca.net.br`, principal `aculta.toca.net.br`. Login de administrador por link de uso único (`drush user:login`, conta do responsável). Dados de teste criados e removidos ao fim de cada rodada.

| Passo | Resultado | Evidência |
| --- | --- | --- |
| Visualização do álbum na Revista (anônimo) | PASS (200) | HTTP |
| Álbum no host principal (anônimo) | PASS (403, regra D13) | HTTP |
| Coleção administrativa: título aponta para a Revista | PASS | HTML do painel |
| Edição e exclusão no host principal | PASS (política administrativa) | HTML do painel |
| Edição de galeria: administrador cria e edita; editor e conta comum não | PASS | `access()` por conta |
| Palco: 66 slides, rotação, teclado no carrossel, toque, sem erros de JavaScript | PASS | BiDi |
| Lightbox: abre, seta, Esc (antes: `1 / 1`) | PASS, corrigido em PR #274 | BiDi |
| Diálogo "Criar galeria" pela URL (administrador, Revista e principal) | PASS | BiDi |
| Diálogo: upload de 2 fotos e "Criar e inserir" | PASS (galeria com 2 imagens) | banco |
| Botão "Criar galeria" na barra do editor, por clique do mouse | PASS após PR #279 (antes: o balão de dica de "Exibir mais itens" cobria o item e interceptava o clique) | BiDi |
| Imagem editorial pela biblioteca ("Adicionar mídia") | PASS após PR #278 (antes: grupo "Imagem" fechado; o campo seguinte cobria o botão) | BiDi |
| Salvar artigo com todos os campos obrigatórios e corpo com galeria incorporada | PASS: artigo salvo na Revista (`/noticias/...`) | BiDi + banco |
| Renderização do artigo na Revista (visitante) | PASS: HTTP 200; 2 fotos no lightbox; nenhuma incorporação sem processar | HTTP |

Rodada 2 (2026-10-15, após o merge de PR #278 e PR #279; configuração do editor sincronizada com a `main`):
- Fluxo completo sem defeitos: "Adicionar mídia" pela biblioteca (clique acerta o alvo), "Criar galeria" pela barra do editor (clique acerta o botão), envio de duas fotos, "Criar e inserir", salvamento do artigo na Revista (`/noticias/...`), renderização com HTTP 200, 3 âncoras de lightbox, nenhuma incorporação sem processar.
- Dados de teste removidos (2 artigos, 1 galeria e as mídias novas; a mídia 194 de antes da rodada foi mantida).
- Observação: o botão "Carregar fotos" do diálogo não foi localizado pelo teste; as prévias (2) apareceram e o envio foi concluído.
- `config:status`: o arquivo do editor da Revista na árvore servida estava antigo (sem o separador da barra); foi atualizado para o da `main`. As demais divergências de configuração do ambiente são anteriores e fora destes PRs.

Causas dos defeitos encontrados e corrigidos:
- **Grupo "Imagem" fechado** (PR #278): o campo de imagem é obrigatório, mas o grupo começava fechado. Com o grupo fechado, o navegador não validava o campo (o envio parecia não fazer nada) e o botão "Adicionar mídia" ficava sob o campo seguinte. Correção: o grupo abre por padrão.
- **Balão de dica sobre o menu do editor** (PR #279): com a barra cheia, os botões de galeria iam para "Exibir mais itens"; o balão de dica desse botão ficava sobre o primeiro item do menu e interceptava o clique. Correção: quebra de linha na barra antes de "Galeria".
- **Campos obrigatórios** (sem defeito de código): resumo, categoria editorial e imagem editorial. O navegador e o servidor rejeitam o envio sem eles. O teste passou a preencher os três.

Observações:
- Rolagem: `scrollIntoView` não rolou a janela para alguns elementos; o teste passou a rolar o contêiner do elemento antes de medir o alvo do clique. Não é defeito do produto (rolagem manual funciona).
- Campo de upload do diálogo: o envio das fotos foi concluído (2 prévias), mas o botão "Carregar fotos" não foi localizado pelo teste na última rodada; o envio aconteceu sem ele.
- Ordem de itens na reordenação: não há alça de arrasto nesta versão; ver a Fase 10 sem reordenação.

Decisão pendente: com a rodada 2 sem defeitos abertos, a Fase 10 pode ser declarada `0.1.0-rc.1` pelo responsável. A versão do módulo continua `0.1.0-dev.12` até essa decisão.

## T. Iniciar nova conversa na barra de mensagens (aculta_messenger 0.1.1-dev.20, 2026-10-15)

### Pré-condições

- Host: servidor de testes `aculta.toca.net.br` (Homelab, runtime `aculta_runtime`).
- Conta: membro comum `teste.comum`, login por link único gerado com `drush uli`.
- Destinatário: `teste.destino.nova` (criado só para o teste).
- Navegador: Firefox ESR 153 headless, controlado pelo geckodriver 0.37.1 (protocolo WebDriver). O Chromium do host travou a aba nesta rodada e não foi usado.
- Larguras: 1280 px (desktop) e 390 px (celular).

### Roteiro

1. Abrir a barra de mensagens; a primeira ação da janela é o botão "Iniciar nova conversa".
2. Clicar no botão: a janela mostra "Nova conversa", o campo "Buscar membro pelo nome" e a dica de três caracteres.
3. Digitar `teste`: aparece o resultado `teste.destino.nova`, sem abrir a busca completa.
4. Escolher o membro: aparecem "Nova conversa com teste.destino.nova", a caixa de mensagem, Enviar, Cancelar e Voltar para o início.
5. Cancelar: volta à busca. Voltar para o início: volta à lista de conversas.
6. Escrever a mensagem e clicar em Enviar: a janela mostra a conversa com a mensagem enviada.

### Registro do resultado

- Passos 1 a 6 passaram nas duas larguras, com capturas de tela. Sem erros de JavaScript no console.
- Defeitos encontrados nesta rodada e corrigidos antes desta entrega:
  - O botão saía como texto puro: o mapeamento de tokens do tema não alcança links nem `html_tag` (são montados como string no pre-render do Core). Passou a ser um elemento de botão.
  - O campo de busca não recebia a classe do tema e o script de busca não era carregado na página. O campo passou a ser `#type => search`, e a biblioteca passou a ser anexada por `hook_page_attachments`.
  - O envio recarregava a página: o AJAX do Form API depende de `drupalSettings`, que o fragmento buscado não traz. O envio passou a ser feito por `fetch` na própria gaveta.
  - O envio falhava com `Call to a member function id() on int`: o Private Message espera entidades de usuário, não IDs.
  - Depois do envio, o Form API redirecionava com 303 para a própria página: o redirecionamento foi desativado, e a gaveta recebe a conversa.
  - Cancelar e Voltar para o início saíam sem os atributos `data-*`, e a gaveta carregava o fragmento como página inteira: os links passaram a ser elementos `#type => link` com atributos.
- Não testado nesta rodada: toque em dispositivo real, leitor de tela, membro com privacidade "somente amigos", envio com mensagem vazia e bloqueio entre membros.
- Observação de produto, não corrigida: a conversa aberta na gaveta mostra "Clear history" e "Member(s)" em inglês, vindos da visão nativa do Private Message. Já existia antes desta mudança.

## U. Revisão completa da barra de mensagens (aculta_messenger 0.1.1-dev.28, 2026-10-15)

### Pré-condições

- Host: servidor de testes `aculta.toca.net.br` (Homelab, runtime `aculta_runtime`).
- Contas: `teste.comum` (uid 5, confirmada) e `teste.b` (uid 31, criada para o teste). A suíte zera, no início, a privacidade, as amizades, os bloqueios e os contadores de envio das duas contas, e apaga as conversas entre elas.
- Privacidade "só amigos" de `teste.b` definida pelo `user.data` (o mesmo dado que o formulário de Configurações grava). Não passou pela interface de Configurações.
- Navegador: Firefox ESR 153 headless, geckodriver 0.37.1 (protocolo WebDriver). Chromium não usado (travou a aba).
- Larguras: 1280 px (desktop) e 390 px (celular).

### Roteiro (suíte de navegador, 52 verificações)

1. Nova conversa: busca com 2 caracteres (dica, sem resultados) e com 3 (resultado); composição; Cancelar; Voltar para o início; primeiro envio.
2. Barra da conversa: "← Todas as conversas" à esquerda e Opções à direita, na mesma linha; ordem do menu sem relação (Adicionar amigo, Limpar histórico, Bloquear); Limpar histórico em duas etapas.
3. Amizade nos dois lados: pedido (Cancelar pedido de amizade no menu de quem enviou); aceite e recusa (Aceitar, Rejeitar, Limpar histórico, Bloquear no menu de quem recebeu); desfazer.
4. Só amigos: aviso no lugar da caixa de resposta; link "clique aqui para enviar um pedido de Amizade."; depois do pedido, "você já enviou pedido de amizade, aguarde."; aceite pelo menu e volta da caixa.
5. Bloqueio nos dois lados: aviso de quem bloqueou (com o link "Clique aqui para desbloquear"); aviso de quem foi bloqueado (sem ação); conversa continua na lista; desbloqueio pelo menu e pelo link do aviso.
6. Resposta depois do desbloqueio, enviada pela gaveta.
7. Limpar histórico: primeira etapa pede confirmação; a segunda limpa só para quem confirmou, e o outro membro continua vendo as mensagens.
8. Celular (390 px): alinhamento da barra, rótulo "Opções" oculto (só o hambúrguer), menu abre, sem rolagem horizontal da página.
9. Erros de JavaScript no console: nenhum nas etapas verificadas.

### Registro do resultado

- 52 de 52 verificações passaram na última rodada, a partir do estado limpo.
- Defeitos encontrados pela suíte e corrigidos antes desta entrega:
  - Resposta enviada pela gaveta navegava para `/private-messages/…` (404): a resposta passou a ser enviada por `fetch` para a rota da gaveta, com as mesmas regras do compositor.
  - "Limpar histórico" apontava para uma rota do domínio da conta (404 no principal): virou ação em duas etapas dentro do menu.
  - Desbloquear pelo link do aviso não funcionava: o aviso e o menu tinham rótulos diferentes e o Form API não identificava o botão enviado.
  - Confirmação da limpeza não era identificada pelo mesmo motivo; a conversa limpa ainda aparecia na resposta por cache. A resposta depois da limpeza passou a ser a lista de conversas.
  - Depois de uma ação o menu fechava (e a confirmação da limpeza ficava escondida): agora fica aberto.
  - Primeira mensagem na etapa "Nova conversa" saía sem a barra de Opções.
  - Restrição "só amigos" não valia para conversas já existentes: agora vale na tela e na validação de envio.
- Limites desta rodada:
  - Sem toque em aparelho real e sem leitor de tela.
  - Conversas em grupo (mais de duas pessoas) não têm "Limpar histórico" no menu.
  - Recusa do envio por POST em conversa "só amigos" não foi exercitada diretamente (o formulário fica oculto nesse estado).
  - O aviso nativo do bloqueio ("User is unable to receive your message") segue em inglês; é configuração do contrib, sem exportação.

## V. Suíte versionada do Messenger e correções (aculta_messenger 0.1.1-dev.31, 2026-10-15)

### Pré-condições
- Runtime de teste (`aculta.toca.net.br`), geckodriver e Firefox headless.
- Preparação idempotente: `tests/browser/aculta-messenger/setup-accounts.php` (contas `teste.b` e `teste.c`, conversa em grupo). Executada pela própria suíte.

### Roteiro
Suíte em `tests/browser/aculta-messenger/suite.mjs`, 70 verificações: nova conversa, menu "Opções", "Limpar histórico" em duas etapas, amizade, "Desfazer amizade" e "Bloquear" em janela de confirmação, avisos de bloqueio e de "só amigos", layout em 390 px, caixa "Opções" do perfil, conversa em grupo (menu só com "Limpar histórico", limpeza só para quem confirma).

### Registro do resultado
- Execução 12 (final): **70 de 70**, sem erros de JavaScript.
- Antes dela, três defeitos reais foram corrigidos e reproduzidos isoladamente: (1) conversa em grupo absorvida pela caixa de um dos membros; (2) menu de exibição reprocessando o envio e carregando formulário em cache sem dependências; (3) limpeza de teste que não encontrava a conversa (consulta com duas condições no mesmo campo multivalorado; trocada pelo mapeador do Private Message).
- Limite: 390 px é aproximação de layout. Não houve teste em dispositivo de toque real nem com leitor de tela.


## Game — rodada de 2026-10-15 (G4)

- Runtime confirmado: `aculta_runtime`, hosts MAIN/ACCOUNT do mapeamento de testes.
- Conta existente `teste.comum`; admin para área restrita, visitante em sessão
  independente. Sem criação de contas/conteúdo ou concessão de conquistas.
- Firefox 8/8: entrada Visão geral → Conquistas e missões, AJAX, formulário,
  missões, mobile 390 px sem overflow, controles Game e ordem após Avisos.
- HTTP 17/17: POST de preferências, ocultação global/individual e destaque vistos
  por visitante; invalidação de cache; administração, controle de disponibilidade
  e política MAIN (GET canonicalizado, POST errado negado).
- Corrigido 500 anônimo em contagem de avisos/Flag (`a0afe29`).
- Preferências e disponibilidade da missão restauradas; screenshots/resultados
  em `.suite-out/game/`, fora do Git. Suítes Game versionadas em tests/browser e
  tests/runtime. Concorrência de concessões e leitor de tela não validados.

### Game — ampliação G5.1

- `tests/runtime/aculta-game/admin-smoke.py`: **34/34**, incluindo as 17 regressões
  anteriores, criação/edição via POST, critérios pedido/doação/manual, versão,
  concessão e revogação com motivos e histórico, imagem pública e motivo privado.
- Fixture própria removida, configurações restauradas. Duas concessões originais
  preservadas por digest das colunas anteriores ao update User 10003.
- URLs novas no MAIN: Game → Conquistas, Criar, Editar, Conceder ou revogar e
  Histórico. Perfil/conta mantêm as escolhas de exibição do Game.
- Três PNGs dos troféus servidos com hash original. Evento de pedido donation
  repetido em transação gerou uma concessão, com rollback integral do ensaio.
- Não valida concorrência, leitor de tela ou fluxo Mercado Pago ponta a ponta.

### Game — G5.2

- Firefox no Runtime do Homelab: **14/14**; catálogo, editor de imagem e condições,
  rota de confirmação, controles da conta, viewport móvel e posição no menu.
- Serviço de definições testado com imagem gerenciada temporária: confirmação
  removeu vínculo e arquivo sem outros usos; fixture limpa. Assets do módulo preservados.
- Gates Drupal 11, fronteira módulo/tema, endereços e slugs passaram. Drush cache
  rebuild concluído em `aculta_runtime`; sem `updb`, import/export ou cron.
- Aviso dispensável integrado à lista e contagem do Messenger; nova concessão
  enfileira e-mail com preferência individual. Transporte/entrega real de e-mail
  não verificado.

## PDF Viewer — CKEditor Media Embed e leitor compartilhado (2026-10-16)

- **Ambiente:** Firefox 153.4 ESR headless + geckodriver 0.37.1 no Homelab;
  Drupal 11.4.10, URI `https://revista420.aculta.toca.net.br`, banco
  `aculta_runtime`. A amostra foi o PDF público que acompanha PDF.js 6.4.299.
- **Autoria e render Drupal/Media:** com sessão uid 1 criada pelo link de login
  único no host da Conta, a edição do artigo abriu no host Magazine com CKEditor
  5. Na biblioteca, a aba “Documento” listou a Media PDF temporária; “Inserir
  Mídia” a colocou no corpo e “Salvar” publicou o artigo. A página pública
  respondeu HTTP 200 e exibiu o formatter, PDF.js local e link autorizado.
  O primeiro 403 ocorreu ao gerar o link de login no host Magazine; usar o host
  da Conta conforme o procedimento resolveu. A Media foi criada por Drush para
  o ensaio; o upload/criação de Media via UI por `content_editor` não foi
  homologado (o gate de permissões confirma `create document media`).
- **Interação real do viewer:** carregamento `Página 1 de 14`, canvas
  `655 × 848`, avanço `Página 2 de 14`, zoom `125%`, busca `Trace-based` com
  cinco resultados, botão de tela cheia ativado e console sem erros JS.
- **Limpeza:** removidos os nodes temporários 284 e 285, Media 231 e 232, Files
  407, 410, 411 e 412, além dos binários permanentes/temporários usados no teste.
  Drush confirmou zero Media `document` e ausência dos arquivos temporários.
  Nenhum conteúdo preexistente foi alterado.
- **Escopo pendente:** upload/criação de Media via UI como `content_editor`, leitura `private://`,
  preview/ImageMagick, CSP, acessibilidade visual/leitor de tela, outros
  browsers e hospedagem LiteSpeed.

## Imagens inline da Revista — revisão integrada (2026-10-16)

- **Ambiente:** servidor de testes, host `revista420.aculta.toca.net.br`, Firefox 153.4 ESR e geckodriver 0.37.1; Drupal 11.4.10, banco `aculta_runtime`.
- **Acesso:** Firefox/geckodriver em sessão privada sem autenticação. Fixture foi artigo temporário `published`, domínio `revista420_aculta_org`, quatro imagens com modelo e legenda. Todas as fixtures criadas nesta rodada foram removidas; conteúdo preexistente não foi alterado.
- **Desktop (1366 px):** as quatro figuras públicas renderizaram com `<figcaption>`. Larguras aferidas: Pequena 320 px, Padrão 580 px, Larga 900 px e Destaque 960 px. As imagens carregadas sob demanda foram verificadas após rolagem.
- **Janela estreita na primeira rodada:** ao pedir 390 px pelo redimensionamento tradicional do geckodriver, o Firefox informou viewport útil de 500 px. Nessa largura, os quatro modelos e imagens mediram 456 px, as legendas apareceram e `document.documentElement.scrollWidth` não excedeu o viewport.
- **Complemento (mesmo dia):** Firefox 153.4 ESR via WebDriver BiDi aplicou viewport CSS de 390 px na página pública anônima de um artigo existente. Quatro figuras demonstrativas, inseridas somente no DOM da sessão e com as classes reais dos modelos, mediram 346 px cada; todas exibiram `figcaption`, sem overflow horizontal (documento: 378 px). As quatro imagens de teste carregaram, com largura natural de 960 px. O modo escuro integrado foi aferido com `aculta_portal.appearance:default_mode` temporariamente em `dark`, restaurado para `light` ao fim; o HTML veio com `data-bs-theme="dark"` e tokens computados de fundo `rgb(23, 21, 19)`, texto `rgb(244, 239, 232)` e legenda `rgb(183, 174, 165)`. Os modelos mantiveram 346 px sem overflow. Nenhum conteúdo foi salvo. A revisão com Orca/AT-SPI foi registrada na rodada seguinte.

## Imagens inline da Revista — persistência e leitor de tela (2026-10-16)

- **Ambiente:** Firefox 153.4 ESR visível no LXQt/X11 (`DISPLAY=:0`), Drupal 11.4.10 e Runtime `aculta_runtime`. Orca/AT-SPI habilitados.
- **Persistência:** formulário de legenda no CKEditor salvou “Legenda salva no Firefox visível” em artigo temporário válido. O corpo armazenou `data-aculta-legenda`; ao reabrir a edição, o campo trouxe o mesmo valor. Artigo(s) temporário(s) removido(s) e confirmado(s) no cleanup.
- **Leitor de tela:** o Orca anunciou o nome e valor do campo de legenda. Em fixture DOM não persistida na página pública, ao focar a imagem o Orca anunciou a legenda “Legenda acessível de teste” e o `alt` “Imagem de teste com texto alternativo”; AT-SPI expôs a legenda e a imagem como elementos filhos do painel/figura. A fixture foi removida e a sessão Firefox fechada.
- **Limite:** a figura foi fixture DOM temporária com as classes reais do modelo; não foi salva em conteúdo. A configuração de runtime permaneceu inalterada.

## ACULTA Forum — busca visual X11 e comparação com Ubuntu Discourse

### Home e categorias vazias — 2026-10-20

- Runtime `toca`, Drupal 11.4.10, URI de propósito Growroom420 e banco
  `aculta_runtime`; Firefox ESR 153.4/geckodriver 0.37.1 visível em X11 `:0`.
- `tests/browser/aculta-forum/forum-index-visual.mjs` fez somente GET em `/` e
  `/categorias`: 13/13 PASS por tela em 390/768/1024 CSS px e claro/escuro.
  A home apresentou quatro categorias raiz; `/categorias`, 12. Ambas sem cards
  de tópicos ou bundle `forum` contrib, sem overflow horizontal.
- EntityQuery confirmou 0 tópicos públicos, 0 comentários hospedados por esses
  tópicos e 0 Nodes antigos `forum`; nada foi criado ou removido. Capturas
  privadas 0600 em
  `/home/aculta-system-data/private/aculta-forum-index-dev102/visual/captures/`.
- Comparação com `https://discourse.ubuntu.com/` observou organização por
  categoria com descrições, volume semanal e tópico/atividade recente. A sessão
  não aceitou o modal de cookies. A referência visual completa de lista/tópico
  permanece pendente porque o Growroom de teste precisa ficar sem conteúdo.
  RUNTIME STATUS: PASS para as páginas vazias; fórum completo PARTIAL.

### Busca pública — 2026-10-20

- Runtime de teste `toca`, sessão Firefox ESR visível no X11 `:0`, anônima e
  isolada. Drush confirmou Drupal 11.4.10, URI `growroom420`, banco
  `aculta_runtime` e 0 tópicos/0 comentários antes e depois.
- `tests/browser/aculta-forum/search-visual.mjs` abriu a busca GET sem gravar
  conteúdo: 20/20 verificações PASS em viewports CSS exatos de 390, 768 e 1024
  px, nos modos claro e escuro. Formulário, categoria, estado vazio, CAPTCHA
  ausente e limites laterais foram verificados. Contraste do botão: 12,40:1 no
  claro e 12,23:1 no escuro.
- O offcanvas fechado do carrinho foi encontrado fora do viewport, sem alterar
  `scrollWidth`; não foi tratado como overflow da busca. Capturas privadas
  `0600` em `/home/aculta-system-data/private/aculta-forum-search-dev91/visual/captures/`.
- Esta rodada cobre apenas o estado de busca vazia. Inspeção comparativa das
  categorias, listas com conteúdo e tópico contra Ubuntu Discourse permanece
  planejada, pois o Runtime do Fórum está intencionalmente sem conteúdo.

### Painel administrativo central — 2026-10-20

- Runtime `toca`, URI `https://aculta.toca.net.br`, Drupal 11.4.10 / banco
  `aculta_runtime`; Firefox ESR 153.4/geckodriver 0.37.1 em X11 `:0`, UID 1.
- `tests/browser/aculta-forum/admin-content-structure.mjs` executou 23/23
  verificações GET-only: página central HTTP 200, indicadores de categorias e
  tópicos, separação dos quatro contextos, links no host Growroom e ausência de
  ferramentas agregadas. Os 12 caminhos estáticos de conteúdo, comentários,
  filas, configurações e denúncias terminaram em `/painel-administrativo/forum`.
- Viewports CSS 390, 768 e 1024 px renderizaram sem overflow. Capturas 0600 em
  `/home/aculta/private/aculta/visual-tests/forum-admin-central-dev120-20261020/`.
- Nenhum formulário, configuração ou conteúdo foi gravado. As rotas de mover
  Node/Comment exigem entidades reais; não foram chamadas por HTTP e nenhuma
  fixture foi criada. A inspeção comparativa completa do Growroom com Ubuntu
  Discourse continua pendente e separada deste smoke administrativo.

### Abas administrativas do Growroom420 — 2026-10-20

- Runtime de teste `toca`, Drush URI `https://aculta.toca.net.br`, banco
  `aculta_runtime`, Firefox ESR/geckodriver em X11 `:0`, UID 1.
- `tests/browser/aculta-forum/admin-content-structure.mjs`: 25/25 verificações
  GET-only. `/painel-administrativo/forum` apresenta o título Painel
  Growroom420 e aba Início; não repete atalhos de ferramentas nem conteúdo de
  arquitetura destinado aos documentos. Abas de primeiro nível, filhos do menu
  lateral e subabas de estado de Comentários foram verificadas; caminhos
  canônicos responderam 200 e caminhos antigos redirecionaram ao painel.
- Viewports CSS 390, 768 e 1024 px sem overflow. Capturas privadas `0600` em
  `/home/aculta/private/aculta-forum-admin-final-20261020/`. Smoke não enviou
  formulários nem criou conteúdo. RUNTIME STATUS: PASS para esta navegação.

Aplicar na revisão visual da dev.11 e repetir na homologação rc.1, usando
Firefox visível na sessão X11 do servidor de teste ou workstation autorizada.
Confirmar `DISPLAY`, URI de teste e viewport efetivo do navegador; registrar
resolução CSS aferida, tema claro/escuro, conta/perfil de acesso e data da
consulta de referência. Não salvar screenshots com dados pessoais ou privados
no repositório; mantê-las em evidência privada e registrar os caminhos sem
copiar conteúdo para documentos versionados.

### Roteiro de comparação

1. Abrir `https://discourse.ubuntu.com/` e observar a organização atual da home,
   categorias, lista e uma discussão. Anotar a data porque o conteúdo e a
   configuração da referência mudam.
2. Abrir o Growroom420 de teste nas larguras CSS 1366, 768 e 390 px. Comparar
   entrada, categorias, subcategorias, lista, tópico, resposta e composição.
3. Avaliar hierarquia, densidade/ritmo, largura de leitura, alinhamento de
   atividade/contadores, estados de tópico, navegação e persistência visual do
   contexto durante leitura. Confirmar autoria `author_forum`/`author_comment`
   e que nenhum contexto LMS/Wiki ou campo privado aparece publicamente.
4. Repetir em claro e escuro. Testar tabulação, foco visível, abrir/fechar
   controles, voltar para a primeira resposta não lida e enviar/validar os
   formulários com conteúdo efêmero aprovado. Não mover conteúdo real durante a
   comparação.
5. Registrar divergências por tela, viewport, severidade e correção. Revisar
   overflow, contraste, alvos de toque, headings, estados vazios/erro e
   comportamento sem JavaScript.

O objetivo é aproximar a experiência e composição do fórum de referência com a
identidade visual ACULTA420; marcas, logos, ilustrações e folhas de estilo do
Ubuntu/Discourse não são copiadas. Resultado permanece **planejado** até rodar
esse roteiro com o Firefox X11 e evidência real.


## R. Rodada no X11 local (2026-10-21): foto, associado, atividade e login

Ambiente: Chromium com janela visível no display X11 local (`DISPLAY=:0`), controlado pelo protocolo DevTools; conta de
teste `teste.comum` (uid 5); administração pelo navegador com link único gerado no servidor de testes. Capturas no
diretório de trabalho do agente (não versionadas). Esta rodada substitui os passos de envio do roteiro N, que ainda citava
"Salvar" depois do recorte e o limite de 2 MB (o limite atual é o da `UploadPolicy`, 10 MB).

| Passo | Esperado | Resultado |
| --- | --- | --- |
| R1 painel e popup no avatar do sistema (P1) | sem "Remover foto"; "Sortear novo avatar" na mesma linha de "Salvar"; campo de envio; sem "Recortar foto"; popup centralizado | OK |
| R2 sortear só na prévia (P7) | prévia muda; avatar do painel não muda; fechar sem salvar não recarrega e mantém a foto; reabrir mostra a foto atual | OK |
| R3 envio com recorte automático (N3) | modal "Recortar foto" abre sozinho; sem abas nem "Crop image"; caixa de recorte; altura limitada e rolagem | OK |
| R4 aplicar recorte (N4/P2) | uma única requisição de recorte; volta ao painel sem reabrir o popup; foto do painel trocada pelo envio | OK |
| R5 foto personalizada (P3/P5) | "Remover foto" presente; sem "Sortear novo avatar"; sem "Recortar foto" | OK |
| R6 remover (P4) | volta a avatar do sistema sorteado; popup mostra "Sortear novo avatar" e sem "Remover foto" | OK |
| R7 associado: pedido (clique real) | "pedido de adesão está em análise" na tela da pessoa | OK |
| R8 associado: administrador convida | relatório mostra o pedido; convite enviado | OK |
| R9 associado: pessoa aceita | "Aceitar convite" e "Recusar convite" visíveis; aceite mostra "Você é associado desde" | OK |
| R10 atividade: abas (Q1/Q2) | cinco abas; Fórum com URL `/atividade/forum` e marcada como atual | OK |
| R11 conquistas: tooltip (aculta_game) | missão concluída com `title` "Obtida em 16/10/2026" | OK |
| R12 login com senha errada e certa (O) | senha errada mostra "não reconhecidos" e fica em /entrar; senha certa chega ao painel | OK |
| R13 erros de JavaScript | nenhum erro de script do site | OK nas rodadas de R1, R7-R12. Na rodada de R3-R6, o console registrou um 403 ao carregar o link de login único na primeira navegação; não há erro de script do site, e a causa do 403 ficou sem reprodução |

Achados de configuração (não são do módulo): o servidor envia uma diretiva `webrtc` no Content-Security-Policy que o
navegador não reconhece ("Unrecognized Content-Security-Policy directive"). Corrigir no cabeçalho de segurança do
ambiente; não afeta a funcionalidade.

Não executado nesta rodada: inspeção visual humana (aparência final, alinhamento e contraste em tela pequena e grande)
e recorte com a área de seleção movida pelo usuário (o fluxo verificado aplica o recorte padrão).
