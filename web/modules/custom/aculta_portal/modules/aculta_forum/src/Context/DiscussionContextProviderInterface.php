<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Context;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides discussion contexts for supported host entities. */
interface DiscussionContextProviderInterface {

  /**
   * Determines whether this provider owns the supplied host entity. */
  public function supports(EntityInterface $entity): bool;

  /**
   * Builds the context and evaluates access for the current account. */
  public function getContext(EntityInterface $entity, AccountInterface $account): DiscussionContextInterface;

}
