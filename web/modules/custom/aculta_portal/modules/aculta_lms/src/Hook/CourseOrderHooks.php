<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\aculta_lms\CourseOrderEnrollmentManager;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Synchronizes Group class memberships when course orders change. */
final class CourseOrderHooks {

  public function __construct(
    #[Autowire(service: 'aculta_lms.course_order_enrollment')]
    private readonly CourseOrderEnrollmentManager $enrollments,
  ) {}

  #[Hook('commerce_order_insert')]
  #[Hook('commerce_order_update')]
  public function orderSaved(EntityInterface $entity): void {
    if (!$entity instanceof OrderInterface
      || $entity->bundle() !== 'curso'
      || ($entity instanceof SynchronizableInterface && $entity->isSyncing())) {
      return;
    }
    $this->enrollments->synchronize($entity);
  }

}
