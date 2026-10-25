<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer_test;

use Drupal\aculta_mailer\Type\MailTypeInterface;

/**
 * Tipo de teste com variáveis escalares conhecidas.
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
  public function variables(): array {
    return [
      'name' => ['label' => 'Nome', 'sample' => 'Maria'],
      'link' => ['label' => 'Link', 'sample' => 'https://example.invalid/x'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSubject(): string {
    return 'Assunto de teste para {{ name }}';
  }

  /**
   * {@inheritdoc}
   */
  public function defaultBody(): string {
    return 'Corpo de teste. Link: {{ link }}';
  }

}
