<?php

declare(strict_types=1);

namespace Drupal\aculta_game\EventSubscriber;

use Drupal\aculta_lms\Event\CourseCompletedEvent;
use Drupal\aculta_user\Badge\BadgeService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Awards the learning milestone from the neutral completion event.
 */
final class CourseCompletionBadgeSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly BadgeService $badges,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CourseCompletedEvent::NAME => 'courseCompleted',
    ];
  }

  /**
   * Awards the first-course badge for a confirmed provider completion.
   */
  public function courseCompleted(CourseCompletedEvent $event): void {
    $this->badges->awardForTrigger((int) $event->accountId, 'course_passed');
  }

}
