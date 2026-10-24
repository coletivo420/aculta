<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_messenger\Unit;

use Drupal\aculta_lms_messenger\EventSubscriber\LearningNoticeEventSubscriber;
use Drupal\aculta_lms_messenger\LearningNoticeManagerInterface;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;

/**
 * Tests the LMS consumer of Messenger's notice event contract.
 */
#[CoversClass(LearningNoticeEventSubscriber::class)]
#[Group('aculta_lms_messenger')]
final class LearningNoticeEventSubscriberTest extends UnitTestCase {

  /**
   * Routes a valid LMS event to existing Message-backed notice storage.
   */
  public function testValidEventUsesTheExistingNoticeManager(): void {
    $manager = $this->createMock(LearningNoticeManagerInterface::class);
    $manager->expects(self::once())
      ->method('record')
      ->with('certificate-uuid', 'certificate', 27, TRUE, 'v1');
    $subscriber = new LearningNoticeEventSubscriber($manager, new NullLogger());

    $subscriber->consume($this->event(
      'lms.certificate',
      'aculta_course_certificate',
      ['notice_type' => 'certificate', 'mail_policy' => 'transactional'],
    ));
  }

  /**
   * Ignores events owned by other producers.
   */
  public function testOtherModulesCannotUseTheLmsStorageAdapter(): void {
    $manager = $this->createMock(LearningNoticeManagerInterface::class);
    $manager->expects(self::never())->method('record');
    $subscriber = new LearningNoticeEventSubscriber($manager, new NullLogger());

    $subscriber->consume($this->event(
      'forum.reply',
      'comment',
      ['notice_type' => 'certificate', 'mail_policy' => 'transactional'],
      'aculta_forum',
    ));
  }

  /**
   * Rejects an event whose declared type and metadata do not match.
   */
  public function testMismatchedNoticeTypeIsIgnored(): void {
    $manager = $this->createMock(LearningNoticeManagerInterface::class);
    $manager->expects(self::never())->method('record');
    $subscriber = new LearningNoticeEventSubscriber($manager, new NullLogger());

    $subscriber->consume($this->event(
      'lms.enrollment',
      'aculta_course_certificate',
      ['notice_type' => 'certificate', 'mail_policy' => 'transactional'],
    ));
  }

  /**
   * Creates a minimal Messenger notice event fixture.
   */
  private function event(
    string $eventType,
    string $sourceEntityType,
    array $metadata,
    string $sourceModule = 'aculta_lms',
  ): MessengerNoticeEvent {
    return new MessengerNoticeEvent(
      $eventType,
      $sourceModule,
      $sourceEntityType,
      'certificate-uuid',
      'v1',
      27,
      'account',
      new \DateTimeImmutable('2026-10-24T12:00:00+00:00'),
      $eventType . ':certificate-uuid:v1:27',
      $metadata,
    );
  }

}
