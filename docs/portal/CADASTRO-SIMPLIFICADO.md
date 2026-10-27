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
- **Bloqueio:** `guest_new_account` está desligado nos fluxos `default`, `doacao` e `assinatura_flow`. Sem ele, o
  Commerce não cria a conta e o cadastro simplificado não dispara. Ligar muda o checkout e exige decisão (ver seção 4).
- Pendente: modal sem fechar (hoje é página), recuperação de senha pelo mesmo modal, painel administrativo.

## 4. Decisões pendentes para o checkout

- Ligar `guest_new_account` nos fluxos `default` e `doacao`, e ligar `allow_guest_checkout` no painel de login, com
  e-mail obrigatório.
- O login (`AcultaCheckoutLogin`) diz que a conta é criada ao concluir o pedido, o que hoje só vale com a configuração
  ligada.
