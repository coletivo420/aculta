<?php

namespace Drupal\aculta_lms_h5p\Entity;

// ACULTA fork of Drupal H5P 2.0.0-beta1; adapted 2026-10-18.
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PDrupal;
use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PEvent;
use Drupal\aculta_lms_h5p\aculta_H5PContentStorageSchema;
use Drupal\aculta_lms_h5p\aculta_H5PContentViewsData;

/**
 * Legacy H5P class identifier retained for Drupal discovery.
 *
 * Defines the H5P content entity. */
#[ContentEntityType(
  id: 'aculta_h5p_content',
  label: new TranslatableMarkup('H5P Content'),
  handlers: [
    'storage_schema' => aculta_H5PContentStorageSchema::class,
    'views_data' => aculta_H5PContentViewsData::class,
  ],
  base_table: 'h5p_content',
  entity_keys: ['id' => 'id'],
)]
// phpcs:ignore Drupal.NamingConventions.ValidClassName.StartWithCapital, Drupal.NamingConventions.ValidClassName.NoUnderscores -- Preserve the H5P discovery identifier.
class aculta_H5PContent extends ContentEntityBase implements ContentEntityInterface {

  /**
   * Cached library metadata for the H5P content entity.
   *
   * @var \stdClass|null
   */
  protected $library;

  /**
   * Defines base fields for H5P content and course document references.
   */
  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   Base fields for H5P content.
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Standard field, used as unique if primary index.
    if ($fields['id'] instanceof BaseFieldDefinition) {
      $fields['id']->setDescription(t('The ID of the H5P Content entity.'));
    }

