# Cadastro simplificado em todos os checkouts

## 1. Decisões do responsável

1. **Confirmação do e-mail:** reaproveitar a confirmação de e-mail que já existe no portal (decisão de 2026-10-27,
   substitui a de criar um fluxo novo). Envio pelo `aculta_mailer`, realm `aculta_registration`.
2. **Criação da senha:** depois de confirmar o e-mail, a pessoa é levada à etapa de senha, que deve ser um modal do
   site **sem opção de fechar**. O mesmo modal serve à recuperação de senha. Hoje a etapa é uma página (`/criar-senha`);
   o modal fica no plano.
3. **Estado antes da confirmação:** o mesmo comportamento de conta com e-mail não confirmado já existente (papel
   `authenticated`, sem `email_confirmed`). A confirmação concede o papel pela política existente.
4. **Escopo:** cadastro simplificado em todos os checkouts (loja, LMS e doação). E-mail obrigatório em todos.

## 2. Arquitetura

```
checkout concluído (CheckoutEvents::COMPLETION)
  ├─ Commerce (GuestCheckoutCompletionSubscriber): cria a conta de convidado, se guest_new_account estiver ligado
  └─ SimplifiedAccountCheckoutSubscriber (prioridade -100, roda depois)
       └─ EmailConfirmationRequester::request(conta, /criar-senha)   ← já existia
            └─ aculta_mail_confirmer (confirmação, realm aculta_registration)
                 └─ aculta_mailer → modelo email_confirmation_request  ← já existia

pessoa clica no link de confirmação
  └─ EmailConfirmerHooks: markConfirmed(uid) + login se a conta é simplificada e o visitante é anônimo
       └─ redireciona para /criar-senha
            └─ SimplifiedAccountPasswordController: só para conta simplificada pendente
                 └─ SimplifiedAccountPasswordForm: senha + confirmação; remove a marca simplificada
```

Marcas de controle em `user.data` (módulo `aculta_portal`):

- `simplified_account`: conta criada pelo cadastro simplificado (pendente de senha).
- `simplified_account_requested`: pedido de confirmação já feito (evita reenvio a cada checkout).

Componentes novos: `SimplifiedAccountCheckoutSubscriber`, `SimplifiedAccountPasswordController`,
`SimplifiedAccountPasswordForm`, rota `aculta_portal.simplified_account.password` (`/criar-senha`).
Alterado: `EmailConfirmationRequester::request()` aceita a URL de resposta opcional (padrão: painel, como antes);
`EmailConfirmerHooks` faz o login da conta simplificada depois de confirmar.

## 3. Estado da implementação

- Feito: confirmação pelo fluxo existente, login depois da confirmação, etapa de senha, marcas de controle.
- **Checkout ligado no servidor de testes (autorizado em 2026-10-27):** `guest_new_account` ligado em `default`,
  `doacao` e `assinatura_flow`, com o aviso nativo do Commerce desligado (`guest_new_account_notify`), para não haver dois
  e-mails. Compra como convidado permitida no login da loja, da doação e da assinatura. Mudança feita no banco do
  servidor de testes (configuração de fluxo), não exportada no repositório.
- Verificado: o requester existente aceita o pedido de confirmação e entrega ao `aculta_mailer`. A entrega real não foi
  verificada: o SMTP recusa domínios inexistentes (554), o que é esperado para endereço de teste fictício. Falta um
  endereço que receba e-mail para confirmar a entrega e o link.
- Pendente: modal sem fechar (hoje é página), recuperação de senha pelo mesmo modal, painel administrativo.

## 4. Decisões

- Checkout: autorizado ligar `guest_new_account` e `allow_guest_checkout` (2026-10-27), em todos os fluxos.
- Aviso nativo do Commerce desligado: a confirmação do portal é o único e-mail de criação de conta.
- Pendência de teste: um endereço que receba e-mail, para verificar a entrega e o link de confirmação.

## 5. Caminhos do código e do teste (atualizado em 2026-10-27)

**Código (módulo `aculta_portal`):**

