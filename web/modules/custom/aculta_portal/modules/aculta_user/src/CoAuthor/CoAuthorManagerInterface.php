<?php

declare(strict_types=1);

namespace Drupal\aculta_user\CoAuthor;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Read-only contract for current ACULTA node co-authorship.
 *
 * This interface deliberately does not expose account e-mail addresses or
 * mutate content. Assignment and access policies belong to separate APIs.
 */
interface CoAuthorManagerInterface {

  /**
   * Returns unique, positive user IDs, excluding the primary node owner.
   *
   * The returned IDs are references, not a guarantee of account eligibility.
   * An absent field is treated as an empty list until the single cutover.
   *
   * @return int[]
   *   Referenced user IDs in stored order.
   */
  public function getCoAuthorIds(NodeInterface $node): array;

  /**
   * Whether an authenticated account is listed as a secondary author.
   *
   * This is a membership check, NOT an access grant or a statement that the
   * account has permissions to edit, delete, view or manage the content.
   */
  public function isCoAuthor(NodeInterface $node, AccountInterface $account): bool;

}
