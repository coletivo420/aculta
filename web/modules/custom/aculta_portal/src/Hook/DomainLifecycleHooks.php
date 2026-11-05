<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Domain\ActivePurposesState;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Responds to Domain entity lifecycle changes to keep active purposes state fresh.
 */
final class DomainLifecycleHooks {

  public function __construct(
    #[Autowire(service: 'aculta_portal.active_purposes_state')]
    private readonly ActivePurposesState $activePurposesState,
  ) {}

  #[Hook('domain_insert')]
  public function onDomainInsert(EntityInterface $entity): void {
    $this->activePurposesState->rebuild();
  }

  #[Hook('domain_update')]
  public function onDomainUpdate(EntityInterface $entity): void {
    $this->activePurposesState->rebuild();
  }

  #[Hook('domain_delete')]
  public function onDomainDelete(EntityInterface $entity): void {
    $this->activePurposesState->rebuild();
  }

}