| Etapa | Arquivo |
|---|---|
| Pedido de confirmação ao concluir o checkout | `src/EventSubscriber/SimplifiedAccountCheckoutSubscriber.php` |
| Envio e controle de reenvio (já existia) | `src/Email/EmailConfirmationRequester.php` (`request($conta, ?Url $respostaUrl)`) |
| Confirmação e login da conta simplificada | `src/Hook/EmailConfirmerHooks.php` (realm `aculta_registration`) |
| Política de confirmação e papel `email_confirmed` (já existia) | `src/Account/EmailConfirmationPolicy.php` |
| Etapa da senha (controller) | `src/Controller/SimplifiedAccountPasswordController.php` (rota `/criar-senha`) |
| Etapa da senha (formulário) | `src/Form/SimplifiedAccountPasswordForm.php` |
| Serviços e rotas | `aculta_portal.services.yml`, `aculta_portal.routing.yml` |

**Envio (módulo `aculta_mailer`, já existia):** modelo `email_confirmation_request`, enviado por `aculta_mailer.mailer`.
O modelo de confirmação de conta criado antes foi removido, para não duplicar o modelo existente.

**Marcas de controle (`user.data`, módulo `aculta_portal`):**
- `simplified_account`: conta criada ou usada pelo cadastro simplificado (pendente de senha).

**Teste automático (caixa de monitoramento):**
- Caixa: `dmarc@aculta.org` é alias de `naoresponda@aculta.org`. Leitura somente, por IMAP `imap.hostinger.com:993`,
  conforme [INSPECAO-VISUAL-EMAIL.md](../operations/INSPECAO-VISUAL-EMAIL.md). A senha vem de `secrets/aculta.secrets.env`
  (`IMAP_PASSWORD`) e nunca é impressa.
- Roteiro: `tests/browser/aculta-frete/` (script de cadastro simplificado a ser versionado; hoje está em
  `scratchpad`). Dispara a conclusão de checkout como convidado (pedido de teste), lê a confirmação pela caixa
  (somente `EXAMINE` e `BODY.PEEK`), abre o link no Firefox visível, confirma e segue para a senha.
- Dados de teste: conta, confirmações e pedidos do `dmarc@aculta.org`. Limpeza restrita a esses registros.

## 6. Estado e achados

- Confirmado no servidor de testes: pedido de confirmação enviado pelo `aculta_mailer`, mensagem recebida na caixa,
  link lido, confirmação feita, conta com papel `email_confirmed`, sessão logada.
- Achado 1: depois de confirmar, a página mostra "Confirmação de e-mail confirmada" mas **não redireciona** para
  `/criar-senha`, embora o destino da resposta esteja definido. Investigação em andamento.
- Achado 2: o assunto do e-mail reaproveitado diz "Confirme seu novo endereço de e-mail", porque o modelo é o de troca de
  e-mail. Decidir se o cadastro simplificado precisa de texto próprio.
- Achado 3: o link do e-mail sai com o host local `http://default` (URL base do ambiente de teste). Em produção, o
  domínio é o público. No teste automático o host é trocado pelo de conta.
- Mudança nova: conta simplificada ainda não confirmada recebe novo pedido em cada compra com o mesmo e-mail (respeitando
  o intervalo de reenvio do requester). Não testada ainda.

## 7. Resultado do teste automático ponta a ponta (2026-10-27)

Roteiro executado no servidor de testes, com o e-mail `dmarc@aculta.org` (caixa de monitoramento, leitura somente):

1. Conclusão de checkout como convidado (pedido de teste): conta criada pelo Commerce, pedido de confirmação enviado.
2. Mensagem recebida pela caixa; link lido; host local trocado pelo de conta.
3. Link aberto no Firefox visível e confirmação feita: "Confirmação de e-mail confirmada".
4. `/criar-senha`: formulário de senha exibido ("Seu e-mail foi confirmado. Crie uma senha para acessar sua conta.").
5. Senha criada e conta logada: "Senha criada. Bem-vindo!".

Pendências desse resultado:
- O redirecionamento automático depois de confirmar ainda não acontece: a página fica em "Confirmação de e-mail confirmada".
  Sem o redirecionamento, a pessoa precisa seguir para `/criar-senha` (ou o link de senha). Investigar a resposta do formulário.
- A etapa da senha é página, não modal sem botão de fechar (decisão do responsável, a fazer).
- Reenvio para conta não confirmada em nova compra: implementado (ver seção 5), ainda não testado no servidor.


## 8. Rodada de fechamento (2026-10-28)

Decisões do responsável, por pergunta interativa:

