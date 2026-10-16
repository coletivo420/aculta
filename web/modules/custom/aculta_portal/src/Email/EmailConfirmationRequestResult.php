<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Email;

/** Outcome of attempting to request a registration confirmation email. */
enum EmailConfirmationRequestResult {

  /** The MailManager accepted the request; final mailbox delivery is unknown. */
  case ACCEPTED;

  /** The account has already used its confirmation request allowance. */
  case RATE_LIMITED;

  /** Another request for this account currently holds the serialization lock. */
  case BUSY;

  /** The supplied account cannot own a confirmation request. */
  case INVALID_ACCOUNT;

  /** Persistence or the mail backend did not accept the request. */
  case FAILED;

}
