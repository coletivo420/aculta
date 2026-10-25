<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\migrate\destination;

use Drupal\aculta_forum\Migration\ForumFlaggingMigrationContext;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\flag\FlaggingInterface;
use Drupal\migrate\Attribute\MigrateDestination;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\migrate\destination\EntityContentBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Recreates Forum report flaggings while preserving source identity metadata.
 */
#[MigrateDestination(id: 'aculta_forum_comment_flagging')]
final class ForumCommentFlagging extends EntityContentBase {

  /**
   * Current source Flagging ID, scoped to one import call.
   */
  private ?int $sourceFlaggingId = NULL;

  /**
   * Current source Comment ID, scoped to one import call.
   */
  private ?int $sourceCommentId = NULL;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration,
    EntityStorageInterface $storage,
    array $bundles,
    EntityFieldManagerInterface $entity_field_manager,
    FieldTypePluginManagerInterface $field_type_manager,
    private readonly ForumFlaggingMigrationContext $migration_context,
    ?AccountSwitcherInterface $account_switcher = NULL,
    ?EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $storage, $bundles, $entity_field_manager, $field_type_manager, $account_switcher, $entity_type_bundle_info);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL): static {
    $bundle_info = $container->get('entity_type.bundle.info');
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('entity_type.manager')->getStorage('flagging'),
      array_keys($bundle_info->getBundleInfo('flagging')),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('aculta_forum.flagging_migration_context'),
      $container->get('account_switcher'),
      $bundle_info,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []): array {
    $this->sourceFlaggingId = (int) $row->getSourceProperty('fid');
    $this->sourceCommentId = (int) $row->getSourceProperty('cid');
    if ($this->sourceFlaggingId < 1 || $this->sourceCommentId < 1) {
      throw new MigrateException('A linha de denúncia não contém IDs de origem válidos.');
    }
    try {
      return parent::import($row, $old_destination_id_values);
    }
    finally {
      $this->sourceFlaggingId = NULL;
      $this->sourceCommentId = NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function save(ContentEntityInterface $entity, array $old_destination_id_values = []): array {
    if (!$entity instanceof FlaggingInterface
      || $this->sourceFlaggingId === NULL
      || $this->sourceCommentId === NULL) {
      throw new MigrateException('O destino de denúncia recebeu entidade ou contexto de origem inválido.');
    }
    return $this->migration_context->saveImportedFlagging(
      $entity,
      $this->sourceFlaggingId,
      $this->sourceCommentId,
      fn (): array => parent::save($entity, $old_destination_id_values),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return [];
  }

}
