# Auditoria do e-mail transacional (F0)

Data: 2026-10-20. Branch: `docs/aculta-forum-0.1.0-dev.1` (PR #297, base `main`). Escopo: inventário estático do
código versionado e da configuração exportada. Nenhum comportamento foi alterado nesta fase.

Referências: [EMAIL-TRANSPORT](../../../../../../docs/portal/EMAIL-TRANSPORT.md),
[ARCHITECTURE](ARCHITECTURE.md), [UPSTREAM](UPSTREAM.md).

## 1. Dependências e módulos ativos

| Item | Situação verificada | Fonte |
|---|---|---|
| `drupal/symfony_mailer` | `^2.0`, versão instalada `2.0.2` | `composer.json`; `web/modules/contrib/symfony_mailer/symfony_mailer.info.yml` |
| Submódulos do pacote | `mailer_policy`, `mailer_transport`, `mailer_override` em `web/modules/contrib/symfony_mailer/modules/` | diretório instalado |
| `drupal/smtp` | Ausente de `composer.json` e de `core.extension` (removido na M2) | `docs/portal/EMAIL-TRANSPORT.md` §5 |
| `symfony_mailer_log` (camada de envio do Drupal Log) | **Não instalado** (sem diretório em `web/modules/contrib/symfony_mailer/modules/`) | diretório instalado |
| Easy Email | **Não instalado** | `composer.json` |
| Core: `SymfonyMailer` | Plugin `symfony_mailer` existe em `web/core/lib/Drupal/Core/Mail/Plugin/Mail/SymfonyMailer.php` | código do Core 11.4 |
| `email_confirmer` / `email_confirmer_user` | Ativos em `core.extension.yml` | `config/sync/core.extension.yml` |
| `key` | `^1.22`, presente em `composer.json` | `composer.json` |
| Mailer Policy | Entidade `mailer_policy` (`MailerPolicy`), adjusters em `EmailAdjusterBase` com atributo `#[EmailAdjuster]` | `modules/mailer_policy/src/` |
| Mailer Override | `MailManagerReplacement` substitui o gerenciador do Core; overrides por plugin (`UserOverride`, `UserRegistrationPasswordOverride`, `CommerceOrderOverride`, `ContactOverride`, entre outros) | `modules/mailer_override/src/` |

Observação: o transporte padrão e o provedor ainda estão inconsistentes na documentação (Hostinger na seção 0 de
`EMAIL-TRANSPORT.md`, SMTP2GO nas seções 3 e 4 e em `config/secrets-contract.json`). Isso precisa ser resolvido antes da
fase de transportes.

## 2. Pontos de envio no código versionado

Busca por `plugin.manager.mail`, `MailManagerInterface`, `hook_mail`, `->mail(` e uso direto de Symfony Mailer em
`web/modules/custom`, `web/themes/custom/aculta420` e `scripts/`.

| # | Local | Chave / tipo | Destino | Síncrono ou fila | Observação |
|---|---|---|---|---|---|
| 1 | `aculta_portal/src/Account/RegistrationMailPolicy.php` | `aculta_portal` / `registration_existing_mail` | Conta já existente com o e-mail do cadastro | Síncrono (dentro do formulário de cadastro) | Resposta ao visitante é a mesma nos dois casos (DT-P06). Serviço `aculta_portal.registration_mail_policy`. |
| 2 | `aculta_game/src/Plugin/QueueWorker/AchievementEmailWorker.php` | `aculta_game` / `achievement_earned` | Titular da conquista (se opt-in) | Fila `aculta_game_achievement_email` | Lança exceção se o sistema não confirmar; idempotência via `userData` (`game_achievement_email_sent`). |
| 3 | `aculta_messenger/src/Aviso/AvisosResumo.php` | `aculta_messenger` / `avisos_resumo` (com `hook_mail` em `AvisosResumoHooks`) | Resumo diário por pessoa | Chamado por cron/rotina | Exige opção ligada, e-mail confirmado e conta ativa; zera contador só após envio. |
| 4 | `aculta_portal` `EmailConfirmationRequester` | `email_confirmer` (módulo contrib) | Confirmação de e-mail | Síncrono | Usa `flood` e `lock.persistent`; logger `logger.channel.mail`. |
| 5 | `aculta_portal` `PortalController::transactionalMailReady()` | — (gate) | — | — | Verifica o transporte para liberar troca de e-mail em "Segurança". |

Consumidores indiretos (não verificados em código instalado nesta fase): mensagens de conta do Core (`user`), Webform
(`system.mail` com backend `webform_php_mail`), Commerce (pedido), LMS e Forum. A busca não encontrou chamada direta
nesses módulos customizados; a lista completa de chaves enviadas pelo Core e por contribs precisa ser feita em F0
complementar, com `drush php:eval` no Runtime de teste, antes de migrar qualquer uma delas.

## 3. Filas, cron e workers relacionados

- `aculta_game_achievement_email` (`AchievementEmailWorker`): envio de conquista.
- `PrivateMessageNotificationWorker` e `MessageSubscribeEventWorker` (`aculta_messenger`): notificações internas (Messenger), que não são
  e-mail externo, mas são o lado interno do mesmo fluxo de aviso.
- Resumo de avisos (`AvisosResumo`): disparado por rotina de cron.

## 4. Scripts e gates que tocam e-mail

- `scripts/validate-mail-transport.php`: gate de transporte (M1/M2).
- `scripts/validate-final-contact.php`, `scripts/validate-final-drupal.php`, `scripts/validate-portal-commerce-security.php`:
  referenciam e-mail; revisar antes de migrar.
- `scripts/institution/phase6-account-email-test.php`: teste de conta por e-mail.
- `web/modules/custom/aculta_portal/src/Controller/PortalRequirementsController.php`: checagem de requisitos.
- `web/modules/custom/aculta_portal/translations/symfony_mailer.pt-br.po`: traduções customizadas da camada de envio do Drupal.

## 5. Configuração exportada

- `config/sync/system.mail.yml`: backend `symfony_mailer` (padrão de envio do Core, segundo `EMAIL-TRANSPORT.md`).
- `config/sync/mailer_transport.*`: transportes sem credenciais (regra de segredos).
- `config/sync/mailer_policy.*`: não verificado nesta fase (listar antes de F5).
- `aculta_deployer/config/deploy.json`: `system.mail`, `system.site` e `webform.webform.aculta_contact` por ambiente.
- `config/secrets-contract.json`: variáveis `SMTP2GO_USERNAME` e `SMTP2GO_PASSWORD`.

## 6. Configurações de confirmação de conta

- `aculta_portal/src/Account/EmailConfirmationPolicy.php` (serviço `aculta_portal.email_confirmation_policy`).
- `aculta_portal/src/EventSubscriber/EmailConfirmationSubscriber.php`.
- `role` `email_confirmed` (alteração em andamento na outra frente; não tocada aqui).
- Documentação normativa: `docs/portal/EMAIL-CONFIRMATION-POLICY.md`.

## 7. Multidomínio e URLs dependentes de ambiente

- Domínios por purpose: `docs/architecture/multidomain.md`; o `aculta_mailer` precisa referenciar identificadores
  estáveis (`main.aculta` etc.), nunca hostnames de Homelab ou produção.
- Message-ID e URLs absolutas em e-mail devem vir do `aculta_deployer` (`deploy.json`, `environment_bound`) e não de constantes.
- Gate de referência: `scripts/validate-environment-addresses.php`.

## 8. Riscos identificados

1. **Acoplamento direto ao `plugin.manager.mail`** em quatro pontos customizados; a migração precisa de um adaptador
   único antes de remover o contrib.
2. **Mistura de transporte e política**: `mailer_override` troca o gerenciador do Core. Qualquer mudança aqui afeta
   todos os consumidores do Core, inclusive os não inventariados.
3. **Fila sem retry próprio**: `AchievementEmailWorker` lança exceção e depende do comportamento de retry da Queue API do Core.
4. **Documentação de transporte inconsistente** (Hostinger x SMTP2GO).
5. **Log de envio inexistente**: não há registro do estado de entrega além do retorno de `mail()`.
6. **Token em e-mail**: `EmailConfirmationRequester` e o fluxo de reset passam links com token; logs não podem registrar
   esses links (regra de privacidade do `aculta_mailer`).

## 9. Não verificado nesta fase

- Lista completa de chaves enviadas pelo Core, `user`, `commerce`, `webform` e demais contribs no Runtime de teste.
- Conteúdo das configurações `mailer_policy.*` exportadas (não há arquivos nesse prefixo em `config/sync` verificado).
- Teste funcional de envio no Runtime (depende de ambiente autorizado; ver seção "Runtime" do ROADMAP).
