<?php

namespace Drupal\aculta_lms_h5p\Plugin\Field\FieldFormatter;

use Drupal\aculta_lms_h5p\Plugin\Field\FieldType\aculta_H5PItem;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PDrupal;
use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PLibraryIdentifier;
use Drupal\aculta_lms_h5p\Entity\aculta_H5PContent;
use Drupal\group\Entity\GroupInterface;
use Drupal\aculta_document_viewer\Pdf\PdfViewerBuilderInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the H5P formatter.
 *
 * @extends \Drupal\Core\Field\FormatterBase<\Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>>
 */
#[FieldFormatter(
  id: 'h5p_default',
  label: new TranslatableMarkup('Padrão'),
  field_types: ['h5p'],
)]
// phpcs:ignore Drupal.NamingConventions.ValidClassName.StartWithCapital,Drupal.NamingConventions.ValidClassName.NoUnderscores -- Preserve the historical H5P fork class identifier used by plugin discovery and cached forms.
final class aculta_H5PDefaultFormatter extends FormatterBase {

  /**
   * The h5p settings config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructs this service or plugin.
   *
   * @param string $plugin_id
   *   Value used for $plugin_id.
   * @param mixed $plugin_definition
   *   Value used for $plugin_definition.
   * @param FieldDefinitionInterface $field_definition
   *   Value used for $field_definition.
   * @param array<string, mixed> $settings
   *   Value used for $settings.
   * @param string $label
   *   Value used for $label.
   * @param string $view_mode
   *   Value used for $view_mode.
   * @param array<string, mixed> $third_party_settings
   *   Value used for $third_party_settings.
   * @param ModuleHandlerInterface $moduleHandler
   *   Value used for $moduleHandler.
   * @param ConfigFactoryInterface $config_factory
   *   Value used for $config_factory.
   * @param AccountProxyInterface $currentUser
   *   Value used for $currentUser.
   * @param aculta_H5PDrupal $framework
   *   Value used for $framework.
   * @param PdfViewerBuilderInterface $pdfViewerBuilder
   *   Value used for $pdfViewerBuilder.
   */
  public function __construct(
    string $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    string $label,
    string $view_mode,
    array $third_party_settings,
    protected ModuleHandlerInterface $moduleHandler,
    ConfigFactoryInterface $config_factory,
    protected AccountProxyInterface $currentUser,
    protected aculta_H5PDrupal $framework,
    protected PdfViewerBuilderInterface $pdfViewerBuilder,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->config = $config_factory->get('aculta_lms_h5p.settings');
  }

  /**
   * {@inheritdoc}
   *
   * @param ContainerInterface $container
   *   Dependency injection container.
   * @param array<array-key, mixed> $configuration
   *   Plugin configuration.
   * @param mixed $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $fieldDefinition = $configuration['field_definition'] ?? NULL;
    $settings = $configuration['settings'] ?? NULL;
    $label = $configuration['label'] ?? NULL;
    $viewMode = $configuration['view_mode'] ?? NULL;
    $thirdPartySettings = $configuration['third_party_settings'] ?? NULL;
    if (!$fieldDefinition instanceof FieldDefinitionInterface
      || !is_array($settings)
      || !is_string($label)
      || !is_string($viewMode)
      || !is_array($thirdPartySettings)
      || !is_string($plugin_id)) {
      throw new \InvalidArgumentException('The H5P formatter configuration is invalid.');
    }
    $typedSettings = [];
    foreach ($settings as $key => $value) {
      if (!is_string($key)) {
        throw new \InvalidArgumentException('The H5P formatter settings keys must be strings.');
      }
      $typedSettings[$key] = $value;
    }
    $typedThirdPartySettings = [];
    foreach ($thirdPartySettings as $key => $value) {
      if (!is_string($key)) {
        throw new \InvalidArgumentException('The H5P third-party settings keys must be strings.');
      }
      $typedThirdPartySettings[$key] = $value;
    }
    return new static($plugin_id,
      $plugin_definition,
      $fieldDefinition,
      $typedSettings,
      $label,
      $viewMode,
      $typedThirdPartySettings,
      $container->get('module_handler'),
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('aculta_lms_h5p.framework'),
      $container->get('aculta_document_viewer.pdf_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summary[] = $this->t('Exibe conteúdo interativo H5P.');

    return $summary;
  }

  /**
   * {@inheritdoc}
   */

