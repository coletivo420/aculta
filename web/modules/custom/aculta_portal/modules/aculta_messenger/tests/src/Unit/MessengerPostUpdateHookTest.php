<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\IntegerItem;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\flag\FlagInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Covers the add-only Site Alert and Forum watermark post-update hooks.
 */
#[Group('aculta_messenger')]
final class MessengerPostUpdateHookTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 3) . '/aculta_messenger.post_update.php';
  }

  /**
   * The dismissal Flag is created from the configured account-scoped values.
   */
  public function testCreatesSiteAlertDismissalFlag(): void {
    $this->assertFlagUpdate(
      'aculta_site_alert_dismissed',
      'Added the per-user dismissal Flag for Site Alerts.',
      [
        'label' => 'Aviso temporário dispensado',
        'flag_short' => 'Dispensar aviso',
        'unflag_short' => 'Mostrar aviso novamente',
      ],
    );
  }

  /**
   * Re-running the dismissal update leaves the existing Flag untouched.
   */
  public function testExistingSiteAlertDismissalFlagIsPreserved(): void {
    $this->assertExistingFlagUpdate(
      'aculta_site_alert_dismissed',
      'The Site Alert dismissal flag was already present.',
    );
  }

  /**
   * The read Flag is created from the configured account-scoped values.
   */
  public function testCreatesSiteAlertReadFlag(): void {
    $this->assertFlagUpdate(
      'aculta_site_alert_read',
      'Added the per-user read Flag for Site Alerts.',
      [
        'label' => 'Aviso temporário lido',
        'flag_short' => 'Marcar como lido',
        'unflag_short' => 'Marcar como não lido',
      ],
    );
  }

  /**
   * Re-running the read update leaves the existing Flag untouched.
   */
  public function testExistingSiteAlertReadFlagIsPreserved(): void {
    $this->assertExistingFlagUpdate(
      'aculta_site_alert_read',
      'The Site Alert read flag was already present.',
    );
  }

  /**
   * The Forum watermark update installs its canonical base-field definition.
   */
  public function testInstallsForumPostWatermark(): void {
    $definitionManager = $this->createMock(EntityDefinitionUpdateManagerInterface::class);
    $definitionManager->expects(self::once())
      ->method('getFieldStorageDefinition')
      ->with('ultimo_post', 'aculta_aviso')
      ->willReturn(NULL);
    $definitionManager->expects(self::once())
      ->method('installFieldStorageDefinition')
      ->with(
        'ultimo_post',
        'aculta_aviso',
        'aculta_messenger',
        self::callback(static fn(mixed $field): bool => $field instanceof BaseFieldDefinition),
      );
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::once())
      ->method('getDefinition')
      ->with('aculta_aviso')
      ->willReturn($entityType);
    $fieldTypeManager = $this->createMock(FieldTypePluginManagerInterface::class);
    $fieldTypeManager->method('getDefaultStorageSettings')->willReturn([]);
    $fieldTypeManager->method('getDefaultFieldSettings')->willReturn([]);
    $typedDataManager = $this->createMock(TypedDataManagerInterface::class);
    $typedDataManager->method('getDefinition')->willReturn(['class' => IntegerItem::class]);
    $container = new ContainerBuilder();
    $container->set('entity.definition_update_manager', $definitionManager);
    $container->set('entity_type.manager', $entityTypeManager);
    $container->set('plugin.manager.field.field_type', $fieldTypeManager);
    $container->set('typed_data_manager', $typedDataManager);

    \Drupal::setContainer($container);
    try {
      $sandbox = [];
      self::assertSame(
        'Added the revisionable Forum post notice watermark.',
        aculta_messenger_post_update_forum_post_watermark($sandbox),
      );
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

  /**
   * An installed Forum watermark is not installed a second time.
   */
  public function testExistingForumPostWatermarkIsPreserved(): void {
    $fieldStorage = $this->createMock(FieldStorageDefinitionInterface::class);
    $definitionManager = $this->createMock(EntityDefinitionUpdateManagerInterface::class);
    $definitionManager->expects(self::once())
      ->method('getFieldStorageDefinition')
      ->with('ultimo_post', 'aculta_aviso')
      ->willReturn($fieldStorage);
    $definitionManager->expects(self::never())->method('installFieldStorageDefinition');
    $container = new ContainerBuilder();
    $container->set('entity.definition_update_manager', $definitionManager);

    \Drupal::setContainer($container);
    try {
      $sandbox = [];
      self::assertSame(
        'The revisionable Forum post notice watermark was already installed.',
        aculta_messenger_post_update_forum_post_watermark($sandbox),
      );
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

  /**
   * Asserts that a missing Site Alert Flag is created with private scope.
   */
  private function assertFlagUpdate(string $id, string $result, array $expectedValues): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('load')->with($id)->willReturn(NULL);
    $storage->expects(self::once())
      ->method('create')
      ->with(self::callback(static fn(array $values): bool =>
        ($values['id'] ?? NULL) === $id
        && ($values['entity_type'] ?? NULL) === 'site_alert'
        && ($values['flag_type'] ?? NULL) === 'entity:site_alert'
        && ($values['global'] ?? NULL) === FALSE
        && ($values['link_type'] ?? NULL) === 'ajax_link'
        && ($values['label'] ?? NULL) === $expectedValues['label']
        && ($values['flag_short'] ?? NULL) === $expectedValues['flag_short']
        && ($values['unflag_short'] ?? NULL) === $expectedValues['unflag_short']
        && ($values['flagTypeConfig']['show_on_form'] ?? NULL) === FALSE
      ))
      ->willReturn($this->createConfiguredMock(FlagInterface::class, [
        'save' => 1,
      ]));
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $this->flagEntityTypeManager($storage));

    \Drupal::setContainer($container);
    try {
      $sandbox = [];
      self::assertSame($result, $id === 'aculta_site_alert_read'
        ? aculta_messenger_post_update_site_alert_read_flag($sandbox)
        : aculta_messenger_post_update_site_alert_dismissal_flag($sandbox));
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

  /**
   * Asserts that an existing Site Alert Flag is not recreated or modified.
   */
  private function assertExistingFlagUpdate(string $id, string $result): void {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('load')->with($id)->willReturn($this->createMock(FlagInterface::class));
    $storage->expects(self::never())->method('create');
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $this->flagEntityTypeManager($storage));

    \Drupal::setContainer($container);
    try {
      $sandbox = [];
      self::assertSame($result, $id === 'aculta_site_alert_read'
        ? aculta_messenger_post_update_site_alert_read_flag($sandbox)
        : aculta_messenger_post_update_site_alert_dismissal_flag($sandbox));
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

  /**
   * Creates the mocked entity manager required by the Flag post-updates.
   */
  private function flagEntityTypeManager(EntityStorageInterface $storage): EntityTypeManagerInterface {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::once())
      ->method('getStorage')
      ->with('flag')
      ->willReturn($storage);
    return $entityTypeManager;
  }

}
