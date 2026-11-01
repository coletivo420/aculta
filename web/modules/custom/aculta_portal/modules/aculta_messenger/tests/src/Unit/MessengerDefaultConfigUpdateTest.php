<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Config\ConfigInstaller;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\field\FieldStorageConfigInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests additive Message configuration updates. */
#[CoversNothing]
#[Group('aculta_messenger')]
final class MessengerDefaultConfigUpdateTest extends UnitTestCase {

  /**
   * The update installs missing defaults without replacing existing config. */
  public function testUpdateInstallsDefaultFriendshipMessageConfiguration(): void {
    $installer = $this->createMock(ConfigInstaller::class);
    $installer->expects(self::once())->method('installDefaultConfig')->with('module', 'aculta_messenger');
    $container = new ContainerBuilder();
    $container->set('config.installer', $installer);
    require_once dirname(__DIR__, 3) . '/aculta_messenger.install';
    \Drupal::setContainer($container);
    try {
      aculta_messenger_update_10007();
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

  /**
   * The additive private-message Mailer update installs default field config.
   */
  public function testUpdateInstallsPrivateMessageMailerMarkerConfiguration(): void {
    $installer = $this->createMock(ConfigInstaller::class);
    $installer->expects(self::once())->method('installDefaultConfig')->with('module', 'aculta_messenger');
    $container = new ContainerBuilder();
    $container->set('config.installer', $installer);
    require_once dirname(__DIR__, 3) . '/aculta_messenger.install';
    \Drupal::setContainer($container);
    try {
      aculta_messenger_update_10008();
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

  /**
   * The friendship Mailer ownership update installs default field config.
   */
  public function testUpdateInstallsFriendshipMailerMarkerConfiguration(): void {
    $installer = $this->createMock(ConfigInstaller::class);
    $installer->expects(self::once())->method('installDefaultConfig')->with('module', 'aculta_messenger');
    $container = new ContainerBuilder();
    $container->set('config.installer', $installer);
    require_once dirname(__DIR__, 3) . '/aculta_messenger.install';
    \Drupal::setContainer($container);
    try {
      aculta_messenger_update_10009();
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

  /**
   * The weekly private-message digest update installs default field config.
   */
  public function testUpdateInstallsPrivateMessageDigestConfiguration(): void {
    $installer = $this->createMock(ConfigInstaller::class);
    $installer->expects(self::once())->method('installDefaultConfig')->with('module', 'aculta_messenger');
    $container = new ContainerBuilder();
    $container->set('config.installer', $installer);
    require_once dirname(__DIR__, 3) . '/aculta_messenger.install';
    \Drupal::setContainer($container);
    try {
      aculta_messenger_update_10010();
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

  /**
   * The site notice read-state field installs through an additive update.
   */
  public function testUpdateInstallsFriendshipSiteReadConfiguration(): void {
    $installer = $this->createMock(ConfigInstaller::class);
    $installer->expects(self::once())->method('installDefaultConfig')->with('module', 'aculta_messenger');
    $container = new ContainerBuilder();
    $container->set('config.installer', $installer);
    require_once dirname(__DIR__, 3) . '/aculta_messenger.install';
    \Drupal::setContainer($container);
    try {
      aculta_messenger_update_10011();
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

  /**
   * The Game notice update installs its shared Message bundle defaults.
   */
  public function testUpdateInstallsGameNoticeConfiguration(): void {
    $installer = $this->createMock(ConfigInstaller::class);
    $installer->expects(self::once())->method('installDefaultConfig')->with('module', 'aculta_messenger');
    $container = new ContainerBuilder();
    $container->set('config.installer', $installer);
    require_once dirname(__DIR__, 3) . '/aculta_messenger.install';
    \Drupal::setContainer($container);
    try {
      aculta_messenger_update_10012();
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

  /**
   * The Events contribution notice update installs its private bundle defaults.
   */
  public function testUpdateInstallsEventsContributionNoticeConfiguration(): void {
    $names = [
      'message.template.aculta_msg_events_contrib_notice',
      'field.storage.message.field_aculta_events_outcome',
      'field.storage.message.field_aculta_events_notice_read',
      'field.storage.message.field_aculta_events_prop_rev',
      'field.storage.message.field_aculta_events_prop_uuid',
      'field.field.message.aculta_msg_events_contrib_notice.field_aculta_events_outcome',
      'field.field.message.aculta_msg_events_contrib_notice.field_aculta_events_notice_read',
      'field.field.message.aculta_msg_events_contrib_notice.field_aculta_events_prop_rev',
      'field.field.message.aculta_msg_events_contrib_notice.field_aculta_events_prop_uuid',
    ];
    $config_manager = $this->createMock(ConfigManagerInterface::class);
    $config_manager->expects(self::exactly(count($names)))->method('loadConfigEntityByName')
      ->willReturn(NULL);
    $config_manager->expects(self::exactly(count($names)))->method('getEntityTypeIdByName')
      ->willReturnCallback(static function (string $name): string {
        return match (TRUE) {
          str_starts_with($name, 'message.template.') => 'message_template',
          str_starts_with($name, 'field.storage.') => 'field_storage_config',
          default => 'field_config',
        };
      });
    $entity = $this->createMock(ConfigEntityInterface::class);
    $entity->expects(self::exactly(count($names)))->method('isInstallable')->willReturn(TRUE);
    $entity->expects(self::exactly(count($names)))->method('save');
    $storages = [];
    foreach (['message_template' => 1, 'field_storage_config' => 4, 'field_config' => 4] as $type => $count) {
      $storage = $this->createMock(ConfigEntityStorageInterface::class);
      $storage->expects(self::exactly($count))->method('createFromStorageRecord')->willReturn($entity);
      $storages[$type] = $storage;
    }
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects(self::exactly(count($names)))->method('getStorage')
      ->willReturnCallback(static fn(string $type): ConfigEntityStorageInterface => $storages[$type]);
    $container = new ContainerBuilder();
    $container->set('extension.list.module', new class {

      /**
       * Returns the Messenger module path for the update fixture.
       */
      public function getPath(string $module): string {
        return dirname(__DIR__, 3);
      }

    });
    $container->set('config.manager', $config_manager);
    $container->set('entity_type.manager', $entity_type_manager);
    require_once dirname(__DIR__, 3) . '/aculta_messenger.install';
    \Drupal::setContainer($container);
    try {
      aculta_messenger_update_10015();
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

  /**
   * The Events notice outcome value is added once and keeps existing choices.
   */
  public function testUpdateAddsSubmittedOutcomeIdempotently(): void {
    $allowedValues = [
      ['value' => 'needs_changes', 'label' => 'Pedir ajustes'],
      ['value' => 'accepted', 'label' => 'Aceitar'],
    ];
    $field = $this->createMock(FieldStorageConfigInterface::class);
    $field->expects(self::exactly(2))->method('getSetting')->with('allowed_values')
      ->willReturnCallback(static function () use (&$allowedValues): array {
        return $allowedValues;
      });
    $field->expects(self::once())->method('setSetting')->with('allowed_values', self::callback(
      static function (array $updated) use (&$allowedValues): bool {
        $allowedValues = $updated;
        return count($updated) === 3 && $updated[2]['value'] === 'submitted';
      },
    ))->willReturnSelf();
    $field->expects(self::once())->method('save')->willReturn($field);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::exactly(2))->method('load')
      ->with('message.field_aculta_events_outcome')->willReturn($field);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects(self::exactly(2))->method('getStorage')
      ->with('field_storage_config')->willReturn($storage);
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    require_once dirname(__DIR__, 3) . '/aculta_messenger.install';
    \Drupal::setContainer($container);
    try {
      aculta_messenger_update_10016();
      aculta_messenger_update_10016();
      self::assertCount(3, $allowedValues);
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

  /**
   * The friendship and game unread-count fields install additively.
   */
  public function testUpdateInstallsUnreadCountFieldConfiguration(): void {
    $module_path = dirname(__DIR__, 3);
    $defaults = new FileStorage($module_path . '/config/install');
    $storage_ids = [
      'field.storage.message.field_aculta_friendship_read',
      'field.storage.message.field_aculta_game_award_uuid',
      'field.storage.message.field_aculta_game_read',
    ];
    $instance_ids = [
      'field.field.message.aculta_msg_friendship.field_aculta_friendship_read',
      'field.field.message.aculta_msg_game_award.field_aculta_game_award_uuid',
      'field.field.message.aculta_msg_game_award.field_aculta_game_read',
    ];
    $created_storages = [];
    $field_storage = $this->createMock(EntityStorageInterface::class);
    $field_storage->expects(self::exactly(6))->method('load')
      ->willReturnCallback(static function (string $id) use (&$created_storages): ?FieldStorageConfigInterface {
        return $created_storages[$id] ?? NULL;
      });
    $field_storage->expects(self::exactly(3))->method('create')
      ->willReturnCallback(function (array $values) use (&$created_storages): FieldStorageConfigInterface {
        $entity = $this->createMock(FieldStorageConfigInterface::class);
        $entity->expects(self::once())->method('save')->willReturn(1);
        $entity->method('getType')->willReturn($values['type']);
        $created_storages[$values['id']] = $entity;
        return $entity;
      });
    $field_config = $this->createMock(EntityStorageInterface::class);
    $field_config->expects(self::exactly(3))->method('load')->willReturn(NULL);
    $saved_entity = $this->createMock(EntityInterface::class);
    $saved_entity->expects(self::exactly(3))->method('save')->willReturn(1);
    $field_config->expects(self::exactly(3))->method('create')
      ->willReturnCallback(static fn(array $values): EntityInterface => $saved_entity);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects(self::exactly(2))->method('getStorage')
      ->willReturnMap([
        ['field_storage_config', $field_storage],
        ['field_config', $field_config],
      ]);
    $extension_list = new class ($module_path) {

      /** Constructs the extension-list path fixture. */
      public function __construct(private string $modulePath) {}

      /** Returns the fixture module path. */
      public function getPath(string $module): string {
        return $this->modulePath;
      }

    };
    $container = new ContainerBuilder();
    $container->set('extension.list.module', $extension_list);
    $container->set('entity_type.manager', $entity_type_manager);
    require_once dirname(__DIR__, 3) . '/aculta_messenger.install';
    \Drupal::setContainer($container);
    try {
      aculta_messenger_update_10013();
      foreach ([...$storage_ids, ...$instance_ids] as $id) {
        self::assertIsArray($defaults->read($id));
      }
      self::assertCount(3, $created_storages);
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

  /**
   * The update is idempotent when all targeted fields already exist.
   */
  public function testUpdatePreservesExistingUnreadCountFields(): void {
    $module_path = dirname(__DIR__, 3);
    $defaults = new FileStorage($module_path . '/config/install');
    $field_storage = $this->createMock(EntityStorageInterface::class);
    $field_storage->expects(self::exactly(3))->method('load')
      ->willReturnCallback(function (string $id) use ($defaults): FieldStorageConfigInterface {
        static $entities = [];
        if (!isset($entities[$id])) {
          $config_name = 'field.storage.' . str_replace('.', '.', $id);
          $entity = $this->createMock(FieldStorageConfigInterface::class);
          $entity->method('getType')->willReturn($defaults->read($config_name)['type']);
          $entities[$id] = $entity;
        }
        return $entities[$id];
      });
    $field_storage->expects(self::never())->method('create');
    $field_config = $this->createMock(EntityStorageInterface::class);
    $field_config->expects(self::exactly(3))->method('load')
      ->willReturn($this->createMock(EntityInterface::class));
    $field_config->expects(self::never())->method('create');
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects(self::exactly(2))->method('getStorage')
      ->willReturnMap([
        ['field_storage_config', $field_storage],
        ['field_config', $field_config],
      ]);
    $extension_list = new class ($module_path) {

      /** Constructs the extension-list path fixture. */
      public function __construct(private string $modulePath) {}

      /** Returns the fixture module path. */
      public function getPath(string $module): string {
        return $this->modulePath;
      }

    };
    $container = new ContainerBuilder();
    $container->set('extension.list.module', $extension_list);
    $container->set('entity_type.manager', $entity_type_manager);
    require_once dirname(__DIR__, 3) . '/aculta_messenger.install';
    \Drupal::setContainer($container);
    try {
      aculta_messenger_update_10013();
    }
    finally {
      \Drupal::unsetContainer();
    }
  }

}