- **Recuperação de senha em modal:** o link de recuperação (`/recuperar-acesso/...`) entra pela conta com o token do Core.
  Só o campo de nova senha aparece, em modal sem X e sem Esc (`js/account-password-modal.js`, título
  "Crie sua nova senha"). Depois de salvar, a pessoa vai ao painel (`PortalFormCallbacks::recoveryPasswordRedirect`).
  O formulário `user_pass_reset` fica fora da regra de envio AJAX da conta (`FormHooks::accountAjaxSubmit`), que
  consumia o link de uso único.
- **E-mail da conta nova:** tipo próprio `simplified_account_confirmation` (`SimplifiedAccountConfirmationMailType`),
  assunto "Confirme seu e-mail e crie sua senha". Os demais pedidos de confirmação (troca de e-mail) mantêm o modelo
  `email_confirmation_request`. O tipo fica guardado em cada confirmação (`mail_type`).
- **Intervalo de reenvio:** configurável no painel (`resend_window`, padrão 900 s, mínimo 60 s). Vale só para este
  fluxo; `EmailConfirmationRequester::request()` aceita o intervalo e o tipo de e-mail como argumentos opcionais.
- **Painel administrativo:** Painel administrativo > Cadastro simplificado (permissão `administer aculta simplified account`):
  liga/desliga e intervalo de reenvio.
- **Nome e sobrenome no checkout:** painel `aculta_personal_name` (`PersonalNamePane`). Preenchido pelo perfil principal
  (Meu Perfil, `field_first_name`/`field_last_name`) quando a pessoa tem conta; senão, pelo que ela informou no pedido.
  Ao concluir, o nome do pedido vai para o perfil da conta criada na compra (`PersonalNameCompletionSubscriber`, prioridade
  -50, antes do cadastro simplificado), sem sobrescrever nome já salvo.
- **Fluxos:** `curso` (LMS, novo) = contato (e-mail), nome e sobrenome, CPF/CNPJ e pagamento; sem endereço de cobrança e
  sem envio. `doacao` ganhou o painel de nome. A loja (`default`) não ganhou painel de nome: o nome já vem no endereço
  de cobrança.

Configuração de fluxo e de tipo de pedido fica só no servidor de testes (não exportada): `commerce_checkout.commerce_checkout_flow.curso`
(criado), `commerce_checkout.commerce_checkout_flow.doacao` (painel de nome) e o tipo de pedido `curso` (flow `curso`).

### Verificado no navegador (servidor de testes, caixa dmarc@aculta.org)

1. **Confirmação e criação de senha** com modal sem X: e-mail chegou, link confirmado, `/criar-senha` abriu como modal,
   Esc não fechou, senha criada ("Senha criada. Bem-vindo!").
2. **Reenvio:** a segunda compra logo depois não enviou e-mail (janela de 15 min). Depois da janela, a compra reenviou
   com o novo assunto e o fluxo foi concluído até o modal de senha.
3. **Recuperação de senha:** pedido em `/recuperar-senha`, e-mail recebido, link de uso único, modal "Crie sua nova senha"
   sem X e sem Esc, senha salva (`changed` da conta atualizado, sessão aberta pelo link). Depois de salvar, o redirecionamento
   vai para `/conta-legada`, que não mostra conteúdo: pendente revisar o destino para a pessoa.

### Bloqueio encontrado no LMS (2026-10-28)

- Na compra de curso (pedido do tipo `curso`, fluxo `curso`), o painel de pagamento mostra "Não existem gateways de pagamento
  disponíveis para este pedido". O gateway Mercado Pago do servidor de testes tem a condição de tipo de pedido limitada a
  `default` (loja). Liberar o tipo `curso` nessa condição é mudança de pagamento: precisa de autorização e de alinhamento com
  o agente do Mercado Pago. Não foi alterado.
- Teste de LMS feito com curso e turma de teste (grupos 75 e 76) e pedido 197. Removidos ao final, com o produto 10 mantido.

### Não verificado

- Painel de nome no fluxo `curso` (LMS), no navegador: bloqueado pelo gateway de pagamento (ver acima). Na doação, a
  tentativa caiu na revisão de um pedido já existente da sessão e o painel de nome não apareceu; falta repetir com pedido novo.
- Testes automáticos (unit/kernel) do `PersonalNameStore`, do `PersonalNameCompletionSubscriber` e do painel administrativo.
