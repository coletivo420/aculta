# Política de confirmação de e-mail

Status: **proposta registrada; implementação pendente de decisões do responsável** (ver seção 6).

## 1. Regra

Usuário com e-mail não confirmado:

- **Pode:** acessar o site, fazer login, navegar e usar a área "Minha conta".
- **Não pode:** realizar cursos (matrícula e acesso às aulas) e editar a wiki (criar ou alterar verbetes).

## 2. Aviso em "Minha conta"

Enquanto o e-mail não estiver confirmado, a área "Minha conta" exibe um aviso em destaque vermelho com:

- texto: o e-mail precisa ser confirmado, com um link **"Clique aqui para reenviar"**;
- **não pode ser descartado** (sem botão de fechar, nem lembrar-depois);
- desaparece somente quando o e-mail for confirmado.

## 3. Definição de "e-mail confirmado"

Um e-mail é confirmado quando o usuário abre o link de confirmação enviado ao endereço. O estado deve ser gravado
pelo Portal (flag por usuário, por exemplo em `user.data` do módulo `aculta_portal`), e não inferido de outro campo.

## 4. Mecanismo técnico (a decidir)

- Hoje o Core bloqueia a conta até a verificação quando `user.settings:verify_mail` está ligado. Isso impede o login
  de quem não confirmou, o que contradiz a regra 1. Para cumprir a regra, a verificação passa a ser feita pelo Portal:
  `verify_mail` desligado, conta ativa, flag de "não confirmado" e confirmação pelo `email_confirmer` (realm próprio de
  cadastro).
- O reenvio usa a rota de reenvio do `email_confirmer`, com o intervalo de `resendrequest_delay` (900 s) para evitar abuso.
- O link de confirmação é gerado sempre no host da conta (como na troca de e-mail).

## 5. Pontos de aplicação

- Cursos: matrícula e acesso às aulas (`aculta_portal` / LMS). Bloquear com `access` do Portal, sem alterar o LMS.
- Wiki: criação e edição de verbetes e categorias (permissões de conteúdo e `entity_access` do Portal).
- Aviso: formulário/seção de "Minha conta" do shell do Portal, apenas para o usuário dono da conta.

Esta política não restringe leitura pública da wiki nem páginas institucionais.

**Troca de e-mail não é bloqueio.** Quem não confirmou o e-mail pode solicitar a troca de endereço em "Segurança"
(e reenviar a confirmação). A regra de escrita bloqueia apenas wiki, comentários, cursos, loja e apoio.

## 6. Decisões do responsável (pendentes)

1. **Contas existentes:** consideradas confirmadas (recomendado) ou precisam confirmar agora?
2. **Confirmação de cadastro:** confirmar também via link do `email_confirmer` (recomendado), mantendo a verificação
   do Core desligada.
3. **Escopo:** além de cursos e edição da wiki, há outras ações a bloquear (por exemplo, apoio, comentários)?
4. **Expiração do link de cadastro:** usar o mesmo `hash_expiration` (24 h) da troca de e-mail, ou outro prazo?

## 6.1 Validade do link (decisão do responsável)

- O link de confirmação **não expira** (`EmailConfirmationEntity::isExpired()` devolve falso; classe trocada no Portal).
- A segurança depende do hash de 43 caracteres de uso único, do cancelamento dos pedidos anteriores e do reenvio
  (`aculta_portal.email_resend`, protegido por login e token CSRF). A configuração `hash_expiration` deixa de valer.
- Antes desta decisão, a premissa era de 24 horas (ver CHANGELOG do Portal 0.2.0-dev.16).

## 6.2 Decisões já tomadas

- Contas existentes precisam confirmar (sem marca `email_confirmed` = não confirmado).
- Cadastro é confirmado pelo `email_confirmer` (realm `aculta_registration`).
- Escopo: cursos, wiki, comentários, loja e apoio.
- Login por OAuth confirma o e-mail (provedor já verificou o endereço).

