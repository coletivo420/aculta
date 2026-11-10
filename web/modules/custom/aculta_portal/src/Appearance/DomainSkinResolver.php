<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Appearance;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\aculta_portal\Domain\DomainSiteManagerInterface;

/**
 * Resolves visual skin IDs from Drupal Domain IDs.
 */
final class DomainSkinResolver {

  public const DEFAULT_SKIN = 'skunk';

  /**
   * The skin IDs supported by the ACULTA UI Skins definitions.
   *
   * @var list<string>
   */
  public const SKIN_IDS = [
    'skunk', 'sativa', 'ruderalis', 'afghan', 'acapulco-gold',
    'lemon-haze', 'golden-goat', 'red-kush', 'panama-red', 'red-congolese',
  ];

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly DomainSiteManagerInterface $domainSiteManager,
  ) {}

  /**
   * Resolves the current Domain's visual skin without changing global config.
   */
  public function resolveCurrent(): string {
    $domainId = $this->domainSiteManager->getCurrentDomainId();
    return $domainId === NULL ? self::DEFAULT_SKIN : $this->resolveDomainId($domainId);
  }

  /**
   * Resolves a skin ID for a Drupal Domain ID.
   *
   * Uses the ACULTA default when no valid mapping exists.
   */
  public function resolveDomainId(string $domainId): string {
    $skinId = $this->configFactory->get('aculta_portal.visual_skins')->get('domains.' . $domainId);
    return is_string($skinId) && in_array($skinId, self::SKIN_IDS, TRUE)
      ? $skinId
      : self::DEFAULT_SKIN;
  }

}
