<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\migrate\destination;

use Drupal\aculta_forum\Entity\ForumPost;
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

}
