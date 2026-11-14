<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Plugin\views\filter;

use Drupal\aculta_user\CoAuthor\CoAuthorManager;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\ManyToOne;
use Drupal\views\Plugin\views\query\Sql;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter handler for author or co-author.
 *
 * Checks if the given user ID matches the node author (uid) OR is referenced
 * in the aculta_coauthors base field table.
 */
#[ViewsFilter('aculta_author_coauthor_name')]
final class AuthorCoAuthorName extends ManyToOne {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function opSimple(): void {
    if (!$this->query instanceof Sql) {
      return;
    }

    if (empty($this->value) || !is_array($this->value)) {
      return;
    }
    $this->ensureMyTable();

    $storage = $this->entityTypeManager->getStorage('node');
    $mapping = $storage->getTableMapping();

    $fieldName = CoAuthorManager::FIELD_NAME;
    $coAuthorsTable = $mapping->getFieldTableName($fieldName);
    $columnNames = $mapping->getColumnNames($fieldName);
    $coAuthorsColumn = $columnNames['target_id'] ?? $fieldName . '_target_id';

    $join = $this->query->getJoinData($coAuthorsTable, $this->tableAlias);
    $this->query->addRelationship($coAuthorsTable, $join, $this->tableAlias);

    // Set distinct so nodes with both author and coauthors matching
    // don't duplicate.
    $this->query->distinct = TRUE;

    $groupId = (string) $this->query->setWhereGroup('OR');
    /** @var array<int|string, mixed> $rawValues */
    $rawValues = $this->value;
    $values = array_values($rawValues);

    $this->query->addWhere($groupId, "$this->tableAlias.$this->realField", $values, (string) $this->operator);
    $this->query->addWhere($groupId, "$coAuthorsTable.$coAuthorsColumn", $values, (string) $this->operator);
  }

}
