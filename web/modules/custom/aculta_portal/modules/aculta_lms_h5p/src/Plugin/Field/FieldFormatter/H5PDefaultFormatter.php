<?php
// ACULTA fork of Drupal H5P 2.0.0-beta1; adapted 2026-10-18.

namespace Drupal\aculta_lms_h5p\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\aculta_lms_h5p\H5PDrupal\H5PDrupal;
use Drupal\aculta_lms_h5p\Entity\H5PContent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'h5p_default' formatter.
 *
 * @FieldFormatter(
 *   id = "h5p_default",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "h5p"
 *   },
 *   quickedit = {
 *     "editor" = "disabled"
 *   }
 * )
 */
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
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = array();

    $summary[] = t('Displays interactive H5P content.');

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = array();

    foreach ($items as $delta => $item) {
      $value = $item->getValue();

      // Load H5P Content entity
      $h5p_content = H5PContent::load($value['h5p_content_id']);
      if (empty($h5p_content)) {
        continue;
      }

      // Grab generic integration settings
      $h5p_integration = H5PDrupal::getGenericH5PIntegrationSettings();

      // Add content specific settings
      $content_id_string = 'cid-' . $h5p_content->id();
      $h5p_integration['contents'][$content_id_string] = $h5p_content->getH5PIntegrationSettings($item->getEntity()->access('update'));

      $core = H5PDrupal::getInstance('core');
      $preloaded_dependencies = $core->loadContentDependencies($h5p_content->id(), 'preloaded');

      $loadpackages = [
        'aculta_lms_h5p/h5p.content',
      ];

      // Get the H5P interactive's dependencies.
      $h5p_packages = [];
      foreach ($preloaded_dependencies as $dependency) {
        $h5p_packages[] = 'h5p/' . _aculta_lms_h5p_library_machine_to_id($dependency);
      }

      // Render only in-context content. The former public iframe endpoint
      // loaded H5P by ID without authorizing its parent entity or Group.
      if (!$h5p_content->isDivEmbeddable()) {
        $element[$delta] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['h5p-content-unavailable']],
          'message' => ['#plain_text' => $this->t('This interactive content is unavailable in this course context.')],
        ];
        continue;
      }

      $loadpackages = array_merge($loadpackages, $h5p_packages);
      $html = '<div class="h5p-content" data-content-id="' . $h5p_content->id() . '"></div>';

      // Render each element as markup.
      $element[$delta] = array(
        '#type' => 'markup',
        '#markup' => $html,
        '#allowed_tags' => ['div'],
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
        ->addCacheTags($this->config->getCacheTags());
      if (H5PDrupal::getInstance()->getOption('save_content_state', FALSE)) {
        $cacheability->addCacheContexts(['user']);
      }

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
