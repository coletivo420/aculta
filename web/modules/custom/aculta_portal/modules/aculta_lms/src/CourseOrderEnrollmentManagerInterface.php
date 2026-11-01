<?php

declare(strict_types=1);

namespace Drupal\aculta_lms;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\user\UserInterface;

/**
 * Provides the Commerce entitlement contract used by LMS enrollment hooks.
 */
interface CourseOrderEnrollmentManagerInterface {

  /**
   * Synchronizes class memberships when a course order changes.
   */
  public function synchronize(OrderInterface $order): void;

  /**
   * Checks whether the student has an active purchase for the class.
   */
  public function hasActivePurchaseForClass(GroupInterface $class, UserInterface $student): bool;

}
