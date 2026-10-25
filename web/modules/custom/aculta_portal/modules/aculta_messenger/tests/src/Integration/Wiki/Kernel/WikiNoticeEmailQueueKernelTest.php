<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Integration\Wiki\Kernel;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\SendResult;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_messenger\Integration\Wiki\Plugin\QueueWorker\WikiNoticeEmailWorker;
use Drupal\aculta_messenger\Integration\Wiki\WikiNoticeManager;
use Drupal\aculta_messenger\Integration\Wiki\WikiNoticeManagerInterface;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\Message;
use Drupal\message\Entity\MessageTemplate;
use Drupal\user\Entity\User;
use Drupal\user\UserDataInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Covers direct Wiki delivery retries using Drupal's database queue API.
 */
#[Group('aculta_messenger')]
#[RunTestsInSeparateProcesses]
final class WikiNoticeEmailQueueKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'options',
    'message',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('message');
    $this->installConfig(['system', 'user', 'filter', 'message']);
    MessageTemplate::create([
      'template' => WikiNoticeManager::TEMPLATE,
      'label' => 'Wiki notice',
      'description' => 'Queue retry fixture.',
      'text' => [['value' => 'Notice', 'format' => 'plain_text']],
    ])->save();

    $this->installField('field_aculta_wiki_event_type', 'string', ['max_length' => 32]);
    $this->installField('field_aculta_wiki_source_uuid', 'string', ['max_length' => 64]);
    $this->installField('field_aculta_wiki_event_version', 'string', ['max_length' => 64]);
    $this->installField('field_aculta_wiki_read', 'boolean');
    $this->installField('field_aculta_wiki_digest_sent', 'boolean');
    $this->installField('field_aculta_msg_delivery', 'string', ['max_length' => 16]);
    $this->installField('field_aculta_messenger_attempts', 'integer');
  }

  /**
   * A failed transport stays queued and succeeds on the next claim.
   */
  public function testTransportFailureCanBeClaimedAndRetried(): void {
    $user = User::create([
      'name' => 'wiki_queue_recipient',
      'mail' => 'wiki-queue@example.invalid',
      'status' => 1,
    ]);
    $user->save();
    $message = Message::create([
      'template' => WikiNoticeManager::TEMPLATE,
      'uid' => $user->id(),
      'field_aculta_wiki_event_type' => 'proposal_outcome',
      'field_aculta_wiki_source_uuid' => 'proposal-queue-uuid',
      'field_aculta_wiki_event_version' => 'revision-1',
      'field_aculta_wiki_read' => 0,
      'field_aculta_wiki_digest_sent' => 1,
      'field_aculta_msg_delivery' => 'pending',
      'field_aculta_messenger_attempts' => 0,
    ]);
    $message->save();

    $notices = $this->createMock(WikiNoticeManagerInterface::class);
    $notices->expects(self::exactly(2))
      ->method('resolve')
      ->willReturnCallback(static function ($resolvedMessage, $recipient) use ($message, $user): array {
        self::assertSame($message->id(), $resolvedMessage->id());
        self::assertSame($user->id(), $recipient->id());
        return [
          'email_summary' => 'Uma atualização da Wiki está disponível.',
          'url' => Url::fromUri('https://aculta.example/wiki/proposta'),
        ];
      });
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->expects(self::exactly(2))->method('emailMode')
      ->with((int) $user->id(), 'wiki')->willReturn(AvisoPreferencias::EMAIL_IMMEDIATE);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $attempt = 0;
    $mailer->expects(self::exactly(2))->method('deliver')->willReturnCallback(
      static function (MailMessage $mail) use (&$attempt): SendResult {
        self::assertSame('wiki_personal_notice', $mail->type);
        if ($attempt++ === 0) {
          throw new \RuntimeException('temporary transport failure');
        }
        return SendResult::sent();
      },
    );
    $lock = $this->createMock(LockBackendInterface::class);
    $lockId = 'aculta_wiki_email:' . $message->id();
    $lock->expects(self::exactly(2))->method('acquire')->with($lockId, 180.0)->willReturn(TRUE);
    $lock->expects(self::exactly(2))->method('release')->with($lockId);
    $worker = new WikiNoticeEmailWorker(
      [],
      'aculta_wiki_messenger_email',
      ['cron' => ['time' => 60]],
      $this->container->get('entity_type.manager'),
      $notices,
      $preferences,
      $this->emailPolicy(TRUE),
      $mailer,
      $lock,
    );

    /** @var \Drupal\Core\Queue\QueueFactory $queueFactory */
    $queueFactory = $this->container->get('queue');
    $queue = $queueFactory->get('aculta_wiki_messenger_email');
    $queue->createItem(['message_id' => (int) $message->id()]);
    $item = $queue->claimItem(60);
    self::assertNotFalse($item);

    try {
      $worker->processItem($item->data);
      self::fail('A transient Mailer error must leave the queue item retryable.');
    }
    catch (\RuntimeException $exception) {
      self::assertSame('Wiki notice email delivery failed.', $exception->getMessage());
    }

    self::assertSame(1, $queue->numberOfItems());
    $storage = $this->container->get('entity_type.manager')->getStorage('message');
    $message = $storage->load($message->id());
    self::assertSame('failed', $message->get('field_aculta_msg_delivery')->value);
    self::assertSame(1, (int) $message->get('field_aculta_messenger_attempts')->value);
    self::assertFalse($queue->claimItem(60), 'A claimed item waits for its Queue API lease.');

    // Expire only this fixture item as if its worker process had ended.
    $this->container->get('database')->update('queue')
      ->fields(['expire' => 0])
      ->condition('item_id', $item->item_id)
      ->execute();
    $retry = $queue->claimItem(60);
    self::assertNotFalse($retry);
    $worker->processItem($retry->data);
    $queue->deleteItem($retry);

    $message = $storage->load($message->id());
    self::assertSame('sent', $message->get('field_aculta_msg_delivery')->value);
    self::assertSame(2, (int) $message->get('field_aculta_messenger_attempts')->value);
    self::assertSame(0, $queue->numberOfItems());
  }

  /**
   * Email opt-out cancels delivery while preserving the site notice.
   */
  public function testOptOutCancelsOnlyTheEmailChannel(): void {
    $user = User::create([
      'name' => 'wiki_optout_recipient',
      'mail' => 'wiki-optout@example.invalid',
      'status' => 1,
    ]);
    $user->save();
    $message = Message::create([
      'template' => WikiNoticeManager::TEMPLATE,
      'uid' => $user->id(),
      'field_aculta_wiki_event_type' => 'proposal_outcome',
      'field_aculta_wiki_source_uuid' => 'proposal-optout-uuid',
      'field_aculta_wiki_event_version' => 'revision-1',
      'field_aculta_wiki_read' => 0,
      'field_aculta_wiki_digest_sent' => 1,
      'field_aculta_msg_delivery' => 'pending',
      'field_aculta_messenger_attempts' => 0,
    ]);
    $message->save();

    $notices = $this->createMock(WikiNoticeManagerInterface::class);
    $notices->expects(self::never())->method('resolve');
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->expects(self::once())->method('emailMode')
      ->with((int) $user->id(), 'wiki')->willReturn(AvisoPreferencias::EMAIL_OFF);
    $mailer = $this->createMock(AcultaMailerInterface::class);
    $mailer->expects(self::never())->method('deliver');
    $lock = $this->createMock(LockBackendInterface::class);
    $lockId = 'aculta_wiki_email:' . $message->id();
    $lock->expects(self::once())->method('acquire')->with($lockId, 180.0)->willReturn(TRUE);
    $lock->expects(self::once())->method('release')->with($lockId);
    $worker = new WikiNoticeEmailWorker(
      [],
      'aculta_wiki_messenger_email',
      ['cron' => ['time' => 60]],
      $this->container->get('entity_type.manager'),
      $notices,
      $preferences,
      $this->emailPolicy(TRUE),
      $mailer,
      $lock,
    );

    $queue = $this->container->get('queue')->get('aculta_wiki_messenger_email');
    $queue->createItem(['message_id' => (int) $message->id()]);
    $item = $queue->claimItem(60);
    self::assertNotFalse($item);
    $worker->processItem($item->data);
    $queue->deleteItem($item);

    $persisted = $this->container->get('entity_type.manager')->getStorage('message')->load($message->id());
    self::assertInstanceOf(Message::class, $persisted, 'The required in-site notice remains persisted.');
    self::assertSame('cancelled', $persisted->get('field_aculta_msg_delivery')->value);
    self::assertSame(0, (int) $persisted->get('field_aculta_wiki_read')->value, 'Cancelling email does not mark the site notice read.');
    self::assertSame(0, $queue->numberOfItems());
  }

  /**
   * Creates a confirmation policy fixture with a controlled account state. */
  private function emailPolicy(bool $confirmed): EmailConfirmationPolicy {
    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturn($confirmed);
    return new EmailConfirmationPolicy(
      $userData,
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(ModuleHandlerInterface::class),
    );
  }

  /**
   * Installs a field on the Wiki notice template.
   */
  private function installField(string $name, string $type, array $settings = []): void {
    FieldStorageConfig::create([
      'entity_type' => 'message',
      'field_name' => $name,
      'type' => $type,
      'settings' => $settings,
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'message',
      'bundle' => WikiNoticeManager::TEMPLATE,
      'field_name' => $name,
      'label' => $name,
    ])->save();
  }

}
