<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Domain;

use Drupal\Core\State\StateInterface;

/**
 * Manages the neutral state list of active purposes.
 *
 * Contrato neutro exposto para o tema e outros consumidores sem exigir
 * consultas a Domain, DomainNegotiator ou DomainPurposeManager.
 */
final class ActivePurposesState {

  public const STATE_KEY = 'aculta_portal.active_purposes';

  public function __construct(
    private readonly StateInterface $state,
    private readonly DomainPurposeManager $domainPurposeManager,
  ) {}

  /**
   * Rebuilds and persists the list of active purpose keys.
   *
   * @return list<string>
   *   The active purpose keys.
   */
  public function rebuild(): array {
    $active = [];
    foreach (array_keys(DomainSurfaceMap::DOMAIN_IDS) as $purpose) {
      $domain = $this->domainPurposeManager->getDomain($purpose);
      $url = $this->domainPurposeManager->pathUrl($purpose, '/');
      if ($domain !== NULL && $domain->status() && $url !== NULL) {
        $active[] = $purpose;
      }
    }
    $this->state->set(self::STATE_KEY, $active);
    return $active;
  }

  /**
   * Retrieves the cached list of active purpose keys from state.
   *
   * @return list<string>
   */
  public function get(): array {
    $purposes = $this->state->get(self::STATE_KEY);
    return is_array($purposes) ? array_values(array_filter($purposes, 'is_string')) : [];
  }

}
