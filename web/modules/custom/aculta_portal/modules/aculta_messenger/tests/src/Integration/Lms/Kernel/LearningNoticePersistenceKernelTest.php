<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Integration\Lms\Kernel;

use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Integration\Lms\EventSubscriber\LearningNoticeEventSubscriber;
use Drupal\aculta_messenger\Integration\Lms\LearningNoticeManager;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\aculta_messenger\Service\NoticeReadStateManager;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupMembership;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\MessageTemplate;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests LMS notice persistence against real Message entities.
 */
#[Group('aculta_messenger')]
#[RunTestsInSeparateProcesses]
final class LearningNoticePersistenceKernelTest extends KernelTestBase {

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
      'template' => LearningNoticeManager::TEMPLATE,
      'label' => 'LMS notice',
      'description' => 'Kernel fixture.',
      'text' => [['value' => 'Notice', 'format' => 'plain_text']],
    ])->save();

    $this->installField('field_aculta_lms_event_type', 'string', ['max_length' => 32]);
    $this->installField('field_aculta_lms_source_uuid', 'string', ['max_length' => 64]);
    $this->installField('field_aculta_lms_event_version', 'string', ['max_length' => 64]);
    $this->installField('field_aculta_lms_read', 'boolean');
    $this->installField('field_aculta_lms_digest_sent', 'boolean');
    $this->installField('field_aculta_msg_delivery', 'string', ['max_length' => 16]);
    $this->installField('field_aculta_messenger_attempts', 'integer');
  }

  /**
   * Message storage deduplicates the same event and recipient.
   */
  public function testNoticeIsPersistedAndDeduplicated(): void {
    $learner = User::create([
      'name' => 'lms_notice_learner',
      'mail' => 'lms-notice@example.invalid',
      'status' => 1,
    ]);
    $learner->save();

    $manager = new LearningNoticeManager(
      $this->container->get('entity_type.manager'),
      (new \ReflectionClass(DomainPurposeManager::class))->newInstanceWithoutConstructor(),
      (new \ReflectionClass(ClassActivityDeadlineManager::class))->newInstanceWithoutConstructor(),
      $this->createMock(DateFormatterInterface::class),
      $this->createMock(QueueFactory::class),
      new NullLogger(),
      $this->createMock(MessengerNoticePublisherInterface::class),
      $this->container->get('lock.persistent'),
    );

    $first = $manager->record('source-uuid', 'deadline', (int) $learner->id(), FALSE, 'version-one');
    $duplicate = $manager->record('source-uuid', 'deadline', (int) $learner->id(), FALSE, 'version-one');
    $changed = $manager->record('source-uuid', 'deadline', (int) $learner->id(), FALSE, 'version-two');

    self::assertNotNull($first);
    self::assertSame($first->id(), $duplicate?->id());
    self::assertNotSame($first->id(), $changed?->id());
    self::assertSame('deadline', $first->get('field_aculta_lms_event_type')->value);
    self::assertSame('source-uuid', $first->get('field_aculta_lms_source_uuid')->value);
    self::assertSame('version-one', $first->get('field_aculta_lms_event_version')->value);
    self::assertSame(2, (int) $this->container->get('entity_type.manager')
      ->getStorage('message')->getQuery()->accessCheck(FALSE)->count()->execute());
  }

  /**
   * Shared LMS events use existing Message storage and remain deduplicated.
   */
  public function testSharedEventPersistsAndDeduplicates(): void {
    $learner = User::create([
      'name' => 'lms_event_learner',
      'mail' => 'lms-event@example.invalid',
      'status' => 1,
    ]);
    $learner->save();
    $queue = $this->createMock(QueueInterface::class);
    $queue->expects(self::once())
      ->method('createItem')
      ->with(self::callback(static fn(array $item): bool => (int) ($item['message_id'] ?? 0) > 0))
      ->willReturn(1);
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory->expects(self::once())
      ->method('get')
      ->with('aculta_lms_messenger_email')
      ->willReturn($queue);
    $manager = new LearningNoticeManager(
      $this->container->get('entity_type.manager'),
      (new \ReflectionClass(DomainPurposeManager::class))->newInstanceWithoutConstructor(),
      (new \ReflectionClass(ClassActivityDeadlineManager::class))->newInstanceWithoutConstructor(),
      $this->createMock(DateFormatterInterface::class),
      $queueFactory,
      new NullLogger(),
      $this->createMock(MessengerNoticePublisherInterface::class),
      $this->container->get('lock.persistent'),
    );
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->willReturn(AvisoPreferencias::EMAIL_IMMEDIATE);
    $subscriber = new LearningNoticeEventSubscriber($manager, $preferences, new NullLogger());
    $event = new MessengerNoticeEvent(
      'lms.deadline', 'aculta_lms', 'group_relationship', 'deadline-uuid', 'v1',
      (int) $learner->id(), 'account', new \DateTimeImmutable('2026-10-24T12:00:00+00:00'),
      'lms.deadline:deadline-uuid:v1:' . $learner->id(),
      ['notice_type' => 'deadline', 'mail_policy' => 'user_preference'],
    );

    $subscriber->consume($event);
    $subscriber->consume($event);

    $messages = $this->container->get('entity_type.manager')
      ->getStorage('message')->getQuery()->accessCheck(FALSE)->count()->execute();
    self::assertSame(1, (int) $messages);
  }

  /**
   * A different account cannot change the persisted LMS read state.
   */
  public function testReadStateRejectsAnotherAccountWithoutMutation(): void {
    $learner = User::create([
      'name' => 'lms_read_owner',
      'mail' => 'lms-read-owner@example.invalid',
      'status' => 1,
    ]);
    $learner->save();
    $other = User::create([
      'name' => 'lms_read_other',
      'mail' => 'lms-read-other@example.invalid',
      'status' => 1,
    ]);
    $other->save();
    $manager = new LearningNoticeManager(
      $this->container->get('entity_type.manager'),
      (new \ReflectionClass(DomainPurposeManager::class))->newInstanceWithoutConstructor(),
      (new \ReflectionClass(ClassActivityDeadlineManager::class))->newInstanceWithoutConstructor(),
      $this->createMock(DateFormatterInterface::class),
      $this->createMock(QueueFactory::class),
      new NullLogger(),
      $this->createMock(MessengerNoticePublisherInterface::class),
      $this->container->get('lock.persistent'),
    );
    $message = $manager->record('deadline-source', 'deadline', (int) $learner->id(), FALSE, 'version-one');
    self::assertNotNull($message);

    self::assertFalse($manager->setRead($message, $other, TRUE));
    $persisted = $this->container->get('entity_type.manager')->getStorage('message')->load($message->id());
    self::assertSame(0, (int) $persisted->get('field_aculta_lms_read')->value);
  }

  /**
   * Central read state rejects a revoked course enrollment.
   */
  public function testCentralReadStateChangesForCurrentEnrollmentOnly(): void {
    $learner = User::create([
      'name' => 'lms_central_read_learner',
      'mail' => 'lms-central-read@example.invalid',
      'status' => 1,
    ]);
    $learner->save();
    $class = $this->createMock(GroupInterface::class);
    $class->method('id')->willReturn('class-24');
    $class->method('bundle')->willReturn('lms_class');
    $class->method('access')->willReturn(AccessResult::allowed());
    $class->method('label')->willReturn('Turma 24');
    $course = $this->createMock(CourseInterface::class);
    $course->method('id')->willReturn('course-12');
    $course->method('bundle')->willReturn('lms_course');
    $course->method('label')->willReturn('Curso aberto');
    $course->method('hasPermission')->with('take course', $learner)->willReturn(TRUE);
    $courseAccessible = TRUE;
    $course->method('access')->willReturnCallback(function () use (&$courseAccessible): AccessResult {
      return $courseAccessible ? AccessResult::allowed() : AccessResult::forbidden();
    });
    $membership = $this->createMock(GroupMembership::class);
    $membership->method('getEntity')->willReturn($learner);
    $membership->method('getPluginId')->willReturn('group_membership');
    $membership->method('getGroup')->willReturn($class);
    $parentRelationship = $this->createMock(GroupRelationshipInterface::class);
    $parentRelationship->method('getGroup')->willReturn($course);
    $relationshipStorage = $this->createMock(EntityStorageInterface::class);
    $relationshipStorage->method('loadByProperties')->willReturnCallback(static fn(array $properties): array => match (TRUE) {
      ($properties['uuid'] ?? NULL) === 'enrollment-source-uuid' => [$membership],
      ($properties['entity_id'] ?? NULL) === 'class-24'
        && ($properties['plugin_id'] ?? NULL) === 'lms_classes' => [$parentRelationship],
      default => [],
    });
    $realEntityTypeManager = $this->container->get('entity_type.manager');
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->willReturnCallback(static fn(string $type): EntityStorageInterface => $type === 'group_relationship'
      ? $relationshipStorage
      : $realEntityTypeManager->getStorage($type));
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('isHttps')->willReturn(FALSE);
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->with('cursos_aculta_org')->willReturn($domain);
    $domainEntities = $this->createMock(EntityTypeManagerInterface::class);
    $domainEntities->method('getStorage')->with('domain')->willReturn($domainStorage);
    $domainNegotiator = $this->createMock(DomainNegotiatorInterface::class);
    $domainNegotiator->method('getActiveDomain')->willReturn(NULL);
    $domainPurposes = new DomainPurposeManager(
      $domainEntities,
      $domainNegotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    $preferences = $this->createMock(AvisoPreferenciasInterface::class);
    $preferences->method('emailMode')->willReturn(AvisoPreferencias::EMAIL_OFF);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $manager = new LearningNoticeManager(
      $entityTypeManager,
      $domainPurposes,
      (new \ReflectionClass(ClassActivityDeadlineManager::class))->newInstanceWithoutConstructor(),
      $this->createMock(DateFormatterInterface::class),
      $this->createMock(QueueFactory::class),
      new NullLogger(),
      $this->createMock(MessengerNoticePublisherInterface::class),
      $lock,
    );
    $event = new MessengerNoticeEvent(
      'lms.enrollment',
      'aculta_lms',
      'group_relationship',
      'enrollment-source-uuid',
      'version-1',
      (int) $learner->id(),
      'aprendizagem',
      new \DateTimeImmutable('2026-10-24T12:00:00+00:00'),
      'lms.enrollment:enrollment-source-uuid:version-1:' . $learner->id(),
      ['notice_type' => 'enrollment', 'mail_policy' => 'user_preference'],
    );
    (new LearningNoticeEventSubscriber($manager, $preferences, new NullLogger()))->consume($event);
    $storage = $realEntityTypeManager->getStorage('message');
    $messageIds = $storage->getQuery()->accessCheck(FALSE)
      ->condition('template', LearningNoticeManager::TEMPLATE)
      ->condition('uid', (int) $learner->id())
      ->execute();
    $message = $storage->load(reset($messageIds));
    self::assertNotNull($message);
    self::assertSame(0, (int) $message->get('field_aculta_lms_read')->value);

    $central = new NoticeReadStateManager(
      $realEntityTypeManager,
      ['lms' => $manager],
    );
    self::assertTrue($central->setRead((int) $message->id(), $learner, TRUE));
    self::assertSame(1, (int) $storage->load($message->id())->get('field_aculta_lms_read')->value);

    $courseAccessible = FALSE;
    self::assertFalse($central->setRead((int) $message->id(), $learner, FALSE));
    self::assertSame(1, (int) $storage->load($message->id())->get('field_aculta_lms_read')->value);
  }

  /**
   * Creates a minimal message field for the kernel fixture.
   *
   * @param string $name
   *   The field machine name.
   * @param string $type
   *   The field type.
   * @param array<string, mixed> $settings
   *   The field-type settings.
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
      'bundle' => LearningNoticeManager::TEMPLATE,
      'field_name' => $name,
      'label' => $name,
    ])->save();
  }

}
