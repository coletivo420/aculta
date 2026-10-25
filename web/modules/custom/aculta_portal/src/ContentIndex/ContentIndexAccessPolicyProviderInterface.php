<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\ContentIndex;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Optional provider contract for source-specific access beyond Entity Access.
 *
 * The Index always combines this result with the source entity's normal view
 * access. Implementations can add contextual rules such as LMS enrollment.
 */
interface ContentIndexAccessPolicyProviderInterface {

  /**
   * Checks provider-specific access for an entity candidate.
   */
  public function checkAccess(ContentEntityInterface $entity, AccountInterface $account): AccessResultInterface;

}
