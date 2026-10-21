<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Migration;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\path_alias\PathAliasInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Moves legacy editorial Nodes to the canonical Revista bundle via Entity API.
 *
 * This operation recreates each Node under the same entity ID. It refuses any
 * condition for which it cannot prove revision, reference, comment and alias
 * preservation from the current data shape.
 */
final class PublicationBundleMigrator {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    #[Autowire(service: 'database')]
    private readonly Connection $database,
  ) {
  }

  /**
   * Returns a count-only plan and validates the supported migration shape.
   *
   * @return array{nids: int[], aliases: int, revisions: int}
   *   A plan that contains no editorial content values.
   */
  public function plan(): array {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $query = $node_storage->getQuery()->accessCheck(FALSE);
    $nids = $query->condition('type', ['article', 'blog'], 'IN')->sort('nid')->execute();
    $aliases = 0;
    $revisions = 0;
    $path_alias_storage = $this->entityTypeManager->getStorage('path_alias');
    $bundle_fields = [];

    foreach (['article', 'blog'] as $bundle) {
      $bundle_fields[$bundle] = $this->entityFieldManager->getFieldDefinitions('node', $bundle);
      $target_fields = $this->entityFieldManager->getFieldDefinitions('node', 'revista');
      foreach ($bundle_fields[$bundle] as $field_name => $definition) {
        if (!isset($target_fields[$field_name]) || $definition->getType() !== $target_fields[$field_name]->getType()) {
          throw new \RuntimeException(sprintf('Campo incompatível no bundle legado: %s.%s.', $bundle, $field_name));
        }
      }
    }

    foreach ($nids as $nid) {
      $node = $node_storage->load($nid);
      if (!$node instanceof NodeInterface) {
        throw new \RuntimeException(sprintf('Node editorial %s não pôde ser carregado.', $nid));
      }
      if (count($node->getTranslationLanguages()) !== 1) {
        throw new \RuntimeException(sprintf('Node %s tem traduções; migração interrompida para preservar as variantes.', $nid));
      }

      $node_revisions = $node_storage->getQuery()->allRevisions()->accessCheck(FALSE)
        ->condition('nid', (int) $nid)->count()->execute();
      if ($node_revisions !== 1) {
        throw new \RuntimeException(sprintf('Node %s tem %s revisões; a migração atual só aceita uma revisão.', $nid, $node_revisions));
      }
      $revisions += $node_revisions;

      $comments = $this->entityTypeManager->getStorage('comment')->getQuery()->accessCheck(FALSE)
        ->condition('entity_type', 'node')->condition('entity_id', (int) $nid)->count()->execute();
      if ($comments !== 0) {
        throw new \RuntimeException(sprintf('Node %s tem Comments; migração interrompida para preservar as entidades.', $nid));
      }

      $this->assertNoIncomingReferences((int) $nid);
      if ($this->database->schema()->tableExists('entity_usage')) {
        $usage_count = $this->database->select('entity_usage', 'eu')->condition('target_type', 'node')
          ->condition('target_id', (int) $nid)->countQuery()->execute()->fetchField();
        if ((int) $usage_count !== 0) {
          throw new \RuntimeException(sprintf('Node %s tem registros de Entity Usage; migração interrompida para preservar referências.', $nid));
        }
      }

      $node_aliases = $path_alias_storage->loadByProperties(['path' => '/node/' . $nid]);
      foreach ($node_aliases as $alias) {
        if (!$alias instanceof PathAliasInterface) {
          throw new \RuntimeException(sprintf('Alias de Node %s tem tipo inesperado.', $nid));
        }
        $alias_revisions = $path_alias_storage->getQuery()->allRevisions()->accessCheck(FALSE)
          ->condition('id', $alias->id())->count()->execute();
        if ($alias_revisions !== 1) {
          throw new \RuntimeException(sprintf('Alias %s do Node %s tem múltiplas revisões.', $alias->id(), $nid));
        }
        $aliases++;
      }
    }

    return ['nids' => array_values(array_map('intval', $nids)), 'aliases' => $aliases, 'revisions' => $revisions];
  }

  /**
   * Migrates the legacy Nodes and their PathAlias entities in one transaction.
   *
   * @return array{nids: int[], aliases: int, revisions: int}
   *   The completed count-only migration report.
   */
  public function migrate(): array {
    $plan = $this->plan();
    if (!$plan['nids']) {
      return $plan;
    }

    $node_storage = $this->entityTypeManager->getStorage('node');
    $path_alias_storage = $this->entityTypeManager->getStorage('path_alias');
    $snapshots = [];
    foreach ($plan['nids'] as $nid) {
      $node = $node_storage->load($nid);
      $aliases = $path_alias_storage->loadByProperties(['path' => '/node/' . $nid]);
      $snapshots[$nid] = [
        'node' => $node,
        'fields' => $node->toArray(),
        'pathauto' => $this->getPathautoState($node),
        'aliases' => array_map(static fn (PathAliasInterface $alias): array => $alias->toArray(), $aliases),
      ];
    }

    $transaction = $this->database->startTransaction();
    try {
      foreach ($snapshots as $nid => $snapshot) {
        $aliases = $path_alias_storage->loadByProperties(['path' => '/node/' . $nid]);
        $path_alias_storage->delete($aliases);
        $node_storage->load($nid)->delete();
        $node_storage->resetCache([(int) $nid]);

        $source_values = $snapshot['fields'];
        $replacement = $node_storage->create([
          'type' => 'revista',
          'nid' => (int) $nid,
          'uuid' => $source_values['uuid'][0]['value'],
        ]);
        foreach ($this->entityFieldManager->getFieldDefinitions('node', $snapshot['node']->bundle()) as $field_name => $definition) {
          if (in_array($field_name, ['nid', 'type', 'uuid', 'vid', 'path', 'revision_default'], TRUE)
            || $definition->isComputed()
            || !$replacement->hasField($field_name)) {
            continue;
          }
          $replacement->set($field_name, $source_values[$field_name] ?? []);
        }
        // The destination workflow defaults new content to draft. Preserve the
        // source publication state explicitly when the legacy bundle had no
        // moderation state of its own.
        if ($replacement->hasField('moderation_state')) {
          $source_moderation_state = $source_values['moderation_state'][0]['value'] ?? NULL;
          $target_states = $this->getRevistaModerationStates();
          $moderation_state = is_string($source_moderation_state) && isset($target_states[$source_moderation_state])
            ? $source_moderation_state
            : ((int) ($source_values['status'][0]['value'] ?? 0) === 1 ? 'published' : 'draft');
          if (isset($target_states[$moderation_state])) {
            $replacement->set('moderation_state', $moderation_state);
          }
        }
        $replacement->set('vid', $source_values['vid'][0]['value']);
        if ($replacement->hasField('path')) {
          $replacement->set('path', [['pathauto' => 0]]);
        }
        $replacement->enforceIsNew();
        $replacement->save();

        foreach ($snapshot['aliases'] as $alias_values) {
          $alias_values = array_intersect_key($alias_values, array_flip([
            'id', 'uuid', 'revision_id', 'path', 'alias', 'langcode', 'status',
            'revision_default', 'revision_created', 'revision_user',
            'revision_log_message',
          ]));
          $path_alias = $path_alias_storage->create($alias_values);
          $path_alias->enforceIsNew();
          $path_alias->save();
        }
        $path_alias_storage->resetCache();

        $this->restorePathautoState($replacement, $snapshot['pathauto']);
        $node_storage->resetCache([(int) $nid]);
      }
      $this->assertCompletedMigration($plan, $snapshots);
      $transaction->commitOrRelease();
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      $node_storage->resetCache($plan['nids']);
      $path_alias_storage->resetCache();
      throw $exception;
    }

    return $plan;
  }

  /**
   * Gets the states supported by the configured Revista workflow, if present.
   *
   * @return array<string, mixed>
   *   State plugin configuration keyed by ID.
   */
  private function getRevistaModerationStates(): array {
    if (!$this->entityTypeManager->hasDefinition('workflow')) {
      return [];
    }
    $workflow = $this->entityTypeManager->getStorage('workflow')->load('revista');
    return $workflow ? $workflow->getTypePlugin()->getStates() : [];
  }

  /**
   * Counts every incoming reference, deliberately ignoring the current user.
   */
  private function assertNoIncomingReferences(int $nid): void {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$entity_type->entityClassImplements(FieldableEntityInterface::class)) {
        continue;
      }
      foreach ($this->entityFieldManager->getFieldStorageDefinitions($entity_type_id) as $field_name => $field_definition) {
        if ($field_definition->getType() !== 'entity_reference' || $field_definition->getSetting('target_type') !== 'node') {
          continue;
        }
        $count = $this->entityTypeManager->getStorage($entity_type_id)->getQuery()->accessCheck(FALSE)
          ->condition($field_name . '.target_id', $nid)->count()->execute();
        if ($count !== 0) {
          throw new \RuntimeException(sprintf('Node %s é referenciado por %s.%s; migração interrompida.', $nid, $entity_type_id, $field_name));
        }
      }
    }
  }

  /**
   * Returns the current Pathauto state, if the field is provided by Pathauto.
   */
  private function getPathautoState(NodeInterface $node): ?int {
    if (!$node->hasField('path') || !$node->get('path')->first()) {
      return NULL;
    }
    $property = $node->get('path')->first()->get('pathauto');
    return $property->getValue() === NULL ? NULL : (int) $property->getValue();
  }

  /**
   * Restores the persisted Pathauto flag after restoring alias entities.
   */
  private function restorePathautoState(NodeInterface $node, ?int $state): void {
    if ($state === NULL || !$node->hasField('path') || !$node->get('path')->first()) {
      return;
    }
    $property = $node->get('path')->first()->get('pathauto');
    if (method_exists($property, 'persist')) {
      $property->setValue($state);
      $property->persist();
    }
  }

  /**
   * Confirms bundle and identity before committing the transaction.
   */
  private function assertCompletedMigration(array $plan, array $snapshots): void {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $alias_storage = $this->entityTypeManager->getStorage('path_alias');
    foreach ($plan['nids'] as $nid) {
      $node_storage->resetCache([(int) $nid]);
      $node = $node_storage->load($nid);
      if (!$node instanceof NodeInterface || $node->bundle() !== 'revista' || (int) $node->id() !== (int) $nid) {
        throw new \RuntimeException(sprintf('O Node %s não foi salvo no bundle Revista.', $nid));
      }
      $original = $snapshots[$nid]['fields'];
      if ($node->uuid() !== $original['uuid'][0]['value'] || (int) $node->getRevisionId() !== (int) $original['vid'][0]['value']) {
        throw new \RuntimeException(sprintf('O Node %s não preservou NID, UUID e VID.', $nid));
      }
      if ((int) $node->isPublished() !== (int) ($original['status'][0]['value'] ?? 0)) {
        throw new \RuntimeException(sprintf('O Node %s não preservou o estado de publicação.', $nid));
      }
      $revisions = $node_storage->getQuery()->allRevisions()->accessCheck(FALSE)->condition('nid', (int) $nid)->count()->execute();
      if ($revisions !== 1) {
        throw new \RuntimeException(sprintf('O Node %s não preservou sua revisão.', $nid));
      }
      $aliases = $alias_storage->loadByProperties(['path' => '/node/' . $nid]);
      $expected_aliases = $snapshots[$nid]['aliases'];
      if (count($aliases) !== count($expected_aliases)) {
        throw new \RuntimeException(sprintf('O Node %s não preservou a quantidade de aliases.', $nid));
      }
      foreach ($expected_aliases as $expected_alias) {
        $restored = $alias_storage->load($expected_alias['id'][0]['value']);
        if (!$restored instanceof PathAliasInterface
          || $restored->uuid() !== $expected_alias['uuid'][0]['value']
          || (int) $restored->getRevisionId() !== (int) $expected_alias['revision_id'][0]['value']
          || $restored->getPath() !== $expected_alias['path'][0]['value']
          || $restored->getAlias() !== $expected_alias['alias'][0]['value']) {
          throw new \RuntimeException(sprintf('O alias %s do Node %s não preservou identidade e revisão.', $expected_alias['id'][0]['value'], $nid));
        }
      }
    }
    $legacy_count = $node_storage->getQuery()->accessCheck(FALSE)->condition('type', ['article', 'blog'], 'IN')->count()->execute();
    if ($legacy_count !== 0) {
      throw new \RuntimeException('Restaram Nodes article/blog após a migração.');
    }
  }

}
