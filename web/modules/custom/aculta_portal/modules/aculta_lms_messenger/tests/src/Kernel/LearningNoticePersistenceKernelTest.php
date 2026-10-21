<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_messenger\Kernel;

use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\aculta_lms_messenger\LearningNoticeManager;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Queue\QueueFactoryInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\MessageTemplate;
use Drupal\user\Entity\User;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\NullLogger;

/**
 * Tests LMS notice persistence against real Message entities.
 */
#[Group('aculta_lms_messenger')]
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
      $this->createMock(QueueFactoryInterface::class),
      new NullLogger(),
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
