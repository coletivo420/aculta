# Troca de e-mail — aculta_user

Pedido de troca de e-mail da própria conta, na aba **E-mail** da Segurança (`/seguranca`, em Minha conta).
Substitui o contrib `change_mail_page`, retirado do Runtime e do `composer.lock` (ver [CHANGELOG](../CHANGELOG.md), 0.4.0-dev.55).

## Regra

- A troca só existe dentro de Minha conta (decisão do responsável; ver `EmailChangePolicy` no aculta_portal).
- O pedido **não grava** o novo endereço. O salvamento passa pelo `aculta_mail_confirmer_user`, que intercepta o save,
  guarda o pedido e envia o link de confirmação de uso único de 24 horas (regra em
  [LINKS-DE-USO-UNICO](../../../../../../../docs/portal/LINKS-DE-USO-UNICO.md)).
- O e-mail só muda depois da confirmação. Enquanto isso, a conta mantém o e-mail atual.
- Sem a confirmação ligada (`aculta_mail_confirmer_user.settings` → `user_email_change.enabled`), o formulário **não
  aparece** e, se for enviado mesmo assim, recusa o envio. Nunca grava direto.

## Peças

| Peça | Arquivo | Função |
| --- | --- | --- |
| `ChangemailForm` (form id `aculta_user_changemail_form`) | `src/Form/ChangemailForm.php` | Pede o novo e-mail e a senha atual. Valida a senha, limita tentativas, recusa e-mail igual ao atual e chama `validate()` do Core. |
| `ChangemailHooks::changemailAlter` | `src/Hook/ChangemailHooks.php` | Ponto `aculta_portal_changemail` (alter da aba E-mail). Entrega o formulário quando o envio está pronto e a confirmação está ligada. |
| `ChangemailRedirectController::redirectToSecurity` | `src/Controller/ChangemailRedirectController.php` | Caminhos legados: `302` para `aculta_portal.security`. Só a própria conta; outra conta recebe `403`. |
| Rotas `aculta_user_changemail.legado` e `aculta_user_changemail.legado_usuario` | `aculta_user.routing.yml` | `/seguranca/email` e `/seguranca/email/{user}`. Opção `_aculta_domain_purpose: account`. |

O aculta_portal monta a aba (`PortalController::securitySectionContent`), mostra o pendente e chama o ponto
`aculta_portal_changemail`. O aculta_user só entrega o formulário. O aculta_portal não referencia classes do aculta_user.

## Senha atual e limite de tentativas

- A senha é conferida com o serviço `password` do Core, antes da validação do e-mail.
- Cada senha errada registra uma tentativa no `flood` (evento `aculta_user_changemail_password`, por conta).
- Com 5 tentativas erradas na última hora, o formulário recusa novas tentativas por uma hora.
- Por isso, testes de senha errada consomem a janela da conta. Limpar a janela de teste: `DELETE FROM flood WHERE event LIKE 'aculta_user_changemail%'`
  (só na conta de teste).

## Caminhos

| Caminho | Situação |
| --- | --- |
| `/seguranca/email` | Legado: `302` para `/seguranca` (aba E-mail). |
| `/seguranca/email/{user}` | Legado: `302` para `/seguranca`, só para a própria conta. |
| `/seguranca/email/cancelar/{user}` | Não muda; cancelamento do pedido pendente (aculta_mail_confirmer_user). |
| `/user/change-mail` e `/user/{user}/change-mail` | Removidos com o contrib: `404`. |

## Testes

- Validação direta no Runtime (`drush scr`): senha errada recusada; e-mail do usuário não alterado.
- Navegador X11 (conta de teste `teste.comum`): senha errada com mensagem em português; pedido válido gravado como
  pendente; espaçamento do bloco pendente; celular 390 px sem rolagem horizontal; sem erros de JavaScript.
- Confirmação real por IMAP: em andamento (ver CHANGELOG do módulo). A confirmação do pedido de troca de e-mail
  depende da caixa de destino recebendo o link.
- Testes Kernel do módulo: bloqueados pela cadeia de serviços do aculta_portal (ver [PENDENCIAS](PENDENCIAS.md)).

## Aviso relacionado

O aviso de e-mail não confirmado (caixa de atenção em Minha conta e item permanente na lista de avisos) fica no
aculta_messenger (`EmailPendingAviso`, modelo `aculta_msg_email_pendente`). Ver `aculta_messenger/docs/`.
