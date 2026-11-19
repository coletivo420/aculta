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
  protected ?int $sourceFlaggingId = NULL;

  /**
   * Current source Comment ID, scoped to one import call.
   */
  protected ?int $sourceCommentId = NULL;

  /**
   * Constructs a ForumCommentFlagging object.
   *
   * @param array<string, mixed> $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration entity.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The flagging entity storage.
   * @param array<string> $bundles
   *   The list of bundles.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
   *   The field type manager.
   * @param \Drupal\aculta_forum\Migration\ForumFlaggingMigrationContext $migration_context
   *   The forum flagging migration context.
   * @param \Drupal\Core\Session\AccountSwitcherInterface|null $account_switcher
   *   The account switcher.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface|null $entity_type_bundle_info
   *   The bundle info service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration,
    EntityStorageInterface $storage,
    array $bundles,
    EntityFieldManagerInterface $entity_field_manager,
    FieldTypePluginManagerInterface $field_type_manager,
    protected readonly ForumFlaggingMigrationContext $migration_context,
    ?AccountSwitcherInterface $account_switcher = NULL,
    ?EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $storage, $bundles, $entity_field_manager, $field_type_manager, $account_switcher, $entity_type_bundle_info);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL): static {
    if (!$migration instanceof MigrationInterface) {
      throw new \InvalidArgumentException('MigrationInterface é obrigatória.');
    }
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
   *
   * @param array<int|string, mixed> $old_destination_id_values
   * @return array<int|string, mixed>|false
   */
  public function import(Row $row, array $old_destination_id_values = []): array|bool {
    $fidVal = $row->getSourceProperty('fid');
    $cidVal = $row->getSourceProperty('cid');
    $this->sourceFlaggingId = is_numeric($fidVal) ? (int) $fidVal : 0;
    $this->sourceCommentId = is_numeric($cidVal) ? (int) $cidVal : 0;
    if ($this->sourceFlaggingId < 1 || $this->sourceCommentId < 1) {
      throw new MigrateException('A linha de denúncia não contém IDs de origem válidos.');
    }
    try {
      $result = parent::import($row, $old_destination_id_values);
      return is_array($result) ? $result : FALSE;
    }
    finally {
      $this->sourceFlaggingId = NULL;
      $this->sourceCommentId = NULL;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<int|string, mixed> $old_destination_id_values
   * @return array<int|string, mixed>
   */
  protected function save(ContentEntityInterface $entity, array $old_destination_id_values = []): array {
    if (!$entity instanceof FlaggingInterface
      || $this->sourceFlaggingId === NULL
      || $this->sourceCommentId === NULL) {
      throw new MigrateException('O destino de denúncia recebeu entidade ou contexto de origem inválido.');
    }
    /** @var array<int|string, mixed> */
    return $this->migration_context->saveImportedFlagging(
      $entity,
      $this->sourceFlaggingId,
      $this->sourceCommentId,
      fn (): array => parent::save($entity, $old_destination_id_values),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   */
  public function fields(): array {
    return [];
  }

}
