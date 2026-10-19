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
use Drupal\aculta_lms_h5p\H5PDrupal\H5PDrupal;
use Drupal\aculta_lms_h5p\H5PDrupal\H5PLibraryIdentifier;
use Drupal\aculta_lms_h5p\Entity\H5PContent;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Plugin implementation of the H5P formatter. */
#[FieldFormatter(
  id: 'h5p_default',
  label: new TranslatableMarkup('Padrão'),
  field_types: ['h5p'],
)]
class H5PDefaultFormatter extends FormatterBase {

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
    protected H5PDrupal $framework,
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
      $h5p_content = H5PContent::load($value['h5p_content_id']);
      if (empty($h5p_content)) {
        continue;
      }

      // Grab generic integration settings
      $course = $item->getEntity();
      $h5p_integration = H5PDrupal::getGenericH5PIntegrationSettings($course instanceof CourseInterface ? $course : NULL);

      // Add content specific settings
      $content_id_string = 'cid-' . $h5p_content->id();
      $content_settings = $h5p_content->getH5PIntegrationSettings($item->getEntity()->access('update'));
      if ($course instanceof CourseInterface) {
        $content_settings['contentUrl'] = base_path() . 'h5p-content/' . $course->id() . '/' . $h5p_content->id();
      }
      $content_settings['exportUrl'] = '';
      $content_settings['displayOptions']['download'] = FALSE;
      $content_settings['displayOptions']['embed'] = FALSE;
      $h5p_integration['contents'][$content_id_string] = $content_settings;

      $core = H5PDrupal::getInstance('core');
      $preloaded_dependencies = $core->loadContentDependencies($h5p_content->id(), 'preloaded');

      $loadpackages = [
        'aculta_lms_h5p/h5p.content',
      ];

      // Get the H5P interactive's dependencies.
      $h5p_packages = [];
      foreach ($preloaded_dependencies as $dependency) {
        $h5p_packages[] = 'aculta_lms_h5p/' . H5PLibraryIdentifier::toDrupalLibraryId($dependency);
      }

      // Render only in-context content. The former public iframe endpoint
      // loaded H5P by ID without authorizing its parent entity or Group.
      if (!$h5p_content->isDivEmbeddable()) {
        $element[$delta] = [
          '#type' => 'container',
          '#attributes' => ['data-portal-ui' => 'h5p-content-unavailable'],
          'message' => ['#plain_text' => $this->t('Esta atividade interativa não está disponível neste curso.')],
        ];
        continue;
      }

      $loadpackages = array_merge($loadpackages, $h5p_packages);
      // A classe h5p-content é contrato do H5P core (vendor/h5p/h5p-core/js/h5p.js), não de design.
      $element[$delta] = array(
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['h5p-content'], 'data-content-id' => $h5p_content->id()],
        '#value' => '',
        '#attached' => [
          'drupalSettings' => [
            'h5p' => [
              'H5PIntegration' => $h5p_integration,
            ]
          ],
          'library' => $loadpackages,
        ],
      );

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
    }
    $this->moduleHandler->alter('h5p_formatter', $element, $items);

    return $element;
  }

}
