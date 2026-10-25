# Textos dos e-mails de conta: rascunho para aprovação

**Status: aprovado em 2026-10-20 e implementado no aculta_portal 0.4.0-dev.231** (tipos da camada, sem configuração de `user.mail`). Decisões: prazo de 24 h; login por usuário e e-mail; registro de confirmação com 7 dias (mantido); cancelamento e bloqueio fora desta rodada.

Regras que os textos seguem: prazo de **24 horas** nos links de uso único (`docs/portal/LINKS-DE-USO-UNICO.md`); nenhuma
senha é enviada por e-mail; cada link serve uma vez.

## Estado atual (o que o site envia hoje)

| Tipo | Origem | Problema encontrado |
|---|---|---|
| Confirmação de novo e-mail | `email_confirmer.settings` › `confirmation_request` | "responda a esta solicitação pelo link abaixo": a pessoa não responde, só clica. |
| Reset de senha | `config/sync/user.mail.yml` › `password_reset` | Texto correto; o prazo de um dia está certo. Pode ficar mais direto. |
| Cadastro (sem aprovação) | `user.mail` › `register_no_approval_required` | "usuário: [user:name] / senha: Sua senha": parece que uma senha foi enviada. Não é. |
| Cadastro pelo administrador | `user.mail` › `register_admin_created` | Mesmo problema da linha acima. |

## 1. Confirmação de novo e-mail (`email_confirmer:confirmation_request`)

**Assunto:** Confirme seu novo endereço de e-mail em [site:name]

**Corpo:**

```
Recebemos um pedido para usar [email-confirmer:confirmation-mail] como e-mail da sua conta em [site:name].

Se foi você, confirme clicando no link abaixo ou copiando e colando no navegador. O link pode ser usado uma vez e vale por 24 horas.

[email-confirmer:confirmation-url]

Se não foi você, ignore esta mensagem: nada será alterado.

— Equipe [site:name]
```

## 2. Reset de senha (`user.mail:password_reset`)

**Assunto:** Redefinição de senha em [site:name]

**Corpo:**

```
[user:display-name],

Recebemos um pedido para redefinir a senha da sua conta em [site:name].

Para criar uma nova senha, clique no link abaixo ou copie e cole no navegador. O link pode ser usado uma vez e vale por 24 horas.

[user:one-time-login-url]

Se não foi você quem pediu, ignore esta mensagem: sua senha não será alterada.

— Equipe [site:name]
```

## 3. Cadastro concluído (`user.mail:register_no_approval_required`)

**Assunto:** Sua conta em [site:name] está pronta

**Corpo:**

```
[user:display-name],

Seu cadastro em [site:name] foi concluído. Para entrar pela primeira vez e definir sua senha, clique no link abaixo ou copie e cole no navegador. O link pode ser usado uma vez e vale por 24 horas.

[user:one-time-login-url]

Depois de definir a senha, você pode entrar em [site:login-url] com o usuário [user:name].

— Equipe [site:name]
```

## 4. Conta criada pelo administrador (`user.mail:register_admin_created`)

**Assunto:** Um administrador criou sua conta em [site:name]

**Corpo:**

```
[user:display-name],

Um administrador do site [site:name] criou uma conta para você. Para entrar pela primeira vez e definir sua senha, clique no link abaixo ou copie e cole no navegador. O link pode ser usado uma vez e vale por 24 horas.

[user:one-time-login-url]

Depois de definir a senha, você pode entrar em [site:login-url] com o usuário [user:name].

— Equipe [site:name]
```

## Pontos para decidir na aprovação

- **Prazo da assinatura de registro:** `email_confirmer.settings` › `confirmation_lifetime` é 604800 s (7 dias). É o prazo de *limpeza do registro*, não o do link (o link vale 24 h por `hash_expiration: 86400`). Confirmar se 7 dias para o registro é o desejado.
- **Login por usuário ou e-mail:** os textos 3 e 4 citam o usuário, como o texto atual. Confirmar se o login aceita e-mail, para escrever a instrução certa.
- **Cancelamento e bloqueio de conta** (`cancel_confirm`, `status_blocked`, `status_canceled`) não entram nesta rodada.
