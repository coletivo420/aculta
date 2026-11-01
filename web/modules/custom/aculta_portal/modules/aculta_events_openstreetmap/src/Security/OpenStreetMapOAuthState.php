<?php

declare(strict_types=1);

namespace Drupal\aculta_events_openstreetmap\Security;

/**
 * Creates and checks a one-use OAuth state value.
 */
final class OpenStreetMapOAuthState {

  /**
   * Emite 32 bytes aleatórios em formato hexadecimal. */
  public function issue(): string {
    return bin2hex(random_bytes(32));
  }

  /**
   * Compara state em tempo constante e recusa valores ausentes. */
  public function matches(?string $expected, ?string $received): bool {
    return is_string($expected) && $expected !== '' && is_string($received) && $received !== ''
      && hash_equals($expected, $received);
  }

}
