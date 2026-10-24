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
   * Routes other LMS producers through the same existing storage adapter.
   */
  public function testRoutesSubmissionReleaseFeedbackAndDeadlineEvents(): void {
    $routes = [
      ['submission', 'lms_answer'],
      ['course_release', 'group'],
      ['lesson_release', 'lms_lesson'],
      ['activity_release', 'lms_activity'],
      ['feedback', 'comment'],
      ['deadline', 'group_relationship'],
      ['deadline_reminder', 'group_relationship'],
    ];
    $manager = $this->createMock(LearningNoticeManagerInterface::class);
    $manager->expects(self::exactly(count($routes)))
      ->method('record')
      ->willReturnCallback(static function (string $uuid, string $type, int $uid, bool $email, string $version) use (&$routes): void {
        $expected = array_shift($routes);
        self::assertSame('certificate-uuid', $uuid);
        self::assertSame($expected[0], $type);
        self::assertSame(27, $uid);
        self::assertFalse($email);
        self::assertSame('v1', $version);
      });
    $subscriber = new LearningNoticeEventSubscriber($manager, new NullLogger());

    foreach ([
      ['submission', 'lms_answer'],
      ['course_release', 'group'],
      ['lesson_release', 'lms_lesson'],
      ['activity_release', 'lms_activity'],
      ['feedback', 'comment'],
      ['deadline', 'group_relationship'],
      ['deadline_reminder', 'group_relationship'],
    ] as [$type, $entityType]) {
      $subscriber->consume($this->event(
        'lms.' . $type,
        $entityType,
        ['notice_type' => $type, 'mail_policy' => 'site_digest'],
      ));
    }
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