    $fields['library_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Library ID'))
      ->setDescription(t('The ID of the library we instanciate using our parameters.'))
      ->setSetting('unsigned', TRUE)
      ->setSetting('size', 'normal')
      ->setRequired(TRUE);

    $fields['parameters'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Parameters'))
      ->setDescription(t('The raw/unsafe parameters.'))
      ->setSetting('size', 'big')
      ->setRequired(TRUE);

    $fields['filtered_parameters'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Filtered Parameters'))
      ->setDescription(t('The filtered parameters that are safe to use'))
      ->setSetting('size', 'big')
      ->setDefaultValue('');

    $fields['disabled_features'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Disabled Features'))
      ->setDescription(t('Keeps track of which features has been disabled for the content.'))
      ->setSetting('unsigned', TRUE)
      ->setSetting('size', 'small')
      ->setDefaultValue(0);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('Title of the main content'))
      ->setSetting('max_length', '255')
      ->setDefaultValue(NULL);

    $fields['a11y_title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Assistive Technologies label'))
      ->setDescription(t('Accessibility title for content'))
      ->setSetting('max_length', '255')
      ->setDefaultValue(NULL);

    $fields['authors'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Authors'))
      ->setDescription(t('List of authors in json format'))
      ->setSetting('size', 'big')
      ->setDefaultValue(NULL);

    $fields['source'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Source'))
      ->setDescription(t('Link to the source, could also be citation'))
      ->setSetting('max_length', '2083')
      ->setDefaultValue(NULL);

    $fields['year_from'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Year (from)'))
      ->setDescription(t('Start year for copyright'))
      ->setSetting('unsigned', TRUE)
      ->setSetting('size', 'normal');

    $fields['year_to'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Year (to)'))
      ->setDescription(t('End year for copyright'))
      ->setSetting('unsigned', TRUE)
      ->setSetting('size', 'normal');

    $fields['license'] = BaseFieldDefinition::create('string')
      ->setLabel(t('License'))
      ->setDescription(t('License name of the content'))
      ->setSetting('max_length', '32')
      ->setDefaultValue(NULL);

    $fields['license_version'] = BaseFieldDefinition::create('string')
      ->setLabel(t('License version'))
      ->setDescription(t('Version of license used for the content'))
      ->setSetting('max_length', '10')
      ->setDefaultValue(NULL);

    $fields['changes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Changes'))
      ->setDescription(t('List of changes in json format'))
      ->setSetting('size', 'big')
      ->setDefaultValue(NULL);

    $fields['license_extras'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('License extras'))
      ->setDescription(t('Extra licensing terms'))
      ->setSetting('size', 'big')
      ->setDefaultValue(NULL);

    $fields['author_comments'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Authors comments'))
      ->setDescription(t('Comments for the editor'))
      ->setSetting('size', 'big')
      ->setDefaultValue(NULL);

    $fields['default_language'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Default Language'))
      ->setDescription(t('Default language for common fields'))
      ->setSetting('max_length', '32')
      ->setDefaultValue(NULL);

    // Reference existing private course Media without copying its file.
    $fields['course_documents'] = BaseFieldDefinition::create(
      'entity_reference',
    )
      ->setLabel(t('Documentos de apoio do curso'))
      ->setDescription(t(
        'Documentos privados existentes relacionados ao curso.',
      ))
      ->setSetting('target_type', 'media')
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED);

    return $fields;
  }

  /**
   * Load library used by content.
   */
  protected function loadLibrary(): void {
    $framework = aculta_H5PDrupal::getInstance();
    if (!$framework instanceof aculta_H5PDrupal) {
      throw new \LogicException('The H5P framework instance is unavailable.');
    }
    $statement = $framework->getDatabaseConnection()->query("
      SELECT title,
            machine_name AS name,
            major_version AS major,
            minor_version AS minor,
            embed_types,
            fullscreen
      FROM {h5p_libraries}
      WHERE library_id = :id",
      [
        ':id' => $this->get('library_id')->value,
      ]);
    $result = $statement?->fetchObject();
    $this->library = $result instanceof \stdClass ? $result : NULL;
  }

  /**
   * Returns library metadata in the requested format.
   *
   * @param bool $assoc
   *   Whether to return an associative array.
   *
   * @return ($assoc is true ? array{machineName: string, majorVersion: int|string, minorVersion: int|string, name: string}|null : \stdClass|null)
   *   Library data in the requested shape, or NULL when its record is missing.
   */
  public function getLibrary(bool $assoc = FALSE): array|\stdClass|null {
    if (empty($this->library)) {
      $this->loadLibrary();
    }

    if ($this->library === NULL) {
      return NULL;
    }
    $name = $this->library->name ?? NULL;
    $major = $this->library->major ?? NULL;
    $minor = $this->library->minor ?? NULL;
    if (!is_string($name) || !(is_string($major) || is_int($major)) || !(is_string($minor) || is_int($minor))) {
      return NULL;
    }
    if ($assoc) {
      return [
        'name' => $name,
        'machineName' => $name,
        'majorVersion' => $major,
        'minorVersion' => $minor,
      ];
    }

    return $this->library;
  }

  /**
   * Returns the H5P library name and version.
   */
  public function getLibraryString(): string {
    if (empty($this->library)) {
      $this->loadLibrary();
    }

    if ($this->library === NULL) {
      return '';
    }
    $name = $this->library->name ?? NULL;
    $major = $this->library->major ?? NULL;
    $minor = $this->library->minor ?? NULL;
    return is_string($name) && (is_string($major) || is_int($major)) && (is_string($minor) || is_int($minor))
      ? "{$name} {$major}.{$minor}"
      : '';
  }

  /**
   * Returns the library database ID used by this content entity.
   */
  public function getLibraryId(): int|string|null {
    $id = $this->get('library_id')->value;
    return is_int($id) || is_string($id) ? $id : NULL;
  }

  /**
   * Returns the preferred embed mode supported by the installed library.
   *
   * H5P Core supports both modes. Div is preferred when available; iframe-only
   * libraries use H5P Core's same-origin, about:blank iframe initializer.
   */
  public function getEmbedType(): ?string {
    if (empty($this->library)) {
      $this->loadLibrary();
    }
    if (empty($this->library)) {
      return NULL;
    }

    $embedTypesValue = $this->library->embed_types ?? NULL;
    if (!is_string($embedTypesValue)) {
      return NULL;
    }
    $embedTypes = array_map('trim', explode(',', strtolower($embedTypesValue)));
    if (in_array('div', $embedTypes, TRUE)) {
      return 'div';
    }
    return in_array('iframe', $embedTypes, TRUE) ? 'iframe' : NULL;
  }

  /**
   * Reports whether the library supports div embedding.
   */
  public function isDivEmbeddable(): bool {
    return $this->getEmbedType() === 'div';
  }

  /**
   * Keeps H5P's export hook disabled to avoid exposing package sources.
   */
  // phpcs:ignore Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps -- Preserve the H5P export hook identifier.
  protected function getExportURL(): string {
    // Exports contain all package source files. Do not expose a direct URL;
    // an authenticated export endpoint requires a separate access contract.
    return '';
  }

  /**
   * Only use for data comparison. Must not be used for content display.
   */
  public function getParameters(): mixed {
    $value = $this->get('parameters')->value;
    return is_string($value) ? json_decode($value) : NULL;
  }

  /**
   * Returns parameters filtered by H5P Core for presentation.
   */
  public function getFilteredParameters(): string {
    if (empty($this->library)) {
      $this->loadLibrary();
    }

    if ($this->library === NULL) {
      return '';
    }
    $library = $this->getLibrary(TRUE);
    if (!is_array($library)) {
      return '';
    }
    $content = [
      'title' => $this->get('title')->value,
      'id' => $this->id(),
      'slug' => 'interactive-content',
      'library' => $library,
      'params' => $this->get('parameters')->value,
      'filtered' => $this->get('filtered_parameters')->value,
      'embedType' => $this->getEmbedType() ?? 'div',
      'metadata' => $this->getMetadata(),
    ];

    $core = aculta_H5PDrupal::getInstance('core');
    if (!$core instanceof \H5PCore) {
      throw new \LogicException('The H5P core instance is unavailable.');
    }
    $filteredParameters = $core->filterParameters($content);
    if (!is_string($filteredParameters)) {
      return '';
    }

    // H5P Core returns the filtered parameter object as JSON. Decode that
    // JSON before invoking the alter hook, then encode the altered object.
    $filteredAsJson = json_decode($filteredParameters);
    $framework = aculta_H5PDrupal::getInstance();
    if (!$framework instanceof aculta_H5PDrupal) {
      throw new \LogicException('The H5P framework instance is unavailable.');
    }
    $framework->alterFilteredParameters($filteredAsJson);
    return json_encode($filteredAsJson, JSON_THROW_ON_ERROR);
  }

  /**
   * @return array<mixed>
   *   Metadata returned by H5P's metadata validator.
   */
  public function getMetadata(): array {
    $authorsValue = $this->get('authors')->value;
    $changesValue = $this->get('changes')->value;
    $metadata = [
      'title' => $this->get('title')->value,
      'authors' => is_string($authorsValue) ? json_decode($authorsValue) : NULL,
      'source' => $this->get('source')->value,
      'yearFrom' => $this->get('year_from')->value,
      'yearTo' => $this->get('year_to')->value,
      'license' => $this->get('license')->value,
      'licenseVersion' => $this->get('license_version')->value,
      'licenseExtras' => $this->get('license_extras')->value,
      'authorComments' => $this->get('author_comments')->value,
      'defaultLanguage' => $this->get('default_language')->value,
      'changes' => is_string($changesValue) ? json_decode($changesValue) : NULL,
      'a11yTitle' => $this->get('a11y_title')->value,
    ];
    foreach ($metadata as $key => $data) {
      if (is_null($data)) {
        unset($metadata[$key]);
      }
    }

    $core = aculta_H5PDrupal::getInstance('core');
    if (!$core instanceof \H5PCore) {
      throw new \LogicException('The H5P core instance is unavailable.');
    }
    $framework = aculta_H5PDrupal::getInstance();
    if (!$framework instanceof aculta_H5PDrupal) {
      throw new \LogicException('The H5P framework instance is unavailable.');
    }
    $validator = new \H5PContentValidator($framework, $core);
    return $validator->validateMetadata($metadata);
  }

  /**
   * Get parameters + metadata in JSON format for the editor.
   *
   * @return string
   *   Filtered parameters and content metadata encoded as JSON.
   */
  // phpcs:ignore Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps -- Preserve the H5P editor protocol method identifier.
  public function getEditorJSON(): string {
    $parameters = $this->getFilteredParameters();
    return '{"params":' . ($parameters !== '' ? $parameters : '{}') . ',"metadata":' . json_encode($this->getMetadata(), JSON_THROW_ON_ERROR) . '}';
  }

  /**
   * Builds H5P integration settings for the current user and content.
   */
  // phpcs:ignore Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps -- Preserve the H5P integration protocol method identifier.
  /**
   * @return array<string, mixed>
   *   Player integration settings.
   */
  public function getaculta_H5PIntegrationSettings(bool $canUpdateEntity = FALSE): array {
    if (empty($this->library)) {
      $this->loadLibrary();
    }

    // Load user data for content.
    $framework = aculta_H5PDrupal::getInstance();
    if (!$framework instanceof aculta_H5PDrupal) {
      throw new \LogicException('The H5P framework instance is unavailable.');
    }
    if ($this->library === NULL) {
      return [];
    }
    $statement = $framework->getDatabaseConnection()->query("
      SELECT sub_content_id, data_id, data
        FROM {h5p_content_user_data}
      WHERE user_id = :user_id
        AND content_main_id = :content_id
        AND preloaded = 1",
      [
        ':user_id' => $framework->getCurrentUserId(),
        ':content_id' => $this->id(),
      ]);
    if ($statement === NULL) {
      return [];
    }

    $content_user_data = [
      0 => [
        'state' => '{}',
      ],
    ];
    foreach ($statement as $result) {
      if (!is_object($result)
        || !isset($result->sub_content_id, $result->data_id)
        || !is_int($result->sub_content_id) && !is_string($result->sub_content_id)
        || !is_int($result->data_id) && !is_string($result->data_id)) {
        continue;
      }
      if (isset($result->data) && is_string($result->data)) {
        $content_user_data[$result->sub_content_id][$result->data_id] = $result->data;
      }
    }

    $core = aculta_H5PDrupal::getInstance('core');
    if (!$core instanceof \H5PCore) {
      throw new \LogicException('The H5P core instance is unavailable.');
    }
    $filtered_parameters = $this->getFilteredParameters();
    $disabledFeatures = $this->get('disabled_features')->value;
    $display_options = $core->getDisplayOptionsForView(
      is_numeric($disabledFeatures) ? (int) $disabledFeatures : 0,
      (int) $canUpdateEntity,
    );

    return [
      'library' => $this->getLibraryString(),
      'jsonContent' => $filtered_parameters,
      'fullScreen' => is_bool($this->library->fullscreen ?? NULL) ? $this->library->fullscreen : FALSE,
      'exportUrl' => $this->getExportURL(),
      'metadata' => $this->getMetadata(),
      'contentUserData' => $content_user_data,
      'displayOptions' => $display_options,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function delete(): void {
    if (empty($this->library)) {
      $this->loadLibrary();
    }

    // Delete entity.
    $id = $this->id();
    parent::delete();

    // Delete all associated files and data.
    $storage = aculta_H5PDrupal::getInstance('storage');
    if (!$storage instanceof \H5PStorage) {
      throw new \LogicException('The H5P storage instance is unavailable.');
    }
    $storage->deletePackage([
      'id' => $id,
      'slug' => 'interactive-content',
    ]);

    // Log content delete.
    if ($this->library === NULL) {
      return;
    }
    $name = $this->library->name ?? NULL;
    $major = $this->library->major ?? NULL;
    $minor = $this->library->minor ?? NULL;
    if (is_string($name) && (is_int($major) || is_string($major)) && (is_int($minor) || is_string($minor))) {
      new aculta_H5PEvent('content', 'delete',
        $id === NULL ? NULL : (string) $id, '',
        $name, $major . '.' . $minor,
      );
    }
  }

}
