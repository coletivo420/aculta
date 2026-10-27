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
