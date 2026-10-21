<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_messenger\Unit;

use Drupal\aculta_lms\Event\AnswerEvaluatedEvent;
use Psr\Log\LoggerInterface;
use Drupal\aculta_lms\Event\CertificateIssuedEvent;
use Drupal\aculta_lms\Event\CourseEnrollmentEvent;
use Drupal\aculta_lms\Event\CourseCompletedEvent;
use Drupal\aculta_lms_messenger\EventSubscriber\LearningNotificationSubscriber;
use Drupal\aculta_lms_messenger\LearningNoticeManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;

/**
 * @coversDefaultClass \Drupal\aculta_lms_messenger\EventSubscriber\LearningNotificationSubscriber
 */
#[CoversClass(LearningNotificationSubscriber::class)]
#[Group('aculta_lms_messenger')]
final class LearningNotificationSubscriberTest extends UnitTestCase {

  /**
   * Confirms that a persisted enrollment becomes a Messenger notice.
   */
  public function testEnrollmentCreatesMessengerNoticeAndTransactionalEmail(): void {
    $manager = $this->createMock(LearningNoticeManagerInterface::class);
    $manager->expects($this->once())
      ->method('record')
      ->with('membership-uuid', 'enrollment', 27, TRUE);

    $subscriber = new LearningNotificationSubscriber($manager, new NullLogger());
    $subscriber->courseEnrollment(new CourseEnrollmentEvent('membership-uuid', '10', '11', '27'));
  }

  /**
   * Confirms that a certificate issue becomes a Messenger notice.
   */
  public function testCertificateCreatesMessengerNoticeAndTransactionalEmail(): void {
    $manager = $this->createMock(LearningNoticeManagerInterface::class);
    $manager->expects($this->once())
      ->method('record')
      ->with('certificate-uuid', 'certificate', 27, TRUE);

    $subscriber = new LearningNotificationSubscriber($manager, new NullLogger());
    $subscriber->certificateIssued(new CertificateIssuedEvent('certificate-uuid', '27'));
  }

  /**
   * Confirms course completion is site-only and enters the shared digest.
   */
  public function testCompletionCreatesSiteNoticeWithoutTransactionalEmail(): void {
    $manager = $this->createMock(LearningNoticeManagerInterface::class);
    $manager->expects($this->once())
      ->method('record')
      ->with('status-uuid', 'completion', 27, FALSE);

    $subscriber = new LearningNotificationSubscriber($manager, new NullLogger());
    $subscriber->courseCompleted(new CourseCompletedEvent(
      'status-uuid',
      '10',
      '27',
      new \DateTimeImmutable('2026-10-20T12:00:00+00:00'),
    ));
  }

  /**
   * An evaluated answer creates only the site and digest notices. */
  public function testEvaluationCreatesSiteNoticeWithoutTransactionalEmail(): void {
    $manager = $this->createMock(LearningNoticeManagerInterface::class);
    $manager->expects($this->once())
      ->method('record')
      ->with('answer-uuid', 'evaluation', 27, FALSE, str_repeat('a', 32));

    $subscriber = new LearningNotificationSubscriber($manager, new NullLogger());
    $subscriber->answerEvaluated(new AnswerEvaluatedEvent('answer-uuid', '27', str_repeat('a', 32)));
  }

  /**
   * Confirms notification failure does not escape the source event.
   */
  public function testPersistFailureIsLoggedAndDoesNotBreakSourceTransaction(): void {
    $manager = $this->createMock(LearningNoticeManagerInterface::class);
    $manager->method('record')->willThrowException(new \RuntimeException('storage unavailable'));
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())->method('error');

    $subscriber = new LearningNotificationSubscriber($manager, $logger);
    $subscriber->certificateIssued(new CertificateIssuedEvent('certificate-uuid', '27'));
    $this->addToAssertionCount(1);
  }

}
