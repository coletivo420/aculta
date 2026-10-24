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
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\MessageTemplate;
use Drupal\user\Entity\User;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
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
   * Read state persists only for the current order owner.
   */
  public function testReadStatePersistsAndRejectsAnotherAccount(): void {
    $user = $this->createUser();
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->willReturn(AvisoPreferencias::EMAIL_OFF);
    $manager = $this->manager($preferences, NULL, $user);
    (new CommerceOrderNoticeConsumer($manager))->consume($this->event((int) $user->id()));

    $storage = $this->container->get('entity_type.manager')->getStorage('message');
    $message = $storage->load(reset($storage->getQuery()->accessCheck(FALSE)->execute()));
    self::assertTrue($manager->setRead($message, $user, TRUE));
    self::assertSame(1, (int) $storage->load($message->id())->get('field_aculta_commerce_read')->value);

    $other = $this->createUser('another_store_reader');
    self::assertFalse($manager->setRead($message, $other, FALSE));
    self::assertSame(1, (int) $storage->load($message->id())->get('field_aculta_commerce_read')->value);
    self::assertTrue($manager->setRead($message, $user, FALSE));
    self::assertSame(0, (int) $storage->load($message->id())->get('field_aculta_commerce_read')->value);
  }

  /**
   * Creates an active account for the notice recipient.
   */
  private function createUser(string $name = 'store_notice_recipient'): User {
    $user = User::create([
      'name' => $name,
      'mail' => $name . '@example.invalid',
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
  private function manager(AvisoPreferenciasInterface $preferences, ?QueueFactory $queueFactory = NULL, ?User $orderCustomer = NULL): CommerceOrderNoticeManager {
    $queueFactory ??= $this->createMock(QueueFactory::class);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    if ($orderCustomer === NULL) {
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

    $checkoutConfig = $this->createMock(ImmutableConfig::class);
    $checkoutConfig->method('get')->with('shop_order_type')->willReturn('default');
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with(CheckoutSettings::CONFIG)->willReturn($checkoutConfig);
    $order = $this->createMock(OrderInterface::class);
    $order->method('id')->willReturn('100');
    $order->method('uuid')->willReturn('order-uuid');
    $order->method('bundle')->willReturn('default');
    $order->method('getCustomerId')->willReturn((int) $orderCustomer->id());
    $order->method('access')->willReturn(AccessResult::allowed());
    $orderStorage = $this->createMock(EntityStorageInterface::class);
    $orderStorage->method('loadByProperties')->willReturn([$order]);

    $domain = $this->createMock(DomainInterface::class);
    $domain->method('isHttps')->willReturn(TRUE);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->with('loja_aculta_org')->willReturn($domain);
    $realEntityManager = $this->container->get('entity_type.manager');
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnCallback(static fn (string $type): EntityStorageInterface => match ($type) {
      'user' => $realEntityManager->getStorage('user'),
      'message' => $realEntityManager->getStorage('message'),
      'domain' => $domainStorage,
      'commerce_order' => $orderStorage,
      default => throw new \UnexpectedValueException('Unexpected entity storage: ' . $type),
    });
    return new CommerceOrderNoticeManager(
      $entityTypeManager,
      new DomainPurposeManager(
        $entityTypeManager,
        $this->createMock(DomainNegotiatorInterface::class),
        $configFactory,
        new RequestStack(),
      ),
      new CheckoutSettings($configFactory),
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