  /**
   * {@inheritdoc}
   *
   * @return array<int, array<array-key, mixed>>
   *   Render arrays for each H5P item.
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];

    $course = $items->getEntity();
    if ($course instanceof GroupInterface) {
      $takeAccess = AccessResult::allowedIf($course->hasPermission('take course', $this->currentUser))
        ->cachePerUser()
        ->addCacheContexts(['user.permissions', 'user.group_permissions']);
      $updateAccess = $course->access('update', $this->currentUser, TRUE);
      $fieldAccess = AccessResult::allowedIf($takeAccess->isAllowed() || $updateAccess->isAllowed())
        ->addCacheableDependency($course)
        ->addCacheableDependency($takeAccess)
        ->addCacheableDependency($updateAccess)
        ->addCacheContexts(['user', 'user.group_permissions']);
      if (!$fieldAccess->isAllowed()) {
        $denied = ['#access' => FALSE];
        CacheableMetadata::createFromObject($fieldAccess)->applyTo($denied);
        return [$denied];
      }
    }

    foreach ($items as $delta => $item) {
      if (!$item instanceof aculta_H5PItem) {
        continue;
      }
      /** @var array{h5p_content_id?: mixed} $value */
      $value = $item->getValue();
      $contentId = $value['h5p_content_id'] ?? NULL;
      if (!is_int($contentId) && !(is_string($contentId) && ctype_digit($contentId))) {
        continue;
      }

      // Load H5P Content entity.
      $h5p_content = aculta_H5PContent::load((int) $contentId);
      if (empty($h5p_content)) {
        continue;
      }

      // Grab generic integration settings.
      $course = $item->getEntity();
      $h5p_integration = aculta_H5PDrupal::getGenericaculta_H5PIntegrationSettings($course instanceof GroupInterface ? $course : NULL);
      $core = aculta_H5PDrupal::getInstance('core');
      if (!$core instanceof \H5PCore) {
        throw new \LogicException('The H5P core instance is unavailable.');
      }
      $contentEntityId = $h5p_content->id();
      if (!is_int($contentEntityId) && !(is_string($contentEntityId) && ctype_digit($contentEntityId))) {
        continue;
      }
      $preloaded_dependencies = $this->framework->loadContentDependencies((int) $contentEntityId, 'preloaded');

      // Add content specific settings.
      $content_id = $h5p_content->id();
      if (!is_int($content_id) && !is_string($content_id)) {
        continue;
      }
      $content_id_string = 'cid-' . $content_id;
      $embedType = $h5p_content->getEmbedType();

      $contentSettingsEntity = $item->getEntity();
      $content_settings = $h5p_content->getaculta_H5PIntegrationSettings($contentSettingsEntity->access('update'));
      if ($content_settings === []) {
        continue;
      }
      $dependency_assets = $core->getDependenciesFiles($preloaded_dependencies, 'h5p-library-assets');
      $scripts = $dependency_assets['scripts'] ?? [];
      $styles = $dependency_assets['styles'] ?? [];
      $content_settings['scripts'] = $core->getAssetsUrls(is_array($scripts) ? $scripts : []);
      $content_settings['styles'] = $core->getAssetsUrls(is_array($styles) ? $styles : []);
      if ($course instanceof GroupInterface) {
        $courseId = $course->id();
        $basePath = base_path();
        if ((is_int($courseId) || is_string($courseId)) && is_string($basePath)) {
          $content_settings['contentUrl'] = sprintf('%sh5p-content/%s/%s', $basePath, (string) $courseId, (string) $content_id);
        }
      }
      $content_settings['exportUrl'] = '';
      $displayOptions = $content_settings['displayOptions'] ?? [];
      $content_settings['displayOptions'] = is_array($displayOptions) ? $displayOptions : [];
      $content_settings['displayOptions']['download'] = FALSE;
      $content_settings['displayOptions']['embed'] = FALSE;
      $integrationContents = $h5p_integration['contents'] ?? [];
      $h5p_integration['contents'] = is_array($integrationContents) ? $integrationContents : [];
      $h5p_integration['contents'][$content_id_string] = $content_settings;

      $loadpackages = [
        'aculta_lms_h5p/h5p.content',
      ];

      // Get the H5P interactive's dependencies.
      $h5p_packages = [];
      foreach ($preloaded_dependencies as $dependency) {
        if ($dependency instanceof \stdClass) {
          $h5p_packages[] = 'aculta_lms_h5p/' . aculta_H5PLibraryIdentifier::toDrupalLibraryId($dependency);
        }
        elseif (is_array($dependency)) {
          $library = [];
          foreach ($dependency as $key => $value) {
            if (is_string($key)) {
              $library[$key] = $value;
            }
          }
          $h5p_packages[] = 'aculta_lms_h5p/' . aculta_H5PLibraryIdentifier::toDrupalLibraryId($library);
        }
      }

