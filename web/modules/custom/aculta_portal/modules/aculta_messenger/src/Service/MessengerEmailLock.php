<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

/**
 * Shared lease for queue workers that call ACULTA Mailer synchronously.
 */
final class MessengerEmailLock {

  /**
   * Covers the 60-second PHP socket timeout plus worker persistence overhead.
   */
  public const TIMEOUT = 180.0;

}
