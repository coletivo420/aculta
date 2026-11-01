<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Service;

/**
 * Generates opaque public identifiers for short links. */
final class LinkShortCodeGenerator {

  private const ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyz';

  /**
   * Generates a 12-character code using the cryptographic random source.
   */
  public function generate(): string {
    $code = '';
    for ($index = 0; $index < 12; $index++) {
      $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
    }
    return $code;
  }

}
