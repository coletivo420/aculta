<?php

declare(strict_types=1);

namespace Drupal\aculta_portal_cep\Settings;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides the configured CEP lookup sources.
 *
 * The default source is checked first; other enabled sources follow their
 * configured tabledrag weight.
 */
final class CepSources {

  public const SETTINGS = 'aculta_portal_cep.settings';

  /**
   * Defaults for sites without saved source configuration.
   *
   * These values match config/install/aculta_portal_cep.settings.yml.
   */
  public const DEFAULTS = [
    'default_source' => 'local',
    'sources' => [
      'local' => ['enabled' => TRUE, 'weight' => 0],
      'viacep' => ['enabled' => TRUE, 'weight' => 1],
      'brasilapi' => ['enabled' => TRUE, 'weight' => 2],
    ],
  ];

  /**
   * Source identifiers and labels in their default order.
   */

  public const LABELS = [
    'local' => 'Base local de CEPs (arquivos)',
    'viacep' => 'ViaCEP',
    'brasilapi' => 'BrasilAPI',
  ];

  public function __construct(
    #[Autowire(service: 'config.factory')]
    private readonly ConfigFactoryInterface $configs,
    #[Autowire(service: 'string_translation')]
    private readonly TranslationInterface $translation,
  ) {}

  /**
   * Returns enabled sources in lookup order.
   *
   * @return array<int, string>
   */
  public function ordered(): array {
    $config = $this->configs->get(self::SETTINGS);
    $rawSources = $config->get('sources');
    $sources = is_array($rawSources) ? $rawSources : self::DEFAULTS['sources'];
    $enabled = [];
    foreach (array_keys(self::LABELS) as $id) {
      $sourceEntry = is_array($sources[$id] ?? NULL) ? $sources[$id] : [];
      if (($sourceEntry['enabled'] ?? FALSE) === TRUE) {
        $weight = $sourceEntry['weight'] ?? 0;
        $enabled[$id] = is_int($weight) || is_numeric($weight) ? (int) $weight : 0;
      }
    }
    asort($enabled);
    /** @var array<int, string> $order */
    $order = array_keys($enabled);
    $rawDefault = $config->get('default_source');
    $default = is_string($rawDefault) && $rawDefault !== '' ? $rawDefault : self::DEFAULTS['default_source'];
    if (in_array($default, $order, TRUE)) {
      $order = array_values(array_unique(array_merge([$default], $order)));
    }
    return $order;
  }

  /**
   * Checks whether a source is enabled.
   */
  public function enabled(string $id): bool {
    return in_array($id, $this->ordered(), TRUE);
  }

  /**
   * Returns the translated label for a source.
   */
  public function label(string $id): string {
    return (string) $this->translation->translate(self::LABELS[$id] ?? $id);
  }

}
