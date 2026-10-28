<?php

declare(strict_types=1);

namespace Drupal\aculta_events_openstreetmap\OAuth;

/**
 * Safe, user-facing OAuth failure.
 *
 * Never contains a secret or upstream response body.
 */
final class OpenStreetMapOAuthException extends \RuntimeException {
}
