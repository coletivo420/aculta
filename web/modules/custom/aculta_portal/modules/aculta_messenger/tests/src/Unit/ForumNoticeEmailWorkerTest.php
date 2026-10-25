<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\DeliveryError;
use Drupal\aculta_mailer\Delivery\SendResult;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_mailer\Message\MessageIdFactory;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_messenger\Aviso\ForumNoticeAccessPolicyInterface;
use Drupal\aculta_messenger\Aviso\ForumNoticeEmailOutbox;
use Drupal\aculta_messenger\Aviso\ForumNoticeEmailOutboxInterface;
use Drupal\aculta_messenger\Mail\ForumNoticeMailType;
use Drupal\aculta_messenger\Plugin\QueueWorker\ForumNoticeEmailWorker;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\comment\CommentInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\DomainInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlaggingInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Drupal\flag\FlagServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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

  /**
   * A Mailer exception consumes the outbox attempt and schedules a retry.
   */
  public function testMailerExceptionUsesBoundedOutboxRetry(): void {
    $event = [
      'id' => 17,
      'uid' => 12,
      'event_type' => 'comment',
      'event_id' => 35,
      'nid' => 72,
      'email_status' => ForumNoticeEmailOutbox::STATUS_PROCESSING,
      'email_attempts' => 1,
      'email_correlation' => '01234567-89ab-4cde-8fab-0123456789ab',
    ];
    $outbox = $this->createMock(ForumNoticeEmailOutboxInterface::class);
    $outbox->expects(self::once())->method('claim')->with(17)->willReturn($event);
    $outbox->expects(self::once())->method('setStatus')
      ->with(17, ForumNoticeEmailOutbox::STATUS_PENDING);

    $recipient = $this->createMock(UserInterface::class);
    $recipient->method('id')->willReturn('12');
    $recipient->method('isActive')->willReturn(TRUE);
    $recipient->method('getEmail')->willReturn('member@example.test');
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('id')->willReturn('72');
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('getCommentedEntity')->willReturn($topic);
    $comment->method('getOwnerId')->willReturn(19);
    $flag = $this->createMock(FlagInterface::class);
    $flagging = $this->createMock(FlaggingInterface::class);
    $flagging->method('getOwnerId')->willReturn(12);

    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(12)->willReturn($recipient);
    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('load')->with(72)->willReturn($topic);
    $commentStorage = $this->createMock(EntityStorageInterface::class);
    $commentStorage->method('load')->with(35)->willReturn($comment);
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('isHttps')->willReturn(TRUE);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->expects(self::once())->method('load')->with('forum_aculta_org')->willReturn($domain);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'user' => $userStorage,
      'node' => $nodeStorage,
      'comment' => $commentStorage,
      'domain' => $domainStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });

    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->with('aculta_portal', 12, 'email_confirmed')->willReturn(TRUE);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with(12, 'forum_resposta')->willReturn(AvisoPreferencias::EMAIL_IMMEDIATE);
    $access = $this->createMock(ForumNoticeAccessPolicyInterface::class);
    $access->method('canViewTopic')->willReturn(TRUE);
    $access->method('canViewComment')->willReturn(TRUE);
    $flags = $this->createMock(FlagServiceInterface::class);
    $flags->method('getFlagById')->with('seguir_topico')->willReturn($flag);
    $flags->method('getEntityFlaggings')->with($flag, $topic)->willReturn([$flagging]);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::once())->method('deliver')
      ->with(self::callback(fn(MailMessage $mail): bool => $mail->correlationId === MessageIdFactory::correlationIdFor(
        'aculta_messenger:forum:' . $event['id'] . ':' . ForumNoticeMailType::ID,
      )))
      ->willThrowException(new \RuntimeException('Transport failure'));
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $domainNegotiator = $this->createMock(DomainNegotiatorInterface::class);
    $domainNegotiator->method('getActiveDomain')->willReturn(NULL);

    $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $urlGenerator->expects(self::once())->method('generateFromRoute')->willReturn('https://forum.example.test/topicos/72');
    $container = new ContainerBuilder();
    $container->set('url_generator', $urlGenerator);
    \Drupal::setContainer($container);
    try {
      $worker = new ForumNoticeEmailWorker(
        [],
        ForumNoticeEmailOutbox::QUEUE,
        [],
        $entities,
        $outbox,
        $preferences,
        $access,
        $flags,
        new EmailConfirmationPolicy($userData, $entities, $this->createMock(ModuleHandlerInterface::class)),
        new DomainPurposeManager(
          $entities,
          $domainNegotiator,
          $this->createMock(ConfigFactoryInterface::class),
          new RequestStack(),
        ),
        $mailer,
        $lock,
      );
      try {
        $worker->processItem(['event_id' => 17]);
        self::fail('A transient delivery failure must request a retry below the attempt limit.');
      }
      catch (\RuntimeException $exception) {
        self::assertSame('Forum direct notice email delivery failed.', $exception->getMessage());
      }
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

  /**
   * A permanent Mailer result marks the outbox terminal without retry.
   */
  public function testPermanentMailerFailureMarksOutboxFailed(): void {
    $event = [
      'id' => 17,
      'uid' => 12,
      'event_type' => 'comment',
      'event_id' => 35,
      'nid' => 72,
      'email_status' => ForumNoticeEmailOutbox::STATUS_PROCESSING,
      'email_attempts' => 1,
      'email_correlation' => '01234567-89ab-4cde-8fab-0123456789ab',
    ];
    $outbox = $this->createMock(ForumNoticeEmailOutboxInterface::class);
    $outbox->expects(self::once())->method('claim')->with(17)->willReturn($event);
    $outbox->expects(self::once())->method('setStatus')
      ->with(17, ForumNoticeEmailOutbox::STATUS_FAILED);

    $recipient = $this->createMock(UserInterface::class);
    $recipient->method('id')->willReturn('12');
    $recipient->method('isActive')->willReturn(TRUE);
    $recipient->method('getEmail')->willReturn('member@example.test');
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('id')->willReturn('72');
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('getCommentedEntity')->willReturn($topic);
    $comment->method('getOwnerId')->willReturn(19);
    $flag = $this->createMock(FlagInterface::class);
    $flagging = $this->createMock(FlaggingInterface::class);
    $flagging->method('getOwnerId')->willReturn(12);

    $userStorage = $this->createMock(EntityStorageInterface::class);
    $userStorage->method('load')->with(12)->willReturn($recipient);
    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('load')->with(72)->willReturn($topic);
    $commentStorage = $this->createMock(EntityStorageInterface::class);
    $commentStorage->method('load')->with(35)->willReturn($comment);
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('isHttps')->willReturn(TRUE);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->expects(self::once())->method('load')->with('forum_aculta_org')->willReturn($domain);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => match ($type) {
      'user' => $userStorage,
      'node' => $nodeStorage,
      'comment' => $commentStorage,
      'domain' => $domainStorage,
      default => throw new \InvalidArgumentException('Unexpected storage: ' . $type),
    });

    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->with('aculta_portal', 12, 'email_confirmed')->willReturn(TRUE);
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with(12, 'forum_resposta')->willReturn(AvisoPreferencias::EMAIL_IMMEDIATE);
    $access = $this->createMock(ForumNoticeAccessPolicyInterface::class);
    $access->method('canViewTopic')->willReturn(TRUE);
    $access->method('canViewComment')->willReturn(TRUE);
    $flags = $this->createMock(FlagServiceInterface::class);
    $flags->method('getFlagById')->with('seguir_topico')->willReturn($flag);
    $flags->method('getEntityFlaggings')->with($flag, $topic)->willReturn([$flagging]);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::once())->method('deliver')
      ->with(self::callback(fn(MailMessage $mail): bool => $mail->correlationId === MessageIdFactory::correlationIdFor(
        'aculta_messenger:forum:' . $event['id'] . ':' . ForumNoticeMailType::ID,
      )))
      ->willReturn(SendResult::failed(DeliveryError::Permanent, 550));
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $domainNegotiator = $this->createMock(DomainNegotiatorInterface::class);
    $domainNegotiator->method('getActiveDomain')->willReturn(NULL);

    $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $urlGenerator->expects(self::once())->method('generateFromRoute')->willReturn('https://forum.example.test/topicos/72');
    $container = new ContainerBuilder();
    $container->set('url_generator', $urlGenerator);
    \Drupal::setContainer($container);
    try {
      $worker = new ForumNoticeEmailWorker(
        [],
        ForumNoticeEmailOutbox::QUEUE,
        [],
        $entities,
        $outbox,
        $preferences,
        $access,
        $flags,
        new EmailConfirmationPolicy($userData, $entities, $this->createMock(ModuleHandlerInterface::class)),
        new DomainPurposeManager(
          $entities,
          $domainNegotiator,
          $this->createMock(ConfigFactoryInterface::class),
          new RequestStack(),
        ),
        $mailer,
        $lock,
      );
      $worker->processItem(['event_id' => 17]);
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

}
