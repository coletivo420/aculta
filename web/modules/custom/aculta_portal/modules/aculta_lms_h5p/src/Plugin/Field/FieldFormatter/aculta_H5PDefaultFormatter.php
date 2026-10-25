<?php
// ACULTA fork of Drupal H5P 2.0.0-beta1; adapted 2026-10-18.

namespace Drupal\aculta_lms_h5p\Plugin\Field\FieldFormatter;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PDrupal;
use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PLibraryIdentifier;
use Drupal\aculta_lms_h5p\Entity\aculta_H5PContent;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\aculta_pdf_viewer\Pdf\PdfViewerBuilderInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Plugin implementation of the H5P formatter. */
#[FieldFormatter(
  id: 'h5p_default',
  label: new TranslatableMarkup('Padrão'),
  field_types: ['h5p'],
)]
class aculta_H5PDefaultFormatter extends FormatterBase {

  /**
   * The h5p settings config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructor.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
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
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('module_handler'),
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('aculta_lms_h5p.framework'),
      $container->get('aculta_pdf_viewer.builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();

    $summary[] = $this->t('Exibe conteúdo interativo H5P.');

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = array();

    $course = $items->getEntity();
    if ($course instanceof CourseInterface) {
      $takeAccess = $course->takeAccess($this->currentUser);
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
      $value = $item->getValue();

      // Load H5P Content entity
      $h5p_content = aculta_H5PContent::load($value['h5p_content_id']);
      if (empty($h5p_content)) {
        continue;
      }

      // Grab generic integration settings
      $course = $item->getEntity();
      $h5p_integration = aculta_H5PDrupal::getGenericaculta_H5PIntegrationSettings($course instanceof CourseInterface ? $course : NULL);
      $core = aculta_H5PDrupal::getInstance('core');
      $preloaded_dependencies = $core->loadContentDependencies($h5p_content->id(), 'preloaded');

      // Add content specific settings
      $content_id_string = 'cid-' . $h5p_content->id();
      $embedType = $h5p_content->getEmbedType();
      $content_settings = $h5p_content->getaculta_H5PIntegrationSettings($item->getEntity()->access('update'));
      $dependency_assets = $core->getDependenciesFiles($preloaded_dependencies, 'h5p-library-assets');
      $content_settings['scripts'] = $core->getAssetsUrls($dependency_assets['scripts']);
      $content_settings['styles'] = $core->getAssetsUrls($dependency_assets['styles']);
      if ($course instanceof CourseInterface) {
        $content_settings['contentUrl'] = base_path() . 'h5p-content/' . $course->id() . '/' . $h5p_content->id();
      }
      $content_settings['exportUrl'] = '';
      $content_settings['displayOptions']['download'] = FALSE;
      $content_settings['displayOptions']['embed'] = FALSE;
      $h5p_integration['contents'][$content_id_string] = $content_settings;

      $loadpackages = [
        'aculta_lms_h5p/h5p.content',
      ];

      // Get the H5P interactive's dependencies.
      $h5p_packages = [];
      foreach ($preloaded_dependencies as $dependency) {
        $h5p_packages[] = 'aculta_lms_h5p/' . aculta_H5PLibraryIdentifier::toDrupalLibraryId($dependency);
      }

      // Render only in-context content. The former public iframe endpoint
      // loaded H5P by ID without authorizing its parent entity or Group. H5P
      // Core's internal iframe mode stays inside this authorized page and
      // initializes an about:blank document; it does not use that endpoint.
      if ($embedType === NULL) {
        $element[$delta] = [
          '#type' => 'container',
          '#attributes' => ['data-portal-ui' => 'h5p-content-unavailable'],
          'message' => ['#plain_text' => $this->t('Esta atividade interativa não está disponível neste curso.')],
        ];
        continue;
      }

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
            ]
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
        $course instanceof CourseInterface
        && $h5p_content->hasField('course_documents')
      ) {
        $documents = [];
        $cacheability->addCacheableDependency($course);
        $relationshipListTag = 'group_relationship_list:plugin:'
          . 'aculta_pdf_viewer_group:course_document:group:' . $course->id();
        $cacheability->addCacheTags([
          $relationshipListTag,
        ]);
        foreach (
          $h5p_content->get('course_documents')->referencedEntities()
          as $media
        ) {
          if (
            !$media instanceof MediaInterface
            || $media->bundle() !== 'course_document'
          ) {
            continue;
          }
          $belongsToCourse = FALSE;
          foreach (
            $course->getRelationships('aculta_pdf_viewer_group:course_document')
            as $relationship
          ) {
            $cacheability->addCacheableDependency($relationship);
            if ((int) $relationship->getEntity()->id() === (int) $media->id()) {
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
            '#type' => 'container',
            '#attributes' => ['data-portal-ui' => 'lms-h5p-course-documents'],
            'items' => $documents,
          ];
          $cacheability->applyTo($element[$delta]['course_documents']);
        }
      }
    }
    $this->moduleHandler->alter('h5p_formatter', $element, $items);

    return $element;
  }

}
