<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\EventSubscriber\LmsEventsSubscriber;
use Drupal\aculta_statistics\Recorder\StatisticsRecorder;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Eventos do LMS contados por curso, sem dado de aluno.
 */
#[CoversClass(LmsEventsSubscriber::class)]
#[Group('aculta_statistics')]
final class LmsEventsSubscriberTest extends TestCase {

  /**
   * Os três eventos do LMS estão mapeados.
   */
  public function testSubscribesToLmsDomainEvents(): void {
    $events = array_keys(LmsEventsSubscriber::getSubscribedEvents());

    $this->assertContains('Drupal\aculta_lms\Event\CourseEnrollmentEvent', $events);
    $this->assertContains('Drupal\aculta_lms\Event\CourseCompletedEvent', $events);
    $this->assertContains('Drupal\aculta_lms\Event\CertificateIssuedEvent', $events);
  }

  /**
   * Matrícula conta por curso, sem o aluno.
   */
  public function testEnrollmentIsCountedByCourse(): void {
    $calls = [];
    $this->subscriber($calls)->onEnrollment((object) ['courseId' => '42', 'studentId' => '99']);

    $this->assertSame([['lms.enrollments.created', '42', 1]], $calls);
  }

  /**
   * Conclusão repetida do mesmo status não conta duas vezes.
   */
  public function testCompletionIsCountedOncePerStatus(): void {
    $calls = [];
    $subscriber = $this->subscriber($calls);
    $event = (object) ['sourceStatusUuid' => 'status-1', 'courseId' => '42'];

    $subscriber->onCompleted($event);
    $subscriber->onCompleted($event);

    $this->assertSame([['lms.courses.completed', '42', 1]], $calls);
  }

  /**
   * Certificado conta o total, uma vez por certificado.
   */
  public function testCertificateIsCountedOncePerCertificate(): void {
    $calls = [];
    $subscriber = $this->subscriber($calls);
    $event = (object) ['certificateUuid' => 'cert-1'];

    $subscriber->onCertificate($event);
    $subscriber->onCertificate($event);

    $this->assertSame([['lms.certificates.issued', '', 1]], $calls);
  }

  /**
   * Cria o assinante com um armazenamento de deduplicação em memória e captura das gravações.
   *
   * @param array<int, array{0: string, 1: string, 2: int}> $calls
   *   Gravações capturadas (métrica, dimensão, quantidade).
   */
  private function subscriber(array &$calls): LmsEventsSubscriber {
    $seen = [];
    $expirable = $this->createMock(KeyValueStoreExpirableInterface::class);
    $expirable->method('setWithExpireIfNotExists')->willReturnCallback(
      static function (string $key) use (&$seen): bool {
        if (isset($seen[$key])) {
          return FALSE;
        }
        $seen[$key] = TRUE;
        return TRUE;
      },
    );
    $factory = $this->createMock(KeyValueExpirableFactoryInterface::class);
    $factory->method('get')->willReturn($expirable);

    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->method('record')->willReturnCallback(static function (string $metric, string $dimension, int $amount) use (&$calls): void {
      $calls[] = [$metric, $dimension, $amount];
    });

    return new LmsEventsSubscriber(new StatisticsRecorder(new MetricCatalog(), $storage), $factory);
  }

}
