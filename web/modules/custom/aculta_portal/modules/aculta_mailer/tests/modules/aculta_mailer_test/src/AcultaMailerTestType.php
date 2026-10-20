<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer_test;

use Drupal\aculta_mailer\Type\MailTypeInterface;

/**
 * Tipo de teste com contexto escalar conhecido.
 */
final class AcultaMailerTestType implements MailTypeInterface {

  /**
   * {@inheritdoc}
   */
  public function id(): string {
    return 'aculta_mailer_test_message';
  }

  /**
   * {@inheritdoc}
   */
  public function subject(array $context): string {
    return 'Assunto de teste para ' . ($context['name'] ?? 'ninguém');
  }

  /**
   * {@inheritdoc}
   */
  public function body(array $context): string {
    return 'Corpo de teste. Link: ' . ($context['link'] ?? '');
  }

}
