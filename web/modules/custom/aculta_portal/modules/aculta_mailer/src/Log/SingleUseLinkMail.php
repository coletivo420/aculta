<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Log;

/**
 * E-mails com link de uso único (confirmação, recuperação de senha, acesso de cadastro).
 *
 * O conteúdo desses e-mails, quando falha, fica guardado para reenvio só por 24 horas: depois disso o cron apaga o
 * conteúdo cifrado (MailLogRetention::purgeExpiredPayloads). Os demais e-mails seguem a retenção do registro.
 *
 * A lista é explícita, por módulo e chave do Core (hook_mail). Um e-mail novo com link de uso único entra aqui.
 */
final class SingleUseLinkMail {

  /**
   * Prazo, em segundos, do conteúdo guardado de e-mail com link de uso único.
   */
  public const TTL = 86400;

  /**
   * Pares módulo:chave (ou tipo do aculta_mailer) cujo conteúdo expira em 24 horas.
   */
  private const KEYS = [
    'aculta_mailer:email_confirmation_request',
    'aculta_mailer:registration_existing_mail',
    'user:password_reset',
    'user:register_admin_created',
    'user:register_no_approval_required',
    'email_confirmer:confirmation_request',
  ];

  /**
   * Texto sem links: cada URL vira `[link removido]`. Usado no conteúdo guardado dos e-mails de uso único.
   */
  public static function withoutLinks(string $text): string {
    return (string) preg_replace('#https?://[^\s"\'<>)]+#i', '[link removido]', $text);
  }

  /**
   * Indica se o e-mail carrega um link de uso único.
   */
  public static function applies(string $module, string $key): bool {
    return in_array($module . ':' . $key, self::KEYS, TRUE);
  }

}
