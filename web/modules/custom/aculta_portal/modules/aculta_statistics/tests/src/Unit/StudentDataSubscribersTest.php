<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\EventSubscriber\LmsStudentStatusSubscriber;
use Drupal\aculta_statistics\EventSubscriber\StudentDataPurgeSubscriber;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Dados por aluno: o instantâneo grava uma linha por curso, aluno e situação, e a exclusão ou revogação apaga tudo.
 */
#[CoversClass(LmsStudentStatusSubscriber::class)]
#[CoversClass(StudentDataPurgeSubscriber::class)]
#[Group('aculta_statistics')]
final class StudentDataSubscribersTest extends TestCase {

  /**
   * Cada linha vira `curso/aluno|situação` com contagem 1, e o dia é substituído.
   */
  public function testSnapshotWritesOneRowPerStudentAndSituation(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->method('currentDay')->willReturn(20000);
    $storage->expects($this->once())->method('replaceDay')->with(
      'lms.student.status',
      20000,
      ['5/42|in_progress' => 1, '5/43|passed' => 1],
    );

    $event = new class {
      public array $rows = [
        ['course' => 5, 'uid' => 42, 'situation' => 'in_progress'],
        ['course' => 5, 'uid' => 43, 'situation' => 'passed'],
      ];
    };
    (new LmsStudentStatusSubscriber($storage))->onSnapshot($event);
  }

  /**
   * Conta excluída e revogação apagam as linhas daquele aluno da métrica restrita.
   */
  public function testAccountDeletionAndRevocationPurgeTheStudent(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->exactly(2))->method('purgeStudent')->with('lms.student.status', 42);

    $subscriber = new StudentDataPurgeSubscriber($storage);
    $subscriber->onAccountDeleted(new class {
      public int $uid = 42;
    });
    $subscriber->onConsentRevoked(new class {
      public int $uid = 42;
      public string $agreementType = 'aculta_user_agreement_terms';
    });
  }

  /**
   * Os nomes assinados são os publicados pelo aculta_lms e pelo aculta_user.
   */
  public function testSubscribedEventNames(): void {
    $this->assertSame(['aculta_lms.student_status_snapshot' => 'onSnapshot'], LmsStudentStatusSubscriber::getSubscribedEvents());
    $this->assertSame(
      ['aculta_user.account_deleted' => 'onAccountDeleted', 'aculta_user.consent_revoked' => 'onConsentRevoked'],
      StudentDataPurgeSubscriber::getSubscribedEvents(),
    );
  }

}
