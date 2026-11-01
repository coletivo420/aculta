<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Social;

/** States for an ACULTA member relationship. */
enum FriendshipStatus: string {

  case Pending = 'pending';
  case Accepted = 'accepted';
  case Declined = 'declined';
  case Cancelled = 'cancelled';
  case Removed = 'removed';

}
