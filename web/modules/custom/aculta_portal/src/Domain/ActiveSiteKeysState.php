<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Domain;

use Drupal\Core\State\StateInterface;

/**
 * Manages the neutral state list of active site keys.
 *
 * Contrato neutro exposto para o tema e outros consumidores sem exigir
 * consultas a Domain, DomainNegotiator ou DomainPurposeManager.
 */
final class ActiveSiteKeysState {

  public const STATE_KEY = 'aculta_portal.active_site_keys';

  public function __construct(
    private readonly StateInterface $state,
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  /**
   * Rebuilds and persists the list of active site keys.
   *
   * @return list<string>
   *   The active site keys.
   */
  public function rebuild(): array {
    $active = [];
    foreach (SiteKey::cases() as $siteKey) {
      $key = $siteKey->value;
      $domain = $this->domainPurposeManager->getDomain($key);
      $url = $this->domainPurposeManager->pathUrl($key, '/');
      if ($domain !== NULL && $domain->status() && $url !== NULL) {
        $active[] = $key;
      }
    }
    $this->state->set(self::STATE_KEY, $active);
    return $active;
  }

  /**
   * Retrieves the cached list of active site keys from state.
   *
   * @return list<string>
   *   Lista das chaves funcionais ativas.
   */
  public function get(): array {
    $siteKeys = $this->state->get(self::STATE_KEY);
    if (!is_array($siteKeys)) {
      return [];
    }
    $valid = array_map(static fn (SiteKey $siteKey): string => $siteKey->value, SiteKey::cases());
    return array_values(array_filter($siteKeys, static fn ($value): bool => is_string($value) && in_array($value, $valid, TRUE)));
  }

}
