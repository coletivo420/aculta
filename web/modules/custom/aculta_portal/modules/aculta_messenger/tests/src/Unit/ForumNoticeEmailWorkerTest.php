<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_messenger\Aviso\ForumNoticeAccessPolicyInterface;
use Drupal\aculta_messenger\Aviso\ForumNoticeEmailOutbox;
use Drupal\aculta_messenger\Aviso\ForumNoticeEmailOutboxInterface;
use Drupal\aculta_messenger\Plugin\QueueWorker\ForumNoticeEmailWorker;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Drupal\flag\FlagServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests current preference revalidation before direct Forum email. */
#[CoversClass(ForumNoticeEmailWorker::class)]
#[Group('aculta_messenger')]
final class ForumNoticeEmailWorkerTest extends UnitTestCase {

  /**
   * Cancels an already queued direct email after the user switches it off. */
  public function testCancelsWhenCurrentCategoryModeIsOff(): void {
    $record = [
      'id' => 17,
      'uid' => 12,
      'event_type' => 'comment',
      'event_id' => 35,
      'nid' => 72,
      'email_status' => 'processing',
      'email_attempts' => 1,
    ];
    $outbox = $this->createMock(ForumNoticeEmailOutboxInterface::class);
    $outbox->expects(self::once())->method('claim')->with(17)->willReturn($record);
    $outbox->expects(self::once())->method('setStatus')
      ->with(17, ForumNoticeEmailOutbox::STATUS_CANCELLED);

    $account = $this->createMock(UserInterface::class);
    $account->method('id')->willReturn('12');
    $account->method('isActive')->willReturn(TRUE);
    $account->method('getEmail')->willReturn('member@example.test');
    $users = $this->createMock(EntityStorageInterface::class);
    $users->method('load')->with(12)->willReturn($account);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'user' => $users,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });
    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->with('aculta_portal', 12, 'email_confirmed')->willReturn(TRUE);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->expects(self::once())->method('emailMode')->with(12, 'forum_resposta')->willReturn('off');
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::never())->method('deliver');
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);

    $worker = new ForumNoticeEmailWorker(
      [],
      ForumNoticeEmailOutbox::QUEUE,
      [],
      $entities,
      $outbox,
      $preferences,
      $this->createMock(ForumNoticeAccessPolicyInterface::class),
      $this->createMock(FlagServiceInterface::class),
      new EmailConfirmationPolicy($userData, $entities, $this->createMock(ModuleHandlerInterface::class)),
      new DomainPurposeManager(
        $entities,
        $this->createMock(DomainNegotiatorInterface::class),
        $this->createMock(ConfigFactoryInterface::class),
        new RequestStack(),
      ),
      $mailer,
      $lock,
    );
    $worker->processItem(['event_id' => 17]);
  }

}
