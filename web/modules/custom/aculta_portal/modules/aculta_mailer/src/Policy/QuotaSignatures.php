<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Policy;

/**
 * Padrões de texto que indicam limite ou cota do transporte (modelo das assinaturas de erro do WP Mail SMTP).
 *
 * Cota de caixa de destinatário ("mailbox quota", "caixa cheia") não conta: é do destinatário, não do transporte.
 * Os textos dos provedores variam; estes padrões são os mais comuns. A lista cresce com os casos observados.
 */
final class QuotaSignatures {

  private const PATTERNS = [
    '/\b(daily|sending|send|rate|message|hourly|monthly) quota\b/i',
    '/\bquota (exceeded|reached|limit)\b/i',
    '/\brate[ -]?limit/i',
    '/\bdaily (sending )?limit\b/i',
    '/\bsending limit\b/i',
    '/\btoo many (messages|emails|recipients|requests)\b/i',
    '/\bmonthly (email )?(allowance|quota|limit)\b/i',
    '/\ballowance (is )?(exhausted|exceeded)\b/i',
    '/\b429\b/',
    '/\blimite (diário|diario|de envio|mensal|semanal)\b/iu',
    '/\bcota (diária|diaria|de envio|excedida|atingida)\b/iu',
  ];

  /**
   * Caixa de destinatário: texto de cota nessa caixa não é sinal do transporte.
   */
  private const RECIPIENT_CONTEXT = '/\b(mailbox|inbox|recipient|destinat|caixa postal|caixa de entrada|usuário|user)\b/i';

  /**
   * Indica se o texto do erro ou do bounce aponta para cota ou limite do transporte.
   */
  public static function matches(string $text): bool {
    if ($text === '' || preg_match(self::RECIPIENT_CONTEXT, $text) === 1) {
      return FALSE;
    }
    foreach (self::PATTERNS as $pattern) {
      if (preg_match($pattern, $text) === 1) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
