<?php

declare(strict_types=1);

namespace Drupal\aculta_portal_cep\Settings;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Fontes de consulta de CEP: base local (arquivos), ViaCEP e BrasilAPI.
 *
 * A ordem vem da configuração: a fonte padrão vem primeiro e as demais seguem pelo peso (tabledrag). Só as fontes
 * ativas entram na consulta. Substitui a ordem fixa que estava no código (base local, ViaCEP, BrasilAPI).
 */
final class CepSources {

  public const SETTINGS = 'aculta_portal_cep.settings';

  /**
   * Padrão quando a configuração ainda não existe (módulo já instalado antes desta configuração). Igual ao
   * config/install/aculta_portal_cep.settings.yml.
   */
  public const DEFAULTS = [
    'default_source' => 'local',
    'sources' => [
      'local' => ['enabled' => TRUE, 'weight' => 0],
      'viacep' => ['enabled' => TRUE, 'weight' => 1],
      'brasilapi' => ['enabled' => TRUE, 'weight' => 2],
    ],
  ];

  /** Identificadores e rótulos das fontes, na ordem padrão. */
  
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

  /** Fontes ativas, na ordem de consulta: a padrão primeiro, depois as demais pelo peso. */
  public function ordered(): array {
    $config = $this->configs->get(self::SETTINGS);
    $sources = $config->get('sources') ?? self::DEFAULTS['sources'];
    $enabled = [];
    foreach (array_keys(self::LABELS) as $id) {
      if (($sources[$id]['enabled'] ?? FALSE) === TRUE) {
        $enabled[$id] = (int) ($sources[$id]['weight'] ?? 0);
      }
    }
    asort($enabled);
    $order = array_keys($enabled);
    $default = (string) ($config->get('default_source') ?? self::DEFAULTS['default_source']);
    if (in_array($default, $order, TRUE)) {
      $order = array_values(array_unique(array_merge([$default], $order)));
    }
    return $order;
  }

  /** Fonte ativa na configuração. */
  public function enabled(string $id): bool {
    return in_array($id, $this->ordered(), TRUE);
  }

  /** Rótulo traduzível de uma fonte. */
  public function label(string $id): string {
    return (string) $this->translation->translate(self::LABELS[$id] ?? $id);
  }

}