      // Render only in-context content. The former public iframe endpoint
      // loaded H5P by ID without authorizing its parent entity or Group. H5P
      // Core's internal iframe mode stays inside this authorized page and
      // initializes an about:blank document; it does not use that endpoint.
      $loadpackages = array_merge($loadpackages, $h5p_packages);
      $player = $embedType === 'iframe'
        ? [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['h5p-iframe-wrapper'],
            'data-content-id' => $h5p_content->id(),
          ],
          'iframe' => [
            '#type' => 'html_tag',
            '#tag' => 'iframe',
            '#attributes' => [
              'id' => 'h5p-iframe-' . $h5p_content->id(),
              'class' => ['h5p-iframe'],
              'data-content-id' => $h5p_content->id(),
              'title' => $h5p_content->get('a11y_title')->value ?: $h5p_content->label(),
              'allowfullscreen' => 'allowfullscreen',
              'scrolling' => 'no',
              'frameborder' => '0',
              'style' => 'height: 1px',
            ],
            '#value' => '',
          ],
        ]
        : [
          // The h5p-content class is a H5P Core JS contract, not design.
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => ['class' => ['h5p-content'], 'data-content-id' => $h5p_content->id()],
          '#value' => '',
        ];

      $element[$delta] = $player + [
        '#attached' => [
          'drupalSettings' => [
            'h5p' => [
              'H5PIntegration' => $h5p_integration,
            ],
          ],
          'library' => $loadpackages,
        ],
      ];

      // Collect the cacheability metadata for the element.
      $cacheability = CacheableMetadata::createFromObject($h5p_content)
        ->addCacheTags(['h5p_content'])
        ->addCacheTags($this->config->getCacheTags())
        // H5P tokens and saved state are user/session-specific even when the
        // optional periodic state save setting is disabled.
        ->addCacheContexts(['user', 'session']);

      // H5P core uses a hash token that becomes invalid between 12 and 24
      // hours. To be sure the cache doesn't serve an out-of-date token, set
      // the max age to 12 hours.
      $cacheability->setCacheMaxAge(12 * 60 * 60);

      $cacheability->applyTo($element[$delta]);

      if (
        $course instanceof GroupInterface
        && $h5p_content->hasField('course_documents')
      ) {
        $documents = [];
        $cacheability->addCacheableDependency($course);
        $documentPluginId = 'aculta_document_viewer:course_document';
        $relationshipListTag = 'group_relationship_list:plugin:'
          . $documentPluginId . ':group:' . $course->id();
        $cacheability->addCacheTags([
          $relationshipListTag,
        ]);
        $documentFieldList = $h5p_content->get('course_documents');
        $referencedDocuments = $documentFieldList instanceof EntityReferenceFieldItemListInterface
          ? $documentFieldList->referencedEntities()
          : [];
        foreach ($referencedDocuments as $media) {
          if (
            !$media instanceof MediaInterface
            || $media->bundle() !== 'course_document'
          ) {
            continue;
          }
          $belongsToCourse = FALSE;
          $relationships = $course->getRelationships($documentPluginId);
          if (empty($relationships)) {
            $relationships = $course->getRelationships('aculta_document_viewer_group:course_document');
          }
          foreach ($relationships as $relationship) {
            $cacheability->addCacheableDependency($relationship);
            if ((int) $relationship->getEntityId() === (int) $media->id()) {
              $belongsToCourse = TRUE;
            }
          }
          if (!$belongsToCourse) {
            continue;
          }
          $viewer = $this->pdfViewerBuilder->build(
            $media,
            'field_media_course_document',
          );
          $documents[] = $viewer;
          $cacheability->addCacheableDependency($media);
          $mediaAccess = $media->access('view', $this->currentUser, TRUE);
          $cacheability->addCacheableDependency($mediaAccess);
          $documentField = $media->get('field_media_course_document');
          $cacheability->addCacheableDependency(
            $documentField->access('view', $this->currentUser, TRUE),
          );
        }
        if ($documents) {
          $element[$delta]['course_documents'] = [
            '#theme' => 'portal_component',
            '#name' => 'layout-section',
            '#slots' => ['content' => $documents],
          ];
          $cacheability->applyTo($element[$delta]['course_documents']);
        }
      }
    }
    $this->moduleHandler->alter('h5p_formatter', $element, $items);

    /** @var array<int, array<array-key, mixed>> $element */
    foreach ($element as &$renderArray) {
      $cacheability = CacheableMetadata::createFromRenderArray($renderArray);
      // H5P integration includes a short-lived token. Alter hooks may add
      // cache dependencies, but must not remove the token expiry cap.
      $cacheability->setCacheMaxAge(12 * 60 * 60);
      $cacheability->applyTo($renderArray);
    }
    unset($renderArray);

    /** @var array<int, array<string, mixed>> $element */
    return $element;
  }

}
