<?php

namespace Drupal\aculta_lms_h5p\Plugin\Field\FieldType;

// ACULTA fork of Drupal H5P 2.0.0-beta1; adapted 2026-10-18.
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PDrupal;
use Drupal\aculta_lms_h5p\Entity\aculta_H5PContent;

/**
 * Legacy H5P class identifier retained for Drupal discovery.
 *
 * Provides the H5P field type. */
#[FieldType(
  id: 'h5p',
  label: new TranslatableMarkup('Conteúdo interativo – H5P'),
  description: new TranslatableMarkup('Este campo armazena o ID de um conteúdo H5P como valor inteiro.'),
  category: 'reference',
  default_formatter: 'h5p_default',
  default_widget: 'h5p_upload',
)]
// phpcs:ignore Drupal.NamingConventions.ValidClassName.StartWithCapital, Drupal.NamingConventions.ValidClassName.NoUnderscores -- Preserve the H5P discovery identifier.
class aculta_H5PItem extends FieldItemBase implements FieldItemInterface {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'h5p_content_id' => [
          'description' => 'Referance to the H5P Content entity ID',
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
      ],
      'indexes' => [
        'h5p_content_id' => ['h5p_content_id'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['h5p_content_id'] = DataDefinition::create('integer')
      ->setLabel(t('H5P Content ID'))
      ->setDescription(t('References the H5P Content Entity'));

    $properties['h5p_content_revisioning_handled'] = DataDefinition::create('boolean')
      ->setLabel(t('H5P Revisioning Handled'))
      ->setDescription(t('Indicates if revisioning has already been handled'));

    $properties['h5p_content_new_translation'] = DataDefinition::create('boolean')
      ->setLabel(t('H5P New Translation'))
      ->setDescription(t('Indicates if this is new translation'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return empty($this->values['h5p_content']) && empty($this->get('h5p_content_id')->getValue());
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {

    // Handles the revisioning when there's no widget doing it.
    $h5p_content_revisioning_handled = !empty($this->get('h5p_content_revisioning_handled')->getValue());
    if ($h5p_content_revisioning_handled || $this->isEmpty()) {
      // No need to do anything.
      return;
    }

    // Determine if this is a new revision.
    $entity = $this->getEntity();
    $is_new_revision = (!empty($entity->original) && $entity->getRevisionId() != $entity->original->getRevisionId());

    // Determine if this is a new translation.
    $do_new_translation = $this->get('h5p_content_new_translation')->getValue();

    // Determine if we do revisioning for H5P content
    // (may be disabled to save disk space)
    $interface = aculta_H5PDrupal::getInstance();
    $do_new_revision = $interface->getOption('revisioning', TRUE) && $is_new_revision;
    if (!$do_new_revision && !$do_new_translation) {
      // No need to do anything.
      return;
    }

    // New revision or translation, clone the existing content.
    $h5p_content_id = $this->get('h5p_content_id')->getValue();
    $h5p_content = aculta_H5PContent::load($h5p_content_id);
    $h5p_content->set('id', NULL);
    $h5p_content->set('filtered_parameters', NULL);
    $h5p_content->save();

    // Clone content folder.
    $core = aculta_H5PDrupal::getInstance('core');
    $core->fs->cloneContent($h5p_content_id, $h5p_content->id());

    // Update field reference id.
    $this->set('h5p_content_id', $h5p_content->id());
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    // Get entity.
    $entity = $this->getEntity();

    if ($entity->isDefaultTranslation() || $this->getFieldDefinition()->isTranslatable()) {
      // Only delete the referenced H5P if this is the default translation
      // or that the field is translatable.
      // (If it's not translatable the H5P is shared between translations)
      // The content can be translated even if field translation is disabled.
      self::deleteaculta_H5PContent($this->get('h5p_content_id')->getValue());
    }

    // The following is a fix to clean up all revisions when deleting an entity
    // (deleteRevision is not called for old revisions when deleting node)
    // Bug in Drupal Core?
    static $revisionsCleanedUp = [];
    if ($entity->isDefaultTranslation() && empty($revisionsCleanedUp[$entity->id()])) {
      // Only trigger cleanup once pr entity.
      $revisionsCleanedUp[$entity->id()] = TRUE;

      $storage = aculta_H5PDrupal::getInstance()->getEntityStorage($entity->getEntityTypeId());
      $table_mapping = $storage->getTableMapping();
      $storage_definition = $this->getFieldDefinition()->getFieldStorageDefinition();

      // Check if we can get data values from the revision table, if not we use
      // the data table as no revisions has been created for this field.
      $revision_table = $table_mapping->getDedicatedRevisionTableName($storage_definition, $storage_definition->isDeleted());
      $database = aculta_H5PDrupal::getInstance()->getDatabaseConnection();
      $from_table = ($database->schema()->tableExists($revision_table) ? $revision_table : $table_mapping->getDedicatedDataTableName($storage_definition, $storage_definition->isDeleted()));

      // Find column name for field instance.
      $columns = $storage_definition->getColumns();
      $column = $table_mapping->getFieldColumnName($storage_definition, key($columns));

      // Look up all the H5P content referenced by this field.
      $results = $database->select($from_table, 'f')
        ->fields('f', [$column])
        ->condition('entity_id', $entity->id())
        ->execute();

      // Delete them one by one.
      while ($h5p_content_id = $results->fetchField()) {
        self::deleteaculta_H5PContent($h5p_content_id);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRevision() {
    $interface = aculta_H5PDrupal::getInstance();
    if ($interface->getOption('revisioning', TRUE)) {
      self::deleteaculta_H5PContent($this->get('h5p_content_id')->getValue());
    }
  }

  /**
   * Deletes H5P content referenced by this field.
   */
  // phpcs:ignore Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps -- Retain the H5P content cleanup method identifier.
  public static function deleteaculta_H5PContent($content_id) {
    if (empty($content_id)) {
      // Nothing to delete.
      return;
    }

    $h5p_content = aculta_H5PContent::load($content_id);
    if (empty($h5p_content)) {
      // Nothing to delete.
      return;
    }

    $h5p_content->delete();
  }

}
