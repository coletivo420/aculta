<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Account;

/**
 * Signals a conflicting public nickname without exposing database details.
 */
final class NicknameClaimConflictException extends \RuntimeException {}
