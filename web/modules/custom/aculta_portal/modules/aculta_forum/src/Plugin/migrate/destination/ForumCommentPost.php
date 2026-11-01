<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\migrate\destination;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\migrate\Attribute\MigrateDestination;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\migrate\destination\EntityContentBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates one revisionable Forum post with all source Comment translations.
 *
 * The migration source ID is the Comment CID, so the Migrate map contains one
 * destination post ID per Comment instead of one row per language.
 */
#[MigrateDestination(id: 'aculta_forum_comment_post')]
final class ForumCommentPost extends EntityContentBase {

  /**
   * The entity type manager used to resolve embedded references.
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
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
    ?AccountSwitcherInterface $account_switcher = NULL,
    ?EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL,
    ?EntityTypeManagerInterface $entity_type_manager = NULL,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $storage, $bundles, $entity_field_manager, $field_type_manager, $account_switcher, $entity_type_bundle_info);
    if (!$entity_type_manager instanceof EntityTypeManagerInterface) {
      throw new \InvalidArgumentException('EntityTypeManagerInterface é obrigatório para validar embeds da migration.');
    }
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   *
   * The trait infers the entity type from an "entity:<type>" derivative, but
   * this destination uses its own plugin ID. The Migrate id map calls getIds()
   * during import, so the base entity type must be declared explicitly.
   */
  protected static function getEntityTypeId($plugin_id) {
    return 'aculta_forum_post';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL): static {
    $entity_type_id = 'aculta_forum_post';
    $entity_type_manager = $container->get('entity_type.manager');
    $bundle_info = $container->get('entity_type.bundle.info');

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $entity_type_manager->getStorage($entity_type_id),
      array_keys($bundle_info->getBundleInfo($entity_type_id)),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('account_switcher'),
      $bundle_info,
      $entity_type_manager,
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\migrate\MigrateException
   *   When source translations do not satisfy the Forum post contract.
   * @throws \Drupal\migrate\Exception\EntityValidationException
   *   When configured entity validation fails.
   */
  public function import(Row $row, array $old_destination_id_values = []): array {
    $source_id = (string) $row->getSourceProperty('cid');
    $owner_id = (int) $row->getSourceProperty('uid');
    $legacy_author_id = (int) $row->getSourceProperty('legacy_author_id');
    if ($owner_id <= 0 && ($legacy_author_id <= 0
      || $this->entityTypeManager->getStorage('aculta_legado_autor')->load($legacy_author_id) === NULL)) {
      throw new MigrateException(sprintf(
        'Comment %s não tem usuário nem autoria histórica válida para o ForumPost; migração interrompida.',
        $source_id,
      ));
    }
    $default_langcode = (string) $row->getSourceProperty('langcode');
    $translations = $row->getSourceProperty('translations');
    if ($default_langcode === '' || !is_array($translations) || !isset($translations[$default_langcode])) {
      throw new MigrateException(sprintf('Comment %s não contém sua tradução padrão.', $source_id));
    }

    $rejected = (bool) $row->getSourceProperty('rejected');
    foreach ($translations as $langcode => $translation) {
      if (!is_array($translation) || !array_key_exists('published', $translation)) {
        throw new MigrateException(sprintf('Comment %s contém dados de tradução inválidos.', $source_id));
      }
      if ($rejected && (bool) $translation['published']) {
        throw new MigrateException(sprintf('Comment %s combina publicação com rejeição histórica.', $source_id));
      }
      if (trim((string) ($translation['body_value'] ?? '')) === '') {
        throw new MigrateException(sprintf('Comment %s tem corpo vazio no idioma %s.', $source_id, $langcode));
      }
      if (($translation['body_format'] ?? NULL) !== 'forum') {
        throw new MigrateException(sprintf('Comment %s usa formato de texto incompatível no idioma %s.', $source_id, $langcode));
      }
    }
    $this->validateEmbeddedEntities($source_id, $translations);

    if ($old_destination_id_values !== []) {
      $existing = $this->storage->load(reset($old_destination_id_values));
      if ($existing instanceof ForumPost && $existing->language()->getId() !== $default_langcode) {
        throw new MigrateException(sprintf(
          'Comment %s mudou o idioma padrão de %s para %s; a migration não pode trocar a tradução padrão do post existente.',
          $source_id,
          $existing->language()->getId(),
          $default_langcode,
        ));
      }
    }

    $row->setDestinationProperty('langcode', $default_langcode);
    $this->rollbackAction = MigrateIdMapInterface::ROLLBACK_DELETE;
    $entity = $this->getEntity($row, $old_destination_id_values);
    if (!$entity instanceof ForumPost) {
      throw new MigrateException(sprintf('Não foi possível criar o post de destino para o Comment %s.', $source_id));
    }
    if ($entity->language()->getId() !== $default_langcode) {
      throw new MigrateException(sprintf('Comment %s não corresponde ao idioma padrão do post de destino.', $source_id));
    }

    $source_langcodes = array_map('strval', array_keys($translations));
    foreach ($entity->getTranslationLanguages() as $language) {
      $langcode = $language->getId();
      if ($langcode !== $default_langcode && !in_array($langcode, $source_langcodes, TRUE)) {
        $entity->removeTranslation($langcode);
      }
    }

    foreach ($translations as $langcode => $values) {
      if (!$entity->hasTranslation((string) $langcode)) {
        $entity->addTranslation((string) $langcode);
      }
      $translation = $entity->getTranslation((string) $langcode);
      $translation->set('subject', (string) ($values['subject'] ?? ''));
      $translation->set('body', [
        'value' => (string) $values['body_value'],
        'format' => (string) $values['body_format'],
      ]);
      $published = (bool) $values['published'];
      $translation->set('status', $published ? 1 : 0);
      $translation->set('moderation_state', $rejected ? 'rejected' : ($published ? 'published' : 'review'));
    }

    if ($this->isEntityValidationRequired($entity)) {
      $this->validateEntity($entity);
    }
    return $this->save($entity, $old_destination_id_values);
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    return [];
  }

  /**
   * Fails closed when a Media or Gallery embed no longer resolves by UUID.
   *
   * @param string $source_id
   *   The source Comment ID.
   * @param array<string, array<string, mixed>> $translations
   *   Source translation values.
   *
   * @throws \Drupal\migrate\MigrateException
   *   When a referenced Media or Gallery is missing or has no UUID.
   */
  private function validateEmbeddedEntities(string $source_id, array $translations): void {
    $uuids = ['media' => [], 'media_gallery' => []];
    foreach ($translations as $translation) {
      $document = Html::load((string) ($translation['body_value'] ?? ''));
      $xpath = new \DOMXPath($document);
      foreach ($xpath->query('//*[@data-entity-type="media" or @data-entity-type="media_gallery"]') ?: [] as $element) {
        if (!$element instanceof \DOMElement) {
          continue;
        }
        $type = $element->getAttribute('data-entity-type');
        $uuid = trim($element->getAttribute('data-entity-uuid'));
        if ($uuid === '') {
          throw new MigrateException(sprintf('Comment %s contém embed %s sem UUID.', $source_id, $type));
        }
        $uuids[$type][$uuid] = TRUE;
      }
    }

    foreach ($uuids as $entity_type => $referenced_uuids) {
      if ($referenced_uuids === []) {
        continue;
      }
      $definition = $this->entityTypeManager->getDefinition($entity_type, FALSE);
      $uuid_key = $definition?->getKey('uuid');
      if (!$definition || !$uuid_key) {
        throw new MigrateException(sprintf('Comment %s contém embed para entidade indisponível: %s.', $source_id, $entity_type));
      }
      $storage = $this->entityTypeManager->getStorage($entity_type);
      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition($uuid_key, array_keys($referenced_uuids), 'IN')
        ->execute();
      $existing_uuids = [];
      foreach ($storage->loadMultiple($ids) as $entity) {
        $existing_uuids[(string) $entity->uuid()] = TRUE;
      }
      $missing = array_diff_key($referenced_uuids, $existing_uuids);
      if ($missing !== []) {
        throw new MigrateException(sprintf('Comment %s contém %d embed(s) %s sem entidade de destino.', $source_id, count($missing), $entity_type));
      }
    }
  }

}
