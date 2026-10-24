<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Integration\Commerce\Kernel;

use Drupal\aculta_messenger\Integration\Commerce\CommerceOrderNoticeManager;
use Drupal\aculta_messenger\Integration\Commerce\EventSubscriber\CommerceOrderNoticeConsumer;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_portal\Checkout\CheckoutSettings;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\MessageTemplate;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\NullLogger;

/**
 * Tests Store event delivery through real shared Message storage. */
#[Group('aculta_messenger')]
#[RunTestsInSeparateProcesses]
final class CommerceOrderNoticePersistenceKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc} */
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
   * {@inheritdoc} */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('message');
    $this->installConfig(['system', 'user', 'filter', 'message']);
    MessageTemplate::create([
      'template' => CommerceOrderNoticeManager::TEMPLATE,
      'label' => 'Store order notice',
      'description' => 'Kernel fixture.',
      'text' => [['value' => 'Notice', 'format' => 'plain_text']],
    ])->save();

    $this->installField('field_aculta_commerce_event_type', 'string', ['max_length' => 32]);
    $this->installField('field_aculta_commerce_uuid', 'string', ['max_length' => 64]);
    $this->installField('field_aculta_commerce_version', 'string', ['max_length' => 64]);
    $this->installField('field_aculta_commerce_read', 'boolean');
    $this->installField('field_aculta_commerce_digest', 'boolean');
    $this->installField('field_aculta_msg_delivery', 'string', ['max_length' => 16]);
    $this->installField('field_aculta_messenger_attempts', 'integer');
  }

  /**
   * Site notice remains while opted out; event replay stays idempotent. */
  public function testOffPreferenceKeepsOneRequiredSiteNotice(): void {
    $user = $this->createUser();
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with((int) $user->id(), 'loja')->willReturn(AvisoPreferencias::EMAIL_OFF);
    $consumer = new CommerceOrderNoticeConsumer($this->manager($preferences));
    $event = $this->event((int) $user->id());

    $consumer->consume($event);
    $consumer->consume($event);

    $storage = $this->container->get('entity_type.manager')->getStorage('message');
    self::assertSame(1, (int) $storage->getQuery()->accessCheck(FALSE)->count()->execute());
    $message = $storage->load(reset($storage->getQuery()->accessCheck(FALSE)->execute()));
    self::assertSame(1, (int) $message->get('field_aculta_commerce_digest')->value);
    self::assertSame('sent', $message->get('field_aculta_msg_delivery')->value);
  }

  /**
   * Direct selection queues one Mailer delivery for one site notice. */
  public function testImmediatePreferenceQueuesOneEmailForOneSiteNotice(): void {
    $user = $this->createUser();
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with((int) $user->id(), 'loja')->willReturn(AvisoPreferencias::EMAIL_IMMEDIATE);
    $queue = $this->createMock(QueueInterface::class);
    $queue->expects(self::once())->method('createItem')->willReturn(1);
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->expects(self::once())->method('get')->with('aculta_commerce_messenger_email')->willReturn($queue);
    $consumer = new CommerceOrderNoticeConsumer($this->manager($preferences, $queueFactory));
    $event = $this->event((int) $user->id());

    $consumer->consume($event);
    $consumer->consume($event);

    $storage = $this->container->get('entity_type.manager')->getStorage('message');
    self::assertSame(1, (int) $storage->getQuery()->accessCheck(FALSE)->count()->execute());
    $message = $storage->load(reset($storage->getQuery()->accessCheck(FALSE)->execute()));
    self::assertSame(1, (int) $message->get('field_aculta_commerce_digest')->value);
    self::assertSame('pending', $message->get('field_aculta_msg_delivery')->value);
  }

  /**
   * Creates an active account for the notice recipient.
   */
  private function createUser(): User {
    $user = User::create([
      'name' => 'store_notice_recipient',
      'mail' => 'store-notice@example.invalid',
      'status' => 1,
    ]);
    $user->save();
    return $user;
  }

  /**
   * Builds the canonical Store order event.
   */
  private function event(int $uid): MessengerNoticeEvent {
    return new MessengerNoticeEvent(
      'store.order_placed',
      'aculta_commerce',
      'commerce_order',
      'order-uuid',
      '1',
      $uid,
      'shop',
      new \DateTimeImmutable('2026-10-24T12:00:00+00:00'),
      'store.order_placed:order-uuid:1:' . $uid,
      [
        'notice_type' => 'order_placed',
        'mail_policy' => 'user_preference',
        'delivery_override' => NULL,
      ],
    );
  }

  /**
   * Creates the real Message manager with isolated dependencies.
   */
  private function manager(AvisoPreferenciasInterface $preferences, ?QueueFactory $queueFactory = NULL): CommerceOrderNoticeManager {
    $queueFactory ??= $this->createMock(QueueFactory::class);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    return new CommerceOrderNoticeManager(
      $this->container->get('entity_type.manager'),
      (new \ReflectionClass(DomainPurposeManager::class))->newInstanceWithoutConstructor(),
      (new \ReflectionClass(CheckoutSettings::class))->newInstanceWithoutConstructor(),
      $preferences,
      $queueFactory,
      $lock,
      new NullLogger(),
    );
  }

  /**
   * Creates one field storage/config pair for the fixture bundle.
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
      'bundle' => CommerceOrderNoticeManager::TEMPLATE,
      'field_name' => $name,
      'label' => $name,
    ])->save();
  }

}
