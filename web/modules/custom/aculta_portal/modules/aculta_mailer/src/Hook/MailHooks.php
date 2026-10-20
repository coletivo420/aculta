<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Hook;

use Drupal\aculta_mailer\Type\MailTypeRegistry;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Renderiza os e-mails do ACULTA Mailer a partir do tipo registrado.
 */
final class MailHooks {

  public function __construct(
    private readonly MailTypeRegistry $types,
  ) {}

  /**
   * Implements hook_mail().
   */
  #[Hook('mail')]
  public function mail(string $key, array &$message, array $params): void {
    if (!$this->types->has($key)) {
      return;
    }
    $type = $this->types->get($key);
    $context = $params['context'] ?? [];
    $message['subject'] = $type->subject($context);
    $message['body'][] = $type->body($context);
    if (isset($params['correlation_id'])) {
      // Cabeçalho próprio para rastrear a mensagem do Drupal até o provedor (ver docs/ARCHITECTURE.md).
      $message['headers']['X-Aculta-Mail-ID'] = (string) $params['correlation_id'];
    }
  }

}
