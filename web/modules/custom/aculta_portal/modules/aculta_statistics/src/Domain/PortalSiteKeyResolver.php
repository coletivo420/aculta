<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Domain;

use Drupal\aculta_portal\Domain\DomainSiteManager;

/**
 * Lê o purpose do `aculta_portal`, quando ele está instalado.
 *
 * O gerenciador do portal é opcional: sem ele, o purpose é NULL e os pedidos ficam como `sem_dominio`.
 */
final class PortalSiteKeyResolver implements SiteKeyResolverInterface {

  public function __construct(
    private readonly ?DomainSiteManager $purposes = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function currentPurpose(): ?string {
    return $this->purposes?->getCurrentSiteKey()?->value;
  }

}
