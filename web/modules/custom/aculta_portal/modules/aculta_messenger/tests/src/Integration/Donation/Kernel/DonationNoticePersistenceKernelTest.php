<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Integration\Donation\Kernel;

use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Integration\Donation\DonationNoticeManager;
use Drupal\aculta_messenger\Integration\Donation\EventSubscriber\DonationNoticeEventConsumer;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\message\Entity\MessageTemplate;
use Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\NullLogger;

/**
 * Verifies distinct Donation events persist in the shared Message storage.
 */
#[Group('aculta_messenger')]
#[RunTestsInSeparateProcesses]
final class DonationNoticePersistenceKernelTest extends KernelTestBase {

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
      'template' => DonationNoticeManager::TEMPLATE,
      'label' => 'Donation notice',
      'description' => 'Kernel fixture.',
      'text' => [['value' => 'Notice', 'format' => 'plain_text']],
    ])->save();

    $this->installField('field_aculta_don_event', 'string', ['max_length' => 64]);
    $this->installField('field_aculta_don_src_uuid', 'string', ['max_length' => 128]);
    $this->installField('field_aculta_don_ord_uuid', 'string', ['max_length' => 128]);
    $this->installField('field_aculta_don_read', 'boolean');
    $this->installField('field_aculta_don_digest', 'boolean');
    $this->installField('field_aculta_msg_delivery', 'string', ['max_length' => 16]);
    $this->installField('field_aculta_messenger_attempts', 'integer');
  }

  /**
   * Required site notices are distinct and replay-safe with e-mail disabled. */
  public function testOrderAndEachApprovedChargeAreDistinctAndIdempotent(): void {
    $user = User::create([
      'name' => 'donation_notice_recipient',
      'mail' => 'donation-notice@example.invalid',
      'status' => 1,
    ]);
    $user->save();
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->with((int) $user->id(), 'doacao')
      ->willReturn(AvisoPreferencias::EMAIL_OFF);
    $consumer = new DonationNoticeEventConsumer($this->manager($preferences, $user));

    $orderEvent = $this->event((int) $user->id(), 'donation.order_placed', 'commerce_order', 'order-1');
    $firstCharge = $this->event((int) $user->id(), 'donation.payment_approved', 'commerce_payment', 'payment-1');
    $secondCharge = $this->event((int) $user->id(), 'donation.payment_approved', 'commerce_payment', 'payment-2');
    $consumer->consume($orderEvent);
    $consumer->consume($orderEvent);
    $consumer->consume($firstCharge);
    $consumer->consume($firstCharge);
    $consumer->consume($secondCharge);

    $storage = $this->container->get('entity_type.manager')->getStorage('message');
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('template', DonationNoticeManager::TEMPLATE)
      ->sort('created')
      ->execute();
    self::assertCount(3, $ids);
    $messages = array_values($storage->loadMultiple($ids));
    self::assertSame([
      'donation.order_placed',
      'donation.payment_approved',
      'donation.payment_approved',
    ], array_map(static fn ($message): string => $message->get('field_aculta_don_event')->value, $messages));
    self::assertSame(['order-1', 'payment-1', 'payment-2'], array_map(
      static fn ($message): string => $message->get('field_aculta_don_src_uuid')->value,
      $messages,
    ));
    self::assertSame([1, 1, 1], array_map(
      static fn ($message): int => (int) $message->get('field_aculta_don_digest')->value,
      $messages,
    ));
  }

  /**
   * An inaccessible source cannot create a persistent site or email notice. */
  public function testInaccessibleOrderIsRejectedBeforePersistence(): void {
    $user = User::create([
      'name' => 'donation_notice_denied',
      'mail' => 'denied@example.invalid',
      'status' => 1,
    ]);
    $user->save();
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->willReturn(AvisoPreferencias::EMAIL_OFF);
    $consumer = new DonationNoticeEventConsumer($this->manager($preferences, $user, FALSE));

    try {
      $consumer->consume($this->event((int) $user->id(), 'donation.order_placed', 'commerce_order', 'order-1'));
      self::fail('An inaccessible Donation order must be rejected.');
    }
    catch (\RuntimeException $exception) {
      self::assertSame('Donation notice source or recipient failed revalidation.', $exception->getMessage());
    }

    $storage = $this->container->get('entity_type.manager')->getStorage('message');
    self::assertSame(0, (int) $storage->getQuery()->accessCheck(FALSE)->count()->execute());
  }

  /**
   * Read state persists on the existing Message and remains owner-protected.
   */
  public function testReadStatePersistsAndRejectsAnotherAccount(): void {
    $user = User::create([
      'name' => 'donation_read_recipient',
      'mail' => 'donation-read@example.invalid',
      'status' => 1,
    ]);
    $user->save();
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->willReturn(AvisoPreferencias::EMAIL_OFF);
    $manager = $this->manager($preferences, $user);
    (new DonationNoticeEventConsumer($manager))->consume(
      $this->event((int) $user->id(), 'donation.order_placed', 'commerce_order', 'order-1'),
    );

    $storage = $this->container->get('entity_type.manager')->getStorage('message');
    $message = $storage->load(reset($storage->getQuery()->accessCheck(FALSE)->execute()));
    self::assertTrue($manager->setRead($message, $user, TRUE));
    self::assertSame(1, (int) $storage->load($message->id())->get('field_aculta_don_read')->value);

    $other = User::create([
      'name' => 'other_donation_reader',
      'mail' => 'other-reader@example.invalid',
      'status' => 1,
    ]);
    $other->save();
    self::assertFalse($manager->setRead($message, $other, FALSE));
    self::assertSame(1, (int) $storage->load($message->id())->get('field_aculta_don_read')->value);
    self::assertTrue($manager->setRead($message, $user, FALSE));
    self::assertSame(0, (int) $storage->load($message->id())->get('field_aculta_don_read')->value);
  }

  /**
   * Builds one canonical Donation envelope. */
  private function event(int $uid, string $type, string $entityType, string $uuid): MessengerNoticeEvent {
    return new MessengerNoticeEvent(
      $type,
      'aculta_donation',
      $entityType,
      $uuid,
      'v1',
      $uid,
      'account',
      new \DateTimeImmutable('2026-10-24T12:00:00+00:00'),
      $type . ':' . $uuid . ':' . $uid,
      [
        'notice_type' => $type === 'donation.order_placed' ? 'order_placed' : 'payment_approved',
        'mail_policy' => 'user_preference',
        'order_uuid' => 'order-1',
      ],
    );
  }

  /**
   * Builds the production manager with isolated external services. */
  private function manager(AvisoPreferenciasInterface $preferences, User $user, bool $orderAccessible = TRUE): DonationNoticeManager {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $orderItem = $this->createMock(OrderItemInterface::class);
    $orderItem->method('bundle')->willReturn('donation');
    $order = $this->createMock(OrderInterface::class);
    $order->method('id')->willReturn('100');
    $order->method('uuid')->willReturn('order-1');
    $order->method('bundle')->willReturn('doacao');
    $order->method('getCustomerId')->willReturn((int) $user->id());
    $order->method('getItems')->willReturn([$orderItem]);
    $order->method('access')->willReturn($orderAccessible ? AccessResult::allowed() : AccessResult::forbidden());
    $orderStorage = $this->createMock(EntityStorageInterface::class);
    $orderStorage->method('loadByProperties')->willReturn([$order]);
    $paymentStorage = $this->createMock(EntityStorageInterface::class);
    $paymentStorage->method('loadByProperties')->willReturnCallback(function (array $properties) use ($order): array {
      $state = $this->createMock(StateItemInterface::class);
      $state->method('getId')->willReturn('completed');
      $payment = $this->createMock(PaymentInterface::class);
      $payment->method('getOrderId')->willReturn((int) $order->id());
      $payment->method('getState')->willReturn($state);
      $payment->method('getRemoteState')->willReturn('approved');
      $payment->method('uuid')->willReturn($properties['uuid']);
      return [$payment];
    });
    $realEntityManager = $this->container->get('entity_type.manager');
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('isHttps')->willReturn(TRUE);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->with('loja_aculta_org')->willReturn($domain);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnCallback(static fn (string $type): EntityStorageInterface => match ($type) {
      'user' => $realEntityManager->getStorage('user'),
      'message' => $realEntityManager->getStorage('message'),
      'domain' => $domainStorage,
      'commerce_order' => $orderStorage,
      'commerce_payment' => $paymentStorage,
      default => throw new \UnexpectedValueException('Unexpected entity storage: ' . $type),
    });
    return new DonationNoticeManager(
      $entityTypeManager,
      new DomainPurposeManager(
        $entityTypeManager,
        $this->createMock(DomainNegotiatorInterface::class),
        $this->createMock(ConfigFactoryInterface::class),
        new RequestStack(),
      ),
      $preferences,
      $this->createMock(QueueFactory::class),
      $lock,
      new NullLogger(),
    );
  }

  /**
   * Installs one Message field on the Donation fixture template. */
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
      'bundle' => DonationNoticeManager::TEMPLATE,
      'field_name' => $name,
      'label' => $name,
    ])->save();
  }

}