## 7. Segurança

- Nenhum endereço de e-mail nem valor de link é gravado em log além do necessário para diagnóstico (nome e usuário).
- O reenvio é limitado pelo intervalo de `resendrequest_delay`.
- A confirmação segue o hash de 43 caracteres de uso único e com validade; o login automático na confirmação está
  registrado em `docs/portal/` como risco aceito (ver CHANGELOG do Portal 0.2.0-dev.14).

## 8. Cadastro com e-mail já cadastrado (0.2.0-dev.22, DT-P06)

Decisão do responsável (2026-10-09): a resposta do cadastro não revela se o e-mail já tem conta.

- **Mensagem única** para e-mail novo e já cadastrado: "Se este e-mail ainda não estiver cadastrado, enviaremos um link para confirmar o seu cadastro. Se ele já estiver cadastrado, enviaremos um aviso com as opções para acessar a sua conta. Verifique a caixa de entrada e a pasta de spam." Mesmo destino (página inicial).
- **Com e-mail já cadastrado:** nenhuma conta é criada, ninguém é logado, e o dono da conta recebe um e-mail (`registration_existing_mail`) com o link para entrar, o link para recuperar a senha e a orientação de que não é preciso fazer nada se não foi ele.
- **Com e-mail novo:** o cadastro segue como antes (conta bloqueada até a confirmação), só com a mensagem única.
- **Implementação:** o erro "already taken" do Core sai do formulário (`validateRegistrationMail`); o botão usa `saveRegistration` no lugar de `::save`; a busca e o aviso ficam em `RegistrationMailPolicy`; o texto do aviso está no hook `mail` do módulo. Callbacks que gravam dados ignoram o caso de e-mail existente.
- **Limites:** (1) o tempo de resposta difere quando o aviso é enviado na mesma requisição; (2) o aviso pode ser usado para incomodar um endereço alheio, por isso o CAPTCHA do cadastro continua obrigatório (ver `TURNSTILE.md`); (3) o aviso não é enfileirado; (4) o aviso depende do SMTP, ainda não validado em produção (DT-P05).

## 9. Troca de e-mail (decisão 2, 2026-10-12)

**Decisão do responsável:** a troca de e-mail dispara uma nova confirmação do novo endereço, e o papel
`email_confirmed` permanece na conta. A troca só existe no formulário de Minha Conta (`change_mail_page`); a edição
de conta do Core não oferece o campo de e-mail (`EmailChangeHooks`).

**Como funciona hoje (código e configuração verificados):**

- O `email_confirmer_user` (`user_presave`, `email_confirmer_user.module`) intercepta toda troca de e-mail de conta
  quando `email_confirmer_user.settings:user_email_change.enabled` está ligado (no Runtime: ligado).
- Para troca feita por quem **não** tem o bypass: o módulo guarda o novo endereço em `user.data`
  (`email_change_new_address`), restaura o endereço antigo, cria a confirmação do novo (realm `email_confirmer_user`)
  e envia o e-mail. O novo endereço só passa a valer depois do clique no link.
- O papel `email_confirmed` não é alterado por essa troca (o papel é irreversível, ver `EmailConfirmedRoleGuard`).
- Troca feita por administrador: o papel `administrator` tem `is_admin: true`, então passa no bypass
  `email confirmer user bypass email change`. A troca é aceita na hora, sem confirmação.

**Pontos em aberto (confirmar antes de implementar):**

1. "Aceitar a alteração" (texto da decisão) difere do comportamento do módulo, que mantém o endereço antigo até a
   confirmação. O comportamento do módulo é o mais protetivo para o combate a contas falsas.
2. O bypass do administrador não pode ser removido por permissão (`is_admin`). Fechar a lacuna exige código próprio,
   e só alcança trocas feitas por código, porque o formulário de administração não tem mais o campo de e-mail.

